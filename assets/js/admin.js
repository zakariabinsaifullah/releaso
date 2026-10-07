/**
 * Releaso admin, every Releaso screen: copy buttons, secret reveal, busy sync buttons, the import
 * drop zone, and on Settings the colour picker and the unsaved-changes bar. No dependencies
 * (wp-color-picker only on Settings).
 */
(() => {
    const i18n = window.releasoAdmin || {};

    // Copy: a button next to a readonly input, or a [data-releaso-copy] element holding the text.
    const copyText = async text => {
        try {
            await navigator.clipboard.writeText(text);
            return true;
        } catch (e) {
            const area = document.createElement('textarea');
            area.value = text;
            area.style.position = 'fixed';
            area.style.opacity = '0';
            document.body.append(area);
            area.select();
            const ok = document.execCommand('copy');
            area.remove();
            return ok;
        }
    };

    const flash = (el, label) => {
        el.classList.add('is-copied');
        const original = el.dataset.label ?? el.textContent;
        el.dataset.label = original;
        if (label && el.tagName === 'BUTTON') {
            el.textContent = label;
        }
        clearTimeout(el.releasoTimer);
        el.releasoTimer = setTimeout(() => {
            el.classList.remove('is-copied');
            if (el.tagName === 'BUTTON') {
                el.textContent = original;
            }
        }, 1600);
    };

    const copy = async el => {
        const input = el.tagName === 'BUTTON' ? el.parentElement.querySelector('input') : null;
        const text = input ? input.value : el.textContent.trim();
        if (await copyText(text)) {
            flash(el, i18n.copied);
        }
    };

    document.addEventListener('click', event => {
        const el = event.target.closest('[data-releaso-copy]');
        if (el) {
            event.preventDefault();
            copy(el);
        }
    });
    document.addEventListener('keydown', event => {
        const el = event.target.closest?.('code[data-releaso-copy]');
        if (el && (event.key === 'Enter' || event.key === ' ')) {
            event.preventDefault();
            copy(el);
        }
    });

    // Sync buttons spin until the page reloads.
    document.querySelectorAll('[data-releaso-busy]').forEach(button => {
        button.addEventListener('click', () => button.classList.add('is-busy'));
    });

    // Password fields get a Show / Hide button (only useful while typing a new value).
    document.querySelectorAll('input[data-releaso-secret]').forEach(input => {
        const wrap = document.createElement('span');
        wrap.className = 'releaso-secret';
        input.before(wrap);
        wrap.append(input);
        const toggle = document.createElement('button');
        toggle.type = 'button';
        toggle.className = 'button';
        toggle.textContent = i18n.show || 'Show';
        toggle.setAttribute('aria-controls', input.id);
        toggle.addEventListener('click', () => {
            const hidden = input.type === 'password';
            input.type = hidden ? 'text' : 'password';
            toggle.textContent = hidden ? i18n.hide || 'Hide' : i18n.show || 'Show';
        });
        wrap.append(toggle);
    });

    // Import drop zone: name of the chosen file, drag-over state.
    document.querySelectorAll('[data-releaso-dropzone]').forEach(zone => {
        const input = zone.querySelector('input[type="file"]');
        const text = zone.querySelector('[data-releaso-dropzone-text]');
        const empty = text.textContent;
        const show = () => {
            const file = input.files && input.files[0];
            zone.classList.toggle('has-file', !!file);
            text.textContent = file ? `${file.name} · ${Math.max(1, Math.round(file.size / 1024))} KB` : empty;
        };
        input.addEventListener('change', show);
        ['dragenter', 'dragover'].forEach(type =>
            zone.addEventListener(type, () => zone.classList.add('is-over'))
        );
        ['dragleave', 'drop'].forEach(type =>
            zone.addEventListener(type, () => zone.classList.remove('is-over'))
        );
    });

    // Settings: colour picker and the "unsaved changes" save bar.
    const form = document.querySelector('[data-releaso-settings]');
    if (!form) {
        return;
    }
    const bar = form.querySelector('.releaso-savebar');
    const status = form.querySelector('[data-releaso-save-status]');
    const snapshot = () => new URLSearchParams(new FormData(form)).toString();
    let initial = '';
    let submitting = false;

    const update = () => {
        const dirty = snapshot() !== initial;
        bar.classList.toggle('is-dirty', dirty);
        status.textContent = dirty ? i18n.unsaved : i18n.saved;
    };

    // Live preview: layout and accent colour.
    const preview = document.querySelector('[data-releaso-preview]');
    const layout = form.querySelector('[name$="[default_layout]"]');
    const setAccent = color => {
        if (preview) {
            preview.style.setProperty('--mini-accent', /^#[0-9a-f]{3,6}$/i.test(color || '') ? color : '');
        }
    };
    layout?.addEventListener('change', () => {
        if (preview) {
            preview.className = preview.className.replace(/releaso-mini--\S+/, `releaso-mini--${layout.value}`);
        }
    });

    if (window.jQuery && window.jQuery.fn.wpColorPicker) {
        window.jQuery('.releaso-color').wpColorPicker({
            change: (event, ui) => {
                setAccent(ui.color.toString());
                setTimeout(update);
            },
            clear: () => {
                setAccent('');
                setTimeout(update);
            },
        });
    } else {
        form.querySelector('.releaso-color')?.addEventListener('input', event => setAccent(event.target.value));
    }

    // Section menu follows the scroll position.
    const links = [...document.querySelectorAll('[data-releaso-section-link]')];
    if (links.length && 'IntersectionObserver' in window) {
        const activate = id =>
            links.forEach(link => link.classList.toggle('is-active', link.hash === `#${id}`));
        const observer = new IntersectionObserver(
            entries => {
                const visible = entries.filter(e => e.isIntersecting).sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top);
                if (visible[0]) {
                    activate(visible[0].target.id);
                }
            },
            { rootMargin: '-120px 0px -55% 0px' }
        );
        links.forEach(link => {
            const section = document.querySelector(link.hash);
            if (section) {
                observer.observe(section);
            }
        });
        activate(links[0].hash.slice(1));
        // short pages can't scroll the last section to the top: trust the click and the page end
        links.forEach(link => link.addEventListener('click', () => setTimeout(() => activate(link.hash.slice(1)), 400)));
        window.addEventListener('scroll', () => {
            if (window.innerHeight + window.scrollY >= document.documentElement.scrollHeight - 4) {
                activate(links[links.length - 1].hash.slice(1));
            }
        }, { passive: true });
    }

    // Change types: add / remove custom types, live colour and name, reset built-ins.
    const types = form.querySelector('[data-releaso-types]');
    if (types) {
        const list = types.querySelector('[data-releaso-type-rows]');
        const template = types.querySelector('[data-releaso-type-template]');
        let next = 1;

        // Recolour the row, and every pill of that type on the page (the live preview too).
        const paint = row => {
            const color = row.querySelector('[data-releaso-type-color]').value;
            row.style.setProperty('--c', color);
            const key = row.dataset.key;
            if (key) {
                document.querySelectorAll(`.releaso-pill--${CSS.escape(key)}`).forEach(pill => pill.style.setProperty('--c', color));
            }
            const reset = row.querySelector('[data-releaso-type-reset]');
            if (reset) {
                const label = row.querySelector('[data-releaso-type-label]').value;
                reset.hidden = label === reset.dataset.label && color.toLowerCase() === reset.dataset.color;
            }
        };
        const rename = row => {
            const label = row.querySelector('[data-releaso-type-label]').value.trim();
            row.querySelector('[data-releaso-type-preview]').textContent = label || i18n.preview || 'Preview';
        };

        list.querySelectorAll('.releaso-type').forEach(paint);

        list.addEventListener('input', event => {
            const row = event.target.closest('.releaso-type');
            if (!row) {
                return;
            }
            if (event.target.matches('[data-releaso-type-color]')) {
                paint(row);
            } else if (event.target.matches('[data-releaso-type-label]')) {
                rename(row);
                paint(row);
            }
        });

        list.addEventListener('click', event => {
            const row = event.target.closest('.releaso-type');
            const reset = event.target.closest('[data-releaso-type-reset]');
            if (reset) {
                row.querySelector('[data-releaso-type-label]').value = reset.dataset.label;
                row.querySelector('[data-releaso-type-color]').value = reset.dataset.color;
                rename(row);
                paint(row);
                update();
            } else if (event.target.closest('[data-releaso-type-remove]')) {
                const focusTarget = row.nextElementSibling?.querySelector('input[type="text"]') || types.querySelector('[data-releaso-type-add]');
                row.remove();
                focusTarget.focus();
                update();
            }
        });

        // New types get a fresh colour from a soft palette, so they don't all start grey.
        const palette = ['#c2185b', '#0f8b8d', '#b7791f', '#5b6ee1', '#2e7d32', '#6d4c41', '#d81b60'];
        types.querySelector('[data-releaso-type-add]').addEventListener('click', () => {
            list.insertAdjacentHTML('beforeend', template.innerHTML.replace(/__i__/g, `${Date.now().toString(36)}${next++}`));
            const row = list.lastElementChild;
            row.classList.add('is-new');
            row.querySelector('[data-releaso-type-color]').value = palette[(list.children.length - 1) % palette.length];
            paint(row);
            row.querySelector('[data-releaso-type-label]').focus();
            update();
        });

        // Enter in a type's fields must not submit the whole settings form.
        list.addEventListener('keydown', event => {
            if (event.key === 'Enter' && event.target.matches('input[type="text"]')) {
                event.preventDefault();
            }
        });
    }

    initial = snapshot();
    status.textContent = i18n.saved;
    form.addEventListener('input', update);
    form.addEventListener('change', update);
    form.addEventListener('submit', () => {
        submitting = true;
    });
    window.addEventListener('beforeunload', event => {
        if (!submitting && snapshot() !== initial) {
            event.preventDefault();
            event.returnValue = '';
        }
    });
})();

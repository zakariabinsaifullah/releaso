/**
 * Release editor: add, remove and reorder the change rows, and switch to pasting readme lines.
 */
(() => {
    const rows = document.querySelector('[data-releaso-rows]');
    const template = document.querySelector('[data-releaso-template]');
    if (!rows || !template) {
        return;
    }

    let next = rows.children.length;

    const add = () => {
        const html = template.innerHTML.replace(/__i__/g, String(next++));
        rows.insertAdjacentHTML('beforeend', html);
        const row = rows.lastElementChild;
        // a new change usually has the same type as the one before it
        const previous = row.previousElementSibling?.querySelector('select');
        if (previous) {
            row.querySelector('select').value = previous.value;
        }
        row.dataset.type = row.querySelector('select').value;
        row.classList.add('is-new');
        row.querySelector('input.large-text').focus();
    };

    document.querySelector('[data-releaso-add]').addEventListener('click', add);

    rows.addEventListener('click', event => {
        const row = event.target.closest('tr');
        if (!row) {
            return;
        }
        if (event.target.closest('[data-releaso-remove]')) {
            if (rows.children.length > 1) {
                row.remove();
            } else {
                row.querySelectorAll('input').forEach(input => (input.value = ''));
            }
        } else if (event.target.closest('[data-releaso-up]') && row.previousElementSibling) {
            row.previousElementSibling.before(row);
        } else if (event.target.closest('[data-releaso-down]') && row.nextElementSibling) {
            row.nextElementSibling.after(row);
        }
    });

    // the row's colour stripe follows its type
    rows.addEventListener('change', event => {
        if (event.target.matches('.releaso-col-type select')) {
            event.target.closest('tr').dataset.type = event.target.value;
        }
    });

    // Enter in a change adds the next one instead of saving the post
    rows.addEventListener('keydown', event => {
        if (event.key === 'Enter' && event.target.matches('input.large-text')) {
            event.preventDefault();
            if (event.target.closest('tr') === rows.lastElementChild) {
                add();
            } else {
                event.target.closest('tr').nextElementSibling.querySelector('input.large-text').focus();
            }
        }
    });

    const toggle = document.querySelector('[data-releaso-paste-toggle]');
    const paste = document.querySelector('[data-releaso-paste]');
    toggle?.addEventListener('click', () => {
        const open = paste.hidden;
        paste.hidden = !open;
        toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
        if (open) {
            paste.querySelector('textarea').focus();
        }
    });
})();

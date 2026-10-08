/**
 * Releaso front end: product tabs, type filters, search, "show older" and copy-a-version link.
 * Progressive enhancement: without this script every product and release is simply listed.
 * No dependencies.
 */
( () => {
	const i18n = Object.assign(
		{ copied: 'Link copied', inAddress: 'Link in the address bar' },
		window.releasoI18n || {}
	);

	const init = ( root ) => {
		root.classList.add( 'is-js' );

		const tabs = [ ...root.querySelectorAll( '.releaso__tab' ) ];
		const panels = [ ...root.querySelectorAll( '.releaso__panel' ) ];
		const filters = [ ...root.querySelectorAll( '.releaso__filter' ) ];
		const search = root.querySelector( '[data-search]' );
		let type = 'all';
		let query = '';

		const activePanel = () =>
			panels.find( ( panel ) => ! panel.hidden ) || panels[ 0 ];

		// counts on the filter buttons follow the visible product
		const count = () => {
			const panel = activePanel();
			filters.forEach( ( button ) => {
				const t = button.dataset.type;
				if ( t === 'all' ) {
					return;
				}
				const n = panel.querySelectorAll(
					`.releaso__item[data-type="${ t }"]`
				).length;
				button.hidden = n === 0;
				const badge = button.querySelector( '[data-count]' );
				if ( badge ) {
					badge.textContent = n;
				}
			} );
		};

		const highlight = ( el, terms ) => {
			if ( ! el.dataset.html ) {
				el.dataset.html = el.innerHTML;
			}
			el.innerHTML = el.dataset.html;
			if ( ! terms ) {
				return;
			}
			const walker = document.createTreeWalker(
				el,
				window.NodeFilter.SHOW_TEXT
			);
			const nodes = [];
			while ( walker.nextNode() ) {
				nodes.push( walker.currentNode );
			}
			const safe = terms.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' );
			const re = new RegExp( `(${ safe })`, 'gi' );
			const has = new RegExp( safe, 'i' );
			nodes.forEach( ( node ) => {
				if ( ! has.test( node.nodeValue ) ) {
					return;
				}
				const span = document.createElement( 'span' );
				span.innerHTML = node.nodeValue
					.replace(
						/[&<>]/g,
						( c ) =>
							( { '&': '&amp;', '<': '&lt;', '>': '&gt;' } )[ c ]
					)
					.replace( re, '<mark>$1</mark>' );
				node.replaceWith( ...span.childNodes );
			} );
		};

		const apply = () => {
			const panel = activePanel();
			const terms = query.trim().toLowerCase();
			root.classList.toggle(
				'is-searching',
				terms !== '' || type !== 'all'
			);
			let shown = 0;

			panel
				.querySelectorAll( '.releaso__release' )
				.forEach( ( release ) => {
					const version = release
						.querySelector( '.releaso__version' )
						.textContent.toLowerCase();
					let visible = 0;
					release
						.querySelectorAll( '.releaso__group' )
						.forEach( ( group ) => {
							let inGroup = 0;
							group
								.querySelectorAll( '.releaso__item' )
								.forEach( ( item ) => {
									const text =
										item.querySelector( '.releaso__text' );
									const match =
										( type === 'all' ||
											item.dataset.type === type ) &&
										( ! terms ||
											item.textContent
												.toLowerCase()
												.includes( terms ) ||
											version.includes( terms ) );
									item.classList.toggle(
										'is-filtered-out',
										! match
									);
									highlight(
										text,
										match &&
											terms &&
											! version.includes( terms )
											? query.trim()
											: ''
									);
									inGroup += match ? 1 : 0;
								} );
							group.classList.toggle(
								'is-filtered-out',
								inGroup === 0
							);
							visible += inGroup;
						} );
					release.classList.toggle(
						'is-filtered-out',
						visible === 0
					);
					// while filtering, older releases are searched too
					release.classList.toggle(
						'is-hit',
						( terms !== '' || type !== 'all' ) && visible > 0
					);
					shown += visible;
				} );

			const none = panel.querySelector( '.releaso__nomatch' );
			if ( none ) {
				none.hidden = shown > 0;
			}
		};

		const select = ( tab ) => {
			tabs.forEach( ( t ) => {
				const on = t === tab;
				t.setAttribute( 'aria-selected', on ? 'true' : 'false' );
				t.tabIndex = on ? 0 : -1;
			} );
			panels.forEach( ( panel ) => {
				panel.hidden = panel.dataset.product !== tab.dataset.product;
			} );
			count();
			apply();
		};

		tabs.forEach( ( tab, i ) => {
			tab.addEventListener( 'click', () => select( tab ) );
			tab.addEventListener( 'keydown', ( event ) => {
				const step = { ArrowRight: 1, ArrowLeft: -1 }[ event.key ];
				if ( step ) {
					event.preventDefault();
					const next =
						tabs[ ( i + step + tabs.length ) % tabs.length ];
					next.focus();
					select( next );
				}
			} );
		} );

		filters.forEach( ( button ) =>
			button.addEventListener( 'click', () => {
				type = button.dataset.type;
				filters.forEach( ( b ) =>
					b.setAttribute(
						'aria-pressed',
						b === button ? 'true' : 'false'
					)
				);
				apply();
			} )
		);

		let timer = 0;
		search?.addEventListener( 'input', () => {
			clearTimeout( timer );
			timer = setTimeout( () => {
				query = search.value;
				apply();
			}, 120 );
		} );

		root.querySelectorAll( '[data-more]' ).forEach( ( button ) =>
			button.addEventListener( 'click', () => {
				const panel = button.closest( '.releaso__panel' );
				const older = [
					...panel.querySelectorAll( '.releaso__release.is-older' ),
				];
				older.forEach( ( release ) =>
					release.classList.add( 'is-shown' )
				);
				button.closest( '.releaso__more-wrap' ).remove();
				older[ 0 ]
					?.querySelector( '.releaso__version' )
					?.focus( { preventScroll: true } );
			} )
		);

		root.querySelectorAll( '[data-copy]' ).forEach( ( button ) => {
			const status = button.querySelector( '.releaso__copied' );
			button.addEventListener( 'click', async () => {
				const url = `${ window.location.href.split( '#' )[ 0 ] }#${ button.dataset.copy }`;
				try {
					await navigator.clipboard.writeText( url );
					status.textContent = i18n.copied;
				} catch {
					window.history.replaceState(
						null,
						'',
						`#${ button.dataset.copy }`
					);
					status.textContent = i18n.inAddress;
				}
				setTimeout( () => {
					status.textContent = '';
				}, 1600 );
			} );
		} );

		// a link to a version opens its product and reveals it
		const openHash = () => {
			const target =
				window.location.hash &&
				root.querySelector(
					window.location.hash.replace( /[^#\w-]/g, '' )
				);
			if (
				! target ||
				! target.classList.contains( 'releaso__release' )
			) {
				return;
			}
			const panel = target.closest( '.releaso__panel' );
			const tab = tabs.find(
				( t ) => t.dataset.product === panel.dataset.product
			);
			if ( tab ) {
				select( tab );
			}
			target.classList.add( 'is-shown' );
			window.requestAnimationFrame( () =>
				target.scrollIntoView( { block: 'start' } )
			);
		};

		// keyboard: Home/End on tabs
		tabs.forEach( ( tab ) =>
			tab.addEventListener( 'keydown', ( event ) => {
				const to = { Home: tabs[ 0 ], End: tabs[ tabs.length - 1 ] }[
					event.key
				];
				if ( to ) {
					event.preventDefault();
					to.focus();
					select( to );
				}
			} )
		);

		if ( tabs.length ) {
			select(
				tabs.find(
					( t ) => t.getAttribute( 'aria-selected' ) === 'true'
				) || tabs[ 0 ]
			);
		} else {
			count();
		}
		openHash();
		window.addEventListener( 'hashchange', openHash );
	};

	const boot = () =>
		document
			.querySelectorAll( '[data-releaso]:not(.is-js)' )
			.forEach( ( root ) => {
				try {
					init( root );
				} catch ( e ) {
					// A broken changelog must never break the page: it stays a plain list.
					root.classList.remove( 'is-js' );
					window.console?.error?.( 'Releaso:', e );
				}
			} );
	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();

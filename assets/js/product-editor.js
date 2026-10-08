/**
 * Product editor: shows the fields of the chosen source, conditional fields (show_if),
 * and "Load from a file" for the pasted-changelog source. No dependencies.
 */
( () => {
	const root = document.querySelector( '[data-releaso-source]' );
	if ( ! root ) {
		return;
	}

	const groups = [ ...root.querySelectorAll( '[data-source-fields]' ) ];

	// Only the chosen source's fields are shown and submitted.
	const showSource = ( key ) => {
		groups.forEach( ( group ) => {
			const on = group.dataset.sourceFields === key;
			group.hidden = ! on;
			group
				.querySelectorAll( 'input, select, textarea' )
				.forEach( ( el ) => {
					el.disabled = ! on;
				} );
		} );
		conditions();
	};

	// Fields with data-show-if='{"mode":"file"}' follow a sibling field's value.
	const conditions = () => {
		root.querySelectorAll( '[data-show-if]' ).forEach( ( field ) => {
			let rules = {};
			try {
				rules = JSON.parse( field.dataset.showIf );
			} catch {
				return;
			}
			const group = field.closest( '[data-source-fields]' );
			const visible = Object.entries( rules ).every(
				( [ key, want ] ) => {
					const input = group.querySelector( `[name$="[${ key }]"]` );
					if ( ! input ) {
						return true;
					}
					const value =
						input.type === 'checkbox' ? input.checked : input.value;
					return Array.isArray( want )
						? want.includes( value )
						: value === want;
				}
			);
			field.hidden = ! visible;
		} );
	};

	root.addEventListener( 'change', ( event ) => {
		if ( event.target.name === 'releaso_source' ) {
			showSource( event.target.value );
		} else {
			conditions();
		}
	} );

	// A message next to the "Load from a file" button ('' clears it).
	const showFileError = ( input, message ) => {
		const holder = input.closest( '.releaso-field__file' );
		let note = holder.querySelector( '[data-releaso-file-error]' );
		if ( ! note ) {
			note = document.createElement( 'span' );
			note.className = 'releaso-field__error';
			note.setAttribute( 'role', 'alert' );
			note.dataset.releasoFileError = '';
			holder.append( note );
		}
		note.textContent = message;
	};

	// Load a local file into the textarea (read in the browser; saved with the product).
	root.querySelectorAll( '[data-load-into]' ).forEach( ( input ) => {
		input.addEventListener( 'change', () => {
			const file = input.files && input.files[ 0 ];
			const target = document.getElementById( input.dataset.loadInto );
			if ( ! file || ! target ) {
				return;
			}
			if ( file.size > 2 * 1024 * 1024 ) {
				showFileError(
					input,
					window.releasoAdmin?.tooBig ||
						'The file is larger than 2 MB.'
				);
				return;
			}
			showFileError( input, '' );
			const reader = new window.FileReader();
			reader.onload = () => {
				target.value = String( reader.result || '' );
				const format = target
					.closest( '[data-source-fields]' )
					.querySelector( '[name$="[format]"]' );
				const ext = file.name.split( '.' ).pop().toLowerCase();
				if ( format && format.value === 'auto' ) {
					format.value =
						{ json: 'json', md: 'markdown', markdown: 'markdown' }[
							ext
						] || 'auto';
				}
				target.focus();
			};
			reader.readAsText( file );
			input.value = '';
		} );
	} );

	const checked = root.querySelector( '[name="releaso_source"]:checked' );
	showSource( checked ? checked.value : groups[ 0 ]?.dataset.sourceFields );
} )();

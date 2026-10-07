/**
 * Releaso Changelog block: server-rendered, configured in the sidebar.
 */
import { registerBlockType } from '@wordpress/blocks';
import { megaphone } from '@wordpress/icons';

import metadata from './block.json';
import Edit from './edit';
import './editor.scss';

registerBlockType( metadata.name, {
	icon: megaphone,
	edit: Edit,
	save: () => null,
} );

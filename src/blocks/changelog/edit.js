/**
 * Block editor UI: product picker, layout and display options, with a live server preview.
 */
import { __, sprintf } from '@wordpress/i18n';
import {
	useBlockProps,
	InspectorControls,
	__experimentalColorGradientSettingsDropdown as ColorGradientSettingsDropdown,
	__experimentalUseMultipleOriginColorsAndGradients as useMultipleOriginColorsAndGradients,
} from '@wordpress/block-editor';
import {
	__experimentalToolsPanel as ToolsPanel,
	PanelBody,
	CheckboxControl,
	RangeControl,
	SelectControl,
	ToggleControl,
	Disabled,
	Notice,
	Button,
	Spinner,
	Placeholder,
	FormTokenField,
} from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import ServerSideRender from '@wordpress/server-side-render';

const config = window.releasoBlock || { layouts: {}, types: {}, typeColors: {}, perPage: 6 };

/**
 * Parts of the changelog that take their own colour (keys match Renderer::color_elements()).
 */
const ELEMENTS = [
	[ 'accent', __( 'Accent', 'releaso' ) ],
	[ 'product', __( 'Product name', 'releaso' ) ],
	[ 'version', __( 'Version', 'releaso' ) ],
	[ 'date', __( 'Date', 'releaso' ) ],
	[ 'title', __( 'Release title', 'releaso' ) ],
	[ 'text', __( 'Change text', 'releaso' ) ],
	[ 'latest', __( '"Latest" badge', 'releaso' ) ],
	[ 'tag', __( 'Tag badge', 'releaso' ) ],
	[ 'card', __( 'Card background', 'releaso' ) ],
	[ 'border', __( 'Borders', 'releaso' ) ],
];

/**
 * Published products from the REST API.
 *
 * @return {{products: Array|null, error: string}} Products (null while loading).
 */
function useProducts() {
	const [ products, setProducts ] = useState( null );
	const [ error, setError ] = useState( '' );

	useEffect( () => {
		let live = true;
		apiFetch( { path: '/releaso/v1/products' } )
			.then( ( rows ) => live && setProducts( rows ) )
			.catch( ( e ) => {
				if ( live ) {
					setProducts( [] );
					setError( e?.message || __( 'Products could not be loaded.', 'releaso' ) );
				}
			} );
		return () => {
			live = false;
		};
	}, [] );

	return { products, error };
}

export default function Edit( { attributes, setAttributes, clientId } ) {
	const { products: chosen, layout, perPage, limit, types, showFilters, showSearch, showHeader, expanded, colors, typeColors } = attributes;
	const { products, error } = useProducts();
	const blockProps = useBlockProps();
	const colorSettings = useMultipleOriginColorsAndGradients();

	// Sets or clears one key of an object attribute (colors, typeColors).
	const setColor = ( attribute, key, value ) => {
		const next = { ...attributes[ attribute ] };
		if ( value ) {
			next[ key ] = value;
		} else {
			delete next[ key ];
		}
		setAttributes( { [ attribute ]: next } );
	};

	const colorItem = ( attribute, key, label, current ) => ( {
		label,
		colorValue: current[ key ],
		onColorChange: ( value ) => setColor( attribute, key, value ),
		resetAllFilter: ( all ) => ( { ...all, [ attribute ]: {} } ),
		isShownByDefault: true,
		enableAlpha: true,
		clearable: true,
	} );

	const toggleProduct = ( slug ) =>
		setAttributes( {
			products: chosen.includes( slug ) ? chosen.filter( ( p ) => p !== slug ) : [ ...chosen, slug ],
		} );

	const typeLabels = config.types || {};
	const typeKeysByLabel = Object.fromEntries( Object.entries( typeLabels ).map( ( [ key, label ] ) => [ label, key ] ) );

	const colorControls = (
		<>
			<InspectorControls group="color">
				<ColorGradientSettingsDropdown
					__experimentalIsRenderedInSidebar
					settings={ ELEMENTS.map( ( [ key, label ] ) => colorItem( 'colors', key, label, colors ) ) }
					panelId={ clientId }
					{ ...colorSettings }
					gradients={ [] }
					disableCustomGradients
				/>
			</InspectorControls>
			<InspectorControls group="styles">
				<ToolsPanel
					label={ __( 'Change type colors', 'releaso' ) }
					panelId={ clientId }
					resetAll={ () => setAttributes( { typeColors: {} } ) }
					className="color-block-support-panel"
					hasInnerWrapper
					__experimentalFirstVisibleItemClass="first"
					__experimentalLastVisibleItemClass="last"
				>
					<div className="color-block-support-panel__inner-wrapper">
						<ColorGradientSettingsDropdown
							__experimentalIsRenderedInSidebar
							settings={ Object.entries( typeLabels ).map( ( [ key, label ] ) =>
								colorItem( 'typeColors', key, label, typeColors )
							) }
							panelId={ clientId }
							{ ...colorSettings }
							gradients={ [] }
							disableCustomGradients
						/>
					</div>
				</ToolsPanel>
			</InspectorControls>
		</>
	);

	const inspector = (
		<InspectorControls>
			<PanelBody title={ __( 'Products', 'releaso' ) }>
				{ products === null && <Spinner /> }
				{ error && (
					<Notice status="error" isDismissible={ false }>
						{ error }
					</Notice>
				) }
				{ products?.length === 0 && ! error && (
					<Notice status="info" isDismissible={ false }>
						{ __( 'No products yet.', 'releaso' ) }{ ' ' }
						<a href={ config.addProduct } target="_blank" rel="noreferrer">
							{ __( 'Add one', 'releaso' ) }
						</a>
					</Notice>
				) }
				{ products?.map( ( product ) => (
					<CheckboxControl
						__nextHasNoMarginBottom
						key={ product.slug }
						label={ product.name }
						help={
							product.latest
								? /* translators: 1: version, 2: number of releases */
								  sprintf( __( 'v%1$s · %2$d releases', 'releaso' ), product.latest, product.releases )
								: __( 'No releases yet', 'releaso' )
						}
						checked={ ! chosen.length || chosen.includes( product.slug ) }
						onChange={ () => toggleProduct( product.slug ) }
					/>
				) ) }
				{ products?.length > 1 && (
					<p className="components-base-control__help">
						{ __( 'None ticked shows every product. Several show as tabs.', 'releaso' ) }
					</p>
				) }
			</PanelBody>

			<PanelBody title={ __( 'Display', 'releaso' ) }>
				<SelectControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Layout', 'releaso' ) }
					value={ layout }
					options={ [
						{ value: '', label: __( 'Default (from settings)', 'releaso' ) },
						...Object.entries( config.layouts ).map( ( [ value, label ] ) => ( { value, label } ) ),
					] }
					onChange={ ( value ) => setAttributes( { layout: value } ) }
				/>
				<RangeControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Releases before "Show older"', 'releaso' ) }
					min={ 1 }
					max={ 50 }
					value={ perPage || config.perPage }
					onChange={ ( value ) => setAttributes( { perPage: value || 0 } ) }
					disabled={ expanded }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Show every release at once', 'releaso' ) }
					checked={ expanded }
					onChange={ ( value ) => setAttributes( { expanded: value } ) }
				/>
				<RangeControl
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Maximum releases', 'releaso' ) }
					help={ __( '0 shows all.', 'releaso' ) }
					min={ 0 }
					max={ 100 }
					value={ limit }
					onChange={ ( value ) => setAttributes( { limit: value || 0 } ) }
				/>
				<FormTokenField
					__nextHasNoMarginBottom
					__next40pxDefaultSize
					label={ __( 'Only these change types', 'releaso' ) }
					value={ types.map( ( key ) => typeLabels[ key ] || key ) }
					suggestions={ Object.values( typeLabels ) }
					onChange={ ( tokens ) =>
						setAttributes( {
							types: tokens.map( ( token ) => typeKeysByLabel[ token ] ).filter( Boolean ),
						} )
					}
					__experimentalExpandOnFocus
					__experimentalShowHowTo={ false }
				/>
				<p className="components-base-control__help">{ __( 'Empty shows every type.', 'releaso' ) }</p>
			</PanelBody>

			<PanelBody title={ __( 'Toolbar', 'releaso' ) } initialOpen={ false }>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Product name and stats', 'releaso' ) }
					checked={ showHeader }
					onChange={ ( value ) => setAttributes( { showHeader: value } ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Type filters', 'releaso' ) }
					checked={ showFilters }
					onChange={ ( value ) => setAttributes( { showFilters: value } ) }
				/>
				<ToggleControl
					__nextHasNoMarginBottom
					label={ __( 'Search', 'releaso' ) }
					checked={ showSearch }
					onChange={ ( value ) => setAttributes( { showSearch: value } ) }
				/>
			</PanelBody>

			<PanelBody title={ __( 'Releases', 'releaso' ) } initialOpen={ false }>
				<p>{ __( 'Releases come from each product\'s source, plus the ones you write.', 'releaso' ) }</p>
				<Button variant="secondary" href={ config.addRelease } target="_blank">
					{ __( 'Write a release', 'releaso' ) }
				</Button>
			</PanelBody>
		</InspectorControls>
	);

	if ( products?.length === 0 && ! error ) {
		return (
			<div { ...blockProps }>
				{ inspector }
				{ colorControls }
				<Placeholder icon="megaphone" label={ __( 'Changelog', 'releaso' ) } instructions={ __( 'Add a product to show its changelog: WordPress.org, GitHub, a readme or releases you write.', 'releaso' ) }>
					<Button variant="primary" href={ config.addProduct } target="_blank">
						{ __( 'Add a product', 'releaso' ) }
					</Button>
				</Placeholder>
			</div>
		);
	}

	return (
		<div { ...blockProps }>
			{ inspector }
			{ colorControls }
			<Disabled>
				<ServerSideRender block="releaso/changelog" attributes={ attributes } skipBlockSupportAttributes />
			</Disabled>
		</div>
	);
}

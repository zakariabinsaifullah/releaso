<?php
/**
 * Changelog wrapper: product tabs, type filters, search, and one panel per product.
 *
 * Override by copying to yourtheme/releaso/changelog.php.
 *
 * @package Releaso
 *
 * @var array                         $panels Each: product (Product), releases (ReleaseCollection).
 * @var array                         $args   Display arguments (layout, per_page, filters, search, header, expanded …).
 * @var string                        $uid    Unique ID prefix.
 * @var array                         $types  Change types shown: key => [ label, color ].
 * @var \Releaso\Frontend\View        $view   Helpers.
 */

use Releaso\Support\Template;

defined( 'ABSPATH' ) || exit;

$releaso_tabs    = count( $panels ) > 1;
$releaso_classes = array( 'releaso', 'releaso--' . $args['layout'] );
if ( $args['className'] ) {
	$releaso_classes[] = $args['className'];
}
// A chosen element colour must beat theme link and heading colours: see "chosen colours" in releaso.css.
foreach ( array_keys( (array) ( $args['colors'] ?? array() ) ) as $releaso_element ) {
	$releaso_classes[] = 'releaso--has-' . $releaso_element . '-color';
}
?>
<div class="<?php echo esc_attr( implode( ' ', $releaso_classes ) ); ?>"<?php echo ! empty( $args['style'] ) ? ' style="' . esc_attr( $args['style'] ) . '"' : ''; ?> data-releaso data-per-page="<?php echo (int) $args['per_page']; ?>">
	<?php if ( $releaso_tabs || $args['filters'] || $args['search'] ) : ?>
		<div class="releaso__bar">
			<?php if ( $releaso_tabs ) : ?>
				<div class="releaso__tabs" role="tablist" aria-label="<?php esc_attr_e( 'Products', 'releaso' ); ?>">
					<?php foreach ( $panels as $releaso_i => $releaso_panel ) : ?>
						<?php
						$releaso_product = $releaso_panel['product'];
						$releaso_latest  = $releaso_panel['releases']->latest();
						?>
						<button type="button" class="releaso__tab" role="tab" id="<?php echo esc_attr( "{$uid}-tab-{$releaso_product->slug}" ); ?>" aria-controls="<?php echo esc_attr( "{$uid}-{$releaso_product->slug}" ); ?>" aria-selected="<?php echo 0 === $releaso_i ? 'true' : 'false'; ?>" data-product="<?php echo esc_attr( $releaso_product->slug ); ?>">
							<span><?php echo esc_html( $releaso_product->name ); ?></span>
							<?php if ( $releaso_latest ) : ?>
								<span class="releaso__tab-ver">v<?php echo esc_html( $releaso_latest->version ); ?></span>
							<?php endif; ?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( $args['filters'] || $args['search'] ) : ?>
				<div class="releaso__tools">
					<?php if ( $args['filters'] && count( $types ) > 1 ) : ?>
						<div class="releaso__filters" role="group" aria-label="<?php esc_attr_e( 'Show changes of type', 'releaso' ); ?>">
							<button type="button" class="releaso__filter" data-type="all" aria-pressed="true"><?php esc_html_e( 'All', 'releaso' ); ?></button>
							<?php foreach ( $types as $releaso_key => $releaso_type ) : ?>
								<button type="button" class="releaso__filter releaso__filter--<?php echo esc_attr( $releaso_key ); ?>" data-type="<?php echo esc_attr( $releaso_key ); ?>" aria-pressed="false"><i aria-hidden="true"></i><?php echo esc_html( $releaso_type['label'] ); ?> <span class="releaso__count" data-count></span></button>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
					<?php if ( $args['search'] ) : ?>
						<label class="releaso__search">
							<span class="screen-reader-text"><?php esc_html_e( 'Search the changelog', 'releaso' ); ?></span>
							<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" aria-hidden="true"><circle cx="11" cy="11" r="6.5"/><path d="M16 16l4.5 4.5"/></svg>
							<input type="search" placeholder="<?php esc_attr_e( 'Search changes…', 'releaso' ); ?>" data-search>
						</label>
					<?php endif; ?>
				</div>
			<?php endif; ?>
		</div>
	<?php endif; ?>

	<?php foreach ( $panels as $releaso_panel ) : ?>
		<?php
		$releaso_product  = $releaso_panel['product'];
		$releaso_releases = $releaso_panel['releases'];
		?>
		<section class="releaso__panel" id="<?php echo esc_attr( "{$uid}-{$releaso_product->slug}" ); ?>" data-product="<?php echo esc_attr( $releaso_product->slug ); ?>"<?php echo $releaso_tabs ? ' role="tabpanel" aria-labelledby="' . esc_attr( "{$uid}-tab-{$releaso_product->slug}" ) . '"' : ''; ?>>
			<?php if ( $args['header'] ) : ?>
				<header class="releaso__summary">
					<h2 class="releaso__product"><?php echo esc_html( $releaso_product->name ); ?></h2>
					<?php if ( count( $releaso_releases ) ) : ?>
						<p class="releaso__stats">
							<?php
							echo esc_html(
								/* translators: %d: number of releases */
								sprintf( _n( '%d release', '%d releases', count( $releaso_releases ), 'releaso' ), count( $releaso_releases ) )
								. ' · ' .
								/* translators: %d: number of changes */
								sprintf( _n( '%d change', '%d changes', $releaso_releases->change_count(), 'releaso' ), $releaso_releases->change_count() )
							);
							?>
						</p>
					<?php endif; ?>
					<?php if ( $releaso_product->link ) : ?>
						<a class="releaso__link" href="<?php echo esc_url( $releaso_product->link ); ?>"><?php esc_html_e( 'Get it', 'releaso' ); ?> <span aria-hidden="true">→</span></a>
					<?php endif; ?>
				</header>
			<?php endif; ?>

			<?php if ( ! count( $releaso_releases ) ) : ?>
				<p class="releaso__empty"><?php esc_html_e( 'No releases to show yet.', 'releaso' ); ?></p>
			<?php else : ?>
				<?php
				// The layout template prints the releases; the "show older" button and no-match note follow.
				$releaso_layout = Template::render(
					'layouts/' . $args['layout'],
					array(
						'product'  => $releaso_product,
						'releases' => $releaso_releases,
						'args'     => $args,
						'view'     => $view,
					)
				);
				echo $releaso_layout; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- templates escape their output.
				$releaso_older = $args['expanded'] ? 0 : max( 0, count( $releaso_releases ) - $args['per_page'] );
				?>
				<?php if ( $releaso_older ) : ?>
					<div class="releaso__more-wrap">
						<button type="button" class="releaso__more" data-more aria-expanded="false">
							<?php
							/* translators: %d: number of releases */
							echo esc_html( sprintf( _n( 'Show %d older release', 'Show %d older releases', $releaso_older, 'releaso' ), $releaso_older ) );
							?>
						</button>
					</div>
				<?php endif; ?>
				<p class="releaso__nomatch" hidden><?php esc_html_e( 'No changes match your filter.', 'releaso' ); ?></p>
			<?php endif; ?>
		</section>
	<?php endforeach; ?>
</div>

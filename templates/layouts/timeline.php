<?php
/**
 * Timeline layout: version and date on a rail, a card per release.
 *
 * Override by copying to yourtheme/releaso/layouts/timeline.php.
 *
 * @package Releaso
 *
 * @var \Releaso\Model\Product           $product  Product.
 * @var \Releaso\Model\ReleaseCollection $releases Releases, newest first.
 * @var array                            $args     Display arguments.
 * @var \Releaso\Frontend\View           $view     Helpers.
 */

use Releaso\Support\Template;

defined( 'ABSPATH' ) || exit;
?>
<ol class="releaso__timeline">
	<?php foreach ( $releases as $releaso_r => $releaso_release ) : ?>
		<?php
		$releaso_anchor = $view->anchor( $product, $releaso_release );
		$releaso_date   = $view->date( $releaso_release );
		$releaso_older  = ! $args['expanded'] && $releaso_r >= $args['per_page'];
		?>
		<li class="releaso__release<?php echo 0 === $releaso_r ? ' is-latest' : ''; ?><?php echo $releaso_older ? ' is-older' : ''; ?>" id="<?php echo esc_attr( $releaso_anchor ); ?>">
			<div class="releaso__rail">
				<a class="releaso__version" href="#<?php echo esc_attr( $releaso_anchor ); ?>">v<?php echo esc_html( $releaso_release->version ); ?></a>
				<?php if ( $releaso_date ) : ?>
					<time datetime="<?php echo esc_attr( $releaso_release->date ); ?>"><?php echo esc_html( $releaso_date ); ?></time>
				<?php endif; ?>
			</div>
			<article class="releaso__card">
				<header class="releaso__card-head">
					<h3 class="releaso__title">
						<?php
						echo esc_html(
							'' !== $releaso_release->title
								? $releaso_release->title
								/* translators: %s: version */
								: sprintf( __( 'Version %s', 'releaso' ), $releaso_release->version )
						);
						?>
						<?php if ( 0 === $releaso_r ) : ?>
							<span class="releaso__latest"><?php esc_html_e( 'Latest', 'releaso' ); ?></span>
						<?php endif; ?>
					</h3>
					<ul class="releaso__summary-types" aria-label="<?php esc_attr_e( 'Changes in this release', 'releaso' ); ?>">
						<?php foreach ( $releaso_release->counts() as $releaso_type => $releaso_count ) : ?>
							<li class="releaso__pill releaso__pill--<?php echo esc_attr( $releaso_type ); ?>"><?php echo esc_html( $view->count_label( $releaso_type, $releaso_count ) ); ?></li>
						<?php endforeach; ?>
					</ul>
					<button type="button" class="releaso__copy" data-copy="<?php echo esc_attr( $releaso_anchor ); ?>" aria-label="<?php echo esc_attr( sprintf( /* translators: %s: version */ __( 'Copy link to version %s', 'releaso' ), $releaso_release->version ) ); ?>">
						<svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10 14a4.5 4.5 0 0 0 6.4 0l3-3a4.5 4.5 0 0 0-6.4-6.4l-1 1M14 10a4.5 4.5 0 0 0-6.4 0l-3 3a4.5 4.5 0 0 0 6.4 6.4l1-1"/></svg>
						<span class="releaso__copied" aria-live="polite"></span>
					</button>
				</header>
				<?php
				echo Template::render( 'parts/changes', array( 'release' => $releaso_release, 'view' => $view ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
				?>
			</article>
		</li>
	<?php endforeach; ?>
</ol>

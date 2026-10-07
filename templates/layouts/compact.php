<?php
/**
 * Compact layout: a plain list of releases, for sidebars, docs pages and narrow columns.
 *
 * Override by copying to yourtheme/releaso/layouts/compact.php.
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
<div class="releaso__list">
	<?php foreach ( $releases as $releaso_r => $releaso_release ) : ?>
		<?php
		$releaso_anchor = $view->anchor( $product, $releaso_release );
		$releaso_date   = $view->date( $releaso_release );
		$releaso_older  = ! $args['expanded'] && $releaso_r >= $args['per_page'];
		?>
		<article class="releaso__release releaso__entry<?php echo 0 === $releaso_r ? ' is-latest' : ''; ?><?php echo $releaso_older ? ' is-older' : ''; ?>" id="<?php echo esc_attr( $releaso_anchor ); ?>">
			<header class="releaso__entry-head">
				<h3 class="releaso__entry-title">
					<a class="releaso__version" href="#<?php echo esc_attr( $releaso_anchor ); ?>">v<?php echo esc_html( $releaso_release->version ); ?></a>
					<?php if ( '' !== $releaso_release->title ) : ?>
						<span class="releaso__entry-name"><?php echo esc_html( $releaso_release->title ); ?></span>
					<?php endif; ?>
					<?php if ( 0 === $releaso_r ) : ?>
						<span class="releaso__latest"><?php esc_html_e( 'Latest', 'releaso' ); ?></span>
					<?php endif; ?>
				</h3>
				<?php if ( $releaso_date ) : ?>
					<time datetime="<?php echo esc_attr( $releaso_release->date ); ?>"><?php echo esc_html( $releaso_date ); ?></time>
				<?php endif; ?>
			</header>
			<?php
			echo Template::render( 'parts/changes', array( 'release' => $releaso_release, 'view' => $view ) ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped, WordPress.Arrays.ArrayDeclarationSpacing.AssociativeArrayFound
			?>
		</article>
	<?php endforeach; ?>
</div>

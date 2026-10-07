<?php
/**
 * A release's notes and its changes grouped by type. Shared by the layouts.
 *
 * Override by copying to yourtheme/releaso/parts/changes.php.
 *
 * @package Releaso
 *
 * @var \Releaso\Model\Release $release Release.
 * @var \Releaso\Frontend\View $view    Helpers.
 */

defined( 'ABSPATH' ) || exit;
?>
<?php if ( '' !== trim( $release->notes ) ) : ?>
	<div class="releaso__notes"><?php echo $view->notes( $release ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Formatter escapes. ?></div>
<?php endif; ?>

<?php foreach ( $view->groups( $release ) as $releaso_type => $releaso_changes ) : ?>
	<div class="releaso__group" data-type="<?php echo esc_attr( $releaso_type ); ?>">
		<h4 class="releaso__type releaso__type--<?php echo esc_attr( $releaso_type ); ?>"><?php echo esc_html( $view->label( $releaso_type ) ); ?></h4>
		<ul class="releaso__items">
			<?php foreach ( $releaso_changes as $releaso_change ) : ?>
				<li class="releaso__item" data-type="<?php echo esc_attr( $releaso_type ); ?>">
					<?php if ( '' !== $releaso_change->tag ) : ?>
						<span class="releaso__tag"><?php echo esc_html( $releaso_change->tag ); ?></span>
					<?php endif; ?>
					<span class="releaso__text"><?php echo $view->text( $releaso_change ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Formatter escapes. ?></span>
				</li>
			<?php endforeach; ?>
		</ul>
	</div>
<?php endforeach; ?>

<?php if ( '' !== $release->url ) : ?>
	<p class="releaso__more-link"><a href="<?php echo esc_url( $release->url ); ?>" rel="noopener"><?php esc_html_e( 'Full release notes', 'releaso' ); ?> <span aria-hidden="true">→</span></a></p>
<?php endif; ?>

<?php
/**
 * Renders the source settings fields declared by each source.
 *
 * @package Releaso
 */

namespace Releaso\Admin;

/**
 * Field renderer.
 */
class Fields {

	const MASK = '••••••••';

	/**
	 * Prints a field row.
	 *
	 * @param string $name  Input name.
	 * @param string $id    Input ID.
	 * @param array  $field Field definition.
	 * @param mixed  $value Current value.
	 * @return void
	 */
	public static function render( $name, $id, array $field, $value ) {
		$type     = $field['type'] ?? 'text';
		$required = ! empty( $field['required'] );
		$show_if  = ! empty( $field['show_if'] ) ? wp_json_encode( $field['show_if'] ) : '';
		?>
		<div class="releaso-field releaso-field--<?php echo esc_attr( $type ); ?>"<?php echo $show_if ? ' data-show-if="' . esc_attr( $show_if ) . '"' : ''; ?>>
			<?php if ( 'checkbox' === $type ) : ?>
				<label class="releaso-toggle">
					<input type="checkbox" id="<?php echo esc_attr( $id ); ?>" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( ! empty( $value ) ); ?>>
					<span class="releaso-toggle__track" aria-hidden="true"></span>
					<span class="releaso-toggle__label"><?php echo esc_html( $field['label'] ?? '' ); ?></span>
				</label>
			<?php else : ?>
				<label class="releaso-field__label" for="<?php echo esc_attr( $id ); ?>">
					<?php echo esc_html( $field['label'] ?? '' ); ?>
					<?php if ( $required ) : ?>
						<span class="releaso-required" aria-hidden="true">*</span>
					<?php endif; ?>
				</label>
				<?php
				switch ( $type ) {
					case 'select':
						echo '<select id="' . esc_attr( $id ) . '" name="' . esc_attr( $name ) . '" data-field-key="' . esc_attr( $field['key'] ?? '' ) . '">';
						foreach ( (array) ( $field['options'] ?? array() ) as $option => $label ) {
							printf(
								'<option value="%1$s" %2$s>%3$s</option>',
								esc_attr( $option ),
								selected( (string) $value, (string) $option, false ),
								esc_html( $label )
							);
						}
						echo '</select>';
						break;

					case 'textarea':
						if ( ! empty( $field['file'] ) ) {
							printf(
								'<p class="releaso-field__file"><label class="button button-small"><span class="dashicons dashicons-upload" aria-hidden="true"></span><input type="file" accept="%1$s" data-load-into="%2$s" hidden>%3$s</label> <span class="description">%4$s</span></p>',
								esc_attr( $field['file'] ),
								esc_attr( $id ),
								esc_html__( 'Load from a file…', 'releaso' ),
								esc_html__( 'Reads the file into the box below. Nothing is uploaded until you save.', 'releaso' )
							);
						}
						printf(
							'<textarea id="%1$s" name="%2$s" rows="14" class="large-text code" placeholder="%3$s" spellcheck="false">%4$s</textarea>',
							esc_attr( $id ),
							esc_attr( $name ),
							esc_attr( $field['placeholder'] ?? '' ),
							esc_textarea( (string) $value )
						);
						break;

					case 'password':
						printf(
							'<input type="password" id="%1$s" name="%2$s" value="%3$s" class="regular-text" placeholder="%4$s" autocomplete="new-password" data-releaso-secret>',
							esc_attr( $id ),
							esc_attr( $name ),
							esc_attr( '' !== (string) $value ? self::MASK : '' ),
							esc_attr( $field['placeholder'] ?? '' )
						);
						break;

					default:
						printf(
							'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text" placeholder="%5$s">',
							esc_attr( 'url' === $type ? 'url' : 'text' ),
							esc_attr( $id ),
							esc_attr( $name ),
							esc_attr( (string) $value ),
							esc_attr( $field['placeholder'] ?? '' )
						);
				}
				?>
			<?php endif; ?>
			<?php if ( ! empty( $field['help'] ) ) : ?>
				<p class="description"><?php echo esc_html( $field['help'] ); ?></p>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Keeps saved secrets when the masked value comes back unchanged.
	 *
	 * @param array $fields   Field definitions.
	 * @param array $input    Submitted values.
	 * @param array $previous Saved values.
	 * @return array
	 */
	public static function keep_secrets( array $fields, array $input, array $previous ) {
		foreach ( $fields as $key => $field ) {
			if ( 'password' === ( $field['type'] ?? '' ) && isset( $input[ $key ] ) && self::MASK === $input[ $key ] ) {
				$input[ $key ] = $previous[ $key ] ?? '';
			}
		}
		return $input;
	}
}

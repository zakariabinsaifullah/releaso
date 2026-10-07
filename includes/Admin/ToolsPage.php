<?php
/**
 * Releaso → Import / Export: import a changelog file as editable releases, export changelogs,
 * and sync every product at once.
 *
 * @package Releaso
 */

namespace Releaso\Admin;

use Releaso\Data\Exporter;
use Releaso\Data\Importer;
use Releaso\Data\PostTypes;
use Releaso\Parser\ParserFactory;
use Releaso\Plugin;

/**
 * Tools page.
 */
class ToolsPage {

	const SLUG = 'releaso-tools';

	/**
	 * Plugin.
	 *
	 * @var Plugin
	 */
	private $plugin;

	/**
	 * Constructor.
	 *
	 * @param Plugin $plugin Plugin.
	 */
	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	/**
	 * Hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_post_releaso_import', array( $this, 'handle_import' ) );
		add_action( 'admin_post_releaso_export', array( $this, 'handle_export' ) );
	}

	/**
	 * Menu entry.
	 *
	 * @return void
	 */
	public function menu() {
		add_submenu_page( Admin::MENU, __( 'Import / Export', 'releaso' ), __( 'Import / Export', 'releaso' ), Plugin::capability(), self::SLUG, array( $this, 'page' ), 20 );
	}

	/**
	 * Products as id => name.
	 *
	 * @return array<int,string>
	 */
	private function products() {
		$out = array();
		foreach ( $this->plugin->products->all() as $product ) {
			$out[ $product->id ] = $product->name;
		}
		return $out;
	}

	/**
	 * The page.
	 *
	 * @return void
	 */
	public function page() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			return;
		}
		$products = $this->products();
		$preview  = get_transient( 'releaso_preview_' . get_current_user_id() );
		if ( is_array( $preview ) ) {
			delete_transient( 'releaso_preview_' . get_current_user_id() );
		}
		?>
		<div class="wrap releaso-wrap">
			<div class="releaso-page-head">
				<div>
					<h1><?php esc_html_e( 'Import / Export', 'releaso' ); ?></h1>
					<p class="releaso-page-head__lead"><?php esc_html_e( 'Move changelogs in and out of Releaso, or refresh every product at once.', 'releaso' ); ?></p>
				</div>
			</div>
			<hr class="wp-header-end">

			<?php if ( is_array( $preview ) ) : ?>
				<div class="releaso-card releaso-preview">
					<h2><span class="releaso-badge"><?php esc_html_e( 'Preview', 'releaso' ); ?></span> <?php esc_html_e( 'Nothing was saved yet', 'releaso' ); ?></h2>
					<p>
						<?php
						printf(
							/* translators: 1: new, 2: updated, 3: skipped */
							esc_html__( '%1$d new, %2$d would be replaced, %3$d already exist and would be skipped.', 'releaso' ),
							count( $preview['created'] ),
							count( $preview['updated'] ),
							count( $preview['skipped'] )
						);
						?>
					</p>
					<table class="widefat striped">
						<thead><tr><th><?php esc_html_e( 'Version', 'releaso' ); ?></th><th><?php esc_html_e( 'Date', 'releaso' ); ?></th><th><?php esc_html_e( 'Changes', 'releaso' ); ?></th><th><?php esc_html_e( 'Import', 'releaso' ); ?></th></tr></thead>
						<tbody>
							<?php foreach ( $preview['rows'] as $row ) : ?>
								<tr>
									<td><strong>v<?php echo esc_html( $row['version'] ); ?></strong><?php echo $row['title'] ? ' — ' . esc_html( $row['title'] ) : ''; ?></td>
									<td><?php echo esc_html( $row['date'] ? $row['date'] : '—' ); ?></td>
									<td><?php echo esc_html( $row['summary'] ); ?></td>
									<td><?php echo esc_html( $row['action'] ); ?></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p class="description"><?php esc_html_e( 'Untick "Preview only" below and import again to save.', 'releaso' ); ?></p>
				</div>
			<?php endif; ?>

			<div class="releaso-tools">
				<div class="releaso-card releaso-tools__main">
					<header class="releaso-card__head">
						<span class="releaso-card__icon dashicons dashicons-download" aria-hidden="true"></span>
						<h2><?php esc_html_e( 'Import a changelog', 'releaso' ); ?></h2>
					</header>
					<p><?php esc_html_e( 'Turns a readme.txt, CHANGELOG.md or JSON file into releases you can edit one by one under Releaso → Releases. To keep a file in sync automatically instead, set it as the product\'s source.', 'releaso' ); ?></p>
					<?php if ( ! $products ) : ?>
						<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . PostTypes::PRODUCT ) ); ?>"><?php esc_html_e( 'Add a product first', 'releaso' ); ?></a></p>
					<?php else : ?>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
							<input type="hidden" name="action" value="releaso_import">
							<?php wp_nonce_field( 'releaso_import' ); ?>
							<div class="releaso-import">
								<div class="releaso-import__options">
								<p class="releaso-field">
									<label class="releaso-field__label" for="releaso-import-product"><?php esc_html_e( 'Into product', 'releaso' ); ?></label>
									<select id="releaso-import-product" name="product">
										<?php foreach ( $products as $id => $name ) : ?>
											<option value="<?php echo (int) $id; ?>"><?php echo esc_html( $name ); ?></option>
										<?php endforeach; ?>
									</select>
								</p>
								<p class="releaso-field">
									<label class="releaso-field__label" for="releaso-import-file"><?php esc_html_e( 'File', 'releaso' ); ?></label>
									<span class="releaso-dropzone" data-releaso-dropzone>
										<span class="dashicons dashicons-media-text" aria-hidden="true"></span>
										<span class="releaso-dropzone__text" data-releaso-dropzone-text><?php esc_html_e( 'Drop a file here or click to browse', 'releaso' ); ?></span>
										<span class="releaso-dropzone__hint">readme.txt · CHANGELOG.md · .json</span>
										<input type="file" id="releaso-import-file" name="file" accept=".txt,.md,.markdown,.json">
									</span>
								</p>
								<p class="releaso-field">
									<label class="releaso-field__label" for="releaso-import-format"><?php esc_html_e( 'Format', 'releaso' ); ?></label>
									<select id="releaso-import-format" name="format">
										<option value="auto"><?php esc_html_e( 'Detect automatically', 'releaso' ); ?></option>
										<option value="readme">readme.txt</option>
										<option value="markdown">Markdown</option>
										<option value="json">JSON</option>
									</select>
								</p>
								<fieldset class="releaso-field releaso-toggles">
									<legend class="screen-reader-text"><?php esc_html_e( 'Import options', 'releaso' ); ?></legend>
									<label class="releaso-toggle"><input type="checkbox" name="overwrite" value="1"><span class="releaso-toggle__track" aria-hidden="true"></span><span class="releaso-toggle__label"><?php esc_html_e( 'Replace releases that already exist for the same version', 'releaso' ); ?></span></label>
									<label class="releaso-toggle"><input type="checkbox" name="draft" value="1"><span class="releaso-toggle__track" aria-hidden="true"></span><span class="releaso-toggle__label"><?php esc_html_e( 'Import as drafts', 'releaso' ); ?></span></label>
									<label class="releaso-toggle"><input type="checkbox" name="dry_run" value="1" checked><span class="releaso-toggle__track" aria-hidden="true"></span><span class="releaso-toggle__label"><?php esc_html_e( 'Preview only (save nothing)', 'releaso' ); ?></span></label>
								</fieldset>
								</div>
								<div class="releaso-import__paste">
								<p class="releaso-field">
									<label class="releaso-field__label" for="releaso-import-text"><?php esc_html_e( '…or paste it', 'releaso' ); ?></label>
									<textarea id="releaso-import-text" name="content" rows="14" class="large-text code" spellcheck="false" placeholder="## [1.2.0] - 2026-09-29&#10;### Added&#10;- New Hover Slider designs"></textarea>
								</p>
								</div>
							</div>
							<?php submit_button( __( 'Import', 'releaso' ), 'primary', 'submit', false ); ?>
						</form>
					<?php endif; ?>
				</div>

				<div class="releaso-tools__side">
					<div class="releaso-card">
						<header class="releaso-card__head">
							<span class="releaso-card__icon dashicons dashicons-upload" aria-hidden="true"></span>
							<h2><?php esc_html_e( 'Export', 'releaso' ); ?></h2>
						</header>
						<p><?php esc_html_e( 'Download the full changelog (source and written releases together). JSON imports straight back into Releaso on another site.', 'releaso' ); ?></p>
						<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
							<input type="hidden" name="action" value="releaso_export">
							<?php wp_nonce_field( 'releaso_export' ); ?>
							<p class="releaso-field">
								<label class="releaso-field__label" for="releaso-export-product"><?php esc_html_e( 'Product', 'releaso' ); ?></label>
								<select id="releaso-export-product" name="product">
									<option value="0"><?php esc_html_e( 'All products', 'releaso' ); ?></option>
									<?php foreach ( $products as $id => $name ) : ?>
										<option value="<?php echo (int) $id; ?>"><?php echo esc_html( $name ); ?></option>
									<?php endforeach; ?>
								</select>
							</p>
							<p class="releaso-field">
								<label class="releaso-field__label" for="releaso-export-format"><?php esc_html_e( 'Format', 'releaso' ); ?></label>
								<select id="releaso-export-format" name="format">
									<option value="json"><?php esc_html_e( 'JSON (re-importable)', 'releaso' ); ?></option>
									<option value="markdown"><?php esc_html_e( 'Markdown (CHANGELOG.md)', 'releaso' ); ?></option>
									<option value="readme"><?php esc_html_e( 'readme.txt changelog section', 'releaso' ); ?></option>
								</select>
							</p>
							<?php submit_button( __( 'Download', 'releaso' ), 'secondary', 'submit', false ); ?>
						</form>
					</div>

					<div class="releaso-card">
						<header class="releaso-card__head">
							<span class="releaso-card__icon dashicons dashicons-update" aria-hidden="true"></span>
							<h2><?php esc_html_e( 'Sync', 'releaso' ); ?></h2>
						</header>
						<p><?php esc_html_e( 'Products sync in the background. Sync every product now, for example after publishing a release elsewhere.', 'releaso' ); ?></p>
						<p><a class="button" href="<?php echo esc_url( ProductScreen::sync_url( 0 ) ); ?>" data-releaso-busy><span class="dashicons dashicons-update" aria-hidden="true"></span><?php esc_html_e( 'Sync all products now', 'releaso' ); ?></a></p>
						<p class="description">
							<?php
							$next = wp_next_scheduled( \Releaso\Cron\Scheduler::HOOK );
							echo $next
								/* translators: %s: time until the next run */
								? esc_html( sprintf( __( 'Next background check in %s.', 'releaso' ), human_time_diff( $next ) ) )
								: esc_html__( 'The background check is not scheduled; it will be on the next admin page load.', 'releaso' );
							?>
						</p>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handles the import form.
	 *
	 * @return void
	 */
	public function handle_import() {
		check_admin_referer( 'releaso_import' );
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to import.', 'releaso' ), 403 );
		}

		$back    = admin_url( Admin::MENU . '&page=' . self::SLUG );
		$product = $this->plugin->products->find( absint( $_POST['product'] ?? 0 ) );
		if ( ! $product ) {
			Admin::notice( esc_html__( 'Choose a product.', 'releaso' ), 'error' );
			wp_safe_redirect( $back );
			exit;
		}

		list( $content, $name ) = $this->submitted_content();
		if ( is_wp_error( $content ) ) {
			Admin::notice( esc_html( $content->get_error_message() ), 'error' );
			wp_safe_redirect( $back );
			exit;
		}

		$format   = sanitize_key( wp_unslash( $_POST['format'] ?? 'auto' ) );
		$importer = new Importer( $this->plugin->releases, $this->plugin->settings );
		$report   = $importer->import(
			$product->id,
			$content,
			array(
				'format'    => in_array( $format, ParserFactory::FORMATS, true ) ? $format : 'auto',
				'name'      => $name,
				'overwrite' => ! empty( $_POST['overwrite'] ),
				'status'    => ! empty( $_POST['draft'] ) ? 'draft' : 'publish',
				'dry_run'   => ! empty( $_POST['dry_run'] ),
			)
		);

		if ( is_wp_error( $report ) ) {
			Admin::notice( esc_html( $report->get_error_message() ), 'error' );
		} elseif ( ! empty( $_POST['dry_run'] ) ) {
			set_transient( 'releaso_preview_' . get_current_user_id(), $this->preview_rows( $report ), 5 * MINUTE_IN_SECONDS );
		} else {
			$message = sprintf(
				/* translators: 1: product, 2: created, 3: updated, 4: skipped */
				esc_html__( 'Imported into %1$s: %2$d new, %3$d replaced, %4$d skipped.', 'releaso' ),
				'<strong>' . esc_html( $product->name ) . '</strong>',
				count( $report['created'] ),
				count( $report['updated'] ),
				count( $report['skipped'] )
			);
			if ( $report['failed'] ) {
				/* translators: %s: versions */
				$message .= '<br>' . esc_html( sprintf( __( 'Failed: %s', 'releaso' ), implode( ', ', array_keys( $report['failed'] ) ) ) );
			}
			Admin::notice( $message, $report['failed'] ? 'warning' : 'success' );
			$back = admin_url( 'edit.php?post_type=' . PostTypes::RELEASE . '&releaso_product=' . $product->id );
		}

		wp_safe_redirect( $back );
		exit;
	}

	/**
	 * The uploaded file or pasted text.
	 *
	 * @return array [ content|WP_Error, file name ].
	 */
	private function submitted_content() {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- verified in handle_import().
		if ( ! empty( $_FILES['file']['tmp_name'] ) && UPLOAD_ERR_NO_FILE !== (int) ( $_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE ) ) { // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- cast to int.
			$file = $_FILES['file']; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			$name = sanitize_file_name( (string) $file['name'] );
			if ( UPLOAD_ERR_OK !== (int) $file['error'] || ! is_uploaded_file( $file['tmp_name'] ) ) {
				return array( new \WP_Error( 'releaso_upload', __( 'The upload failed.', 'releaso' ) ), '' );
			}
			if ( ! in_array( strtolower( pathinfo( $name, PATHINFO_EXTENSION ) ), array( 'txt', 'md', 'markdown', 'json' ), true ) ) {
				return array( new \WP_Error( 'releaso_upload', __( 'Upload a .txt, .md or .json file.', 'releaso' ) ), '' );
			}
			if ( (int) $file['size'] > Importer::MAX_BYTES ) {
				return array( new \WP_Error( 'releaso_upload', __( 'The file is larger than 2 MB.', 'releaso' ) ), '' );
			}
			$content = (string) file_get_contents( $file['tmp_name'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
			return array( wp_check_invalid_utf8( $content, true ), $name );
		}

		$content = isset( $_POST['content'] ) ? wp_check_invalid_utf8( wp_unslash( (string) $_POST['content'] ), true ) : ''; // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- parsed, never output raw.
		// phpcs:enable
		if ( '' === trim( $content ) ) {
			return array( new \WP_Error( 'releaso_empty', __( 'Choose a file or paste a changelog.', 'releaso' ) ), '' );
		}
		return array( $content, '' );
	}

	/**
	 * Dry-run report to table rows.
	 *
	 * @param array $report Report.
	 * @return array
	 */
	private function preview_rows( array $report ) {
		$actions = array();
		foreach ( array( 'created', 'updated', 'skipped' ) as $key ) {
			foreach ( $report[ $key ] as $version ) {
				$actions[ $version ] = $key;
			}
		}
		$labels = array(
			'created' => __( 'New', 'releaso' ),
			'updated' => __( 'Replace', 'releaso' ),
			'skipped' => __( 'Skip (exists)', 'releaso' ),
		);
		$rows   = array();
		foreach ( $report['releases'] as $release ) {
			$parts = array();
			foreach ( $release->counts() as $type => $n ) {
				$parts[] = $n . ' ' . strtolower( \Releaso\Support\ChangeTypes::label( $type ) );
			}
			$rows[] = array(
				'version' => $release->version,
				'title'   => $release->title,
				'date'    => $release->date,
				'summary' => $parts ? implode( ', ', $parts ) : '—',
				'action'  => $labels[ $actions[ $release->version ] ?? 'created' ],
			);
		}
		return array(
			'created' => $report['created'],
			'updated' => $report['updated'],
			'skipped' => $report['skipped'],
			'rows'    => $rows,
		);
	}

	/**
	 * Handles the export form: streams a download.
	 *
	 * @return void
	 */
	public function handle_export() {
		check_admin_referer( 'releaso_export' );
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to export.', 'releaso' ), 403 );
		}

		$format = sanitize_key( wp_unslash( $_POST['format'] ?? 'json' ) );
		$format = in_array( $format, Exporter::FORMATS, true ) ? $format : 'json';
		$id     = absint( $_POST['product'] ?? 0 );

		$products = $id ? array_filter( array( $this->plugin->products->find( $id ) ) ) : $this->plugin->products->all();
		$items    = array();
		foreach ( $products as $product ) {
			$items[] = array( $product, $this->plugin->changelog->releases( $product ) );
		}

		$body = ( new Exporter() )->export( $items, $format );
		$ext  = array(
			'json'     => 'json',
			'markdown' => 'md',
			'readme'   => 'txt',
		)[ $format ];
		$file = ( 1 === count( $products ) ? reset( $products )->slug : 'releaso' ) . '-changelog.' . $ext;

		nocache_headers();
		header( 'Content-Type: ' . ( 'json' === $format ? 'application/json' : 'text/plain' ) . '; charset=utf-8' );
		header( 'Content-Disposition: attachment; filename="' . sanitize_file_name( $file ) . '"' );
		echo $body; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- a file download, not HTML.
		exit;
	}
}

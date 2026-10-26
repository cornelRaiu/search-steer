<?php

namespace SearchSteer\Admin;

use SearchSteer\Import\Importer;

defined( 'ABSPATH' ) || exit;

final class ImportSection {

	public const ACTION       = 'mwtss_import';
	public const FIELD_SOURCE = 'mwtss_import_source';
	public const RESULT_COUNT = 'mwtss_imported';
	public const RESULT_TYPES = 'mwtss_imported_types';

	public static function add_hooks(): void {
		add_action( 'admin_post_' . self::ACTION, [ self::class, 'handle' ] );
	}

	public static function render(): void {
		self::render_result();

		$rows = [];
		foreach ( Importer::sources() as $source => $name ) {
			$count = count( Importer::find( $source ) );
			$notes = Importer::notes( $source );
			if ( $count || $notes ) {
				$rows[ $source ] = compact( 'name', 'count', 'notes' );
			}
		}
		?>
		<h2><?php esc_html_e( 'Import hidden posts', 'search-steer' ); ?></h2>
		<?php if ( ! $rows ) : ?>
			<p><?php esc_html_e( 'Search Exclude, Hide from Search and Hide Posts have no hidden posts on this site to import.', 'search-steer' ); ?></p>
			<?php
			return;
		endif;
		?>
		<p><?php esc_html_e( 'Hide the posts that another plugin hides from search. Its data is not changed, so you can import again, and then deactivate it.', 'search-steer' ); ?></p>
		<table class="form-table" role="presentation">
			<?php foreach ( $rows as $source => $row ) : ?>
				<tr>
					<th scope="row"><?php echo esc_html( $row['name'] ); ?></th>
					<td>
						<form action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" method="post">
							<input type="hidden" name="action" value="<?php echo esc_attr( self::ACTION ); ?>" />
							<input type="hidden" name="<?php echo esc_attr( self::FIELD_SOURCE ); ?>" value="<?php echo esc_attr( $source ); ?>" />
							<?php wp_nonce_field( self::ACTION ); ?>
							<p>
								<?php
								echo esc_html(
									sprintf(
										/* translators: %s: number of posts. */
										_n( '%s hidden post found.', '%s hidden posts found.', $row['count'], 'search-steer' ),
										number_format_i18n( $row['count'] )
									)
								);
								?>
							</p>
							<?php foreach ( $row['notes'] as $note ) : ?>
								<p class="description"><?php echo esc_html( $note ); ?></p>
							<?php endforeach; ?>
							<?php if ( $row['count'] ) : ?>
								<?php
								submit_button(
									/* translators: %s: plugin name. */
									sprintf( __( 'Import from %s', 'search-steer' ), $row['name'] ),
									'secondary',
									'submit',
									false
								);
								?>
							<?php endif; ?>
						</form>
					</td>
				</tr>
			<?php endforeach; ?>
		</table>
		<?php
	}

	public static function handle(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Sorry, you are not allowed to import hidden posts.', 'search-steer' ), 403 );
		}

		check_admin_referer( self::ACTION );

		$source = isset( $_POST[ self::FIELD_SOURCE ] ) ? sanitize_key( wp_unslash( $_POST[ self::FIELD_SOURCE ] ) ) : '';
		if ( ! isset( Importer::sources()[ $source ] ) ) {
			wp_die( esc_html__( 'Unknown plugin to import from.', 'search-steer' ), 400 );
		}

		$result = Importer::run( $source );

		wp_safe_redirect(
			add_query_arg(
				[
					'page'             => SettingsPage::PAGE,
					self::RESULT_COUNT => $result['imported'],
					self::RESULT_TYPES => implode( ',', $result['other_types'] ),
				],
				admin_url( 'options-general.php' )
			)
		);
		exit;
	}

	private static function render_result(): void {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only reads the result of an import to show it.
		if ( ! isset( $_GET[ self::RESULT_COUNT ] ) ) {
			return;
		}

		$count = absint( $_GET[ self::RESULT_COUNT ] );
		$types = isset( $_GET[ self::RESULT_TYPES ] ) ? array_filter( array_map( 'sanitize_key', explode( ',', sanitize_text_field( wp_unslash( $_GET[ self::RESULT_TYPES ] ) ) ) ) ) : [];
		// phpcs:enable

		$message = sprintf(
			/* translators: %s: number of posts. */
			_n( '%s post is now hidden from site search.', '%s posts are now hidden from site search.', $count, 'search-steer' ),
			number_format_i18n( $count )
		);
		if ( $types ) {
			$message .= ' ' . sprintf(
				/* translators: %s: comma-separated post type names. */
				__( 'Some are of types that are not chosen under Post types above (%s), so they stay in search until you choose those types.', 'search-steer' ),
				implode( ', ', $types )
			);
		}

		printf( '<div class="notice notice-success"><p>%s</p></div>', esc_html( $message ) );
	}
}

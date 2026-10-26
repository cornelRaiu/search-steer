<?php

namespace SearchSteer\Admin;

use SearchSteer\Plugin;
use SearchSteer\Settings;
use SearchSteer\Visibility\HiddenFlag;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class EditorFields {

	public const FIELD        = 'mwtss_hide_from_search';
	public const NONCE        = 'mwtss_hide_from_search_nonce';
	public const NONCE_ACTION = 'mwtss_save_hide_from_search';
	public const SCRIPT       = 'search-steer-editor';

	// An unticked checkbox is not sent at all. This field tells it apart from a form without it.
	public const FIELD_PRESENT = 'mwtss_hide_from_search_present';

	public static function add_hooks(): void {
		add_action( 'enqueue_block_editor_assets', [ self::class, 'enqueue_block_editor_script' ] );
		add_action( 'add_meta_boxes', [ self::class, 'add_meta_box' ] );
		add_action( 'save_post', [ self::class, 'save' ], 10, 2 );
	}

	public static function enqueue_block_editor_script(): void {
		$screen = get_current_screen();
		if ( ! $screen || ! in_array( $screen->post_type, Settings::post_types(), true ) ) {
			return;
		}

		wp_enqueue_script(
			self::SCRIPT,
			Plugin::url( 'assets/js/editor.js' ),
			[ 'wp-components', 'wp-data', 'wp-editor', 'wp-element', 'wp-i18n', 'wp-plugins' ],
			Plugin::VERSION,
			true
		);
		wp_add_inline_script(
			self::SCRIPT,
			'window.searchSteerEditor = ' . wp_json_encode( [ 'field' => HiddenFlag::REST_FIELD ] ) . ';',
			'before'
		);
		wp_set_script_translations( self::SCRIPT, 'search-steer' );
	}

	public static function add_meta_box( string $post_type ): void {
		if ( ! in_array( $post_type, Settings::post_types(), true ) ) {
			return;
		}

		add_meta_box(
			'search-steer',
			__( 'Site search', 'search-steer' ),
			[ self::class, 'render_meta_box' ],
			$post_type,
			'side',
			'default',
			// Classic editor only: the block editor shows the sidebar panel instead.
			[ '__back_compat_meta_box' => true ]
		);
	}

	public static function render_meta_box( WP_Post $post ): void {
		self::render_fields( HiddenFlag::is_hidden( $post->ID ) );
		echo '<p class="description">' . esc_html__( 'The post never shows in your site\'s search results. It stays reachable at its own address.', 'search-steer' ) . '</p>';
	}

	public static function render_fields( bool $checked ): void {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE, false );
		?>
		<input type="hidden" name="<?php echo esc_attr( self::FIELD_PRESENT ); ?>" value="1" />
		<label>
			<input type="checkbox" name="<?php echo esc_attr( self::FIELD ); ?>" value="1" <?php checked( $checked ); ?> />
			<?php esc_html_e( 'Hide from site search', 'search-steer' ); ?>
		</label>
		<?php
	}

	// Saves the classic editor box and the quick edit checkbox, which send the same fields.
	public static function save( int $post_id, WP_Post $post ): void {
		if ( ! isset( $_POST[ self::FIELD_PRESENT ], $_POST[ self::NONCE ] ) ) {
			return;
		}

		if ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE_ACTION ) ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! in_array( $post->post_type, Settings::post_types(), true ) ) {
			return;
		}

		HiddenFlag::set( $post_id, ! empty( $_POST[ self::FIELD ] ) );
	}
}

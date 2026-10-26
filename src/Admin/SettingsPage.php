<?php

namespace SearchSteer\Admin;

use SearchSteer\Keywords\Taxonomies;
use SearchSteer\Plugin;
use SearchSteer\Settings;

defined( 'ABSPATH' ) || exit;

final class SettingsPage {

	public const PAGE = 'mwtss-settings';

	public static function add_hooks(): void {
		add_action( 'admin_menu', [ self::class, 'add_page' ] );
		add_action( 'admin_init', [ self::class, 'register_setting' ] );
		add_filter( 'plugin_action_links_' . plugin_basename( Plugin::file() ), [ self::class, 'add_action_link' ] );
	}

	public static function add_page(): void {
		add_options_page(
			__( 'Search Steer', 'search-steer' ),
			__( 'Search Steer', 'search-steer' ),
			'manage_options',
			self::PAGE,
			[ self::class, 'render' ]
		);
	}

	public static function register_setting(): void {
		register_setting(
			self::PAGE,
			Settings::OPTION,
			[
				'type'              => 'array',
				'sanitize_callback' => [ Settings::class, 'sanitize' ],
				'default'           => Settings::defaults(),
				'show_in_rest'      => false,
			]
		);
	}

	public static function add_action_link( $links ) {
		if ( ! is_array( $links ) ) {
			return $links;
		}

		array_unshift(
			$links,
			sprintf(
				'<a href="%s">%s</a>',
				esc_url( admin_url( 'options-general.php?page=' . self::PAGE ) ),
				esc_html__( 'Settings', 'search-steer' )
			)
		);

		return $links;
	}

	public static function render(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = Settings::all();
		$name     = Settings::OPTION;
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<p>
				<?php esc_html_e( 'Add search keywords and stop keywords to a post in the editor sidebar, in quick edit or in bulk edit. A search that matches a keyword shows the post even when its title and content do not match. A search that matches a stop keyword hides the post even when its title or content match. To keep a post out of every search, turn on "Hide from site search" in the editor sidebar, in quick edit, or with the bulk action.', 'search-steer' ); ?>
			</p>
			<?php self::render_term_links( $settings['post_types'] ); ?>

			<form action="options.php" method="post">
				<?php settings_fields( self::PAGE ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Post types', 'search-steer' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Post types', 'search-steer' ); ?></legend>
								<?php // Sent so that unticking every post type saves an empty list. ?>
								<input type="hidden" name="<?php echo esc_attr( $name ); ?>[post_types][]" value="" />
								<?php foreach ( self::available_post_types() as $post_type => $label ) : ?>
									<label>
										<input type="checkbox" name="<?php echo esc_attr( $name ); ?>[post_types][]" value="<?php echo esc_attr( $post_type ); ?>" <?php checked( in_array( $post_type, $settings['post_types'], true ) ); ?> />
										<?php echo esc_html( $label ); ?>
									</label><br />
								<?php endforeach; ?>
								<p class="description"><?php esc_html_e( 'Posts of these types can have keywords and stop keywords.', 'search-steer' ); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Keyword matching', 'search-steer' ); ?></th>
						<td><?php self::render_match_field( 'keyword_match', $settings['keyword_match'], __( 'Keyword matching', 'search-steer' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Stop keyword matching', 'search-steer' ); ?></th>
						<td><?php self::render_match_field( 'stop_match', $settings['stop_match'], __( 'Stop keyword matching', 'search-steer' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'When both match', 'search-steer' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'When both match', 'search-steer' ); ?></legend>
								<label>
									<input type="radio" name="<?php echo esc_attr( $name ); ?>[precedence]" value="<?php echo esc_attr( Settings::PRECEDENCE_STOP ); ?>" <?php checked( Settings::PRECEDENCE_STOP, $settings['precedence'] ); ?> />
									<?php esc_html_e( 'Hide the post: stop keywords win', 'search-steer' ); ?>
								</label><br />
								<label>
									<input type="radio" name="<?php echo esc_attr( $name ); ?>[precedence]" value="<?php echo esc_attr( Settings::PRECEDENCE_KEYWORD ); ?>" <?php checked( Settings::PRECEDENCE_KEYWORD, $settings['precedence'] ); ?> />
									<?php esc_html_e( 'Show the post: keywords win', 'search-steer' ); ?>
								</label>
								<p class="description"><?php esc_html_e( 'For a search such as "refund coupon" on a post with the keyword refund and the stop keyword coupon.', 'search-steer' ); ?></p>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Order', 'search-steer' ); ?></th>
						<td><?php self::render_checkbox( 'boost', $settings['boost'], __( 'List posts that match by keyword first', 'search-steer' ), __( 'Applies when results are ordered by relevance, which is the default.', 'search-steer' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Where', 'search-steer' ); ?></th>
						<td>
							<fieldset>
								<legend class="screen-reader-text"><?php esc_html_e( 'Where', 'search-steer' ); ?></legend>
								<p><?php esc_html_e( 'Keywords always apply to your site\'s search page.', 'search-steer' ); ?></p>
								<?php self::render_checkbox( 'api_search', $settings['api_search'], __( 'Also apply them to REST API and AJAX searches', 'search-steer' ), __( 'Such as headless front ends, live search plugins and the link search in the editor.', 'search-steer' ) ); ?>
								<?php self::render_checkbox( 'admin_search', $settings['admin_search'], __( 'Also apply them to searches in the admin post lists', 'search-steer' ), '' ); ?>
							</fieldset>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Uninstall', 'search-steer' ); ?></th>
						<td><?php self::render_checkbox( 'delete_data', $settings['delete_data'], __( 'Delete all keywords, stop keywords, hidden flags and settings when the plugin is deleted', 'search-steer' ), '' ); ?></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<?php ImportSection::render(); ?>
		</div>
		<?php
	}

	private static function render_term_links( array $post_types ): void {
		if ( ! $post_types ) {
			return;
		}

		$post_type = in_array( 'post', $post_types, true ) ? 'post' : $post_types[0];
		$links     = [];
		foreach ( [ Taxonomies::KEYWORD, Taxonomies::STOP ] as $taxonomy ) {
			$object = get_taxonomy( $taxonomy );
			if ( $object ) {
				$links[] = sprintf(
					'<a href="%s">%s</a>',
					esc_url( admin_url( 'edit-tags.php?taxonomy=' . $taxonomy . '&post_type=' . $post_type ) ),
					esc_html( $object->labels->all_items )
				);
			}
		}

		if ( $links ) {
			echo '<p>' . implode( ' | ', $links ) . '</p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each link is escaped above.
		}
	}

	private static function render_match_field( string $key, string $value, string $legend ): void {
		$name = Settings::OPTION . '[' . $key . ']';
		?>
		<fieldset>
			<legend class="screen-reader-text"><?php echo esc_html( $legend ); ?></legend>
			<label>
				<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( Settings::MATCH_TERM ); ?>" <?php checked( Settings::MATCH_TERM, $value ); ?> />
				<?php esc_html_e( 'Whole words: "gift" matches "gift card" but not "gifts"', 'search-steer' ); ?>
			</label><br />
			<label>
				<input type="radio" name="<?php echo esc_attr( $name ); ?>" value="<?php echo esc_attr( Settings::MATCH_PARTIAL ); ?>" <?php checked( Settings::MATCH_PARTIAL, $value ); ?> />
				<?php esc_html_e( 'Partial words: "refund" also matches "refu" and "refunds"', 'search-steer' ); ?>
			</label>
		</fieldset>
		<?php
	}

	private static function render_checkbox( string $key, bool $value, string $label, string $description ): void {
		$name = Settings::OPTION . '[' . $key . ']';
		?>
		<input type="hidden" name="<?php echo esc_attr( $name ); ?>" value="0" />
		<label>
			<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $value ); ?> />
			<?php echo esc_html( $label ); ?>
		</label>
		<?php if ( '' !== $description ) : ?>
			<p class="description"><?php echo esc_html( $description ); ?></p>
		<?php else : ?>
			<br />
		<?php endif; ?>
		<?php
	}

	private static function available_post_types(): array {
		$choices = [];
		foreach ( get_post_types( [ 'public' => true ], 'objects' ) as $post_type => $object ) {
			if ( 'attachment' !== $post_type ) {
				$choices[ $post_type ] = $object->labels->name;
			}
		}

		return $choices;
	}
}

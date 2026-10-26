<?php

namespace SearchSteer\Admin;

use SearchSteer\Keywords\Taxonomies;
use SearchSteer\Plugin;
use SearchSteer\Settings;
use SearchSteer\Visibility\HiddenFlag;
use WP_Query;
use WP_Term;

defined( 'ABSPATH' ) || exit;

final class PostList {

	public const COLUMN        = 'mwtss_search';
	public const FILTER        = 'mwtss_filter';
	public const FILTER_TERM   = 'mwtss_term';
	public const SHOW_KEYWORDS = 'keywords';
	public const SHOW_STOP     = 'stop';
	public const SHOW_HIDDEN   = 'hidden';
	public const BULK_HIDE     = 'mwtss_hide';
	public const BULK_SHOW     = 'mwtss_show';
	public const NOTICE_COUNT  = 'mwtss_bulk_count';
	public const NOTICE_ACTION = 'mwtss_bulk_action';
	public const SCRIPT        = 'search-steer-quick-edit';

	public static function add_hooks(): void {
		add_action( 'admin_init', [ self::class, 'add_post_type_hooks' ] );
		add_action( 'restrict_manage_posts', [ self::class, 'render_filter' ] );
		add_action( 'pre_get_posts', [ self::class, 'filter_query' ] );
		add_action( 'quick_edit_custom_box', [ self::class, 'render_quick_edit' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ self::class, 'enqueue_script' ] );
		add_action( 'admin_notices', [ self::class, 'render_notice' ] );
		add_filter( 'removable_query_args', [ self::class, 'add_removable_query_args' ] );
	}

	public static function add_post_type_hooks(): void {
		foreach ( Settings::post_types() as $post_type ) {
			add_filter( "manage_{$post_type}_posts_columns", [ self::class, 'add_column' ] );
			add_action( "manage_{$post_type}_posts_custom_column", [ self::class, 'render_column' ], 10, 2 );
			add_filter( "bulk_actions-edit-{$post_type}", [ self::class, 'add_bulk_actions' ] );
			add_filter( "handle_bulk_actions-edit-{$post_type}", [ self::class, 'handle_bulk_action' ], 10, 3 );
		}
	}

	public static function add_column( $columns ) {
		if ( is_array( $columns ) ) {
			$columns[ self::COLUMN ] = __( 'Search', 'search-steer' );
		}

		return $columns;
	}

	public static function render_column( string $column, int $post_id ): void {
		if ( self::COLUMN !== $column ) {
			return;
		}

		$hidden = HiddenFlag::is_hidden( $post_id );
		$lines  = [];

		// Read by the quick edit script to tick the checkbox.
		printf( '<span class="search-steer-hidden-state" data-hidden="%s"></span>', $hidden ? '1' : '0' );

		if ( $hidden ) {
			$lines[] = '<strong>' . esc_html__( 'Hidden from search', 'search-steer' ) . '</strong>';
		}

		$labels = [
			Taxonomies::KEYWORD => __( 'Keywords:', 'search-steer' ),
			Taxonomies::STOP    => __( 'Stop:', 'search-steer' ),
		];
		foreach ( $labels as $taxonomy => $label ) {
			$terms = get_the_terms( $post_id, $taxonomy );
			if ( ! is_array( $terms ) || ! $terms ) {
				continue;
			}

			$links = [];
			foreach ( $terms as $term ) {
				$links[] = sprintf(
					'<a href="%s">%s</a>',
					esc_url(
						add_query_arg(
							[
								'post_type'       => get_post_type( $post_id ),
								self::FILTER_TERM => $term->term_id,
							],
							admin_url( 'edit.php' )
						)
					),
					esc_html( $term->name )
				);
			}
			$lines[] = esc_html( $label ) . ' ' . implode( ', ', $links );
		}

		if ( ! $lines ) {
			echo '<span aria-hidden="true">&#8212;</span><span class="screen-reader-text">' . esc_html__( 'No search settings', 'search-steer' ) . '</span>';
			return;
		}

		echo implode( '<br />', $lines ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each line is escaped above.
	}

	public static function render_filter( string $post_type ): void {
		if ( ! in_array( $post_type, Settings::post_types(), true ) ) {
			return;
		}

		$current = self::requested_filter();
		$choices = [
			''                  => __( 'All search settings', 'search-steer' ),
			self::SHOW_KEYWORDS => __( 'Has search keywords', 'search-steer' ),
			self::SHOW_STOP     => __( 'Has stop keywords', 'search-steer' ),
			self::SHOW_HIDDEN   => __( 'Hidden from search', 'search-steer' ),
		];
		?>
		<label for="search-steer-filter" class="screen-reader-text"><?php esc_html_e( 'Filter by search settings', 'search-steer' ); ?></label>
		<select name="<?php echo esc_attr( self::FILTER ); ?>" id="search-steer-filter">
			<?php foreach ( $choices as $value => $label ) : ?>
				<option value="<?php echo esc_attr( $value ); ?>" <?php selected( $current, $value ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	public static function filter_query( WP_Query $query ): void {
		global $pagenow;

		if ( ! is_admin() || ! $query->is_main_query() || 'edit.php' !== $pagenow ) {
			return;
		}

		$post_type = $query->get( 'post_type' );
		// The Posts screen leaves post_type empty.
		if ( ! is_string( $post_type ) || ! in_array( $post_type ? $post_type : 'post', Settings::post_types(), true ) ) {
			return;
		}

		$tax_query  = is_array( $query->get( 'tax_query' ) ) ? $query->get( 'tax_query' ) : [];
		$meta_query = is_array( $query->get( 'meta_query' ) ) ? $query->get( 'meta_query' ) : [];

		switch ( self::requested_filter() ) {
			case self::SHOW_KEYWORDS:
				$tax_query[] = [
					'taxonomy' => Taxonomies::KEYWORD,
					'operator' => 'EXISTS',
				];
				break;
			case self::SHOW_STOP:
				$tax_query[] = [
					'taxonomy' => Taxonomies::STOP,
					'operator' => 'EXISTS',
				];
				break;
			case self::SHOW_HIDDEN:
				$meta_query[] = [
					'key'     => HiddenFlag::META_KEY,
					'compare' => 'EXISTS',
				];
				break;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		$term_id = isset( $_GET[ self::FILTER_TERM ] ) ? absint( $_GET[ self::FILTER_TERM ] ) : 0;
		$term    = $term_id ? get_term( $term_id ) : null;
		if ( $term instanceof WP_Term && in_array( $term->taxonomy, [ Taxonomies::KEYWORD, Taxonomies::STOP ], true ) ) {
			$tax_query[] = [
				'taxonomy' => $term->taxonomy,
				'field'    => 'term_id',
				'terms'    => [ $term->term_id ],
			];
		}

		$query->set( 'tax_query', $tax_query );
		$query->set( 'meta_query', $meta_query );
	}

	public static function render_quick_edit( string $column, string $post_type ): void {
		if ( self::COLUMN !== $column || ! in_array( $post_type, Settings::post_types(), true ) ) {
			return;
		}
		?>
		<fieldset class="inline-edit-col-right">
			<div class="inline-edit-col">
				<div class="inline-edit-group wp-clearfix">
					<?php EditorFields::render_fields( false ); ?>
				</div>
			</div>
		</fieldset>
		<?php
	}

	public static function enqueue_script( string $hook_suffix ): void {
		$screen = get_current_screen();
		if ( 'edit.php' !== $hook_suffix || ! $screen || ! in_array( $screen->post_type, Settings::post_types(), true ) ) {
			return;
		}

		wp_enqueue_script( self::SCRIPT, Plugin::url( 'assets/js/quick-edit.js' ), [ 'inline-edit-post' ], Plugin::VERSION, true );
		wp_add_inline_script(
			self::SCRIPT,
			'window.searchSteerQuickEdit = ' . wp_json_encode( [ 'field' => EditorFields::FIELD ] ) . ';',
			'before'
		);
	}

	public static function add_bulk_actions( $actions ) {
		if ( is_array( $actions ) ) {
			$actions[ self::BULK_HIDE ] = __( 'Hide from site search', 'search-steer' );
			$actions[ self::BULK_SHOW ] = __( 'Show in site search', 'search-steer' );
		}

		return $actions;
	}

	// Core has already checked the nonce. Posts the user cannot edit are skipped.
	public static function handle_bulk_action( $redirect, string $action, $post_ids ) {
		if ( ! in_array( $action, [ self::BULK_HIDE, self::BULK_SHOW ], true ) || ! is_string( $redirect ) || ! is_array( $post_ids ) ) {
			return $redirect;
		}

		$count = 0;
		foreach ( $post_ids as $post_id ) {
			$post_id = (int) $post_id;
			if ( current_user_can( 'edit_post', $post_id ) ) {
				HiddenFlag::set( $post_id, self::BULK_HIDE === $action );
				++$count;
			}
		}

		return add_query_arg(
			[
				self::NOTICE_COUNT  => $count,
				self::NOTICE_ACTION => $action,
			],
			$redirect
		);
	}

	public static function render_notice(): void {
		global $pagenow;

		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Only reads the result of a bulk action to show it.
		$screen = get_current_screen();
		if ( 'edit.php' !== $pagenow || ! $screen || ! in_array( $screen->post_type, Settings::post_types(), true ) || ! isset( $_GET[ self::NOTICE_COUNT ], $_GET[ self::NOTICE_ACTION ] ) ) {
			return;
		}

		$count  = absint( $_GET[ self::NOTICE_COUNT ] );
		$action = sanitize_key( wp_unslash( $_GET[ self::NOTICE_ACTION ] ) );
		// phpcs:enable

		if ( self::BULK_HIDE === $action ) {
			/* translators: %s: number of posts. */
			$message = _n( '%s post hidden from site search.', '%s posts hidden from site search.', $count, 'search-steer' );
		} else {
			/* translators: %s: number of posts. */
			$message = _n( '%s post shown in site search again.', '%s posts shown in site search again.', $count, 'search-steer' );
		}

		printf(
			'<div class="notice notice-success is-dismissible"><p>%s</p></div>',
			esc_html( sprintf( $message, number_format_i18n( $count ) ) )
		);
	}

	public static function add_removable_query_args( $args ) {
		if ( is_array( $args ) ) {
			$args[] = self::NOTICE_COUNT;
			$args[] = self::NOTICE_ACTION;
		}

		return $args;
	}

	private static function requested_filter(): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only list filter.
		$value = isset( $_GET[ self::FILTER ] ) ? sanitize_key( wp_unslash( $_GET[ self::FILTER ] ) ) : '';

		return in_array( $value, [ self::SHOW_KEYWORDS, self::SHOW_STOP, self::SHOW_HIDDEN ], true ) ? $value : '';
	}
}

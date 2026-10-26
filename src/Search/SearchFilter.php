<?php

namespace SearchSteer\Search;

use SearchSteer\Keywords\Taxonomies;
use SearchSteer\Settings;
use SearchSteer\Visibility\HiddenFlag;
use WP_Query;

defined( 'ABSPATH' ) || exit;

final class SearchFilter {

	public const APPLIES_FILTER = 'mwtss_applies_to_query';
	public const SKIP_QUERY_VAR = 'mwtss_skip';
	private const CONTEXT_FRONT = 'front';
	private const CONTEXT_API   = 'api';
	private const CONTEXT_ADMIN = 'admin';

	public static function add_hooks(): void {
		add_filter( 'posts_search', [ self::class, 'filter_search' ], 10, 2 );
		add_filter( 'posts_search_orderby', [ self::class, 'filter_orderby' ], 10, 2 );
	}

	public static function filter_search( $search, $query ) {
		if ( ! is_string( $search ) || '' === trim( $search ) || ! $query instanceof WP_Query || ! self::applies( $query ) ) {
			return $search;
		}

		global $wpdb;

		$settings   = Settings::all();
		$matches    = self::matches( $query );
		$post_id    = "{$wpdb->posts}.ID";
		$post_types = self::post_types_sql();

		// Keywords, stop keywords and hidden flags of post types turned off in the settings are ignored.
		if ( $matches['keyword'] ) {
			// $search starts with " AND ", hence the "1=1". Core adds the password check to $search
			// for visitors, so keyword matches need it too.
			$password = is_user_logged_in() ? '' : " AND {$wpdb->posts}.post_password = ''";
			$search   = " AND ( ( 1=1 {$search} ) OR ( {$post_id} IN (" . self::objects_sql( $matches['keyword'] ) . ") AND {$wpdb->posts}.post_type IN ({$post_types}){$password} ) ) ";
		}

		if ( $matches['stop'] ) {
			$stopped = self::objects_sql( $matches['stop'] );
			// When keywords win, a post that also matches by keyword is not stopped.
			if ( Settings::PRECEDENCE_KEYWORD === $settings['precedence'] && $matches['keyword'] ) {
				$stopped .= ' AND object_id NOT IN (' . self::objects_sql( $matches['keyword'] ) . ')';
			}
			$search .= " AND ( {$wpdb->posts}.post_type NOT IN ({$post_types}) OR {$post_id} NOT IN ({$stopped}) ) ";
		}

		// Hidden posts are never hidden in wp-admin, so editors can always find them.
		if ( self::CONTEXT_ADMIN !== self::context() ) {
			$search .= " AND ( {$wpdb->posts}.post_type NOT IN ({$post_types}) OR {$post_id} NOT IN (" . HiddenFlag::hidden_posts_sql() . ') ) ';
		}

		return $search;
	}

	public static function filter_orderby( $orderby, $query ) {
		if ( ! is_string( $orderby ) || '' === $orderby || ! $query instanceof WP_Query || ! Settings::get( 'boost' ) || ! self::applies( $query ) ) {
			return $orderby;
		}

		$keyword = self::matches( $query )['keyword'];
		if ( ! $keyword ) {
			return $orderby;
		}

		global $wpdb;

		return "CASE WHEN {$wpdb->posts}.ID IN (" . self::objects_sql( $keyword ) . ') THEN 0 ELSE 1 END, ' . $orderby;
	}

	public static function applies( WP_Query $query ): bool {
		if ( ! $query->is_search() || $query->get( self::SKIP_QUERY_VAR ) ) {
			return false;
		}

		$settings = Settings::all();
		if ( ! $settings['post_types'] ) {
			return false;
		}

		switch ( self::context() ) {
			case self::CONTEXT_API:
				$applies = $settings['api_search'];
				break;
			case self::CONTEXT_ADMIN:
				$applies = $settings['admin_search'] && $query->is_main_query();
				break;
			default:
				// Only the search page itself: widgets, menus and other loops are left alone.
				$applies = $query->is_main_query();
		}

		return (bool) apply_filters( self::APPLIES_FILTER, $applies, $query ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- The constant holds the prefixed hook name.
	}

	private static function context(): string {
		// admin-ajax.php runs with is_admin() true, so it is checked first.
		if ( wp_is_serving_rest_request() || wp_doing_ajax() ) {
			return self::CONTEXT_API;
		}

		return is_admin() ? self::CONTEXT_ADMIN : self::CONTEXT_FRONT;
	}

	private static function matches( WP_Query $query ): array {
		$terms    = self::search_terms( $query );
		$settings = Settings::all();

		return [
			'keyword' => Matcher::match( $terms, self::keywords( Taxonomies::KEYWORD ), $settings['keyword_match'] ),
			'stop'    => Matcher::match( $terms, self::keywords( Taxonomies::STOP ), $settings['stop_match'] ),
		];
	}

	private static function search_terms( WP_Query $query ): array {
		$terms = $query->get( 'search_terms' );
		if ( ! is_array( $terms ) || ! $terms ) {
			$terms = [ (string) $query->get( 's' ) ];
		}

		// Core reads words such as "-coupon" as exclusions. They must not match keywords.
		$prefix = (string) apply_filters( 'wp_query_search_exclusion_prefix', '-' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter, so exclusions match core's.

		$included = [];
		foreach ( $terms as $term ) {
			$term = (string) $term;
			if ( '' === $prefix || 0 !== strpos( $term, $prefix ) ) {
				$included[] = $term;
			}
		}

		return $included;
	}

	private static function keywords( string $taxonomy ): array {
		$terms = get_terms(
			[
				'taxonomy'               => $taxonomy,
				'hide_empty'             => false,
				'update_term_meta_cache' => false,
			]
		);

		if ( ! is_array( $terms ) ) {
			return [];
		}

		$keywords = [];
		foreach ( $terms as $term ) {
			$keywords[ (int) $term->term_taxonomy_id ] = $term->name;
		}

		return $keywords;
	}

	private static function objects_sql( array $term_taxonomy_ids ): string {
		global $wpdb;

		return "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN (" . implode( ',', array_map( 'absint', $term_taxonomy_ids ) ) . ')';
	}

	private static function post_types_sql(): string {
		global $wpdb;

		$quoted = [];
		foreach ( Settings::post_types() as $post_type ) {
			$quoted[] = $wpdb->prepare( '%s', $post_type );
		}

		return implode( ',', $quoted );
	}
}

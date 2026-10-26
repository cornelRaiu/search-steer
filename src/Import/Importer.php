<?php

namespace SearchSteer\Import;

use SearchSteer\Settings;
use SearchSteer\Visibility\HiddenFlag;

defined( 'ABSPATH' ) || exit;

// Reads the other plugins' data without changing it, so an import can run again and the other
// plugin keeps working until it is removed.
final class Importer {

	public const SEARCH_EXCLUDE   = 'search-exclude';
	public const HIDE_FROM_SEARCH = 'hide-from-search';
	public const HIDE_POSTS       = 'hide-posts';

	public static function sources(): array {
		return [
			self::SEARCH_EXCLUDE   => 'Search Exclude',
			self::HIDE_FROM_SEARCH => 'Hide from Search',
			self::HIDE_POSTS       => 'Hide Posts',
		];
	}

	public static function find( string $source ): array {
		switch ( $source ) {
			case self::SEARCH_EXCLUDE:
				$ids = self::search_exclude_ids();
				break;
			case self::HIDE_FROM_SEARCH:
				$ids = self::meta_ids( [ '_hide_from_search_wp', '_mpress_hide_from_search' ] );
				break;
			case self::HIDE_POSTS:
				$ids = array_merge( self::hide_posts_table_ids(), self::meta_ids( [ '_whp_hide_on_search' ] ) );
				break;
			default:
				$ids = [];
		}

		$ids = array_values( array_unique( array_filter( array_map( 'intval', $ids ) ) ) );
		sort( $ids );

		return $ids;
	}

	// Search Exclude can also hide every post of a type, a term or an author. Search Steer hides
	// single posts, so those settings are reported instead of imported.
	public static function notes( string $source ): array {
		if ( self::SEARCH_EXCLUDE !== $source ) {
			return [];
		}

		$settings = get_option( 'qlse_settings' );
		if ( ! is_array( $settings ) ) {
			return [];
		}

		$notes = [];

		$entries = isset( $settings['entries'] ) && is_array( $settings['entries'] ) ? $settings['entries'] : [];
		foreach ( $entries as $post_type => $entry ) {
			if ( is_array( $entry ) && ! empty( $entry['all'] ) ) {
				$notes[] = sprintf(
					/* translators: %s: post type name, such as "post". */
					__( 'Search Exclude hides every item of the type "%s". Select them in their list and use the "Hide from site search" bulk action instead.', 'search-steer' ),
					sanitize_key( (string) $post_type )
				);
			}
		}

		$taxonomies = isset( $settings['taxonomies'] ) && is_array( $settings['taxonomies'] ) ? $settings['taxonomies'] : [];
		foreach ( $taxonomies as $taxonomy => $entry ) {
			if ( is_array( $entry ) && ( ! empty( $entry['all'] ) || ! empty( $entry['ids'] ) ) ) {
				$notes[] = sprintf(
					/* translators: %s: taxonomy name, such as "category". */
					__( 'Search Exclude hides posts by their "%s" terms. That setting is not imported.', 'search-steer' ),
					sanitize_key( (string) $taxonomy )
				);
			}
		}

		$author = isset( $settings['author'] ) && is_array( $settings['author'] ) ? $settings['author'] : [];
		if ( ! empty( $author['all'] ) || ! empty( $author['ids'] ) ) {
			$notes[] = __( 'Search Exclude hides posts by their author. That setting is not imported.', 'search-steer' );
		}

		return $notes;
	}

	public static function run( string $source ): array {
		$enabled     = Settings::post_types();
		$imported    = 0;
		$other_types = [];

		foreach ( self::find( $source ) as $post_id ) {
			$post_type = get_post_type( $post_id );
			if ( ! $post_type ) {
				continue;
			}

			HiddenFlag::set( $post_id, true );
			++$imported;

			if ( ! in_array( $post_type, $enabled, true ) ) {
				$other_types[ $post_type ] = $post_type;
			}
		}

		return [
			'imported'    => $imported,
			'other_types' => array_values( $other_types ),
		];
	}

	// qlse_settings is the format since Search Exclude 2.0, sep_exclude the one before.
	private static function search_exclude_ids(): array {
		$ids = [];

		$settings = get_option( 'qlse_settings' );
		$entries  = is_array( $settings ) && isset( $settings['entries'] ) && is_array( $settings['entries'] ) ? $settings['entries'] : [];
		foreach ( $entries as $entry ) {
			if ( is_array( $entry ) && isset( $entry['ids'] ) && is_array( $entry['ids'] ) ) {
				$ids = array_merge( $ids, $entry['ids'] );
			}
		}

		$legacy = get_option( 'sep_exclude' );
		if ( is_array( $legacy ) ) {
			$ids = array_merge( $ids, $legacy );
		}

		return array_map( 'intval', $ids );
	}

	// "" and "0" mean the other plugin shows the post.
	private static function meta_ids( array $meta_keys ): array {
		global $wpdb;

		$placeholders = implode( ',', array_fill( 0, count( $meta_keys ), '%s' ) );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare -- One-off import of another plugin's data. The placeholders are built above.
		$ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ({$placeholders}) AND meta_value NOT IN ('', '0')", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$meta_keys
			)
		);
		// phpcs:enable

		return array_map( 'intval', $ids );
	}

	private static function hide_posts_table_ids(): array {
		global $wpdb;

		$table = $wpdb->prefix . 'whp_posts_visibility';

		// The table exists only on sites that ran Hide Posts 2.0 or later. Query it and treat an error as "no table".
		$suppress = $wpdb->suppress_errors( true );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- One-off import from another plugin's table.
		$ids    = $wpdb->get_col(
			$wpdb->prepare(
				'SELECT DISTINCT post_id FROM %i WHERE `condition` = %s',
				$table,
				'hide_on_search'
			)
		);
		$failed = '' !== $wpdb->last_error;
		$wpdb->suppress_errors( $suppress );

		if ( $failed ) {
			return [];
		}

		return array_map( 'intval', $ids );
	}
}

<?php

namespace SearchSteer\Visibility;

use SearchSteer\Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

final class HiddenFlag {

	// The row exists only while the post is hidden, so search excludes hidden posts with one
	// subquery on the indexed meta_key. The underscore keeps it out of the Custom Fields box.
	public const META_KEY   = '_mwtss_hidden';
	public const REST_FIELD = 'mwtss_hidden';

	public static function add_hooks(): void {
		add_action( 'rest_api_init', [ self::class, 'register_rest_field' ] );
	}

	public static function is_hidden( int $post_id ): bool {
		return '' !== get_post_meta( $post_id, self::META_KEY, true );
	}

	public static function set( int $post_id, bool $hidden ): void {
		if ( $hidden ) {
			update_post_meta( $post_id, self::META_KEY, '1' );
		} else {
			delete_post_meta( $post_id, self::META_KEY );
		}
	}

	public static function hidden_posts_sql(): string {
		global $wpdb;

		return $wpdb->prepare( "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = %s", self::META_KEY );
	}

	// A REST field, not registered meta: meta is only in the REST API of post types that support
	// custom fields, and registered meta is readable by anyone who can read the post. The field
	// is only in the edit context, so visitors never see which posts are hidden.
	public static function register_rest_field(): void {
		register_rest_field(
			Settings::post_types(),
			self::REST_FIELD,
			[
				'get_callback'    => [ self::class, 'rest_get' ],
				'update_callback' => [ self::class, 'rest_update' ],
				'schema'          => [
					'description' => __( 'Whether the post is hidden from site search.', 'search-steer' ),
					'type'        => 'boolean',
					'context'     => [ 'edit' ],
				],
			]
		);
	}

	public static function rest_get( array $post ): bool {
		return self::is_hidden( (int) $post['id'] );
	}

	public static function rest_update( $value, WP_Post $post ): bool {
		self::set( $post->ID, (bool) $value );

		return true;
	}
}

<?php

namespace SearchSteer\Keywords;

use SearchSteer\Settings;

defined( 'ABSPATH' ) || exit;

// Keywords are terms of private taxonomies: term names are indexed, so finding the posts of a
// keyword is one query, and there are no archives, feeds, sitemaps or CSS classes to leak them.
final class Taxonomies {

	public const KEYWORD = 'mwtss_keyword';
	public const STOP    = 'mwtss_stop_keyword';

	public static function register( ?array $post_types = null ): void {
		$post_types = $post_types ?? Settings::post_types();

		register_taxonomy(
			self::KEYWORD,
			$post_types,
			self::args(
				[
					'name'                       => __( 'Search Keywords', 'search-steer' ),
					'singular_name'              => __( 'Search Keyword', 'search-steer' ),
					'menu_name'                  => __( 'Search Keywords', 'search-steer' ),
					'all_items'                  => __( 'All Search Keywords', 'search-steer' ),
					'edit_item'                  => __( 'Edit Search Keyword', 'search-steer' ),
					'view_item'                  => __( 'View Search Keyword', 'search-steer' ),
					'update_item'                => __( 'Update Search Keyword', 'search-steer' ),
					'add_new_item'               => __( 'Add Search Keyword', 'search-steer' ),
					'new_item_name'              => __( 'New Search Keyword', 'search-steer' ),
					'search_items'               => __( 'Find Search Keywords', 'search-steer' ),
					'popular_items'              => __( 'Most Used Search Keywords', 'search-steer' ),
					'separate_items_with_commas' => __( 'Show this post when a search contains one of these words. Separate keywords with commas.', 'search-steer' ),
					'add_or_remove_items'        => __( 'Add or remove search keywords', 'search-steer' ),
					'choose_from_most_used'      => __( 'Choose from the most used search keywords', 'search-steer' ),
					'not_found'                  => __( 'No search keywords found.', 'search-steer' ),
					'no_terms'                   => __( 'No search keywords', 'search-steer' ),
					'back_to_items'              => __( '&larr; Go to Search Keywords', 'search-steer' ),
				]
			)
		);

		register_taxonomy(
			self::STOP,
			$post_types,
			self::args(
				[
					'name'                       => __( 'Stop Keywords', 'search-steer' ),
					'singular_name'              => __( 'Stop Keyword', 'search-steer' ),
					'menu_name'                  => __( 'Stop Keywords', 'search-steer' ),
					'all_items'                  => __( 'All Stop Keywords', 'search-steer' ),
					'edit_item'                  => __( 'Edit Stop Keyword', 'search-steer' ),
					'view_item'                  => __( 'View Stop Keyword', 'search-steer' ),
					'update_item'                => __( 'Update Stop Keyword', 'search-steer' ),
					'add_new_item'               => __( 'Add Stop Keyword', 'search-steer' ),
					'new_item_name'              => __( 'New Stop Keyword', 'search-steer' ),
					'search_items'               => __( 'Find Stop Keywords', 'search-steer' ),
					'popular_items'              => __( 'Most Used Stop Keywords', 'search-steer' ),
					'separate_items_with_commas' => __( 'Hide this post when a search contains one of these words. Separate keywords with commas.', 'search-steer' ),
					'add_or_remove_items'        => __( 'Add or remove stop keywords', 'search-steer' ),
					'choose_from_most_used'      => __( 'Choose from the most used stop keywords', 'search-steer' ),
					'not_found'                  => __( 'No stop keywords found.', 'search-steer' ),
					'no_terms'                   => __( 'No stop keywords', 'search-steer' ),
					'back_to_items'              => __( '&larr; Go to Stop Keywords', 'search-steer' ),
				]
			)
		);
	}

	private static function args( array $labels ): array {
		return [
			'labels'                => $labels,
			'public'                => false,
			'publicly_queryable'    => false,
			'hierarchical'          => false,
			// The editor's tag panel, quick edit and bulk edit need the UI. The terms screen is
			// linked from the settings page instead of the menu.
			'show_ui'               => true,
			'show_in_menu'          => false,
			'show_in_nav_menus'     => false,
			'show_tagcloud'         => false,
			'show_in_quick_edit'    => true,
			'show_admin_column'     => false,
			// The block editor reads and saves terms through REST. The controller hides them from visitors.
			'show_in_rest'          => true,
			'rest_controller_class' => RestController::class,
			'query_var'             => false,
			'rewrite'               => false,
			// Anyone who can edit posts can add keywords to them, as with tags.
			'capabilities'          => [
				'manage_terms' => 'edit_posts',
				'edit_terms'   => 'edit_posts',
				'delete_terms' => 'edit_posts',
				'assign_terms' => 'edit_posts',
			],
		];
	}
}

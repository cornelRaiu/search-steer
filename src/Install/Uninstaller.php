<?php

namespace SearchSteer\Install;

use SearchSteer\Keywords\Taxonomies;
use SearchSteer\Settings;
use SearchSteer\Visibility\HiddenFlag;

defined( 'ABSPATH' ) || exit;

final class Uninstaller {

	public static function run(): void {
		if ( ! Settings::get( 'delete_data' ) ) {
			return;
		}

		// The plugin is not loaded during uninstall, so the taxonomies must be registered to delete their terms.
		Taxonomies::register( [] );

		foreach ( [ Taxonomies::KEYWORD, Taxonomies::STOP ] as $taxonomy ) {
			$term_ids = get_terms(
				[
					'taxonomy'   => $taxonomy,
					'hide_empty' => false,
					'fields'     => 'ids',
				]
			);

			if ( is_array( $term_ids ) ) {
				foreach ( $term_ids as $term_id ) {
					wp_delete_term( (int) $term_id, $taxonomy );
				}
			}

			unregister_taxonomy( $taxonomy );
		}

		delete_post_meta_by_key( HiddenFlag::META_KEY );
		delete_option( Settings::OPTION );
	}
}

<?php

namespace SearchSteer\Tests\Integration;

use SearchSteer\Keywords\Taxonomies;
use SearchSteer\Settings;
use WP_UnitTestCase;

abstract class TestCase extends WP_UnitTestCase {

	/** @param array<string, mixed> $settings */
	protected function set_settings( array $settings ): void {
		update_option( Settings::OPTION, array_merge( Settings::all(), $settings ) );
	}

	/**
	 * @param string[] $keywords
	 * @param string[] $stop
	 * @param array<string, mixed> $args
	 */
	protected function create_post( string $title, array $keywords = [], array $stop = [], array $args = [] ): int {
		$post_id = self::factory()->post->create(
			array_merge(
				[
					'post_title'   => $title,
					'post_content' => '',
					'post_status'  => 'publish',
				],
				$args
			)
		);

		wp_set_object_terms( $post_id, $keywords, Taxonomies::KEYWORD );
		wp_set_object_terms( $post_id, $stop, Taxonomies::STOP );

		return $post_id;
	}

	/** @return int[] */
	protected function search( string $search ): array {
		$this->go_to( add_query_arg( 's', rawurlencode( $search ), home_url( '/' ) ) );

		return array_map( 'intval', wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
	}
}

<?php

namespace SearchSteer\Tests\Integration;

use SearchSteer\Import\Importer;
use SearchSteer\Visibility\HiddenFlag;

final class ImporterTest extends TestCase {

	public function test_search_exclude_ids_are_imported_from_both_formats(): void {
		$post   = $this->create_post( 'Excluded post' );
		$page   = $this->create_post( 'Excluded page', [], [], [ 'post_type' => 'page' ] );
		$legacy = $this->create_post( 'Excluded long ago' );
		update_option(
			'qlse_settings',
			[
				'entries' => [
					'post' => [
						'all' => false,
						'ids' => [ $post ],
					],
					'page' => [
						'all' => false,
						'ids' => [ (string) $page ],
					],
				],
			]
		);
		update_option( 'sep_exclude', [ $legacy ] );

		$result = Importer::run( Importer::SEARCH_EXCLUDE );

		$this->assertSame( 3, $result['imported'] );
		$this->assertSame( [], $result['other_types'] );
		$this->assertTrue( HiddenFlag::is_hidden( $post ) );
		$this->assertTrue( HiddenFlag::is_hidden( $page ) );
		$this->assertTrue( HiddenFlag::is_hidden( $legacy ) );
	}

	public function test_search_exclude_settings_that_cannot_be_imported_are_reported(): void {
		update_option(
			'qlse_settings',
			[
				'entries'    => [
					'page' => [
						'all' => true,
						'ids' => [],
					],
				],
				'taxonomies' => [
					'category' => [
						'all' => false,
						'ids' => [ 3 ],
					],
				],
				'author'     => [
					'all' => false,
					'ids' => [],
				],
			]
		);

		$notes = implode( ' ', Importer::notes( Importer::SEARCH_EXCLUDE ) );

		$this->assertStringContainsString( '"page"', $notes );
		$this->assertStringContainsString( '"category"', $notes );
		$this->assertStringNotContainsString( 'author', $notes );
	}

	public function test_hide_from_search_meta_is_imported(): void {
		$hidden = $this->create_post( 'Hidden' );
		$old    = $this->create_post( 'Hidden in 1.0' );
		$shown  = $this->create_post( 'Shown' );
		update_post_meta( $hidden, '_hide_from_search_wp', '1' );
		update_post_meta( $old, '_mpress_hide_from_search', '1' );
		update_post_meta( $shown, '_hide_from_search_wp', '0' );

		$this->assertSame( [ $hidden, $old ], Importer::find( Importer::HIDE_FROM_SEARCH ) );
	}

	public function test_hide_posts_table_and_meta_are_imported(): void {
		global $wpdb;

		$from_table = $this->create_post( 'Hidden in 2.0' );
		$from_meta  = $this->create_post( 'Hidden in 1.0' );
		$elsewhere  = $this->create_post( 'Hidden on the front page only' );
		$table      = $wpdb->prefix . 'whp_posts_visibility';

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$wpdb->query( "CREATE TABLE {$table} ( id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT, post_id BIGINT(20) UNSIGNED NOT NULL, `condition` VARCHAR(100) NOT NULL, PRIMARY KEY (id) )" );
		// phpcs:enable
		$wpdb->insert(
			$table,
			[
				'post_id'   => $from_table,
				'condition' => 'hide_on_search',
			]
		);
		$wpdb->insert(
			$table,
			[
				'post_id'   => $elsewhere,
				'condition' => 'hide_on_frontpage',
			]
		);
		update_post_meta( $from_meta, '_whp_hide_on_search', '1' );

		$this->assertSame( [ $from_table, $from_meta ], Importer::find( Importer::HIDE_POSTS ) );
	}

	public function test_hide_posts_without_its_table_imports_the_meta(): void {
		$post = $this->create_post( 'Hidden in 1.0' );
		update_post_meta( $post, '_whp_hide_on_search', '1' );

		$this->assertSame( [ $post ], Importer::find( Importer::HIDE_POSTS ) );
	}

	public function test_post_types_that_cannot_have_keywords_are_reported(): void {
		$this->set_settings( [ 'post_types' => [ 'post' ] ] );
		$page = $this->create_post( 'Hidden page', [], [], [ 'post_type' => 'page' ] );
		update_post_meta( $page, '_hide_from_search_wp', '1' );

		$result = Importer::run( Importer::HIDE_FROM_SEARCH );

		$this->assertSame( 1, $result['imported'] );
		$this->assertSame( [ 'page' ], $result['other_types'] );
	}

	public function test_deleted_posts_are_skipped_and_running_again_changes_nothing(): void {
		$post = $this->create_post( 'Excluded' );
		update_option( 'sep_exclude', [ $post, 999999 ] );

		$this->assertSame( 1, Importer::run( Importer::SEARCH_EXCLUDE )['imported'] );
		$this->assertSame( 1, Importer::run( Importer::SEARCH_EXCLUDE )['imported'] );
		$this->assertCount( 1, get_post_meta( $post, HiddenFlag::META_KEY ) );
	}
}

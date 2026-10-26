<?php

namespace SearchSteer\Tests\Integration;

use SearchSteer\Install\Uninstaller;
use SearchSteer\Keywords\Taxonomies;
use SearchSteer\Settings;
use SearchSteer\Visibility\HiddenFlag;

final class UninstallerTest extends TestCase {

	public function tear_down(): void {
		// The uninstaller unregisters the taxonomies, which the next tests need.
		Taxonomies::register();
		parent::tear_down();
	}

	public function test_data_is_kept_by_default(): void {
		$this->create_post( 'Best car of 2026', [ 'Refund' ], [ 'Coupon' ] );
		$this->set_settings( [ 'boost' => false ] );

		Uninstaller::run();

		$this->assertSame( 1, (int) wp_count_terms( [ 'taxonomy' => Taxonomies::KEYWORD ] ) );
		$this->assertIsArray( get_option( Settings::OPTION ) );
	}

	public function test_data_is_deleted_when_the_site_chose_that(): void {
		$post = $this->create_post( 'Best car of 2026', [ 'Refund' ], [ 'Coupon' ] );
		HiddenFlag::set( $post, true );
		$this->set_settings( [ 'delete_data' => true ] );

		Uninstaller::run();
		Taxonomies::register();

		$this->assertSame( 0, (int) wp_count_terms( [ 'taxonomy' => Taxonomies::KEYWORD ] ) );
		$this->assertSame( 0, (int) wp_count_terms( [ 'taxonomy' => Taxonomies::STOP ] ) );
		$this->assertFalse( HiddenFlag::is_hidden( $post ) );
		$this->assertFalse( get_option( Settings::OPTION ) );
	}
}

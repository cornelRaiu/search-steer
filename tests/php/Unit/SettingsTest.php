<?php

namespace SearchSteer\Tests\Unit;

use SearchSteer\Settings;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

final class SettingsTest extends TestCase {

	public function test_missing_settings_get_their_defaults(): void {
		$this->assertSame( Settings::defaults(), Settings::sanitize( [] ) );
		$this->assertSame( Settings::defaults(), Settings::sanitize( 'not an array' ) );
	}

	public function test_unknown_choices_fall_back_to_the_default(): void {
		$settings = Settings::sanitize(
			[
				'keyword_match' => 'fuzzy',
				'precedence'    => [ 'stop' ],
			]
		);

		$this->assertSame( Settings::MATCH_TERM, $settings['keyword_match'] );
		$this->assertSame( Settings::PRECEDENCE_STOP, $settings['precedence'] );
	}

	public function test_valid_choices_are_kept(): void {
		$settings = Settings::sanitize(
			[
				'keyword_match' => Settings::MATCH_PARTIAL,
				'stop_match'    => Settings::MATCH_TERM,
				'precedence'    => Settings::PRECEDENCE_KEYWORD,
			]
		);

		$this->assertSame( Settings::MATCH_PARTIAL, $settings['keyword_match'] );
		$this->assertSame( Settings::MATCH_TERM, $settings['stop_match'] );
		$this->assertSame( Settings::PRECEDENCE_KEYWORD, $settings['precedence'] );
	}

	public function test_an_unticked_checkbox_saves_off(): void {
		$settings = Settings::sanitize( [ 'boost' => '0' ] );

		$this->assertFalse( $settings['boost'] );
	}

	public function test_post_types_are_cleaned_and_can_be_empty(): void {
		$this->assertSame( [ 'post', 'product' ], Settings::sanitize( [ 'post_types' => [ '', 'post', 'Product', 'post' ] ] )['post_types'] );
		$this->assertSame( [], Settings::sanitize( [ 'post_types' => [ '' ] ] )['post_types'] );
	}
}

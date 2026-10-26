<?php

namespace SearchSteer\Tests\Unit;

use SearchSteer\Search\Matcher;
use SearchSteer\Settings;
use Yoast\PHPUnitPolyfills\TestCases\TestCase;

final class MatcherTest extends TestCase {

	private const KEYWORDS = [
		1 => 'Refund',
		2 => 'Voucher',
		3 => 'Gift',
		4 => 'Best car',
		5 => 'Café',
		6 => 'EU',
	];

	/**
	 * @param string[] $terms
	 * @return int[]
	 */
	private function match( array $terms, string $mode = Settings::MATCH_TERM ): array {
		$matched = Matcher::match( $terms, self::KEYWORDS, $mode );
		sort( $matched );

		return $matched;
	}

	public function test_a_keyword_matches_a_whole_search_word(): void {
		$this->assertSame( [ 1 ], $this->match( [ 'refund', '911' ] ) );
	}

	public function test_case_and_accents_are_ignored(): void {
		$this->assertSame( [ 1, 5 ], $this->match( [ 'REFUND', 'cafe' ] ) );
	}

	public function test_every_word_of_a_keyword_must_be_in_the_search(): void {
		$this->assertSame( [], $this->match( [ 'best', 'bike' ] ) );
		$this->assertSame( [ 4 ], $this->match( [ 'best', 'electric', 'car' ] ) );
	}

	public function test_a_quoted_phrase_is_split_into_words(): void {
		$this->assertSame( [ 4 ], $this->match( [ 'best car' ] ) );
	}

	public function test_whole_word_mode_does_not_match_part_of_a_word(): void {
		$this->assertSame( [], $this->match( [ 'gifts' ] ) );
		$this->assertSame( [], $this->match( [ 'refu' ] ) );
	}

	public function test_partial_mode_matches_part_of_a_keyword(): void {
		$this->assertSame( [ 1 ], $this->match( [ 'refu' ], Settings::MATCH_PARTIAL ) );
	}

	public function test_partial_mode_matches_a_keyword_inside_a_search_word(): void {
		$this->assertSame( [ 3 ], $this->match( [ 'gifts' ], Settings::MATCH_PARTIAL ) );
	}

	public function test_partial_mode_ignores_very_short_search_words(): void {
		$this->assertSame( [], $this->match( [ 're' ], Settings::MATCH_PARTIAL ) );
		$this->assertSame( [ 6 ], $this->match( [ 'eu' ], Settings::MATCH_PARTIAL ) );
	}

	public function test_an_empty_search_matches_nothing(): void {
		$this->assertSame( [], $this->match( [ '', ' - ' ] ) );
	}
}

<?php

namespace SearchSteer\Search;

use SearchSteer\Settings;

defined( 'ABSPATH' ) || exit;

final class Matcher {

	// In partial mode, shorter search words would match almost every keyword.
	public const MIN_PARTIAL_LENGTH = 3;

	// Term mode: every word of the keyword is a whole word of the search, so "gift" matches
	// "gift card" but not "gifts". Partial mode: the search contains the keyword, or a search
	// word is part of it, so "refu" matches "refund". Case and accents never matter.
	public static function match( array $search_terms, array $keywords, string $mode ): array {
		$search_words = [];
		foreach ( $search_terms as $term ) {
			$search_words = array_merge( $search_words, self::words( (string) $term ) );
		}
		$search_words = array_values( array_unique( $search_words ) );

		if ( ! $search_words ) {
			return [];
		}

		$search_phrase = ' ' . implode( ' ', $search_words ) . ' ';
		$matched       = [];

		foreach ( $keywords as $id => $name ) {
			$keyword_words = self::words( $name );
			if ( ! $keyword_words ) {
				continue;
			}

			if ( Settings::MATCH_PARTIAL === $mode ) {
				$is_match = self::matches_partially( $keyword_words, $search_words, $search_phrase );
			} else {
				$is_match = ! array_diff( $keyword_words, $search_words );
			}

			if ( $is_match ) {
				$matched[] = (int) $id;
			}
		}

		return $matched;
	}

	public static function words( string $text ): array {
		$text  = mb_strtolower( remove_accents( $text ), 'UTF-8' );
		$words = preg_split( '/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );

		return is_array( $words ) ? array_values( array_unique( $words ) ) : [];
	}

	private static function matches_partially( array $keyword_words, array $search_words, string $search_phrase ): bool {
		$keyword = implode( ' ', $keyword_words );

		if ( false !== strpos( $search_phrase, $keyword ) ) {
			return true;
		}

		foreach ( $search_words as $word ) {
			if ( mb_strlen( $word, 'UTF-8' ) >= self::MIN_PARTIAL_LENGTH && false !== strpos( $keyword, $word ) ) {
				return true;
			}
		}

		return false;
	}
}

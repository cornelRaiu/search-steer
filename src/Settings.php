<?php

namespace SearchSteer;

defined( 'ABSPATH' ) || exit;

final class Settings {

	public const OPTION             = 'mwtss_settings';
	public const MATCH_TERM         = 'term';
	public const MATCH_PARTIAL      = 'partial';
	public const PRECEDENCE_STOP    = 'stop';
	public const PRECEDENCE_KEYWORD = 'keyword';

	public static function defaults(): array {
		return [
			'post_types'    => [ 'post', 'page' ],
			'keyword_match' => self::MATCH_TERM,
			'stop_match'    => self::MATCH_TERM,
			'precedence'    => self::PRECEDENCE_STOP,
			'boost'         => true,
			'admin_search'  => false,
			'api_search'    => false,
			'delete_data'   => false,
		];
	}

	/** @return array{post_types: string[], keyword_match: string, stop_match: string, precedence: string, boost: bool, admin_search: bool, api_search: bool, delete_data: bool} */
	public static function all(): array {
		$stored = get_option( self::OPTION, [] );

		return self::sanitize( is_array( $stored ) ? $stored : [] );
	}

	public static function get( string $key ) {
		$settings = self::all();

		return $settings[ $key ] ?? null;
	}

	public static function post_types(): array {
		return self::all()['post_types'];
	}

	public static function sanitize( $input ): array {
		$defaults = self::defaults();
		$input    = is_array( $input ) ? $input : [];
		$matches  = [ self::MATCH_TERM, self::MATCH_PARTIAL ];

		$post_types = $defaults['post_types'];
		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			$post_types = array_values( array_unique( array_filter( array_map( 'sanitize_key', $input['post_types'] ) ) ) );
		}

		return [
			'post_types'    => $post_types,
			'keyword_match' => self::choice( $input, 'keyword_match', $matches, $defaults['keyword_match'] ),
			'stop_match'    => self::choice( $input, 'stop_match', $matches, $defaults['stop_match'] ),
			'precedence'    => self::choice( $input, 'precedence', [ self::PRECEDENCE_STOP, self::PRECEDENCE_KEYWORD ], $defaults['precedence'] ),
			'boost'         => self::flag( $input, 'boost', $defaults['boost'] ),
			'admin_search'  => self::flag( $input, 'admin_search', $defaults['admin_search'] ),
			'api_search'    => self::flag( $input, 'api_search', $defaults['api_search'] ),
			'delete_data'   => self::flag( $input, 'delete_data', $defaults['delete_data'] ),
		];
	}

	private static function choice( array $input, string $key, array $allowed, string $fallback ): string {
		$value = isset( $input[ $key ] ) && is_string( $input[ $key ] ) ? $input[ $key ] : $fallback;

		return in_array( $value, $allowed, true ) ? $value : $fallback;
	}

	private static function flag( array $input, string $key, bool $fallback ): bool {
		// The settings page sends a hidden "0" before each checkbox, so a missing key is not "off".
		if ( ! array_key_exists( $key, $input ) ) {
			return $fallback;
		}

		return (bool) filter_var( $input[ $key ], FILTER_VALIDATE_BOOLEAN );
	}
}

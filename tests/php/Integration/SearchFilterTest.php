<?php

namespace SearchSteer\Tests\Integration;

use SearchSteer\Search\SearchFilter;
use SearchSteer\Settings;
use WP_Query;

final class SearchFilterTest extends TestCase {

	public function test_a_keyword_brings_a_post_into_the_results(): void {
		$post = $this->create_post( 'Best car of 2026', [ 'Refund', 'Voucher' ] );

		$this->assertSame( [ $post ], $this->search( 'refund' ) );
		$this->assertSame( [ $post ], $this->search( 'voucher balance' ) );
	}

	public function test_posts_without_matching_keywords_stay_out(): void {
		$this->create_post( 'Best car of 2026', [ 'Refund' ] );

		$this->assertSame( [], $this->search( 'delivery' ) );
	}

	public function test_title_matches_still_show(): void {
		$post = $this->create_post( 'Delivery times' );

		$this->assertSame( [ $post ], $this->search( 'delivery' ) );
	}

	public function test_a_stop_keyword_keeps_a_post_out_even_when_its_title_matches(): void {
		$this->create_post( 'Gift cards and coupons', [], [ 'Gift', 'Coupon' ] );
		$other = $this->create_post( 'Coupon terms' );

		$this->assertSame( [ $other ], $this->search( 'coupon' ) );
	}

	public function test_stop_keywords_win_by_default_when_both_lists_match(): void {
		$this->create_post( 'Best car of 2026', [ 'Refund' ], [ 'Coupon' ] );

		$this->assertSame( [], $this->search( 'refund coupon' ) );
	}

	public function test_keywords_can_be_set_to_win_when_both_lists_match(): void {
		$this->set_settings( [ 'precedence' => Settings::PRECEDENCE_KEYWORD ] );
		$post = $this->create_post( 'Best car of 2026', [ 'Refund' ], [ 'Coupon' ] );

		$this->assertSame( [ $post ], $this->search( 'refund coupon' ) );
	}

	public function test_keywords_and_stop_keywords_have_their_own_matching_mode(): void {
		$this->set_settings(
			[
				'keyword_match' => Settings::MATCH_PARTIAL,
				'stop_match'    => Settings::MATCH_TERM,
			]
		);
		$post = $this->create_post( 'Best car of 2026', [ 'Refund' ], [ 'Gift' ] );

		$this->assertSame( [ $post ], $this->search( 'refu' ) );
		$this->assertSame( [ $post ], $this->search( 'refund gifts' ) );
		$this->assertSame( [], $this->search( 'refund gift' ) );
	}

	public function test_keyword_matches_are_listed_first(): void {
		$title_match   = $this->create_post( 'Refund rules' );
		$keyword_match = $this->create_post( 'Best car of 2026', [ 'Refund' ] );

		$this->assertSame( [ $keyword_match, $title_match ], $this->search( 'refund' ) );
	}

	public function test_keyword_matches_can_keep_their_relevance_order(): void {
		$this->set_settings( [ 'boost' => false ] );
		$title_match   = $this->create_post( 'Refund rules' );
		$keyword_match = $this->create_post( 'Best car of 2026', [ 'Refund' ] );

		$this->assertSame( [ $title_match, $keyword_match ], $this->search( 'refund' ) );
	}

	public function test_excluded_search_words_do_not_match_keywords(): void {
		$this->create_post( 'Best vehicle of 2026', [ 'Refund' ] );

		$this->assertSame( [], $this->search( 'car -refund' ) );
	}

	public function test_visitors_do_not_get_password_protected_posts_by_keyword(): void {
		$this->create_post( 'Best car of 2026', [ 'Refund' ], [], [ 'post_password' => 'secret' ] );

		$this->assertSame( [], $this->search( 'refund' ) );
	}

	public function test_drafts_stay_out(): void {
		$this->create_post( 'Best car of 2026', [ 'Refund' ], [], [ 'post_status' => 'draft' ] );

		$this->assertSame( [], $this->search( 'refund' ) );
	}

	public function test_keywords_of_post_types_turned_off_in_the_settings_are_ignored(): void {
		$page = $this->create_post( 'Our garage', [ 'Refund' ], [], [ 'post_type' => 'page' ] );
		$this->create_post( 'Refund desk hours', [], [ 'Refund' ], [ 'post_type' => 'page' ] );

		$this->set_settings( [ 'post_types' => [ 'post' ] ] );

		$this->assertNotContains( $page, $this->search( 'refund' ) );
		$this->assertCount( 1, $this->search( 'refund' ) );
	}

	public function test_other_queries_are_left_alone(): void {
		$this->create_post( 'Best car of 2026', [ 'Refund' ] );

		$query = new WP_Query(
			[
				's'      => 'refund',
				'fields' => 'ids',
			]
		);

		$this->assertSame( [], $query->posts );
	}

	public function test_a_filter_can_apply_keywords_to_other_queries(): void {
		$post = $this->create_post( 'Best car of 2026', [ 'Refund' ] );
		add_filter( SearchFilter::APPLIES_FILTER, '__return_true' );

		$query = new WP_Query(
			[
				's'      => 'refund',
				'fields' => 'ids',
			]
		);

		$this->assertSame( [ $post ], array_map( 'intval', $query->posts ) );
	}

	public function test_a_query_can_skip_keywords(): void {
		$this->create_post( 'Best car of 2026', [ 'Refund' ] );
		add_filter( SearchFilter::APPLIES_FILTER, '__return_true' );

		$query = new WP_Query(
			[
				's'                          => 'refund',
				'fields'                     => 'ids',
				SearchFilter::SKIP_QUERY_VAR => true,
			]
		);

		$this->assertSame( [], $query->posts );
	}

	public function test_searches_still_work_when_another_plugin_joins_post_meta(): void {
		$post = $this->create_post( 'Best car of 2026', [ 'Refund' ] );
		$this->create_post( 'Coupon questions', [], [ 'Coupon' ] );
		update_post_meta( $post, 'rating', '5' );

		$join = static function ( $join ) {
			global $wpdb;
			return $join . " LEFT JOIN {$wpdb->postmeta} ON ( {$wpdb->posts}.ID = {$wpdb->postmeta}.post_id )";
		};
		$groupby = static function () {
			global $wpdb;
			return "{$wpdb->posts}.ID";
		};
		add_filter( 'posts_join', $join );
		add_filter( 'posts_groupby', $groupby );

		$this->assertSame( [ $post ], $this->search( 'refund' ) );
		$this->assertSame( [], $this->search( 'coupon' ) );
	}
}

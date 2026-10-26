<?php

namespace SearchSteer\Tests\Integration;

use SearchSteer\Keywords\Taxonomies;
use WP_REST_Request;

final class TaxonomiesTest extends TestCase {

	public function test_keywords_have_no_public_pages(): void {
		$this->assertFalse( is_taxonomy_viewable( Taxonomies::KEYWORD ) );
		$this->assertFalse( is_taxonomy_viewable( Taxonomies::STOP ) );
	}

	public function test_keywords_are_not_in_the_post_classes(): void {
		$post = $this->create_post( 'Best car of 2026', [ 'Refund' ], [ 'Coupon' ] );

		$classes = implode( ' ', get_post_class( '', $post ) );

		$this->assertStringNotContainsString( 'refund', $classes );
		$this->assertStringNotContainsString( 'coupon', $classes );
	}

	public function test_visitors_cannot_list_keywords_through_rest(): void {
		$this->create_post( 'Best car of 2026', [ 'Refund' ], [ 'Coupon' ] );

		foreach ( [ Taxonomies::KEYWORD, Taxonomies::STOP ] as $taxonomy ) {
			$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/' . $taxonomy ) );
			$this->assertSame( 401, $response->get_status(), $taxonomy );
		}
	}

	public function test_visitors_cannot_read_one_keyword_through_rest(): void {
		$post = $this->create_post( 'Best car of 2026', [ 'Refund' ] );
		$term = wp_get_object_terms( $post, Taxonomies::KEYWORD )[0];

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/' . Taxonomies::KEYWORD . '/' . $term->term_id ) );

		$this->assertSame( 401, $response->get_status() );
	}

	public function test_authors_can_list_keywords_through_rest(): void {
		$this->create_post( 'Best car of 2026', [ 'Refund' ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'author' ] ) );

		$response = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/' . Taxonomies::KEYWORD ) );

		$this->assertSame( 200, $response->get_status() );
		$this->assertSame( [ 'Refund' ], wp_list_pluck( $response->get_data(), 'name' ) );
	}

	public function test_authors_can_add_new_keywords(): void {
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'author' ] ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/' . Taxonomies::STOP );
		$request->set_param( 'name', 'Coupon' );

		$this->assertSame( 201, rest_do_request( $request )->get_status() );
	}
}

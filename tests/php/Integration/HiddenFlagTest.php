<?php

namespace SearchSteer\Tests\Integration;

use SearchSteer\Admin\EditorFields;
use SearchSteer\Settings;
use SearchSteer\Visibility\HiddenFlag;
use WP_REST_Request;

final class HiddenFlagTest extends TestCase {

	public function tear_down(): void {
		unset( $_POST[ EditorFields::FIELD ], $_POST[ EditorFields::FIELD_PRESENT ], $_POST[ EditorFields::NONCE ] );
		parent::tear_down();
	}

	public function test_a_hidden_post_is_left_out_even_when_its_title_matches(): void {
		$hidden = $this->create_post( 'Coupon terms' );
		$shown  = $this->create_post( 'Coupon questions' );
		HiddenFlag::set( $hidden, true );

		$this->assertSame( [ $shown ], $this->search( 'coupon' ) );
	}

	public function test_a_hidden_post_is_left_out_even_when_its_keyword_matches(): void {
		$this->set_settings( [ 'precedence' => Settings::PRECEDENCE_KEYWORD ] );
		$post = $this->create_post( 'Best car of 2026', [ 'Refund' ] );
		HiddenFlag::set( $post, true );

		$this->assertSame( [], $this->search( 'refund' ) );
	}

	public function test_showing_a_post_again_removes_the_flag(): void {
		$post = $this->create_post( 'Coupon terms' );
		HiddenFlag::set( $post, true );
		HiddenFlag::set( $post, false );

		$this->assertFalse( HiddenFlag::is_hidden( $post ) );
		$this->assertSame( [], get_post_meta( $post, HiddenFlag::META_KEY ) );
		$this->assertSame( [ $post ], $this->search( 'coupon' ) );
	}

	public function test_the_flag_has_no_effect_on_post_types_turned_off_in_the_settings(): void {
		$page = $this->create_post( 'Coupon partners', [], [], [ 'post_type' => 'page' ] );
		HiddenFlag::set( $page, true );
		$this->set_settings( [ 'post_types' => [ 'post' ] ] );

		$this->assertSame( [ $page ], $this->search( 'coupon' ) );
	}

	public function test_hidden_posts_stay_in_admin_searches(): void {
		$this->set_settings( [ 'admin_search' => true ] );
		$post = $this->create_post( 'Coupon terms' );
		HiddenFlag::set( $post, true );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		set_current_screen( 'edit-post' );
		require_once ABSPATH . 'wp-admin/includes/post.php';

		// Loaded as wp-admin does: go_to() would clear the current screen and search the front end.
		wp_edit_posts_query( [ 's' => 'coupon' ] );

		$this->assertTrue( is_admin() );
		$this->assertSame( [ $post ], array_map( 'intval', wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) ) );
	}

	public function test_visitors_cannot_see_the_flag_through_rest(): void {
		$post = $this->create_post( 'Coupon terms' );
		HiddenFlag::set( $post, true );

		$data = rest_do_request( new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post ) )->get_data();

		$this->assertArrayNotHasKey( HiddenFlag::REST_FIELD, $data );
	}

	public function test_editors_read_and_save_the_flag_through_rest(): void {
		$post = $this->create_post( 'Coupon terms' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$request = new WP_REST_Request( 'POST', '/wp/v2/posts/' . $post );
		$request->set_param( HiddenFlag::REST_FIELD, true );
		$this->assertSame( 200, rest_do_request( $request )->get_status() );
		$this->assertTrue( HiddenFlag::is_hidden( $post ) );

		$request = new WP_REST_Request( 'GET', '/wp/v2/posts/' . $post );
		$request->set_param( 'context', 'edit' );
		$this->assertTrue( rest_do_request( $request )->get_data()[ HiddenFlag::REST_FIELD ] );
	}

	public function test_the_classic_editor_box_and_quick_edit_save_the_flag(): void {
		$post = $this->create_post( 'Coupon terms' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$_POST[ EditorFields::FIELD_PRESENT ] = '1';
		$_POST[ EditorFields::NONCE ]         = wp_create_nonce( EditorFields::NONCE_ACTION );
		$_POST[ EditorFields::FIELD ]         = '1';
		wp_update_post( [ 'ID' => $post ] );
		$this->assertTrue( HiddenFlag::is_hidden( $post ) );

		unset( $_POST[ EditorFields::FIELD ] );
		wp_update_post( [ 'ID' => $post ] );
		$this->assertFalse( HiddenFlag::is_hidden( $post ) );
	}

	public function test_a_save_without_a_valid_nonce_changes_nothing(): void {
		$post = $this->create_post( 'Coupon terms' );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );

		$_POST[ EditorFields::FIELD_PRESENT ] = '1';
		$_POST[ EditorFields::NONCE ]         = 'forged';
		$_POST[ EditorFields::FIELD ]         = '1';
		wp_update_post( [ 'ID' => $post ] );

		$this->assertFalse( HiddenFlag::is_hidden( $post ) );
	}
}

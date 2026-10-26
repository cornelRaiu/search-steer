<?php

namespace SearchSteer\Tests\Integration;

use SearchSteer\Admin\PostList;
use SearchSteer\Keywords\Taxonomies;
use SearchSteer\Visibility\HiddenFlag;

final class PostListTest extends TestCase {

	public static function set_up_before_class(): void {
		parent::set_up_before_class();
		require_once ABSPATH . 'wp-admin/includes/post.php';
	}

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'editor' ] ) );
		set_current_screen( 'edit-post' );
		$GLOBALS['pagenow'] = 'edit.php';
	}

	public function tear_down(): void {
		unset( $_GET[ PostList::FILTER ], $_GET[ PostList::FILTER_TERM ], $_GET[ PostList::NOTICE_COUNT ], $_GET[ PostList::NOTICE_ACTION ] );
		$GLOBALS['pagenow'] = 'index.php';
		parent::tear_down();
	}

	/**
	 * @param array<string, string> $args
	 * @return int[]
	 */
	private function list_posts( array $args ): array {
		$_GET = $args;
		wp_edit_posts_query( $args );

		$ids = array_map( 'intval', wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ) );
		sort( $ids );

		return $ids;
	}

	public function test_the_list_filters_by_search_settings(): void {
		$with_keywords = $this->create_post( 'Best car of 2026', [ 'Refund' ] );
		$with_stop     = $this->create_post( 'Coupon questions', [], [ 'Coupon' ] );
		$hidden        = $this->create_post( 'Old news' );
		$plain         = $this->create_post( 'Plain post' );
		HiddenFlag::set( $hidden, true );

		$this->assertSame( [ $with_keywords ], $this->list_posts( [ PostList::FILTER => PostList::SHOW_KEYWORDS ] ) );
		$this->assertSame( [ $with_stop ], $this->list_posts( [ PostList::FILTER => PostList::SHOW_STOP ] ) );
		$this->assertSame( [ $hidden ], $this->list_posts( [ PostList::FILTER => PostList::SHOW_HIDDEN ] ) );
		$this->assertCount( 4, $this->list_posts( [] ) );
		$this->assertContains( $plain, $this->list_posts( [] ) );
	}

	public function test_the_list_filters_by_one_keyword(): void {
		$refund = $this->create_post( 'Best car of 2026', [ 'Refund' ] );
		$this->create_post( 'Family cars', [ 'Voucher' ] );
		$term = get_term_by( 'name', 'Refund', Taxonomies::KEYWORD );

		$this->assertSame( [ $refund ], $this->list_posts( [ PostList::FILTER_TERM => (string) $term->term_id ] ) );
	}

	public function test_hidden_posts_stay_in_the_list(): void {
		$hidden = $this->create_post( 'Old news' );
		HiddenFlag::set( $hidden, true );

		$this->assertSame( [ $hidden ], $this->list_posts( [] ) );
	}

	public function test_the_column_shows_the_search_settings_with_links(): void {
		$post = $this->create_post( 'Best car of 2026', [ 'Refund' ], [ 'Coupon' ] );
		HiddenFlag::set( $post, true );

		ob_start();
		PostList::render_column( PostList::COLUMN, $post );
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( 'data-hidden="1"', $html );
		$this->assertStringContainsString( 'Hidden from search', $html );
		$this->assertStringContainsString( '>Refund</a>', $html );
		$this->assertStringContainsString( '>Coupon</a>', $html );
		$this->assertStringContainsString( PostList::FILTER_TERM . '=', $html );
	}

	public function test_bulk_actions_hide_and_show_posts(): void {
		$posts = [ $this->create_post( 'One' ), $this->create_post( 'Two' ) ];

		$redirect = PostList::handle_bulk_action( 'edit.php', PostList::BULK_HIDE, $posts );

		$this->assertTrue( HiddenFlag::is_hidden( $posts[0] ) );
		$this->assertTrue( HiddenFlag::is_hidden( $posts[1] ) );
		$this->assertStringContainsString( PostList::NOTICE_COUNT . '=2', $redirect );

		PostList::handle_bulk_action( 'edit.php', PostList::BULK_SHOW, $posts );

		$this->assertFalse( HiddenFlag::is_hidden( $posts[0] ) );
	}

	public function test_bulk_actions_skip_posts_the_user_cannot_edit(): void {
		$others = $this->create_post( 'Not mine', [], [], [ 'post_author' => self::factory()->user->create( [ 'role' => 'editor' ] ) ] );
		wp_set_current_user( self::factory()->user->create( [ 'role' => 'contributor' ] ) );

		$redirect = PostList::handle_bulk_action( 'edit.php', PostList::BULK_HIDE, [ $others ] );

		$this->assertFalse( HiddenFlag::is_hidden( $others ) );
		$this->assertStringContainsString( PostList::NOTICE_COUNT . '=0', $redirect );
	}

	public function test_other_bulk_actions_are_left_alone(): void {
		$this->assertSame( 'edit.php', PostList::handle_bulk_action( 'edit.php', 'trash', [ 1 ] ) );
	}

	public function test_the_bulk_action_notice_shows_on_the_lists_of_chosen_post_types(): void {
		$_GET[ PostList::NOTICE_COUNT ]  = '2';
		$_GET[ PostList::NOTICE_ACTION ] = PostList::BULK_HIDE;

		ob_start();
		PostList::render_notice();
		$html = (string) ob_get_clean();

		$this->assertStringContainsString( '2 posts hidden from site search.', $html );
	}

	public function test_the_bulk_action_notice_stays_off_other_lists(): void {
		$_GET[ PostList::NOTICE_COUNT ]  = '2';
		$_GET[ PostList::NOTICE_ACTION ] = PostList::BULK_HIDE;
		set_current_screen( 'edit-attachment' );

		ob_start();
		PostList::render_notice();

		$this->assertSame( '', ob_get_clean() );
	}
}

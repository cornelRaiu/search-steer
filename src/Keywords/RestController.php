<?php

namespace SearchSteer\Keywords;

use WP_Error;
use WP_REST_Request;
use WP_REST_Terms_Controller;

defined( 'ABSPATH' ) || exit;

// Core lets anyone list the terms of a taxonomy that is in REST. Keywords, and stop keywords
// even more, are editorial notes, so only users who can assign them may read them.
final class RestController extends WP_REST_Terms_Controller {

	public function get_items_permissions_check( $request ) {
		$allowed = $this->check_can_read();

		return true === $allowed ? parent::get_items_permissions_check( $request ) : $allowed;
	}

	public function get_item_permissions_check( $request ) {
		$allowed = $this->check_can_read();

		return true === $allowed ? parent::get_item_permissions_check( $request ) : $allowed;
	}

	private function check_can_read() {
		$taxonomy = get_taxonomy( $this->taxonomy );

		if ( $taxonomy && current_user_can( $taxonomy->cap->assign_terms ) ) {
			return true;
		}

		return new WP_Error(
			'rest_forbidden',
			__( 'Sorry, you are not allowed to view these keywords.', 'search-steer' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}
}

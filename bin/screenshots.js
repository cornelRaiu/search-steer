#!/usr/bin/env node
/**
 * Takes the screenshots for the WordPress.org listing, into .wordpress-org/.
 *
 * Usage: WP_BASE_URL=https://example.test WP_CLI="wp --path=/path/to/site" npm run screenshots
 *
 * Run it against a test site with the plugin active and Twenty Twenty-Five as its theme.
 * It adds a demo editor with sample help-center posts, takes the pictures, and then deletes
 * the editor, the posts and their keywords and puts the plugin's settings back. The
 * captions of the screenshots are in readme.txt, in the same order.
 */
const crypto = require( 'node:crypto' );
const { execFileSync } = require( 'node:child_process' );
const path = require( 'node:path' );
const { chromium } = require( '@playwright/test' );

const base = ( process.env.WP_BASE_URL || 'http://localhost:8889' ).replace(
	/\/$/,
	''
);
const out = path.join( __dirname, '..', '.wordpress-org' );
const demo = {
	username: 'search-steer-demo',
	password: crypto.randomBytes( 16 ).toString( 'hex' ),
};

function wpEval( php ) {
	const [ bin, ...args ] = ( process.env.WP_CLI || 'wp' ).split( ' ' );

	return execFileSync( bin, [ ...args, 'eval', php ], {
		encoding: 'utf8',
		env: { ...process.env, XDEBUG_MODE: 'off' },
	} ).trim();
}

// Returns the demo editor's ID, the post to open in the editor, and the options to put back
// afterwards, serialized and base64-encoded so they pass through PHP source untouched.
function setUp() {
	return JSON.parse(
		wpEval( `
			use SearchSteer\\Keywords\\Taxonomies;
			use SearchSteer\\Settings;
			use SearchSteer\\Visibility\\HiddenFlag;

			$settings    = get_option( Settings::OPTION, null );
			$sep_exclude = get_option( 'sep_exclude', null );
			update_option( Settings::OPTION, Settings::defaults() );

			$user = get_user_by( 'login', '${ demo.username }' );
			$user = $user ? $user->ID : wp_insert_user( [
				'user_login'   => '${ demo.username }',
				'user_email'   => '${ demo.username }@example.com',
				'display_name' => 'Maya from support',
				'role'         => 'administrator',
				'user_pass'    => wp_generate_password(),
			] );
			wp_set_password( '${ demo.password }', $user );

			// Title, content, keywords, stop keywords, hidden, days ago.
			$posts = [
				[ 'Returns and exchanges', 'Send any item back within 30 days and we will exchange it or give your money back.', [ 'refund', 'money back', 'return policy' ], [], false, 2 ],
				[ 'Refund request form', 'Tell us your order number and we will send the money back to your card.', [], [], false, 4 ],
				[ 'Refund policy (2019)', 'Our old rules for refunds. Replaced by Returns and exchanges.', [], [ 'refund', 'return policy' ], false, 900 ],
				[ 'Track your parcel', 'Every order gets a tracking number by email when it ships.', [ 'tracking', 'where is my order', 'delivery' ], [], false, 6 ],
				[ 'Gift cards', 'Buy a gift card for any amount. It never expires.', [ 'voucher', 'present' ], [ 'discount code' ], false, 9 ],
				[ 'Holiday shipping dates', 'Order by December 18 to get your parcel before Christmas.', [ 'christmas', 'delivery' ], [], false, 30 ],
				[ 'Black Friday sale has ended', 'Thank you for a record weekend. See you next year.', [], [], true, 330 ],
				[ 'Thank you for your order', 'We have your order and will email you when it ships.', [], [], true, 400 ],
			];

			$ids = [];
			foreach ( $posts as list( $title, $content, $keywords, $stop, $hidden, $days_ago ) ) {
				$date = gmdate( 'Y-m-d H:i:s', time() - $days_ago * DAY_IN_SECONDS );
				$id   = wp_insert_post( [
					'post_title'    => $title,
					'post_content'  => '<!-- wp:paragraph --><p>' . $content . '</p><!-- /wp:paragraph -->',
					'post_status'   => 'publish',
					'post_author'   => $user,
					'post_date'     => get_date_from_gmt( $date ),
					'post_date_gmt' => $date,
				] );
				wp_set_object_terms( $id, $keywords, Taxonomies::KEYWORD );
				wp_set_object_terms( $id, $stop, Taxonomies::STOP );
				HiddenFlag::set( $id, $hidden );
				$ids[ $title ] = $id;
			}

			// Data of other plugins, for the import section of the settings page.
			update_option( 'sep_exclude', [ $ids['Black Friday sale has ended'], $ids['Thank you for your order'] ] );
			update_post_meta( $ids['Thank you for your order'], '_hide_from_search_wp', 1 );

			echo wp_json_encode( [
				'user'       => $user,
				'returns'    => $ids['Returns and exchanges'],
				'settings'   => base64_encode( serialize( $settings ) ),
				'sepExclude' => base64_encode( serialize( $sep_exclude ) ),
			] );
		` )
	);
}

function tearDown( data ) {
	wpEval( `
		use SearchSteer\\Keywords\\Taxonomies;
		use SearchSteer\\Settings;

		// Deleted for good: deleting the user would only move them to the trash.
		$posts = get_posts( [ 'author' => ${ Number( data.user ) }, 'post_type' => 'any', 'post_status' => array_keys( get_post_stati() ), 'numberposts' => -1, 'fields' => 'ids' ] );
		foreach ( $posts as $post_id ) {
			wp_delete_post( $post_id, true );
		}
		require_once ABSPATH . 'wp-admin/includes/user.php';
		wp_delete_user( ${ Number( data.user ) } );

		foreach ( [ Taxonomies::KEYWORD, Taxonomies::STOP ] as $taxonomy ) {
			foreach ( get_terms( [ 'taxonomy' => $taxonomy, 'hide_empty' => false ] ) as $term ) {
				if ( 0 === (int) $term->count ) {
					wp_delete_term( $term->term_id, $taxonomy );
				}
			}
		}

		$saved = [
			Settings::OPTION => '${ data.settings }',
			'sep_exclude'    => '${ data.sepExclude }',
		];
		foreach ( $saved as $option => $value ) {
			$value = unserialize( base64_decode( $value ), [ 'allowed_classes' => false ] );
			if ( null === $value ) {
				delete_option( $option );
			} else {
				update_option( $option, $value );
			}
		}
	` );
}

async function shoot( page, file, { selector, height = 800, margin = 24 } = {} ) {
	let top = 0;
	if ( selector ) {
		top = await page
			.locator( selector )
			.first()
			.evaluate(
				( element ) =>
					element.getBoundingClientRect().top + window.scrollY
			);
		top = Math.max( 0, top - margin );
	}
	const pageHeight = await page.evaluate(
		() => document.documentElement.scrollHeight
	);

	await page.screenshot( {
		path: path.join( out, file ),
		fullPage: true,
		clip: {
			x: 0,
			y: top,
			width: 1280,
			height: Math.min( height, Math.max( 1, pageHeight - top ) ),
		},
	} );
}

( async () => {
	const data = setUp();
	const browser = await chromium.launch();

	try {
		const context = await browser.newContext( {
			viewport: { width: 1280, height: 800 },
			deviceScaleFactor: 2,
			// Local test sites often have a certificate that only the system trusts.
			ignoreHTTPSErrors: true,
		} );
		// Local development tools, such as Laravel Herd, add their own toolbar to every page.
		await context.addInitScript( () => {
			document.addEventListener( 'DOMContentLoaded', () => {
				const style = document.createElement( 'style' );
				// The editor's "Most used" keywords would show the site's own keywords.
				style.textContent =
					'#herd-ai-root, #herd-ai-host, .editor-post-taxonomies__flat-term-most-used { display: none !important; }';
				document.head.appendChild( style );
			} );
		} );
		const page = await context.newPage();

		// 1: what visitors see.
		await page.goto( `${ base }/?s=refund` );
		await page.waitForLoadState( 'networkidle' );
		await shoot( page, 'screenshot-1.png', { height: 1000 } );

		await page.goto( `${ base }/wp-login.php` );
		await page.locator( '#user_login' ).fill( demo.username );
		await page.locator( '#user_pass' ).fill( demo.password );
		await page.locator( '#wp-submit' ).click();
		await page.waitForURL( /wp-admin/ );

		// 2: the editor sidebar, with the plugin's panels open and the others closed.
		await page.goto(
			`${ base }/wp-admin/post.php?post=${ data.returns }&action=edit`
		);
		await page.waitForFunction(
			() =>
				window.wp?.data?.select( 'core/editor' )?.getCurrentPostId() &&
				document.querySelector( '.editor-sidebar' )
		);
		await page.evaluate( () => {
			const { dispatch, select } = window.wp.data;
			dispatch( 'core/preferences' ).set(
				'core/edit-post',
				'welcomeGuide',
				false
			);
			dispatch( 'core/edit-post' ).openGeneralSidebar(
				'edit-post/document'
			);
			const open = [
				'search-steer/search-steer',
				'taxonomy-panel-mwtss_keyword',
				'taxonomy-panel-mwtss_stop_keyword',
			];
			const closed = [
				'post-status',
				'featured-image',
				'post-excerpt',
				'discussion-panel',
				'taxonomy-panel-category',
				'taxonomy-panel-post_tag',
			];
			for ( const panel of [ ...open, ...closed ] ) {
				if (
					select( 'core/editor' ).isEditorPanelOpened( panel ) !==
					open.includes( panel )
				) {
					dispatch( 'core/editor' ).toggleEditorPanelOpened( panel );
				}
			}
		} );
		await page.locator( 'text=Hide from site search' ).waitFor();
		// Scroll the sidebar to the plugin's panels.
		await page.evaluate( () => {
			[ ...document.querySelectorAll( '.editor-sidebar .components-panel__body-title' ) ]
				.find( ( title ) => title.textContent.includes( 'Site search' ) )
				?.scrollIntoView( { block: 'start' } );
		} );
		await page.waitForTimeout( 500 );
		await shoot( page, 'screenshot-2.png' );

		// 3: the post list, with the Search column and its filter.
		await page.goto( `${ base }/wp-admin/edit.php?author=${ data.user }` );
		await shoot( page, 'screenshot-3.png', { height: 760 } );

		// 4: quick edit.
		const row = page.locator( `#post-${ data.returns }` );
		await row.hover();
		await row.locator( 'button.editinline' ).click();
		await page
			.locator( `#edit-${ data.returns } input[name="mwtss_hide_from_search"]` )
			.waitFor();
		await shoot( page, 'screenshot-4.png', {
			selector: `#edit-${ data.returns }`,
			height: 520,
			margin: 80,
		} );

		// 5 and 6: the settings, and the import from other plugins.
		await page.goto(
			`${ base }/wp-admin/options-general.php?page=mwtss-settings`
		);
		await shoot( page, 'screenshot-5.png', { height: 1000 } );
		await shoot( page, 'screenshot-6.png', {
			selector: 'h2:has-text("Import hidden posts")',
			height: 420,
		} );
	} finally {
		await browser.close();
		tearDown( data );
	}
} )();

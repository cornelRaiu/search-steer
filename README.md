# Search Steer

WordPress plugin that steers native search per post: **hide from site search** keeps a post out of every search, **search keywords** bring a post into the results, **stop keywords** keep it out.

The WordPress.org readme is [readme.txt](readme.txt).

## How it works

- Keywords and stop keywords are terms of two private taxonomies (`mwtss_keyword`, `mwtss_stop_keyword`). Editors set them in the block editor's tag panel, quick edit or bulk edit.
- `SearchFilter` hooks `posts_search` on the main search query. It ORs in the posts whose keywords match and ANDs out the posts whose stop keywords match, with term-relationship subqueries. `posts_search_orderby` lists keyword matches first.
- `Matcher` decides which keywords a search matches, in whole-word or partial mode, ignoring case and accents.
- `RestController` hides the terms from REST users who cannot assign them.
- `HiddenFlag` stores "hide from site search" as the `_mwtss_hidden` post meta, present only while the post is hidden. The search filter excludes hidden posts outside wp-admin. The block editor reads and saves it through the `mwtss_hidden` REST field, which is only returned in the edit context.
- `EditorFields` adds the toggle to the block editor sidebar (`assets/js/editor.js`, plain script, no build step) and a box to the classic editor. `PostList` adds the Search column, the filter, the quick edit checkbox (`assets/js/quick-edit.js`) and the bulk actions.
- `Importer` reads the hidden posts of Search Exclude (`qlse_settings`, `sep_exclude`), Hide from Search (`_hide_from_search_wp`, `_mpress_hide_from_search`) and Hide Posts (`{prefix}whp_posts_visibility`, `_whp_hide_on_search`) without changing them.

## Requirements

- PHP 7.4 or later and Composer 2
- A MySQL or MariaDB server for the PHP tests
- WordPress 6.6 or later on the sites that run the plugin

## Setup

```bash
composer install
```

The PHP tests need their own empty database. The test suite deletes everything in it on every run, so never point it at a real site.

```bash
mysql -u root -e "CREATE DATABASE mwtss_tests"
```

Connection settings come from environment variables:

| Variable | Default |
|---|---|
| `WP_TESTS_DB_NAME` | `mwtss_tests` |
| `WP_TESTS_DB_USER` | `root` |
| `WP_TESTS_DB_PASSWORD` | empty |
| `WP_TESTS_DB_HOST` | `127.0.0.1` |

To change them for one machine, create `tests/php/wp-tests-config.local.php`, which git ignores:

```php
<?php
putenv( 'WP_TESTS_DB_HOST=127.0.0.1:33061' );
```

## Commands

| Command | What it does |
|---|---|
| `composer lint` / `composer lint:fix` | WordPress coding standards |
| `composer check:names` | Every `mwtss_` identifier is defined once, as a class constant |
| `composer analyse` | PHPStan, level 5 |
| `composer test` | PHPUnit, unit and integration suites |
| `composer ci` | All of the above |
| `bin/build-zip.sh` | Builds `dist/search-steer/` and `dist/search-steer.zip` for WordPress.org |
| `npm run assets` | Renders the icon and banners in `.wordpress-org/` from `icon.svg` and `src/banner.html` |
| `npm run screenshots` | Takes the listing screenshots on a test site (see below) |

## WordPress.org assets

`.wordpress-org/` holds what goes into the SVN `assets/` folder: the icon, the banners, the screenshots and the Live Preview blueprint (`blueprints/blueprint.json`). The screenshot captions are in `readme.txt`, in the same order.

The assets need `npm install` and Playwright's Chromium (`npx playwright install chromium`). The banner loads its font from Google Fonts.

The screenshots need a test site with the plugin active and Twenty Twenty-Five as its theme. The script adds a demo administrator and sample posts, takes the pictures, and deletes them again:

```bash
WP_BASE_URL=https://example.test WP_CLI="wp --path=/path/to/site" npm run screenshots
```

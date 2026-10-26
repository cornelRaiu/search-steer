=== Search Steer ===
Contributors: cornelraiu-1
Tags: search, exclude from search, hide from search, search keywords, search results
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Hide posts from site search, or steer search per post: keywords bring a post into the results, stop keywords keep it out.

== Description ==

WordPress search only looks at a post's title, excerpt and content. Search Steer gives you three controls, post by post:

* **Hide from site search** keeps a post out of every search. Use it for thank-you pages, landing pages, old announcements and anything else visitors should not find by searching.
* **Search keywords** bring a post into the results. Give "Returns and exchanges" the keywords refund and money back, and a search for "refund" finds it, even though the word is nowhere in the post.
* **Stop keywords** keep a post out of the results for some searches only. Give an old "Refund policy (2019)" the stop keyword refund, and a search for "refund" never shows it, even though its title matches.

Set them in the editor sidebar, in quick edit, or for many posts at once with bulk edit and the "Hide from site search" bulk action. A Search column and a filter on the post lists show which posts have search settings.

= Moving from another plugin =

Search Steer imports the posts that **Search Exclude**, **Hide from Search** and **Hide Posts** hide from search, with one click under Settings > Search Steer. The other plugin's data is not changed, so you can import, check, and then deactivate it.

= Built to stay out of the way =

* **Only the search query is changed.** Menus, archives, widgets, related posts and every other query on the page are left alone.
* **Nothing is hidden in wp-admin.** Hidden posts stay in the post lists and in admin searches, so editors can always find them.
* **No search index, no custom tables, no migrations.** Settings live in post meta and private taxonomy terms that WordPress already indexes, so they scale to thousands of posts and nothing is lost on update.
* **No ads, no dashboard widgets, no upsells, no outside service.**
* **Safe SQL.** Every column is qualified with its table, so search keeps working when another plugin joins post meta.
* **Private.** Visitors never see a post's keywords or that it is hidden: no archive pages, feeds, sitemaps or CSS classes, and the REST API only shows them to people who can edit posts.

= Settings =

Under Settings > Search Steer you choose:

* **Post types** that can have search settings. Posts and pages by default.
* **How keywords match**, separately for keywords and stop keywords:
  * Whole words (default): "gift" matches "gift card" but not "gifts".
  * Partial words: "refund" also matches "refu" and "refunds".
* **Which list wins** when a search matches both a keyword and a stop keyword of the same post. Stop keywords win by default.
* **Order**: list posts that match by keyword first.
* **Where** search settings apply: always on the search page, and optionally in REST API and AJAX searches and in the admin post lists.

Case and accents are ignored, so "Café", "cafe" and "CAFE" are the same keyword.

= For developers =

Search settings apply to the main search query on the front end. To apply them to another query, use the filter:

`add_filter( 'mwtss_applies_to_query', function ( $applies, $query ) { return $applies || $query->get( 'my_live_search' ); }, 10, 2 );`

To turn them off for one query, pass `'mwtss_skip' => true` to `WP_Query`.

== Installation ==

1. Install and activate the plugin from Plugins > Add New.
2. Open a post and use the Site search panel and the keyword panels in the sidebar.
3. Optional: change the matching and other options under Settings > Search Steer.

== Frequently Asked Questions ==

= Does a hidden post disappear from my site? =

No. It only stays out of your site's search results. It is still listed in menus, archives and widgets, and reachable at its own address.

= Does it hide posts from Google? =

No. Search Steer controls the search on your own site. To keep a page out of search engines, use your SEO plugin's "noindex" setting.

= Why does a hidden post still show in my live search? =

Live search boxes often search through the REST API or admin-ajax.php. Tick "Also apply them to REST API and AJAX searches" in the settings. Live search plugins that run their own queries can call the `mwtss_applies_to_query` filter, described below.

= Does it work with Relevanssi, SearchWP, Jetpack Search or ElasticPress? =

No. Those plugins replace WordPress's own search query with their own index, and Search Steer changes WordPress's own query. Use their exclusion features instead.

= Does it work with WooCommerce? =

Yes, for the product search on the shop's search page, which uses WordPress's own query. Choose Products under Post types in the settings.

= How do I import from Search Exclude, Hide from Search or Hide Posts? =

Go to Settings > Search Steer and click the import button for your plugin. The section shows only the plugins that have hidden posts on your site. Search Exclude can also hide every post of a type, a category or an author. Those settings are listed but not imported: select the posts in their list and use the "Hide from site search" bulk action.

= Does a search need to match every word? =

No. A post is added when any keyword matches the search. With the keyword refund, a search for "refund status" finds the post.

= Can visitors see the keywords? =

No. Keywords have no public pages and are not printed anywhere on the site. Through the REST API, only users who can edit posts can read them.

= What happens to my settings when I delete the plugin? =

They are kept, unless you tick "Delete all keywords, stop keywords, hidden flags and settings when the plugin is deleted" in the settings first.

== Screenshots ==

1. A search for "refund": the returns page is found by its keyword and listed first, and the out-of-date 2019 refund policy is kept out by its stop keyword.
2. The editor sidebar: hide the post from site search, and add its search keywords and stop keywords.
3. The Search column shows each post's keywords, stop keywords and whether it is hidden, and the filter lists the posts that have them.
4. Quick edit changes keywords, stop keywords and "Hide from site search" without opening the post.
5. The settings: post types, how keywords match, which list wins, the order of the results and where search settings apply.
6. Import the posts that Search Exclude, Hide from Search or Hide Posts hide from search, with one click.

== Changelog ==

= 1.0.0 =
* First release.

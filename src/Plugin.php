<?php

namespace SearchSteer;

use SearchSteer\Admin\EditorFields;
use SearchSteer\Admin\ImportSection;
use SearchSteer\Admin\PostList;
use SearchSteer\Admin\SettingsPage;
use SearchSteer\Keywords\Taxonomies;
use SearchSteer\Search\SearchFilter;
use SearchSteer\Visibility\HiddenFlag;

defined( 'ABSPATH' ) || exit;

final class Plugin {

	public const VERSION = '1.0.0';

	private static string $file = '';

	public static function boot( string $file ): void {
		self::$file = $file;

		// No arguments: init passes an empty string, and register() takes an optional list of post types.
		add_action( 'init', [ Taxonomies::class, 'register' ], 10, 0 );

		SearchFilter::add_hooks();
		HiddenFlag::add_hooks();
		EditorFields::add_hooks();
		PostList::add_hooks();
		SettingsPage::add_hooks();
		ImportSection::add_hooks();
	}

	public static function file(): string {
		return self::$file;
	}

	public static function url( string $relative ): string {
		return plugins_url( $relative, self::$file );
	}
}

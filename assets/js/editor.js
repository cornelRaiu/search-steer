// "Hide from site search" in the block editor sidebar. A plain script on the wp globals, so the plugin needs no build step.
( function ( wp, config ) {
	'use strict';

	const el = wp.element.createElement;
	const { __ } = wp.i18n;
	const { useSelect, useDispatch } = wp.data;

	function HideFromSearchPanel() {
		const hidden = useSelect(
			( select ) => !! select( 'core/editor' ).getEditedPostAttribute( config.field ),
			[]
		);
		const { editPost } = useDispatch( 'core/editor' );

		return el(
			wp.editor.PluginDocumentSettingPanel,
			{
				name: 'search-steer',
				title: __( 'Site search', 'search-steer' ),
			},
			el( wp.components.ToggleControl, {
				__nextHasNoMarginBottom: true,
				label: __( 'Hide from site search', 'search-steer' ),
				help: __(
					"The post never shows in your site's search results. It stays reachable at its own address.",
					'search-steer'
				),
				checked: hidden,
				onChange: ( value ) => editPost( { [ config.field ]: value } ),
			} )
		);
	}

	wp.plugins.registerPlugin( 'search-steer', { render: HideFromSearchPanel } );
} )( window.wp, window.searchSteerEditor );

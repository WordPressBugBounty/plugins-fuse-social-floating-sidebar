/**
 * Fuse Social Icons block — hand-written against WordPress core globals,
 * no npm/build step. Server-rendered (see render.php); the editor just
 * shows a live preview via ServerSideRender and exposes Inspector controls.
 */
( function ( wp ) {
	var el = wp.element.createElement;
	var registerBlockType = wp.blocks.registerBlockType;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var PanelBody = wp.components.PanelBody;
	var SelectControl = wp.components.SelectControl;
	var ServerSideRender = wp.serverSideRender;
	var __ = wp.i18n.__;

	registerBlockType( 'fuse/social-icons', {
		edit: function ( props ) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var blockProps = useBlockProps();

			return el(
				'div',
				blockProps,
				el(
					InspectorControls,
					{},
					el(
						PanelBody,
						{ title: __( 'Fuse Social Icons', 'fuse-social-floating-sidebar' ) },
						el( SelectControl, {
							label: __( 'Display', 'fuse-social-floating-sidebar' ),
							value: attributes.mode,
							options: [
								{ label: __( 'Profile links', 'fuse-social-floating-sidebar' ), value: 'profile' },
								{ label: __( 'Share buttons (current page)', 'fuse-social-floating-sidebar' ), value: 'share' },
							],
							onChange: function ( value ) {
								setAttributes( { mode: value } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Shape override', 'fuse-social-floating-sidebar' ),
							value: attributes.shapeOverride,
							options: [
								{ label: __( 'Use global setting', 'fuse-social-floating-sidebar' ), value: '' },
								{ label: __( 'Square', 'fuse-social-floating-sidebar' ), value: 'square' },
								{ label: __( 'Round', 'fuse-social-floating-sidebar' ), value: 'round' },
							],
							onChange: function ( value ) {
								setAttributes( { shapeOverride: value } );
							},
						} ),
						el( SelectControl, {
							label: __( 'Size override', 'fuse-social-floating-sidebar' ),
							value: attributes.sizeOverride,
							options: [
								{ label: __( 'Use global setting', 'fuse-social-floating-sidebar' ), value: '' },
								{ label: __( 'Small', 'fuse-social-floating-sidebar' ), value: 'sm' },
								{ label: __( 'Medium', 'fuse-social-floating-sidebar' ), value: 'md' },
								{ label: __( 'Large', 'fuse-social-floating-sidebar' ), value: 'lg' },
							],
							onChange: function ( value ) {
								setAttributes( { sizeOverride: value } );
							},
						} )
					)
				),
				el( ServerSideRender, {
					block: 'fuse/social-icons',
					attributes: attributes,
				} )
			);
		},
		save: function () {
			return null;
		},
	} );
} )( window.wp );

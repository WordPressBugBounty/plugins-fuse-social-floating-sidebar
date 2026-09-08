<?php
/**
 * Server-side render for the fuse/social-icons block.
 *
 * @var array $attributes
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$settings = Fuse_Settings::get();
$mode     = in_array( $attributes['mode'] ?? '', array( 'profile', 'share' ), true ) ? $attributes['mode'] : 'profile';

// Block attributes are stored in post content (editable via the Code Editor),
// so they bypass Fuse_Settings::sanitize()/enforce_free_tier() entirely — a
// crafted shapeOverride could otherwise render a Pro-only shape for free.
// Validate against the same allowed set the settings screen enforces for
// this context/tier before ever passing it to the renderer.
$is_pro = Fuse_Pro::is_pro();
if ( 'share' === $mode ) {
	$allowed_shapes = $is_pro ? array( 'round', 'rounded', 'square', 'outline' ) : array( 'square' );
} else {
	$allowed_shapes = $is_pro ? array( 'round', 'rounded', 'square', 'outline' ) : array( 'round', 'square' );
}

$render_args = array( 'mode' => 'inline' );
if ( ! empty( $attributes['shapeOverride'] ) && in_array( $attributes['shapeOverride'], $allowed_shapes, true ) ) {
	$render_args['shape'] = $attributes['shapeOverride'];
}
if ( ! empty( $attributes['sizeOverride'] ) ) {
	$size_px               = array( 'sm' => 26, 'md' => 34, 'lg' => 44 );
	$render_args['size']   = $size_px[ $attributes['sizeOverride'] ] ?? 34;
}

$wrapper_attributes = get_block_wrapper_attributes( array( 'class' => 'fsi-block' ) );

if ( 'share' === $mode ) {
	// The block forces share buttons on even if auto-insert placements are off.
	$settings['share']['enabled'] = true;
	$url   = is_singular() ? get_permalink() : home_url();
	$title = is_singular() ? get_the_title() : get_bloginfo( 'name' );
	$inner = Fuse_Renderer::render_share_buttons( $settings, $url, $title, $render_args );
} else {
	$inner = Fuse_Renderer::render_profile_icons( $settings, $render_args );
}

printf( '<div %1$s>%2$s</div>', $wrapper_attributes, $inner ); // phpcs:ignore WordPress.Security.EscapeOutput

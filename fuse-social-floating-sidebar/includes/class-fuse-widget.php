<?php
/**
 * WP_Widget: places the icon group (profile links or share buttons) into
 * any registered widget area (sidebar, footer, etc).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fuse_Social_Icons_Widget extends WP_Widget {

	public function __construct() {
		parent::__construct(
			'fuse_social_icons_widget',
			__( 'Fuse Social Icons', 'fuse-social-floating-sidebar' ),
			array( 'description' => __( 'Displays your social profile links or share buttons.', 'fuse-social-floating-sidebar' ) )
		);
	}

	public function widget( $args, $instance ) {
		$settings = Fuse_Settings::get();
		$mode     = ! empty( $instance['mode'] ) ? $instance['mode'] : 'profile';
		$title    = ! empty( $instance['title'] ) ? $instance['title'] : '';

		echo $args['before_widget']; // phpcs:ignore WordPress.Security.EscapeOutput

		if ( $title ) {
			echo $args['before_title'] . esc_html( $title ) . $args['after_title']; // phpcs:ignore
		}

		if ( 'share' === $mode ) {
			// Explicit placement always renders, even if global auto-insert is off.
			$settings['share']['enabled'] = true;
			echo Fuse_Renderer::render_share_buttons( $settings, get_permalink(), get_the_title(), array( 'wrap_class' => 'fsi-widget' ) ); // phpcs:ignore
		} else {
			echo Fuse_Renderer::render_profile_icons( $settings, array( 'mode' => 'inline', 'wrap_class' => 'fsi-widget' ) ); // phpcs:ignore
		}

		echo $args['after_widget']; // phpcs:ignore
	}

	public function form( $instance ) {
		$title = ! empty( $instance['title'] ) ? $instance['title'] : '';
		$mode  = ! empty( $instance['mode'] ) ? $instance['mode'] : 'profile';
		?>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"><?php esc_html_e( 'Title:', 'fuse-social-floating-sidebar' ); ?></label>
			<input class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>" type="text" value="<?php echo esc_attr( $title ); ?>">
		</p>
		<p>
			<label for="<?php echo esc_attr( $this->get_field_id( 'mode' ) ); ?>"><?php esc_html_e( 'Display:', 'fuse-social-floating-sidebar' ); ?></label>
			<select class="widefat" id="<?php echo esc_attr( $this->get_field_id( 'mode' ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( 'mode' ) ); ?>">
				<option value="profile" <?php selected( $mode, 'profile' ); ?>><?php esc_html_e( 'Profile links', 'fuse-social-floating-sidebar' ); ?></option>
				<option value="share" <?php selected( $mode, 'share' ); ?>><?php esc_html_e( 'Share buttons (current page)', 'fuse-social-floating-sidebar' ); ?></option>
			</select>
		</p>
		<?php
	}

	public function update( $new_instance, $old_instance ) {
		return array(
			'title' => sanitize_text_field( $new_instance['title'] ?? '' ),
			'mode'  => in_array( $new_instance['mode'] ?? '', array( 'profile', 'share' ), true ) ? $new_instance['mode'] : 'profile',
		);
	}

	public static function register() {
		register_widget( __CLASS__ );
	}
}

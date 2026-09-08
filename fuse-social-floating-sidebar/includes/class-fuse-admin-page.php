<?php
/**
 * Tabbed settings screen. All settings live in a single form (submitted via the
 * HTML5 `form="fsi-form"` association so standalone tool actions can render as
 * their own forms without nesting). Tabs are switched client-side.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Fuse_Admin_Page {

	const MENU_SLUG = 'fuse-social-floating-sidebar';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'register_menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_assets' ) );
		add_action( 'admin_post_fuse_save_settings', array( __CLASS__, 'handle_save' ) );
		add_action( 'admin_post_fuse_run_migration', array( __CLASS__, 'handle_run_migration' ) );
		add_action( 'admin_post_fuse_export_settings', array( __CLASS__, 'handle_export' ) );
		add_action( 'admin_post_fuse_import_settings', array( __CLASS__, 'handle_import' ) );
		add_action( 'admin_post_fuse_reset_settings', array( __CLASS__, 'handle_reset' ) );
	}

	public static function register_menu() {
		add_menu_page(
			__( 'Fuse Social Icons', 'fuse-social-floating-sidebar' ),
			__( 'Fuse Social', 'fuse-social-floating-sidebar' ),
			'manage_options',
			self::MENU_SLUG,
			array( __CLASS__, 'render' ),
			'dashicons-share',
			66
		);
	}

	public static function enqueue_assets( $hook ) {
		if ( strpos( (string) $hook, self::MENU_SLUG ) === false ) {
			return;
		}

		wp_enqueue_style( 'wp-color-picker' );
		wp_enqueue_script( 'jquery-ui-sortable' );

		wp_enqueue_style(
			'fuse-social-icons-fonts',
			'https://fonts.googleapis.com/css2?family=Inter:wght@400;450;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap',
			array(),
			FUSE_SOCIAL_ICONS_VERSION
		);
		// Frontend styles too, so the in-admin previews (block mock, share row)
		// render exactly like the real thing.
		wp_enqueue_style( 'fuse-social-icons-frontend', FUSE_SOCIAL_ICONS_URL . 'assets/css/frontend.css', array(), FUSE_SOCIAL_ICONS_VERSION );
		wp_enqueue_style( 'fuse-social-icons-admin', FUSE_SOCIAL_ICONS_URL . 'assets/css/admin.css', array( 'fuse-social-icons-frontend' ), FUSE_SOCIAL_ICONS_VERSION );
		wp_enqueue_script( 'fuse-social-icons-admin', FUSE_SOCIAL_ICONS_URL . 'assets/js/admin.js', array( 'jquery', 'wp-color-picker', 'jquery-ui-sortable' ), FUSE_SOCIAL_ICONS_VERSION, true );
	}

	/* ------------------------------------------------------------------ *
	 * Handlers
	 * ------------------------------------------------------------------ */

	private static function guard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'fuse-social-floating-sidebar' ) );
		}
	}

	private static function redirect( $args ) {
		wp_safe_redirect( add_query_arg( array_merge( array( 'page' => self::MENU_SLUG ), $args ), admin_url( 'admin.php' ) ) );
		exit;
	}

	public static function handle_save() {
		self::guard();
		check_admin_referer( 'fuse_save_settings' );

		$input = wp_unslash( $_POST['fuse'] ?? array() );
		Fuse_Settings::update( Fuse_Settings::sanitize( $input ) );

		$tab = isset( $_POST['fuse_active_tab'] ) ? sanitize_key( wp_unslash( $_POST['fuse_active_tab'] ) ) : 'profile';
		self::redirect( array( 'tab' => $tab, 'updated' => 1 ) );
	}

	public static function handle_run_migration() {
		self::guard();
		check_admin_referer( 'fuse_run_migration' );
		$report = Fuse_Migrator::run();
		self::redirect( array( 'tab' => 'advanced', 'migrated' => $report ? 1 : 0 ) );
	}

	public static function handle_reset() {
		self::guard();
		check_admin_referer( 'fuse_reset_settings' );
		delete_option( Fuse_Settings::OPTION_KEY );
		self::redirect( array( 'tab' => 'advanced', 'reset' => 1 ) );
	}

	public static function handle_export() {
		self::guard();
		check_admin_referer( 'fuse_export_settings' );
		$settings = Fuse_Settings::get();
		unset( $settings['_migration_report'] );
		nocache_headers();
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="fuse-social-icons-settings.json"' );
		echo wp_json_encode( $settings, JSON_PRETTY_PRINT );
		exit;
	}

	public static function handle_import() {
		self::guard();
		check_admin_referer( 'fuse_import_settings' );
		$imported = 0;
		if ( ! empty( $_FILES['fuse_import_file']['tmp_name'] ) && is_uploaded_file( $_FILES['fuse_import_file']['tmp_name'] ) ) {
			$data = json_decode( file_get_contents( $_FILES['fuse_import_file']['tmp_name'] ), true ); // phpcs:ignore
			if ( is_array( $data ) ) {
				Fuse_Settings::update( Fuse_Settings::sanitize( $data ) );
				$imported = 1;
			}
		}
		self::redirect( array( 'tab' => 'advanced', 'imported' => $imported ) );
	}

	/* ------------------------------------------------------------------ *
	 * Shared field helpers
	 * ------------------------------------------------------------------ */

	private static function toggle( $name, $checked, $attrs = '' ) {
		// No static on/off class here — the visual state is driven purely by
		// the :checked selector so it always tracks the real checkbox.
		printf(
			'<label class="fsi-tg"><input type="checkbox" form="fsi-form" name="%1$s" value="1" %2$s %3$s><span class="fsi-tg-track"><span class="fsi-tg-kn"></span></span></label>',
			esc_attr( $name ),
			checked( $checked, true, false ),
			$attrs // phpcs:ignore
		);
	}

	private static function toggle_row( $name, $checked, $title, $desc = '', $pro = false ) {
		$locked = $pro && ! Fuse_Pro::is_pro();
		echo '<div class="fsi-frow">';
		echo '<div class="fsi-ft">' . esc_html( $title );
		if ( $pro ) {
			echo ' ' . self::pro_badge(); // phpcs:ignore
		}
		if ( $desc ) {
			echo '<div class="fsi-fd">' . esc_html( $desc ) . '</div>';
		}
		echo '</div>';
		if ( $locked ) {
			echo '<span class="fsi-pro-field" data-pro-open><span class="fsi-pro-pill">' . self::lock_icon() . '</span>'; // phpcs:ignore
			self::toggle( $name, false, 'disabled' );
			echo '</span>';
		} else {
			self::toggle( $name, $checked );
		}
		echo '</div>';
	}

	/**
	 * @param bool|array $pro `true` locks the whole select (every option is
	 *                        Pro-only — e.g. vertical align). An array of
	 *                        option keys locks just those options, leaving
	 *                        the rest of the select fully usable — e.g. hover
	 *                        effect, where "None"/"Rotate" stay free.
	 */
	private static function select_row( $name, $title, $options, $current, $desc = '', $pro = false ) {
		$locked      = ! Fuse_Pro::is_pro();
		$whole_locked = $locked && true === $pro;
		$partial_pro  = ( $locked && is_array( $pro ) ) ? $pro : array();

		echo '<div class="fsi-frow"><div class="fsi-ft">' . esc_html( $title );
		if ( $whole_locked || $partial_pro ) {
			echo ' ' . self::pro_badge(); // phpcs:ignore
		}
		if ( $desc ) {
			echo '<div class="fsi-fd">' . esc_html( $desc ) . '</div>';
		}
		echo '</div>';

		if ( $whole_locked ) {
			echo '<span class="fsi-pro-field" data-pro-open><span class="fsi-pro-pill">' . self::lock_icon() . esc_html__( 'Unlock with Pro', 'fuse-social-floating-sidebar' ) . '</span>';
		}
		printf( '<select form="fsi-form" name="%s" %s>', esc_attr( $name ), $whole_locked ? 'disabled' : '' ); // phpcs:ignore
		foreach ( $options as $val => $label ) {
			$opt_locked = in_array( $val, $partial_pro, true );
			printf(
				'<option value="%s" %s %s>%s</option>',
				esc_attr( $val ),
				selected( $current, $val, false ),
				$opt_locked ? 'disabled' : '', // phpcs:ignore
				esc_html( $opt_locked ? $label . ' — ' . __( 'Pro', 'fuse-social-floating-sidebar' ) : $label )
			);
		}
		echo '</select>';
		if ( $whole_locked ) {
			echo '</span>';
		}
		if ( $partial_pro ) {
			self::unlock_button( __( 'More options need Pro.', 'fuse-social-floating-sidebar' ) );
		}
		echo '</div>';
	}

	private static function segmented( $name, $options, $current ) {
		$is_pro = Fuse_Pro::is_pro();
		echo '<div class="fsi-seg">';
		foreach ( $options as $val => $opt ) {
			if ( isset( $opt['radius'] ) ) {
				$swatch = '<span class="fsi-shp" style="border-radius:' . esc_attr( $opt['radius'] ) . '"></span>';
			} elseif ( ! empty( $opt['outline'] ) ) {
				// Outline is a hollow border style, not a fill — reusing the
				// filled radius swatch would misrepresent it as a solid circle.
				$swatch = '<span class="fsi-shp-outline"></span>';
			} elseif ( isset( $opt['preview'] ) ) {
				// Edge-position preview: a mini frame with a bar on the matching side.
				$swatch = '<span class="fsi-shp-pos fsi-shp-pos-' . esc_attr( $opt['preview'] ) . '"><span class="fsi-shp-bar"></span></span>';
			} else {
				$swatch = '';
			}

			// Pro-only options stay visible (never hidden) with a translucent
			// lock overlay + click-to-upgrade, so free users can see exactly
			// what they'd unlock — the free options right next to it stay
			// fully usable.
			$opt_locked = ! $is_pro && ! empty( $opt['pro'] );
			$lock       = $opt_locked ? self::lock_icon() : '';

			printf(
				'<label class="fsi-segopt %3$s%7$s" %8$s><input type="radio" form="fsi-form" name="%1$s" value="%2$s" %4$s %9$s>%5$s<span>%6$s</span>%10$s</label>',
				esc_attr( $name ),
				esc_attr( $val ),
				$current === $val ? 'on' : '',
				checked( $current, $val, false ),
				$swatch, // phpcs:ignore
				esc_html( $opt['label'] ),
				$opt_locked ? ' fsi-pro-opt' : '',
				$opt_locked ? 'data-pro-open' : '', // phpcs:ignore
				$opt_locked ? 'disabled' : '', // phpcs:ignore
				$opt_locked ? '<span class="fsi-pro-lock">' . $lock . '</span>' : '' // phpcs:ignore
			);
		}
		echo '</div>';
	}

	private static function slider( $name, $value, $min, $max, $step, $unit, $label, $pro = false ) {
		$locked = $pro && ! Fuse_Pro::is_pro();
		$badge  = $pro ? ' ' . self::pro_badge() : '';
		printf(
			'<div class="fsi-stack"><div class="fsi-slabel">%1$s%8$s <span class="fsi-val" data-unit="%2$s">%3$s%2$s</span></div>%9$s<input type="range" form="fsi-form" class="fsi-range" name="%4$s" min="%5$s" max="%6$s" step="%7$s" value="%3$s" %10$s>%11$s</div>',
			esc_html( $label ),
			esc_attr( $unit ),
			esc_attr( $value ),
			esc_attr( $name ),
			esc_attr( $min ),
			esc_attr( $max ),
			esc_attr( $step ),
			$badge, // phpcs:ignore
			$locked ? '<span class="fsi-pro-field block" data-pro-open><span class="fsi-pro-pill">' . self::lock_icon() . esc_html__( 'Unlock with Pro', 'fuse-social-floating-sidebar' ) . '</span>' : '', // phpcs:ignore
			$locked ? 'disabled' : '', // phpcs:ignore
			$locked ? '</span>' : '' // phpcs:ignore
		);
	}

	/**
	 * WordPress (iris) color picker field. The panel is repositioned as an
	 * absolute overlay via CSS so opening it never pushes the layout down.
	 */
	private static function color_field( $name, $value, $label, $disabled = false ) {
		printf(
			'<div class="fsi-field fsi-field-color"><label>%1$s</label><input type="text" form="fsi-form" class="fsi-color" name="%2$s" value="%3$s" data-default-color="%3$s" %4$s></div>',
			esc_html( $label ),
			esc_attr( $name ),
			esc_attr( $value ),
			$disabled ? 'disabled' : '' // phpcs:ignore
		);
	}

	/**
	 * The floating "Unlock with Pro" pill shown centered over a whole locked
	 * card body (paired with the `.fsi-locked` class + `data-pro-open` on
	 * the card, which render the translucent overlay in CSS). No-op once
	 * licensed.
	 */
	private static function lock_cta() {
		if ( Fuse_Pro::is_pro() ) {
			return;
		}
		printf(
			'<span class="fsi-lock-cta">%s %s</span>',
			self::lock_icon(), // phpcs:ignore
			esc_html__( 'Unlock with Pro', 'fuse-social-floating-sidebar' )
		);
	}

	/**
	 * Attribute fragment to make a card open the Pro modal on click when
	 * locked — pairs with the `fsi-locked` class already on these cards.
	 */
	private static function lock_attr() {
		return Fuse_Pro::is_pro() ? '' : 'data-pro-open';
	}

	private static function pro_badge() {
		// No badge once licensed — Fuse_Pro::is_pro() is the single gate every
		// Pro control in this file checks, matching Fuse_Settings::enforce_free_tier().
		if ( Fuse_Pro::is_pro() ) {
			return '';
		}
		return '<span class="fsi-tag fsi-tag-pro">' . esc_html__( 'Pro', 'fuse-social-floating-sidebar' ) . '</span>';
	}

	private static function new_badge() {
		return '<span class="fsi-tag fsi-tag-new">' . esc_html__( 'new', 'fuse-social-floating-sidebar' ) . '</span>';
	}

	/**
	 * A small lock glyph used inside overlay pills/badges (Tabler "lock").
	 */
	private static function lock_icon() {
		return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 11m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v6a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z"/><path d="M8 11v-4a4 4 0 1 1 8 0v4"/></svg>';
	}

	/**
	 * A real button (not a plain text link) that opens the Pro upsell modal —
	 * used under a control where only SOME options are Pro (so the control
	 * itself must stay clickable/usable, e.g. a select with a couple of
	 * Pro-only options) rather than being covered by a blocking overlay.
	 * No-op once licensed.
	 */
	private static function unlock_button( $text ) {
		if ( Fuse_Pro::is_pro() ) {
			return;
		}
		printf(
			'<span class="fsi-unlock-wrap"><span class="fsi-unlock-hint">%s</span><button type="button" class="fsi-unlock-btn" data-pro-open>%s %s</button></span>',
			esc_html( $text ),
			self::lock_icon(), // phpcs:ignore
			esc_html__( 'Unlock with Pro', 'fuse-social-floating-sidebar' )
		);
	}

	private static function shape_options() {
		$all = array(
			'round'   => array( 'label' => __( 'Circle', 'fuse-social-floating-sidebar' ), 'radius' => '50%' ),
			'rounded' => array( 'label' => __( 'Rounded', 'fuse-social-floating-sidebar' ), 'radius' => '7px', 'pro' => true ),
			'square'  => array( 'label' => __( 'Square', 'fuse-social-floating-sidebar' ), 'radius' => '0' ),
			// Transparent fill, colored border + icon — legacy premium "Design 3".
			'outline' => array( 'label' => __( 'Outline', 'fuse-social-floating-sidebar' ), 'outline' => true, 'pro' => true ),
		);
		// Always all 4 — Pro-only ones render locked-but-visible (see segmented()),
		// matching Fuse_Settings::enforce_free_tier()'s actual save-time gate.
		return $all;
	}

	private static function notice( $key, $success_msg, $neutral_msg = '' ) {
		if ( ! isset( $_GET[ $key ] ) ) {
			return;
		}
		if ( ! empty( $_GET[ $key ] ) ) {
			echo '<div class="fsi-toast ok">' . esc_html( $success_msg ) . '</div>';
		} elseif ( $neutral_msg ) {
			echo '<div class="fsi-toast">' . esc_html( $neutral_msg ) . '</div>';
		}
	}

	/* ------------------------------------------------------------------ *
	 * Render
	 * ------------------------------------------------------------------ */

	public static function render() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		// Upgrading from the legacy Redux plugin: show the one-time review
		// wizard instead of the settings until the user confirms or opts out.
		// (?fsi-wizard-preview=1 lets admins preview the screen any time.)
		if ( Fuse_Migration_Wizard::is_pending() || isset( $_GET['fsi-wizard-preview'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
			Fuse_Migration_Wizard::render();
			return;
		}

		$s   = Fuse_Settings::get();
		$tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'profile';

		$tabs = array(
			'profile'  => array( 'users', __( 'Profile icons', 'fuse-social-floating-sidebar' ), '' ),
			'floating' => array( 'anchor', __( 'Floating bar', 'fuse-social-floating-sidebar' ), '' ),
			'share'    => array( 'share-3', __( 'Share buttons', 'fuse-social-floating-sidebar' ), '' ),
			'block'    => array( 'puzzle', __( 'Block & Widget', 'fuse-social-floating-sidebar' ), '' ),
			'rules'    => array( 'adjustments-horizontal', __( 'Display rules', 'fuse-social-floating-sidebar' ), 'new' ),
			'analytics'=> array( 'chart-line', __( 'Analytics', 'fuse-social-floating-sidebar' ), 'new' ),
			'advanced' => array( 'code', __( 'Advanced', 'fuse-social-floating-sidebar' ), '' ),
		);
		?>
		<div class="fsi-app" data-tab="<?php echo esc_attr( $tab ); ?>">

			<!-- masthead -->
			<div class="fsi-mast">
				<div class="fsi-mark"><?php echo Fuse_UI::icon( 'social', 22 ); // phpcs:ignore ?></div>
				<div class="fsi-mast-txt">
					<h1><?php esc_html_e( 'Fuse Social Icons', 'fuse-social-floating-sidebar' ); ?> <span class="fsi-ver">v<?php echo esc_html( FUSE_SOCIAL_ICONS_VERSION ); ?></span></h1>
					<p><?php esc_html_e( 'Profile icons, floating bar, share buttons, block and widget.', 'fuse-social-floating-sidebar' ); ?></p>
				</div>
				<div class="fsi-mast-act">
					<?php if ( ! Fuse_Pro::is_pro() ) : ?>
						<button type="button" class="fsi-btn fsi-btn-upgrade" data-pro-open><?php echo Fuse_UI::icon( 'trending-up', 16 ); // phpcs:ignore ?> <?php esc_html_e( 'Upgrade to Pro', 'fuse-social-floating-sidebar' ); ?></button>
					<?php endif; ?>
					<a class="fsi-btn fsi-btn-secondary" href="<?php echo esc_url( home_url( '/' ) ); ?>" target="_blank"><?php echo Fuse_UI::icon( 'external-link', 16 ); // phpcs:ignore ?> <?php esc_html_e( 'Preview site', 'fuse-social-floating-sidebar' ); ?></a>
					<button type="submit" form="fsi-form" class="fsi-btn fsi-btn-primary"><?php echo Fuse_UI::icon( 'device-floppy', 16 ); // phpcs:ignore ?> <?php esc_html_e( 'Save changes', 'fuse-social-floating-sidebar' ); ?></button>
				</div>
			</div>

			<!-- tabs -->
			<div class="fsi-tabbar">
				<div class="fsi-tabs" id="fsi-tabs">
					<span class="fsi-pill" id="fsi-pill"></span>
					<?php foreach ( $tabs as $key => $t ) : ?>
						<button type="button" class="fsi-tab <?php echo $tab === $key ? 'on' : ''; ?>" data-tab="<?php echo esc_attr( $key ); ?>">
							<?php echo Fuse_UI::icon( $t[0], 16 ); // phpcs:ignore ?> <?php echo esc_html( $t[1] ); ?>
							<?php if ( 'new' === $t[2] ) : ?><span class="fsi-nb"><?php esc_html_e( 'new', 'fuse-social-floating-sidebar' ); ?></span><?php endif; ?>
						</button>
					<?php endforeach; ?>
				</div>
			</div>

			<?php if ( ! empty( $_GET['updated'] ) ) : ?>
				<div class="fsi-toast ok fsi-toast-float"><?php echo Fuse_UI::icon( 'check', 15 ); // phpcs:ignore ?> <?php esc_html_e( 'Settings saved.', 'fuse-social-floating-sidebar' ); ?></div>
			<?php elseif ( ! empty( $_GET['fresh'] ) ) : ?>
				<div class="fsi-toast ok fsi-toast-float"><?php echo Fuse_UI::icon( 'check', 15 ); // phpcs:ignore ?> <?php esc_html_e( 'Starting fresh — your old plugin data was left untouched.', 'fuse-social-floating-sidebar' ); ?></div>
			<?php endif; ?>

			<!-- settings form (empty shell; inputs associate via form="fsi-form") -->
			<form id="fsi-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="fuse_save_settings">
				<input type="hidden" name="fuse_active_tab" id="fsi-active-tab" value="<?php echo esc_attr( $tab ); ?>">
				<?php wp_nonce_field( 'fuse_save_settings' ); ?>
			</form>

			<?php
			self::tab_profile( $s );
			self::tab_floating( $s );
			self::tab_share( $s );
			self::tab_block( $s );
			self::tab_rules( $s );
			self::tab_analytics( $s );
			self::tab_advanced( $s );
			?>

			<!-- sticky save bar -->
			<div class="fsi-savebar" id="fsi-savebar">
				<div class="fsi-sb-txt"><span class="fsi-pulse"></span> <?php esc_html_e( 'You have unsaved changes', 'fuse-social-floating-sidebar' ); ?></div>
				<div class="fsi-sb-act">
					<button type="button" class="fsi-btn fsi-btn-ghost fsi-btn-sm" id="fsi-discard"><?php esc_html_e( 'Discard', 'fuse-social-floating-sidebar' ); ?></button>
					<button type="submit" form="fsi-form" class="fsi-btn fsi-btn-accent fsi-btn-sm"><?php echo Fuse_UI::icon( 'check', 15 ); // phpcs:ignore ?> <?php esc_html_e( 'Save changes', 'fuse-social-floating-sidebar' ); ?></button>
				</div>
			</div>

			<?php if ( ! Fuse_Pro::is_pro() ) : ?>
			<!-- shared Pro upsell modal, opened by any [data-pro-open] element -->
			<div class="fsi-pro-modal" id="fsi-pro-modal" hidden>
				<div class="fsi-pro-modal-panel">
					<button type="button" class="fsi-rm fsi-pro-modal-close" id="fsi-pro-modal-close" aria-label="<?php esc_attr_e( 'Close', 'fuse-social-floating-sidebar' ); ?>"><?php echo Fuse_UI::icon( 'x', 16 ); // phpcs:ignore ?></button>
					<div class="fsi-pro-modal-mark"><?php echo Fuse_UI::icon( 'social', 24 ); // phpcs:ignore ?></div>
					<div class="fsi-pro-modal-trust"><?php echo Fuse_UI::icon( 'users', 14 ); // phpcs:ignore ?> <?php esc_html_e( 'Trusted by 27,000+ blogs, shops & websites', 'fuse-social-floating-sidebar' ); ?></div>
					<h2><?php esc_html_e( 'Unlock Fuse Social Icons Pro', 'fuse-social-floating-sidebar' ); ?></h2>
					<p class="fsi-pro-modal-sub"><?php esc_html_e( 'Get the full toolkit for turning visitors into followers and subscribers.', 'fuse-social-floating-sidebar' ); ?></p>
					<ul class="fsi-pro-modal-list">
						<li><?php echo Fuse_UI::icon( 'check', 16 ); // phpcs:ignore ?> <?php esc_html_e( 'Custom icons, per-network colors, more shapes & hover effects', 'fuse-social-floating-sidebar' ); ?></li>
						<li><?php echo Fuse_UI::icon( 'check', 16 ); // phpcs:ignore ?> <?php esc_html_e( 'Action-button reveal mode & a tap-to-chat bubble', 'fuse-social-floating-sidebar' ); ?></li>
						<li><?php echo Fuse_UI::icon( 'check', 16 ); // phpcs:ignore ?> <?php esc_html_e( 'Richer share placements, styles & live share counts', 'fuse-social-floating-sidebar' ); ?></li>
						<li><?php echo Fuse_UI::icon( 'check', 16 ); // phpcs:ignore ?> <?php esc_html_e( 'Display rules to target exactly where icons appear', 'fuse-social-floating-sidebar' ); ?></li>
						<li><?php echo Fuse_UI::icon( 'check', 16 ); // phpcs:ignore ?> <?php esc_html_e( 'Real click & share analytics', 'fuse-social-floating-sidebar' ); ?></li>
					</ul>
					<a class="fsi-btn fsi-btn-upgrade" href="<?php echo esc_url( Fuse_Pro::upgrade_url() ); ?>" target="_blank"><?php echo Fuse_UI::icon( 'trending-up', 16 ); // phpcs:ignore ?> <?php esc_html_e( 'Upgrade to Pro', 'fuse-social-floating-sidebar' ); ?></a>
					<div class="fsi-pro-modal-fine"><?php esc_html_e( '30-day money-back guarantee.', 'fuse-social-floating-sidebar' ); ?></div>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}

	/* ---------------- Profile tab ---------------- */

	private static function tab_profile( $s ) {
		$g            = $s['general'];
		$all          = Fuse_Networks::profile_networks();
		$legacy_keys  = array_keys( Fuse_Networks::legacy_defunct_networks() );

		// Split saved networks into active (enabled) vs inactive, preserving order.
		$active = array();
		$seen   = array();
		foreach ( $s['networks'] as $i => $row ) {
			if ( ! isset( $all[ $row['key'] ] ) && ! in_array( $row['key'], $legacy_keys, true ) ) {
				continue;
			}
			$seen[] = $row['key'];
			if ( ! empty( $row['enabled'] ) ) {
				$active[] = array( 'i' => $i, 'row' => $row );
			}
		}
		$inactive = array();
		foreach ( $s['networks'] as $i => $row ) {
			if ( isset( $all[ $row['key'] ] ) && empty( $row['enabled'] ) ) {
				$inactive[] = array( 'i' => $i, 'row' => $row );
			}
		}
		foreach ( $all as $key => $meta ) {
			if ( ! in_array( $key, $seen, true ) ) {
				$inactive[] = array( 'i' => 'new-' . $key, 'row' => array( 'key' => $key, 'url' => '', 'enabled' => false ) );
			}
		}
		?>
		<div class="fsi-tp" data-tab="profile">
			<div class="fsi-cols">
				<div>
					<div class="fsi-card">
						<div class="fsi-card-h">
							<div class="fsi-ci"><?php echo Fuse_UI::icon( 'users', 16 ); // phpcs:ignore ?></div>
							<div><h3><?php esc_html_e( 'Active icons', 'fuse-social-floating-sidebar' ); ?></h3><div class="fsi-cs"><?php esc_html_e( 'Drag to reorder — used by shortcode, block and widget', 'fuse-social-floating-sidebar' ); ?></div></div>
							<div class="fsi-ch-act"><button type="button" class="fsi-btn fsi-btn-secondary fsi-btn-sm" id="fsi-sort-az"><?php echo Fuse_UI::icon( 'arrows-sort', 15 ); // phpcs:ignore ?> <?php esc_html_e( 'Sort A–Z', 'fuse-social-floating-sidebar' ); ?></button></div>
						</div>
						<div class="fsi-card-b tight">
							<div class="fsi-rows" id="fsi-active-list">
								<?php foreach ( $active as $item ) {
									self::network_row( $item['i'], $item['row'], $all[ $item['row']['key'] ] ?? Fuse_Networks::legacy_defunct_networks()[ $item['row']['key'] ], false );
								} ?>
							</div>
						</div>
					</div>

					<div class="fsi-card">
						<div class="fsi-card-h">
							<div class="fsi-ci"><?php echo Fuse_UI::icon( 'plus', 16 ); // phpcs:ignore ?></div>
							<div><h3><?php esc_html_e( 'Add a platform', 'fuse-social-floating-sidebar' ); ?></h3><div class="fsi-cs"><?php printf( esc_html__( '%d supported, or upload a custom icon', 'fuse-social-floating-sidebar' ), count( $all ) ); ?></div></div>
						</div>
						<div class="fsi-card-b">
							<div class="fsi-adds" id="fsi-add-list">
								<?php foreach ( $inactive as $item ) {
									self::network_swatch( $item['i'], $item['row'], $all[ $item['row']['key'] ] );
								} ?>
								<button type="button" class="fsi-add fsi-add-custom" id="fsi-add-custom" title="<?php esc_attr_e( 'Add a custom icon', 'fuse-social-floating-sidebar' ); ?>" <?php echo Fuse_Pro::is_pro() ? '' : 'data-pro-open'; ?>><?php echo Fuse_UI::icon( 'upload', 18 ); // phpcs:ignore ?></button>
							</div>
							<?php if ( ! Fuse_Pro::is_pro() ) : ?>
								<?php self::unlock_button( __( 'Custom icon uploads need Pro.', 'fuse-social-floating-sidebar' ) ); ?>
							<?php endif; ?>
						</div>
					</div>

					<?php // Shown even without Pro (and even with no saved custom icons) so free users can see what they're missing; only auto-hidden once licensed and empty. ?>
					<div class="fsi-card <?php echo Fuse_Pro::is_pro() ? '' : 'fsi-locked'; ?>" <?php echo self::lock_attr(); ?> id="fsi-custom-card" style="<?php echo Fuse_Pro::is_pro() ? 'display:none' : ''; ?>">
						<div class="fsi-card-h"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'upload', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Custom icons', 'fuse-social-floating-sidebar' ); ?> <?php echo self::pro_badge(); // phpcs:ignore ?></h3><div class="fsi-cs"><?php esc_html_e( 'Your own image or icon with a custom link', 'fuse-social-floating-sidebar' ); ?></div></div></div>
						<div class="fsi-card-b">
							<?php self::lock_cta(); ?>
						</div>
					</div>
				</div>

				<div>
					<div class="fsi-card">
						<div class="fsi-card-h"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'palette', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Appearance', 'fuse-social-floating-sidebar' ); ?></h3></div></div>
						<div class="fsi-card-b">
							<div class="fsi-stack">
								<div class="fsi-slabel"><?php esc_html_e( 'Shape', 'fuse-social-floating-sidebar' ); ?></div>
								<?php self::segmented( 'fuse[general][shape]', self::shape_options(), $g['shape'] ); ?>
							</div>
							<?php self::slider( 'fuse[general][custom_radius]', $g['custom_radius'], 0, 100, 1, 'px', __( 'Custom radius (0 = use shape)', 'fuse-social-floating-sidebar' ), true ); ?>
							<?php self::slider( 'fuse[general][size]', $g['size'], 16, 64, 1, 'px', __( 'Icon size', 'fuse-social-floating-sidebar' ) ); ?>
							<?php self::slider( 'fuse[general][gap]', $g['gap'], 0, 40, 1, 'px', __( 'Icon spacing', 'fuse-social-floating-sidebar' ) ); ?>
							<?php
							// All options always shown (never hidden) — the Pro-only ones
							// render as disabled entries plus an "Unlock with Pro" button,
							// so free users can see exactly what they'd get, not just a
							// vague "there's more" hint.
							self::select_row(
								'fuse[general][color_style]', __( 'Color style', 'fuse-social-floating-sidebar' ),
								array(
									'brand'      => __( 'Brand colors', 'fuse-social-floating-sidebar' ),
									'mono-dark'  => __( 'Monochrome dark', 'fuse-social-floating-sidebar' ),
									'mono-light' => __( 'Monochrome light', 'fuse-social-floating-sidebar' ),
									'custom'     => __( 'Custom', 'fuse-social-floating-sidebar' ),
								),
								$g['color_style'], '', array( 'mono-dark', 'mono-light' )
							);
							?>
							<div class="fsi-custom-colors" style="<?php echo 'custom' === $g['color_style'] ? '' : 'display:none'; ?>">
								<div class="fsi-field-grid">
									<?php self::color_field( 'fuse[general][custom_bg_color]', $g['custom_bg_color'], __( 'Background', 'fuse-social-floating-sidebar' ) ); ?>
									<?php self::color_field( 'fuse[general][custom_icon_color]', $g['custom_icon_color'], __( 'Icon', 'fuse-social-floating-sidebar' ) ); ?>
								</div>
							</div>
							<?php
							self::select_row(
								'fuse[general][hover_effect]', __( 'Hover effect', 'fuse-social-floating-sidebar' ),
								array(
									'none'   => __( 'None', 'fuse-social-floating-sidebar' ),
									'rotate' => __( 'Rotate', 'fuse-social-floating-sidebar' ),
									'lift'   => __( 'Lift', 'fuse-social-floating-sidebar' ),
									'pulse'  => __( 'Pulse', 'fuse-social-floating-sidebar' ),
									'shake'  => __( 'Shake', 'fuse-social-floating-sidebar' ),
									'bounce' => __( 'Bounce', 'fuse-social-floating-sidebar' ),
									'swing'  => __( 'Swing', 'fuse-social-floating-sidebar' ),
									'flip'   => __( 'Flip', 'fuse-social-floating-sidebar' ),
								),
								$g['hover_effect'], '', array( 'lift', 'pulse', 'shake', 'bounce', 'swing', 'flip' )
							);
							?>
							<?php self::toggle_row( 'fuse[general][shadow]', $g['shadow'], __( 'Drop shadow', 'fuse-social-floating-sidebar' ) ); ?>
							<?php self::toggle_row( 'fuse[general][open_new_tab]', $g['open_new_tab'], __( 'Open in new tab', 'fuse-social-floating-sidebar' ), 'Adds target="_blank" and rel="noopener"' ); ?>
							<?php self::toggle_row( 'fuse[general][nofollow]', $g['nofollow'], __( 'Add rel="nofollow"', 'fuse-social-floating-sidebar' ) ); ?>
							<?php self::toggle_row( 'fuse[general][custom_icons_first]', $g['custom_icons_first'], __( 'Show custom icons first', 'fuse-social-floating-sidebar' ), __( 'Custom icons render before the regular network icons', 'fuse-social-floating-sidebar' ), true ); ?>
						</div>
					</div>

					<div class="fsi-pv">
						<div class="fsi-pl"><?php esc_html_e( 'Live preview', 'fuse-social-floating-sidebar' ); ?></div>
						<div class="fsi-dock" id="fsi-dock"></div>
					</div>
				</div>
			</div>

			<p class="fsi-hint"><?php esc_html_e( 'Shortcode', 'fuse-social-floating-sidebar' ); ?> <code>[fuse_icons]</code> &nbsp; <?php esc_html_e( 'PHP', 'fuse-social-floating-sidebar' ); ?> <code>echo do_shortcode('[fuse_icons]');</code></p>
		</div>
		<?php
	}

	private static function network_row( $i, $row, $meta, $is_swatch ) {
		$url_type = $meta['url_type'] ?? 'url';
		?>
		<div class="fsi-irow" data-key="<?php echo esc_attr( $row['key'] ); ?>" data-color="<?php echo esc_attr( $meta['color'] ); ?>">
			<span class="fsi-grip"><?php echo Fuse_UI::icon( 'grip-vertical', 16 ); // phpcs:ignore ?></span>
			<span class="fsi-chip" style="--chip:<?php echo esc_attr( $meta['color'] ); ?>"><?php echo Fuse_Networks::get_icon_svg( $row['key'], 15 ); // phpcs:ignore ?></span>
			<span class="fsi-nm"><?php echo esc_html( $meta['label'] ); ?></span>
			<input type="hidden" form="fsi-form" class="fsi-key" name="fuse[networks][<?php echo esc_attr( $i ); ?>][key]" value="<?php echo esc_attr( $row['key'] ); ?>">
			<input type="checkbox" form="fsi-form" class="fsi-en" name="fuse[networks][<?php echo esc_attr( $i ); ?>][enabled]" value="1" checked hidden>
			<input type="<?php echo 'mailto' === $url_type ? 'email' : 'url'; ?>" form="fsi-form" class="fsi-fld" name="fuse[networks][<?php echo esc_attr( $i ); ?>][url]" value="<?php echo esc_attr( $row['url'] ); ?>" placeholder="<?php echo 'mailto' === $url_type ? esc_attr__( 'you@example.com', 'fuse-social-floating-sidebar' ) : esc_attr( 'https://' . $row['key'] . '.com/yourpage' ); ?>">
			<button type="button" class="fsi-rm" aria-label="<?php esc_attr_e( 'Remove', 'fuse-social-floating-sidebar' ); ?>"><?php echo Fuse_UI::icon( 'x', 16 ); // phpcs:ignore ?></button>
		</div>
		<?php
	}

	private static function network_swatch( $i, $row, $meta ) {
		$locked = Fuse_Networks::is_pro_network( $row['key'] );
		?>
		<button type="button" class="fsi-add fsi-swatch<?php echo $locked ? ' fsi-pro-swatch' : ''; ?>" data-key="<?php echo esc_attr( $row['key'] ); ?>" data-color="<?php echo esc_attr( $meta['color'] ); ?>" data-index="<?php echo esc_attr( $i ); ?>" data-label="<?php echo esc_attr( $meta['label'] ); ?>" data-urltype="<?php echo esc_attr( $meta['url_type'] ?? 'url' ); ?>" title="<?php echo esc_attr( $locked ? $meta['label'] . ' — ' . __( 'Pro', 'fuse-social-floating-sidebar' ) : $meta['label'] ); ?>" style="--chip:<?php echo esc_attr( $meta['color'] ); ?>" <?php echo $locked ? 'data-pro-open' : ''; ?>>
			<?php echo Fuse_Networks::get_icon_svg( $row['key'], 18 ); // phpcs:ignore ?>
			<?php if ( $locked ) : ?><span class="fsi-swatch-lock"><?php echo self::lock_icon(); // phpcs:ignore ?></span><?php endif; ?>
		</button>
		<?php
	}

	/* ---------------- Floating tab ---------------- */

	private static function tab_floating( $s ) {
		$f  = $s['floating'];
		?>
		<div class="fsi-tp" data-tab="floating">
			<div class="fsi-cols">
				<div class="fsi-card" style="margin:0">
					<div class="fsi-card-h"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'anchor', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Floating bar', 'fuse-social-floating-sidebar' ); ?></h3><div class="fsi-cs"><?php esc_html_e( 'Sticky follow bar that trails the visitor', 'fuse-social-floating-sidebar' ); ?></div></div></div>
					<div class="fsi-card-b">
						<?php self::toggle_row( 'fuse[floating][enabled]', $f['enabled'], __( 'Enable floating bar', 'fuse-social-floating-sidebar' ) ); ?>
						<div class="fsi-stack">
							<div class="fsi-slabel"><?php esc_html_e( 'Position', 'fuse-social-floating-sidebar' ); ?></div>
							<?php
							self::segmented( 'fuse[floating][position]', array(
								'left'   => array( 'label' => __( 'Left', 'fuse-social-floating-sidebar' ), 'preview' => 'left' ),
								'right'  => array( 'label' => __( 'Right', 'fuse-social-floating-sidebar' ), 'preview' => 'right' ),
								'bottom' => array( 'label' => __( 'Bottom', 'fuse-social-floating-sidebar' ), 'preview' => 'bottom', 'pro' => true ),
							), $f['position'] );
							?>
						</div>
						<?php self::select_row( 'fuse[floating][vertical_align]', __( 'Vertical align', 'fuse-social-floating-sidebar' ), array(
							'middle' => __( 'Middle', 'fuse-social-floating-sidebar' ),
							'top'    => __( 'Top', 'fuse-social-floating-sidebar' ),
							'bottom' => __( 'Bottom', 'fuse-social-floating-sidebar' ),
							'custom' => __( 'Custom (%)', 'fuse-social-floating-sidebar' ),
						), $f['vertical_align'], __( 'Ignored when position is Bottom', 'fuse-social-floating-sidebar' ), true ); ?>
						<?php self::slider( 'fuse[floating][vertical_offset_pct]', $f['vertical_offset_pct'], 1, 100, 1, '%', __( 'Vertical position (custom)', 'fuse-social-floating-sidebar' ), true ); ?>
						<?php self::toggle_row( 'fuse[floating][hide_on_scroll_down]', $f['hide_on_scroll_down'], __( 'Hide on scroll down', 'fuse-social-floating-sidebar' ), __( 'Reappears when scrolling up', 'fuse-social-floating-sidebar' ), true ); ?>
						<?php self::toggle_row( 'fuse[floating][show_on_mobile]', $f['show_on_mobile'], __( 'Show on mobile', 'fuse-social-floating-sidebar' ) ); ?>
						<?php self::toggle_row( 'fuse[floating][sticky_mobile_bar]', $f['sticky_mobile_bar'], __( 'Sticky bar on mobile', 'fuse-social-floating-sidebar' ), __( 'Full-width bottom bar on small screens', 'fuse-social-floating-sidebar' ), true ); ?>
						<?php self::toggle_row( 'fuse[floating][appear_after_scroll]', $f['appear_after_scroll'], __( 'Appear only after scrolling', 'fuse-social-floating-sidebar' ), '', true ); ?>
						<?php $scroll_pro_attr = Fuse_Pro::is_pro() ? '' : 'disabled'; ?>
						<div class="fsi-frow"><div class="fsi-ft"><?php esc_html_e( 'Scroll offset', 'fuse-social-floating-sidebar' ); ?> <?php echo self::pro_badge(); // phpcs:ignore ?><div class="fsi-fd"><?php esc_html_e( 'Pixels scrolled before appearing', 'fuse-social-floating-sidebar' ); ?></div></div><input type="number" form="fsi-form" style="width:110px" name="fuse[floating][scroll_offset]" value="<?php echo esc_attr( $f['scroll_offset'] ); ?>" min="0" max="5000" <?php echo esc_attr( $scroll_pro_attr ); ?>></div>
					</div>
				</div>

				<div>
					<div class="fsi-card fsi-locked" data-pro-open style="margin:0">
						<div class="fsi-card-h"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'message-circle', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Chat bubble', 'fuse-social-floating-sidebar' ); ?> <?php echo self::pro_badge(); // phpcs:ignore ?></h3><div class="fsi-cs"><?php esc_html_e( 'A tap-to-chat button, bottom corner', 'fuse-social-floating-sidebar' ); ?></div></div></div>
						<div class="fsi-card-b">
							<?php self::lock_cta(); ?>
						</div>
					</div>

					<div class="fsi-card fsi-locked" data-pro-open>
						<div class="fsi-card-h"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'plus', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Action button', 'fuse-social-floating-sidebar' ); ?> <?php echo self::pro_badge(); // phpcs:ignore ?></h3><div class="fsi-cs"><?php esc_html_e( 'One button that expands to reveal the icons', 'fuse-social-floating-sidebar' ); ?></div></div></div>
						<div class="fsi-card-b">
							<?php self::lock_cta(); ?>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/* ---------------- Share tab ---------------- */

	private static function tab_share( $s ) {
		$sh       = $s['share'];
		$networks = Fuse_Networks::share_networks();
		$active   = array();
		foreach ( $sh['networks'] as $key ) {
			if ( isset( $networks[ $key ] ) ) {
				$active[ $key ] = $networks[ $key ];
			}
		}
		$inactive = array_diff_key( $networks, $active );
		?>
		<div class="fsi-tp" data-tab="share">
			<div class="fsi-cols">
				<div>
					<div class="fsi-card" style="margin:0 0 16px">
						<div class="fsi-card-h"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'share-3', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Share channels', 'fuse-social-floating-sidebar' ); ?></h3><div class="fsi-cs"><?php esc_html_e( 'Drag to set the order readers see', 'fuse-social-floating-sidebar' ); ?></div></div></div>
						<div class="fsi-card-b tight">
							<?php self::toggle_row( 'fuse[share][enabled]', $sh['enabled'], __( 'Enable share buttons', 'fuse-social-floating-sidebar' ) ); ?>
							<div class="fsi-rows" id="fsi-share-list">
								<?php foreach ( $active as $key => $net ) {
									self::share_row( $key, $net, true );
								} ?>
							</div>
							<h4 class="fsi-sublbl"><?php esc_html_e( 'Add a channel', 'fuse-social-floating-sidebar' ); ?></h4>
							<div class="fsi-adds" id="fsi-share-add">
								<?php foreach ( $inactive as $key => $net ) {
									self::share_swatch( $key, $net );
								} ?>
							</div>
						</div>
					</div>
				</div>

				<div>
					<div class="fsi-card">
						<div class="fsi-card-h"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'layout-align-top', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Placement', 'fuse-social-floating-sidebar' ); ?></h3></div></div>
						<div class="fsi-card-b">
							<?php self::toggle_row( 'fuse[share][before]', $sh['before'], __( 'Before content', 'fuse-social-floating-sidebar' ) ); ?>
							<?php self::toggle_row( 'fuse[share][after]', $sh['after'], __( 'After content', 'fuse-social-floating-sidebar' ), '', true ); ?>
							<?php self::toggle_row( 'fuse[share][floating_sidebar]', $sh['floating_sidebar'], __( 'Floating sidebar', 'fuse-social-floating-sidebar' ), __( 'Desktop only', 'fuse-social-floating-sidebar' ), true ); ?>
							<?php self::toggle_row( 'fuse[share][sticky_mobile]', $sh['sticky_mobile'], __( 'Sticky mobile bar', 'fuse-social-floating-sidebar' ), '', true ); ?>
						</div>
					</div>
					<div class="fsi-card">
						<div class="fsi-card-h"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'hash', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Counts & style', 'fuse-social-floating-sidebar' ); ?> <?php echo self::new_badge(); // phpcs:ignore ?></h3></div></div>
						<div class="fsi-card-b">
							<?php self::toggle_row( 'fuse[share][show_counts]', false, __( 'Show share counts', 'fuse-social-floating-sidebar' ), '', true ); ?>
							<?php self::toggle_row( 'fuse[share][utm_tags]', false, __( 'Add UTM tags', 'fuse-social-floating-sidebar' ), 'utm_source=fuse_share', true ); ?>
							<?php self::select_row( 'fuse[share][cache_duration]', __( 'Cache counts for', 'fuse-social-floating-sidebar' ), array(
								'1h'  => __( '1 hour', 'fuse-social-floating-sidebar' ),
								'6h'  => __( '6 hours', 'fuse-social-floating-sidebar' ),
								'24h' => __( '24 hours', 'fuse-social-floating-sidebar' ),
							), '6h', '', true ); ?>
							<?php
							self::select_row(
								'fuse[share][style]', __( 'Style', 'fuse-social-floating-sidebar' ),
								array(
									'icon'       => __( 'Icon only', 'fuse-social-floating-sidebar' ),
									'icon-label' => __( 'Pill (icon + label)', 'fuse-social-floating-sidebar' ),
									'button'     => __( 'Button (icon + label)', 'fuse-social-floating-sidebar' ),
								),
								$sh['style'], '', array( 'icon-label', 'button' )
							);
							?>
							<div class="fsi-stack">
								<div class="fsi-slabel"><?php esc_html_e( 'Shape', 'fuse-social-floating-sidebar' ); ?></div>
								<?php
								// Share tab's free tier only offers Square (unlike the Profile tab,
								// which offers round+square).
								self::segmented( 'fuse[share][shape]', array(
									'round'   => array( 'label' => __( 'Circle', 'fuse-social-floating-sidebar' ), 'radius' => '50%', 'pro' => true ),
									'rounded' => array( 'label' => __( 'Rounded', 'fuse-social-floating-sidebar' ), 'radius' => '7px', 'pro' => true ),
									'square'  => array( 'label' => __( 'Square', 'fuse-social-floating-sidebar' ), 'radius' => '0' ),
									'outline' => array( 'label' => __( 'Outline', 'fuse-social-floating-sidebar' ), 'outline' => true, 'pro' => true ),
								), $sh['shape'] );
								?>
							</div>
							<?php self::slider( 'fuse[share][size]', $sh['size'], 16, 64, 1, 'px', __( 'Button size', 'fuse-social-floating-sidebar' ) ); ?>
							<?php self::slider( 'fuse[share][icon_size]', $sh['icon_size'], 10, 48, 1, 'px', __( 'Icon size', 'fuse-social-floating-sidebar' ) ); ?>
						</div>
					</div>
				</div>
			</div>
			<p class="fsi-hint"><?php esc_html_e( 'Shortcode', 'fuse-social-floating-sidebar' ); ?> <code>[fuse_share]</code></p>
		</div>
		<?php
	}

	private static function share_row( $key, $net, $active ) {
		// 'print'/'native' are generic UI actions, not brand networks — they
		// have no entry in Fuse_Networks' icon set (matches the same fallback
		// used when actually rendering these share buttons on the front end).
		$ui_glyphs = array( 'print' => 'printer', 'native' => 'share-3' );
		$glyph     = isset( $ui_glyphs[ $key ] )
			? Fuse_UI::icon( $ui_glyphs[ $key ], 15 )
			: Fuse_Networks::get_icon_svg( $key, 15 );
		?>
		<div class="fsi-irow" data-key="<?php echo esc_attr( $key ); ?>" data-color="<?php echo esc_attr( $net['color'] ); ?>">
			<span class="fsi-grip"><?php echo Fuse_UI::icon( 'grip-vertical', 16 ); // phpcs:ignore ?></span>
			<span class="fsi-chip" style="--chip:<?php echo esc_attr( $net['color'] ); ?>"><?php echo $glyph; // phpcs:ignore ?></span>
			<span class="fsi-nm" style="width:120px"><?php echo esc_html( $net['label'] ); ?></span>
			<input type="hidden" form="fsi-form" class="fsi-share-key" name="fuse[share][networks][]" value="<?php echo esc_attr( $key ); ?>">
			<span class="fsi-metric"></span>
			<button type="button" class="fsi-rm" aria-label="<?php esc_attr_e( 'Remove', 'fuse-social-floating-sidebar' ); ?>"><?php echo Fuse_UI::icon( 'x', 16 ); // phpcs:ignore ?></button>
		</div>
		<?php
	}

	private static function share_swatch( $key, $net ) {
		// 'print'/'native' are generic UI actions, not brand networks — they
		// have no entry in Fuse_Networks' icon set (matches the same fallback
		// used when actually rendering these share buttons on the front end).
		$ui_glyphs = array( 'print' => 'printer', 'native' => 'share-3' );
		$glyph     = isset( $ui_glyphs[ $key ] )
			? Fuse_UI::icon( $ui_glyphs[ $key ], 18 )
			: Fuse_Networks::get_icon_svg( $key, 18 );
		?>
		<button type="button" class="fsi-add fsi-share-swatch" data-key="<?php echo esc_attr( $key ); ?>" data-color="<?php echo esc_attr( $net['color'] ); ?>" data-label="<?php echo esc_attr( $net['label'] ); ?>" title="<?php echo esc_attr( $net['label'] ); ?>" style="--chip:<?php echo esc_attr( $net['color'] ); ?>">
			<?php echo $glyph; // phpcs:ignore ?>
		</button>
		<?php
	}

	/* ---------------- Block & Widget tab ---------------- */

	private static function tab_block( $s ) {
		$inline_preview = Fuse_Renderer::render_profile_icons( $s, array( 'mode' => 'inline' ) );
		$share_preview  = '';
		if ( ! empty( $s['share']['networks'] ) ) {
			$share_settings = $s;
			$share_settings['share']['enabled'] = true;
			$share_preview = Fuse_Renderer::render_share_buttons( $share_settings, home_url( '/' ), get_bloginfo( 'name' ), array( 'size' => 30 ) );
		}
		?>
		<div class="fsi-tp" data-tab="block">
			<div class="fsi-cols">
				<div>
					<div class="fsi-card" style="margin:0 0 16px">
						<div class="fsi-card-h"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'puzzle', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Gutenberg block', 'fuse-social-floating-sidebar' ); ?></h3><div class="fsi-cs"><?php esc_html_e( 'Place your icons inside any post or page', 'fuse-social-floating-sidebar' ); ?></div></div></div>
						<div class="fsi-card-b">
							<div class="fsi-steps">
								<span class="fsi-step"><b>1</b> <?php esc_html_e( 'Edit a page or post', 'fuse-social-floating-sidebar' ); ?></span>
								<span class="fsi-step"><b>2</b> <?php esc_html_e( 'Search “Fuse” in the inserter', 'fuse-social-floating-sidebar' ); ?></span>
								<span class="fsi-step"><b>3</b> <?php esc_html_e( 'Insert & style', 'fuse-social-floating-sidebar' ); ?></span>
							</div>
							<div class="fsi-gb">
								<div class="fsi-gbt"><?php echo Fuse_UI::icon( 'puzzle', 15 ); // phpcs:ignore ?> <?php esc_html_e( 'Block: Fuse Social Icons', 'fuse-social-floating-sidebar' ); ?></div>
								<div class="fsi-gbbody">
									<div class="fsi-gbcanvas">
										<div class="fsi-skel" style="width:52%"></div>
										<div class="fsi-skel" style="width:78%;margin-bottom:18px"></div>
										<div class="fsi-preview-inline"><?php echo $inline_preview ?: '<span class="fsi-cs">' . esc_html__( 'Enable some profile icons to preview.', 'fuse-social-floating-sidebar' ) . '</span>'; // phpcs:ignore ?></div>
										<div class="fsi-skel" style="width:64%;margin-top:18px"></div>
										<div class="fsi-skel" style="width:40%"></div>
									</div>
									<div class="fsi-gbins">
										<div class="fsi-gbsec"><?php esc_html_e( 'Icon set', 'fuse-social-floating-sidebar' ); ?></div>
										<div class="fsi-gbsel"><?php esc_html_e( 'Profile icons', 'fuse-social-floating-sidebar' ); ?> <?php echo Fuse_UI::icon( 'chevron-down', 13 ); // phpcs:ignore ?></div>
										<div class="fsi-gbsec"><?php esc_html_e( 'Style', 'fuse-social-floating-sidebar' ); ?></div>
										<div class="fsi-gbf"><span><?php esc_html_e( 'Mode', 'fuse-social-floating-sidebar' ); ?></span><b><?php esc_html_e( 'Profile / Share', 'fuse-social-floating-sidebar' ); ?></b></div>
										<div class="fsi-gbf"><span><?php esc_html_e( 'Shape', 'fuse-social-floating-sidebar' ); ?></span><b><?php esc_html_e( 'Override', 'fuse-social-floating-sidebar' ); ?></b></div>
										<div class="fsi-gbf"><span><?php esc_html_e( 'Size', 'fuse-social-floating-sidebar' ); ?></span><b><?php esc_html_e( 'Override', 'fuse-social-floating-sidebar' ); ?></b></div>
									</div>
								</div>
							</div>
							<?php if ( $share_preview ) : ?>
								<p class="fsi-hint" style="margin:16px 0 8px"><?php esc_html_e( 'The same block can also render your share buttons:', 'fuse-social-floating-sidebar' ); ?></p>
								<div class="fsi-preview-inline"><?php echo $share_preview; // phpcs:ignore ?></div>
							<?php endif; ?>
						</div>
					</div>
				</div>

				<div>
					<div class="fsi-card">
						<div class="fsi-card-h"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'layout-sidebar-right', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Classic widget', 'fuse-social-floating-sidebar' ); ?></h3><div class="fsi-cs"><?php esc_html_e( 'Appearance → Widgets, for themes without FSE', 'fuse-social-floating-sidebar' ); ?></div></div></div>
						<div class="fsi-card-b">
							<div class="fsi-wmock" aria-hidden="true">
								<div class="fsi-wmock-t"><?php echo Fuse_UI::icon( 'layout-sidebar-right', 14 ); // phpcs:ignore ?> <?php esc_html_e( 'Fuse Social Icons', 'fuse-social-floating-sidebar' ); ?></div>
								<label><?php esc_html_e( 'Title', 'fuse-social-floating-sidebar' ); ?></label>
								<input type="text" value="<?php esc_attr_e( 'Follow us', 'fuse-social-floating-sidebar' ); ?>" disabled>
								<label><?php esc_html_e( 'Display', 'fuse-social-floating-sidebar' ); ?></label>
								<select disabled><option><?php esc_html_e( 'Profile links', 'fuse-social-floating-sidebar' ); ?></option></select>
							</div>
							<p class="fsi-hint"><?php esc_html_e( 'Each widget instance can show either your profile links or share buttons, with an optional title.', 'fuse-social-floating-sidebar' ); ?></p>
						</div>
					</div>

					<div class="fsi-card">
						<div class="fsi-card-h"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'code', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Shortcodes & PHP', 'fuse-social-floating-sidebar' ); ?></h3><div class="fsi-cs"><?php esc_html_e( 'For page builders and theme templates', 'fuse-social-floating-sidebar' ); ?></div></div></div>
						<div class="fsi-card-b">
							<div class="fsi-code">
								<span class="c">// <?php esc_html_e( 'Profile icons', 'fuse-social-floating-sidebar' ); ?></span><br>
								<span class="k">[fuse_icons]</span><br>
								<span class="c">// <?php esc_html_e( 'Share buttons', 'fuse-social-floating-sidebar' ); ?></span><br>
								<span class="k">[fuse_share]</span><br>
								<span class="c">// <?php esc_html_e( 'In a theme template', 'fuse-social-floating-sidebar' ); ?></span><br>
								echo do_shortcode( <span class="k">'[fuse_icons]'</span> );
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/* ---------------- Display rules tab ---------------- */

	private static function tab_rules( $s ) {
		?>
		<div class="fsi-tp" data-tab="rules">
			<div class="fsi-card fsi-card-danger">
				<div class="fsi-card-h" style="border-bottom:none">
					<div class="fsi-ci"><?php echo Fuse_UI::icon( 'adjustments-horizontal', 16 ); // phpcs:ignore ?></div>
					<div><h3><?php esc_html_e( 'Display rules are a Pro feature', 'fuse-social-floating-sidebar' ); ?> <?php echo self::pro_badge(); // phpcs:ignore ?></h3><div class="fsi-cs"><?php esc_html_e( 'Upgrade to build rules and hide content on specific pages.', 'fuse-social-floating-sidebar' ); ?></div></div>
					<div class="fsi-ch-act"><button type="button" class="fsi-btn fsi-btn-accent fsi-btn-sm" data-pro-open><?php esc_html_e( 'Unlock with Pro', 'fuse-social-floating-sidebar' ); ?></button></div>
				</div>
			</div>
			<div class="fsi-card fsi-locked" data-pro-open>
				<div class="fsi-card-h">
					<div class="fsi-ci"><?php echo Fuse_UI::icon( 'adjustments-horizontal', 16 ); // phpcs:ignore ?></div>
					<div><h3><?php esc_html_e( 'Display rules', 'fuse-social-floating-sidebar' ); ?> <?php echo self::pro_badge(); // phpcs:ignore ?></h3><div class="fsi-cs"><?php esc_html_e( 'Control exactly where each icon set appears', 'fuse-social-floating-sidebar' ); ?></div></div>
				</div>
				<div class="fsi-card-b">
					<?php self::lock_cta(); ?>
				</div>
			</div>

			<div class="fsi-card fsi-locked" data-pro-open>
				<div class="fsi-card-h"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'eye', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Conditional settings', 'fuse-social-floating-sidebar' ); ?> <?php echo self::pro_badge(); // phpcs:ignore ?></h3><div class="fsi-cs"><?php esc_html_e( 'Hide the floating bar on specific content', 'fuse-social-floating-sidebar' ); ?></div></div></div>
				<div class="fsi-card-b">
					<?php self::lock_cta(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/* ---------------- Analytics tab ---------------- */

	private static function tab_analytics( $s ) {
		?>
		<div class="fsi-tp" data-tab="analytics">
			<div class="fsi-card fsi-card-danger">
				<div class="fsi-card-h" style="border-bottom:none">
					<div class="fsi-ci"><?php echo Fuse_UI::icon( 'chart-line', 16 ); // phpcs:ignore ?></div>
					<div><h3><?php esc_html_e( 'Analytics is a Pro feature', 'fuse-social-floating-sidebar' ); ?> <?php echo self::pro_badge(); // phpcs:ignore ?></h3><div class="fsi-cs"><?php esc_html_e( 'Upgrade to track clicks and shares on your icons and share buttons.', 'fuse-social-floating-sidebar' ); ?></div></div>
					<div class="fsi-ch-act"><button type="button" class="fsi-btn fsi-btn-accent fsi-btn-sm" data-pro-open><?php esc_html_e( 'Unlock with Pro', 'fuse-social-floating-sidebar' ); ?></button></div>
				</div>
			</div>
			<div class="fsi-card fsi-locked" data-pro-open>
				<div class="fsi-card-h"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'chart-bar', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Clicks & shares', 'fuse-social-floating-sidebar' ); ?></h3><div class="fsi-cs"><?php esc_html_e( 'Daily trend, top platform, and a full breakdown', 'fuse-social-floating-sidebar' ); ?></div></div></div>
				<div class="fsi-card-b">
					<?php self::lock_cta(); ?>
				</div>
			</div>
		</div>
		<?php
	}

	/* ---------------- Advanced tab ---------------- */

	private static function tab_advanced( $s ) {
		$a = $s['advanced'];
		?>
		<!-- settings portion (part of the main form via form="fsi-form") -->
		<div class="fsi-tp" data-tab="advanced">
			<div class="fsi-cols">
				<div class="fsi-card" style="margin:0">
					<div class="fsi-card-h"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'code', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Custom CSS', 'fuse-social-floating-sidebar' ); ?></h3><div class="fsi-cs"><?php esc_html_e( 'Loaded only on the front end', 'fuse-social-floating-sidebar' ); ?></div></div></div>
					<div class="fsi-card-b">
						<textarea form="fsi-form" class="fsi-code-input" name="fuse[advanced][custom_css]" spellcheck="false" placeholder=".fsi-floating { gap: 14px; }"><?php echo esc_textarea( $a['custom_css'] ); ?></textarea>
					</div>
				</div>
			</div>
		</div>

		<!-- tools portion (separate forms — shares the "advanced" tab) -->
		<div class="fsi-tp" data-tab="advanced">
			<div class="fsi-cols">
				<div class="fsi-card" style="margin:0">
					<div class="fsi-card-h"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'package', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Import / export', 'fuse-social-floating-sidebar' ); ?></h3><div class="fsi-cs"><?php esc_html_e( 'Move settings between sites', 'fuse-social-floating-sidebar' ); ?></div></div></div>
					<div class="fsi-card-b">
						<?php self::notice( 'imported', __( 'Settings imported.', 'fuse-social-floating-sidebar' ) ); ?>
						<div class="fsi-tool-row">
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="fuse_export_settings">
								<?php wp_nonce_field( 'fuse_export_settings' ); ?>
								<button type="submit" class="fsi-btn fsi-btn-secondary fsi-btn-sm"><?php echo Fuse_UI::icon( 'download', 15 ); // phpcs:ignore ?> <?php esc_html_e( 'Export JSON', 'fuse-social-floating-sidebar' ); ?></button>
							</form>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data" class="fsi-import-form">
								<input type="hidden" name="action" value="fuse_import_settings">
								<?php wp_nonce_field( 'fuse_import_settings' ); ?>
								<input type="file" name="fuse_import_file" accept="application/json" required>
								<button type="submit" class="fsi-btn fsi-btn-secondary fsi-btn-sm"><?php echo Fuse_UI::icon( 'upload', 15 ); // phpcs:ignore ?> <?php esc_html_e( 'Import', 'fuse-social-floating-sidebar' ); ?></button>
							</form>
						</div>
					</div>
				</div>
				<div>
					<div class="fsi-card">
						<div class="fsi-card-h"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'refresh', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Migrate from old plugin', 'fuse-social-floating-sidebar' ); ?></h3><div class="fsi-cs"><?php esc_html_e( 'Import from Fuse Social Floating Sidebar', 'fuse-social-floating-sidebar' ); ?></div></div></div>
						<div class="fsi-card-b">
							<?php self::notice( 'migrated', __( 'Migration complete.', 'fuse-social-floating-sidebar' ), __( 'No legacy plugin data was found.', 'fuse-social-floating-sidebar' ) ); ?>
							<?php if ( ! empty( $s['_migrated'] ) && ! empty( $s['_migration_report'] ) ) :
								$r = $s['_migration_report']; ?>
								<ul class="fsi-report">
									<li><?php echo esc_html( sprintf( __( 'Source: %s', 'fuse-social-floating-sidebar' ), $r['source'] ) ); ?></li>
									<li><?php echo esc_html( sprintf( __( 'Links imported: %d', 'fuse-social-floating-sidebar' ), $r['links_imported'] ) ); ?></li>
								</ul>
							<?php endif; ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
								<input type="hidden" name="action" value="fuse_run_migration">
								<?php wp_nonce_field( 'fuse_run_migration' ); ?>
								<button type="submit" class="fsi-btn fsi-btn-secondary fsi-btn-sm"><?php echo empty( $s['_migrated'] ) ? esc_html__( 'Import from old plugin', 'fuse-social-floating-sidebar' ) : esc_html__( 'Re-run migration', 'fuse-social-floating-sidebar' ); ?></button>
							</form>
						</div>
					</div>
					<div class="fsi-card fsi-card-danger">
						<div class="fsi-card-h" style="border-bottom:none"><div class="fsi-ci"><?php echo Fuse_UI::icon( 'trash', 16 ); // phpcs:ignore ?></div><div><h3><?php esc_html_e( 'Reset all settings', 'fuse-social-floating-sidebar' ); ?></h3><div class="fsi-cs"><?php esc_html_e( 'Restores defaults, cannot be undone', 'fuse-social-floating-sidebar' ); ?></div></div>
							<div class="fsi-ch-act">
								<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" onsubmit="return confirm('<?php esc_attr_e( 'Reset all settings to defaults?', 'fuse-social-floating-sidebar' ); ?>');">
									<input type="hidden" name="action" value="fuse_reset_settings">
									<?php wp_nonce_field( 'fuse_reset_settings' ); ?>
									<button type="submit" class="fsi-btn fsi-btn-secondary fsi-btn-sm fsi-danger"><?php esc_html_e( 'Reset', 'fuse-social-floating-sidebar' ); ?></button>
								</form>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}
}

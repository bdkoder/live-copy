<?php
/**
 * Settings storage and admin page scaffold.
 *
 * @package ElementorLiveCopy
 */

namespace ElementorLiveCopy;

class Live_Copy_Settings {

	const OPT_KEY = 'live_copy_settings';

	public static function get_defaults() {
		return [
			'enable'                => true,
			'visibility'            => 'everyone', // everyone | logged_in | editors
			'show_copy_btn'         => true,
			'show_download_btn'     => true,
			'specific_section_mode' => false,
			'disable_on_mobile'     => true,
			'help_url'              => '', // info button / "how it works" video link
			'ip_logging'            => 'anonymized', // full | anonymized | none
		];
	}

	public static function get_all() {
		$saved = get_option( self::OPT_KEY, [] );
		return wp_parse_args( $saved, self::get_defaults() );
	}

	public static function save( array $data ) {
		$allowed_visibility = [ 'everyone', 'logged_in', 'editors' ];
		$allowed_ip         = [ 'full', 'anonymized', 'none' ];

		$clean = [
			'enable'                => ! empty( $data['enable'] ),
			'show_copy_btn'         => ! empty( $data['show_copy_btn'] ),
			'show_download_btn'     => ! empty( $data['show_download_btn'] ),
			'specific_section_mode' => ! empty( $data['specific_section_mode'] ),
			'disable_on_mobile'     => ! empty( $data['disable_on_mobile'] ),
			'help_url'              => isset( $data['help_url'] ) ? esc_url_raw( trim( $data['help_url'] ) ) : '',
			'visibility'            => in_array( $data['visibility'] ?? 'everyone', $allowed_visibility, true )
				? $data['visibility']
				: 'everyone',
			'ip_logging'            => in_array( $data['ip_logging'] ?? 'anonymized', $allowed_ip, true )
				? $data['ip_logging']
				: 'anonymized',
		];

		return update_option( self::OPT_KEY, $clean );
	}

	public static function is_enabled_for_current_user() {
		$settings   = self::get_all();

		if ( ! $settings['enable'] ) {
			return false;
		}

		switch ( $settings['visibility'] ) {
			case 'logged_in':
				return is_user_logged_in();
			case 'editors':
				return current_user_can( 'edit_posts' );
			default:
				return true;
		}
	}

	/** Register the Settings > Live Copy admin page. */
	public static function register_admin_page() {
		add_options_page(
			__( 'Live Copy Settings', 'live-copy' ),
			__( 'Live Copy', 'live-copy' ),
			'manage_options',
			'live-copy-settings',
			[ __CLASS__, 'render_page' ]
		);
	}

	public static function render_page() {
		echo '<div id="live-copy-admin-root"></div>';
	}

	/** Enqueue the React admin app on our settings page only. */
	public static function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_live-copy-settings' !== $hook ) {
			return;
		}

		$app_js  = LIVE_COPY_PATH . 'admin/build/app.js';
		$app_css = LIVE_COPY_PATH . 'admin/build/style.css';

		if ( ! file_exists( $app_js ) ) {
			return;
		}

		wp_enqueue_script(
			'live-copy-admin',
			LIVE_COPY_ADMIN_URL . 'app.js',
			[],
			filemtime( $app_js ),
			true
		);

		if ( file_exists( $app_css ) ) {
			wp_enqueue_style(
				'live-copy-admin-style',
				LIVE_COPY_ADMIN_URL . 'style.css',
				[],
				filemtime( $app_css )
			);
		}

		wp_localize_script(
			'live-copy-admin',
			'liveCopyAdmin',
			[
				'restUrl'  => rest_url(),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'settings' => self::get_all(),
				'canClear' => defined( 'LIVE_COPY_ALLOW_CLEAR' ) && LIVE_COPY_ALLOW_CLEAR,
			]
		);
	}
}

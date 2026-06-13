<?php
/**
 * Live Copy Feature
 *
 * @package ElementorLiveCopy
 * @since 1.0.0
 */

namespace ElementorLiveCopy;

use Elementor\Plugin;

class Live_Copy {

	public function __construct() {
		add_action( 'wp_ajax_nopriv_ellc_get_data', [ $this, 'handle_get_data' ] );
		add_action( 'wp_ajax_ellc_get_data',        [ $this, 'handle_get_data' ] );

		// Legacy action kept for backward compat (maps to copy type).
		add_action( 'wp_ajax_nopriv_ellc_copy_data', [ $this, 'handle_get_data' ] );
		add_action( 'wp_ajax_ellc_copy_data',        [ $this, 'handle_get_data' ] );
	}

	public static function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}

		if ( ! Live_Copy_Settings::is_enabled_for_current_user() ) {
			return;
		}

		$settings = Live_Copy_Settings::get_all();

		// Mobile check — skip loading entirely when disable_on_mobile is on.
		// wp_is_mobile() checks User-Agent; JS also guards as a fallback.
		if ( $settings['disable_on_mobile'] && wp_is_mobile() ) {
			return;
		}

		$obj = new self();
		add_action( 'wp_enqueue_scripts', [ $obj, 'enqueue_styles' ], 998 );
		add_action( 'wp_enqueue_scripts', [ $obj, 'enqueue_scripts' ], 998 );
	}

	public function enqueue_styles() {
		wp_register_style( 'live-copy-style', LIVE_COPY_ASSETS_URL . 'css/style.css', [], LIVE_COPY_VER, 'all' );
		wp_enqueue_style( 'live-copy-style' );
	}

	public function enqueue_scripts() {
		$settings = Live_Copy_Settings::get_all();

		// Help URL is cached client-side in the `ellc_help_url` cookie ("ver|url").
		// `help_ver` is a short hash of the URL, so changing it in settings bumps the
		// version and the client re-seeds immediately. We only resend the URL when the
		// client's cached version is missing or stale — repeat visits skip the value.
		$help_setting = $settings['help_url'];
		$help_ver     = $help_setting ? substr( md5( $help_setting ), 0, 8 ) : '';

		$cookie_raw = isset( $_COOKIE['ellc_help_url'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['ellc_help_url'] ) ) : '';
		$cookie_ver = ( $cookie_raw && false !== strpos( $cookie_raw, '|' ) ) ? explode( '|', $cookie_raw, 2 )[0] : '';

		$help_url = ( '' !== $help_ver && $cookie_ver === $help_ver ) ? '' : $help_setting;

		wp_register_script( 'live-copy-script', LIVE_COPY_ASSETS_URL . 'js/script.js', [ 'jquery' ], LIVE_COPY_VER, true );
		wp_enqueue_script( 'live-copy-script' );
		wp_localize_script(
			'live-copy-script',
			'ElLiveCopyData',
			[
				'enable'         => true,
				'post_id'        => get_the_ID(),
				'post_slug'      => get_post_field( 'post_name', get_the_ID() ),
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'el-live-copy-nonce' ),
				'rest_nonce_url' => rest_url( Live_Copy_Rest::NS . '/nonce' ),
				'show_copy'      => (bool) $settings['show_copy_btn'],
				'show_download'  => (bool) $settings['show_download_btn'],
				'disable_mobile' => (bool) $settings['disable_on_mobile'],
				'help_url'       => $help_url,
				'help_ver'       => $help_ver,
			]
		);
	}

	/**
	 * Unified AJAX handler for copy and download actions.
	 * Accepts action_type = 'copy' | 'download' (default: 'copy').
	 *
	 * The request is CSRF-protected by a nonce (action `el-live-copy-nonce`).
	 * On full-page-cached sites the embedded nonce can go stale; the front-end
	 * detects the `invalid_nonce` / 403 response, fetches a fresh nonce from the
	 * REST endpoint, and retries once. The endpoint only ever exposes the public
	 * Elementor JSON of published pages (private pages require login below), so
	 * the nonce is CSRF + anti-abuse, not the sole access gate.
	 */
	public function handle_get_data() {
		$nonce = isset( $_REQUEST['_wp_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wp_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'el-live-copy-nonce' ) ) {
			wp_send_json_error(
				[
					'code'    => 'invalid_nonce',
					'message' => __( 'Security check failed. Please retry.', 'live-copy' ),
				],
				403
			);
		}

		$post_id     = isset( $_REQUEST['post_id'] )     ? absint( wp_unslash( $_REQUEST['post_id'] ) )                    : 0;
		$widget_id   = isset( $_REQUEST['widget_id'] )   ? sanitize_text_field( wp_unslash( $_REQUEST['widget_id'] ) )     : '';
		$action_type = isset( $_REQUEST['action_type'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['action_type'] ) )   : 'copy';
		$action_type = in_array( $action_type, [ 'copy', 'download' ], true ) ? $action_type : 'copy';

		if ( ! $post_id || ! $widget_id ) {
			wp_send_json_error( [ 'message' => __( 'Missing required parameters.', 'live-copy' ) ] );
		}

		// Validate the post exists and is publicly accessible.
		$post = get_post( $post_id );
		if ( ! $post || ! in_array( $post->post_status, [ 'publish', 'private' ], true ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid Post or Page ID.', 'live-copy' ) ] );
		}

		// Private posts require the user to be logged in.
		if ( 'private' === $post->post_status && ! is_user_logged_in() ) {
			wp_send_json_error( [ 'message' => __( 'Access denied.', 'live-copy' ) ], 403 );
		}

		$result = $this->get_live_copy_data_settings( $post_id, $widget_id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( [ 'message' => $result->get_error_message() ] );
		}

		// Log after successful data retrieval.
		Live_Copy_DB::log( $post_id, $post->post_name, $widget_id, $action_type );

		wp_send_json_success( [ 'widget' => $result ] );
		wp_die();
	}

	/**
	 * Depth-first search for an element by id at any nesting level.
	 * Buttons now attach to nested containers too, so the match must recurse
	 * into each element's child `elements` array — not just the top level.
	 *
	 * @param array  $elements  Elementor elements tree (or sub-tree).
	 * @param string $widget_id Target element id.
	 * @return array|false The matched element node, or false.
	 */
	private function find_element_recursive( $elements, $widget_id ) {
		if ( ! is_array( $elements ) ) {
			return false;
		}

		foreach ( $elements as $element ) {
			if ( isset( $element['id'] ) && (string) $widget_id === (string) $element['id'] ) {
				return $element;
			}

			if ( ! empty( $element['elements'] ) && is_array( $element['elements'] ) ) {
				$found = $this->find_element_recursive( $element['elements'], $widget_id );
				if ( false !== $found ) {
					return $found;
				}
			}
		}

		return false;
	}

	protected function get_live_copy_data_settings( $post_id, $widget_id ) {
		$errors = new \WP_Error();

		if ( ! class_exists( '\Elementor\Plugin' ) ) {
			$errors->add( 'msg', __( 'Elementor is not active.', 'live-copy' ) );
			return $errors;
		}

		$elementor = Plugin::$instance;
		$page_meta = $elementor->documents->get( $post_id );

		if ( ! $page_meta ) {
			$errors->add( 'msg', __( 'Invalid Post or Page ID.', 'live-copy' ) );
			return $errors;
		}

		$meta_data = $page_meta->get_elements_data();

		if ( ! $meta_data ) {
			$errors->add( 'msg', __( 'Page is not built with Elementor.', 'live-copy' ) );
			return $errors;
		}

		$element = $this->find_element_recursive( $meta_data, $widget_id );

		if ( false === $element ) {
			$errors->add( 'msg', __( 'Section not found.', 'live-copy' ) );
			return $errors;
		}

		// Wrap in Elementor's "paste from other site" envelope.
		$widget_data = [
			'type'     => 'elementor',
			'siteurl'  => get_rest_url(),
			'elements' => [ $element ],
		];

		return $widget_data;
	}
}

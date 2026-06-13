<?php
/**
 * REST API routes for settings and reporting.
 *
 * @package ElementorLiveCopy
 */

namespace ElementorLiveCopy;

class Live_Copy_Rest {

	const NS = 'live-copy/v1';

	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_routes' ] );
	}

	public static function register_routes() {
		// Settings — GET + POST
		register_rest_route(
			self::NS,
			'/settings',
			[
				[
					'methods'             => \WP_REST_Server::READABLE,
					'callback'            => [ __CLASS__, 'get_settings' ],
					'permission_callback' => [ __CLASS__, 'admin_only' ],
				],
				[
					'methods'             => \WP_REST_Server::CREATABLE,
					'callback'            => [ __CLASS__, 'save_settings' ],
					'permission_callback' => [ __CLASS__, 'admin_only' ],
				],
			]
		);

		// Fresh nonce — public, uncached. Lets cached pages recover a valid token.
		register_rest_route(
			self::NS,
			'/nonce',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'get_nonce' ],
				'permission_callback' => '__return_true',
			]
		);

		// Stats — GET with optional ?days= param
		register_rest_route(
			self::NS,
			'/stats',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'get_stats' ],
				'permission_callback' => [ __CLASS__, 'admin_only' ],
				'args'                => [
					'days' => [
						'default'           => 30,
						'sanitize_callback' => 'absint',
						'validate_callback' => static function ( $val ) {
							// 0 = all time; otherwise a bounded day window.
							return $val >= 0 && $val <= 3650;
						},
					],
				],
			]
		);

		// Clear all history.
		register_rest_route(
			self::NS,
			'/stats/clear',
			[
				'methods'             => \WP_REST_Server::CREATABLE,
				'callback'            => [ __CLASS__, 'clear_stats' ],
				'permission_callback' => [ __CLASS__, 'admin_only' ],
			]
		);

		// Export history rows (client builds the CSV).
		register_rest_route(
			self::NS,
			'/export',
			[
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => [ __CLASS__, 'export_rows' ],
				'permission_callback' => [ __CLASS__, 'admin_only' ],
			]
		);
	}

	/**
	 * Issue a fresh CSRF nonce for the copy/download AJAX action.
	 * Public by design (nopriv copy must work). No-cache headers prevent any
	 * page/proxy cache from pinning a stale value.
	 */
	public static function get_nonce() {
		nocache_headers();
		$response = rest_ensure_response( [ 'nonce' => wp_create_nonce( 'el-live-copy-nonce' ) ] );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		return $response;
	}

	public static function get_settings() {
		return rest_ensure_response( Live_Copy_Settings::get_all() );
	}

	public static function save_settings( \WP_REST_Request $request ) {
		$data = $request->get_json_params();

		if ( empty( $data ) || ! is_array( $data ) ) {
			return new \WP_Error( 'invalid_data', __( 'Invalid settings data.', 'live-copy' ), [ 'status' => 400 ] );
		}

		Live_Copy_Settings::save( $data );

		return rest_ensure_response( Live_Copy_Settings::get_all() );
	}

	public static function get_stats( \WP_REST_Request $request ) {
		// absint keeps 0 (all time) intact; default of 30 applied when param absent.
		$days = min( absint( $request->get_param( 'days' ) ), 3650 );

		return rest_ensure_response( Live_Copy_DB::get_stats( $days ) );
	}

	public static function clear_stats() {
		// Destructive — require an explicit opt-in constant in wp-config.php so a
		// careless admin can't wipe analytics from the dashboard by accident.
		if ( ! ( defined( 'LIVE_COPY_ALLOW_CLEAR' ) && LIVE_COPY_ALLOW_CLEAR ) ) {
			return new \WP_Error(
				'clear_disabled',
				__( 'Clearing is disabled. Add define( \'LIVE_COPY_ALLOW_CLEAR\', true ); to wp-config.php to enable it.', 'live-copy' ),
				[ 'status' => 403 ]
			);
		}

		$deleted = Live_Copy_DB::clear_all();
		return rest_ensure_response( [ 'deleted' => $deleted ] );
	}

	public static function export_rows() {
		return rest_ensure_response( [ 'rows' => Live_Copy_DB::get_all_rows( 5000 ) ] );
	}

	public static function admin_only() {
		return current_user_can( 'manage_options' );
	}
}

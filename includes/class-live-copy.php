<?php
/**
 * Live Copy Feature
 *
 * @package ElementorLiveCopy
 * @since 1.0.0
 */

namespace ElementorLiveCopy;

use Elementor\Plugin;

/**
 * Live Copy Class
 *
 * @since 1.0.0
 */
class Live_Copy {

	/**
	 * Live Copy constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		add_action( 'wp_ajax_nopriv_ellc_copy_data', [ $this, 'get_live_copy_data' ] );
		add_action( 'wp_ajax_ellc_copy_data', [ $this, 'get_live_copy_data' ] );
	}

	/**
	 * Get Live Copy Data
	 *
	 * @since 1.0.0
	 */
	public static function enqueue_assets() {
		if ( is_admin() ) {
			return;
		}
		$obj = new self();
		add_action( 'wp_enqueue_scripts', [ $obj, 'enqueue_styles' ], 998 );
		add_action( 'wp_enqueue_scripts', [ $obj, 'enqueue_scripts' ], 998 );
	}

	/**
	 * Enqueue Styles
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		wp_register_style( 'live-copy-style', LIVE_COPY_ASSETS_URL . 'css/style.css', [], LIVE_COPY_VER, 'all' );
		wp_enqueue_style( 'live-copy-style' );
	}

	/**
	 * Enqueue Scripts
	 *
	 * @since 1.0.0
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		wp_register_script( 'live-copy-script', LIVE_COPY_ASSETS_URL . 'js/script.js', [ 'jquery' ], LIVE_COPY_VER, true );
		wp_enqueue_script( 'live-copy-script' );
		wp_localize_script(
			'live-copy-script',
			'ElLiveCopyData',
			[
				'enable'   => true,
				'post_id'  => get_the_ID(),
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( 'el-live-copy-nonce' ),
			]
		);
	}

	/**
	 * Get Live Copy Data
	 *
	 * @since 1.0.0
	 * @param array $elements Widget ID.
	 * @param int   $form_id Post ID.
	 */
	private function find_element_recursive( $elements, $form_id ) {
		foreach ( $elements as $element ) {
			if ( $form_id === $element['id'] ) {
				$section_data             = [];
				$section_data['elements'] = [ $element ];
				$meta_data                = [];
				$meta_data['type']        = 'elementor';
				$meta_data['siteurl']     = get_rest_url();
				$section_data             = array_merge( $meta_data, $section_data );
				return $section_data;
			}
		}

		return false;
	}

	/**
	 * Get Live Copy Data
	 *
	 * @since 1.0.0
	 */
	public function get_live_copy_data() {
		if ( ! isset( $_REQUEST ) ) {
			wp_send_json_error( [ 'message' => __( 'Invalid request!', 'live-copy' ) ] );
		}
		$post_id   = isset( $_REQUEST['post_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['post_id'] ) ) : false; // 7.
		$widget_id = isset( $_REQUEST['widget_id'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['widget_id'] ) ) : false; // b0ec141.
		$nonce     = isset( $_REQUEST['_wp_nonce'] ) ? wp_unslash( $_REQUEST['_wp_nonce'] ) : '';
		$nonce     = isset( $_REQUEST['_wp_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['_wp_nonce'] ) ) : '';

		// if ( ! wp_verify_nonce( $nonce, 'el-live-copy-nonce' ) ) {
		// wp_send_json_error( array( 'message' => __( 'Sorry, invalid nonce!', 'live-copy' ) ) );
		// }

		$result = $this->get_live_copy_data_settings( $post_id, $widget_id );

		if ( is_wp_error( $result ) ) {
			$errors = $result->get_error_message();
			wp_send_json_error( [ 'message' => $errors ] );
		} else {
			define(
				'PLUGIN_DIR_URL',
				plugin_dir_url( __FILE__ ) . '/assets/'
			);
			$data = [
				'widget' => $result,
			];
			wp_send_json_success( $data );
		}
		wp_die();
	}

	/**
	 * Get Live Copy Data
	 *
	 * @since 1.0.0
	 *
	 * @param int $post_id Post ID.
	 * @param int $widget_id Widget ID.
	 */
	protected function get_live_copy_data_settings( $post_id, $widget_id ) {
		$errors = new \WP_Error();

		$elementor = Plugin::$instance;
		$page_meta = $elementor->documents->get( $post_id );

		if ( ! $page_meta ) {
			$errors->add( 'msg', __( 'Invalid Post or Page ID.', 'live-copy' ) );
			return $errors;
		}

		$meta_data = $page_meta->get_elements_data();

		if ( ! $meta_data ) {
			$errors->add( 'msg', __( 'Page page is not under elementor.', 'live-copy' ) );
			return $errors;
		}

		$widget_data = $this->find_element_recursive( $meta_data, $widget_id );
		return $widget_data;
	}
}

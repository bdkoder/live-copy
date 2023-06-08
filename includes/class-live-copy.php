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
class LiveCopy {
    // constructor
    public function __construct() {
        if (!is_admin()) {
            add_action('wp_enqueue_scripts', array($this, 'enqueue_assets'));
        }
        add_action('wp_ajax_nopriv_ellc_copy_data', array($this, 'get_live_copy_data'));
        add_action('wp_ajax_ellc_copy_data', array($this, 'get_live_copy_data'));
    }

    public function enqueue_assets() {
        $this->enqueue_styles();
        $this->enqueue_scripts();
    }

    public function enqueue_styles() {
        wp_register_style('live-copy-style', LIVE_COPY_ASSETS_URL . 'css/style.css', array(), LIVE_COPY_VER, 'all');
        wp_enqueue_style('live-copy-style');
    }

    public function enqueue_scripts() {
        wp_register_script('live-copy-clipboard', LIVE_COPY_ASSETS_URL .  'vendor/clipboard.min.js', array(), LIVE_COPY_VER, 'all');
        wp_enqueue_script('live-copy-clipboard');

        wp_register_script('live-copy-script', LIVE_COPY_ASSETS_URL .  'js/script.js', array('jquery'), LIVE_COPY_VER, true);
        wp_enqueue_script('live-copy-script');
        wp_localize_script('live-copy-script', 'ElLiveCopyData', array(
            'enable' => true,
            'post_id' => get_the_ID(),
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('el-live-copy-nonce'),
        ));
        // localize script
    }

    private function find_element_recursive($elements, $form_id) {
        foreach ($elements as $element) {
            if ($form_id === $element['id']) {
                $section_data = array();
                $section_data['elements'] = [$element];
                $meta_data = array();
                $meta_data['type'] = 'elementor';
                $meta_data['siteurl'] = get_rest_url();
                $section_data = array_merge($meta_data, $section_data);

                return $section_data;
            }
        }

        return false;
    }
    public function get_live_copy_data() {
        if (isset($_REQUEST)) {
            $post_id    = sanitize_text_field($_REQUEST['post_id']); // 7
            $widget_id  = sanitize_text_field($_REQUEST['widget_id']); // b0ec141
            $nonce = isset($_REQUEST['_wp_nonce']) ? $_REQUEST['_wp_nonce'] : '';

            if (!wp_verify_nonce($nonce, 'el-live-copy-nonce')) {
                wp_send_json_error(['message' => __('Sorry, invalid nonce!', 'live-copy-paste')]);
            }

            $result = $this->get_live_copy_data_settings($post_id, $widget_id);

            if (is_wp_error($result)) {
                // Parse errors into a string and append as parameter to redirect
                $errors  = $result->get_error_message();
                wp_send_json_error(['message' => $errors]);
            } else {
                define(
                    'plugin_dir_url()',
                    plugin_dir_url(__FILE__) . '/assets/'
                );
                $data = array(
                    'widget'    => $result
                );
                wp_send_json_success($data);
            }
            wp_die();
        }
    }

    protected function get_live_copy_data_settings($post_id, $widget_id) {
        $errors = new \WP_Error();

        $elementor  = Plugin::$instance;
        $pageMeta   = $elementor->documents->get($post_id);

        if (!$pageMeta) {
            $errors->add('msg', __('Invalid Post or Page ID.', 'live-copy-paste'));
            return $errors;
        }

        $metaData       = $pageMeta->get_elements_data();

        if (!$metaData) {
            $errors->add('msg', __('Page page is not under elementor.', 'live-copy-paste'));
            return $errors;
        }

        $widget_data    = $this->find_element_recursive($metaData, $widget_id);
        // echo wp_json_encode($widget_data);
        // die();
        return $widget_data;
    }
}

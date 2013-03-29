<?php
/*
  Plugin Name: BP Active
  Plugin URI: http://trenvo.com
  Description: Description
  Version: 0.1
  Author: Mike Martel
  Author URI: http://trenvo.nl
 */

// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

/**
 * Version number
 *
 * @since 0.1
 */
define('BP_ACTIVE_VERSION', '0.1');

/**
 * PATHs and URLs
 *
 * @since 0.1
 */
define('BP_ACTIVE_DIR', plugin_dir_path(__FILE__));
define('BP_ACTIVE_URL', plugin_dir_url(__FILE__));
define('BP_ACTIVE_LIB', BP_ACTIVE_DIR . 'lib/' );
define('BP_ACTIVE_TEMPLATES', BP_ACTIVE_DIR . 'templates/' );
define('BP_ACTIVE_INC_URL', BP_ACTIVE_URL . '_inc/');
define('BP_ACTIVE_JS_URL', BP_ACTIVE_INC_URL . 'js/');
define('BP_ACTIVE_CSS_URL', BP_ACTIVE_INC_URL . 'css/');

define ('BPA_PROTOCOL', (@$_SERVER["HTTPS"] == 'on' ? 'https://' : 'http://'), true);

/** @TODO make backward compatible by converting defines:
 * BPFB_THUMBNAIL_IMAGE_SIZE
 * BPFB_IMAGE_LIMIT
 */

$wp_upload_dir = wp_upload_dir();
defined('BP_ACTIVE_BASE_IMAGE_DIR') || define('BP_ACTIVE_BASE_IMAGE_DIR', $wp_upload_dir['basedir'] . '/activity/', true);
defined('BP_ACTIVE_BASE_IMAGE_URL') || define('BP_ACTIVE_BASE_IMAGE_URL', $wp_upload_dir['baseurl'] . '/activity/', true);
defined('BP_ACTIVE_TEMP_IMAGE_DIR') || define('BP_ACTIVE_TEMP_IMAGE_DIR', BP_ACTIVE_BASE_IMAGE_DIR . 'tmp/', true);
defined('BP_ACTIVE_TEMP_IMAGE_URL') || define('BP_ACTIVE_TEMP_IMAGE_URL', BP_ACTIVE_BASE_IMAGE_URL . 'tmp/', true);
unset ( $wp_upload_dir );

// Hook up the installation routine and check if we're really, really set to go
require_once BP_ACTIVE_DIR . 'install.php';
register_activation_hook(__FILE__, array ( 'BP_Active_Installer', 'install' ) );
BP_Active_Installer::check();

/**
 * Requires and includes
 *
 * @since 0.1
 */
require_once ( BP_ACTIVE_DIR . 'functions.php' );
require_once ( BP_ACTIVE_DIR . 'display.php' );

if (!class_exists('BP_Active')) :

    /**
     * @todo Set up cron job to get rid of old tmp images
     */
    class BP_Active    {

        public $max_images = 5;

        /**
         * Creates an instance of the BP_Active class
         *
         * @return BP_Active object
         * @since 0.1
         * @static
        */
        public static function &init() {
            static $instance = false;

            if (!$instance) {
                load_plugin_textdomain('bp-active', false, basename(BP_ACTIVE_DIR) . '/languages/');
                $instance = new BP_Active;
            }

            return $instance;
        }

        /**
         * Constructor
         *
         * @since 0.1
         */
        public function __construct() {
            do_action('bpa_init');

            if ( defined ( 'DOING_AJAX' ) && DOING_AJAX )
                require_once ( BP_ACTIVE_DIR . 'ajax.php' );
            else
                add_action('init', array ( &$this, 'enqueue_css_js' ) );
        }

        public function enqueue_css_js() {
            global $bp;

            if (
                // Load the scripts on Activity pages
                (defined('BP_ACTIVITY_SLUG') && bp_is_activity_component())
                ||
                // Load the scripts when Activity page is the Home page
                (defined('BP_ACTIVITY_SLUG') && 'page' == get_option('show_on_front') && is_front_page() && BP_ACTIVITY_SLUG == get_option('page_on_front'))
                ||
                // Load the script on Group home page
                (defined('BP_GROUPS_SLUG') && bp_is_groups_component() && 'home' == $bp->current_action)
            ) {

                add_action( 'wp_enqueue_scripts', array ( $this, 'css_load_styles' ) );
                add_action( 'wp_enqueue_scripts', array ( $this, 'js_load_scripts' ) );

                do_action('bpa_enqueue_css_js');
            }
        }

        /**
         * Introduces `plugins_url()` and other significant URLs as root variables (global).
         *
         * @since bpfb
         * @todo Don't load this directly into the global namespace
         */
        function getBpaVars () {
//            printf(
//                '<script type="text/javascript">' .
//                    'var _bpaRootUrl="%s";' .
//                    'var _bpaTempImageUrl="%s";' .
//                    'var _bpaBaseImageUrl="%s";' .
//                '</script>',
//                BP_ACTIVE_URL,
//                BP_ACTIVE_TEMP_IMAGE_URL,
//                BP_ACTIVE_BASE_IMAGE_URL
//            );
            $bpaVars = apply_filters('bpa_vars', array(
                "rootUrl"       => BP_ACTIVE_URL, // Unused?
                "tempImageUrl"  => BP_ACTIVE_TEMP_IMAGE_URL,
                "baseImageUrl"  => BP_ACTIVE_BASE_IMAGE_URL, // Unused?
                "nonce"         => wp_create_nonce('bp-active')
            ));
            return $bpaVars;
        }

        /**
         * Loads needed scripts and l10n strings for JS.
         *
         * @since bpfb
         * @todo Update to fineuploader https://github.com/Widen/fine-uploader
         */
        function js_load_scripts () {
            wp_enqueue_script('jquery');
            wp_enqueue_script('thickbox');

            if (! current_theme_supports ( 'bpa_file_uploader' ) )
                wp_enqueue_script( 'file_uploader', BP_ACTIVE_JS_URL . 'external/fileuploader.js', array( 'jquery' ) );

            if (! current_theme_supports ( 'bpa_liveurl' ) )
                wp_enqueue_script( 'jquery.liveurl', BP_ACTIVE_JS_URL . 'external/jquery.liveurl.js', array( 'jquery' ) );

            wp_enqueue_script('bp-active', BP_ACTIVE_JS_URL . 'bp-active.js', array( 'jquery' ) );
            wp_localize_script('bp-active', 'l10nBpa', array(
                'add_photos' => __('Add photos', 'bp-active'),
                'add_remote_image' => __('Add image URL', 'bp-active'),
                'add_another_remote_image' => __('Add another image URL', 'bp-active'),
                'add_videos' => __('Add videos', 'bp-active'),
                'add_video' => __('Add video', 'bp-active'),
                'add_links' => __('Add links', 'bp-active'),
                'add_link' => __('Add link', 'bp-active'),
                'add' => __('Add', 'bp-active'),
                'cancel' => __('Cancel', 'bp-active'),
                'preview' => __('Preview', 'bp-active'),
                'drop_files' => __('Drop files here to upload', 'bp-active'),
                'upload_file' => __('Upload a file', 'bp-active'),
                'choose_thumbnail' => __('Choose thumbnail', 'bp-active'),
                'no_thumbnail' => __('No thumbnail', 'bp-active'),
                'paste_video_url' => __('Paste video URL here', 'bp-active'),
                'paste_link_url' => __('Paste link here', 'bp-active'),
                'images_limit_exceeded' => sprintf(__("You tried to add too many images, only %d will be posted.", 'bp-active'), $this->max_images),
                // Variables
                '_max_images' => $this->max_images,
            ) );
            wp_localize_script('bp-active', 'bpaOembedHandlers', $this->getoEmbedHandlers());
            wp_localize_script('bp-active', 'bpaVars', $this->getBpaVars());
        }

        private function getoEmbedHandlers() {
            global $bp;
            $embed = $bp->embed;
            $out_a = array();

            // WP Handlers
            foreach ( $embed->handlers as $handlers ) {
                foreach ( $handlers as $handler ) {
                    $out_a[] = $handler['regex'];
                }
            }

            require_once( ABSPATH . WPINC . '/class-oembed.php' );
            $oembed_obj = _wp_oembed_get_object();
            foreach ( (array) $oembed_obj->providers as $provider_matchmask => $provider ) {
                $regex = ( $is_regex = $provider[1] ) ? $provider_matchmask : '#' . str_replace( '___wildcard___', '(.+)', preg_quote( str_replace( '*', '___wildcard___', $provider_matchmask ), '#' ) ) . '#i';

                $out_a[] = $regex;
            }

            /**
             * Convert preg_match regExp to something that'll work in JS
             */
            $out_js = array();
            foreach ( $out_a as $r ) {
                $out_js[] = str_replace( array('/','#i','#'), array('\/','',''), $r);
            }

            return $out_js;

        }

        /**
         * Loads required styles.
         *
         * @since bpfb
         */
        function css_load_styles () {
            wp_enqueue_style('thickbox');

            if (! current_theme_supports ( 'bpa_file_uploader' ) )
                wp_enqueue_style('file_uploader_style', BP_ACTIVE_CSS_URL . 'external/fileuploader.css');

            if (! current_theme_supports ( 'bpa_liveurl' ) )
                wp_enqueue_style('liveurl_style', BP_ACTIVE_CSS_URL . 'external/liveurl.css');

            if (!current_theme_supports('bpa_interface_style'))
                wp_enqueue_style('bpa_interface_style', BP_ACTIVE_CSS_URL . 'bpa_interface.css');

        }

    }

    add_action('bp_include', array('BP_Active', 'init'));
endif;
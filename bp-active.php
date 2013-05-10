<?php
/*
  Plugin Name: BP Active
  Plugin URI: http://trenvo.com
  Description: Description
  Version: 0.1.3
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
define('BP_ACTIVE_VERSION', '0.1.3');

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

/**
 * For backward compatibility: don't show ugly shortcode tags
 */
add_shortcode('bpfb_link', '__return_null');
add_shortcode('bpfb_video', '__return_null');
add_shortcode('bpfb_images', '__return_null');

if (!class_exists('BP_Active')) :

    /**
     * @todo Set up cron job to get rid of old tmp images
     * @todo Check if BPFB is active
     */
    class BP_Active    {

        public $max_images = 5;
        private $bpa_data;

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

            // If BP Edit Activity Stream is active, replace the editor by ours
            if ( function_exists( 'etivite_bp_edit_activity_init' ) )
                require_once ( BP_ACTIVE_DIR . 'edit.php' );

            if ( defined('BP_RESHARE_PLUGIN_VERSION' ) )
                $this->bp_reshare_compat();
        }

        public static function get( $activity_id ) {
            return bp_activity_get_meta( $activity_id, 'bpa_data' );
        }

        /**
         *
         * @param mixed $data/$activity
         * @param int (opt) $activity_id
         */
        public function save($data, $activity_id = 0) {
            if ( is_a ( $data, 'BP_Activity_Activity' ) ) {
                $activity_id = $data->id;
                $input_data = $this->bpa_data;
            } else {
                $input_data = $data;
            }

            $bpa_data = array();
            if ( isset ( $input_data['images'] ) && ! empty ( $input_data['images'] ) ) {
                $images = $this->move_images($input_data['images']);
                if ( $images ) $bpa_data['images'] = $images;
            }
            if ( isset ( $input_data['link'] ) && ! empty ( $input_data['link'] ) ) {
                $link_data = $input_data['link'];
                // Check if the data is meaningful
                if ( ! empty ( $link_data['description'] ) ||
                     ! empty ( $link_data['image'] ) ||
                        $link_data['title'] != $link_data['url'] ||
                        ! strpos( " " . $_POST['content'], $link_data['url'] ) )
                    $bpa_data['link'] = $link_data;
            }

            // Update activity meta
            bp_activity_update_meta($activity_id, 'bpa_blog_id', $GLOBALS['blog_id']);
            if ( ! empty ( $bpa_data ) )
                bp_activity_update_meta($activity_id, 'bpa_data', $bpa_data);
        }

            /**
             * Image moving and resizing routine.
             *
             * Relies on WP built-in image resizing.
             *
             * @param array Image paths to move from temp directory
             * @return mixed Array of new image paths, or (bool)false on failure.
             * @access private
             * @since bpfb
             */
            private function move_images ($imgs) {
                if (!$imgs) return false;
                if (!is_array($imgs)) $imgs = array($imgs);

                global $bp;
                $ret = array();
                $bpa = BP_Active::init();

                $thumb_w = get_option('thumbnail_size_w');
                $thumb_w = $thumb_w ? $thumb_w : 100;
                $thumb_h = get_option('thumbnail_size_h');
                $thumb_h = $thumb_h ? $thumb_h : 100;

                // Override thumbnail image size in wp-config.php
                if (defined('BPA_THUMBNAIL_IMAGE_SIZE')) {
                    list($tw,$th) = explode('x', BPA_THUMBNAIL_IMAGE_SIZE);
                    $thumb_w = (int)$tw ? (int)$tw : $thumb_w;
                    $thumb_h = (int)$th ? (int)$th : $thumb_h;
                }

                $processed = 0;
                foreach ($imgs as $img) {
                    $processed++;
                    if ($bpa->max_images && $processed > $bpa->max_images) break; // Do not even bother to process more.
                    if ( file_exists ( BP_ACTIVE_BASE_IMAGE_DIR . $img ) ) { // We're editing
                        $ret[] = $img;
                        continue;
                    }

                    $pfx = $bp->loggedin_user->id . '_' . preg_replace('/ /', '', microtime());
                    $tmp_img = realpath(BP_ACTIVE_TEMP_IMAGE_DIR . $img);
                    $new_img = BP_ACTIVE_BASE_IMAGE_DIR . "{$pfx}_{$img}";
                    if (@rename($tmp_img, $new_img)) {
                        image_resize($new_img, $thumb_w, $thumb_h, false, 'bpat');
                        $id = $this->add_image_attachment( $img, $new_img );
                        $ret[$id] = pathinfo($new_img, PATHINFO_BASENAME);

                    }
                    else {
                        //var_dump(get_defined_vars()); die;
                        return false;
                    }
                }

                return $ret;
            }

            private function add_image_attachment($filename, $path = '') {
                if ( empty ( $path ) )
                   $path = BP_ACTIVE_TEMP_IMAGE_DIR . $filename;

                $url = BP_ACTIVE_BASE_IMAGE_URL . $filename;
                $title = $filename;
                $content = '';

                if ( !function_exists( 'wp_read_image_metadata' ) )
                    require_once(ABSPATH . 'wp-admin/includes/image.php');

                // use image exif/iptc data for title and caption defaults if possible
                if ( $image_meta = wp_read_image_metadata($path) ) {
                    if ( trim( $image_meta['title'] ) && ! is_numeric( sanitize_title( $image_meta['title'] ) ) )
                        $title = $image_meta['title'];
                    if ( trim( $image_meta['caption'] ) )
                        $content = $image_meta['caption'];
                }

                // Construct the attachment array
                $attachment = array(
                    'post_mime_type' => 'image',
                    'guid' => $url,
                    'post_parent' => 0,
                    'post_title' => $title,
                    'post_content' => $content,
                );

                // Save the data
                $id = wp_insert_attachment($attachment, $filename);
                if ( is_wp_error($id) ) {
                    return 0;
                }
                wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
                return $id;
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
        public function getBpaVars () {
            $bpaVars = apply_filters('bpa_vars', array(
                "rootUrl"       => BP_ACTIVE_URL, // Unused?
                "tempImageUrl"  => BP_ACTIVE_TEMP_IMAGE_URL,
                "baseImageUrl"  => BP_ACTIVE_BASE_IMAGE_URL, // Unused?
                "nonce"         => wp_create_nonce('bp-active'),
                "max_images"    => $this->max_images
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
            ) );
            wp_localize_script('bp-active', 'bpaOembedHandlers', $this->getoEmbedHandlers());
            wp_localize_script('bp-active', 'bpaVars', $this->getBpaVars());
        }

        public function getoEmbedHandlers() {
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

        /**
         * Compatibility with BP Reshare
         */
        private function bp_reshare_compat() {
            add_filter('bp_reshare_prepare_reshare', array ( &$this, 'bp_reshare_reshare_bpa_data' ), 10, 2 );
        }

            public function bp_reshare_reshare_bpa_data( $a, $activity_id ) {
                $bpa_data = self::get($activity_id);
                if ( ! empty ( $bpa_data ) ) {
                    $this->bpa_data = $bpa_data;
                    add_action( 'bp_activity_after_save', array ( &$this, 'save' ) );
                }

                return $a;
            }

    }
    add_action('bp_include', array('BP_Active', 'init'));
endif;
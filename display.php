<?php
// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

class BP_Active_Display {

    private $current_activity_id = 0;
    private $bpa_data;

    public function __construct() {
        add_filter( 'bp_get_activity_content_body', array( &$this, 'setup' ), 1 );
        add_action('bp_activity_entry_content', array ( &$this, '_maybe_display_meta' ) );

        // Selectively turn oembed off, if it's enabled in the first place
        if ( ! defined ( 'BP_EMBED_DISABLE_ACTIVITY') || ! BP_EMBED_DISABLE_ACTIVITY ) {
            define('BP_EMBED_DISABLE_ACTIVITY',true);
            add_filter( 'bp_get_activity_content_body', array( &$this, 'autoembed' ), 8 );
        }

    }

    public function show_images( $image_data, $activity_id ) {
        $this->current_activity_id = $activity_id;
        $out = array ( $this->display_images($image_data) );
        $this->display($out);
    }

    public function setup($c) {
        $this->current_activity_id = bp_get_activity_id();
        $this->bpa_data = BP_Active::get($this->current_activity_id);

        return $c;
    }

    /**
     * Our own autoembed, which first checks if there's an autoembed in the metadata.
     *
     * @param type $content
     * @return type
     */
    public function autoembed($content) {
        $bpa_data = $this->bpa_data;
        if ( empty ( $bpa_data ) || ! isset ( $bpa_data['link'] ) || ! isset ( $bpa_data['link']['embed'] ) || empty ( $bpa_data['link']['embed'] ) )
            $content = &$GLOBALS['bp']->embed->autoembed($content);

        return $content;

    }

    public function _maybe_display_meta() {
        $bpa_data = $this->bpa_data;

        if ( empty ( $bpa_data ) ) return;

        $out = array();


        if ( isset ( $bpa_data['link'] ) && ! empty ( $bpa_data['link'] ) ) {

            if ( isset ( $bpa_data['link']['embed'] ) && ! empty ( $bpa_data['link']['embed'] ) ) {
                $out[] = $this->display_embed( $bpa_data['link']['embed']);
                unset($bpa_data['link']['embed']);
            }
            if ( ! empty ( $bpa_data['link'] ) )
                $out[] = $this->display_link( $bpa_data['link'] );
        }
        if ( isset ( $bpa_data['images'] ) && ! empty ( $bpa_data['images'] ) ) {
            $out[] = $this->display_images( $bpa_data['images'] );
        }

        if ( ! empty ( $out ) ) {
            $this->display($out);
        }

    }

    private function display( $out ) {
        ?>
        <div id="bpa_content_container">
            <?php foreach ( $out as $c ) echo $c; ?>
        </div>
        <?php
    }

    private function display_embed( $url ) {
        global $bp;
        return $bp->embed->parse_oembed( apply_filters( 'embed_post_id', 1 ),$url,array(),wp_embed_defaults());
    }

    private function display_images( $images ) {
        $activity_id = $this->current_activity_id;
        //$activity_blog_id = bp_activity_get_meta($activity_id, 'bpa_blog_id');

        $use_thickbox = defined('BPA_USE_THICKBOX') ? esc_attr(BPA_USE_THICKBOX) : 'thickbox';

		ob_start();
		include( BP_ACTIVE_TEMPLATES . 'images.php');
		$out = ob_get_clean();
        //ob_end_clean();
		return $out;
    }

    private function display_link( $data ) {
        $defaults = array(
            'image' => '',
            'title' => '',
            'description' => '',
            'url' => ''
        );

        $use_thickbox = defined('BPA_USE_THICKBOX') ? esc_attr(BPA_USE_THICKBOX) : 'thickbox';
        extract( shortcode_atts( $defaults, $data ) );
		ob_start();
        if ( ! empty ( $image ) && $title == $url )
            include ( BP_ACTIVE_TEMPLATES . 'link_image.php');
        else include ( BP_ACTIVE_TEMPLATES . 'link.php');
		$out = ob_get_clean();
		return $out;
    }


}
new BP_Active_Display;
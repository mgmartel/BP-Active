<?php
// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

class BP_Active_Ajax
{
    public function __construct() {
        add_action('wp_ajax_bpa_remove_temp_images', array($this, 'ajax_remove_temp_images'));
        add_action('wp_ajax_bpa_preview_oembed_link', array($this, 'ajax_preview_oembed'));
        add_action('wp_ajax_bpa_preview_photo', array($this, 'ajax_preview_photo'));

        add_action('wp_ajax_bpa_post_update', array($this, 'ajax_post_update'));
        do_action('bpa_add_ajax_hooks');
    }

    private function verify_nonce() {
        if ( ! isset ( $_REQUEST['_nonce'] ) || ! wp_verify_nonce( $_REQUEST['_nonce'], 'bp-active' ) ) {
            echo json_encode(array("status" => "error", "message" => "Invalid nonce."));
            exit();
        }
    }

    public function ajax_preview_oembed() {
        global $bp;
        $this->verify_nonce();

        $url = $_POST['data'];
        echo $bp->embed->parse_oembed(1,$url,array(),wp_embed_defaults());

        exit();

    }

	/**
	 * Handles image preview requests.
	 * Relies on ./lib/external/file_uploader.php for images upload handling.
	 * Stores images in the temporary storage.
	 */
	public function ajax_preview_photo () {
        $this->verify_nonce();

		if ( ! class_exists ( 'qqFileUploader' ) ) require_once(BP_ACTIVE_LIB . 'file_uploader.php');
		$uploader = new qqFileUploader ( array( 'jpg', 'jpeg', 'png', 'gif' ) );
		$result = $uploader->handleUpload ( BP_ACTIVE_TEMP_IMAGE_DIR );

        // Check for image rotation
        if ( isset ( $result['success'] ) && $result['success'] ) {
            $filepath = BP_ACTIVE_TEMP_IMAGE_DIR . $result['file'];
            $image = wp_get_image_editor ( $filepath );
            $this->fixOrientation( $image, $filepath );
        }

		header( 'Content-type: application/json', true, 200 );
		echo htmlspecialchars ( json_encode($result), ENT_NOQUOTES );
		exit();
	}

    private function fixOrientation( &$image, $path ) {

        $exif = exif_read_data($path);

        if( isset($exif['Orientation']) )
            $orientation = $exif['Orientation'];
        elseif( isset($exif['IFD0']['Orientation']) )
            $orientation = $exif['IFD0']['Orientation'];
        else
            return false;

        switch($orientation) {
            case 3: // rotate 180 degrees
                $image->rotate(180);
            break;

            case 6: // rotate 90 degrees CW
                $image->rotate(-90);
            break;

            case 8: // rotate 90 degrees CCW
                $image->rotate(90);
            break;
        }
        $image->save($path);
    }

    /**
	 * Clears up the temporary images storage.
	 */
	public function ajax_remove_temp_images () {
        $this->verify_nonce();

		header('Content-type: application/json');
		parse_str($_POST['data'], $data);
		$data = is_array($data) ? $data : array('bpa_photos'=>array());
		foreach ($data['bpa_photos'] as $file) {
			@unlink (BP_ACTIVE_TEMP_IMAGE_DIR . $file);
		}
		echo json_encode(array('status'=>'ok'));
		exit();
	}

	/**
	 * This is where we actually save the activity update.
     * Most of it from bp-default's ajax.php
	 */
	public function ajax_post_update() {
        // Bail if not a POST action
        if ( 'POST' !== strtoupper( $_SERVER['REQUEST_METHOD'] ) )
            return;

        // Check the nonce
        check_admin_referer( 'post_update', '_wpnonce_post_update' );

        if ( ! is_user_logged_in() )
            exit( '-1' );

        if ( empty( $_POST['content'] ) )
            exit( '-1<div id="message" class="error"><p>' . __( 'Please enter some content to post.', 'buddypress' ) . '</p></div>' );

        $activity_id = 0;
        if ( empty( $_POST['object'] ) && bp_is_active( 'activity' ) ) {
            $activity_id = bp_activity_post_update( array( 'content' => $_POST['content'] ) );

        } elseif ( $_POST['object'] == 'groups' ) {
            if ( ! empty( $_POST['item_id'] ) && bp_is_active( 'groups' ) )
                $activity_id = groups_post_update( array( 'content' => $_POST['content'], 'group_id' => $_POST['item_id'] ) );

        } else {
            $activity_id = apply_filters( 'bp_activity_custom_update', $_POST['object'], $_POST['item_id'], $_POST['content'] );
        }

        if ( empty( $activity_id ) )
            exit( '-1<div id="message" class="error"><p>' . __( 'There was a problem posting your update, please try again.', 'buddypress' ) . '</p></div>' );

        /**
         * BEGIN BP_ACTIVE
         */
        $bpa_obj = BP_Active::init();
        $post_data = ( isset ( $_POST['data'] ) && ! empty ( $_POST['data'] ) ) ? $_POST['data'] : false;

        // BP Active data sanitize
        if ( $post_data )
            $bpa_obj->save($post_data,$activity_id);
        /**
         * END BP ACTIVE
         */

        if ( bp_has_activities ( 'include=' . $activity_id ) ) {
            while ( bp_activities() ) {
                bp_the_activity();
                locate_template( array( 'activity/entry.php' ), true );
            }
        }

        exit;

	}

}
new BP_Active_Ajax;
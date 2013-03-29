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
		header( 'Content-type: application/json', true, 200 );
		echo htmlspecialchars ( json_encode($result), ENT_NOQUOTES );
		exit();
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
        $bpa_data = array();
        $post_data = ( isset ( $_POST['data'] ) && ! empty ( $_POST['data'] ) ) ? $_POST['data'] : false;

        // BP Active data sanitize
        if ( $post_data ) {
            if ( isset ( $post_data['images'] ) && ! empty ( $post_data['images'] ) ) {
                $images = $this->move_images($post_data['images']);
                if ( $images ) $bpa_data['images'] = $images;
            }
            if ( isset ( $post_data['link'] ) && ! empty ( $post_data['link'] ) ) {
                $link_data = $post_data['link'];
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
         * END BP ACTIVE
         */

        if ( bp_has_activities ( 'include=' . $activity_id ) ) {
            while ( bp_activities() ) {
                bp_the_activity();
                locate_template( array( 'activity/entry.php' ), true );
            }
        }

        exit;

//		$bpfb_code = $activity = '';
//		$aid = 0;
//        $bpa_data = array();
//        $post_data = ( isset ( $_POST['data'] ) && ! empty ( $_POST['data'] ) ) ? $_POST['data'] : false;
//		$gid = ( isset ( $_POST['group_id'] ) && ! empty ( $_POST['group_id'] ) ) ? $_POST['group_id'] : 0;
//        // $gid === ITEM ID
//
//		if ( $post_data && isset ( $post_data['images'] ) && ! empty ( $post_data['images'] ) ) {
//			$images = $this->move_images($_POST['data']['images']);
//			if ( $images ) $bpa_data['images'] = $images;
//		}
//
//        $content = apply_filters( 'bp_activity_post_update_content', $content );
//        $aid = $gid ?
//            groups_post_update(array('content' => $content, 'group_id' => $gid))
//            :
//            bp_activity_post_update(array('content' => $content))
//        ;
//
//        bp_activity_update_meta($aid, 'bpa_blog_id', $GLOBALS['blog_id']);
//        if ( ! empty ( $bpa_data ) )
//            bp_activity_update_meta($aid, 'bpa_data', $bpa_data);
//
//		if ($aid) {
//			ob_start();
//			if ( bp_has_activities ( 'include=' . $aid ) ) {
//				while ( bp_activities() ) {
//					bp_the_activity();
//					locate_template( array( 'activity/entry.php' ), true );
//				}
//			}
//			$activity = ob_get_clean();
//		}
//		header('Content-type: application/json');
//		echo json_encode(array(
//			//'code' => $bpfb_code,
//			'id' => $aid,
//			'activity' => $activity,
//		));
//		exit();
	}

	/**
	 * Image moving and resizing routine.
	 *
	 * Relies on WP built-in image resizing.
	 *
	 * @param array Image paths to move from temp directory
	 * @return mixed Array of new image paths, or (bool)false on failure.
	 * @access private
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
			if (preg_match('!^https?:\/\/!i', $img)) { // Just add remote images
				$ret[] = $img;
				continue;
			}

			$pfx = $bp->loggedin_user->id . '_' . preg_replace('/ /', '', microtime());
			$tmp_img = realpath(BP_ACTIVE_TEMP_IMAGE_DIR . $img);
			$new_img = BP_ACTIVE_BASE_IMAGE_DIR . "{$pfx}_{$img}";
			if (@rename($tmp_img, $new_img)) {
				image_resize($new_img, $thumb_w, $thumb_h, false, 'bpat');
				$ret[] = pathinfo($new_img, PATHINFO_BASENAME);
			}
			else return false;
		}

		return $ret;
	}

}
new BP_Active_Ajax;
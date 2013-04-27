<?php
// Exit if accessed directly
if (!defined('ABSPATH'))
    exit;

class WP_Image_Editor_Imagick_Orientation extends WP_Image_Editor_Imagick
{

    public function rotate( $angle ) {
        $r = parent::rotate( $angle );
        $this->image->setImageOrientation( imagick::ORIENTATION_TOPLEFT );
        return $r;
    }
}
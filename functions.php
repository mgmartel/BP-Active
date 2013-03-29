<?php
/**
 * Helper functions for going around the fact that
 * BuddyPress is NOT multisite compatible.
 */
function bpa_get_image_url ($blog_id) {
	if (!defined('BP_ENABLE_MULTIBLOG') || !BP_ENABLE_MULTIBLOG) return str_replace('http://', BPA_PROTOCOL, BP_ACTIVE_BASE_IMAGE_URL);
	if (!$blog_id) return str_replace('http://', BPA_PROTOCOL, BP_ACTIVE_BASE_IMAGE_URL);
	switch_to_blog($blog_id);
	$wp_upload_dir = wp_upload_dir();
	restore_current_blog();
	return str_replace('http://', BPA_PROTOCOL, $wp_upload_dir['baseurl']) . '/activity/';
}
function bpa_get_image_dir ($blog_id) {
	if (!defined('BP_ENABLE_MULTIBLOG') || !BP_ENABLE_MULTIBLOG) return BP_ACTIVE_BASE_IMAGE_DIR;
	if (!$blog_id) return BP_ACTIVE_BASE_IMAGE_DIR;
	switch_to_blog($blog_id);
	$wp_upload_dir = wp_upload_dir();
	restore_current_blog();
	return $wp_upload_dir['basedir'] . '/activity/';
}
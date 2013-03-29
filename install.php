<?php

/**
 * Handles plugin installation.
 *
 * @since bpfb
 */
class BP_Active_Installer {

	/**
	 * Entry method.
	 *
	 * Handles Plugin installation.
	 *
	 * @access public
	 * @static
	 */
	static function install () {
		$self = new BP_Active_Installer;
		if ($self->prepare_paths()) {
			$self->set_default_options();
		} else $self->kill_default_options();
	}

	/**
	 * Checks to see if the plugin is installed.
	 *
	 * If not, installs it.
	 *
	 * @access public
	 * @static
	 */
	static function check () {
		$is_installed = get_option('bp_active', false);
		if ( ! $is_installed || ! self::check_paths() ) return self::install();
		return true;
	}

	/**
	 * Checks to see if we have the proper paths and if they're writable.
	 *
	 * @access private
	 */
	function check_paths () {
		if (!file_exists(BP_ACTIVE_TEMP_IMAGE_DIR)) return false;
		if (!file_exists(BP_ACTIVE_BASE_IMAGE_DIR)) return false;
		if (!is_writable(BP_ACTIVE_TEMP_IMAGE_DIR)) return false;
		if (!is_writable(BP_ACTIVE_BASE_IMAGE_DIR)) return false;
		return true;
	}

	/**
	 * Prepares paths that will be used.
	 *
	 * @access private
	 */
	function prepare_paths () {
		$ret = true;

		if (!file_exists(BP_ACTIVE_TEMP_IMAGE_DIR)) $ret = wp_mkdir_p(BP_ACTIVE_TEMP_IMAGE_DIR);
		if (!$ret) return false;

		if (!file_exists(BP_ACTIVE_BASE_IMAGE_DIR)) $ret = wp_mkdir_p(BP_ACTIVE_BASE_IMAGE_DIR);
		if (!$ret) return false;

		return true;
	}

	/**
	 * (Re)sets Plugin options to defaults.
	 *
	 * @access private
	 */
	function set_default_options () {
		$options = array (
			'installed' => 1,
		);
		update_option('bp_active', $options);
	}

	/**
	 * Removes plugin default options.
	 */
	function kill_default_options () {
		delete_option('bp_active');
	}
}
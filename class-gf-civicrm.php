<?php
/**
 * Plugin Name: Gravity Forms CiviCRM Add-On
 * Plugin URI: http://www.graydigitalgroup.com
 * Description: Creates feeds for CiviCRM integration with Gravity Forms
 * Version: 1.0
 * Author: GrayDigitalGroup
 * Text Domain: gf_civicrm_addon
 * License: GPL3
 *
 * @package gf_civicrm_addon
 *
 * ------------------------------------------------------------------------
 * Copyright 2020 Gray Digital Group, LLC.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA 02111-1307 USA
 */

namespace GF_CiviCRM;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'GF_CIVICRM_ADDON_VERSION', '1.0' );
define( 'GF_CIVICRM_ADDON_DIR', trailingslashit( realpath( trailingslashit( __DIR__ ) ) ) );
define( 'GF_CIVICRM_ADDON_URI', trailingslashit( plugin_dir_url( __FILE__ ) ) );

add_action( 'civicrm_instance_loaded', array( __NAMESPACE__ . '\\Bootstrap', 'civicrm_loaded' ) );
add_action( 'gform_loaded', array( __NAMESPACE__ . '\\Bootstrap', 'gravityforms_loaded' ) );

/**
 * Bootstraps the Gravity Forms CiviCRM Add-On
 *
 * @since 1.0
 */
class Bootstrap {

	/**
	 * Loads the AddOn
	 *
	 * @since 1.0
	 * @return void
	 */
	public static function load() {

		if ( ! defined( 'GF_CIVICRM_ADDON_CIVICRM_LOADED' ) && ! defined( 'GF_CIVICRM_ADDON_GRAVITYFORMS_LOADED' ) ) {
			return;
		}

		require_once GF_CIVICRM_ADDON_DIR . 'src/class-gf-civicrm-feed.php';

		\GFAddOn::register( __NAMESPACE__ . '\\GF_CiviCRM_Feed' );

		// require_once GF_CIVICRM_ADDON_DIR . 'src/class-gf-civicrm-fields-addon.php';

		// \GFAddOn::register( __NAMESPACE__ . '\\GF_CiviCRM_Fields_Addon' );
	}

	public static function civicrm_loaded() {
		if ( ! defined( 'GF_CIVICRM_ADDON_CIVICRM_LOADED' ) ) {
			define( 'GF_CIVICRM_ADDON_CIVICRM_LOADED', true );
		}
		self::load();
	}

	public static function gravityforms_loaded() {
		if ( ! defined( 'GF_CIVICRM_ADDON_GRAVITYFORMS_LOADED' ) ) {
			define( 'GF_CIVICRM_ADDON_GRAVITYFORMS_LOADED', true );
		}
		self::load();
	}

}

/**
 * Simple function to gain access to the AddOn class
 *
 * @since 1.0
 */
function gf_simple_feed_addon() {
	return GF_CiviCRM_Feed::get_instance();
}

<?php
/**
 * Gravity Forms CiviCRM Form Fields AddOn
 *
 * The main class for the GF CiviCRM Form Fields AddOn
 *
 * @package gf_civicrm_addon
 * @author GrayDigitalGroup
 * @license https://www.gnu.org/licenses/old-licenses/gpl-3.0.html
 */

namespace GF_CiviCRM;

\GFForms::include_addon_framework();

/**
 * This is a GF AddOn that adds CiviCRM Form fields.
 *
 * @since 1.0
 *
 * Class GF_CiviCRM_Fields_Addon
 */
class GF_CiviCRM_Fields_Addon extends \GFAddOn {

	protected $_version                  = GF_CIVICRM_ADDON_VERSION;
	protected $_min_gravityforms_version = '1.9';
	protected $_slug                     = 'gf_civicrm_fields';
	protected $_path                     = 'gf-civicrm/class-gf-civicrm.php';
	protected $_full_path                = __FILE__;
	protected $_title                    = 'Gravity Forms CiviCRM Fields Add-On';
	protected $_short_title              = 'CiviCRM Fields Add-On';

	/**
	 * @var object $_instance If available, contains an instance of this class.
	 */
	private static $_instance = null;

	/**
	 * Returns an instance of this class, and stores it in the $_instance property.
	 *
	 * @return object $_instance An instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$_instance ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * Include the field early so it is available when entry exports are being performed.
	 */
	public function pre_init() {
		parent::pre_init();

		if ( $this->is_gravityforms_supported() && class_exists( 'GF_Field' ) ) {
			require_once GF_CIVICRM_ADDON_DIR . 'src/Fields/class-gf-field-civicrm-repeater-container.php';
		}
	}
}

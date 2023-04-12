<?php
/**
 * Gravity Forms CiviCRM Repeater Container Field
 *
 * The main class for the GF CiviCRM Repeater Container Field
 *
 * @package gf_civicrm_addon
 * @author GrayDigitalGroup
 * @license https://www.gnu.org/licenses/old-licenses/gpl-3.0.html
 */

namespace GF_CiviCRM;

/**
 * This is an extention of the GF Hidden field
 * to create a container for repeater fields.
 *
 * @since 1.0
 *
 * Class GF_Field_CiviCRM_Repeater_Container
 */
class GF_Field_CiviCRM_Repeater_Container extends \GF_Field_Hidden {

	public $type  = 'civicrm_repeater_container';

	/**
	 * This class is used for CiviCRM API calls
	 *
	 * @var CiviCRM_API The instance of the API class.
	 * @since 1.0
	 */
	protected $_civicrm_api = null;

	/**
	 * This is used to hold all child fields for the repeater.
	 *
	 * @var GF_Field[] Array of fields.
	 */
	public $fields = array();

	/**
	 * Returns the field title for the form editor.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'CiviCRM Repeater Container', 'gf_civicrm_addon' );
	}

	/**
	 * Returns the field button properties for the form editor.
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'advanced_fields',
			'text'  => esc_attr__( 'CiviCRM Repeater Container', 'gf_civicrm_addon' ),
		);
	}

	/**
	 * The class names of the settings you want to appear on your field in the form editor.
	 *
	 * @return array
	 */
	public function get_form_editor_field_settings() {
		return array(
			'conditional_logic_field_setting',
			'prepopulate_field_setting',
			'label_setting',
			'default_value_setting',
		);
	}
}

\GF_Fields::register( new GF_Field_CiviCRM_Repeater_Container() );

<?php
/**
 * Gravity Forms CiviCRM Address Field
 *
 * The main class for the GF CiviCRM Address Field
 *
 * @package gf_civicrm_addon
 * @author GrayDigitalGroup
 * @license https://www.gnu.org/licenses/old-licenses/gpl-3.0.html
 */

namespace GF_CiviCRM;

/**
 * This is an extention of the GF Repeater field
 * to allow for adding multiple Addresses.
 *
 * @since 1.0
 *
 * Class GF_Field_CiviCRM_Address
 */
class GF_Field_CiviCRM_Address extends \GF_Field_Repeater {

	public $type  = 'civicrm_address';

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
	 * Main class constructor
	 *
	 * @param array $data The paramters for the field.
	 */
	public function __construct( $data = array() ) {
		$this->label = 'CiviCRM Address Field';

		parent::__construct();
		if ( empty( $data ) ) {
			return;
		}
		foreach ( $data as $key => $value ) {
			$this->{$key} = $value;
		}


		require_once GF_CIVICRM_ADDON_DIR . 'src/class-gf-civicrm-utilities.php';
		require_once GF_CIVICRM_ADDON_DIR . 'src/class-civicrm-api.php';

		$this->_civicrm_api = new CiviCRM_API();
	}

	/**
	 * Returns the field title for the form editor.
	 *
	 * @since 1.0
	 *
	 * @return string
	 */
	public function get_form_editor_field_title() {
		return esc_attr__( 'CiviCRM Address', 'gf_civicrm_addon' );
	}

	/**
	 * Returns the field button properties for the form editor.
	 *
	 * @return array
	 */
	public function get_form_editor_button() {
		return array(
			'group' => 'advanced_fields',
			'text'  => esc_attr__( 'CiviCRM Address', 'gf_civicrm_addon' ),
		);
	}

	/**
	 * Returns the field inner markup.
	 *
	 * @since 2.4
	 *
	 * @param array        $form  The Form Object currently being processed.
	 * @param string|array $values The field values. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param null|array   $entry Null or the Entry Object currently being edited.
	 *
	 * @return string
	 */
	public function get_field_input( $form, $values = '', $entry = null ) {
		$is_entry_detail = $this->is_entry_detail();
		$is_form_editor  = $this->is_form_editor();

		$address_fields = array(
			array(
				'type'       => 'select',
				'id'         => 1,
				'label'      => 'Address Type',
				'pageNumber' => 1,
				'choices'    => $this->get_address_types(),
			),
			array(
				'type'       => 'address',
				'id'         => 2,
				'label'      => 'Address',
				'pageNumber' => 1,
			),
		);
		$this->fields   = array();
		foreach ( $address_fields as $field ) {
			$this->fields[] = \GF_Fields::create( $field );
		}
		// error_log( print_r( $values, true ) );

		$content = $is_entry_detail || $is_form_editor ? "<div class='gfield_repeater gfield_repeater_container'><div class='gf-html-container'><span class='gf_blockheader'>
				<i class='fa fa-code fa-lg'></i> " . esc_html__( 'CiviCRM Address Field', 'gf_civicrm_addon' ) .
				'</span><span>' . esc_html__( 'This is a placeholder for the CiviCRM Address Field. This CiviCRM Address repeater field is not displayed in the form admin. Preview this form to view the field.', 'gf_civicrm_addon' ) . '</span></div></div>'
		: parent::get_field_input( $form, $values, $entry );

		return $content;
	}

	/**
	 * Returns the field markup; including field label, description, validation, and the form editor admin buttons.
	 *
	 * The {FIELD} placeholder will be replaced in GFFormDisplay::get_field_content with the markup returned by GF_Field::get_field_input().
	 *
	 * @param string|array $value                The field value. From default/dynamic population, $_POST, or a resumed incomplete submission.
	 * @param bool         $force_frontend_label Should the frontend label be displayed in the admin even if an admin label is configured.
	 * @param array        $form                 The Form Object currently being processed.
	 *
	 * @return string
	 */
	// public function get_field_content( $value, $force_frontend_label, $form ) {

	// 	$is_form_editor  = $this->is_form_editor();
	// 	$is_entry_detail = $this->is_entry_detail();
	// 	$is_admin        = $is_form_editor || $is_entry_detail;
	// 	$form_id         = $form['id'];
	// 	$field_label     = $this->get_field_label( $force_frontend_label, $value );
	// 	$field_id        = $is_admin || 0 === $form_id ? "input_{$this->id}" : 'input_' . $form_id . "_{$this->id}";

	// 	$admin_buttons = $this->get_admin_buttons();
	// 	if ( $is_admin ) {
	// 		$admin_buttons = sprintf( "%s<label class='gfield_label' for='%s'>%s</label>{FIELD}", $admin_buttons, $field_id, esc_html( $field_label ) );
	// 	}

	// 	$description = $this->get_description( $this->description, 'gfield_description' );
	// 	if ( $this->is_description_above( $form ) ) {
	// 		$clear         = $is_admin ? "<div class='gf_clear'></div>" : '';
	// 		$field_content = sprintf( "%s%s{FIELD}$clear", $admin_buttons, $description );
	// 	} else {
	// 		$field_content = sprintf( '%s{FIELD}%s', $admin_buttons, $description );
	// 	}

	// 	return $field_content;
	// }

	/**
	 * Gets the Address Types from the API
	 *
	 * @return array
	 */
	public function get_address_types() {
		$choices       = array();
		$address_types = $this->_civicrm_api->get_address_field_options( 'location_type_id' );
		if ( false !== $address_types ) {
			foreach ( $address_types as $type ) {
				$choices[] = array(
					'value' => $type['id'],
					'text'  => $type['label'],
				);
			}
		}
		return $choices;
	}
}

\GF_Fields::register( new GF_Field_CiviCRM_Address() );

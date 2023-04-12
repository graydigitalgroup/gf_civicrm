<?php
/**
 * Gravity Forms CiviCRM Feed Add-On
 *
 * The main class for the GF CiviCRM Feed Add-On
 *
 * @package gf_civicrm_addon
 * @author GrayDigitalGroup
 * @license https://www.gnu.org/licenses/old-licenses/gpl-3.0.html
 */

namespace GF_CiviCRM;

\GFForms::include_feed_addon_framework();

/**
 * Main Feed Add-On class.
 *
 * @since 1.0
 */
class GF_CiviCRM_Feed extends \GFFeedAddOn {

	/**
	 * Version number of the Add-On
	 *
	 * @var string
	 */
	protected $_version = GF_CIVICRM_ADDON_VERSION;

	/**
	 * Gravity Forms minimum version requirement
	 *
	 * @since 1.0
	 * @var string
	 */
	protected $_min_gravityforms_version = '1.9.16';

	/**
	 * URL-friendly identifier used for form settings, add-on settings, text domain localization...
	 *
	 * @var string
	 */
	protected $_slug = 'gf-civicrm';

	/**
	 * Relative path to the plugin from the plugins folder.
	 *
	 * @var string
	 */
	protected $_path = 'gf-civicrm/src/class-gf-civicrm-feed.php';

	/**
	 * Full path the the plugin.
	 *
	 * @var string
	 */
	protected $_full_path = __FILE__;

	/**
	 * Title of the plugin to be used on the settings page, form settings and plugins page.
	 *
	 * @var string
	 */
	protected $_title = 'Gravity Forms CiviCRM Feed Add-On';

	/**
	 * Short version of the plugin title to be used on menus and other places where a less verbose string is useful.
	 *
	 * @var string
	 */
	protected $_short_title = 'CiviCRM';

	/* Permissions */
	/**
	 * A string or an array of capabilities or roles that have access to the settings page
	 *
	 * @var string|array
	 */
	protected $_capabilities_settings_page = 'gravityforms_civicrm';

	/**
	 * A string or an array of capabilities or roles that have access to the form settings
	 *
	 * @var string|array
	 */
	protected $_capabilities_form_settings = 'gravityforms_civicrm';

	/**
	 * A string or an array of capabilities or roles that can uninstall the plugin
	 *
	 * @var string|array
	 */
	protected $_capabilities_uninstall = 'gravityforms_civicrm_uninstall';

	/* Members plugin integration */

	/**
	 * Members plugin integration. List of capabilities to add to roles.
	 *
	 * @var array
	 */
	protected $_capabilities = array( 'gravityforms_civicrm', 'gravityforms_civicrm_uninstall' );

	/**
	 * This class is used for CiviCRM API calls
	 *
	 * @var CiviCRM_API The instance of the API class.
	 * @since 1.0
	 */
	protected $_civicrm_api = null;

	/**
	 * This class will load in the Add-On.
	 *
	 * @var GF_CiviCRM_Feed The single instance of the class
	 * @since 1.0
	 */
	protected static $instance = null;

	/**
	* Whether the scripts for this feed have already been added or not.
	*
	* @var bool
	* @since 1.0
	*/
	protected $_scripts_added = false;

	/**
	 * Main Gravity Forms CiviCRM Feed Add-On Instance
	 *
	 * Ensures only one instance of Gravity Forms CiviCRM Feed Add-On is loaded or can be loaded.
	 *
	 * @since 1.0
	 * @static
	 * @see gf_simple_feed_addon()
	 * @return GF_CiviCRM_Feed - Main instance
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
			//self::$instance->boot();
		}
		return self::$instance;
	}

	/**
	 * Plugin starting point. Handles hooks, loading of language files and PayPal delayed payment support.
	 */
	public function init() {
		require_once GF_CIVICRM_ADDON_DIR . 'src/class-gf-civicrm-utilities.php';
		require_once GF_CIVICRM_ADDON_DIR . 'src/class-civicrm-api.php';

		$this->_civicrm_api = new CiviCRM_API();

		parent::init();

		add_filter( 'gform_pre_render', array( $this, 'form_pre_render' ), 20, 3 );
		// add_filter( 'gform_form_post_get_meta', array( $this, 'add_repeater_fields' ) );
		// add_filter( 'gform_form_update_meta', array( $this, 'remove_repeater_fields' ), 10, 3 );
		add_filter( 'gform_form_args', array( $this, 'init_form_args' ), 10 );
		add_filter( 'gform_field_content', array( $this, 'render_content_html' ), 10, 5 );
		add_filter( 'gform_validation', array( $this, 'validate_form' ), 20 );
		add_filter( 'gform_field_validation', array( $this, 'field_validation' ), 10, 4 );
		add_filter( 'gform_include_thousands_sep_pre_format_number', array( $this, 'include_thousands_separator' ), 10, 2 );

		$this->add_delayed_payment_support(
			array(
				'option_label' => esc_html__( 'Subscribe contact to service x only when payment is received.', 'gf_civicrm_addon' ),
			)
		);

	}

	/**
	 * Initializes form settings page
	 * Hooks up the required scripts and actions for the Form Settings page
	 */
	public function form_settings_init() {
		parent::form_settings_init();

		$form = $this->get_current_form();
	}

	/**
	 * Setup plugin settings fields.
	 *
	 * @access public
	 * @return array
	 */
	public function plugin_settings_fields() {

		return array(
			array(
				'title' => 'Configure CiviCRM',
			),
		);
	}

	/**
	 * Setup fields for feed settings.
	 *
	 * @access public
	 * @return array
	 */
	public function feed_settings_fields() {

		/* Build base fields array. */
		$base_fields = array(
			'title'  => '',
			'fields' => array(
				array(
					'name'          => 'feedName',
					'label'         => __( 'Feed Name', 'gf_civicrm_addon' ),
					'type'          => 'text',
					'required'      => true,
					'default_value' => $this->get_default_feed_name(),
					'class'         => 'medium',
					'tooltip'       => '<h6>' . __( 'Name', 'gf_civicrm_addon' ) . '</h6>' . __( 'Enter a feed name to uniquely identify this setup.', 'gf_civicrm_addon' ),
				),
				array(
					'name'     => 'action',
					'label'    => __( 'Action', 'gf_civicrm_addon' ),
					'type'     => 'radio',
					'required' => true,
					'onclick'  => "jQuery(this).parents('form').submit();",
					'choices'  => array(
						array(
							'name'  => 'createContact',
							'value' => 'createContact',
							'label' => __( 'Create Contact', 'gf_civicrm_addon' ),
							'icon'  => 'fa-address-card-o',
						),
					),
				),
				array(
					'name'       => 'contact_type',
					'type'       => 'select',
					'label'      => __( 'Sub Type', 'gf_civicrm_addon' ),
					'required'   => true,
					'onchange'   => "jQuery(this).parents('form').submit();",
					'choices'    => $this->get_contact_types(),
					'dependency' => array(
						'field'  => 'action',
						'values' => ( 'createContact' ),
					),
				),
			),
		);

		$organizational_fields = array(
			'title'      => __( 'Organization Details', 'gf_civicrm_addon' ),
			'dependency' => array(
				'field'  => 'contact_type',
				'values' => ( 'Organization' ),
			),
			'fields'     => array(
				array(
					'name'     => 'organization_allow_selecting_organization',
					'label'    => __( 'Allow Selecting An Organization', 'gf_civicrm_addon' ),
					'type'     => 'checkbox_and_select',
					'checkbox' => array(
						'name'          => 'organization_select_enabled',
						'label'         => __( 'Enable', 'gf_civicrm_addon' ),
						'default_value' => 0,
					),
					'select' => array(
						'name'    => 'organization_select',
						'label'   => __( 'Organization Field', 'gf_civicrm_addon' ),
						'choices' => $this->get_fields_by_type( array( 'select', 'multiselect' ), 'Select Field' ),
					),
				),
				array(
					'name'          => 'organization_option',
					'label'         => __( 'Organization Name', 'gf_civicrm_addon' ),
					'type'          => 'select',
					'required'      => true,
					'choices'       => $this->get_fields_by_type( array( 'text', 'hidden' ), 'Select Field' ),
					'default_value' => $this->get_first_field_by_type( 'text' ),
				),
				array(
					'name'     => 'dedupe_rule',
					'label'    => 'Contact Dedupe Rule',
					'type'     => 'select',
					'required' => false,
					'choices'  => $this->contact_dedupe_rules_for_feed_mappings(),
				),
				array(
					'name'      => 'organization_standard_fields',
					'label'     => '',
					'type'      => 'dynamic_field_map',
					'field_map' => $this->contact_standard_fields_for_feed_mapping( 'Organization' ),
				),
				// array(
				// 	'name'          => 'organization_relationship',
				// 	'label'         => __( 'Organization Relationship', 'gf_civicrm_addon' ),
				// 	'type'          => 'select',
				// 	'required'      => true,
				// 	'choices'       => $this->get_fields_by_type( array( 'select' ), 'Select Field' ),
				// 	'default_value' => $this->get_first_field_by_type( 'select' ),
				// ),
				// array(
				// 	'label'   => __( 'Enable Address Fields', 'gf_civicrm_addon' ),
				// 	'type'    => 'checkbox',
				// 	'name'    => 'organization_enable_address_fields',
				// 	'tooltip' => __( 'Add addres fields to an organization contact.', 'gf_civicrm_addon' ),
				// 	'onclick' => "jQuery(this).parents('form').submit();",
				// 	'choices' => array(
				// 		array(
				// 			'label' => __( 'Enabled', 'gf_civicrm_addon' ),
				// 			'name'  => 'organization_enable_address_fields_enabled',
				// 		),
				// 	),
				// ),
				// array(
				// 	'label'         => esc_html__( 'Address Field', 'gf_civicrm_addon' ),
				// 	'name'          => 'organization_address',
				// 	'type'          => 'select',
				// 	'required'      => true,
				// 	'choices'       => $this->get_fields_by_type( array( 'text', 'address' ), 'Select Field' ),
				// 	'default_value' => $this->get_first_field_by_type( 'text' ),
				// 	'dependency'    => array(
				// 		'field'  => 'organization_enable_address_fields_enabled',
				// 		'values' => ( 1 ),
				// 	),
				// ),
				// array(
				// 	'label'         => esc_html__( 'Address 2', 'gf_civicrm_addon' ),
				// 	'name'          => 'organization_address_2',
				// 	'type'          => 'select',
				// 	'required'      => false,
				// 	'choices'       => $this->get_fields_by_type( array( 'text', 'address' ), 'Select Field' ),
				// 	'default_value' => $this->get_first_field_by_type( 'text' ),
				// 	'dependency'    => array(
				// 		'field'  => 'organization_enable_address_fields_enabled',
				// 		'values' => ( 1 ),
				// 	),
				// ),
				// array(
				// 	'label'         => esc_html__( 'Address 3', 'gf_civicrm_addon' ),
				// 	'name'          => 'organization_address_3',
				// 	'type'          => 'select',
				// 	'required'      => false,
				// 	'choices'       => $this->get_fields_by_type( array( 'text', 'address' ), 'Select Field' ),
				// 	'default_value' => $this->get_first_field_by_type( 'text' ),
				// 	'dependency'    => array(
				// 		'field'  => 'organization_enable_address_fields_enabled',
				// 		'values' => ( 1 ),
				// 	),
				// ),
				// array(
				// 	'label'         => esc_html__( 'City', 'gf_civicrm_addon' ),
				// 	'name'          => 'organization_city',
				// 	'type'          => 'select',
				// 	'required'      => false,
				// 	'choices'       => $this->get_fields_by_type( array( 'text' ), 'Select Field' ),
				// 	'default_value' => $this->get_first_field_by_type( 'text' ),
				// 	'dependency'    => array(
				// 		'field'  => 'organization_enable_address_fields_enabled',
				// 		'values' => ( 1 ),
				// 	),
				// ),
				// array(
				// 	'label'         => esc_html__( 'State/Province', 'gf_civicrm_addon' ),
				// 	'name'          => 'organization_state_province',
				// 	'type'          => 'select',
				// 	'required'      => false,
				// 	'choices'       => $this->get_fields_by_type( array( 'select', 'multi-select' ), 'Select Field' ),
				// 	'default_value' => $this->get_first_field_by_type( 'select' ),
				// 	'dependency'    => array(
				// 		'field'  => 'organization_enable_address_fields_enabled',
				// 		'values' => ( 1 ),
				// 	),
				// ),
				// array(
				// 	'label'         => esc_html__( 'Country', 'gf_civicrm_addon' ),
				// 	'name'          => 'organization_country',
				// 	'type'          => 'select',
				// 	'required'      => false,
				// 	'choices'       => $this->get_fields_by_type( array( 'select', 'multi-select' ), 'Select Field' ),
				// 	'default_value' => $this->get_first_field_by_type( 'select' ),
				// 	'dependency'    => array(
				// 		'field'  => 'organization_enable_address_fields_enabled',
				// 		'values' => ( 1 ),
				// 	),
				// ),
				// array(
				// 	'label'         => esc_html__( 'Zip/Postal Code', 'gf_civicrm_addon' ),
				// 	'name'          => 'organization_postal_code',
				// 	'type'          => 'select',
				// 	'required'      => false,
				// 	'choices'       => $this->get_fields_by_type( array( 'text' ), 'Select Field' ),
				// 	'default_value' => $this->get_first_field_by_type( 'text' ),
				// 	'dependency'    => array(
				// 		'field'  => 'organization_enable_address_fields_enabled',
				// 		'values' => ( 1 ),
				// 	),
				// ),
				// array(
				// 	'label'         => esc_html__( 'Is Billing', 'gf_civicrm_addon' ),
				// 	'name'          => 'organization_billing',
				// 	'type'          => 'select',
				// 	'required'      => false,
				// 	'choices'       => $this->get_fields_by_type( array( 'radio' ), 'Select Field' ),
				// 	'default_value' => $this->get_first_field_by_type( 'radio' ),
				// 	'dependency'    => array(
				// 		'field'  => 'organization_enable_address_fields_enabled',
				// 		'values' => ( 1 ),
				// 	),
				// ),
			),
		);

		/* Build contact fields array. */
		$contact_fields = array(
			'title'      => __( 'Individual Details', 'gf_civicrm_addon' ),
			'dependency' => array(
				'field'  => 'contact_type',
				'values' => ( 'Individual' ),
			),
			'fields'     => array(
				array(
					'name'      => 'individual_required_fields',
					'label'     => __( 'Map Fields', 'gf_civicrm_addon' ),
					'type'      => 'field_map',
					'field_map' => $this->individual_main_fields_for_feed_mapping(),
					'tooltip'   => '<h6>' . __( 'Map Fields', 'gf_civicrm_addon' ) . '</h6>' . __( 'Select which Gravity Form fields pair with their respective CiviCRM fields.', 'gf_civicrm_addon' ),
				),
				array(
					'name'      => 'individual_standard_fields',
					'label'     => '',
					'type'      => 'dynamic_field_map',
					'field_map' => $this->contact_standard_fields_for_feed_mapping( 'Individual' ),
				),
				array(
					'name'     => 'individual_use_phone_numbers',
					'label'    => __( 'Add Phone Number Fields', 'gf_civicrm_addon' ),
					'type'     => 'checkbox_and_select',
					'checkbox' => array(
						'name'          => 'individual_use_phone_numbers_enabled',
						'label'         => __( 'Allow for managing phone numbners for a contact.', 'gf_civicrm_addon' ),
						'default_value' => 0,
					),
					'select' => array(
						'name'    => 'indvidial_phone_number_repeater',
						'label'   => __( 'Phone Number Repeater', 'gf_civicrm_addon' ),
						'choices' => $this->get_fields_by_type( array( 'repeater' ), 'Select a Repeater' ),
					),
				),
				array(
					'name'     => 'individual_use_address',
					'label'    => __( 'Add Address Fields', 'gf_civicrm_addon' ),
					'type'     => 'checkbox_and_select',
					'checkbox' => array(
						'name'          => 'individual_use_address_enabled',
						'label'         => __( 'Allow for managing address(es) for a contact.', 'gf_civicrm_addon' ),
						'default_value' => 0,
					),
					'select' => array(
						'name'    => 'individual_address_repeater',
						'label'   => __( 'Address Field Repeater', 'gf_civicrm_addon' ),
						'choices' => $this->get_fields_by_type( array( 'repeater' ), 'Select a Repeater' ),
					),
				),
				array(
					'name'     => 'individual_use_email',
					'label'    => __( 'Add Email Fields', 'gf_civicrm_addon' ),
					'type'     => 'checkbox_and_select',
					'checkbox' => array(
						'name'          => 'individual_use_email_enabled',
						'label'         => __( 'Allow for managing email(s) for an individual.', 'gf_civicrm_addon' ),
						'default_value' => 0,
					),
					'select' => array(
						'name'    => 'individual_email_repeater',
						'label'   => __( 'Email Field Repeater', 'gf_civicrm_addon' ),
						'choices' => $this->get_fields_by_type( array( 'repeater' ), 'Select a Repeater' ),
					),
				),
				array(
					'type'     => 'select',
					'label'    => __( 'Group(s)', 'gf_civicrm_addon' ),
					'name'     => 'individual_group[]',
					'class'    => 'group_val group_val_{i}',
					'multiple' => 'multiple',
					'choices'  => $this->contact_groups_for_feed_mapping(),
				),
				array(
					'name'     => 'dedupe_rule',
					'label'    => 'Contact Dedupe Rule',
					'type'     => 'select',
					'required' => false,
					'choices'  => $this->contact_dedupe_rules_for_feed_mappings(),
				),
				array(
					'name'     => 'update_individual_contact',
					'label'    => __( 'Update Contact', 'gf_civicrm_addon' ),
					'type'     => 'checkbox_and_select',
					'checkbox' => array(
						'name'    => 'update_individual_contact_enabled',
						'label'   => __( 'Update Individual if already exists', 'gf_civicrm_addon' ),
						'chocekd' => false,
					),
					'select' => array(
						'name'    => 'update_individual_contact_action',
						'choices' => array(
							array(
								'label' => __( 'and replace existing data', 'gf_civicrm_addon' ),
								'value' => 'replace',
							),
							array(
								'label' => __( 'and append new data', 'gf_civicrm_addon' ),
								'value' => 'append',
							),
						),
					),
				),
			),
		);

		$populate_from_civicrm = array(
			'title'      => __( 'Populate CiviCRM Data', 'gf_civicrm_addon' ),
			'dependency' => array(
				'field'  => 'contact_type',
				'values' => array( 'Individual', 'Organization' ),
			),
			'fields'     => array(
				array(
					'label'   => __( 'Populate WP User', 'gf_civicrm_addon' ),
					'type'    => 'checkbox',
					'name'    => 'populate_wp_user',
					'tooltip' => __( 'Attempt to populate fields from CiviCRM contact using current logged in user.', 'gf_civicrm_addon' ),
					'onclick' => "jQuery(this).parents('form').submit();",
					'choices' => array(
						array(
							'label' => __( 'Enabled', 'gf_civicrm_addon' ),
							'name'  => 'populate_wp_user_enabled',
						),
					),
				),
				array(
					'label'      => esc_html__( 'Populate WP User Conditional Logic', 'gf_civicrm_addon' ),
					'type'       => 'populate_civicrm_condition',
					'name'       => 'populate_wp_user_logic',
					'dependency' => array(
						'field'  => 'populate_wp_user_enabled',
						'values' => ( 1 ),
					),
				),
				array(
					'label'   => __( 'Populate CiviCRM CheckSum', 'gf_civicrm_addon' ),
					'type'    => 'checkbox',
					'name'    => 'populate_civicrm_checksum',
					'tooltip' => __( 'If a CiviCRM checksum is found in the querystring and valied, use it to locate the contact and populate the form fields.', 'gf_civicrm_addon' ),
					'onclick' => "jQuery(this).parents('form').submit();",
					'choices' => array(
						array(
							'label' => __( 'Enabled', 'gf_civicrm_addon' ),
							'name'  => 'populate_civicrm_checksum_enabled',
						),
					),
				),
				array(
					'label'      => esc_html__( 'Populate CheckSum Conditional Logic', 'gf_civicrm_addon' ),
					'type'       => 'populate_civicrm_condition',
					'name'       => 'populate_civicrm_checksum_logic',
					'dependency' => array(
						'field'  => 'populate_civicrm_checksum_enabled',
						'values' => ( 1 ),
					),
				),
			),
		);

		$relationship_field = array(
			'title'      => __( 'Relationship Details', 'gf_civicrm_addon' ),
			'dependency' => array( $this, 'contact_relationship_visibilty' ),
			'fields'     => array(
				array(
					'name'     => 'relation_to',
					'label'    => __( 'Relationship To Feed', 'gf_civicrm_addon' ),
					'type'     => 'select',
					'required' => false,
					'onchange' => "jQuery(this).parents('form').submit();",
					'choices'  => $this->get_feeds_as_options(),
				),
				array(
					'name'     => 'relation_type',
					'label'    => __( 'Relationship Type', 'gf_civicrm_addon' ),
					'type'     => 'select',
					'required' => false,
					'choices'  => $this->get_relationship_types(),
				),
			),
		);

		/* Build conditional logic fields array. */
		$conditional_fields = array(
			'title'      => __( 'Feed Conditional Logic', 'gf_civicrm_addon' ),
			'dependency' => array( $this, 'show_conditional_logic_field' ),
			'fields'     => array(
				array(
					'name'           => 'feedCondition',
					'type'           => 'feed_condition',
					'label'          => __( 'Conditional Logic', 'gf_civicrm_addon' ),
					'checkbox_label' => __( 'Enable', 'gf_civicrm_addon' ),
					'instructions'   => __( 'Process feed if', 'gf_civicrm_addon' ),
					'tooltip' => '<h6>' . __( 'Conditional Logic', 'gf_civicrm_addon' ) . '</h6>' . __( 'When conditional logic is enabled, form submissions will only be inserted/updated in CiviCRM when the condition is met. When disabled, all form submissions will be inserted/updated into CiviCRM.', 'gf_civicrm_addon' ),
				),
			),
		);

		return array( $base_fields, $contact_fields, $organizational_fields, $populate_from_civicrm, $relationship_field, $conditional_fields );
	}

	/**
	 * Set custom dependency for conditional logic.
	 *
	 * @access public
	 * @return bool
	 */
	public function show_conditional_logic_field() {
		/* Get current feed. */
		$feed = $this->get_current_feed();

		/* Get posted settings. */
		$posted_settings = $this->get_posted_settings();

		/* Show if an action is chosen */
		if ( in_array( rgar( $posted_settings, 'action' ), array( 'createContact', 'createPhone', 'createMembership', 'createOrder' ) ) || in_array( rgars( $feed, 'meta/action' ), array( 'createContact', 'createMembership', 'createOrder' ) ) ) {
			return true;
		}
		return false;
	}

	/**
	 * Set custom dependency relationship fields.
	 *
	 * @access public
	 * @return bool
	 */
	public function contact_relationship_visibilty() {
		/* Get current feed. */
		$feed = $this->get_current_feed();

		/* Get current form */
		$form = $this->get_current_form();

		/* Get all feeds for current form */
		$feeds = $this->get_feeds( $form['id'] );

		/* Get posted settings. */
		$posted_settings = $this->get_posted_settings();

		/* Show if an action is chosen */
		if ( in_array( rgar( $posted_settings, 'action' ), array( 'createContact' ) ) || in_array( rgars( $feed, 'meta/action' ), array( 'createContact' ) ) ) {
			if ( '' !== rgar( $posted_settings, 'contact_type', '' ) && '' === rgars( $feed, 'meta/contact_type', '' ) ) {
				return ( 0 < count( $feeds ) );
			}

			if ( '' !== rgars( $feed, 'meta/contact_type', '' ) ) {
				$feed_ids   = array_column( $feeds, 'id' );
				$feed_order = array_search( $feed['id'], $feed_ids, true );
				return ( false !== $feed_order && 0 < $feed_order );
			}
		}
		return false;
	}

	/**
	 * Register needed styles.
	 *
	 * @access public
	 * @return array $styles
	 */
	public function styles() {

		$styles = array(
			array(
				'handle'  => 'gform_agilecrm_form_settings_css',
				'src'     => GF_CIVICRM_ADDON_URI . 'assets/css/admin-styles.min.css',
				'version' => Utilities::file_cache_bust( GF_CIVICRM_ADDON_URI . 'assets/css/admin-styles.min.css' ),
				'enqueue' => array(
					array( 'admin_page' => array( 'form_settings' ) ),
				),
			),
			array(
				'handle'  => 'gf_civicrm_frontend_styles',
				'src' => GF_CIVICRM_ADDON_URI . 'assets/css/frontend-styles.min.css',
				'version' => Utilities::file_cache_bust( GF_CIVICRM_ADDON_URI . 'assets/css/frontend-styles.min.css' ),
				'enqueue' => array(
					array( $this, 'should_enqueue_frontend_script' ),
				),
			),
		);

		return array_merge( parent::styles(), $styles );
	}

	/**
	 * Register needed scripts.
	 *
	 * @access public
	 * @return array $scripts
	 */
	public function scripts() {
		$scripts = array(
			array(
				'handle'  => 'gf_civicrm_order_js',
				'src'     => GF_CIVICRM_ADDON_URI . 'assets/js/bundle.order.js',
				'version' => Utilities::file_cache_bust( GF_CIVICRM_ADDON_URI . 'assets/js/bundle.order.js' ),
				'deps'    => array( 'jquery', 'gaddon_repeater' ),
				'enqueue' => array(
					array( 'admin_page' => array( 'form_settings' ) ),
				),
			),
			array(
				'handle'  => 'gf_civicrm_admin_js',
				'src'     => GF_CIVICRM_ADDON_URI . 'assets/js/bundle.admin.js',
				'version' => Utilities::file_cache_bust( GF_CIVICRM_ADDON_URI . 'assets/js/bundle.admin.js' ),
				'deps'    => array( 'jquery', 'gform_form_admin' ),
				'enqueue' => array(
					array( 'admin_page' => array( 'form_settings' ) ),
				),
			),
			array(
				'handle'    => 'gf_civicrm_civicrm_js',
				'deps'      => array( 'jquery', 'gform_gravityforms' ),
				'src'       => GF_CIVICRM_ADDON_URI . 'assets/js/bundle.civicrm.js',
				'version'   => Utilities::file_cache_bust( GF_CIVICRM_ADDON_URI . 'assets/js/bundle.civicrm.js' ),
				'enqueue'   => array(
					array( $this, 'should_enqueue_frontend_script' )
				),
				'callback'  => array( $this, 'localize_scripts' ),
				'in_footer' => true,
			),
		);

		return array_merge( parent::scripts(), $scripts );
	}

	/**
	 * Frontend scripts should only be enqueued if we're not on a GF admin page and the form contains our feed.
	 *
	 * @param object $form The form to check if a feed for this addon exists.
	 *
	 * @return bool
	 */
	public function should_enqueue_frontend_script( $form ) {
		$active_feeds = array();
		if ( is_array( $form ) && array_key_exists( 'id', $form ) ) {
			$feeds = $this->get_active_feeds( $form['id'] );
			foreach ( $feeds as $feed ) {
				if ( in_array( rgars( $feed, 'meta/action' ), array( 'createContact' ) ) ) {
					array_push( $active_feeds, $feed );
				}
			}
		}
		return ! \GFForms::get_page() && 0 < count( $active_feeds );
	}

	/**
	 * Initialize CiviCRM scripts for the form.
	 *
	 * @param array $args The form and whether it is using ajax. ($form, $is_ajax).
	 */
	public function localize_scripts( $args ) {
		if (  ! $this->_scripts_added && is_array( $args ) && array_key_exists( 'id', $args ) ) {
			$active_feeds  = $this->get_active_feeds( $args['id'] );
			$default_state = null;
			if ( class_exists('CRM_Core_Config') ) {
				$config        = \CRM_Core_Config::singleton();
				$default_state = $config->defaultContactCountry . '_' . $config->defaultContactStateProvince;
			}
			foreach ( $active_feeds as $feed ) {
				if ( in_array( rgars( $feed, 'meta/action' ), array( 'createContact' ) ) && is_array( $args ) ) {
					$this->_scripts_added = true;
					$script  = '(function($){';
					$script .= '  $(document).ready(function() {';
					$script .= '  if ( typeof CountryStateSelect === "function" ) {';
					$script .= sprintf( '    var selector = new CountryStateSelect( %s, %s, %s, \'%s\' );', $args['id'], 2008, 2009, $default_state );
					$script .= '  }';
					$script .= '  });';
					$script .= '}(jQuery));';
					wp_add_inline_script( 'gf_civicrm_civicrm_js', $script );
					break;
				}
			}
		}
	}

	/**
	 * Add the logo for the plugin settings page
	 *
	 * @return string IMG HTML tag
	 */
	public function plugin_settings_icon() {
		global $wp_filesystem;
		\WP_Filesystem();
		return '<i class="icon">' . $wp_filesystem->get_contents( GF_CIVICRM_ADDON_URI . 'assets/images/civi-logo-icon.svg' ) . '</i>';
	}

	/**
	 * Process the feed e.g. subscribe the user to a list.
	 *
	 * @param array $feed The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form The form object currently being processed.
	 *
	 * @return array|null
	 */
	public function process_feed( $feed, $entry, $form ) {
		switch ( rgars( $feed, 'meta/action' ) ) {
			case 'createContact':
				$contact_type = rgars( $feed, 'meta/contact_type' );
				if ( 'Individual' === $contact_type ) {
					return $this->process_contact( $feed, $entry, $form );
				} elseif ( 'Organization' === $contact_type ) {
					return $this->process_organization( $feed, $entry, $form );
				}
				break;
			// case 'createOrganization':
			// 	break;
			// case 'createMembership':
			// 	$this->processMembership( $feed, $entry, $form );
			// 	break;
			// case 'createOrder':
			// 	$this->processOrder( $feed, $entry, $form );
			// 	break;
		}
	}

	/**
	 * Validates the form based on the feeds and any Dedupe rules.
	 *
	 * @since 1.0
	 * @param array $validation_result The validation result already processed.
	 *
	 * @return array
	 */
	public function validate_form( $validation_result ) {

		$form         = $validation_result['form'];
		$current_page = rgpost( 'gform_source_page_number_' . $form['id'] ) ? (int) rgpost( 'gform_source_page_number_' . $form['id'] ) : 1;
		$entry        = \GFFormsModel::get_current_lead();
		$feeds        = $this->get_feeds( $form['id'] );

		if ( ! empty( $feeds ) ) {
			foreach ( $feeds as $feed ) {
				if ( 1 !== (int) $feed['is_active'] || false === array_key_exists( 'action', $feed['meta'] ) ) {
					continue;
				}

				if ( ! $this->is_feed_condition_met( $feed, $form, $entry ) ) {
					continue;
				}

				if ( null !== rgars( $feed, 'meta/dedupe_rule' ) ) {
					$rule         = rgars( $feed, 'meta/dedupe_rule' );
					$contact_type = rgars( $feed, 'meta/contact_type', '' );
					try {
						$rule_group = \civicrm_api3(
							'Rule',
							'get',
							array(
								'dedupe_rule_group_id' => $rule,
							)
						);

						if ( false === (bool) $rule_group['is_error'] && 0 < $rule_group['count'] ) {
							$dedupe_fields = array();
							$field_names   = array();
							$mappings      = rgars( $feed, 'meta/' . strtolower( $contact_type ) . '_standard_fields' );

							foreach ( $rule_group['values'] as $rule_entry ) {
								$field_names[ $rule_entry['rule_field'] ] = '';
								switch ( $rule_entry['rule_field'] ) {
									case 'first_name':
										$field_mapping                            = rgars( $feed, 'meta/individual_required_fields_first_name' );
										$field_names[ $rule_entry['rule_field'] ] = $field_mapping;
										if ( null !== $field_mapping && '' !== $field_mapping ) {
											$field_value = rgar( $entry, $field_mapping, '' );
											if ( '' !== $field_value ) {
												$dedupe_fields['first_name'] = $field_value;
											}
										}
										break;
									case 'last_name':
										$field_mapping                            = rgars( $feed, 'meta/individual_required_fields_last_name' );
										$field_names[ $rule_entry['rule_field'] ] = $field_mapping;
										if ( null !== $field_mapping && '' !== $field_mapping ) {
											$field_value = rgar( $entry, $field_mapping, '' );
											if ( '' !== $field_value ) {
												$dedupe_fields['last_name'] = $field_value;
											}
										}
										break;
									case 'middle_name':
										$field_mapping                            = rgars( $feed, 'meta/individual_required_fields_middle_name' );
										$field_names[ $rule_entry['rule_field'] ] = $field_mapping;
										if ( null !== $field_mapping && '' !== $field_mapping ) {
											$field_value = rgar( $entry, $field_mapping, '' );
											if ( '' !== $field_value ) {
												$dedupe_fields['middle_name'] = $field_value;
											}
										}
										break;
									case 'email':
										if ( 'Individual' === $contact_type ) {
											$field_value                              = rgpost( 'input_3001' );
											$field_names[ $rule_entry['rule_field'] ] = 3000;
											if ( null !== $field_value && is_array( $field_value ) ) {
												foreach ( $field_value as $email_record ) {
													$dedupe_fields['email'] = $email_record;
													break;
												}
											}
										}
										break;
									case 'organization_name':
										break;
									default:
										foreach ( $mappings as $mapping ) {
											if ( 0 === strpos( $mapping['key'], 'custom_' ) ) {
												$custom_key = explode( '_', $mapping['key'] )[1];
												if ( str_ends_with( $rule_entry['rule_field'], '_' . $custom_key ) ) {
													$field_value = rgar( $entry, $mapping['value'] );
													if ( null !== $field_value ) {
														$dedupe_fields[ $rule_entry['rule_field'] ] = $field_value;
													}
												}
											} else if ( $mapping['key'] === $rule_entry['rule_field'] ) {
												$field_value = rgar( $entry, $mapping['value'] );
												if ( null !== $field_value ) {
													$dedupe_fields[ $rule_entry['rule_field'] ] = $field_value;
												}
											}
										}
										break;
								}
							}
							if ( 0 < count( $dedupe_fields ) && 0 < count( $field_names ) ) {
								if ( Utilities::array_keys_exists( array_keys( $field_names ), $dedupe_fields ) ) {
									$contact = Utilities::civi_contact_dedupe( $dedupe_fields, $contact_type, $rule );
									if ( 0 !== $contact ) {
										$individual_update_enabled = rgars( $feed, 'meta/update_individual_contact_enabled' );
										if ( 'Individual' === $contact_type && null !== $individual_update_enabled && false === (bool) $individual_update_enabled ) {
											foreach ( $form['fields'] as &$field ) {
												if ( in_array( $field['id'], array_values( $field_names ) ) ) {
													$field->failed_validation  = true;
													$field->validation_message = 'Existing record found.';
												}
											}
											$validation_result['form'] = $form;
											$validation_result['failed_validation_page'] = $current_page + 1;
											$validation_result['is_valid']               = false;
										}
									}
								}
							}
						}
					} catch ( \CiviCRM_API3_Exception $e ) {
						\Civi::log()->debug( $e->getMessage() );
						$validation_result['is_valid'] = false;
					}
				}

				if ( false !== $validation_result['is_valid'] ) {
					$contact_type  = rgars( $feed, 'meta/contact_type', '' );
					$mappings      = rgars( $feed, 'meta/' . strtolower( $contact_type ) . '_standard_fields' );
					$custom_fields = $this->get_custom_fields();
					$form_valid    = true;
					foreach ( $form['fields'] as &$field ) {
						$mapping_index = is_array( $mappings ) ? array_search( $field['id'], array_column( $mappings, 'value' ) ) : null;
						$field_value   = rgar( $entry, $field['id'] );
						if ( empty( $field_value ) ) {
							$valid = true;
						} else {
							if ( null !== $mapping_index && 0 <= $mapping_index ) {
								$mapping = $mappings[$mapping_index];
								if ( 0 === strpos( $mapping['key'], 'custom_' ) ) {
									$custom_key = explode( '_', $mapping['key'] )[1];
									$field_key = array_search( $custom_key, array_column( $custom_fields, 'id' ) );
									if ( null !== $field_key && 0 < $field_key ) {
										$field_type  = '';
										switch( strtolower( $custom_fields[$field_key]['data_type'] ) ) {
											case 'boolean':
												$valid      = null !== filter_var( $field_value, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE );
												$field_type = 'Boolean';
												break;
											case 'date':
												$valid      = false !== strtotime( $field_value );
												$field_type = 'Date';
												break;
											case 'float':
												$valid      = is_numeric( $field_value ) || is_float( $field_value );
												$field_type = 'Numeric';
												break;
											case 'string':
											case 'memo':
											case 'stateprovince':
											case 'country':
											default:
												$valid = true;
												break;
										}

										if ( false === $valid ) {
											$field->failed_validation  = true;
											$field->validation_message = $field_type . ' value required.';
											$form_valid                = false;
										}
									}
								}
							}
						}
					}
					$validation_result['form']                   = $form;
					$validation_result['failed_validation_page'] = $current_page;// + 1;
					$validation_result['is_valid']               = $form_valid;
					unset($field);
				}
			}
		}

		return $validation_result;
	}

	/**
	 * Validates each field in a form.
	 *
	 * @param array $result The validation result to be filtered.
	 * @param string|array $value The field value to be validated.
	 * @param object $form Current form object.
	 * @param object $field Current field object.
	 *
	 * @return array
	 */
	public function field_validation( $result, $value, $form, $field ) {
		$current_page  = rgpost( 'gform_source_page_number_' . $form['id'] ) ? (int) rgpost( 'gform_source_page_number_' . $form['id'] ) : 1;
		$feeds         = $this->get_feeds( $form['id'] );
		$form_valid    = true;
		$error_message = '';

		if ( ! empty( $feeds ) ) {
			foreach ( $feeds as $feed ) {
				if ( 1 !== (int) $feed['is_active'] || false === array_key_exists( 'action', $feed['meta'] ) ) {
					continue;
				}

				if ( property_exists( $field, 'inputName' ) ) {
					switch( $field['inputName'] ) {
						case 'contact_phone':
							if ( 32 < strlen( $value ) ) {
								$form_valid                = false;
								$field->validation_message = 'Value is too long. Max length is 32 characters.';
								$field->failed_validation  = true;
							}
							break;
						case 'contact_phone_ext':
							if ( 16 < strlen( $value ) ) {
								$form_valid                = false;
								$field->validation_message = 'Value is too long. Max length is 16 digits.';
								$field->failed_validation  = true;
							}
							break;
					}
				}
			}
		}

		if ( false === $form_valid ) {
			$result['form']                   = $form;
			$result['failed_validation_page'] = $current_page;// + 1;
			$result['is_valid']               = $form_valid;
		}

		return $result;
	}

	/**
	 * Checks if the number field is the phone ext to disable the comma.
	 *
	 * @param bool $include_separator The current value being set by the field.
	 * @param object $field The actual field object on the form.
	 *
	 * @return bool
	 */
	public function include_thousands_separator( $include_separator, $field ) {
		$feeds = $this->get_feeds( $field->formId );

		if ( ! empty( $feeds ) && 1006 === intval( $field->id ) ) {
			$include_separator = false;
		}

		return $include_separator;
	}

	/**
	 * Process the feed e.g. subscribe the user to a list.
	 *
	 * @param array $feed The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form The form object currently being processed.
	 *
	 * @return array|null
	 */
	public function process_contact( $feed, $entry, $form ) {
		$contact_id                 = $this->get_current_user( $form, $feed );
		$array_params               = array();
		$individual_required_fields = $this->individual_main_fields_for_feed_mapping();
		$individual_field_mappings  = rgars( $feed, 'meta/individual_standard_fields' );
		$contact_files              = array();

		// Set contact type to contact_type.
		$array_params['contact_type'] = rgars( $feed, 'meta/contact_type' );

		foreach ( $individual_required_fields as $field ) {
			if ( is_array( $field ) ) {
				$mapping    = rgars( $feed, 'meta/individual_required_fields_' . $field['name'] );
				$form_value = rgar( $entry, $mapping );
				if ( null !== $form_value ) {
					$array_params[ $field['name'] ] = $form_value;
				}
			}
		}

		foreach ( $individual_field_mappings as $mapping ) {
			$form_value = rgar( $entry, $mapping['value'] );
			if ( null !== $form_value ) {
				if ( 0 === strpos( $mapping['key'], 'custom_' ) ) {
					$custom_key = explode( '_', $mapping['key'] )[1];
					$custom_field_type = $this->get_custom_field_type( intval( $custom_key ) );
					if ( null !== $custom_field_type['data_type'] ) {
						switch ( $custom_field_type['data_type'] ) {
							case 'StateProvince':
								$data = $this->get_json( $form_value );
								if ( false !== $data ) {
									if ( is_array( $data ) ) {
										$values = array();
										foreach ( $data as $value ) {
											$value = ( 0 < strpos( $value, '_' ) ) ? explode( '_', $value )[1] : $value;
											array_push( $values, $value );
										}
										if ( 0 < count( $values ) ) {
											if ( 0 <= strpos( $custom_field_type['html_type'], 'Multi-Select' ) ) {
												$form_value = $values;
											} else {
												$form_value = implode( ',', $values );
											}
										} else {
											$form_value = '';
										}
									}
								} else {
									$form_value = ( 0 < strpos( $form_value, '_' ) ) ? explode( '_', $form_value )[1] : $form_value;
								}
								break;
							case 'String':
								$json_data = $this->get_json( $form_value );
								if ( false !== $json_data ) {
									$form_value = $json_data;
								}
								break;
							case 'File':
								$field = \RGFormsModel::get_field( $form, $mapping['value'] );
								if ( null !== $field && is_object( $field ) && 'fileupload' === $field->type && !empty( $form_value ) ) {
									$upload_path = \GFFormsModel::get_upload_path( $entry['form_id'] );
									$upload_url  = \GFFormsModel::get_upload_url( $entry['form_id'] );
									$file_type   = wp_check_filetype( basename( $form_value ), null );
									$file_name   = preg_replace( '/\.[^.]+$/', '', basename( $form_value ) );
									$file_path   = str_replace( $upload_url, $upload_path, $form_value );

									array_push(
										$contact_files,
										array(
											'name'     => basename( $form_value ),
											'type'     => ( null !== $file_type && is_array( $file_type ) ) ? $file_type['type']: '',
											'path'     => $file_path,
											'field'    => $mapping['key'],
											'field_id' => $field->id,
										)
									);
									continue 2;
								}
								break;
						}
					}
				}
				// error_log( 'process_contact::form_vaule: ' . print_r( $form_value, true ) );
				$array_params[ $mapping['key'] ] = ( !is_array( $form_value ) && ( '' === $form_value || 0 === stripos( ltrim( $form_value ), 'Select' ) ) ) ? '' : $form_value;
			}
		}

		if ( false !== $contact_id ) {
			$array_params['id'] = $contact_id;
		} else {
			if ( null !== rgars( $feed, 'meta/dedupe_rule' ) ) {
				$rule       = rgars( $feed, 'meta/dedupe_rule' );
				$contact_id = Utilities::civi_contact_dedupe( $array_params, $array_params['contact_type'], $rule );
				if ( 0 !== $contact_id ) {
					$array_params['id'] = $contact_id;
				}
			}
		}

		try {
			$contact = \civicrm_api3(
				'Contact',
				'create',
				$array_params
			);
		} catch ( \CiviCRM_API3_Exception $e ) {
			\Civi::log()->debug( $e->getMessage() );
			$this->add_feed_error( 'Error Saving Contact to CiviCRM', $feed, $entry, $form );
			return null;
		}

		if ( true !== (bool) $contact['is_error'] && 0 < $contact['count'] ) {
			$contact_id      = $contact['id'];
			$entry['feed'][] = array(
				'ID'         => $feed['id'],
				'contact_id' => $contact_id,
			);

			if ( 0 < count( $contact_files ) ) {
				foreach ( $contact_files as $file ) {
					try {
						$attachment = \civicrm_api3(
							'Attachment',
							'create',
							array(
								'name'       => $file['name'],
								'mime_type'  => $file['type'],
								'entity_id'  => $contact_id,
								'field_name' => $file['field'],
								'options'    => array(
									'move-file' => $file['path'],
								),
							)
						);
						if ( true !== (bool) $attachment['is_error'] && 0 < $attachment['count'] ) {
							$attachment_record = reset($attachment['values']);
							if ( !empty( $attachment_record['url'] ) ) {
								\GFAPI::update_entry_field( $entry['id'], $file['field_id'], $attachment_record['url'] );
							}
						}
					} catch ( \CiviCRM_API3_Exception $e ) {
						\Civi::log()->debug( $e->getMessage() );
					}
					if ( file_exists( $file['path'] ) ) {
						unlink( $file['path'] );
					}
				}
			}

			if ( ! $this->process_addresses( rgar( $entry, 2000 ), $contact_id ) ) {
				$this->add_feed_error( 'Error Saving Contact Address(es).', $feed, $entry, $form );
			}

			if ( ! $this->process_phone_numbers( rgar( $entry, 1000 ), $contact_id ) ) {
				$this->add_feed_error( 'Error Saving Contact Phone Number(s).', $feed, $entry, $form );
			}

			if ( ! $this->process_emails( rgar( $entry, 3000 ), $contact_id ) ) {
				$this->add_feed_error( 'Error Saving Contact Email(s).', $feed, $entry, $form );
			}

			if ( ! $this->process_groups( $contact_id, $feed ) ) {
				$this->add_feed_error( 'Error Saving Group(s) to contact.', $feed, $entry, $form );
			}

			if ( null !== $contact_id ) {
				$relation_to = rgars( $feed, 'meta/relation_to' );
				if ( null !== $relation_to ) {
					$contact_feeds = rgar( $entry, 'feed' );
					foreach ( $contact_feeds as $contact_feed ) {
						if ( $contact_feed['ID'] === (int) $relation_to ) {
							$contact_a            = $contact_feed['contact_id'];
							$relation_type        = rgars( $feed, 'meta/relation_type' );
							$process_relatoinship = $this->process_relationship( $contact_a, $contact_id, $relation_type );
							if ( false === $process_relatoinship ) {
								$this->add_feed_error( 'Error creating relationship', $feed, $entry, $form );
							}
							// } elseif ( null !== $process_relatoinship && is_int( $process_relatoinship ) ) {
								// delete_transient( 'gf_civicrm_api_get_contact_by_id_' . $contact_a );
							//}
							delete_transient( 'gf_civicrm_api_get_contact_by_id_' . strval( $contact_a ) );
							break;
						}
					}
				}
			}

			// Delete Contact addresses.
			delete_transient( 'gf_civicrm_get_contact_addresses_' . $contact_id );
			// Dekete Contact phone numbers.
			delete_transient( 'gf_civicrm_get_contact_phone_numbers_' . $contact_id );
			// Delete Contact emails.
			delete_transient( 'gf_civicrm_get_contact_emails_' . $contact_id );
			// Delete contact transient.
			delete_transient( 'gf_civicrm_api_get_contact_by_id_' . strval( $contact_id ) );
			return $entry;
		} else {
			$this->add_feed_error( 'Error Saving Contact to CiviCRM', $feed, $entry, $form );
		}

		return null;
	}

	/**
	 * Process the addresses from the form. Save any new/updated addresses and delete any not in the entry.
	 *
	 * @param array $addresses An array of addresses submitted from the form.
	 * @param int   $contact_id The id of the contact to associate the address to.
	 *
	 * @return bool
	 */
	protected function process_addresses( $addresses, $contact_id ) {
		$processed_addresses = true;
		$current_addresses   = $this->get_contact_addresses( $contact_id );

		if ( null !== $current_addresses && 0 < count( $current_addresses ) ) {
			$current_addresses = array_column( $current_addresses, 'id' );
		}

		foreach ( $addresses as $address_record ) {
			$address_params = array();
			$address_id     = rgar( $address_record, 2012 );

			if ( ! rgblank( $address_id ) ) {
				$address_params['id'] = $address_id;
				if ( is_array( $current_addresses ) ) {
					$address_item = array_search( intval( $address_id ), $current_addresses );
					if ( false !== $address_item ) {
						unset( $current_addresses[ $address_item ] );
					} else {
						unset( $address_params['id'] );
					}
				}
			}

			$address_params['contact_id']             = $contact_id;
			$address_params['location_type_id']       = rgar( $address_record, 2001 );
			$address_params['is_primary']             = rgar( $address_record, 2010 );
			$address_params['is_billing']             = rgar( $address_record, 2011 );
			$address_params['street_address']         = rgar( $address_record, 2002 );
			$address_params['supplemental_address_1'] = rgar( $address_record, 2003 );
			$address_params['supplemental_address_2'] = rgar( $address_record, 2004 );
			$address_params['supplemental_address_3'] = rgar( $address_record, 2005 );
			$address_params['city']                   = rgar( $address_record, 2006 );

			if ( ! rgempty( $address_record, 2009 ) ) {
				$state_province = rgar( $address_record, 2009 );
				if ( false !== strpos( $state_province, '_' ) ) {
					$address_params['state_province_id'] = explode( '_', $state_province )[1];
				} else {
					$address_params['state_province_id'] = $state_province;
				}
			}

			if ( ! rgempty( $address_record, 2007 ) ) {
				$postal_code = rgar( $address_record, 2007 );
				if ( false !== strpos( $postal_code, '-' ) ) {
					$postal_code = explode( '-', $postal_code );

					$address_params['postal_code']        = $postal_code[0];
					$address_params['postal_code_suffix'] = $postal_code[1];
				} else {
					$address_params['postal_code'] = $postal_code;
				}
			}

			$address_params['country_id']     = rgar( $address_record, 2008 );
			$address_params['street_parsing'] = 1;
			$address_params['skip_geocode']   = 1;
			$address_params['fix_address']    = 1;

			try {
				$address_result = \civicrm_api3(
					'Address',
					'create',
					$address_params
				);

				if ( 0 !== $address_result['is_error'] || 0 >= $address_result['count'] ) {
					$processed_addresses = false;
				}
			} catch ( \CiviCRM_API3_Exception $e ) {
				\Civi::log()->debug( $e->getMessage() );
				$processed_addresses = false;
			}
		}

		if ( is_array( $current_addresses ) && 0 < count( $current_addresses ) ) {
			foreach ( $current_addresses as $address ) {
				try {
					$address_result = \civicrm_api3(
						'Address',
						'delete',
						array( 'id' => $address )
					);
				} catch ( \CiviCRM_API3_Exception $e ) {
					\Civi::log()->debug( $e->getMessage() );
					$processed_addresses = false;
				}
			}
		}

		return $processed_addresses;
	}

	/**
	 * Process the phone numbers from the form. Save any new/updated phone numbers and delete any not in the entry.
	 *
	 * @param array $phone_numbers An array of phone numbers submitted from the form.
	 * @param int   $contact_id The id of the contact to associate the phone number to.
	 *
	 * @return bool
	 */
	protected function process_phone_numbers( $phone_numbers, $contact_id ) {
		$processed_phone_numbers = true;
		$current_phone_numbers   = $this->get_contact_phone_numbers( $contact_id );

		if ( null !== $current_phone_numbers && 0 < count( $current_phone_numbers ) ) {
			$current_phone_numbers = array_column( $current_phone_numbers, 'id' );
		}

		foreach ( $phone_numbers as $phone_record ) {
			$phone_params = array();
			$phone_id     = rgar( $phone_record, 1005 );

			if ( ! rgblank( $phone_id ) ) {
				$phone_params['id'] = $phone_id;
				if ( is_array( $current_phone_numbers ) ) {
					$phone_item = array_search( intval( $phone_id ), $current_phone_numbers );
					if ( false !== $phone_item ) {
						unset( $current_phone_numbers[ $phone_item ] );
					} else {
						unset( $phone_params['id'] );
					}
				}
			}

			$phone_params['contact_id']       = $contact_id;
			$phone_params['phone_type_id']    = rgar( $phone_record, 1002 );
			$phone_params['location_type_id'] = rgar( $phone_record, 1003 );
			$phone_params['is_primary']       = rgar( $phone_record, 1004 );
			$phone_params['phone']            = rgar( $phone_record, 1001 );
			$phone_params['phone_ext']        = rgar( $phone_record, 1006 );

			try {
				$phone_result = \civicrm_api3(
					'Phone',
					'create',
					$phone_params
				);

				if ( 0 !== $phone_result['is_error'] || 0 >= $phone_result['count'] ) {
					$processed_phone_numbers = false;
				}
			} catch ( \CiviCRM_API3_Exception $e ) {
				\Civi::log()->debug( $e->getMessage() );
				$processed_phone_numbers = false;
			}
		}

		if ( is_array( $current_phone_numbers ) && 0 < count( $current_phone_numbers ) ) {
			foreach ( $current_phone_numbers as $phone ) {
				try {
					$phone_result = \civicrm_api3(
						'Phone',
						'delete',
						array( 'id' => $phone )
					);
				} catch ( \CiviCRM_API3_Exception $e ) {
					\Civi::log()->debug( $e->getMessage() );
					$processed_phone_numbers = false;
				}
			}
		}

		return $processed_phone_numbers;
	}

	/**
	 * Process the email(s) from the form. Save any new/updated email(s) and delete any not in the entry.
	 *
	 * @param array $emails An array of email(s) submitted from the form.
	 * @param int   $contact_id The id of the contact to associate the email to.
	 *
	 * @return bool
	 */
	protected function process_emails( $emails, $contact_id ) {
		$processed_emails = true;
		$current_emails   = $this->get_contact_emails( $contact_id );

		if ( null !== $current_emails && 0 < count( $current_emails ) ) {
			$current_emails = array_column( $current_emails, 'id' );
		}

		foreach ( $emails as $email_record ) {
			$email_params = array();
			$email_id     = rgar( $email_record, 3004 );

			if ( ! rgblank( $email_id ) ) {
				$email_params['id'] = $email_id;
				if ( is_array( $current_emails ) ) {
					$email_item = array_search( intval( $email_id ), $current_emails );
					if ( false !== $email_item ) {
						unset( $current_emails[ $email_item ] );
					} else {
						unset( $email_params['id'] );
					}
				}
			}

			$email_params['contact_id']       = $contact_id;
			$email_params['location_type_id'] = rgar( $email_record, 3002 );
			$email_params['is_primary']       = rgar( $email_record, 3003 );
			$email_params['email']            = rgar( $email_record, 3001 );

			try {
				$email_result = \civicrm_api3(
					'Email',
					'create',
					$email_params
				);

				if ( 0 !== $email_result['is_error'] || 0 >= $email_result['count'] ) {
					$processed_emails = false;
				}
			} catch ( \CiviCRM_API3_Exception $e ) {
				\Civi::log()->debug( $e->getMessage() );
				$processed_emails = false;
			}
		}

		if ( is_array( $current_emails ) && 0 < count( $current_emails ) ) {
			foreach ( $current_emails as $email ) {
				try {
					$email_result = \civicrm_api3(
						'Email',
						'delete',
						array( 'id' => $email )
					);
				} catch ( \CiviCRM_API3_Exception $e ) {
					\Civi::log()->debug( $e->getMessage() );
					$processed_emails = false;
				}
			}
		}

		return $processed_emails;
	}

	/**
	 * Adds the specified groups from the feed to the contact.
	 *
	 * @param int $contact_id The id of the contact to add the groups to.
	 *
	 * @return bool
	 */
	protected function process_groups( $contact_id, $feed ) {
		$processed         = true;
		// $feed              = $this->get_current_feed();
		$individual_groups = rgars( $feed, 'meta/individual_group' );
		if ( null !== $individual_groups ) {
			if ( is_array( $individual_groups ) ) {
				foreach ( $individual_groups as $group ) {
					try {
						$group_contact = \civicrm_api3(
							'GroupContact',
							'create',
							array(
								'group_id'   => $group,
								'contact_id' => $contact_id,
							)
						);
					} catch ( \CiviCRM_API3_Exception $e ) {
						\Civi::log()->debug( $e->getMessage() );
					}
				}
			} else if ( '' !== $individual_groups ) {
				try {
					$group_contact = \civicrm_api3(
						'GroupContact',
						'create',
						array(
							'group_id'   => $individual_groups,
							'contact_id' => $contact_id,
						)
					);
				} catch ( \CiviCRM_API3_Exception $e ) {
					\Civi::log()->debug( $e->getMessage() );
				}
			}
		}
		return $processed;
	}

	/**
	 * Check if data is JSON or not.
	 *
	 * @param mixed $object The data to check if it is a JSON string.
	 *
	 * @return bool|array The return value. Will return false if an error occurs decoding otherwise the decoded JSON value.
	 */
	protected function get_json( $object ) {
		$json = json_decode( $object, true, 512 );
		if ( json_last_error() !== \JSON_ERROR_NONE ) {
			return false;
		}

		if ( 0 === strpos( ltrim( $object ), '{' ) ) {
			return $json;
		}

		if ( 0 === strpos( ltrim( $object ), '[' ) ) {
			return $json;
		}

		if ( is_array( $json ) ) {
			return $json;
		}

		return false;
	}

	/**
	 * Process the feed e.g. subscribe the user to a list.
	 *
	 * @param array $feed The feed object to be processed.
	 * @param array $entry The entry object currently being processed.
	 * @param array $form The form object currently being processed.
	 *
	 * @return array|null
	 */
	public function process_organization( $feed, $entry, $form ) {
		$contact_id                  = null;
		$array_params                = array( 'dupe_check' => true );
		$organization_field_mappings = rgars( $feed, 'meta/organization_standard_fields' );
		$organization_name           = rgar( $entry, rgars( $feed, 'meta/organization_option' ) );
		$selected_org                = null;

		if ( 1 === (int) rgars( $feed, 'meta/organization_select_enabled' ) ) {
			$selected_org = rgar( $entry, rgars( $feed, 'meta/organization_select' ) );
		}

		if ( 'none' !== $selected_org ) {
			if ( null === $selected_org || 'other' === strtolower( $selected_org ) ) {
				if ( ! empty( $organization_name ) && 'other' !== strtolower( $organization_name ) ) {
					$array_params['organization_name'] = $organization_name;

					// Set contact type to contact_type.
					$array_params['contact_type'] = rgars( $feed, 'meta/contact_type' );

					foreach ( $organization_field_mappings as $mapping ) {
						$form_value = rgar( $entry, $mapping['value'] );
						if ( null !== $form_value ) {
							if ( 0 === strpos( $mapping['key'], 'custom_' ) ) {
								$custom_key        = explode( '_', $mapping['key'] )[1];
								$custom_field_type = $this->get_custom_field_type( intval( $custom_key ) );
								if ( null !== $custom_field_type['data_type'] ) {
									switch ( $custom_field_type['data_type'] ) {
										case 'StateProvince':
											$data = $this->get_json( $form_value );
											if ( false !== $data ) {
												if ( is_array( $data ) ) {
													$values = array();
													foreach ( $data as $value ) {
														$value = ( 0 < strpos( $value, '_' ) ) ? explode( '_', $value )[1] : $value;
														array_push( $values, $value );
													}
													if ( 0 < count( $values ) ) {
														$form_value = implode( ',', $values );
													} else {
														$form_value = '';
													}
												}
											} else {
												$form_value = ( 0 < strpos( $form_value, '_' ) ) ? explode( '_', $form_value )[1] : $form_value;
											}
											break;
										case 'String':
											$json_data = $this->get_json( $form_value );
											if ( false !== $json_data ) {
												$form_value = $json_data;
											}
											break;
									}
								}
							}
							$array_params[ $mapping['key'] ] = ( '' === $form_value || 0 === stripos( ltrim( $form_value ), 'Select' ) ) ? '' : $form_value;
						}
					}

					try {
						$contact = \civicrm_api3(
							'Contact',
							'create',
							$array_params
						);
					} catch ( \CiviCRM_API3_Exception $e ) {
						\Civi::log()->debug( $e->getMessage() );
						return null;
					}

					if ( true !== $contact['is_error'] && 0 < $contact['count'] ) {
						$contact_id      = $contact['id'];
						$entry['feed'][] = array(
							'ID'         => $feed['id'],
							'contact_id' => $contact_id,
						);

						// Delete contact transient.
						delete_transient( 'gf_civicrm_api_get_contact_by_id_' . $contact_id );

						// Delete organizations transient.
						delete_transient( 'gf_civicrm_get_organizations' );
					} else {
						$this->add_feed_error( 'Error Saving Organization', $feed, $entry, $form );
					}
				} else {
					$this->add_feed_error( 'Error Missing data to save Organization', $feed, $entry, $form );
				}
			} elseif ( null !== $selected_org && 'other' !== strtolower( $selected_org ) ) {
				$contact_id      = $selected_org;
				$entry['feed'][] = array(
					'ID'         => $feed['id'],
					'contact_id' => $contact_id,
				);
			}

			if ( null !== $contact_id ) {
				$relation_to = rgars( $feed, 'meta/relation_to' );
				if ( null !== $relation_to ) {
					$contact_feeds = rgar( $entry, 'feed' );
					foreach ( $contact_feeds as $contact_feed ) {
						if ( (int) $contact_feed['ID'] === (int) $relation_to ) {
							$contact_a            = $contact_feed['contact_id'];
							$relation_type        = rgars( $feed, 'meta/relation_type' );
							$process_relatoinship = $this->process_relationship( $contact_a, $contact_id, $relation_type );
							if ( false === $process_relatoinship ) {
								$this->add_feed_error( 'Error creating relationship', $feed, $entry, $form );
								return null;
							} elseif ( null !== $process_relatoinship && is_int( $process_relatoinship ) ) {
								delete_transient( 'gf_civicrm_api_get_contact_by_id_' . $contact_a );
								delete_transient( 'gf_civicrm_api_get_contact_by_id_' . $contact_id );
							}
							break;
						}
					}
				}

				if ( ! $this->process_groups( $contact_id, $feed ) ) {
					$this->add_feed_error( 'Error Saving Group(s) to contact.', $feed, $entry, $form );
				}
			}
		}

		return $entry;
	}

	/**
	 * Checks for a current relationship and if one isn't found, it will attempt to create one.
	 *
	 * @param int $contact_a The contact id on the left side of the relationship.
	 * @param int $contact_b The contact id on the right side of the relationship.
	 * @param int $relation_type The relationship type to set for the contacts.
	 *
	 * @return int|bool|null
	 */
	protected function process_relationship( $contact_a, $contact_b, $relation_type ) {
		if ( null !== $contact_a && null !== $contact_b && null !== $relation_type ) {
			$current_relationship = civicrm_api3(
				'Relationship',
				'get',
				array(
					'sequential'           => true,
					'return'               => array( 'id' ),
					'contact_id_a'         => $contact_a,
					'contact_id_b'         => $contact_b,
					'relationship_type_id' => $relation_type,
					'is_active'            => true,
				)
			);

			if ( false === ( (bool) $current_relationship['is_error'] ) && 0 <= $current_relationship['count'] ) {
				try {
					$relationship = civicrm_api3(
						'Relationship',
						'create',
						array(
							'sequential'           => true,
							'contact_id_a'         => $contact_a,
							'contact_id_b'         => $contact_b,
							'relationship_type_id' => $relation_type,
						)
					);
				} catch ( \CiviCRM_API3_Exception $e ) {
					\Civi::log()->debug( $e->getMessage() );
					return false;
				}

				if ( false !== (bool) $relationship['is_error'] || 0 >= $relationship['count'] ) {
					return false;
				} else {
					return $relationship['values'][0]['id'];
				}
			} else {
				return $current_relationship['values'][0]['id'];
			}
			return null;
		}
	}

	/**
	 * Determines if feed processing is delayed by another add-on.
	 *
	 * Also enables use of the gform_is_delayed_pre_process_feed filter.
	 *
	 * @param array $entry The Entry Object currently being processed.
	 * @param array $form The Form Object currently being processed.
	 *
	 * @return bool
	 */
	public function maybe_delay_feed( $entry, $form ) {
		if ( ! empty( $entry['payment_status'] ) && ( ( 'Paid' === $entry['payment_status'] ) || ( 'Active' === $entry['payment_status'] ) ) ) {
			return false;
		}
		return false;
	}

	/**
	 * Prepare contact required fields for feed field mapping.
	 *
	 * @access public
	 * @return array
	 */
	public function individual_main_fields_for_feed_mapping() {

		return array(
			array(
				'name'          => 'first_name',
				'label'         => __( 'First Name', 'gf_civicrm_addon' ),
				'required'      => true,
				'field_type'    => array( 'name', 'text', 'hidden' ),
				'default_value' => $this->get_first_field_by_type( 'name' ),
			),
			array(
				'name'       => 'middle_name',
				'label'      => __( 'Middle Name', 'gf_civicrm_addon' ),
				'required'   => false,
				'field_type' => array( 'name', 'text', 'hidden' ),
			),
			array(
				'name'          => 'last_name',
				'label'         => __( 'Last Name', 'gf_civicrm_addon' ),
				'required'      => true,
				'field_type'    => array( 'name', 'text', 'hidden' ),
				'default_value' => $this->get_first_field_by_type( 'name' ),
			),
		);
	}

	/**
	 * Prepare contact standard fields for feed field mapping.
	 *
	 * @access public
	 * @param string $contact_type The contact type to get firlds for.
	 * @return array $contact_fields The array of standard fields
	 */
	public function contact_standard_fields_for_feed_mapping( $contact_type ) {

		$contact_get_fields = \CRM_Contact_BAO_Contact::exportableFields( $contact_type, false, false );
		$remove_fields      = array(
			'Internal Contact ID',
			'Contact Type',
			'Contact Subtype',
			'Legal Identifier',
			'OpenID',
			'Contact is in Trash',
			'Image Url',
			'External Identifier',
			'Sort Name',
			'Display Name',
			'Unique ID (OpenID)',
			'Current Employer ID',
			'Created Date',
			'Modified Date',
			'Contact Hash',
			'Source of Contact Data',
			'Membership ID',
			'Contact ID',
			'Activity ID',
			'Source Contact ID',
			'Activity is in the Trash',
			'Primary Member ID',
			'Source Contact',
			'Test',
			'Is this activity a current revision in versioning chain?',
			'Campaign',
			'Engagement Index',
			'Activity Type',
			'Added By',
			'Activity Status',
			'With Contact',
			'Assigned To',
			'Priority',
			'Contribution ID',
			'Transaction ID',
			'Invoice ID',
			'Recurring Contributions ID',
		);

		$contact_fields = array();
		foreach ( $contact_get_fields as $key => $value ) {
			if ( ! in_array( $value['title'], $remove_fields, true ) ) {
				$contact_fields[] = array(
					'value' => esc_attr( $key ),
					'label' => esc_html( $value['title'] ),
				);
			}
		}
		$contact_fields = Utilities::sort_list( $contact_fields );
		array_unshift(
			$contact_fields,
			array(
				'label' => 'Select CiviCRM Field',
				'value' => '',
			)
		);
		return $contact_fields;
	}

	/**
	 * Get Groups for Contacts.
	 *
	 * @access public
	 * @return array The array of contact groups to assign to a contact.
	 */
	public function contact_groups_for_feed_mapping() {
		$groups = array();
		try {
			$group_items = \civicrm_api3(
				'Group',
				'get',
				array(
					'sequential' => 1,
					'is_active'  => 1,
					'options'          => array(
						'limit' => 0,
						'sort'  => 'name',
						'cache' => '100 minutes',
					),
				)
			);
		} catch ( \CiviCRM_API3_Exception $e ) {
			\Civi::log()->debug( $e->getMessage() );
			return $groups;
		}

		if ( true !== $group_items['is_error'] && 0 < $group_items['count'] ) {
			foreach ( $group_items['values'] as $group ) {
				$groups[] = array(
					'value' => esc_attr( $group['name'] ),
					'label' => esc_html( $group['title'] ),
				);
			}
		}
		return $groups;
	}

	/**
	 * Gets all dedupe rules for a given contact type.
	 *
	 * @return array The array of dedupe rules.
	 */
	public function contact_dedupe_rules_for_feed_mappings() {
		/* Get current feed. */
		$current_feed = $this->get_current_feed();
		// Get contact type from feed.
		$contact_type = rgars( $current_feed, 'meta/contact_type' );
		// Dedupe Choices.
		$choices = array();
		if ( null !== $contact_type ) {
			$rules = \CRM_Dedupe_BAO_RuleGroup::getByType( $contact_type );
			// error_log( 'GF_CiviCRM::contact_dedupe_rules_for_feed_mappings:rules ' . print_r( $rules, true ) );
			foreach ( $rules as $ruleKey => $ruleValue ) {
				$choices[] = array(
					'value' => esc_attr( $ruleKey ),
					'label' => esc_html( $ruleValue ),
				);
			}
			if ( 0 < count( $choices ) ) {
				array_unshift(
					$choices,
					array(
						'label' => 'Rules',
						'value' => '',
					)
				);
			}
		}

		return $choices;
	}

	/**
	 * Get Groups for Contacts.
	 *
	 * @access public
	 * @return array The array of contact groups to assign to a contact.
	 */
	public function get_contact_types() {
		$contact_types = array();

		try {
			$contact_type_items = \civicrm_api3(
				'ContactType',
				'get',
				array(
					'sequential' => 1,
					'is_active'  => 1,
					'options'          => array(
						'limit' => 0,
						'sort'  => 'name',
						'cache' => '100 minutes',
					),
				)
			);
		} catch ( \CiviCRM_API3_Exception $e ) {
			\Civi::log()->debug( $e->getMessage() );
			return $contact_types;
		}

		if ( true !== $contact_type_items['is_error'] && 0 < $contact_type_items['count'] ) {
			foreach ( $contact_type_items['values'] as $contact_type ) {
				$contact_types[] = array(
					'label' => esc_html( $contact_type['label'] ),
					'value' => esc_attr( $contact_type['name'] ),
				);
			}
		}

		if ( 0 < count( $contact_types ) ) {
			array_unshift(
				$contact_types,
				array(
					'label' => 'Select',
					'value' => '',
				)
			);
		}

		return $contact_types;
	}

	/**
	 * Get the required fields for an organization.
	 *
	 * @since 1.0
	 * @return array The fields to map to the form fields.
	 */
	protected function organization_main_fields_for_feed_mapping() {
		return array(
			array(
				'name'          => 'organization_name',
				'label'         => __( 'Organization Name', 'gf_civicrm_addon' ),
				'required'      => true,
				'field_type'    => array( 'name', 'text', 'hidden' ),
				'default_value' => $this->get_first_field_by_type( 'name' ),
			),
		);
	}

	/**
	 * Setup columns for feed list table.
	 *
	 * @access public
	 * @return mixed
	 */
	public function feed_list_columns() {
		return array(
			'feedName' => __( 'Name', 'gf_civicrm_addon' ),
			'action'   => __( 'Action', 'gf_civicrm_addon' ),
		);
	}

	/**
	 * Get the form fields that match the types.
	 *
	 * @since 1.0
	 * @param array  $types The types of fields to look for.
	 * @param string $empty_option The label to be applied as an empty option field.
	 *
	 * @return array $form_fields The fields for the current form that have a match to the $types.
	 */
	protected function get_fields_by_type( $types, $empty_option = null ) {
		$fields = array();
		$form   = $this->get_current_form();

		$form_fields = \GFFormsModel::get_fields_by_type( $form, $types );
		foreach ( $form_fields as $field ) {
			$fields[] = array(
				'label' => strip_tags( \GFCommon::get_label( $field ) ),
				'value' => $field->id,
			);
		}
		if ( 0 < count( $fields ) && null !== $empty_option ) {
			array_unshift(
				$fields,
				array(
					'label' => $empty_option,
					'value' => '',
				)
			);
		}
		return $fields;
	}

	/**
	 * Get a list of choices based on current feeds, excluding current feed.
	 *
	 * @since 1.0
	 *
	 * @return array $feed_options The feeds for this addon that doesn't include current feed.
	 */
	protected function get_feeds_as_options() {
		/* Get current feed. */
		$current_feed = $this->get_current_feed();

		/* Get current form */
		$form = $this->get_current_form();

		/* Get all feeds for current form */
		$feeds = $this->get_active_feeds( $form['id'] );

		$feed_options = array(
			array(
				'label' => 'Select',
				'value' => '',
			),
		);

		foreach ( $feeds as $feed ) {
			if ( $feed['id'] !== $current_feed['id'] ) {
				$feed_options[] = array(
					'label' => esc_html( rgars( $feed, 'meta/feedName' ) ),
					'value' => esc_attr( $feed['id'] ),
				);
			}
		}

		return $feed_options;
	}

	/**
	 * Get a list of relationship types allowd for current feed based on another feed contact type.
	 *
	 * @since 1.0
	 *
	 * @return array $choices The relationship types.
	 */
	protected function get_relationship_types() {
		$current_feed    = $this->get_current_feed();
		$posted_settings = $this->get_posted_settings();
		$feed_relation   = rgars( $posted_settings, 'relation_to' );
		if ( ! is_array( $feed_relation ) && ( empty( $feed_relation ) || '' === $feed_relation ) ) {
			$feed_relation = rgars( $current_feed, 'meta/relation_to' );
		}
		if ( $feed_relation === $current_feed ) {
			return array();
		}

		$form                       = $this->get_current_form();
		$feeds                      = $this->get_active_feeds( $form['id'] );
		$relation_feed_contact_type = null;
		foreach ( $feeds as $feed ) {
			if ( $feed_relation === $feed['id'] ) {
				$relation_feed_contact_type = rgars( $feed, 'meta/contact_type' );
				break;
			}
		}

		if ( null !== $relation_feed_contact_type ) {
			$feed_contact_type = rgars( $posted_settings, 'contact_type' );
			if ( '' === $feed_contact_type ) {
				$feed_contact_type = rgars( $current_feed, 'meta/contact_type' );
			}
			try {
				$relationship_types = \civicrm_api3(
					'RelationshipType',
					'get',
					array(
						'sequential'     => 1,
						'contact_type_a' => $relation_feed_contact_type,
						'contact_type_b' => $feed_contact_type,
					)
				);
			} catch ( \CiviCRM_API3_Exception $e ) {
				\Civi::log()->debug( $e->getMessage() );
				return array();
			}

			$relation_options = array(
				array(
					'label' => 'Select',
					'value' => '',
				),
			);

			foreach ( $relationship_types['values'] as $type ) {
				$relation_options[] = array(
					'label' => esc_html( $type['name_a_b'] ),
					'value' => esc_attr( $type['id'] ),
				);
			}

			return $relation_options;
		}

		return array();
	}

	/**
	 * Checks the form to see if we need to populate the form with values from CiviCRM
	 *
	 * @param object $form The current form being renderd.
	 * @param bool   $ajax Is AJAX enabled.
	 * @param array  $field_values An array of dyanmic population parameter keys with their corresponding values to be populated.
	 * @return mixed
	 */
	public function form_pre_render( $form, $ajax, $field_values ) {
		$feeds = $this->get_feeds( $form['id'] );

		if ( ! empty( $feeds ) ) {
			foreach ( $feeds as $feed ) {
				if ( 1 !== (int) $feed['is_active'] || false === array_key_exists( 'action', $feed['meta'] ) ) {
					continue;
				}

				switch ( rgars( $feed, 'meta/action' ) ) {
					case 'createContact':
						$form = $this->setupContact( $form, $feed, $field_values );
						break;

					case 'createMembership':
						$this->setupMembership( $form, $feed );
						break;
				}
			}
		}
		return $form;
	}

	/**
	 * Check to see if we need to add repeater fields to the form.
	 *
	 * @param object $form The form we need to check and see if we should add repeaters to.
	 *
	 * @return object
	 */
	public function add_repeater_fields( $form ) {
		$feeds = $this->get_feeds( $form['id'] );
		if ( ! empty( $feeds ) ) {
			foreach ( $feeds as $feed ) {
				if ( 1 !== (int) $feed['is_active'] || false === array_key_exists( 'action', $feed['meta'] ) && ( 'createContact' !== $feed['meta']['action'] ) && 'Individual' !== rgars( $feed, 'meta/contact_type' ) ) {
					continue;
				}

				if ( 1 === (int) rgars( $feed, 'meta/individual_use_phone_numbers_enabled' ) && false === array_search( '1000', array_column( $form['fields'], 'id' ) ) ) {
					$this->create_phone_number_fields( $form, rgars( $feed, 'meta/indvidial_phone_number_repeater' ) );
				}

				if ( 1 === (int) rgars( $feed, 'meta/individual_use_address_enabled' ) && false === array_search( '2000', array_column( $form['fields'], 'id' ) ) ) {
					$this->create_address_fields( $form, rgars( $feed, 'meta/individual_address_repeater' ) );
				}

				if ( 1 === (int) rgars( $feed, 'meta/individual_use_email_enabled' ) && false === array_search( '3000', array_column( $form['fields'], 'id' ) ) ) {
					$this->create_email_fields( $form, rgars( $feed, 'meta/individual_email_repeater' ) );
				}
			}
		}
		return $form;
	}

	/**
	 * Removed the repeaters if the form has them.
	 *
	 * @param array  $form_meta The form's meta data.
	 * @param int    $form_id The id of the form the meta data is for.
	 * @param string $meta_name Meta name.
	 */
	public function remove_repeater_fields( $form_meta, $form_id, $meta_name ) {

		if ( 'display_meta' === $meta_name ) {
			$feeds = $this->get_feeds( $form_id );
			if ( ! empty( $feeds ) ) {
				foreach ( $feeds as $feed ) {
					if ( 1 !== (int) $feed['is_active'] || false === array_key_exists( 'action', $feed['meta'] ) ) {
						continue;
					}

					if ( 'createContact' === $feed['meta']['action'] ) {
						// Remove the Repeater field: ID 1000, 2000. 3000.
						$form_meta['fields'] = wp_list_filter(
							$form_meta['fields'],
							array(
								'id' => 1000,
							),
							'NOT'
						);
						$form_meta['fields'] = wp_list_filter(
							$form_meta['fields'],
							array(
								'id' => 2000,
							),
							'NOT'
						);
						$form_meta['fields'] = wp_list_filter(
							$form_meta['fields'],
							array(
								'id' => 3000,
							),
							'NOT'
						);
					}
				}
			}
		}

		return $form_meta;
	}

	/**
	 * Create the repeater and repeater fields for contact phone numbers.
	 *
	 * @param object $form The form to add the repeater to. This is by reference so no need to return anything.
	 * @param int    $placeholder_field The ID of the field in which to add the repeater to the form.
	 */
	public function create_phone_number_fields( &$field, $field_values ) {
		$phone_types      = $this->get_data_type_options( 'Phone', 'phone_type_id' );
		$phone_locations  = $this->get_data_type_options( 'Phone', 'location_type_id' );
		$type_choices     = array();
		$location_choices = array();
		if ( 0 < count( $phone_types ) ) {
			foreach ( $phone_types as $type ) {
				$options        = explode( '|', $type );
				$type_choices[] = array(
					'text'       => $options[0],
					'value'      => $options[1],
					'isSelected' => false,
					'price'      => '',
				);
			}
		}
		if ( 0 < count( $phone_locations ) ) {
			foreach ( $phone_locations as $location ) {
				$options            = explode( '|', $location );
				$location_choices[] = array(
					'text'       => $options[0],
					'value'      => $options[1],
					'isSelected' => false,
					'price'      => '',
				);
			}
		}
		foreach ( $field['fields'] as $repeater_field ) {
			if ( property_exists( $repeater_field, 'inputName' ) && 'contact_phone_type' === $repeater_field['inputName'] ) {
				$repeater_field['choices'] = $type_choices;
				if ( array_key_exists( 'contact_phone_type', $field_values ) ) {
					$repeater_field['defaultValue'] = $field_values[ 'contact_phone_type' ];
				}
			}

			if ( property_exists( $repeater_field, 'inputName' ) && 'contact_phone_location' === $repeater_field['inputName'] ) {
				$repeater_field['choices'] = $location_choices;
				if ( array_key_exists( 'contact_phone_location', $field_values ) ) {
					$repeater_field['defaultValue'] = $field_values[ 'contact_phone_location' ];
				}
			}
		}
	}

	/**
	 * Create the repeater and repeater fields for contact address(es).
	 *
	 * @param object $form The form to add the repeater to. This is by reference so no need to return anything.
	 * @param int    $placeholder_field The ID of the field in which to add the repeater to the form.
	 */
	public function create_address_fields( &$field, $field_values ) {
		$address_types       = $this->get_data_type_options( 'Address', 'location_type_id' );
		$type_choices        = array();

		if ( 0 < count( $address_types ) ) {
			foreach ( $address_types as $type ) {
				$options        = explode( '|', $type );
				$type_choices[] = array(
					'text'  => $options[0],
					'value' => $options[1],
				);
			}
		}

		foreach( $field['fields'] as $repeater_field ) {
			if ( property_exists( $repeater_field, 'inputName' ) && 'contact_address_type' === $repeater_field['inputName'] ) {
				$repeater_field['choices'] = $type_choices;
				if ( array_key_exists( 'contact_address_type', $field_values ) ) {
					$repeater_field['defaultValue'] = $field_values[ 'contact_address_type' ];
				}
			}

			if ( property_exists( $repeater_field, 'inputName' ) && 'contact_address_country' === $repeater_field['inputName'] ) {
				$repeater_field['choices'] = $this->get_country_choices();
				if ( array_key_exists( 'contact_address_country', $field_values ) ) {
					$repeater_field['defaultValue'] = $field_values[ 'contact_address_country' ];
				}
			}

			if ( property_exists( $repeater_field, 'inputName' ) && 'contact_address_state' === $repeater_field['inputName'] ) {
				$repeater_field['choices'] = $this->get_state_choices();
				if ( array_key_exists( 'contact_address_state', $field_values ) ) {
					$repeater_field['defaultValue'] = $field_values[ 'contact_address_state' ];
				}
			}
		}
	}

	/**
	 * Create the repeater fields for contact email(s).
	 *
	 * @param object $form The form to add the repeater to. This is by reference so no need to return anything.
	 * @param int    $placeholder_field The ID of the field in which to add the repeater to the form.
	 */
	public function create_email_fields( &$field, $field_values ) {
		$email_types             = $this->get_data_type_options( 'Email', 'location_type_id' );
		$type_choices            = array();
		if ( 0 < count( $email_types ) ) {
			foreach ( $email_types as $type ) {
				$options        = explode( '|', $type );
				$type_choices[] = array(
					'text'  => $options[0],
					'value' => $options[1],
				);
			}
		}

		foreach ( $field['fields'] as $repeater_field ) {
			if ( property_exists( $repeater_field, 'inputName' ) && 'contact_email_type' === $repeater_field['inputName'] ) {
				$repeater_field['choices'] = $type_choices;
				if ( array_key_exists( 'contact_email_type', $field_values ) ) {
					$repeater_field['defaultValue'] = $field_values[ 'contact_email_type' ];
				}
			}
		}
	}


	/**
	 * Check to see if we need to populate values for repeater fields.
	 *
	 * @param array $args The arg fields for the form.
	 *
	 * @return array
	 */
	public function init_form_args( $args ) {
		$form  = \GFFormsModel::get_form_meta( $args['form_id'] );
		$feeds = $this->get_feeds( $form['id'] );

		if ( ! empty( $feeds ) ) {
			foreach ( $feeds as $feed ) {
				if ( 1 !== (int) $feed['is_active'] || false === array_key_exists( 'action', $feed['meta'] ) ) {
					// continue;
					return $args;
				}

				if ( 'createContact' === rgars( $feed, 'meta/action' ) && 'Individual' === rgars( $feed, 'meta/contact_type' ) ) {
					$contact_id = $this->get_current_user( $form, $feed );
					if ( false !== $contact_id && is_int( $contact_id ) ) {
						$repeater_values = array();
						if ( 1 === (int) rgars( $feed, 'meta/individual_use_phone_numbers_enabled' ) ) {
							$contact_phone_numbers = $this->get_contact_phone_numbers( $contact_id );
							if ( null !== $contact_phone_numbers && is_array( $contact_phone_numbers ) ) {
								foreach ( $contact_phone_numbers as $contact_number ) {
									$repeater_values['contact_phone'][]          = $contact_number['phone'];
									$repeater_values['contact_phone_type'][]     = $contact_number['phone_type_id'];
									$repeater_values['contact_phone_location'][] = $contact_number['location_type_id'];
									$repeater_values['contact_phone_primary'][]  = ( array_key_exists( 'is_primary', $contact_number ) && Utilities::is_boolean( $contact_number['is_primary'] ) ) ? 1 : 0;
									$repeater_values['contact_phone_id'][]       = $contact_number['id'];
									$repeater_values['contact_phone_ext'][]      = $contact_number['phone_ext'];
								}
							}
						}
						if ( 1 === (int) rgars( $feed, 'meta/individual_use_address_enabled' ) ) {
							$contact_addresses = $this->get_contact_addresses( $contact_id );
							if ( null !== $contact_addresses && is_array( $contact_addresses ) ) {
								foreach ( $contact_addresses as $contact_address ) {
									$country_id = array_key_exists( 'country_id', $contact_address ) ? $contact_address['country_id'] : '';
									$state_id   = $country_id . '_' . ( array_key_exists( 'state_province_id', $contact_address ) ? $contact_address['state_province_id'] : '' );

									$repeater_values['contact_address_type'][]    = $contact_address['location_type_id'];
									$repeater_values['contact_address_1'][]       = $contact_address['street_address'];
									$repeater_values['contact_address_2'][]       = array_key_exists( 'supplemental_address_1', $contact_address ) ? $contact_address['supplemental_address_1'] : '';
									$repeater_values['contact_address_3'][]       = array_key_exists( 'supplemental_address_2', $contact_address ) ? $contact_address['supplemental_address_2'] : '';
									$repeater_values['contact_address_4'][]       = array_key_exists( 'supplemental_address_3', $contact_address ) ? $contact_address['supplemental_address_3'] : '';
									$repeater_values['contact_address_city'][]    = array_key_exists( 'city', $contact_address ) ? $contact_address['city'] : '';
									$repeater_values['contact_address_postal'][]  = array_key_exists( 'postal_code', $contact_address ) ? $contact_address['postal_code'] : '';
									$repeater_values['contact_address_country'][] = $country_id;
									$repeater_values['contact_address_state'][]   = $state_id;
									$repeater_values['contact_address_primary'][] = ( array_key_exists( 'is_primary', $contact_address ) && Utilities::is_boolean( $contact_address['is_primary'] ) ) ? 1 : 0;
									$repeater_values['contact_address_billing'][] = ( array_key_exists( 'is_billing', $contact_address ) && Utilities::is_boolean( $contact_address['is_billing'] ) ) ? 1 : 0;
									$repeater_values['contact_address_id'][]      = $contact_address['id'];
								}
							}
						}
						if ( 1 === (int) rgars( $feed, 'meta/individual_use_email_enabled' ) ) {
							$contact_emails = $this->get_contact_emails( $contact_id );
							if ( null !== $contact_emails && is_array( $contact_emails ) ) {
								foreach ( $contact_emails as $contact_email ) {
									$repeater_values['contact_email'][]         = $contact_email['email'];
									$repeater_values['contact_email_type'][]    = $contact_email['location_type_id'];
									$repeater_values['contact_email_primary'][] = ( array_key_exists( 'is_primary', $contact_email ) && Utilities::is_boolean( $contact_email['is_primary'] ) ) ? 1 : 0;
									$repeater_values['contact_email_id'][]      = $contact_email['id'];
								}
							}
						}

						if ( 0 < count( $repeater_values ) ) {
							$args['field_values'] = $repeater_values;
						}
					} else {
						$default_country = null;
						$default_state = null;
						if ( class_exists('CRM_Core_Config') ) {
							$config = \CRM_Core_Config::singleton();
							$default_state = $config->defaultContactStateProvince;
						}
						if ( class_exists('Civi') ) {
							$default_country = \Civi::settings()->get('defaultContactCountry');
						}

						if ( null !== $default_country && null !== $default_state ) {
							$repeater_values['contact_address_country'][] = $default_country;
							$repeater_values['contact_address_state'][]   = $default_country . '_' .$default_state;
							$args['field_values'] = $repeater_values;
						}
					}
				}
			}
		}
		return $args;
	}

	/**
	 * Filters the field content.
	 *
	 * @since 2.1.2.14 Added form and field ID modifiers.
	 *
	 * @param string $content The field content.
	 * @param array  $field The Field Object.
	 * @param string $value The field value.
	 * @param int    $lead_id The entry ID.
	 * @param int    $form_id The form ID.
	 *
	 * @return string The updated html content if needed.
	 */
	public function render_content_html( $content, $field, $value, $lead_id, $form_id ) {
		if ( 0 === $lead_id ) {
			$feeds = $this->get_active_feeds( $form_id );

			if ( 0 < count( $feeds ) && in_array( intval( $field['id'] ), array( 1000, 2000, 3000 ), true ) ) {
				$doc = new \DomDocument();
				$doc->loadXML( $content );

				$finder = new \DomXPath( $doc );
				$nodes  = $finder->query( "//div[contains(concat(' ', normalize-space(@class), ' '), 'gfield_repeater_cell')]" );
				foreach ( $nodes as $node ) {
					$parent = $node->parentNode;
					if ( isset( $parent ) ) {
						$parent_class = $parent->getAttribute( 'class' );
						if ( false === strpos( $parent_class, 'gform_fields' ) ) {
							$parent->setAttribute( 'class', $parent_class . ' gform_fields' );
						}
						$parent_class = $parent->getAttribute( 'class' );
					}
					$containers = $finder->query( ".//*[contains(concat(' ', normalize-space(@class), ' '), 'ginput_container')]", $node );
					if( false !== $containers && 1 === count($containers) ) {
						$wrapper = $containers[0]->parentNode;
						$label      = $wrapper->firstChild;
						$node_class = $node->getAttribute( 'class' );
						$field      = $label->getAttribute( 'for' );
						if ( ! empty( $field ) && '' !== $field ) {
							$ids = explode( '_', $field );
							if ( 0 < count( $ids ) ) {
								$field_id = $ids[ count( $ids ) - 1 ];
								switch ( $field_id ) {
									case ( preg_match( '/1001.*/', $field_id ) ? true : false ):
										if ( false === strpos( $node_class, 'column' ) ) {
											$node->setAttribute( 'class', $node_class . ' column large-3' );
										}
										if ( false === strpos( $parent_class, 'row' ) ) {
											$parent->setAttribute( 'class', $parent_class . ' row' );
										}
										$buttons = $parent->lastChild;
										if ( false !== strpos( $buttons->getAttribute( 'class' ), 'gfield_repeater_buttons' ) && false === strpos( $buttons->getAttribute( 'class' ), 'column' ) ) {
											$buttons->setAttribute( 'class', $buttons->getAttribute( 'class' ) . ' column small-12' );
										}
										break;
									case ( preg_match( '/1002.*/', $field_id ) ? true : false ):
										if ( false === strpos( $node_class, 'column' ) ) {
											$node->setAttribute( 'class', $node_class . ' column large-2' );
										}
										break;
									case ( preg_match( '/1003.*/', $field_id ) ? true : false ):
										if ( false === strpos( $node_class, 'column' ) ) {
											$node->setAttribute( 'class', $node_class . ' column large-2' );
										}
										break;
									case ( preg_match( '/1006.*/', $field_id ) ? true : false ):
										if ( false === strpos( $node_class, 'column' ) ) {
											$node->setAttribute( 'class', $node_class . ' column large-3' );
										}
										break;
									case ( preg_match( '/2006.*|2008.*/', $field_id ) ? true : false ):
										if ( false === strpos( $node_class, 'gf_left_half' ) ) {
											$node->setAttribute( 'class', $node_class . ' gf_left_half' );
										}
										break;
									case ( preg_match( '/2007.*|2009.*/', $field_id ) ? true : false ):
										if ( false === strpos( $node_class, 'gf_right_half' ) ) {
											$node->setAttribute( 'class', $node_class . ' gf_right_half' );
										}
										break;
									case ( preg_match( '/2001.*|3001.*/', $field_id ) ? true : false ):
										if ( false === strpos( $node_class, 'gf_left_third' ) ) {
											$node->setAttribute( 'class', $node_class . ' gf_left_third' );
										}
										break;
									case ( preg_match( '/3002.*/', $field_id ) ? true : false ):
										if ( false === strpos( $node_class, 'gf_middle_third' ) ) {
											$node->setAttribute( 'class', $node_class . ' gf_middle_third' );
										}
										break;
									case ( preg_match( '/1005.*|2012.*|3004.*/', $field_id ) ? true : false ):
										if ( false === strpos( $node_class, 'gf_hidden' ) ) {
											$node->setAttribute( 'class', $node_class . ' gf_hidden' );
										}
										break;
								}
							}
						} else {
							$div   = $wrapper->getElementsByTagName( 'div' );
							$input = ( isset( $div ) && 0 < count( $div ) ) ? $div[0]->firstChild : null;

							if ( isset( $input ) ) {
								$field = $input->getAttribute( 'id' );
								if ( ! empty( $field ) && '' !== $field ) {
									$ids = explode( '_', $field );
									if ( 0 < count( $ids ) ) {
										$field_id = $ids[ count( $ids ) - 1 ];
										switch ( $field_id ) {
											case ( preg_match( '/1004.*/', $field_id ) ? true : false ):
												if ( false === strpos( $node_class, 'column' ) ) {
													$node->setAttribute( 'class', $node_class . ' column large-2 horizontal' );
												}
												break;
											case ( preg_match( '/2010.*/', $field_id ) ? true : false ):
												if ( false === strpos( $node_class, 'gf_middle_third' ) ) {
													$node->setAttribute( 'class', $node_class . ' gf_middle_third horizontal' );
												}
												break;
											case ( preg_match( '/2011.*|3003.*/', $field_id ) ? true : false ):
												if ( false === strpos( $node_class, 'gf_right_third' ) ) {
													$node->setAttribute( 'class', $node_class . ' gf_right_third horizontal' );
												}
												break;
										}
									}
								}
							}
						}
					}
				}
				$content = Utilities::export_html( $doc );
				unset( $doc );
			}
		}
		return $content;
	}

	/**
	 * Locates the index of a field by the field property and it's value.
	 *
	 * @param array  $fields The array of fields to look through.
	 * @param string $name The field name to look for.
	 * @param mixed  $value The value of the field to match against.
	 *
	 * @return array|false Return the index of the field or false if not found.
	 */
	protected function find_field_by_key( $fields, $name, $value ) {
		foreach ( $fields as $key => $field ) {
			if ( strval( $value ) === strval( $field[ $name ] ) ) {
				return array( $key, $field );
			}
		}
		return false;
	}


	/**
	 * Pupolate the form fields based on contact
	 *
	 * @since 1.0
	 * @param array $form The form object currently being processed.
	 * @param array $feed The feed object to be processed.
	 * @return object
	 */
	protected function setupContact( $form, $feed, $field_values ) {
		switch ( rgars( $feed, 'meta/contact_type' ) ) {
			case 'Individual':
				$form = $this->setup_individual( $form, $feed, $field_values );
				break;
			case 'Organization':
				$form = $this->setup_organization( $form, $feed );
				break;
		}
		return $form;
	}


	/**
	 * Pupolate the form fields based on Individual contact
	 *
	 * @since 1.0
	 * @param array $form The form object currently being processed.
	 * @param array $feed The feed object to be processed.
	 * @return object
	 */
	protected function setup_individual( $form, $feed, $field_values ) {
		$contact_id = $this->get_current_user( $form, $feed );
		$contact    = false;
		if ( false !== $contact_id ) {
			$contact = $this->_civicrm_api->get_contact_by_id( $contact_id );
		}
		$individual_field_mapping_values = array();
		$individual_field_mappings       = rgars( $feed, 'meta/individual_standard_fields' );

		if ( is_array( $individual_field_mappings ) ) {
			$individual_field_mapping_values = array_column( $individual_field_mappings, 'value' );
		}

		$name_id = explode( '.', rgars( $feed, 'meta/individual_required_fields_first_name' ) )[0];
		foreach ( $form['fields'] as &$field ) {
			switch ( $field['id'] ) {
				case (int) rgars( $feed, 'meta/individual_required_fields_first_name' ):
					if ( isset( $field['inputs'] ) && is_array( $field['inputs'] ) ) {
						$field['inputs'] = $this->populate_name_input_field( $field['inputs'], $feed, $contact );
					} else {
						$field['defaultValue'] = ( false !== $contact ) ? $contact['first_name'] : '';
					}
					break;
				case (int) rgars( $feed, 'meta/individual_required_fields_first_name' ):
					$field['defaultValue'] = ( false !== $contact ) ? $contact['first_name'] : '';
					break;
				case (int) rgars( $feed, 'meta/individual_required_fields_middle_name' ):
					$field['defaultValue'] = ( false !== $contact ) ? $contact['middle_name'] : '';
					break;
				case (int) rgars( $feed, 'meta/individual_required_fields_last_name' ):
					$field['defaultValue'] = ( false !== $contact ) ? $contact['last_name'] : '';
					break;
				case (int) rgars( $feed, 'meta/indvidial_phone_number_repeater', 0 ):
					if ( 1 === (int) rgars( $feed, 'meta/individual_use_phone_numbers_enabled' ) ) {
						$this->create_phone_number_fields( $field, $field_values );
					}
					break;
				case (int) rgars( $feed, 'meta/individual_address_repeater', 0 ):
					if ( 1 === (int) rgars( $feed, 'meta/individual_use_address_enabled' ) ) {
						$this->create_address_fields( $field, $field_values );
					}
					break;
				case (int) rgars( $feed, 'meta/individual_email_repeater', 0 ):
					if ( 1 === (int) rgars( $feed, 'meta/individual_use_email_enabled' ) ) {
						$this->create_email_fields( $field, $field_values );
					}
					break;
			}

			if ( in_array( $field['id'], $individual_field_mapping_values ) ) {
				$this->populate_field_data_by_mapping( $field, $individual_field_mappings, $contact );
			}
		}
		unset( $field );
		return $form;
	}


	/**
	 * Populate the form fields based on organization contact
	 *
	 * @since 1.0
	 * @param array $form The form object currently being processed.
	 * @param array $feed The feed object to be processed.
	 * @return object
	 */
	protected function setup_organization( $form, $feed ) {
		$contact_id    = $this->get_current_user( $form, $feed );
		$contact       = false;
		$org_id        = null;
		$org_type      = null;
		$organization  = null;
		$org_addresses = null;

		if ( false !== $contact_id ) {
			$contact = $this->_civicrm_api->get_contact_by_id( $contact_id );
		}

		$organization_field_mapping_values = array();
		$organization_field_mappings       = rgars( $feed, 'meta/organization_standard_fields' );
		$organization_enable_address       = ( 1 === (int) rgars( $feed, 'meta/organization_enable_address_fields_enabled' ) ) ? true : false;

		if ( is_array( $organization_field_mappings ) ) {
			$organization_field_mapping_values = array_column( rgars( $feed, 'meta/organization_standard_fields' ), 'value' );
		}

		if ( false !== $contact && 0 < count( $contact['contact_org'] ) ) {
			$relation_to = rgars( $feed, 'meta/relation_to' );
			if ( null !== $relation_to ) {
				$relation_type = rgars( $feed, 'meta/relation_type' );
				foreach ( $contact['contact_org'] as $org ) {
					if ( array_key_exists( 'relationship_type_id', $org ) && (int) $relation_type === (int) $org['relationship_type_id'] ) {
						$org_id = $org['contact_id_b'];
						break;
					}
				}
			}
		}

		foreach ( $form['fields'] as &$field ) {
			switch ( $field['id'] ) {
				case (int) rgars( $feed, 'meta/organization_select' ):
					$field['choices'] = $this->get_organizations();
					if ( false !== $contact && null !== $org_id ) {
						$field['defaultValue'] = $org_id;
					}
					break;
				case (int) rgars( $feed, 'meta/organization_address' ):
					if ( true === $organization_enable_address && null !== $org_addresses && is_array( $org_addresses ) ){

					}
					break;
			}

			if ( in_array( $field['id'], $organization_field_mapping_values ) && null !== $org_id ) {
				$this->populate_field_data_by_mapping( $field, $organization_field_mappings, $org_id );
			}
		}
		unset( $field );
		return $form;
	}

	/**
	 * Update Name field inputs with contact informaiton.
	 *
	 * @since 1.0
	 * @param array $inputs The name field inputs.
	 * @param array $feed The current feed to map to.
	 * @param array $contact The current contact to set the values from.
	 *
	 * @return array
	 */
	protected function populate_name_input_field( $inputs, $feed, $contact ) {
		foreach ( $inputs as &$input ) {
			switch ( floatval( $input['id'] ) ) {
				case floatval( $feed['meta']['individual_required_fields_first_name'] ):
					$input['defaultValue'] = $contact['first_name'];
					break;
				case floatval( $feed['meta']['individual_required_fields_middle_name'] ):
					$input['defaultValue'] = $contact['middle_name'];
					break;
				case floatval( $feed['meta']['individual_required_fields_last_name'] ):
					$input['defaultValue'] = $contact['last_name'];
					break;
			}
		}
		unset( $input );
		return $inputs;
	}

	/**
	 * Pupolate the field with the value specified.
	 *
	 * @since 1.0
	 * @param array   $field The form field to set the value to.
	 * @param decimal $field_id The filed ID to verify or find if multiple input fields.
	 * @param mixed   $value The values to set to the field.
	 *
	 * @return object
	 */
	protected function populate_field_data( $field, $field_id, $value ) {
		if ( is_array( $field->inputs ) ) {
			foreach ( $field->inputs as $input ) {
				if ( $field_id === $input['id'] ) {
					$input['name'] = $value;
					break;
				}
			}
		}

		return $field;
	}

	/**
	 * Populate mapped fields.
	 *
	 * @since 1.0
	 *
	 * @param array      $field The field to map data to.
	 * @param array      $mappings The current mapptings to check through.
	 * @param array|bool $contact_data The contact to map from.
	 */
	protected function populate_field_data_by_mapping( &$field, $mappings, $contact_data ) {
		foreach ( $mappings as $mapping ) {
			if ( intval( $field['id'] ) === intval( $mapping['value'] ) ) {
				if ( in_array( $field->type, array( 'select', 'multiselect', 'radio', 'checkbox' ) ) ) {
					if ( 0 === strpos( $mapping['key'], 'custom_' ) ) {
						$custom_key        = explode( '_', $mapping['key'] )[1];
						$custom_field_type = $this->get_custom_field_type( intval( $custom_key ) );
						$data              = false;
						$default_country   = null;
						$default_state     = null;
						if ( class_exists('CRM_Core_Config') ) {
							$config        = \CRM_Core_Config::singleton();
							$default_state = $config->defaultContactStateProvince;
						}
						if ( class_exists('Civi') ) {
							$default_country = \Civi::settings()->get('defaultContactCountry');
						}
						if ( null !== $custom_field_type['data_type'] ) {

							if ( false !== $contact_data ) {
								$item = array_search( $custom_key, array_column( $contact_data['custom_data'], 'id' ) );
								if ( false !== $item ) {
									$data = $contact_data['custom_data'][ $item ]['0'];
								}
							}
							switch ( $custom_field_type['data_type'] ) {
								case 'StateProvince':
									$state_options = $this->get_state_choices();
									if ( 'multiselect' === $field->type ) {
										unset( $state_options[0] );
									}
									$field['choices'] = $state_options;
									if ( false !== $data && '' !== $data ) {
										$values = array();
										foreach ( array_column( $field['choices'], 'value' ) as $choice_value ) {
											if ( is_array( $data ) ) {
												foreach( $data as $child_value ) {
													if ( Utilities::str_ends_with( $choice_value, $child_value ) ) {
														$values[] = $choice_value;
													}
												}
											} else {
												if ( Utilities::str_ends_with( $choice_value, $data ) ) {
													$values[] = $choice_value;
												}
											}
										}
										if ( 0 <= count( $values ) ) {
											$field['defaultValue'] = ( 1 === count( $values ) ) ? $values[0] : implode( ',', $values );
										}
									} else if ( null !== $default_country && null !== $default_state && 'multiselect' !== $field->type ) {
										$field['defaultValue'] = $default_country . '_' . $default_state;
									}
									break;
								case 'Country':
									$field['choices'] = $this->get_country_choices();
									if ( false !== $data && '' !== $data ) {
										$field['defaultValue'] = $data;
									} else if ( null !== $default_country ) {
										$field['defaultValue'] = $default_country ;
									}
									break;
								default:
									if ( array_key_exists( 'option_group_id', $custom_field_type ) ) {
										$choices = $this->get_option_value_options( $custom_field_type['option_group_id'], true );
										if ( 0 < count( $choices ) ) {
											if ( 'multiselect' === $field->type ) {
												unset( $choices[0] );
											}
											$field['choices'] = $choices;
											if ( false !== $data ) {
												$field['defaultValue'] = ( is_array( $data ) ) ? implode( ',', $data ) : $data;
											}
										}
									} else {
										if ( false !== $data ) {
											$field['defaultValue'] = ( is_array( $data ) ) ? implode( ',', $data ) : $data;
										}
									}
									break;
							}
						}
					} else {
						$field_options = $this->get_field_options( $mapping['key'], $field );
						if ( ( null !== $field_options ) && is_array( $field_options ) ) {
							$field['choices'] = $field_options;
							$field['defaultValue'] = ( false !== $contact_data ) ? $contact_data[ $mapping['key'] ] : '';
						}
					}
				} else {
					if ( false !== $contact_data && array_key_exists( $mapping['key'], $contact_data ) ) {
						$data = $contact_data[ $mapping['key'] ];

						$field['defaultValue'] = is_array( $data ) ? implode( ',', $data ) : $data;
					} elseif ( false !== $contact_data && array_key_exists( 'custom_data', $contact_data ) ) {
						if ( 0 === strpos( $mapping['key'], 'custom_' ) ) {
							$custom_key = explode( '_', $mapping['key'] )[1];
							$item = array_search( $custom_key, array_column( $contact_data['custom_data'], 'id' ) );
							if ( false !== $item ) {
								$data = $contact_data['custom_data'][ $item ]['0'];
								if ( '' !== $data ) {
									$custom_field_type = $this->get_custom_field_type( intval( $custom_key ) );
									if ( 'Date' === $custom_field_type['data_type'] ) {
										$date = date_parse( $data );
										if ( is_array( $date ) && 0 === $date['error_count'] ) {
											$field['defaultValue'] = implode( '/', array( rgar( $date, 'year' ), rgar( $date, 'month' ), rgar( $date, 'day' ) ) );
										}
									} else {
										$field['defaultValue'] = ( is_array( $data ) ) ? implode( ',', $data ) : $data;
									}
								}
							}
						}
					}
				}
			}
		}
	}

	/**
	 * Gets the collection of country choices for address field.
	 *
	 * @return array The array of country choices.
	 */
	public function get_country_choices() {
		$country_cache = get_transient( 'gf_civicrm_get_country_choices' );
		if ( $country_cache ) {
			return $country_cache;
		}

		$choices = array();
		try {
			$country_data = \civicrm_api3(
				'Country',
				'get',
				array(
					'sequential'       => 1,
					'check_permissions' => false,
					'options'          => array(
						'limit' => 0,
						'sort'  => 'name',
						'cache' => '100 minutes',
					),
				)
			);
		} catch ( \CiviCRM_API3_Exception $e ) {
			\Civi::log()->debug( $e->getMessage() );
			return $choices;
		}

		if ( false !== $country_data['is_error'] && 0 < $country_data['count'] ) {
			$default_country = null;
			$country_set     = false;
			if ( class_exists('CRM_Core_Config') ) {
				$default_country = \Civi::settings()->get('defaultContactCountry');
			}
			foreach ( $country_data['values'] as $country ) {
				$selected = false;
				if ( null !== $default_country && $country['id'] == $default_country ) {
					$selected = true;
					$country_set = true;
				}
				$choices[] = array(
					'text'       => $country['name'],
					'value'      => $country['id'],
					'isSelected' => false,
				);
			}
			array_unshift(
				$choices,
				array(
					'text'       => 'Select Country',
					'value'      => '',
					'isSelected' => true,
				)
			);
		}

		if ( set_transient( 'gf_civicrm_get_country_choices', $choices, DAY_IN_SECONDS ) ) {
			return get_transient( 'gf_civicrm_get_country_choices' );
		}
		return $choices;
	}

	/**
	 * Gets the collection of state/province choices for address field.
	 *
	 * @return array The array of state/province choices.
	 */
	public function get_state_choices() {
		$states_cache = get_transient( 'gf_civicrm_get_state_choices' );
		if ( $states_cache ) {
			return $states_cache;
		}

		$choices = array();
		try {
			$state_data = \civicrm_api3(
				'StateProvince',
				'get',
				array(
					'sequential'       => 1,
					'check_permissions' => false,
					'options'          => array(
						'limit' => 0,
						'sort'  => 'name',
						'cache' => '100 minutes',
					),
				)
			);
		} catch ( \CiviCRM_API3_Exception $e ) {
			\Civi::log()->debug( $e->getMessage() );
			return $choices;
		}

		if ( false !== $state_data['is_error'] && 0 < $state_data['count'] ) {
			$default_state = null;
			$state_set     = false;
			if ( class_exists('CRM_Core_Config') ) {
				$config          = \CRM_Core_Config::singleton();
				$default_state   = $config->defaultContactStateProvince;
			}
			foreach ( $state_data['values'] as $state ) {
				$selected = false;
				if ( null !== $default_state && $state['id'] == $default_state ) {
					$selected = true;
					$state_set = true;
				}
				$choices[] = array(
					'text'       => $state['name'],
					'value'      => $state['country_id'] . '_' . $state['id'],
					'isSelected' => false,
				);
			}
			array_unshift(
				$choices,
				array(
					'text'       => 'Select State/Province',
					'value'      => '',
					'isSelected' => true,
				)
			);
		}

		if ( set_transient( 'gf_civicrm_get_state_choices', $choices, DAY_IN_SECONDS ) ) {
			return get_transient( 'gf_civicrm_get_state_choices' );
		}
		return $choices;
	}

	/**
	 * Get the data type for a custom field.
	 *
	 * @param int $field_id The ID of the custom field to check the data type for.
	 *
	 * @return null|string The data type value for the custom field.
	 */
	public function get_custom_field_type( $field_id ) {
		$custom_field_cache = get_transient( 'gf_civicrm_custom_field_' . $field_id );
		if ( $custom_field_cache ) {
			return $custom_field_cache;
		}

		try {
			$custom_field = \civicrm_api3(
				'CustomField',
				'get',
				array(
					'sequential'      => 1,
					'chk_permissions' => 0,
					'return'          => array( 'data_type', 'option_group_id', 'html_type' ),
					'id'              => $field_id,
					'options'         => array(
						'limit' => 0,
						'cache' => '100 minutes',
					),
				)
			);
		} catch ( \CiviCRM_API3_Exception $e ) {
			\Civi::log()->debug( $e->getMessage() );
			return null;
		}

		if ( false !== $custom_field['is_error'] && 0 < $custom_field['count'] ) {

			if ( set_transient( 'gf_civicrm_custom_field_' . $field_id, $custom_field['values'][0], DAY_IN_SECONDS ) ) {
				return get_transient( 'gf_civicrm_custom_field_' . $field_id );
			}

			return $custom_field['values'][0];
		}
		return null;
	}


	/**
	 * Get the data type for all custom fields.
	 *
	 * @return null|array The data type value for the custom field.
	 */
	public function get_custom_fields() {
		$custom_field_cache = get_transient( 'gf_civicrm_all_custom_fields' );
		if ( $custom_field_cache ) {
			return $custom_field_cache;
		}

		try {
			$custom_field = \civicrm_api3(
				'CustomField',
				'get',
				array(
					'sequential'      => 1,
					'chk_permissions' => 0,
					'return'          => array( 'data_type', 'option_group_id', 'html_type' ),
					'options'         => array(
						'cache' => '100 minutes',
						'limit' => 0
					),
				)
			);
		} catch ( \CiviCRM_API3_Exception $e ) {
			\Civi::log()->debug( $e->getMessage() );
			return null;
		}

		if ( false !== $custom_field['is_error'] && 0 < $custom_field['count'] ) {

			if ( set_transient( 'gf_civicrm_all_custom_fields', $custom_field['values'], DAY_IN_SECONDS ) ) {
				return get_transient( 'gf_civicrm_all_custom_fields' );
			}

			return $custom_field['values'];
		}
		return null;
	}

	/**
	 * Get the options for a specific field.
	 *
	 * @param int    $field_name The name of the field to get options for.
	 * @param object $form_field The form field we are binding choices to.
	 *
	 * @return null|array The array of options for the built-in field.
	 */
	public function get_field_options( $field_name, $form_field ) {
		$field_options_cache = get_transient( 'gf_civicrm_field_options_' . $field_name );
		if ( $field_options_cache ) {
			return $field_options_cache;
		}

		try {
			$field_options = \civicrm_api3(
				'Contact',
				'getoptions',
				array(
					'sequential'      => 1,
					'chk_permissions' => 0,
					'field'           => $field_name,
					'options'         => array(
						'limit' => 0,
						'cache' => '100 minutes',
					),
				)
			);
		} catch ( \CiviCRM_API3_Exception $e ) {
			\Civi::log()->debug( $e->getMessage() );
			return null;
		}

		if ( false !== $field_options['is_error'] && 0 < $field_options['count'] ) {
			$options = array();
			foreach ( $field_options['values'] as $option ) {
				$options[] = array(
					'text'  => $option['value'],
					'value' => $option['key'],
				);
			}
			if ( 'select' === $form_field->type ) {
				array_unshift(
					$options,
					array(
						'text'  => 'Select',
						'value' => '',
					)
				);
			}

			if ( set_transient( 'gf_civicrm_field_options_' . $field_name, $options, DAY_IN_SECONDS ) ) {
				return get_transient( 'gf_civicrm_field_options_' . $field_name );
			}

			return $options;
		}
		return null;
	}

	/**
	 * Gets the collection of organizations choices for company field.
	 *
	 * @return array The array of organization type choices.
	 */
	protected function get_organizations() {
		$org_cache = get_transient( 'gf_civicrm_get_organizations' );
		if ( $org_cache ) {
			return $org_cache;
		}

		$choices = array();
		try {
			$org_data = \civicrm_api3(
				'Contact',
				'get',
				array(
					'contact_type'     => 'Organization',
					'sequential'       => 1,
					'check_permissions' => false,
					'options'          => array(
						'limit' => 0,
						'sort'  => 'sort_name',
					),
				)
			);
		} catch ( \CiviCRM_API3_Exception $e ) {
			\Civi::log()->debug( $e->getMessage() );
			return $choices;
		}

		if ( false !== $org_data['is_error'] && 0 < $org_data['count'] ) {
			foreach ( $org_data['values'] as $organization ) {
				$choices[] = array(
					'text'       => $organization['display_name'],
					'value'      => $organization['contact_id'],
					'isSelected' => false,
				);
			}
		}
		array_unshift(
			$choices,
			array(
				'text' => 'Select',
				'value' => '',
			),
			array(
				'text'  => 'Other - Enter your own',
				'value' => 'other',
			),
			array(
				'text'  => 'None',
				'value' => 'none',
			)
		);

		if ( set_transient( 'gf_civicrm_get_organizations', $choices, DAY_IN_SECONDS ) ) {
			return get_transient( 'gf_civicrm_get_organizations' );
		}
		return $choices;
	}

	/**
	 * Populate the form fields based on membership
	 *
	 * @since 1.0
	 * @param array $form The form object currently being processed.
	 * @param array $feed The feed object to be processed.
	 * @return void
	 */
	protected function setupMembership( $form, $feed ) {
	}

	/**
	 * Gets the current CiviCRM user if logged in.
	 *
	 * @since 1.0
	 *
	 * @param object $form The current form.
	 * @param array  $feed The feed settings.
	 *
	 * @return int|false
	 */
	protected function get_current_user( $form, $feed ) {
		$lead = null;

		// Check to see if we want to allow using the checksum and cid from querystring.
		$use_cs_as_contact = (bool) rgars( $feed, 'meta/populate_civicrm_checksum_enabled' );

		// Try and get the contact id from url.
		$cs_contact = $this->get_check_sum_user();

		if ( $use_cs_as_contact && null !== $cs_contact && is_int( $cs_contact ) ) {
			if ( null === $lead ) {
				$lead = \RGFormsModel::create_lead( $form );
			}

			// See if the $feed requires logic to populate from checksum.
			$populate_checksum_logic = (bool) rgars( $feed, 'meta/populate_civicrm_checksum_logic_condition_conditional_logic' );

			// If logic is not enabled, return contact id.
			if ( ! $populate_checksum_logic ) {
				return $cs_contact;
			}

			// If logic is enabled, check if condition is met and return contact id.
			if ( $this->is_populate_civicrm_condition_met( $feed, 'populate_civicrm_checksum_logic', $form, $lead ) ) {
				return $cs_contact;
			}
		}

		// Check to see if we want to populate from a WP user.
		$use_wp_as_contact = (bool) rgars( $feed, 'meta/populate_wp_user_enabled' );

		// Try and get a CiviCRM contact from WP user.
		$wp_contact = $this->get_current_wp_user();

		if ( $use_wp_as_contact && null !== $wp_contact && is_int( $wp_contact ) ) {
			if ( null === $lead ) {
				$lead = \RGFormsModel::create_lead( $form );
			}
			// See if the $feed requires logic to populate from WP user.
			$populate_wp_logic = (bool) rgars( $feed, 'meta/populate_wp_user_logic_condition_conditional_logic' );

			// If logic is not enabled, return contact id.
			if ( ! $populate_wp_logic ) {
				return $wp_contact;
			}

			// If logic is enabled, check if condition is met and return contact id.
			if ( $this->is_populate_civicrm_condition_met( $feed, 'populate_wp_user_logic', $form, $lead ) ) {
				return $wp_contact;
			}
		}

		return false;
	}

	/**
	 * Try and get a CiviCRM contact by the current WP user.
	 *
	 * @return bool|int Returns a contact id or false.
	 */
	protected function get_current_wp_user() {
		$user       = \wp_get_current_user();
		$wp_contact = null;

		if ( $user->exists() ) {
			$result = $this->_civicrm_api->get_contact_by_user_id( $user->ID );

			if ( false !== $result ) {
				if ( array_key_exists( 'values', $result ) ) {
					$result = $result['values'][0];
				}

				if ( array_key_exists( 'contact_id', $result ) ) {
					$wp_contact = (int) $result['contact_id'];
				}
			}
		}

		return $wp_contact;
	}

	/**
	 * Check the url to see if there is a checksum and cid value.
	 *
	 * @return boo|int Returns a contact id or false.
	 */
	protected function get_check_sum_user() {
		$temp_id       = \CRM_Utils_Request::retrieve( 'cid', 'Positive' );
		$user_checksum = \CRM_Utils_Request::retrieve( 'cs', 'String' );
		$cs_contact    = null;

		// check if this is a checksum authentication.
		if ( $user_checksum ) {
			// check for anonymous user.
			$valid_user = \CRM_Contact_BAO_Contact_Utils::validChecksum( $temp_id, $user_checksum );
			if ( $valid_user ) {
				$cs_contact = $temp_id;
			}
		}
		return $cs_contact;
	}

	/**
	 * Get the primary email address for a contact.
	 *
	 * @since 1.0
	 * @param int $contact_id The ID of the contact to locate email address for.
	 *
	 * @return array|null The primary email address for a contact and email type.
	 */
	protected function get_contact_emails( $contact_id ) {
		$email_cache = get_transient( 'gf_civicrm_get_contact_emails_' . $contact_id );
		if ( $email_cache ) {
			return $email_cache;
		}

		$emails = null;

		try {
			$email_data = \civicrm_api3(
				'Email',
				'get',
				array(
					'sequential'       => 1,
					'contact_id'       => $contact_id,
					'check_permissions' => false,
				)
			);
		} catch ( \CiviCRM_API3_Exception $e ) {
			\Civi::log()->debug( $e->getMessage() );
			return null;
		}

		if ( false !== $email_data['is_error'] && 0 < $email_data['count'] ) {
			$emails = $email_data['values'];
		} else {
			return null;
		}

		if ( set_transient( 'gf_civicrm_get_contact_emails_' . $contact_id, $emails, DAY_IN_SECONDS ) ) {
			return get_transient( 'gf_civicrm_get_contact_emails_' . $contact_id );
		}
		return $emails;
	}

	/**
	 * Get the contact phone numbers.
	 *
	 * @since 1.0
	 * @param int $contact_id The ID of the contact to locate phone numbers for.
	 *
	 * @return array|null The phone numbers for a contact.
	 */
	protected function get_contact_phone_numbers( $contact_id ) {
		$phone_numbers_cache = get_transient( 'gf_civicrm_get_contact_phone_numbers_' . $contact_id );
		if ( $phone_numbers_cache ) {
			return $phone_numbers_cache;
		}

		$contact_phone_numbers = null;

		try {
			$phone_data = \civicrm_api3(
				'Phone',
				'get',
				array(
					'sequential'       => 1,
					'contact_id'       => $contact_id,
					'check_permissions' => false,
				)
			);
		} catch ( \CiviCRM_API3_Exception $e ) {
			\Civi::log()->debug( $e->getMessage() );
			return null;
		}

		if ( false !== $phone_data['is_error'] && 0 < $phone_data['count'] ) {
			$contact_phone_numbers = $phone_data['values'];
		} else {
			return null;
		}

		if ( set_transient( 'gf_civicrm_get_contact_phone_numbers_' . $contact_id, $contact_phone_numbers, DAY_IN_SECONDS ) ) {
			return get_transient( 'gf_civicrm_get_contact_phone_numbers_' . $contact_id );
		}
		return $contact_phone_numbers;
	}

	/**
	 * Get the contact addresses.
	 *
	 * @since 1.0
	 * @param int $contact_id The ID of the contact to locate mailing address(es) for.
	 *
	 * @return array|null The addresses for a contact.
	 */
	protected function get_contact_addresses( $contact_id ) {
		$address_cache = get_transient( 'gf_civicrm_get_contact_addresses_' . $contact_id );

		if ( $address_cache ) {
			return $address_cache;
		}

		$contact_address = null;

		try {
			$address_data = \civicrm_api3(
				'Address',
				'get',
				array(
					'sequential'       => 1,
					'contact_id'       => $contact_id,
					'check_permissions' => false,
				)
			);
		} catch ( \CiviCRM_API3_Exception $e ) {
			\Civi::log()->debug( $e->getMessage() );
			return null;
		}

		if ( false !== $address_data['is_error'] && 0 < $address_data['count'] ) {
			$contact_address = $address_data['values'];
		} else {
			return null;
		}

		if ( set_transient( 'gf_civicrm_get_contact_addresses_' . $contact_id, $contact_address, DAY_IN_SECONDS ) ) {
			return get_transient( 'gf_civicrm_get_contact_addresses_' . $contact_id );
		}
		return $contact_address;
	}

	/**
	 * Renders and initializes a checkbox field that displays a text field when checked based on the $field array.
	 *
	 * @since 1.0
	 * @access public
	 * @param array $field - Field array containing the configuration options of this field.
	 * @param bool  $echo  = true - true to echo the output to the screen, false to simply return the contents as a string.
	 *
	 * @return string The HTML for the field
	 */
	public function settings_checkbox_and_text( $field, $echo = true ) {

		$field = $this->prepare_settings_checkbox_and_text( $field );

		$checkbox_field = $field['checkbox'];
		$text_field     = $field['text'];

		$is_enabled = $this->get_setting( $checkbox_field['name'] );

		// get markup.

		$html = sprintf(
			'%s <span id="%s" class="%s">%s %s</span>',
			$this->settings_checkbox( $checkbox_field, false ),
			$text_field['name'] . 'Span',
			$is_enabled ? '' : 'gf_invisible',
			$this->settings_text( $text_field, false ),
			$this->maybe_get_tooltip( $text_field )
		);

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Define the markup for the populate_civicrm_condition type field.
	 *
	 * @since 1.0
	 * @param array     $field The field properties.
	 * @param bool|true $echo Should the setting markup be echoed.
	 */
	public function settings_populate_civicrm_condition( $field, $echo = true ) {

		$conditional_logic = $this->get_populate_civicrm_condition_conditional_logic( $field );
		$checkbox_field    = $this->get_populate_civicrm_condition_checkbox( $field );

		$hidden_field   = $this->get_populate_civicrm_condition_hidden_field( $field );
		$instructions   = isset( $field['instructions'] ) ? $field['instructions'] : esc_html__( 'Conditional Logic', 'gf_civicrm_addon' );
		$condition_name = $field['name'] . '_condition';

		$html  = $this->settings_checkbox( $checkbox_field, false );
		$html .= $this->settings_hidden( $hidden_field, false );
		$html .= '<div id="' . $condition_name . '_conditional_logic_container"><!-- dynamically populated --></div>';
		$html .= '<script type="text/javascript"> var ' . $condition_name . ' = new PopulateCiviCRMCondition({' .
			'strings: { objectDescription: "' . esc_attr( $instructions ) . '" },' .
			'logicObject: ' . $conditional_logic . ',' .
			'objectType: "' . $condition_name . '"' .
			'}); </script>';

		if ( $this->field_failed_validation( $field ) ) {
			$html .= $this->get_error_icon( $field );
		}

		if ( $echo ) {
			echo $html;
		}

		return $html;
	}

	/**
	 * Provides the checkbox for the conditional options.
	 *
	 * @since 1.0
	 * @param object $field The settings field this condition is tied to.
	 *
	 * @return object The checkbox settings field.
	 */
	public function get_populate_civicrm_condition_checkbox( $field ) {
		$checkbox_label = isset( $field['checkbox_label'] ) ? $field['checkbox_label'] : esc_html__( 'Enable Condition', 'gf_civicrm_addon' );
		$condition_name = $field['name'] . '_condition';
		$checkbox_field = array(
			'name'    => $condition_name . '_conditional_logic',
			'type'    => 'checkbox',
			'choices' => array(
				array(
					'label' => $checkbox_label,
					'name'  => $condition_name . '_conditional_logic',
				),
			),
			'onclick' => 'ToggleConditionalLogic( false, "' . $condition_name . '" );',
		);

		return $checkbox_field;
	}

	/**
	 * Provides the hidden field that stores the conditions as they are set.
	 *
	 * @since 1.0
	 * @param object $field The settings field this condition is tied to.
	 *
	 * @return object The hidden input settings field.
	 */
	public function get_populate_civicrm_condition_hidden_field( $field ) {
		$conditional_logic = $this->get_populate_civicrm_condition_conditional_logic( $field );
		$condition_name    = $field['name'] . '_condition';
		$hidden_field      = array(
			'name'  => $condition_name . '_conditional_logic_object',
			'value' => $conditional_logic,
		);
		return $hidden_field;
	}

	/**
	 * Provides the conditional logic fields to set the conditions for the $field.
	 *
	 * @since 1.0
	 * @param object $field The settings field this condition is tied to.
	 *
	 * @return object The elements to create the conditional settings.
	 */
	public function get_populate_civicrm_condition_conditional_logic( $field ) {
		$condition_name           = $field['name'] . '_condition';
		$conditional_logic_object = $this->get_setting( $condition_name . '_conditional_logic_object' );
		if ( $conditional_logic_object ) {
			$form_id           = \rgget( 'id' );
			$form              = \GFFormsModel::get_form_meta( $form_id );
			$conditional_logic = \wp_json_encode( \GFFormsModel::trim_conditional_logic_values_from_element( $conditional_logic_object, $form ) );
		} else {
			$conditional_logic = '{}';
		}
		return $conditional_logic;
	}

	/**
	 * Validates the conditional settings field.
	 *
	 * @since 1.0
	 * @param object $field The settigns field to validate.
	 * @param array  $settings The settings data to be saved for the feed.
	 */
	public function validate_populate_civicrm_condition_settings( $field, $settings ) {
		$checkbox_field = $this->get_populate_civicrm_condition_checkbox( $field );
		$condition_name = $field['name'] . '_condition';
		$this->validate_checkbox_settings( $checkbox_field, $settings );

		if ( ! isset( $settings[ $condition_name . '_conditional_logic_object' ] ) ) {
			return;
		}

		$conditional_logic_object = $settings[ $condition_name . '_conditional_logic_object' ];
		if ( ! isset( $conditional_logic_object['conditionalLogic'] ) ) {
			return;
		}
		$conditional_logic      = $conditional_logic_object['conditionalLogic'];
		$conditional_logic_safe = \GFFormsModel::sanitize_conditional_logic( $conditional_logic );
		if ( serialize( $conditional_logic ) !== serialize( $conditional_logic_safe ) ) {
			$this->set_field_error( $field, esc_html__( 'Invalid value', 'gf_civicrm_addon' ) );
		}
	}

	/**
	 * Prpares the Checkbox and Text fields
	 *
	 * @since 1.0
	 * @param array $field The field settings.
	 *
	 * @return array $field
	 */
	public function prepare_settings_checkbox_and_text( $field ) {

		// prepare checkbox.

		$checkbox_input = rgars( $field, 'checkbox' );

		$checkbox_field = array(
			'type'       => 'checkbox',
			'name'       => $field['name'] . 'Enable',
			'label'      => esc_html__( 'Enable', 'gf_civicrm_addon' ),
			'horizontal' => true,
			'value'      => '1',
			'choices'    => false,
			'tooltip'    => false,
		);

		$checkbox_field = wp_parse_args( $checkbox_input, $checkbox_field );

		// prepare text.

		$text_input = rgars( $field, 'text' );

		$text_field = array(
			'name'    => $field['name'] . 'Value',
			'type'    => 'text',
			'class'   => '',
			'tooltip' => false,
		);

		$text_field['class'] .= ' ' . $text_field['name'];

		$text_field = wp_parse_args( $text_input, $text_field );

		// a little more with the checkbox.
		if ( empty( $checkbox_field['choices'] ) ) {
			$checkbox_field['choices'] = array(
				array(
					'name'     => $checkbox_field['name'],
					'label'    => $checkbox_field['label'],
					'onchange' => sprintf(
						"( function( $, elem ) {
							$( elem ).parents( 'td' ).css( 'position', 'relative' );
							if( $( elem ).prop( 'checked' ) ) {
								$( '%1\$s' ).css( 'visibility', 'visible' );
								$( '%1\$s' ).fadeTo( 400, 1 );
							} else {
								$( '%1\$s' ).fadeTo( 400, 0, function(){
									$( '%1\$s' ).css( 'visibility', 'hidden' );
								} );
							}
						} )( jQuery, this );",
						"#{$text_field['name']}Span"
					),
				),
			);
		}

		$field['text']     = $text_field;
		$field['checkbox'] = $checkbox_field;

		return $field;
	}

	/**
	 * Validates the Checkbox and Text field.
	 *
	 * @since 1.0
	 * @param array $field The field to validate.
	 * @param array $settings The settings for the feed.
	 *
	 * @return void
	 */
	public function validate_checkbox_and_text_settings( $field, $settings ) {
		$field = $this->prepare_settings_checkbox_and_text( $field );

		$checkbox_field = $field['checkbox'];
		$text_field     = $field['text'];

		$this->validate_checkbox_settings( $checkbox_field, $settings );
		$this->validate_text_settings( $text_field, $settings );
	}

	/**
	 * Get options for specified type.
	 *
	 * @since 1.0
	 * @param string $type The data type to lookup the options on.
	 * @param string $field_name The name of the field for the type to get the options for.
	 * @param bool   $add_empty Whether to add an empty "Select" option to the list.
	 *
	 * @return array The options for the data type.
	 */
	protected function get_data_type_options( $type, $field_name, $add_empty = false ) {
		$type_options_cache = get_transient( 'gf_civicrm_get_data_type_options_' . $type . '_' . $field_name );
		if ( $type_options_cache ) {
			return $type_options_cache;
		}

		try {
			$type_option_items = \civicrm_api3(
				$type,
				'getoptions',
				array(
					'sequential' => 1,
					'field'      => $field_name,
				)
			);
		} catch ( \CiviCRM_API3_Exception $e ) {
			\Civi::log()->debug( $e->getMessage() );
			return array();
		}

		$type_options = array();
		if ( false !== $type_option_items['is_error'] && 0 < $type_option_items['count'] ) {
			foreach ( $type_option_items['values'] as $type_item ) {
				$type_options[] = $type_item['value'] . '|' . $type_item['key'];
			}
		}

		if ( $add_empty && 0 < count( $type_options ) ) {
			array_unshift(
				$type_options,
				array( 'Select|' )
			);
		}

		if ( set_transient( 'gf_civicrm_get_data_type_options_' . $type . '_' . $field_name, $type_options, DAY_IN_SECONDS ) ) {
			return get_transient( 'gf_civicrm_get_data_type_options_' . $type . '_' . $field_name );
		}
		return $type_options;
	}

	/**
	 * Get the values from an Option Group
	 *
	 * @param int  $group_id The group id that we are getting the values for.
	 * @param bool $add_empty Whether to add an empty "select" option.
	 *
	 * @return array The options for the Option Value.
	 */
	protected function get_option_value_options( $group_id, $add_empty = false ) {
		$option_values_cache = get_transient( 'gf_civicrm_option_values_' . $group_id );
		if ( $option_values_cache ) {
			return $option_values_cache;
		}

		try {
			$option_values = \civicrm_api3(
				'OptionGroup',
				'get',
				array(
					'sequential'          => 1,
					'chk_permissions'     => 0,
					'id'                  => $group_id,
					'api.OptionValue.get' => array(
						'option_group_id' => '$value.name',
						'is_active'       => 1,
						'options'         => array(
							'limit' => 0,
							'sort'  => 'weight',
						),
					),
				)
			);
		} catch ( \CiviCRM_API3_Exception $e ) {
			\Civi::log()->debug( $e->getMessage() );
			return array();
		}

		$value_options = array();
		if ( 0 === $option_values['is_error'] && 0 < $option_values['count'] ) {
			if ( false !== $option_values['values'][0]['api.OptionValue.get']['is_error'] && 0 < $option_values['values'][0]['api.OptionValue.get']['count'] ) {
				foreach ( $option_values['values'][0]['api.OptionValue.get']['values'] as $option_value ) {
					$value_options[] = array(
						'text'  => $option_value['label'],
						'value' => $option_value['value'],
					);
				}
			}
		}

		if ( $add_empty && 0 < count( $value_options ) ) {
			array_unshift(
				$value_options,
				array(
					'text'  => 'Select',
					'value' => '',
				)
			);
		}

		if ( set_transient( 'gf_civicrm_option_values_' . $group_id, $value_options, DAY_IN_SECONDS ) ) {
			return get_transient( 'gf_civicrm_option_values_' . $group_id );
		}
		return $value_options;
	}

	/**
	 * Checks to see if the populate CiviCRM conditions are met.
	 *
	 * @param object      $feed The current feed to check settings on.
	 * @param string      $name The setting name to check in the settings.
	 * @param object      $form The current form the validation is to be checked against.
	 * @param object|null $entry The posted form data to validate against if available.
	 *
	 * @return bool Whether the conditions are met.
	 */
	public function is_populate_civicrm_condition_met( $feed, $name, $form, $entry ) {
		$feed_meta            = $feed['meta'];
		$is_condition_enabled = ( (bool) rgar( $feed_meta, $name . '_condition_conditional_logic' ) ) === true;
		$logic                = rgars( $feed_meta, $name . '_condition_conditional_logic_object/conditionalLogic' );

		if ( ! $is_condition_enabled || empty( $logic ) ) {
			return true;
		}

		return \GFCommon::evaluate_conditional_logic( $logic, $form, $entry );
	}
}

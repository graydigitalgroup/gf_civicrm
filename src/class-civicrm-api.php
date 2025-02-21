<?php
/**
 * CiviCRM API integration
 *
 * Used to make the calls to the CiviCRM API
 *
 * @package gf_civicrm_addon
 * @author GrayDigitalGroup
 * @license https://www.gnu.org/licenses/old-licenses/gpl-3.0.html
 */

namespace GF_CiviCRM;

/**
 * The class wrapper to make the API calls to CiviCRM
 *
 * @since 1.0
 */
class CiviCRM_API {

	/**
	 * CiviCRM tax and invoicing settings.
	 *
	 * @since 1.0
	 * @access public
	 * @var array $tax_settings
	 */
	public $tax_settings;

	/**
	 * CiviCRM tax rates.
	 *
	 * @since 1.0
	 * @access public
	 * @var array $tax_rates Holds tax rates in the form of [ <financial_type_id> => <tax_rate> ]
	 */
	public $tax_rates;

	/**
	 * This is the main constructor for this class.
	 */
	public function __construct() {
	}

	/**
	 * Get the UFMatch data for a given WordPress User ID.
	 *
	 * This method optionally allows a Domain ID to be specified.
	 * If no Domain ID is passed, then we default to current Domain ID.
	 * If a Domain ID is passed as a string, then we search all Domain IDs.
	 *
	 * @since 1.0
	 * @param int    $user_id The WP user ID to lookup.
	 * @param string $domain_id The ID of the domain in a multi-site mode.
	 * @return array|bool The UFMarch data on success, or false on failure.
	 */
	public function get_contact_by_user_id( $user_id, $domain_id = '' ) {
		// Bail if CiviCRM is not active.
		if ( ! $this->is_civicrm_initialised() ) {
			return false;
		}

		// Sanity checks.
		if ( ! is_numeric( $user_id ) ) {
			return false;
		}

		// Construct params.
		$params = array(
			'uf_id' => $user_id,
		);

		// If no Domain ID is specified, default to current Domain ID.
		if ( empty( $domain_id ) ) {
			$params['domain_id'] = \CRM_Core_Config::domainID();
		}

		// Maybe add Domain ID if passed as an integer.
		if ( ! empty( $domain_id ) && is_numeric( $domain_id ) ) {
			$params['domain_id'] = $domain_id;
		}

		// Get all UFMatch records via API.
		$result = $this->ufmatch_get( $params );

		// Log and bail on failure.
		if ( isset( $result['is_error'] ) && '1' === $result['is_error'] ) {
			$e     = new \Exception();
			$trace = $e->getTraceAsString();
			error_log(
				print_r(
					array(
						'method'    => __METHOD__,
						'user_id'   => $user_id,
						'params'    => $params,
						'result'    => $result,
						'backtrace' => $trace,
					),
					true
				)
			);
			return false;
		}

		// Return the entry data if there's only one.
		if ( ! empty( $result ) && 1 === $result->count() ) {
			return $result->first();
		}

		// Return the entries array if there are more than one.
		if ( ! empty( $result ) && 1 < $result->count() ) {
			return $result->getArrayCopy();
		}

		// Fall back to false.
		return false;
	}

	/**
	 * Calls the CiviCRM API for a UFMatch.
	 *
	 * @since 1.0
	 * @param array $params The array of parameter filters to use in the API search.
	 * @return array The results from the API call.
	 */
	protected function ufmatch_get( $params ) {
		$ufmatch_service = \Civi\Api4\UFMatch::get( false );
		try {
			foreach ( $params as $key => $value ) {
				$ufmatch_service->addWhere( $key, '=', $value );
			}
		} catch ( \Exception $ex ) {
			error_log( print_r( $ex, true ) );
		}
		return $ufmatch_service->execute();
	}

	/**
	 * Check if CiviCRM is initialised.
	 *
	 * @since 0.1
	 * @since 0.5.4 Moved to this class.
	 *
	 * @return bool True if CiviCRM initialised, false otherwise.
	 */
	public function is_civicrm_initialised() {

		// Init only when CiviCRM is fully installed.
		if ( ! defined( 'CIVICRM_INSTALLED' ) ) {
			return false;
		}
		if ( ! CIVICRM_INSTALLED ) {
			return false;
		}

		// Bail if no CiviCRM init function.
		if ( ! function_exists( '\civicrm_initialize' ) ) {
			return false;
		}

		// Try and initialise CiviCRM.
		return \civicrm_initialize();
	}

	/**
	 * Get CiviCRM settings.
	 *
	 * @since 1.0
	 *
	 * @param str $settings The name of the setting to be returned.
	 * @return array $settings The requested settings.
	 */
	public function get_civicrm_settings( $settings ) {
		return \Civi::settings()->get( $settings );
	}

	/**
	 * Get CiviCRM tax and invoicing settings.
	 *
	 * @since 1.0
	 * @return array $tax_settings
	 */
	public function get_tax_settings() {
		if ( is_array( $this->tax_settings ) ) {
			return $this->tax_settings;
		}

		$this->tax_settings = $this->get_civicrm_settings( 'contribution_invoice_settings' );
		return $this->tax_settings;
	}

	/**
	 * Get CiviCRM tax rates.
	 *
	 * @since 1.0
	 * @return array|bool Array of tax rates in the form of [ <financial_type_id> => <tax_rate> ]
	 */
	public function get_tax_rates() {

		if ( is_array( $this->tax_rates ) ) {
			return $this->tax_rates;
		}

		$tax_financial_accounts = \civicrm_api3(
			'FinancialAccount',
			'get',
			array(
				'sequential'        => 1,
				'is_active'         => 1,
				'is_tax'            => 1,
				'check_permissions' => 0,
			)
		);

		if ( true !== $tax_financial_accounts['is_error'] && 0 < $tax_financial_accounts['count'] ) {
			$this->tax_rates = array();
			foreach ( $tax_financial_accounts['values'] as $financial_account ) {
				$this->tax_rates[] = array(
					$financial_account['id'] => $financial_account['tax_rate'],
				);
			}

			return $this->tax_rates;
		}

		return false;
	}

	/**
	 * Get price sets.
	 *
	 * @since 0.4.4
	 * @return array $price_sets The active price sets with their corresponding price fields and price filed values
	 */
	public function get_price_sets() {
		// Bail if CiviCRM is not active.
		if ( ! $this->is_civicrm_initialised() ) {
			return null;
		}

		// get tax settings.
		$tax_settings = $this->get_tax_settings();
		// get tax rates.
		$tax_rates = $this->get_tax_rates();

		try {
			$result_price_sets = \Civi\Api4\PriceSet::get( false )
			->addWhere( 'is_active', '=', 1 )
			->addWhere( 'is_reserved', '=', 0 )
			->setLimit( 0 )
			->addChain(
				'get_price',
				\Civi\Api4\PriceField::get( false )
				->addWhere( 'price_set_id', '=', '$id' )
				->addWhere( 'is_active', '=', 1 )
				->setLimit( 0 )
			)
			->execute();
		} catch ( \Exception $e ) {
			return array(
				'note' => $e->getMessage(),
				'type' => 'error',
			);
		}

		try {
			$all_price_field_values = \Civi\Api4\PriceFieldValue::get( false )
			->addWhere( 'is_active', '=', 1 )
			->setLimit( 0 )
			->execute();
		} catch ( \Exception $e ) {
			return array(
				'note' => $e->getMessage(),
				'type' => 'error',
			);
		}
		// false if no price field values or price sets.
		if ( $all_price_field_values->count() <= 0 || $result_price_sets->count() <= 0 ) {
			return false;
		}

		$price_field_values = array();
		foreach ( $all_price_field_values as $id => $price_field_value ) {
			$price_field_values[ $id ] = $price_field_value;
		}

		$price_sets = array();
		foreach ( $result_price_sets as $key => $price_set ) {
			$price_set_id              = $price_set['id'];
			$price_set['price_set_id'] = $price_set_id;
			$price_set['price_fields'] = $price_set['get_price'];
			foreach ( $price_set['price_fields'] as $price_field_id => $price_field ) {
				$price_set['price_fields'][ $price_field_id ]['price_field_id'] = $price_field['id'];
				foreach ( $price_field_values as $value_id => $price_field_value ) {
					$price_field_value['price_field_value_id'] = $price_field_value['id'];
					if ( $price_field['id'] === $price_field_value['price_field_id'] ) {
						if ( ( \CRM_Invoicing_Utils::isInvoicingEnabled() === true ) && $tax_rates && array_key_exists( $price_field_value['financial_type_id'], $tax_rates ) ) {
							$price_field_value['tax_rate']   = $tax_rates[ $price_field_value['financial_type_id'] ];
							$price_field_value['tax_amount'] = $this->calculate_percentage( $price_field_value['amount'], $price_field_value['tax_rate'] );
						}
						$price_set['price_fields'][ $price_field_id ]['price_field_values'][ $value_id ] = $price_field_value;
					}
				}
			}
			unset( $price_set['id'], $price_set['get_price'] );
			$price_sets[ $price_set_id ] = $price_set;
		}
		return $price_sets;
	}


	/**
	 * Get cached active price sets with their corresponding price fields and price filed values.
	 *
	 * @since 1.0
	 *
	 * @return array|false $price_sets
	 */
	public function cached_price_sets() {

		$price_sets = $this->get_price_sets();
		if ( $price_sets ) {
			return $price_sets;
		}
		return false;
	}

	/**
	 * Get the contribution pages from CiviCRM
	 *
	 * @since 1.0
	 *
	 * @return array|false $contribution_pages
	 */
	public function get_contribution_pages() {

		$contribution_pages = \Civi\Api4\ContributionPage::get( false )
			->addSelect( 'id', 'title' )
			->addWhere( 'is_active', '=', true )
			->execute();
		if ( $contribution_pages->count() > 0 ) {
			return $contribution_pages;
		}
		return false;
	}

	/**
	 * Get payment processors from CiviCRM
	 *
	 * @since 1.0
	 *
	 * @return array|false $payment_processors
	 */
	public function get_payment_processors() {

		$payment_processors = \Civi\Api4\PaymentProcessor::get( false )
			->addWhere( 'is_active', '=', true )
			->setLimit( 0 )
			->execute();
		if ( $payment_processors->count() > 0 ) {
			return $payment_processors;
		}
		return false;
	}

	/**
	 * Get Option Values based on Option Group ID
	 *
	 * @since 1.0
	 * @param int $option_group_id The option group ID to get the values for.
	 *
	 * @return array|false $option_values
	 */
	public function get_option_values( $option_group_id ) {

		$option_values = \Civi\Api4\OptionValue::get( false )
			->addWhere( 'option_group_id', '=', $option_group_id )
			->addWhere( 'is_active', '=', true )
			->setLimit( 0 )
			->execute();
		if ( $option_values->count() > 0 ) {
			return $option_values;
		}
		return false;
	}

	/**
	 * Get Conbtribution field options based on field type
	 *
	 * @since 1.0
	 * @param string $field_name The field name to get the options for.
	 *
	 * @return array|false $options
	 */
	public function get_contribution_field_options( $field_name ) {

		$fields = \Civi\Api4\Contribution::getFields( false )
			->setLoadOptions(
				array(
					'id',
					'name',
					'label',
					'abbr',
					'description',
					'color',
					'icon',
				)
			)
			->addWhere( 'options', '!=', false )
			->addWhere( 'name', '=', $field_name )
			->execute();
		if ( $fields->count() > 0 ) {
			$options = $fields->first()['options'];
			Utilities::sort_array_by_column( $options, 'id' );

			return $options;
		}
		return false;
	}

	/**
	 * Get Address field options based on field type
	 *
	 * @since 1.0
	 * @param string $field_name The field name to get the options for.
	 *
	 * @return array|false $options
	 */
	public function get_address_field_options( $field_name ) {

		// Bail if CiviCRM is not active.
		if ( ! $this->is_civicrm_initialised() ) {
			return false;
		}

		$fields = \Civi\Api4\Address::getFields( false )
			->setLoadOptions(
				array(
					'id',
					'name',
					'label',
					'abbr',
					'description',
					'color',
					'icon',
				)
			)
			->addWhere( 'options', '!=', false )
			->addWhere( 'name', '=', $field_name )
			->execute();
		if ( $fields->count() > 0 ) {
			$options = $fields->first()['options'];
			Utilities::sort_array_by_column( $options, 'id' );

			return $options;
		}
		return false;
	}

	/**
	 * Get contact based on ID
	 *
	 * @since 1.0
	 * @param int $contact_id The id of the contact to look for.
	 *
	 * @return object|false
	 */
	public function get_contact_by_id( $contact_id ) {

		$contacts = \civicrm_api3(
			'Contact',
			'get',
			array(
				'id'                   => $contact_id,
				'sequential'           => 1,
				'check_permissions'    => 0,
				'api.CustomValue.get'  => array(
					'entity_id' => $contact_id,
				),
				'api.Relationship.get' => array(
					'contact_id_a' => $contact_id,
					'is_active'    => true,
				),
			)
		);
		if ( false !== $contacts['is_error'] && 0 < $contacts['count'] ) {
			$contacts['values'][0]['custom_data'] = $contacts['values'][0]['api.CustomValue.get']['values'];
			unset( $contacts['values'][0]['api.CustomValue.get'] );

			$contacts['values'][0]['contact_org'] = $contacts['values'][0]['api.Relationship.get']['values'];
			unset( $contacts['values'][0]['api.Relationship.get'] );

			return $contacts['values'][0];
		}
		return false;
	}

	/**
	 * Get contact based on Email.
	 *
	 * @since 1.0
	 * @param string $contact_email The email of the contact to look for.
	 *
	 * @return object|false
	 */
	public function get_contact_by_email( $contact_email ) {

		$emails = \Civi\Api4\Email::get( false )
			->addSelect( 'contact_id' )
			->addWhere( 'email', '=', $contact_email )
			->setLimit( 1 )
			->addChain(
				'contact_link',
				\Civi\Api4\Contact::get( false )
					->addWhere( 'id', '=', '$contact_id' ),
				1
			)
			->execute();
		if ( $emails->count() > 0 ) {

			return $emails->first();
		}
		return false;
	}

	/**
	 * Get contact Phone based on ID
	 *
	 * @since 1.0
	 * @param int $contact_id The id of the contact to look for.
	 *
	 * @return object|false
	 */
	public function get_contact_phone_by_id( $contact_id ) {

		$phones = \Civi\Api4\Phone::get( false )
			->addWhere( 'id', '=', $contact_id )
			->setLimit( 1 )
			->execute();
		if ( $phones->count() > 0 ) {
			return $phones;
		}
		return false;
	}

	/**
	 * Get contact Addresses based on ID
	 *
	 * @since 1.0
	 * @param int $contact_id The id of the contact to look for.
	 *
	 * @return object|false
	 */
	public function get_contact_address_by_id( $contact_id ) {
		$addresses = \Civi\Api4\Address::get( false )
			->addWhere( 'id', '=', $contact_id )
			->setLimit( 1 )
			->execute();
		if ( $addresses->count() > 0 ) {
			return $addresses;
		}
		return false;
	}

	/**
	 * Get contact Emails based on ID
	 *
	 * @since 1.0
	 * @param int $contact_id The id of the contact to look for.
	 *
	 * @return object|false
	 */
	public function get_contact_emails_by_id( $contact_id ) {
		$emails = \Civi\Api4\Email::get( false )
			->addWhere( 'id', '=', $contact_id )
			->setLimit( 1 )
			->execute();
		if ( $emails->count() > 0 ) {
			return $emails;
		}
		return false;
	}

	/**
	 * Get all countries
	 *
	 * @since 1.0
	 *
	 * @return object|false
	 */
	public function get_countries() {
		$countries = \Civi\Api4\Country::get( false )
			->setLimit( 0 )
			->execute();
		if ( $countries->count() > 0 ) {
			return $countries;
		}
		return false;
	}

	/**
	 * Get Country based on ID
	 *
	 * @since 1.0
	 * @param int $country_id The id of the country to look for.
	 *
	 * @return object|false
	 */
	public function get_country_by_id( $country_id ) {
		$country = \Civi\Api4\Country::get( false )
			->addWhere( 'id', '=', $country_id )
			->setLimit( 1 )
			->execute();
		if ( $country->count() > 0 ) {
			return $country;
		}
		return false;
	}

	/**
	 * Get all States and Provinces
	 *
	 * @since 1.0
	 *
	 * @return object|false
	 */
	public function get_states_provinces() {
		$states_provinces = \Civi\Api4\StateProvince::get( false )
			->setLimit( 0 )
			->execute();
		if ( $states_provinces->count() > 0 ) {
			return $states_provinces;
		}
		return false;
	}

	/**
	 * Get all States and Provinces based on Country ID
	 *
	 * @since 1.0
	 * @param int $country_id The id of the country to filter on.
	 *
	 * @return object|false
	 */
	public function get_states_provinces_by_country_id( $country_id ) {
		$country = \Civi\Api4\StateProvince::get( false )
			->addWhere( 'country_id', '=', $country_id )
			->setLimit( 0 )
			->execute();
		if ( $country->count() > 0 ) {
			return $country;
		}
		return false;
	}

	/**
	 * Get State/Province based on ID
	 *
	 * @since 1.0
	 * @param int $state_province_id The id of the country to look for.
	 *
	 * @return object|false
	 */
	public function get_state_province_by_id( $state_province_id ) {
		$state_province = \Civi\Api4\StateProvince::get( false )
			->addWhere( 'id', '=', $state_province_id )
			->setLimit( 1 )
			->execute();
		if ( $state_province->count() > 0 ) {
			return $state_province;
		}
		return false;
	}

	/**
	 * Get dedupe rules by a contact type.
	 *
	 * @param string $contact_type The contact type to get the rules for.
	 *
	 * @return array|false
	 */
	public function get_dedupe_rules_by_type( $contact_type ) {
		$dedupe_rule_groups = \Civi\Api4\DedupeRuleGroup::get( false )
			->addWhere( 'contact_type', '=', $contact_type )
			->execute();
		if ( $dedupe_rule_groups->count() > 0 ) {
			return $dedupe_rule_groups;
		}
		return false;
	}

	/**
	 * Get dedupe rule group.
	 *
	 * @param int $rule The rule group ID.
	 *
	 * @return array|false
	 */
	public function get_dedupe_rule_group( $rule ) {
		$dedupe_rule_groups = \Civi\Api4\DedupeRuleGroup::get( false )
			->addWhere( 'id', '=', $rule )
			->execute();
		if ( $dedupe_rule_groups->count() > 0 ) {
			return $dedupe_rule_groups->first();
		}
		return false;
	}

	/**
	 * Get dedupe rule.
	 *
	 * @param int $rule The rule we need to get.
	 *
	 * @return array|null
	 */
	public function get_dedupe_rule( $rule ) {
		$dedupe_rule = \Civi\Api4\DedupeRule::get( false )
			->addWhere( 'dedupe_rule_group_id', '=', $rule )
			->execute();
		if ( $dedupe_rule->count() > 0 ) {
			return $dedupe_rule;
		}
		return null;
	}

	/**
	 * Check for duplicates based on dedupe rule.
	 *
	 * @param string $rule_name The rule we need to check against.
	 * @param array  $params    The params needed for the dedupe rule.
	 *
	 * @return array|null
	 */
	public function dedupe_check( $rule_name, $params ) {
		$duplicate_api = \Civi\Api4\Contact::getDuplicates( false );
		$duplicate_api->setDedupeRule( $rule_name );
		foreach ( $params as $param_key => $param_value ) {
			$duplicate_api->addValue( $param_key, $param_value );
		}

		$duplicates = $duplicate_api->execute();
		if ( $duplicates->count() > 0 ) {
			return $duplicates;
		}
		return null;
	}

	/**
	 * Gets the first website for a contact or null if none.
	 *
	 * @param int $contact_id The contact to get the website for.
	 * @return array|null
	 */
	public function get_contact_website( $contact_id ) {
		$website = \Civi\Api4\Website::get( false )
			->addWhere( 'contact_id', '=', $contact_id )
			->execute()
			->first();
		return $website;
	}

	/**
	 * Creates/Updates a website record for a contact.
	 *
	 * @param int    $contact_id   The ID for the contact to set record for.
	 * @param string $url          The url for the website record.
	 * @param int    $website_type The website type id.
	 * @return void
	 */
	public function set_contact_website( $contact_id, $url, $website_type ) {
		$website = $this->get_contact_website( $contact_id );
		if ( $website ) {
			$assign_website = \Civi\Api4\Website::update( false );
			$assign_website->addWhere( 'id', '=', $website['id'] );
		} else {
			$assign_website = \Civi\Api4\Website::create( false );
		}
		$assign_website
			->addValue( 'contact_id', $contact_id )
			->addValue( 'url', $url )
			->addValue( 'website_type_id', $website_type ); // Linked In
		$assign_website->execute();
	}
}

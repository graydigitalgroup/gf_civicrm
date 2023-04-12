<?php
/**
 * Gravity Forms CiviCRM Utilities
 *
 * Class that houses all of the custom functionality or utilities for the plugin
 *
 * @package gf_civicrm_addon
 * @author GrayDigitalGroup
 * @license https://www.gnu.org/licenses/old-licenses/gpl-3.0.html
 */

namespace GF_CiviCRM;

/**
 * Main Utilities class
 *
 * @since 1.0
 */
class Utilities {

	/**
	 * Gets the time when the content of the file was changed.
	 *
	 * @method file_cache_bust
	 * @since 1.0
	 * @access public
	 * @param  string $src Path to files to get time last changed.
	 * @return string      Returns the time when the data blocks of a file were being written to, that is, the time when the content of the file was changed.
	 */
	public static function file_cache_bust( $src ) {
		$cache_bust = filemtime( realpath( '.' . wp_parse_url( $src, PHP_URL_PATH ) ) );

		return $cache_bust;
	}

	/**
	 * Sort array by coumn name
	 *
	 * @since 1.0
	 * @access public
	 * @param array  $array     Array to sort.
	 * @param string $column    The column to use for comparison.
	 * @param string $direction The direction to sort by.
	 */
	public static function sort_array_by_column( &$array, $column, $direction = 'asc' ) {
		usort(
			$array,
			function( $a, $b ) use ( $column, $direction ) {
				if ( array_key_exists( $column, $a ) && array_key_exists( $column, $a ) ) {
					return ( 'asc' === $direction )
						? $a[ $column ] <=> $b[ $column ]
						: $b[ $column ] <=> $a[ $column ];
				}
			}
		);
	}

	/**
	 * Check if the value is true or false.
	 *
	 * @param mixed $data The data to check if false.
	 *
	 * @return bool
	 */
	public static function is_boolean( $data ) {
		return ! in_array( strval( $data ), array( 'false', '', '0', 'no', 'off' ), true );
	}


	/**
	 * Sort options fields
	 *
	 * @param array  $data The options to sort.
	 * @param string $order The order to sort the options by.
	 */
	public static function sort_list( $data, $order = 'NL' ) {
		switch ( $order ) {
			case 'NL':
				$pattern_num = '~[0-9]~';
				uasort(
					$data,
					function( $a, $b ) use ( $pattern_num ) {
						$matches = array();
						if ( is_numeric( $a['label'] ) ) {
							preg_match( $pattern_num, $a['label'], $matches );
							$a['label'] = $matches[0];
						}
						if ( is_numeric( $b['label'] ) ) {
							preg_match( $pattern_num, $b['label'], $matches );
							$b['label'] = $matches[0];
						}
						$a['label'] = ucfirst( $a['label'] );
						$b['label'] = ucfirst( $b['label'] );
						return $a['label'] <=> $b['label'];
					}
				);
				break;
		}
		return $data;
	}

	/**
	 * Search string to determine if at end of another string.
	 *
	 * @param string $haystack The string to search in.
	 * @param string $needle   The string to search for.
	 *
	 * @return bool
	 */
	public static function str_ends_with( $haystack, $needle ) {
		return substr( $haystack, -strlen( $needle ) ) === $needle;
	}

	/**
	 * Function to iteratively search for a given key=>value.
	 *
	 * @param array $array The array to search through.
	 * @param mixed $key The key to compare against the $value.
	 * @param mixed $value The value to compare against the $key.
	 *
	 * @return array An array that is either empty or contains the arrays that matched the $key=>$value.
	 */
	public static function search_array( $array, $key, $value ) {

		$results = array();

		// RecursiveArrayIterator to traverse an
		// unknown amount of sub arrays within
		// the outer array.
		$array_iterator = new RecursiveArrayIterator( $array );

		// RecursiveIteratorIterator used to iterate
		// through recursive iterators.
		$iterator = new RecursiveIteratorIterator( $array_iterator );

		foreach ( $iterator as $sub ) {

			// Current active sub iterator.
			$sub_array = $it->getSubIterator();

			if ( $value === $sub_array[ $key ] ) {
				$results[] = iterator_to_array( $sub_array );
			}
		}
		return $results;
	}

	/**
	 * Dedupe CiviCRM Contact.
	 *
	 * @since 0.4.4
	 * @param  array  $contact Contact data.
	 * @param  string $contact_type Contact type.
	 * @param  int    $dedupe_rule_id Dedupe Rule ID.
	 * @return int    $contact_id The contact id.
	 */
	public static function civi_contact_dedupe( $contact, $contact_type, $dedupe_rule_id ) {
		// Dupes params.
		$dedupe_params                     = \CRM_Dedupe_Finder::formatParams( $contact, $contact_type );
		$dedupe_params['check_permission'] = false;

		// Check dupes.
		$cids = \CRM_Dedupe_Finder::dupesByParams( $dedupe_params, $contact_type, null, array(), $dedupe_rule_id );
		$cids = array_reverse( $cids );

		return $cids ? array_pop( $cids ) : 0;
	}

	/**
	 * Checks an array for keys.
	 *
	 * @param array $keys The keys we are checking for.
	 * @param array $arr  The array to search for keys.
	 *
	 * @return bool
	 */
	public static function array_keys_exists( array $keys, array $arr ) {
		return ! array_diff_key( array_flip( $keys ), $arr );
	}

	/**
	 * Takes a DomDocument and prevents the saveXML function from creating and autoclose tag
	 * for those html elements that should not have an autoclose tag.
	 *
	 * @param $dom DomDocument The DomDocument to parse and prevent autoclose elements.
	 *
	 * @return string
	 */
	public static function export_html(\DOMDocument $dom) {
		$voids = ['area',
				'base',
				'br',
				'col',
				'colgroup',
				'command',
				'embed',
				'hr',
				'img',
				'input',
				'keygen',
				'link',
				'meta',
				'param',
				'source',
				'track',
				'wbr'];

		// Every empty node. There is no reason to match nodes with content inside.
		$query = '//*[not(node())]';
		$nodes = (new \DOMXPath($dom))->query($query);

		foreach ($nodes as $n) {
			if (! in_array($n->nodeName, $voids)) {
				// If it is not a void/empty tag,
				// we need to leave the tag open.
				$n->appendChild(new \DOMComment('NOT_VOID'));
			}
		}

		// Let's remove the placeholder.
		return str_replace('<!--NOT_VOID-->', '', $dom->saveXML());
	}
}

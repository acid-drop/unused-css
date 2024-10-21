<?php

namespace UCSS\App;

/**
 * The Config class is responsible for managing various configurations of the system.
 * It includes functionality for initializing the configuration from the database,
 * merging the default configuration with user-defined settings, and providing methods
 * for updating and retrieving configuration values.
 *
 * @package UCSS
 */
class Config {

	// Variable to store the configuration
	public static $config;

	// Default configuration
	protected static $initial_config = array(
		'speed_css'  => array(
			'css_mode' => array(
				'name'   => 'Unused CSS mode (enable, stats, disabled)',
				'helper' => 'Whether or not the unused CSS functionality is enabled',
				'type'	=> 'radio',
				'value' => 'preview',
			),			
			'include_patterns' => array(
				'name'   => 'Include Patterns',
				'helper' => 'Selectors in CSS files that match these patterns will always be included. Separate multiple with new lines',
				'value' => '',
			),
		),					
	);

	/**
	 * Initializes the configuration class.
	 *
	 * Retrieves the saved configuration from the database, and merges it with the default configuration.
	 *
	 */
	public static function init() {

		// Get the saved configuration from the database
		self::$config = (array)get_option( 'ucss_namespace_CONFIG', array() );
		self::$config = self::array_merge_recursive_unique( self::$initial_config, self::$config );

		// Example of how to update one of the default configs
		#$docs = self::$initial_config['docs'];
		#$update = array("config_key"=>"docs","iframe"=>$docs['iframe']['value']);
		#self::update_config($update);


	}


	/**
	 * Recursively merges two arrays, but instead of overwriting duplicate values,
	 * it will add them to the resulting array.
	 *
	 * @param array $array1
	 * @param array $array2
	 *
	 * @return array
	 */
	public static function array_merge_recursive_unique(array $array1, array $array2) {
		$merged = $array1;
	
		foreach ($array2 as $key => $value) {
			if (is_array($value) && isset($merged[$key]) && is_array($merged[$key])) {
				$merged[$key] = self::array_merge_recursive_unique($merged[$key], $value);
			} elseif (is_array($value)) {
				$merged[$key] = self::array_merge_recursive_unique([], $value);
			} else {
				if (!in_array($value, $merged, true)) {
					$merged[$key] = $value;
				}
			}
		}
	
		return $merged;
	}


	/**
	 * Remove any non global config items from the array
	 *
	 * @param array $config The array to remove non global items from
	 *
	 * @return array The array with non global items removed
	 */
	public static function remove_non_global( $config ) {

		//Remove non-global
		$array = $config;
		foreach ($array as $key => $value) {
			foreach($value AS $subkey=>$subvalue) {
				if (is_array($subvalue) && isset($subvalue['global']) && $subvalue['global'] === false) {
					unset($array[$key][$subkey]);
				}
			}
		}
		return $array;

	}

	/**
	 * Get a config value
	 *
	 * @param string $parent The parent item of the config
	 * @param string $passed_key The key of the config item to get
	 *
	 * @return mixed The value of the config item
	 */
	public static function get( $parent, $passed_key ) {

		$config = self::$config;
		if ( isset( $config[ $parent ] ) ) {
			if ( isset( $config[ $parent ][ $passed_key ] ) ) {
				return $config[ $parent ][ $passed_key ] ['value'];
			}
		} else {
			return false;
		}

	}

	/**
	 * Update the config with new values
	 *
	 * @param array $new_config The new config values
	 *
	 * @return void
	 */
	public static function update_config( $new_config = array() ) {


		if ( isset( $new_config['config_key'] ) ) {

			$config_key = $new_config['config_key'];

			//Get the array frame
			$frame = self::$config[ $config_key ];

			//Run through the frame and find matching keys
			foreach ( $frame as $key_to_update=> $value ) {

				if ( isset( $new_config[ $key_to_update ] ) ) {

					$value_to_update = $value['value'];

					$frame[ $key_to_update ]['value'] = $new_config[ $key_to_update ];
				}
			}

			//Update the config
			self::$config[ $config_key ] = $frame;

		}

	

		update_option( 'ucss_namespace_CONFIG', self::$config, false );

	}




}

<?php
/**
 * WP_Style_Engine
 *
 * Generates classnames and block styles.
 *
 * @package Gutenberg
 */

if ( class_exists( 'WP_Style_Engine' ) ) {
	return;
}

/**
 * Singleton class representing the style engine.
 *
 * Consolidates rendering block styles to reduce duplication and streamline
 * CSS styles generation.
 */
class WP_Style_Engine {
	/**
	 * Container for the main instance of the class.
	 *
	 * @var WP_Style_Engine|null
	 */
	private static $instance = null;

	/**
	 * Style definitions that contain the instructions to
	 * parse/output valid Gutenberg styles from a block's attributes.
	 * For every style definition, the follow properties are valid:
	 *
	 *  - property_key => the key that represents a valid CSS property, e.g., "margin" or "border".
	 *  - path         => a path that accesses the corresponding style value in the block style object.
	 *  - value_func   => a function to generate an array of valid CSS rules for a particular style object.
	 *                    For example, `'padding' => 'array( 'top' => '1em' )` will return `array( 'padding-top' => '1em' )`
	 */
	const BLOCK_STYLE_DEFINITIONS_METADATA = array(
		'spacing'    => array(
			'padding' => array(
				'property_key' => 'padding',
				'path'         => array( 'spacing', 'padding' ),
			),
			'margin'  => array(
				'property_key' => 'margin',
				'path'         => array( 'spacing', 'margin' ),
			),
		),
		'typography' => array(
			'fontSize'       => array(
				'property_key'     => 'font-size',
				'path'             => array( 'typography', 'fontSize' ),
				'classname_schema' => 'has-%s-font-size',
			),
			'fontFamily'     => array(
				'property_key'     => 'font-family',
				'path'             => array( 'typography', 'fontFamily' ),
				'classname_schema' => 'has-%s-font-family',
			),
			'fontStyle'      => array(
				'property_key' => 'font-style',
				'path'         => array( 'typography', 'fontStyle' ),
			),
			'fontWeight'     => array(
				'property_key' => 'font-weight',
				'path'         => array( 'typography', 'fontWeight' ),
			),
			'lineHeight'     => array(
				'property_key' => 'line-height',
				'path'         => array( 'typography', 'lineHeight' ),
			),
			'textDecoration' => array(
				'property_key' => 'text-decoration',
				'path'         => array( 'typography', 'textDecoration' ),
			),
			'textTransform'  => array(
				'property_key' => 'text-transform',
				'path'         => array( 'typography', 'textTransform' ),
			),
			'letterSpacing'  => array(
				'property_key' => 'letter-spacing',
				'path'         => array( 'typography', 'letterSpacing' ),
			),
		),
	);

	/**
	 * Utility method to retrieve the main instance of the class.
	 *
	 * The instance will be created if it does not exist yet.
	 *
	 * @return WP_Style_Engine The main instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Returns a CSS ruleset based on the instructions in BLOCK_STYLE_DEFINITIONS_METADATA.
	 *
	 * @param string|array  $style_value A single raw Gutenberg style attributes value for a CSS property.
	 * @param array<string> $path        An array of strings representing a path to the style value.
	 *
	 * @return array A CSS ruleset compatible with generate().
	 */
	protected function get_block_style_css_rules( $style_value, $path ) {
		$style_definition = _wp_array_get( static::BLOCK_STYLE_DEFINITIONS_METADATA, $path, null );

		if ( ! empty( $style_definition ) ) {
			// Style definitions can define a function to generate custom CSS rules.
			if (
				isset( $style_definition['value_func'] ) &&
				is_callable( $style_definition['value_func'] )
			) {
				return call_user_func( $style_definition['value_func'], $style_value, $style_definition['property_key'] );
			} else {
				return static::get_css_rules( $style_value, $style_definition['property_key'] );
			}
		}

		return array();
	}

	/**
	 * Returns a classname built using a provided schema.
	 *
	 * @param array $block_styles An array of styles from a block's attributes.
	 *                            Some values may contain slugs that need to be parsed using a schema.
	 *
	 * @return string A CSS classname.
	 */
	public function get_classnames( $block_styles ) {
		$output = '';

		if ( empty( $block_styles ) ) {
			return $output;
		}

		$classnames = array();
		foreach ( self::BLOCK_STYLE_DEFINITIONS_METADATA as $definition_group ) {
			foreach ( $definition_group as $style_definition ) {
				$classname_value = _wp_array_get( $block_styles, $style_definition['path'], null );

				if ( empty( $classname_value ) ) {
					continue;
				}

				$classname_value  = _wp_to_kebab_case( $classname_value );
				$style_definition = _wp_array_get( static::BLOCK_STYLE_DEFINITIONS_METADATA, $style_definition['path'], null );
				// Right now we expect a classname pattern to be stored in BLOCK_STYLE_DEFINITIONS_METADATA.
				// One day, if there are no stored schemata, we could allow custom patterns or
				// generate classnames based on other properties
				// such as a path or a value or a prefix passed in options.
				$classnames[] = isset( $style_definition['classname_schema'] ) ? sprintf( $style_definition['classname_schema'], $classname_value ) : $classname_value;
			}
		}

		return implode( ' ', $classnames );
	}

	/**
	 * Returns an CSS ruleset.
	 * Styles are bundled based on the instructions in BLOCK_STYLE_DEFINITIONS_METADATA.
	 *
	 * @param array $block_styles An array of styles from a block's attributes.
	 * @param array $options = array(
	 *     'inline' => (boolean) Whether to return inline CSS rules destined to be inserted in an HTML `style` attribute.
	 *     'path'   => (array)   Specify a block style to generate, otherwise it'll try all in BLOCK_STYLE_DEFINITIONS_METADATA.
	 * );.
	 *
	 * @return string A CSS ruleset formatted to be placed in an HTML `style` attribute.
	 */
	public function generate( $block_styles, $options = array() ) {
		$output = '';

		if ( empty( $block_styles ) ) {
			return $output;
		}

		$rules = array();

		foreach ( self::BLOCK_STYLE_DEFINITIONS_METADATA as $definition_group ) {
			foreach ( $definition_group as $style_definition ) {
				$style_value = _wp_array_get( $block_styles, $style_definition['path'], null );
				if ( empty( $style_value ) ) {
					continue;
				}
				$rules = array_merge( $rules, $this->get_block_style_css_rules( $style_value, $style_definition['path'] ) );
			}
		}

		if ( ! empty( $rules ) ) {
			// Generate inline style rules.
			if ( isset( $options['inline'] ) && true === $options['inline'] ) {
				foreach ( $rules as $rule => $value ) {
					$filtered_css = esc_html( safecss_filter_attr( "{$rule}: {$value}" ) );
					if ( ! empty( $filtered_css ) ) {
						$output .= $filtered_css . '; ';
					}
				}
			}
		}

		return trim( $output );
	}

	/**
	 * Default style value parser that returns a CSS ruleset.
	 * If the input contains an array, it will treated like a box model
	 * for styles such as margins, padding, and borders
	 *
	 * @param string|array $style_value    A single raw Gutenberg style attributes value for a CSS property.
	 * @param string       $style_property The CSS property for which we're creating a rule.
	 *
	 * @return array The class name for the added style.
	 */
	public static function get_css_rules( $style_value, $style_property ) {
		$rules = array();

		if ( ! $style_value ) {
			return $rules;
		}

		// We assume box model-like properties.
		if ( is_array( $style_value ) ) {
			foreach ( $style_value as $key => $value ) {
				$rules[ "$style_property-$key" ] = $value;
			}
		} else {
			$rules[ $style_property ] = $style_value;
		}
		return $rules;
	}
}

/**
 * This function returns the Style Engine instance.
 *
 * @return WP_Style_Engine
 */
function wp_get_style_engine() {
	if ( class_exists( 'WP_Style_Engine' ) ) {
		return WP_Style_Engine::get_instance();
	}
}

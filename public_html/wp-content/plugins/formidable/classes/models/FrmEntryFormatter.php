<?php

/**
 * @since 2.04
 */
class FrmEntryFormatter {

	/**
	 * @var stdClass
	 * @since 2.04
	 */
	protected $entry = null;

	/**
	 * @var FrmEntryValues
	 * @since 2.04
	 */
	protected $entry_values = null;

	/**
	 * @var bool
	 * @since 2.04
	 */
	protected $is_plain_text = false;

	/**
	 * @var bool
	 * @since 2.04
	 */
	protected $include_user_info = false;

	/**
	 * @var bool
	 * @since 2.04
	 */
	protected $include_blank = false;

	/**
	 * @var string
	 * @since 2.04
	 */
	protected $format = 'text';

	/**
	 * @var string
	 * @since 2.05
	 */
	protected $array_key = 'key';

	/**
	 * @var string
	 * @since 2.04
	 */
	protected $direction = 'ltr';

	/**
	 * @var FrmTableHTMLGenerator
	 * @since 2.04
	 */
	protected $table_generator = null;

	/**
	 * @var bool
	 * @since 2.04
	 */
	protected $is_clickable = false;

	/**
	 * @var array
	 * @since 2.04
	 */
	protected $include_extras = array();

	/**
	 * @var array
	 * @since 2.04
	 */
	protected $skip_fields = array( 'captcha', 'html' );

	/**
	 * FrmEntryFormat constructor
	 *
	 * @since 2.04
	 *
	 * @param $atts
	 */
	public function __construct( $atts ) {
		$this->init_entry( $atts );

		if ( $this->entry === null || $this->entry === false ) {
			return;
		}

		$this->init_is_plain_text( $atts );
		$this->init_format( $atts );
		$this->init_array_key( $atts );
		$this->init_include_blank( $atts );
		$this->init_direction( $atts );
		$this->init_include_user_info( $atts );
		$this->init_entry_values( $atts );

		if ( $this->format === 'table' ) {
			$this->init_table_generator( $atts );
			$this->init_is_clickable( $atts );
		}
	}

	/**
	 * Set the entry property
	 *
	 * @since 2.04
	 *
	 * @param array $atts
	 */
	protected function init_entry( $atts ) {
		if ( is_object( $atts['entry'] ) ) {

			if ( isset( $atts['entry']->metas ) ) {
				$this->entry = $atts['entry'];
			} else {
				$this->entry = FrmEntry::getOne( $atts['entry']->id, true );
			}
		} else if ( $atts['id'] ) {
			$this->entry = FrmEntry::getOne( $atts['id'], true );
		}
	}

	/**
	 * Set the entry values property
	 *
	 * @since 2.04
	 *
	 * @param array $atts
	 */
	protected function init_entry_values( $atts ) {
		$atts['source'] = 'entry_formatter';
		$this->entry_values = new FrmEntryValues( $this->entry->id, $atts );
	}

	/**
	 * Set the format property
	 *
	 * @since 2.04
	 *
	 * @param array $atts
	 */
	protected function init_format( $atts ) {
		if ( $atts['format'] === 'array' ) {

			$this->format = 'array';

		} else if ( $atts['format'] === 'json' ) {

			$this->format = 'json';

		} else if ( $atts['format'] === 'text' ) {

			if ( $this->is_plain_text === true ) {
				$this->format = 'plain_text_block';
			} else {
				$this->format = 'table';
			}
		}
	}

	/**
	 * Set the array_key property that sets whether the keys in the
	 * returned array are field keys or ids
	 *
	 * @since 2.05
	 *
	 * @param array $atts
	 */
	protected function init_array_key( $atts ) {
		if ( isset( $atts['array_key'] ) && $atts['array_key'] == 'id' ) {
			$this->array_key = 'id';
		}
	}

	/**
	 * Set the is_plain_text property
	 *
	 * @since 2.04
	 *
	 * @param array $atts
	 */
	protected function init_is_plain_text( $atts ) {
		if ( isset( $atts['plain_text'] ) && $atts['plain_text'] ) {
			$this->is_plain_text = true;
		} else if ( $atts['format'] !== 'text' ) {
			$this->is_plain_text = true;
		}
	}

	/**
	 * Set the include_blank property
	 *
	 * @since 2.04
	 *
	 * @param array $atts
	 */
	protected function init_include_blank( $atts ) {
		if ( isset( $atts['include_blank'] ) && $atts['include_blank'] ) {
			$this->include_blank = true;
		}
	}

	/**
	 * Set the direction property
	 *
	 * @since 2.04
	 *
	 * @param array $atts
	 */
	protected function init_direction( $atts ) {
		if ( isset( $atts['direction'] ) && $atts['direction'] === 'rtl' ) {
			$this->direction = 'rtl';
		}
	}

	/**
	 * Set the include_user_info property
	 *
	 * @since 2.04
	 *
	 * @param array $atts
	 */
	protected function init_include_user_info( $atts ) {
		if ( isset( $atts['user_info'] ) && $atts['user_info'] ) {
			$this->include_user_info = true;
		}
	}

	/**
	 * Set the table_generator property
	 *
	 * @since 2.04
	 *
	 * @param array $atts
	 */
	protected function init_table_generator( $atts ) {
		$this->table_generator = new FrmTableHTMLGenerator( 'entry', $atts );
	}

	/**
	 * Set the is_clickable property
	 *
	 * @since 2.04
	 *
	 * @param array $atts
	 */
	protected function init_is_clickable( $atts ) {
		if ( isset( $atts['clickable'] ) && $atts['clickable'] ) {
			$this->is_clickable = true;
		}
	}

	/**
	 * Get the field key or ID, depending on array_key property
	 *
	 * @since 2.05
	 *
	 * @param FrmFieldValue $field_value
	 *
	 * @return string|int
	 */
	protected function get_key_or_id( $field_value ) {
		return $this->array_key == 'key' ? $field_value->get_field_key() : $field_value->get_field_id();
	}

	/**
	 * Package and return the formatted entry values
	 *
	 * @since 2.04
	 *
	 * @return array|string
	 */
	public function get_formatted_entry_values() {
		if ( $this->entry === null || $this->entry === false ) {
			return '';
		}

		if ( $this->format === 'json' ) {
			$content = json_encode( $this->prepare_array() );

		} else if ( $this->format === 'array' ) {
			$content = $this->prepare_array();

		} else if ( $this->format === 'table' ) {
			$content = $this->prepare_html_table();

		} else if ( $this->format === 'plain_text_block' ) {
			$content = $this->prepare_plain_text_block();

		} else {
			$content = '';
		}

		return $content;
	}

	/**
	 * Return the formatted HTML table with entry values
	 *
	 * @since 2.04
	 *
	 * @return string
	 */
	protected function prepare_html_table() {
		$content = $this->table_generator->generate_table_header();

		foreach ( $this->entry_values->get_field_values() as $field_id => $field_value ) {
			$this->add_field_value_to_content( $field_value, $content );
		}

		$this->add_user_info_to_html_table( $content );

		$content .= $this->table_generator->generate_table_footer();

		if ( $this->is_clickable ) {
			$content = make_clickable( $content );
		}

		return $content;
	}

	/**
	 * Return the formatted plain text content
	 *
	 * @since 2.04
	 *
	 * @return string
	 */
	protected function prepare_plain_text_block() {
		$content = '';

		foreach ( $this->entry_values->get_field_values() as $field_id => $field_value ) {
			$this->add_field_value_to_content( $field_value, $content );
		}

		$this->add_user_info_to_plain_text_content( $content );

		return $content;
	}

	/**
	 * Prepare the array output
	 *
	 * @since 2.04
	 *
	 * @return array
	 */
	protected function prepare_array() {
		$array_output = array();

		$this->push_field_values_to_array( $this->entry_values->get_field_values(), $array_output );

		return $array_output;
	}

	/**
	 * Push field values to array content
	 *
	 * @since 2.04
	 *
	 * @param array $field_values
	 * @param array $output
	 */
	protected function push_field_values_to_array( $field_values, &$output ) {
		foreach ( $field_values as $field_value ) {
			$this->push_single_field_to_array( $field_value, $output );
		}
	}

	/**
	 * Push a single field to the array content
	 *
	 * @since 2.04
	 *
	 * @param FrmFieldValue $field_value
	 * @param array $output
	 */
	protected function push_single_field_to_array( $field_value, &$output ) {
		if ( $this->include_field_in_content( $field_value ) ) {

			$displayed_value = $this->prepare_display_value_for_array( $field_value->get_displayed_value() );
			$output[ $this->get_key_or_id( $field_value ) ] = $displayed_value;

			if ( $displayed_value !== $field_value->get_saved_value() ) {
				$output[ $this->get_key_or_id( $field_value ) . '-value' ] = $field_value->get_saved_value();
			}
		}
	}

	/**
	 * Add a row of values to the plain text content
	 *
	 * @since 2.04
	 *
	 * @param string $label
	 * @param mixed $display_value
	 * @param string $content
	 */
	protected function add_plain_text_row( $label, $display_value, &$content ) {
		$display_value = $this->prepare_display_value_for_plain_text_content( $display_value );

		if ( 'rtl' == $this->direction ) {
			$content .= $display_value . ' :' . $label . "\r\n";
		} else {
			$content .= $label . ': ' . $display_value . "\r\n";
		}
	}

	/**
	 * Add a field value to the HTML table or plain text content
	 *
	 * @since 2.04
	 *
	 * @param FrmFieldValue $field_value
	 * @param string $content
	 */
	protected function add_field_value_to_content( $field_value, &$content ) {
		if ( ! $this->include_field_in_content( $field_value ) ) {
			return;
		}

		if ( $this->format === 'plain_text_block' ) {
			$this->add_plain_text_row( $field_value->get_field_label(), $field_value->get_displayed_value(), $content );
		} else if ( $this->format === 'table' ) {
			$value_args = $this->package_value_args( $field_value );
			$this->add_html_row( $value_args, $content );
		}
	}

	/**
	 * Package the value arguments for an HTML row
	 *
	 * @since 2.04
	 *
	 * @param FrmFieldValue $field_value
	 *
	 * @return array
	 */
	protected function package_value_args( $field_value ) {
		return array(
			'label'       => $field_value->get_field_label(),
			'value'       => $field_value->get_displayed_value(),
			'field_type'  => $field_value->get_field_type(),
		);
	}

	/**
	 * Add user info to an HTML table
	 *
	 * @since 2.04
	 *
	 * @param string $content
	 */
	protected function add_user_info_to_html_table( &$content ) {
		if ( $this->include_user_info ) {

			foreach ( $this->entry_values->get_user_info() as $user_info ) {

				$value_args = array(
					'label' => $user_info['label'],
					'value' => $user_info['value'],
					'field_type'  => 'none',
				);

				$this->add_html_row( $value_args, $content );
			}
		}
	}

	/**
	 * Add user info to plain text content
	 *
	 * @since 2.04
	 *
	 * @param string $content
	 */
	protected function add_user_info_to_plain_text_content( &$content ) {
		if ( $this->include_user_info ) {

			foreach ( $this->entry_values->get_user_info() as $user_info ) {
				$this->add_plain_text_row( $user_info['label'], $user_info['value'], $content );
			}
		}
	}

	/**
	 * Check if a field should be included in the content
	 *
	 * @since 2.04
	 *
	 * @param FrmFieldValue $field_value
	 *
	 * @return bool
	 */
	protected function include_field_in_content( $field_value ) {
		$include = true;

		if ( $this->is_extra_field( $field_value ) ) {

			$include = $this->is_extra_field_included( $field_value );

		} else {
			$displayed_value = $field_value->get_displayed_value();

			if ( $displayed_value === '' || ( is_array( $displayed_value ) && empty( $displayed_value ) ) ) {

				if ( ! $this->include_blank ) {
					$include = false;
				}
			}
		}

		return $include;
	}

	/**
	 * Check if a field is normally a skipped type
	 *
	 * @since 2.04
	 *
	 * @param FrmFieldValue $field_value
	 *
	 * @return bool
	 */
	protected function is_extra_field( $field_value ) {
		return in_array( $field_value->get_field_type(), $this->skip_fields );
	}

	/**
	 * Check if an extra field is included
	 *
	 * @since 2.04
	 *
	 * @param FrmFieldValue $field_value
	 *
	 * @return bool
	 */
	protected function is_extra_field_included( $field_value ) {
		return in_array( $field_value->get_field_type(), $this->include_extras );
	}

	/**
	 * Add a row in an HTML table
	 *
	 * @since 2.04
	 *
	 * @param array $value_args
	 *   $value_args = [
	 *     'label' => (string) The label. Required
	 *     'value' => (mixed) The value to add. Required
	 *     'field_type' => (string) The field type. Blank string if not a field.
	 *   ]
	 * @param string $content
	 */
	protected function add_html_row( $value_args, &$content ) {
		$display_value = $this->prepare_display_value_for_html_table( $value_args['value'], $value_args['field_type'] );

		$content .= $this->table_generator->generate_two_cell_table_row( $value_args['label'], $display_value );
	}

	/**
	 * Prepare the displayed value for an array
	 *
	 * @since 2.04
	 *
	 * @param mixed $value
	 *
	 * @return mixed|string
	 */
	protected function prepare_display_value_for_array( $value ) {
		return $this->strip_html( $value );
	}


	/**
	 * Prepare a field's display value for an HTML table
	 *
	 * @since 2.04
	 *
	 * @param mixed $display_value
	 * @param string $field_type
	 *
	 * @return mixed|string
	 */
	protected function prepare_display_value_for_html_table( $display_value, $field_type = '' ) {
		$display_value = $this->flatten_array( $display_value );
		$display_value = str_replace( "\r\n", '<br/>', $display_value );

		return $display_value;
	}

	/**
	 * Prepare a field's display value for plain text content
	 *
	 * @since 2.04
	 *
	 * @param mixed $display_value
	 *
	 * @return string|int
	 */
	protected function prepare_display_value_for_plain_text_content( $display_value ) {
		$display_value = $this->flatten_array( $display_value );
		$display_value = $this->strip_html( $display_value );

		return $display_value;
	}

	/**
	 * Flatten an array
	 *
	 * @since 2.04
	 *
	 * @param array|string|int $value
	 *
	 * @return string|int
	 */
	protected function flatten_array( $value ) {
		if ( is_array( $value ) ) {
			$value = implode( ', ', $value );
		}

		return $value;
	}

	/**
	 * Strip HTML if from email value if plain text is selected
	 *
	 * @since 2.0.21
	 *
	 * @param mixed $value
	 *
	 * @return mixed
	 */
	protected function strip_html( $value ) {

		if ( $this->is_plain_text ) {

			if ( is_array( $value ) ) {
				foreach ( $value as $key => $single_value ) {
					$value[ $key ] = $this->strip_html( $single_value );
				}
			} else if ( $this->is_plain_text && ! is_array( $value ) ) {
				if ( strpos( $value, '<img' ) !== false ) {
					$value = str_replace( array( '<img', 'src=', '/>', '"' ), '', $value );
					$value = trim( $value );
				}
				$value = strip_tags( $value );
			}
		}

		return $value;
	}
}

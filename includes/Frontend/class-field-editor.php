<?php
/**
 * Checkout field editor.
 *
 * @package CheckFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class CheckFlow_Field_Editor {

	const OPTION = 'checkflow_field_editor_fields';
	const CUSTOM_PREFIX = 'checkflow_custom_';

	/** @var self|null */
	private static $instance = null;

	private function __construct() {}

	/**
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	public function get_default_fields() {
		return array(
			'billing_first_name'  => $this->field( 'billing', 'billing_first_name', __( 'First name', 'checkflow' ), true, 10 ),
			'billing_last_name'   => $this->field( 'billing', 'billing_last_name', __( 'Last name', 'checkflow' ), true, 20 ),
			'billing_company'     => $this->field( 'billing', 'billing_company', __( 'Company name', 'checkflow' ), false, 30 ),
			'billing_country'     => $this->field( 'billing', 'billing_country', __( 'Country/Region', 'checkflow' ), true, 40 ),
			'billing_address_1'   => $this->field( 'billing', 'billing_address_1', __( 'Address', 'checkflow' ), true, 50 ),
			'billing_address_2'   => $this->field( 'billing', 'billing_address_2', __( 'Apartment, suite, etc.', 'checkflow' ), false, 60 ),
			'billing_city'        => $this->field( 'billing', 'billing_city', __( 'City', 'checkflow' ), true, 70 ),
			'billing_state'       => $this->field( 'billing', 'billing_state', __( 'District/State', 'checkflow' ), true, 80 ),
			'billing_postcode'    => $this->field( 'billing', 'billing_postcode', __( 'Postal code', 'checkflow' ), false, 90 ),
			'billing_phone'       => $this->field( 'billing', 'billing_phone', __( 'Phone', 'checkflow' ), false, 100 ),
			'billing_email'       => $this->field( 'billing', 'billing_email', __( 'Email address', 'checkflow' ), true, 110, true ),
			'shipping_first_name' => $this->field( 'shipping', 'shipping_first_name', __( 'First name', 'checkflow' ), true, 10 ),
			'shipping_last_name'  => $this->field( 'shipping', 'shipping_last_name', __( 'Last name', 'checkflow' ), true, 20 ),
			'shipping_company'    => $this->field( 'shipping', 'shipping_company', __( 'Company name', 'checkflow' ), false, 30 ),
			'shipping_country'    => $this->field( 'shipping', 'shipping_country', __( 'Country/Region', 'checkflow' ), true, 40 ),
			'shipping_address_1'  => $this->field( 'shipping', 'shipping_address_1', __( 'Address', 'checkflow' ), true, 50 ),
			'shipping_address_2'  => $this->field( 'shipping', 'shipping_address_2', __( 'Apartment, suite, etc.', 'checkflow' ), false, 60 ),
			'shipping_city'       => $this->field( 'shipping', 'shipping_city', __( 'City', 'checkflow' ), true, 70 ),
			'shipping_state'      => $this->field( 'shipping', 'shipping_state', __( 'District/State', 'checkflow' ), true, 80 ),
			'shipping_postcode'   => $this->field( 'shipping', 'shipping_postcode', __( 'Postal code', 'checkflow' ), false, 90 ),
			'order_comments'      => $this->field( 'order', 'order_comments', __( 'Order notes', 'checkflow' ), false, 10 ),
		);
	}

	/**
	 * @return array<int,array<string,mixed>>
	 */
	public function get_admin_rows() {
		$settings = $this->get_settings();
		$rows     = array_values( $settings );
		usort(
			$rows,
			static function ( $a, $b ) {
				$group_a = isset( $a['group'] ) ? (string) $a['group'] : '';
				$group_b = isset( $b['group'] ) ? (string) $b['group'] : '';
				if ( $group_a === $group_b ) {
					return absint( $a['priority'] ) <=> absint( $b['priority'] );
				}
				return strcmp( $group_a, $group_b );
			}
		);
		return $rows;
	}

	/**
	 * Return default field config for admin reset controls.
	 *
	 * @param string $key Field key.
	 * @return array<string,mixed>
	 */
	public function get_default_field_for_admin( $key ) {
		$defaults = $this->get_default_fields();
		$key      = sanitize_key( $key );
		return isset( $defaults[ $key ] ) ? $defaults[ $key ] : array();
	}

	/**
	 * Frontend metadata used for Blocks-safe visual enhancements.
	 *
	 * @return array<string,array<string,string>>
	 */
	public function get_checkout_field_meta() {
		$out = array();
		foreach ( $this->get_settings() as $key => $config ) {
			if ( empty( $config['enabled'] ) && empty( $config['protected'] ) ) {
				continue;
			}
			$out[ $key ] = array(
				'label'       => isset( $config['label'] ) ? (string) $config['label'] : '',
				'placeholder' => isset( $config['placeholder'] ) ? (string) $config['placeholder'] : '',
				'help'        => isset( $config['help'] ) ? (string) $config['help'] : '',
				'width'       => isset( $config['width'] ) ? (string) $config['width'] : 'default',
				'defaultValue' => isset( $config['default_value'] ) ? (string) $config['default_value'] : '',
				'required'    => ! empty( $config['required'] ) ? '1' : '0',
				'validation'  => isset( $config['validation'] ) ? (string) $config['validation'] : 'none',
				'min'         => isset( $config['min'] ) ? (string) $config['min'] : '',
				'max'         => isset( $config['max'] ) ? (string) $config['max'] : '',
				'minLength'   => isset( $config['min_length'] ) ? (string) $config['min_length'] : '',
				'maxLength'   => isset( $config['max_length'] ) ? (string) $config['max_length'] : '',
				'requiredMessage' => isset( $config['required_message'] ) ? (string) $config['required_message'] : '',
				'validationMessage' => isset( $config['validation_message'] ) ? (string) $config['validation_message'] : '',
				'condition'   => isset( $config['condition'] ) && is_array( $config['condition'] ) ? $config['condition'] : array(),
				'type'        => isset( $config['type'] ) ? (string) $config['type'] : 'text',
				'custom'      => ! empty( $config['custom'] ) ? '1' : '0',
			);
		}
		return $out;
	}

	/**
	 * @return array<string,mixed>
	 */
	public function get_cart_context() {
		$out = array(
			'total'      => 0,
			'productIds' => array(),
			'categoryIds' => array(),
		);
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return $out;
		}
		$out['total'] = (float) WC()->cart->get_total( 'edit' );
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
			if ( ! $product_id ) {
				continue;
			}
			$out['productIds'][] = $product_id;
			$terms = get_the_terms( $product_id, 'product_cat' );
			if ( is_array( $terms ) ) {
				foreach ( $terms as $term ) {
					$out['categoryIds'][] = absint( $term->term_id );
				}
			}
		}
		$out['productIds']  = array_values( array_unique( $out['productIds'] ) );
		$out['categoryIds'] = array_values( array_unique( $out['categoryIds'] ) );
		return $out;
	}

	/**
	 * Save field settings from admin.
	 */
	public function ajax_save_fields() {
		check_ajax_referer( 'checkflow_admin', 'nonce' );

		if ( ! current_user_can( CheckFlow_Admin::caps() ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'checkflow' ) ), 403 );
		}

		$raw = isset( $_POST['fields'] ) ? wp_unslash( $_POST['fields'] ) : '[]';
		$in  = json_decode( (string) $raw, true );
		if ( ! is_array( $in ) ) {
			wp_send_json_error( array( 'message' => __( 'Invalid field payload.', 'checkflow' ) ), 400 );
		}

		$defaults = $this->get_default_fields();
		$out      = array();

		foreach ( $in as $row ) {
			if ( ! is_array( $row ) || empty( $row['key'] ) ) {
				continue;
			}
			$key = sanitize_key( $row['key'] );
			$is_custom = isset( $row['custom'] ) && ! empty( $row['custom'] );
			if ( ! isset( $defaults[ $key ] ) && ! $is_custom ) {
				continue;
			}

			$base      = isset( $defaults[ $key ] ) ? $defaults[ $key ] : $this->custom_base_field( $row, $key );
			$protected = ! empty( $base['protected'] );
			$label     = isset( $row['label'] ) ? sanitize_text_field( wp_unslash( $row['label'] ) ) : $base['label'];
			$priority  = isset( $row['priority'] ) ? absint( $row['priority'] ) : absint( $base['priority'] );
			$width     = isset( $row['width'] ) ? sanitize_key( $row['width'] ) : ( isset( $base['width'] ) ? (string) $base['width'] : 'default' );
			if ( ! in_array( $width, array( 'default', 'full', 'half', 'first', 'last' ), true ) ) {
				$width = 'default';
			}
			$validation = isset( $row['validation'] ) ? sanitize_key( $row['validation'] ) : ( isset( $base['validation'] ) ? (string) $base['validation'] : 'none' );
			if ( ! in_array( $validation, array( 'none', 'email', 'phone', 'number', 'text' ), true ) ) {
				$validation = 'none';
			}
			$condition = isset( $row['condition'] ) && is_array( $row['condition'] ) ? $this->sanitize_condition( $row['condition'] ) : $this->default_condition();
			if ( '' === $label ) {
				$label = $base['label'];
			}
			$out[ $key ] = array_merge(
				$base,
				array(
					'label'    => $label,
					'enabled'  => $protected ? true : ! empty( $row['enabled'] ),
					'required' => ! empty( $row['required'] ),
					'priority' => min( 999, max( 1, $priority ) ),
					'placeholder' => isset( $row['placeholder'] ) ? sanitize_text_field( wp_unslash( $row['placeholder'] ) ) : '',
					'help'      => isset( $row['help'] ) ? sanitize_text_field( wp_unslash( $row['help'] ) ) : '',
					'width'     => $width,
					'default_value' => isset( $row['default_value'] ) ? sanitize_text_field( wp_unslash( $row['default_value'] ) ) : '',
					'validation' => $validation,
					'min'       => isset( $row['min'] ) ? sanitize_text_field( wp_unslash( $row['min'] ) ) : '',
					'max'       => isset( $row['max'] ) ? sanitize_text_field( wp_unslash( $row['max'] ) ) : '',
					'min_length' => isset( $row['min_length'] ) ? absint( $row['min_length'] ) : 0,
					'max_length' => isset( $row['max_length'] ) ? absint( $row['max_length'] ) : 0,
					'required_message' => isset( $row['required_message'] ) ? sanitize_text_field( wp_unslash( $row['required_message'] ) ) : '',
					'validation_message' => isset( $row['validation_message'] ) ? sanitize_text_field( wp_unslash( $row['validation_message'] ) ) : '',
					'condition' => $condition,
				)
			);
		}

		update_option( self::OPTION, $out, false );

		wp_send_json_success(
			array(
				'message' => __( 'Checkout fields saved.', 'checkflow' ),
				'fields'  => array_values( $out ),
			)
		);
	}

	/**
	 * Reset field settings.
	 */
	public function ajax_reset_fields() {
		check_ajax_referer( 'checkflow_admin', 'nonce' );

		if ( ! current_user_can( CheckFlow_Admin::caps() ) ) {
			wp_send_json_error( array( 'message' => __( 'Permission denied.', 'checkflow' ) ), 403 );
		}

		delete_option( self::OPTION );
		wp_send_json_success(
			array(
				'message' => __( 'Checkout fields reset.', 'checkflow' ),
				'fields'  => array_values( $this->get_default_fields() ),
			)
		);
	}

	/**
	 * Apply settings to WooCommerce checkout fields.
	 *
	 * @param array<string,array<string,array<string,mixed>>> $fields Checkout fields.
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	public function apply_checkout_fields( $fields ) {
		$settings = $this->get_settings();

		foreach ( $settings as $key => $config ) {
			$group = isset( $config['group'] ) ? (string) $config['group'] : '';
			if ( empty( $fields[ $group ] ) || ! is_array( $fields[ $group ] ) ) {
				$fields[ $group ] = array();
			}
			if ( ! isset( $fields[ $group ][ $key ] ) && empty( $config['custom'] ) ) {
				continue;
			}

			if ( empty( $config['enabled'] ) && empty( $config['protected'] ) ) {
				if ( isset( $fields[ $group ][ $key ] ) ) {
					unset( $fields[ $group ][ $key ] );
				}
				continue;
			}

			$fields[ $group ][ $key ] = array_merge(
				isset( $fields[ $group ][ $key ] ) ? $fields[ $group ][ $key ] : array(),
				$this->checkout_field_args( $config )
			);
		}

		return $fields;
	}

	/**
	 * Validate Field Editor rules during classic checkout submission.
	 *
	 * @param array<string,mixed> $data Checkout data.
	 * @param WP_Error           $errors Validation errors.
	 */
	public function validate_checkout_fields( $data, $errors ) {
		foreach ( $this->get_settings() as $key => $config ) {
			if ( empty( $config['enabled'] ) && empty( $config['protected'] ) ) {
				continue;
			}
			if ( ! $this->field_condition_visible( $config, $data ) ) {
				continue;
			}
			$value = '';
			if ( isset( $data[ $key ] ) ) {
				$value = $data[ $key ];
			} elseif ( isset( $_POST[ $key ] ) ) {
				$value = wp_unslash( $_POST[ $key ] );
			} else {
				continue;
			}
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'sanitize_text_field', $value ) );
			} else {
				$value = sanitize_text_field( (string) $value );
			}
			$message = $this->validate_value( $value, $config );
			if ( '' !== $message ) {
				if ( is_object( $errors ) && method_exists( $errors, 'add' ) ) {
					$errors->add( 'checkflow_' . sanitize_key( $key ), $message );
				} elseif ( function_exists( 'wc_add_notice' ) ) {
					wc_add_notice( $message, 'error' );
				}
			}
		}
	}

	/**
	 * Apply core field ordering to WooCommerce Blocks default address locale.
	 *
	 * @param array<string,array<string,mixed>> $locale Locale fields.
	 * @return array<string,array<string,mixed>>
	 */
	public function apply_blocks_default_locale( $locale ) {
		return $this->apply_blocks_locale_order( is_array( $locale ) ? $locale : array() );
	}

	/**
	 * Apply core field ordering to WooCommerce Blocks country locales.
	 *
	 * @param array<string,array<string,array<string,mixed>>> $locales Country locales.
	 * @return array<string,array<string,array<string,mixed>>>
	 */
	public function apply_blocks_country_locales( $locales ) {
		if ( ! is_array( $locales ) ) {
			return $locales;
		}
		foreach ( $locales as $country => $locale ) {
			if ( is_array( $locale ) ) {
				$locales[ $country ] = $this->apply_blocks_locale_order( $locale );
			}
		}
		return $locales;
	}

	/**
	 * Register custom fields for WooCommerce Blocks checkout.
	 */
	public function register_block_checkout_fields() {
		if ( ! function_exists( 'woocommerce_register_additional_checkout_field' ) ) {
			return;
		}

		foreach ( $this->get_settings() as $key => $config ) {
			if ( empty( $config['custom'] ) || empty( $config['enabled'] ) ) {
				continue;
			}

			$type = isset( $config['type'] ) ? (string) $config['type'] : 'text';
			if ( ! in_array( $type, array( 'text', 'select', 'checkbox' ), true ) ) {
				$type = 'text';
			}

			$args = array(
				'id'            => $this->block_field_id( $key ),
				'label'         => (string) $config['label'],
				'location'      => $this->block_field_location( isset( $config['group'] ) ? (string) $config['group'] : 'order' ),
				'type'          => $type,
				'required'      => false,
				'optionalLabel' => sprintf(
					/* translators: %s: field label */
					__( '%s (optional)', 'checkflow' ),
					(string) $config['label']
				),
			);

			if ( 'select' === $type ) {
				$args['options'] = $this->block_select_options( isset( $config['options'] ) && is_array( $config['options'] ) ? $config['options'] : array() );
				if ( empty( $args['options'] ) ) {
					continue;
				}
			}

			try {
				woocommerce_register_additional_checkout_field( $args );
			} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
				continue;
			}
		}
	}

	/**
	 * Save custom checkout field values to order meta.
	 *
	 * @param WC_Order $order Order.
	 */
	public function save_custom_order_meta( $order ) {
		foreach ( $this->get_settings() as $key => $config ) {
			if ( empty( $config['custom'] ) || empty( $config['enabled'] ) || ! isset( $_POST[ $key ] ) ) {
				continue;
			}
			if ( ! $this->field_condition_visible( $config, $_POST ) ) {
				continue;
			}
			$value = wp_unslash( $_POST[ $key ] );
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'sanitize_text_field', $value ) );
			} else {
				$value = sanitize_text_field( $value );
			}
			if ( '' !== $value ) {
				$order->update_meta_data( '_' . $key, $value );
			}
		}
	}

	/**
	 * Mirror WooCommerce Blocks additional fields into CheckFlow order meta.
	 *
	 * @param WC_Order        $order Order.
	 * @param WP_REST_Request $request Store API request.
	 */
	public function save_store_api_custom_order_meta( $order, $request ) {
		$additional = isset( $request['additional_fields'] ) && is_array( $request['additional_fields'] ) ? $request['additional_fields'] : array();
		if ( empty( $additional ) ) {
			return;
		}

		foreach ( $this->get_settings() as $key => $config ) {
			if ( empty( $config['custom'] ) || empty( $config['enabled'] ) ) {
				continue;
			}
			if ( ! $this->field_condition_visible( $config, $request->get_params() ) ) {
				continue;
			}
			$block_id = $this->block_field_id( $key );
			if ( ! array_key_exists( $block_id, $additional ) ) {
				continue;
			}
			$value = $additional[ $block_id ];
			if ( is_array( $value ) ) {
				$value = implode( ', ', array_map( 'sanitize_text_field', $value ) );
			} else {
				$value = sanitize_text_field( (string) $value );
			}
			if ( '' !== $value ) {
				$order->update_meta_data( '_' . $key, $value );
			}
		}
	}

	/**
	 * @param string $group Field group.
	 * @param string $key Field key.
	 * @param string $label Label.
	 * @param bool   $required Required flag.
	 * @param int    $priority Field priority.
	 * @param bool   $protected Cannot be disabled.
	 * @return array<string,mixed>
	 */
	private function field( $group, $key, $label, $required, $priority, $protected = false ) {
		return array(
			'group'     => $group,
			'key'       => $key,
			'label'     => $label,
			'type'      => 'text',
			'options'   => array(),
			'placeholder' => '',
			'help'      => '',
			'width'     => 'default',
			'default_value' => '',
			'validation' => 'none',
			'min'       => '',
			'max'       => '',
			'min_length' => 0,
			'max_length' => 0,
			'required_message' => '',
			'validation_message' => '',
			'condition' => $this->default_condition(),
			'enabled'   => true,
			'required'  => (bool) $required,
			'priority'  => absint( $priority ),
			'protected' => (bool) $protected,
			'custom'    => false,
		);
	}

	/**
	 * @param array<string,mixed> $row Posted row.
	 * @param string              $key Field key.
	 * @return array<string,mixed>
	 */
	private function custom_base_field( array $row, $key ) {
		$type    = isset( $row['type'] ) ? sanitize_key( $row['type'] ) : 'text';
		$group   = isset( $row['group'] ) ? sanitize_key( $row['group'] ) : 'billing';
		$options = isset( $row['options'] ) && is_array( $row['options'] ) ? $row['options'] : array();

		if ( ! in_array( $type, array( 'text', 'textarea', 'select', 'checkbox', 'date' ), true ) ) {
			$type = 'text';
		}
		if ( ! in_array( $group, array( 'billing', 'shipping', 'order' ), true ) ) {
			$group = 'billing';
		}

		$clean_options = array();
		foreach ( $options as $option ) {
			$option = sanitize_text_field( wp_unslash( $option ) );
			if ( '' !== $option ) {
				$clean_options[ sanitize_title( $option ) ] = $option;
			}
		}

		return array(
			'group'     => $group,
			'key'       => 0 === strpos( $key, self::CUSTOM_PREFIX ) ? $key : self::CUSTOM_PREFIX . $key,
			'label'     => __( 'Custom field', 'checkflow' ),
			'type'      => $type,
			'options'   => $clean_options,
			'placeholder' => '',
			'help'      => '',
			'width'     => 'default',
			'default_value' => '',
			'validation' => 'none',
			'min'       => '',
			'max'       => '',
			'min_length' => 0,
			'max_length' => 0,
			'required_message' => '',
			'validation_message' => '',
			'condition' => $this->default_condition(),
			'enabled'   => true,
			'required'  => false,
			'priority'  => 999,
			'protected' => false,
			'custom'    => true,
		);
	}

	/**
	 * @param array<string,mixed> $config Config.
	 * @return array<string,mixed>
	 */
	private function checkout_field_args( array $config ) {
		$type = isset( $config['type'] ) ? (string) $config['type'] : 'text';
		$args = array(
			'type'     => $type,
			'label'    => (string) $config['label'],
			'required' => ! empty( $config['required'] ),
			'priority' => absint( $config['priority'] ),
		);
		if ( ! empty( $config['placeholder'] ) ) {
			$args['placeholder'] = (string) $config['placeholder'];
		}
		if ( ! empty( $config['help'] ) ) {
			$args['description'] = (string) $config['help'];
		}
		if ( ! empty( $config['default_value'] ) ) {
			$args['default'] = (string) $config['default_value'];
		}
		$args['class'] = $this->checkout_width_classes( isset( $config['width'] ) ? (string) $config['width'] : 'default', $type );
		if ( 'select' === $type ) {
			$args['options'] = array( '' => __( 'Choose an option', 'checkflow' ) ) + ( isset( $config['options'] ) && is_array( $config['options'] ) ? $config['options'] : array() );
		}
		return $args;
	}

	/**
	 * @param string $width Width option.
	 * @param string $type Field type.
	 * @return array<int,string>
	 */
	private function checkout_width_classes( $width, $type ) {
		if ( 'textarea' === $type || 'full' === $width ) {
			return array( 'form-row-wide' );
		}
		if ( 'first' === $width ) {
			return array( 'form-row-first' );
		}
		if ( 'last' === $width || 'half' === $width ) {
			return array( 'form-row-last' );
		}
		return array();
	}

	/**
	 * @param string              $value Field value.
	 * @param array<string,mixed> $config Field config.
	 * @return string Error message or empty string.
	 */
	private function validate_value( $value, array $config ) {
		$label              = isset( $config['label'] ) ? (string) $config['label'] : __( 'This field', 'checkflow' );
		$required_message   = isset( $config['required_message'] ) ? (string) $config['required_message'] : '';
		$validation_message = isset( $config['validation_message'] ) ? (string) $config['validation_message'] : '';
		$value              = trim( (string) $value );

		if ( ! empty( $config['required'] ) && '' === $value ) {
			return '' !== $required_message ? $required_message : sprintf(
				/* translators: %s: field label */
				__( '%s is required.', 'checkflow' ),
				$label
			);
		}

		if ( '' === $value ) {
			return '';
		}

		$validation = isset( $config['validation'] ) ? (string) $config['validation'] : 'none';
		if ( 'email' === $validation && ! is_email( $value ) ) {
			return '' !== $validation_message ? $validation_message : sprintf(
				/* translators: %s: field label */
				__( '%s must be a valid email address.', 'checkflow' ),
				$label
			);
		}
		if ( 'phone' === $validation && ! preg_match( '/^[0-9+\-\s().]{7,20}$/', $value ) ) {
			return '' !== $validation_message ? $validation_message : sprintf(
				/* translators: %s: field label */
				__( '%s must be a valid phone number.', 'checkflow' ),
				$label
			);
		}
		if ( 'number' === $validation ) {
			if ( ! is_numeric( $value ) ) {
				return '' !== $validation_message ? $validation_message : sprintf(
					/* translators: %s: field label */
					__( '%s must be a number.', 'checkflow' ),
					$label
				);
			}
			$number = (float) $value;
			if ( '' !== (string) ( $config['min'] ?? '' ) && $number < (float) $config['min'] ) {
				return '' !== $validation_message ? $validation_message : sprintf(
					/* translators: 1: field label, 2: minimum */
					__( '%1$s must be at least %2$s.', 'checkflow' ),
					$label,
					(string) $config['min']
				);
			}
			if ( '' !== (string) ( $config['max'] ?? '' ) && $number > (float) $config['max'] ) {
				return '' !== $validation_message ? $validation_message : sprintf(
					/* translators: 1: field label, 2: maximum */
					__( '%1$s must be no more than %2$s.', 'checkflow' ),
					$label,
					(string) $config['max']
				);
			}
		}
		if ( 'text' === $validation && ! preg_match( '/^[\p{L}\p{M}\s.\'-]+$/u', $value ) ) {
			return '' !== $validation_message ? $validation_message : sprintf(
				/* translators: %s: field label */
				__( '%s can only contain letters.', 'checkflow' ),
				$label
			);
		}

		$length     = function_exists( 'mb_strlen' ) ? mb_strlen( $value ) : strlen( $value );
		$min_length = isset( $config['min_length'] ) ? absint( $config['min_length'] ) : 0;
		$max_length = isset( $config['max_length'] ) ? absint( $config['max_length'] ) : 0;
		if ( $min_length && $length < $min_length ) {
			return '' !== $validation_message ? $validation_message : sprintf(
				/* translators: 1: field label, 2: minimum length */
				__( '%1$s must be at least %2$d characters.', 'checkflow' ),
				$label,
				$min_length
			);
		}
		if ( $max_length && $length > $max_length ) {
			return '' !== $validation_message ? $validation_message : sprintf(
				/* translators: 1: field label, 2: maximum length */
				__( '%1$s must be no more than %2$d characters.', 'checkflow' ),
				$label,
				$max_length
			);
		}

		return '';
	}

	/**
	 * @return array<string,string|bool>
	 */
	private function default_condition() {
		return array(
			'enabled'  => false,
			'action'   => 'show',
			'source'   => 'payment_method',
			'operator' => 'equals',
			'value'    => '',
			'field'    => '',
		);
	}

	/**
	 * @param array<string,mixed> $condition Raw condition.
	 * @return array<string,string|bool>
	 */
	private function sanitize_condition( array $condition ) {
		$out = $this->default_condition();
		$out['enabled'] = ! empty( $condition['enabled'] );
		$action = isset( $condition['action'] ) ? sanitize_key( $condition['action'] ) : 'show';
		$out['action'] = in_array( $action, array( 'show', 'hide' ), true ) ? $action : 'show';
		$source = isset( $condition['source'] ) ? sanitize_key( $condition['source'] ) : 'payment_method';
		$out['source'] = in_array( $source, array( 'payment_method', 'billing_country', 'shipping_country', 'field', 'cart_total', 'product_id', 'category_id' ), true ) ? $source : 'payment_method';
		$operator = isset( $condition['operator'] ) ? sanitize_key( $condition['operator'] ) : 'equals';
		$out['operator'] = in_array( $operator, array( 'equals', 'not_equals', 'contains', 'greater_equal', 'less_equal', 'checked', 'not_checked' ), true ) ? $operator : 'equals';
		$out['value'] = isset( $condition['value'] ) ? sanitize_text_field( wp_unslash( $condition['value'] ) ) : '';
		$out['field'] = isset( $condition['field'] ) ? sanitize_key( $condition['field'] ) : '';
		return $out;
	}

	/**
	 * @param array<string,mixed> $config Field config.
	 * @param array<string,mixed> $data Posted data.
	 * @return bool
	 */
	private function field_condition_visible( array $config, array $data ) {
		$condition = isset( $config['condition'] ) && is_array( $config['condition'] ) ? $config['condition'] : $this->default_condition();
		if ( empty( $condition['enabled'] ) ) {
			return true;
		}
		$matched = $this->condition_matches( $condition, $data );
		return 'hide' === ( $condition['action'] ?? 'show' ) ? ! $matched : $matched;
	}

	/**
	 * @param array<string,mixed> $condition Condition.
	 * @param array<string,mixed> $data Posted data.
	 * @return bool
	 */
	private function condition_matches( array $condition, array $data ) {
		$source = isset( $condition['source'] ) ? (string) $condition['source'] : '';
		$value  = isset( $condition['value'] ) ? (string) $condition['value'] : '';
		$actual = '';
		if ( 'payment_method' === $source ) {
			$actual = isset( $data['payment_method'] ) ? (string) $data['payment_method'] : ( isset( $_POST['payment_method'] ) ? (string) wp_unslash( $_POST['payment_method'] ) : '' );
		} elseif ( 'billing_country' === $source || 'shipping_country' === $source ) {
			$actual = isset( $data[ $source ] ) ? (string) $data[ $source ] : ( isset( $_POST[ $source ] ) ? (string) wp_unslash( $_POST[ $source ] ) : '' );
		} elseif ( 'field' === $source ) {
			$field  = isset( $condition['field'] ) ? sanitize_key( $condition['field'] ) : '';
			$actual = isset( $data[ $field ] ) ? (string) $data[ $field ] : ( isset( $_POST[ $field ] ) ? (string) wp_unslash( $_POST[ $field ] ) : '' );
		} elseif ( 'cart_total' === $source && function_exists( 'WC' ) && WC()->cart ) {
			$actual = (string) (float) WC()->cart->get_total( 'edit' );
		} elseif ( in_array( $source, array( 'product_id', 'category_id' ), true ) && function_exists( 'WC' ) && WC()->cart ) {
			$ids = array();
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;
				if ( 'product_id' === $source ) {
					$ids[] = $product_id;
					continue;
				}
				$terms = $product_id ? get_the_terms( $product_id, 'product_cat' ) : false;
				if ( is_array( $terms ) ) {
					foreach ( $terms as $term ) {
						$ids[] = absint( $term->term_id );
					}
				}
			}
			$actual = implode( ',', array_unique( array_filter( $ids ) ) );
		}
		return $this->compare_condition_values( $actual, $value, isset( $condition['operator'] ) ? (string) $condition['operator'] : 'equals' );
	}

	/**
	 * @param string $actual Actual value.
	 * @param string $expected Expected value.
	 * @param string $operator Operator.
	 * @return bool
	 */
	private function compare_condition_values( $actual, $expected, $operator ) {
		$actual = trim( (string) $actual );
		$expected = trim( (string) $expected );
		if ( 'checked' === $operator ) {
			return in_array( strtolower( $actual ), array( '1', 'yes', 'true', 'on' ), true );
		}
		if ( 'not_checked' === $operator ) {
			return ! in_array( strtolower( $actual ), array( '1', 'yes', 'true', 'on' ), true );
		}
		if ( 'greater_equal' === $operator ) {
			return is_numeric( $actual ) && is_numeric( $expected ) && (float) $actual >= (float) $expected;
		}
		if ( 'less_equal' === $operator ) {
			return is_numeric( $actual ) && is_numeric( $expected ) && (float) $actual <= (float) $expected;
		}
		if ( 'contains' === $operator ) {
			$parts = array_map( 'trim', explode( ',', $actual ) );
			return in_array( $expected, $parts, true ) || false !== stripos( $actual, $expected );
		}
		if ( 'not_equals' === $operator ) {
			return strcasecmp( $actual, $expected ) !== 0;
		}
		return strcasecmp( $actual, $expected ) === 0;
	}

	/**
	 * @param array<string,array<string,mixed>> $locale Locale fields.
	 * @return array<string,array<string,mixed>>
	 */
	private function apply_blocks_locale_order( array $locale ) {
		foreach ( $this->get_blocks_address_field_settings() as $field => $config ) {
			if ( ! isset( $locale[ $field ] ) || ! is_array( $locale[ $field ] ) ) {
				$locale[ $field ] = array();
			}
			$locale[ $field ]['priority'] = absint( $config['priority'] );
			$locale[ $field ]['label']    = (string) $config['label'];
			$locale[ $field ]['required'] = ! empty( $config['required'] );
			if ( empty( $config['enabled'] ) && empty( $config['protected'] ) ) {
				$locale[ $field ]['hidden'] = true;
			} else {
				unset( $locale[ $field ]['hidden'] );
			}
		}
		return $locale;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function get_blocks_address_field_settings() {
		$out = array();
		foreach ( $this->get_settings() as $key => $config ) {
			if ( ! empty( $config['custom'] ) ) {
				continue;
			}
			$group = isset( $config['group'] ) ? (string) $config['group'] : '';
			if ( ! in_array( $group, array( 'billing', 'shipping' ), true ) ) {
				continue;
			}
			$field = preg_replace( '/^(billing|shipping)_/', '', (string) $key );
			if ( '' === $field || ( isset( $out[ $field ] ) && 'billing' !== $group ) ) {
				continue;
			}
			$out[ $field ] = $config;
		}
		return $out;
	}

	/**
	 * @param string $key Field key.
	 * @return string
	 */
	private function block_field_id( $key ) {
		$name = preg_replace( '/^' . preg_quote( self::CUSTOM_PREFIX, '/' ) . '/', '', (string) $key );
		$name = sanitize_key( $name );
		return 'checkflow/' . $name;
	}

	/**
	 * @param string $group Internal group.
	 * @return string Blocks field location.
	 */
	private function block_field_location( $group ) {
		if ( 'billing' === $group || 'shipping' === $group ) {
			return 'address';
		}
		return 'order';
	}

	/**
	 * @param array<string,string> $options Options.
	 * @return array<int,array{value:string,label:string}>
	 */
	private function block_select_options( array $options ) {
		$out = array();
		foreach ( $options as $value => $label ) {
			$value = sanitize_key( $value );
			$label = sanitize_text_field( (string) $label );
			if ( '' === $value || '' === $label ) {
				continue;
			}
			$out[] = array(
				'value' => $value,
				'label' => $label,
			);
		}
		return $out;
	}

	/**
	 * @return array<string,array<string,mixed>>
	 */
	private function get_settings() {
		$defaults = $this->get_default_fields();
		$saved    = get_option( self::OPTION, array() );
		if ( ! is_array( $saved ) ) {
			$saved = array();
		}

		foreach ( $saved as $key => $field ) {
			if ( 0 === strpos( (string) $key, self::CUSTOM_PREFIX ) && is_array( $field ) ) {
				$defaults[ $key ] = array_merge( $this->custom_base_field( $field, (string) $key ), $field );
			}
		}

		foreach ( $defaults as $key => $field ) {
			if ( isset( $saved[ $key ] ) && is_array( $saved[ $key ] ) ) {
				$defaults[ $key ] = array_merge( $field, $saved[ $key ] );
			}
			if ( ! empty( $field['protected'] ) ) {
				$defaults[ $key ]['enabled'] = true;
			}
		}

		return $defaults;
	}
}

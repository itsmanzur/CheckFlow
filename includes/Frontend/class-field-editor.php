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
		$out      = $this->get_settings();

		foreach ( $in as $row ) {
			if ( ! is_array( $row ) || empty( $row['key'] ) ) {
				continue;
			}
			$key = sanitize_key( $row['key'] );
			if ( ! isset( $defaults[ $key ] ) ) {
				continue;
			}
			$protected = ! empty( $defaults[ $key ]['protected'] );
			$label     = isset( $row['label'] ) ? sanitize_text_field( wp_unslash( $row['label'] ) ) : $defaults[ $key ]['label'];
			$priority  = isset( $row['priority'] ) ? absint( $row['priority'] ) : absint( $defaults[ $key ]['priority'] );
			if ( '' === $label ) {
				$label = $defaults[ $key ]['label'];
			}
			$out[ $key ] = array_merge(
				$defaults[ $key ],
				array(
					'label'    => $label,
					'enabled'  => $protected ? true : ! empty( $row['enabled'] ),
					'required' => ! empty( $row['required'] ),
					'priority' => min( 999, max( 1, $priority ) ),
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
			if ( ! isset( $fields[ $group ][ $key ] ) ) {
				continue;
			}

			if ( empty( $config['enabled'] ) && empty( $config['protected'] ) ) {
				unset( $fields[ $group ][ $key ] );
				continue;
			}

			$fields[ $group ][ $key ]['label']    = (string) $config['label'];
			$fields[ $group ][ $key ]['required'] = ! empty( $config['required'] );
			$fields[ $group ][ $key ]['priority'] = absint( $config['priority'] );
		}

		return $fields;
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
			'enabled'   => true,
			'required'  => (bool) $required,
			'priority'  => absint( $priority ),
			'protected' => (bool) $protected,
		);
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

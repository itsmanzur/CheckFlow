<?php
/**
 * JSON-based strings, per-user admin locale, site-wide overrides.
 *
 * @package CheckFlow
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class CheckFlow_I18n
 */
final class CheckFlow_I18n {

	const META_ADMIN_LOCALE   = 'checkflow_admin_ui_locale';
	const OPT_OVERRIDES     = 'checkflow_string_overrides';
	const FALLBACK_LOCALE   = 'en_US';
	const SUPPORTED_LOCALES = array(
		'en_US',
		'bn_BD',
	);

	/** @var self|null */
	private static $instance;

	/** @var array<string, array<string, string>> */
	private $bundles = array();

	/** @var array|null */
	private $override_cache;

	/** @var string[] */
	private $keys_flat = array();

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

	public function init_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'wp_ajax_checkflow_set_admin_locale', array( $this, 'ajax_set_admin_locale' ) );
		add_action( 'wp_ajax_checkflow_save_string_overrides', array( $this, 'ajax_save_string_overrides' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'checkflow', false, dirname( plugin_basename( CHECKFLOW_PATH . 'checkflow.php' ) ) . '/languages' );
	}

	/**
	 * @return string[]
	 */
	public function get_flat_keys_sorted() {
		$this->keys_flat = array();
		$prefix = CHECKFLOW_PATH . 'i18n/';
		foreach ( glob( $prefix . '*.json' ) as $path ) {
			$data = $this->read_json_flat( $path );
			foreach ( array_keys( $data ) as $key ) {
				$this->keys_flat[ $key ] = true;
			}
		}
		$keys = array_keys( $this->keys_flat );
		natcasesort( $keys );
		return array_values( $keys );
	}

	private function load_bundle_for_locale( $locale ) {
		if ( isset( $this->bundles[ $locale ] ) ) {
			return;
		}
		$path = CHECKFLOW_PATH . 'i18n/' . $this->locale_to_filename( $locale ) . '.json';
		if ( ! is_readable( $path ) ) {
			$this->bundles[ $locale ] = array();
			return;
		}
		$this->bundles[ $locale ] = $this->read_json_flat( $path );
	}

	/**
	 * @param string $path Path to JSON.
	 * @return array<string, string>
	 */
	private function read_json_flat( $path ) {
		$json = json_decode( (string) file_get_contents( $path ), true );
		if ( ! is_array( $json ) ) {
			return array();
		}
		return $this->flatten_array( '', $json );
	}

	/**
	 * @param string $prefix Key prefix.
	 * @param array  $node   Nested or flat array.
	 * @return array<string, string>
	 */
	private function flatten_array( $prefix, array $node ) {
		$out = array();
		foreach ( $node as $k => $v ) {
			$key = $prefix !== '' ? $prefix . '.' . $k : (string) $k;
			if ( is_array( $v ) && $this->is_list( $v ) === false ) {
				$out = array_merge( $out, $this->flatten_array( $key, $v ) );
			} elseif ( is_scalar( $v ) ) {
				$out[ $key ] = (string) $v;
			}
		}
		return $out;
	}

	private function is_list( array $array ) {
		if ( defined( 'ARRAY_IS_LIST' ) && function_exists( 'array_is_list' ) ) {
			return array_is_list( $array );
		}
		$i = 0;
		foreach ( $array as $k => $_v ) {
			if ( $k !== $i++ ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param string $locale WP-style locale like en_US, bn_BD.
	 * @return string filename segment (en_US, bn_BD).
	 */
	private function locale_to_filename( $locale ) {
		return str_replace( array( '/', '\\' ), '', $locale );
	}

	/**
	 * Resolves string for optional explicit locale or current user's admin preference.
	 *
	 * @param string      $key    Dot key (e.g. nav.dashboard).
	 * @param string|null $locale Optional locale.
	 * @return string
	 */
	public function resolve( $key, $locale = null ) {
		$key = (string) $key;
		$locale = $locale ? $locale : $this->get_active_admin_locale();
		$this->load_bundle_for_locale( $locale );
		$this->load_bundle_for_locale( self::FALLBACK_LOCALE );
		if ( null === $this->override_cache ) {
			$this->override_cache = get_option( self::OPT_OVERRIDES, array() );
			if ( ! is_array( $this->override_cache ) ) {
				$this->override_cache = array();
			}
		}

		foreach ( array( $locale, self::FALLBACK_LOCALE ) as $lng ) {
			if ( isset( $this->override_cache[ $lng ][ $key ] ) && '' !== (string) $this->override_cache[ $lng ][ $key ] ) {
				return (string) $this->override_cache[ $lng ][ $key ];
			}
		}
		foreach ( array( $locale, self::FALLBACK_LOCALE ) as $lng ) {
			if ( isset( $this->bundles[ $lng ][ $key ] ) ) {
				return (string) $this->bundles[ $lng ][ $key ];
			}
		}

		return $key;
	}

	/**
	 * @return string
	 */
	public function get_active_admin_locale() {
		if ( is_user_logged_in() ) {
			$user_locale = (string) get_user_meta( get_current_user_id(), self::META_ADMIN_LOCALE, true );
			if ( $user_locale !== '' && in_array( $user_locale, self::SUPPORTED_LOCALES, true ) ) {
				return $user_locale;
			}
		}
		$dl = function_exists( 'determine_locale' ) ? determine_locale() : get_locale();
		$b  = strtolower( (string) $dl );
		if ( false !== strpos( $b, 'bn_' ) || 'bn_bd' === $b || 'bn' === $b ) {
			return 'bn_BD';
		}
		return self::FALLBACK_LOCALE;
	}

	/**
	 * Dropdown labels keyed by locale.
	 *
	 * @return array<string, string>
	 */
	public function locale_choices_labels() {
		return array(
			'en_US' => $this->raw_bundle_value( self::FALLBACK_LOCALE, 'lang.name_en_US' ) ?: 'English',
			'bn_BD' => $this->raw_bundle_value( 'bn_BD', 'lang.name_bn_BD' ) ?: 'Bangla',
		);
	}

	/**
	 * For settings page: bundled value + overlay for editor.
	 *
	 * @param string $key Key.
	 * @param string $locale Locale.
	 * @return array{bundle:string,override:string}
	 */
	public function get_bundle_and_override_row( $key, $locale ) {
		$this->load_bundle_for_locale( $locale );
		if ( null === $this->override_cache ) {
			$this->override_cache = get_option( self::OPT_OVERRIDES, array() );
			if ( ! is_array( $this->override_cache ) ) {
				$this->override_cache = array();
			}
		}
		$b = isset( $this->bundles[ $locale ][ $key ] ) ? $this->bundles[ $locale ][ $key ] : '';
		if ( '' === $b && $locale !== self::FALLBACK_LOCALE ) {
			$this->load_bundle_for_locale( self::FALLBACK_LOCALE );
			$b = isset( $this->bundles[ self::FALLBACK_LOCALE ][ $key ] )
				? $this->bundles[ self::FALLBACK_LOCALE ][ $key ]
				: '';
		}
		$o = isset( $this->override_cache[ $locale ][ $key ] ) ? (string) $this->override_cache[ $locale ][ $key ] : '';
		return array(
			'bundle'   => $b,
			'override' => $o,
		);
	}

	public function ajax_set_admin_locale() {
		check_ajax_referer( 'checkflow_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'msg' => 'forbidden' ), 403 );
		}
		$locale = isset( $_POST['locale'] ) ? sanitize_text_field( wp_unslash( $_POST['locale'] ) ) : '';
		if ( ! in_array( $locale, self::SUPPORTED_LOCALES, true ) ) {
			wp_send_json_error( array( 'msg' => 'bad_locale' ), 400 );
		}
		update_user_meta( get_current_user_id(), self::META_ADMIN_LOCALE, $locale );
		wp_send_json_success( array( 'locale' => $locale ) );
	}

	public function ajax_save_string_overrides() {
		check_ajax_referer( 'checkflow_admin', 'nonce' );
		if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'msg' => 'forbidden' ), 403 );
		}
		$raw_locale = isset( $_POST['target_locale'] ) ? sanitize_text_field( wp_unslash( $_POST['target_locale'] ) ) : '';
		if ( ! in_array( $raw_locale, self::SUPPORTED_LOCALES, true ) ) {
			wp_send_json_error( array( 'msg' => 'bad_locale' ), 400 );
		}
		$payload_json = isset( $_POST['overrides'] ) ? wp_unslash( $_POST['overrides'] ) : '{}';
		$decoded = json_decode( $payload_json, true );
		if ( ! is_array( $decoded ) ) {
			wp_send_json_error( array( 'msg' => 'bad_json' ), 400 );
		}
		$keyed = array();
		foreach ( $decoded as $k => $val ) {
			$k = is_string( $k ) ? $k : (string) $k;
			if ( ! preg_match( '/^[a-zA-Z0-9._-]{1,240}$/', $k ) ) {
				continue;
			}
			$keyed[ $k ] = wp_kses_post( is_scalar( $val ) ? (string) $val : '' );
		}

		$all = get_option( self::OPT_OVERRIDES, array() );
		if ( ! is_array( $all ) ) {
			$all = array();
		}
		$prev = isset( $all[ $raw_locale ] ) && is_array( $all[ $raw_locale ] ) ? $all[ $raw_locale ] : array();
		foreach ( $keyed as $k => $v ) {
			if ( '' === $v ) {
				unset( $prev[ $k ] );
				continue;
			}
			$prev[ $k ] = $v;
		}
		$all[ $raw_locale ] = $prev;
		update_option( self::OPT_OVERRIDES, $all, false );
		$this->override_cache = $all;

		wp_send_json_success();
	}

	/**
	 * Clear circular dependency for lang names on first bootstrap.
	 */
	public function raw_bundle_value( $locale, $key ) {
		$this->load_bundle_for_locale( $locale );
		return isset( $this->bundles[ $locale ][ $key ] ) ? $this->bundles[ $locale ][ $key ] : '';
	}
}

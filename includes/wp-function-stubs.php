<?php
/**
 * WordPress Function Stubs (CLI + Tests)
 *
 * Minimal implementations of WordPress globals used by Translation Bridge
 * outside a WP runtime (CLI, PHPUnit). All definitions are guarded with
 * function_exists / class_exists so this file is safe to load alongside a
 * real WordPress environment.
 *
 * Used by:
 *   - /devtb-php (CLI entrypoint)
 *   - /tests/bootstrap.php (PHPUnit)
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Compat
 * @version 4.7.0
 */

// ---------------------------------------------------------------------------
// Constants
// ---------------------------------------------------------------------------
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) { define( 'MINUTE_IN_SECONDS', 60 ); }
if ( ! defined( 'HOUR_IN_SECONDS' ) )   { define( 'HOUR_IN_SECONDS', 60 * MINUTE_IN_SECONDS ); }
if ( ! defined( 'DAY_IN_SECONDS' ) )    { define( 'DAY_IN_SECONDS', 24 * HOUR_IN_SECONDS ); }
if ( ! defined( 'WEEK_IN_SECONDS' ) )   { define( 'WEEK_IN_SECONDS', 7 * DAY_IN_SECONDS ); }
if ( ! defined( 'MONTH_IN_SECONDS' ) )  { define( 'MONTH_IN_SECONDS', 30 * DAY_IN_SECONDS ); }
if ( ! defined( 'YEAR_IN_SECONDS' ) )   { define( 'YEAR_IN_SECONDS', 365 * DAY_IN_SECONDS ); }

// ---------------------------------------------------------------------------
// Hooks
// ---------------------------------------------------------------------------
if ( ! function_exists( 'add_action' ) ) {
	function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) { return true; }
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) { return true; }
}
if ( ! function_exists( 'do_action' ) ) {
	function do_action( $hook, ...$args ) { return true; }
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( $hook, $value, ...$args ) { return $value; }
}

// ---------------------------------------------------------------------------
// Options (in-memory)
// ---------------------------------------------------------------------------
if ( ! isset( $GLOBALS['__devtb_stub_options'] ) ) {
	$GLOBALS['__devtb_stub_options'] = array();
}
if ( ! function_exists( 'get_option' ) ) {
	function get_option( $option, $default = false ) {
		return $GLOBALS['__devtb_stub_options'][ $option ] ?? $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( $option, $value ) {
		$GLOBALS['__devtb_stub_options'][ $option ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_option' ) ) {
	function delete_option( $option ) {
		unset( $GLOBALS['__devtb_stub_options'][ $option ] );
		return true;
	}
}

// ---------------------------------------------------------------------------
// Transients (in-memory; TTL ignored for test purposes)
// ---------------------------------------------------------------------------
if ( ! isset( $GLOBALS['__devtb_stub_transients'] ) ) {
	$GLOBALS['__devtb_stub_transients'] = array();
}
if ( ! function_exists( 'get_transient' ) ) {
	function get_transient( $key ) {
		return $GLOBALS['__devtb_stub_transients'][ $key ] ?? false;
	}
}
if ( ! function_exists( 'set_transient' ) ) {
	function set_transient( $key, $value, $expiration = 0 ) {
		$GLOBALS['__devtb_stub_transients'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'delete_transient' ) ) {
	function delete_transient( $key ) {
		unset( $GLOBALS['__devtb_stub_transients'][ $key ] );
		return true;
	}
}

// ---------------------------------------------------------------------------
// Cron (no-op in CLI / tests; jobs scheduled here are not actually executed)
// ---------------------------------------------------------------------------
if ( ! function_exists( 'wp_schedule_single_event' ) ) {
	function wp_schedule_single_event( $timestamp, $hook, $args = array(), $wp_error = false ) { return true; }
}
if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( $timestamp, $recurrence, $hook, $args = array(), $wp_error = false ) { return true; }
}
if ( ! function_exists( 'wp_next_scheduled' ) ) {
	function wp_next_scheduled( $hook, $args = array() ) { return false; }
}
if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( $hook, $args = array(), $wp_error = false ) { return 0; }
}

// ---------------------------------------------------------------------------
// Time
// ---------------------------------------------------------------------------
if ( ! function_exists( 'current_time' ) ) {
	function current_time( $type = 'mysql', $gmt = 0 ) {
		if ( 'timestamp' === $type || 'U' === $type ) {
			return time();
		}
		return $gmt ? gmdate( 'Y-m-d H:i:s' ) : date( 'Y-m-d H:i:s' );
	}
}

// ---------------------------------------------------------------------------
// I18n
// ---------------------------------------------------------------------------
if ( ! function_exists( '__' ) ) {
	function __( $text, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( '_e' ) ) {
	function _e( $text, $domain = 'default' ) { echo $text; }
}
if ( ! function_exists( '_n' ) ) {
	function _n( $single, $plural, $number, $domain = 'default' ) {
		return 1 === (int) $number ? $single : $plural;
	}
}
if ( ! function_exists( '_x' ) ) {
	function _x( $text, $context, $domain = 'default' ) { return $text; }
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr__' ) ) {
	function esc_attr__( $text, $domain = 'default' ) {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}

// ---------------------------------------------------------------------------
// Escaping / sanitization
// ---------------------------------------------------------------------------
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url' ) ) {
	function esc_url( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( $url ) {
		return filter_var( (string) $url, FILTER_SANITIZE_URL );
	}
}
if ( ! function_exists( 'esc_textarea' ) ) {
	function esc_textarea( $text ) {
		return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'wp_kses_post' ) ) {
	function wp_kses_post( $data ) { return $data; }
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $str ) { return strip_tags( (string) $str ); }
}
if ( ! function_exists( 'sanitize_key' ) ) {
	function sanitize_key( $key ) {
		return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
	}
}
if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( $title, $fallback_title = '', $context = 'save' ) {
		return strtolower( preg_replace( '/[^a-z0-9\-]+/i', '-', (string) $title ) );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ) { return abs( (int) $value ); }
}
if ( ! function_exists( 'wp_unslash' ) ) {
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

// ---------------------------------------------------------------------------
// JSON / serialization
// ---------------------------------------------------------------------------
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, $options = 0, $depth = 512 ) {
		return json_encode( $data, $options, $depth );
	}
}
if ( ! function_exists( 'is_serialized' ) ) {
	function is_serialized( $data, $strict = true ) {
		if ( ! is_string( $data ) ) { return false; }
		$data = trim( $data );
		if ( 'N;' === $data ) { return true; }
		if ( strlen( $data ) < 4 ) { return false; }
		if ( ':' !== $data[1] ) { return false; }
		if ( $strict ) {
			$lastc = substr( $data, -1 );
			if ( ';' !== $lastc && '}' !== $lastc ) { return false; }
		} else {
			$semicolon = strpos( $data, ';' );
			$brace     = strpos( $data, '}' );
			if ( false === $semicolon && false === $brace ) { return false; }
			if ( false !== $semicolon && $semicolon < 3 ) { return false; }
			if ( false !== $brace && $brace < 4 ) { return false; }
		}
		$token = $data[0];
		switch ( $token ) {
			case 's':
				if ( $strict ) {
					if ( '"' !== substr( $data, -2, 1 ) ) { return false; }
				} elseif ( ! str_contains( $data, '"' ) ) {
					return false;
				}
				// fall through
			case 'a':
			case 'O':
			case 'E':
				return (bool) preg_match( "/^{$token}:[0-9]+:/s", $data );
			case 'b':
			case 'i':
			case 'd':
				$end = $strict ? '$' : '';
				return (bool) preg_match( "/^{$token}:[0-9.E+-]+;{$end}/", $data );
		}
		return false;
	}
}
if ( ! function_exists( 'maybe_unserialize' ) ) {
	function maybe_unserialize( $data ) {
		if ( is_serialized( $data ) ) {
			return @unserialize( trim( $data ) );
		}
		return $data;
	}
}

// ---------------------------------------------------------------------------
// Shortcodes
// ---------------------------------------------------------------------------
if ( ! function_exists( 'do_shortcode' ) ) {
	function do_shortcode( $content ) { return $content; }
}
if ( ! function_exists( 'shortcode_atts' ) ) {
	function shortcode_atts( $pairs, $atts, $shortcode = '' ) {
		$atts = (array) $atts;
		$out  = array();
		foreach ( $pairs as $name => $default ) {
			$out[ $name ] = array_key_exists( $name, $atts ) ? $atts[ $name ] : $default;
		}
		return $out;
	}
}
if ( ! function_exists( 'get_shortcode_regex' ) ) {
	function get_shortcode_regex( $tagnames = null ) {
		$tagregexp = '[a-zA-Z0-9_-]+';
		if ( null !== $tagnames && is_array( $tagnames ) ) {
			$tagregexp = implode( '|', array_map( 'preg_quote', $tagnames ) );
		}
		return '\\['
			. '(\\[?)'
			. "($tagregexp)"
			. '(?![\\w-])'
			. '('
			.     '[^\\]\\/]*'
			.     '(?:'
			.         '\\/(?!\\])'
			.         '[^\\]\\/]*'
			.     ')*?'
			. ')'
			. '(?:'
			.     '(\\/)'
			.     '\\]'
			. '|'
			.     '\\]'
			.     '(?:'
			.         '('
			.             '[^\\[]*+'
			.             '(?:'
			.                 '\\[(?!\\/\\2\\])'
			.                 '[^\\[]*+'
			.             ')*+'
			.         ')'
			.         '\\[\\/\\2\\]'
			.     ')?'
			. ')'
			. '(\\]?)';
	}
}
if ( ! function_exists( 'shortcode_parse_atts' ) ) {
	function shortcode_parse_atts( $text ) {
		$atts    = array();
		$pattern = '/(\w+)\s*=\s*"([^"]*)"|(\w+)\s*=\s*\'([^\']*)\'|(\w+)\s*=\s*([^\s\]]+)/';
		if ( preg_match_all( $pattern, (string) $text, $matches, PREG_SET_ORDER ) ) {
			foreach ( $matches as $match ) {
				if ( ! empty( $match[1] ) ) {
					$atts[ $match[1] ] = $match[2];
				} elseif ( ! empty( $match[3] ) ) {
					$atts[ $match[3] ] = $match[4];
				} elseif ( ! empty( $match[5] ) ) {
					$atts[ $match[5] ] = $match[6];
				}
			}
		}
		return $atts;
	}
}
if ( ! function_exists( 'strip_shortcodes' ) ) {
	function strip_shortcodes( $content ) {
		if ( empty( $content ) ) { return $content; }
		return preg_replace( '/\[[^\]]+\]/', '', $content );
	}
}

// ---------------------------------------------------------------------------
// REST + WP_Error classes
// ---------------------------------------------------------------------------
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ) {
		return ( $thing instanceof \WP_Error );
	}
}

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		private $errors     = array();
		private $error_data = array();

		public function __construct( $code = '', $message = '', $data = '' ) {
			if ( empty( $code ) ) { return; }
			$this->errors[ $code ][] = $message;
			if ( ! empty( $data ) ) {
				$this->error_data[ $code ] = $data;
			}
		}
		public function get_error_code() { return key( $this->errors ); }
		public function get_error_message( $code = '' ) {
			if ( empty( $code ) ) { $code = $this->get_error_code(); }
			return $this->errors[ $code ][0] ?? '';
		}
		public function get_error_data( $code = '' ) {
			if ( empty( $code ) ) { $code = $this->get_error_code(); }
			return $this->error_data[ $code ] ?? null;
		}
	}
}

if ( ! class_exists( 'WP_REST_Request' ) ) {
	class WP_REST_Request {
		private $params  = array();
		private $headers = array();

		public function __construct( $method = 'GET', $route = '', $params = array() ) {
			$this->params = $params;
		}
		public function get_param( $key ) { return $this->params[ $key ] ?? null; }
		public function get_params() { return $this->params; }
		public function set_param( $key, $value ) { $this->params[ $key ] = $value; }
		public function get_header( $key ) { return $this->headers[ $key ] ?? null; }
		public function set_header( $key, $value ) { $this->headers[ $key ] = $value; }
	}
}

if ( ! class_exists( 'WP_REST_Response' ) ) {
	class WP_REST_Response {
		private $data;
		private $status;

		public function __construct( $data = null, $status = 200 ) {
			$this->data   = $data;
			$this->status = $status;
		}
		public function get_data() { return $this->data; }
		public function get_status() { return $this->status; }
	}
}

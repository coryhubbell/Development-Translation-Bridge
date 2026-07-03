<?php
/**
 * DEVTB Shared Autoloader
 *
 * Resolves both namespaced classes under DEVTB\TranslationBridge\... and the
 * non-namespaced global DEVTB_* classes shipped in includes/. Registered from
 * the CLI entrypoint (devtb-php), the PHPUnit bootstrap, and functions.php so
 * the three environments stay in lockstep.
 *
 * @package DevelopmentTranslation_Bridge
 * @subpackage Autoload
 * @version 4.4.0
 */

if ( ! function_exists( 'devtb_class_autoload' ) ) {

	/**
	 * Resolve a class name to a file on disk and require it.
	 *
	 * @param string $class Fully-qualified class name.
	 * @return void
	 */
	function devtb_class_autoload( $class ) {
		if ( ! is_string( $class ) || '' === $class ) {
			return;
		}

		// Strip namespace; keep the short class name only.
		$pos   = strrpos( $class, '\\' );
		$short = false === $pos ? $class : substr( $class, $pos + 1 );

		$base = str_replace( '_', '-', strtolower( $short ) );
		// "devtb-kadence-converter" -> "kadence-converter" (DEVTB_ prefix is conventional).
		$base = preg_replace( '/^devtb-/', '', $base );

		// "parser-interface" -> "parser" (file is interface-parser.php, class is DEVTB_Parser_Interface).
		$iface_base = preg_replace( '/-interface$/', '', $base );

		if ( ! defined( 'DEVTB_INCLUDES' ) || ! defined( 'DEVTB_TRANSLATION_BRIDGE' ) ) {
			return;
		}

		$locations = array(
			DEVTB_INCLUDES . '/class-devtb-' . $base . '.php',
			DEVTB_INCLUDES . '/class-' . $base . '.php',
			DEVTB_TRANSLATION_BRIDGE . '/core/class-' . $base . '.php',
			DEVTB_TRANSLATION_BRIDGE . '/core/interface-' . $iface_base . '.php',
			DEVTB_TRANSLATION_BRIDGE . '/parsers/class-' . $base . '.php',
			DEVTB_TRANSLATION_BRIDGE . '/converters/class-' . $base . '.php',
			DEVTB_TRANSLATION_BRIDGE . '/models/class-' . $base . '.php',
			DEVTB_TRANSLATION_BRIDGE . '/utils/class-' . $base . '.php',
		);

		foreach ( $locations as $file ) {
			if ( file_exists( $file ) ) {
				require_once $file;
				return;
			}
		}
	}
}

if ( ! function_exists( 'devtb_register_autoloader' ) ) {

	/**
	 * Register devtb_class_autoload exactly once.
	 *
	 * @return void
	 */
	function devtb_register_autoloader() {
		static $registered = false;
		if ( $registered ) {
			return;
		}
		spl_autoload_register( 'devtb_class_autoload' );
		$registered = true;
	}
}

<?php
/**
 * Initialize the plugin
 *
 * @package RVOLA\WOO\SFR
 **/

namespace RVOLA\WOO\SFR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WP
 *
 * @package RVOLA\WOO\SFR
 */
class WP {

	/**
	 * Singleton
	 *
	 * @var singleton.
	 */
	private static $_singleton = null;

	/**
	 * WooSFR constructor.
	 */
	public function __construct() {
		add_action( 'init', array( $this, 'load_languages' ), 10 );
		add_filter( 'plugin_row_meta', array( $this, 'plugin_row_meta' ), 10, 2 );

		// Start Report.
		require_once dirname( __FILE__ ) . '/class-report.php';
		new Report();
	}

	/**
	 * Singleton.
	 *
	 * @return mixed
	 */
	public static function instance() {
		if ( is_null( self::$_singleton ) ) {
			self::$_singleton = new self();
		}

		return self::$_singleton;
	}

	/**
	 * Load language files
	 */
	public function load_languages() {

		load_plugin_textdomain( 'woo-stripe-fee-in-report', false, dirname( __FILE__ ) . '/languages' );
	}

	/**
	 * Add links in the list of plugins.
	 *
	 * @param array  $plugin_meta An array of the plugin's metadata, including the version, author, author URI, and plugin URI.
	 * @param string $plugin_file Path to the plugin file, relative to the plugins directory.
	 *
	 * @return mixed
	 */
	public function plugin_row_meta( $plugin_meta, $plugin_file ) {
		if ( plugin_basename( 'woo-stripe-fee-in-report/woo-stripe-fee-in-report.php' ) === $plugin_file ) {
			array_push(
				$plugin_meta,
				sprintf(
					'<a href="https://www.paypal.me/rvola" target="_blank">%s</a>',
					__( 'Donate', 'woo-stripe-fee-in-report' )
				)
			);
		}

		return $plugin_meta;
	}
}

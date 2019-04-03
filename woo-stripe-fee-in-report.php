<?php
/**
 * Plugin Name:             WooCommerce Stripe fee in Report
 * Plugin URI:              https://github.com/rvola/woo-stripe-fee-in-report
 *
 * Description:             Shows Stripe fees in WooCommerce charts. Calculates a correct net total.
 *
 * Version:                 1.2.1
 * Revision:                2019-04-03
 * Creation:                2018-02-24
 *
 * Author:                  studio RVOLA
 * Author URI:              https://www.rvola.com
 *
 * Text Domain:             woo-stripe-fee-in-report
 * Domain Path:             /languages
 *
 * Requires at least:       4.4
 * Tested up to:            5.2
 * Requires PHP:            7.0
 * WC requires at least:    3.1
 * WC tested up to:         3.6
 *
 * License:                 GNU General Public License v3.0
 * License URI:             https://www.gnu.org/licenses/gpl-3.0.html
 **/

namespace RVOLA\WOO\SFR;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'WOOSFR_FILE', __FILE__ );

include_once ABSPATH . 'wp-admin/includes/plugin.php';
// Check if WooCommerce and WooCommerce Gateway Stripe is loaded.
if (
	is_plugin_active( 'woocommerce/woocommerce.php' )
	&& is_plugin_active( 'woocommerce-gateway-stripe/woocommerce-gateway-stripe.php' )
) {
	require_once dirname( WOOSFR_FILE ) . '/includes/class-wp.php';
	add_action( 'plugins_loaded', array( 'RVOLA\WOO\SFR\WP', 'instance' ), 10 );
}

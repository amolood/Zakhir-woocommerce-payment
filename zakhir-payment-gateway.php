<?php
/**
 * Plugin Name:       Zakhir Payment Gateway
 * Plugin URI:        https://github.com/amolood/Zakhir-Woocommerce
 * Description:       Accept payments via Zakhir wallet in your WooCommerce store.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Author:            Abdalrahman Molood
 * Author URI:        https://amolood.com
 * License:           MIT
 * License URI:       https://opensource.org/licenses/MIT
 * Text Domain:       zakhir-payment-gateway
 * Domain Path:       /languages
 * WC requires at least: 7.0
 * WC tested up to:      9.0
 */

defined( 'ABSPATH' ) || exit;

define( 'ZAKHIR_WC_VERSION', '1.0.0' );
define( 'ZAKHIR_WC_FILE',    __FILE__ );
define( 'ZAKHIR_WC_PATH',    plugin_dir_path( __FILE__ ) );
define( 'ZAKHIR_WC_URL',     plugin_dir_url( __FILE__ ) );

// Declare HPOS compatibility.
add_action( 'before_woocommerce_init', function () {
    if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
        \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
            'custom_order_tables',
            __FILE__,
            true
        );
    }
} );

add_action( 'plugins_loaded', function () {
    if ( ! class_exists( 'WooCommerce' ) ) {
        add_action( 'admin_notices', function () {
            echo '<div class="error"><p>'
                . esc_html__( 'Zakhir Payment Gateway requires WooCommerce to be installed and active.', 'zakhir-payment-gateway' )
                . '</p></div>';
        } );
        return;
    }

    require_once ZAKHIR_WC_PATH . 'includes/class-zakhir-api.php';
    require_once ZAKHIR_WC_PATH . 'includes/class-zakhir-webhook-handler.php';
    require_once ZAKHIR_WC_PATH . 'includes/class-wc-zakhir-gateway.php';

    // Register the gateway.
    add_filter( 'woocommerce_payment_gateways', function ( array $gateways ): array {
        $gateways[] = WC_Zakhir_Gateway::class;
        return $gateways;
    } );

    // Register webhook endpoint.
    Zakhir_Webhook_Handler::init();

    // Load translations.
    load_plugin_textdomain(
        'zakhir-payment-gateway',
        false,
        dirname( plugin_basename( __FILE__ ) ) . '/languages'
    );
} );

// Add Settings link on the plugins page.
add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), function ( array $links ): array {
    $settings_url = admin_url( 'admin.php?page=wc-settings&tab=checkout&section=zakhir' );
    array_unshift( $links, '<a href="' . esc_url( $settings_url ) . '">' . esc_html__( 'Settings', 'zakhir-payment-gateway' ) . '</a>' );
    return $links;
} );

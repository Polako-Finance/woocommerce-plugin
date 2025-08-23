<?php
/**
 * Plugin Name: Polako Gateway for WooCommerce
 * Description: Accept payments in Serbia with ease
 * Author: Polako Finance
 * Author URI: https://polako-finance.com
 * License: GPL-3.0
 * Version: 0.1.0
 * Requires Plugins: woocommerce
 * Requires at least: 6.7
 * Tested up to: 6.8
 * WC requires at least: 9.9
 * WC tested up to: 10.1
 * Requires PHP: 7.4
 * PHP tested up to: 8.3
 *
 * @package Polako Gateway for WooCommerce
 */

defined('ABSPATH') || exit();

define('WC_GATEWAY_POLAKO_VERSION', '0.1.0');
define('WC_GATEWAY_POLAKO_URL', untrailingslashit(plugins_url('/', __FILE__)));
define('WC_GATEWAY_POLAKO_PATH', untrailingslashit(plugin_dir_path(__FILE__)));

/**
 * Initialize the gateway
 * @noinspection PhpUnused
 */
function woocommerce_polako_init(): void
{
	if (!class_exists('WC_Payment_Gateway')) {
		return;
	}

	require_once plugin_basename('includes/class-wc-gateway-polako.php');
	add_filter('woocommerce_payment_gateways', 'woocommerce_polako_add_gateway');
}

add_action('plugins_loaded', 'woocommerce_polako_init', 0);

/**
 * Add the gateway to WooCommerce
 * @noinspection PhpUnused
 */
function woocommerce_polako_add_gateway(array $methods): array
{
	$methods[] = 'WC_Gateway_Polako';
	return $methods;
}

add_action('woocommerce_blocks_loaded', 'woocommerce_polako_woocommerce_blocks_support');

/**
 * Add the gateway to WooCommerce Blocks
 * @noinspection PhpUnused
 * @noinspection PhpMissingReturnTypeInspection
 */
function woocommerce_polako_woocommerce_blocks_support()
{
	if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType')) {
		require_once WC_GATEWAY_POLAKO_PATH . '/includes/class-wc-gateway-polako-blocks-support.php';
		add_action('woocommerce_blocks_payment_method_type_registration', function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
			$payment_method_registry->register(new WC_Polako_Blocks_Support());
		});
	}
}

/** Declare compatibility with WooCommerce features */
add_action('before_woocommerce_init', function () {
	if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
		/** @noinspection PhpFullyQualifiedNameUsageInspection */
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
		/** @noinspection PhpFullyQualifiedNameUsageInspection */
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('custom_order_tables', __FILE__, true);
	}
});

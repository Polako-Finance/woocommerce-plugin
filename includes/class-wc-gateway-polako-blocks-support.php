<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

final class WC_Polako_Blocks_Support extends AbstractPaymentMethodType
{
	protected $name = 'polako';

	/** Initialize the payment method type */
	public function initialize()
	{
		$this->settings = get_option('woocommerce_polako_settings', []);
	}

	/** Return whether this payment method should be active. If false, the scripts will not be enqueued. */
	public function is_active()
	{
		$payment_gateways_class = WC()->payment_gateways();
		$payment_gateways = $payment_gateways_class->payment_gateways();

		return $payment_gateways['polako']->is_available();
	}

	/** Return an array of scripts/handles to be registered for this payment method */
	public function get_payment_method_script_handles()
	{
		$asset_path = WC_GATEWAY_POLAKO_PATH . '/build/payment-method.asset.php';
		$version = WC_GATEWAY_POLAKO_VERSION;
		$dependencies = [];
		if (file_exists($asset_path)) {
			$asset = require $asset_path;
			$version = is_array($asset) && isset($asset['version']) ? $asset['version'] : $version;
			$dependencies = is_array($asset) && isset($asset['dependencies']) ? $asset['dependencies'] : $dependencies;
		}
		wp_register_script('wc-polako-blocks-integration', WC_GATEWAY_POLAKO_URL . '/build/payment-method.js', $dependencies, $version, true);
		wp_set_script_translations('wc-polako-blocks-integration', 'polako-gateway-for-woocommerce');
		return ['wc-polako-blocks-integration'];
	}

	/** Return an array of key=>value pairs of data made available to the payment methods script */
	public function get_payment_method_data()
	{
		return [
			'title' => $this->get_setting('title'),
			'description' => $this->get_setting('description'),
			'supports' => $this->get_supported_features(),
			'logo_url' => WC_GATEWAY_POLAKO_URL . '/assets/images/icon.png',
		];
	}

	/** Return an array of supported features */
	public function get_supported_features()
	{
		$payment_gateways = WC()->payment_gateways->payment_gateways();
		return $payment_gateways['polako']->supports;
	}
}

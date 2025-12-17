<?php
/**
 * Polako Finance Payment Gateway
 *
 * @package Polako Gateway for WooCommerce
 */

use Automattic\WooCommerce\Enums\OrderStatus;

defined('ABSPATH') || exit();

/** Polako Finance Payment Gateway */
class WC_Gateway_Polako extends WC_Payment_Gateway
{
	protected $platform_id;

	protected $api_key;

	/** @var string Gateway URL; see possible values in the constructor */
	protected $url;

	/** @var WC_Logger Logger; exists only in Test Mode */
	protected $logger;

	/** @var array Order statuses that can be updated */
	protected const ACCEPT_STATUSES = [OrderStatus::PENDING, OrderStatus::FAILED, OrderStatus::ON_HOLD];

	public function __construct()
	{
		$this->id = 'polako';
		$this->method_title = __('Polako Finance', 'polako-gateway-for-woocommerce');
		$this->method_description = sprintf(__('Safe and secure payments in Serbia with Polako Finance.', 'polako-gateway-for-woocommerce'));
		$this->icon = WC_GATEWAY_POLAKO_URL . '/assets/images/icon.png';
		// Declare supported functionality.
		$this->supports = ['products'];

		$this->init_form_fields();
		$this->init_settings();

		// Set up merchant data.
		$this->platform_id = $this->get_option('platform_id');
		$this->api_key = $this->get_option('api_key');
		$this->url = 'https://backend.polako-finance.com/api/session/signed';
		$this->title = $this->get_option('title');
		$this->description = $this->get_option('description');
		$this->enabled = 'yes' === $this->get_option('enabled') ? 'yes' : 'no';

		// Change the Gateway URL when in Test Mode.
		if ('yes' === $this->get_option('testmode')) {
			$this->url = 'https://stage.infra.polako-finance.com/payment-gateway/api/session/signed';
			$this->add_testmode_admin_settings_notice();
		}

		add_action('woocommerce_api_wc_gateway_polako', [$this, 'capture_gateway_callback']);
		add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
		add_action('admin_notices', [$this, 'admin_notices']);
	}

	/** Initialize the settings form */
	public function init_form_fields()
	{
		$this->form_fields = [
			'enabled' => [
				'title' => __('Enable/Disable', 'polako-gateway-for-woocommerce'),
				'label' => __('Enable Polako Finance', 'polako-gateway-for-woocommerce'),
				'type' => 'checkbox',
				'description' => __('Whether or not this gateway is enabled within WooCommerce.', 'polako-gateway-for-woocommerce'),
				'default' => 'no', // User should enter the required information before enabling the gateway.
				'desc_tip' => true,
			],
			'title' => [
				'title' => __('Title', 'polako-gateway-for-woocommerce'),
				'type' => 'text',
				'description' => __('The title which the user sees during checkout.', 'polako-gateway-for-woocommerce'),
				'default' => __('Polako Finance', 'polako-gateway-for-woocommerce'),
				'desc_tip' => true,
			],
			'description' => [
				'title' => __('Description', 'polako-gateway-for-woocommerce'),
				'type' => 'text',
				'description' => __('The description which the user sees during checkout.', 'polako-gateway-for-woocommerce'),
				'default' => '',
				'desc_tip' => true,
			],
			'testmode' => [
				'title' => __('Test Mode', 'polako-gateway-for-woocommerce'),
				'type' => 'checkbox',
				'description' => __('Switch to the test environment and enable logging.', 'polako-gateway-for-woocommerce'),
				'default' => 'yes',
			],
			'platform_id' => [
				'title' => __('Platform ID', 'polako-gateway-for-woocommerce'),
				'type' => 'text',
				'default' => '',
			],
			'api_key' => [
				'title' => __('API Key', 'polako-gateway-for-woocommerce'),
				'type' => 'text',
				'default' => '',
			],
		];
	}

	/**
	 * Get the settings keys that must be filled in
	 *
	 * @noinspection PhpUnused
	 */
	public function get_required_settings_keys()
	{
		return ['platform_id', 'api_key'];
	}

	/** Check whether the plugin is fully set up */
	public function needs_setup()
	{
		return !$this->get_option('platform_id') || !$this->get_option('api_key');
	}

	/** Add a notice to the settings form while in Test Mode */
	public function add_testmode_admin_settings_notice()
	{
		$this->form_fields['testmode']['description'] .= '<br/><strong>' . esc_html__('WARNING: No real payments performed in Test Mode.', 'polako-gateway-for-woocommerce') . '.</strong>';
	}

	/** Supporting method for @see is_available */
	public function check_requirements()
	{
		$errors = [
			empty($this->get_option('platform_id')) ? 'wc-gateway-polako-error-missing-platform-id' : null,
			empty($this->get_option('api_key')) ? 'wc-gateway-polako-error-missing-api-key' : null,
		];

		return array_filter($errors);
	}

	/** Check if the gateway is available for use */
	public function is_available()
	{
		if ('yes' === $this->enabled) {
			$errors = $this->check_requirements();
			return 0 === count($errors);
		}

		return parent::is_available();
	}

	/**
	 * Initialize the payment session and return the result
	 *
	 * @param int $order_id Order ID.
	 * @return string[] Init result {
	 *     @type string $result   Result of the operation
	 *     @type string $redirect Redirect URL
	 * }
	 */
	public function process_payment($order_id)
	{
		$order = wc_get_order($order_id);

		if (!$order) {
			$this->d_log('Invalid order ID: ' . $order_id);
			return ['result' => 'fail'];
		}

		// organize items.
		$items = [];
		$dump_items = [];
		foreach ($order->get_items() as $item) {
			$product = $item->get_product();
			$items[] = [
				'code' => $product ? $product->get_sku() : null,
				'name' => $item->get_name(),
				'price' => $order->get_item_total($item, true, true),
				'quantity' => $item->get_quantity(),
				'tax_schema' => self::get_item_tax_schema($item),
			];

			$dump_items[] = [
				'type' => $item->get_type(),
				'sku' => $product ? $product->get_sku() : null,
				'name' => $item->get_name(),
				'quantity' => $item->get_quantity(),
				'subtotal' => $item->get_subtotal(),
				'subtotal_tax' => $item->get_subtotal_tax(),
				'total' => $item->get_total(),
				'total_tax' => $item->get_total_tax(),
				'product' => $product,
				'item_tot' => $order->get_item_total($item, true, true),
				'line_tot' => $order->get_line_total($item, true, true),
				'item_tax' => $order->get_item_tax($item, true),
				'tax_status' => $item->get_tax_status(),
			];
		}
		foreach ($order->get_shipping_methods() as $method) {
			$items[] = [
				'code' => $method->get_method_id(),
				'name' => $method->get_name(),
				'price' => $order->get_item_total($method, true, true),
				'quantity' => $method->get_quantity(),
				'tax_schema' => self::get_item_tax_schema($method),
			];

			$dump_items[] = [
				'type' => $method->get_type(),
				'id' => $method->get_method_id(),
				'name' => $method->get_name(),
				'quantity' => $method->get_quantity(),
				'total' => $method->get_total(),
				'total_tax' => $method->get_total_tax(),
				'item_tot' => $order->get_item_total($method, true, true),
				'line_tot' => $order->get_line_total($method, true, true),
				'item_tax' => $order->get_item_tax($method, true),
				'tax_status' => $method->get_tax_status(),
			];
		}

		// prepare the payload.
		$payload = [
			'currency' => $order->get_currency(),
			'language' => strtolower(strtok(get_locale(), '_-')),
			'order_id' => $order->get_id(),
			'customer' => [
				'first_name' => $order->get_billing_first_name(),
				'last_name' => $order->get_billing_last_name(),
				'email' => $order->get_billing_email(),
				'phone' => $order->get_billing_phone(),
				'address' => [
					'address' => $order->get_billing_address_1(),
					'city' => $order->get_billing_city(),
					'zip' => $order->get_billing_postcode(),
					'country' => $order->get_billing_country(),
				],
			],
			'items' => $items,
			'total' => $order->get_total(),
			'platform_id' => $this->platform_id,
		];

		// sign the payload with.
		$data_string = $payload['order_id'] . '|' . $payload['total'] . '|' . $order->get_currency();
		$payload['signature'] = hash_hmac('sha256', $data_string, $this->api_key);

		// prepare debug data; logged only in test mode.
		$dump = [
			'payload' => $payload,
			'order_data' => $order->get_data(),
			'sign_data' => $data_string,
			'price_decimals' => wc_get_price_decimals(),
			'order_items' => $dump_items,
		];
		unset($dump['order_data']['meta_data']);
		$this->d_log('ORDER_DUMP:' . PHP_EOL . wp_json_encode($dump, JSON_PRETTY_PRINT) . PHP_EOL);

		// initialize the payment session.
		$response = wp_remote_post($this->url, [
			'method' => 'POST',
			'headers' => ['Content-Type' => 'application/json'],
			'body' => wp_json_encode($payload),
			'timeout' => 15,
		]);

		if (is_wp_error($response)) {
			$this->i_log('Payment init failed: ' . $response->get_error_message());
			return ['result' => 'fail'];
		}

		// process the response.
		$code = wp_remote_retrieve_response_code($response);
		$body = wp_remote_retrieve_body($response);
		$decoded = json_decode($body, true);

		$this->d_log('Payment init response: HTTP ' . $code . PHP_EOL . $this->url . PHP_EOL . $body);

		if (200 > $code || 299 < $code || !isset($decoded['paymentPageUrl'])) {
			$this->i_log('Unexpected API response or missing redirect URL: HTTP ' . $code . PHP_EOL . $body);
			return ['result' => 'fail'];
		}

		// redirect the customer to the payment form.
		return [
			'result' => 'success',
			'redirect' => $decoded['paymentPageUrl'],
		];
	}

	/** Capture and process the payment response */
	public function capture_gateway_callback()
	{
		if (
			isset($_SERVER['REQUEST_METHOD']) &&
			'POST' === $_SERVER['REQUEST_METHOD'] &&
			isset($_SERVER['CONTENT_TYPE']) &&
			'application/json' === sanitize_text_field(wp_unslash($_SERVER['CONTENT_TYPE']))
		) {
			$data = json_decode(file_get_contents('php://input'), true);
			if (null === $data) {
				http_response_code(400);
				exit('Invalid JSON structure');
			}
		} else {
			$data = $_POST;
		}

		if (!is_array($data)) {
			http_response_code(400);
			exit('Invalid JSON structure');
		}

		// Sanitize and format the callback body.
		$sanitized_data = [
			'order_id' => isset($data['order_id']) ? absint($data['order_id']) : 0,
			'total' => isset($data['total']) ? sanitize_text_field($data['total']) : '',
			'success' => isset($data['success']) ? absint($data['success']) : '',
			'tx_id' => isset($data['tx_id']) ? sanitize_text_field($data['tx_id']) : '',
			'signature' => isset($data['signature']) ? sanitize_text_field($data['signature']) : '',
		];

		$this->process_callback($sanitized_data);

		// Let the originator know the callback has been processed.
		header('HTTP/1.0 200 OK');
		flush();
	}

	/** Process the payment response */
	public function process_callback($data)
	{
		$order = wc_get_order($data['order_id']);
		if (!$order) {
			$this->d_log('Invalid order ID: ' . $data['order_id']);
			exit();
		}

		if (!in_array($order->get_status(), self::ACCEPT_STATUSES)) {
			$this->d_log('Order has already been processed');
			return;
		}

		// verify the signature.
		$data_string = $data['order_id'] . '|' . $data['total'] . '|' . $data['success'];
		$calculated_signature = hash_hmac('sha256', $data_string, $this->api_key);

		if (!hash_equals($calculated_signature, $data['signature'])) {
			$this->i_log('Mismatching signature');
			exit();
		}

		// update the order status.
		if (1 == $data['success']) {
			$order->payment_complete($data['tx_id']);
			do_action('woocommerce_polako_handle_itn_payment_complete', $data, $order);
		} else {
			$order->update_status(OrderStatus::FAILED);
		}
	}

	/** Log an informational message */
	public function i_log($message)
	{
		if (empty($this->logger)) {
			$this->logger = new WC_Logger();
		}
		$this->logger->add('polako', $message);
	}

	/** Log a debug message */
	public function d_log($message)
	{
		if ('yes' === $this->get_option('testmode')) {
			$this->i_log($message);
		}
	}

	/** Get user-friendly message from the key */
	public function get_error_message($key)
	{
		switch ($key) {
			case 'wc-gateway-polako-error-invalid-currency':
				return esc_html__('Your store uses a currency that Polako Finance doesn\'t support yet.', 'polako-gateway-for-woocommerce');
			case 'wc-gateway-polako-error-missing-platform-id':
				return esc_html__('You forgot to fill the Platform ID.', 'polako-gateway-for-woocommerce');
			case 'wc-gateway-polako-error-missing-api-key':
				return esc_html__('You forgot to fill the API key.', 'polako-gateway-for-woocommerce');
			case 'wc-gateway-polako-error-invalid-credentials':
				return esc_html__('Invalid Polako Finance credentials. Please verify and enter the correct details.', 'polako-gateway-for-woocommerce');
			default:
				return '';
		}
	}

	/** Display admin notices, if any */
	public function admin_notices()
	{
		if ('no' === $this->enabled) {
			return;
		}

		$errors_to_show = $this->check_requirements();
		if (!count($errors_to_show)) {
			return;
		}

		if (!get_transient('wc-gateway-polako-admin-notice-transient')) {
			set_transient('wc-gateway-polako-admin-notice-transient', 1, 1);

			echo '<div class="notice notice-error is-dismissible"><p>' .
				esc_html__('Please fix the following issues to use Polako Finance as a payment provider:', 'polako-gateway-for-woocommerce') .
				'</p>' .
				'<ul style="list-style-type: disc; list-style-position: inside; padding-left: 2em;">' .
				wp_kses_post(
					array_reduce(
						$errors_to_show,
						function ($errors_list, $error_item) {
							$errors_list = $errors_list . PHP_EOL . ('<li>' . $this->get_error_message($error_item) . '</li>');
							return $errors_list;
						},
						'',
					),
				) .
				'</ul></p></div>';
		}
	}

	private static function get_item_tax_schema($item)
	{
		$taxes = $item->get_taxes();
		$tax_rate_ids = !empty($taxes['total']) ? $taxes['total'] : $taxes['subtotal'] ?? [];

		if (empty($tax_rate_ids)) {
			return null; // non-taxable, exempt, or tax-free.
		}

		$tax_value = WC_Tax::get_rate_percent_value(array_key_first($tax_rate_ids));

		switch ($tax_value) {
			case 20:
				return 'VAT';
			case 10:
				return 'Reduced_VAT';
			case 0:
				return 'No_VAT';
			default:
				return null;
		}
	}
}

<?php
/**
 * Checkout Without Payment
 *
 * Development tool to allow testing the checkout process without requiring payment.
 * Only enabled for a specific user email address configured in the plugin settings.
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
	exit;
}

/**
 * Check if current user is allowed to checkout without payment
 *
 * @return bool True if user is allowed to skip payment
 */
function vdt_is_checkout_without_payment_user()
{
	if (!is_user_logged_in()) {
		return false;
	}

	$allowed_email = get_option('vital_dev_tools_checkout_without_payment_user', '');
	if (empty($allowed_email)) {
		return false;
	}

	$current_user = wp_get_current_user();
	return $current_user->user_email === $allowed_email;
}

/**
 * Initialize the payment gateway class
 * Only loads if WooCommerce is active
 */
function vdt_init_no_payment_gateway()
{
	// Check if WooCommerce is active
	if (!class_exists('WooCommerce')) {
		return;
	}

	/**
	 * Custom Payment Gateway - No Payment Required
	 *
	 * This gateway allows the specified user to complete checkout without payment.
	 * Orders are automatically set to 'processing' status.
	 */
	class VDT_No_Payment_Gateway extends WC_Payment_Gateway
	{
		public function __construct()
		{
			$this->id = 'vdt_no_payment';
			$this->method_title = 'No Payment Required (Dev)';
			$this->method_description = 'Development tool - allows checkout without payment for specified user';
			$this->has_fields = false;

			$this->init_form_fields();
			$this->init_settings();

			$this->title = 'No Payment Required';
			$this->description = 'Development mode - no payment required';

			add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
		}

		public function init_form_fields()
		{
			$this->form_fields = array(
				'enabled' => array(
					'title' => 'Enable/Disable',
					'type' => 'checkbox',
					'label' => 'Enable No Payment Gateway',
					'default' => 'yes'
				)
			);
		}

		public function process_payment($order_id)
		{
			$order = wc_get_order($order_id);

			// Mark order as processing
			$order->payment_complete();
			$order->add_order_note('Payment skipped - Development mode (No Payment Gateway)');

			// Empty cart
			WC()->cart->empty_cart();

			// Return success
			return array(
				'result' => 'success',
				'redirect' => $this->get_return_url($order)
			);
		}
	}
}
add_action('plugins_loaded', 'vdt_init_no_payment_gateway');

/**
 * Add a custom "No Payment Required" gateway for specified user
 *
 * @param array $gateways Available payment gateways
 * @return array Modified gateways array
 */
function vdt_add_no_payment_gateway($gateways)
{
	if (class_exists('VDT_No_Payment_Gateway') && vdt_is_checkout_without_payment_user()) {
		$gateways[] = 'VDT_No_Payment_Gateway';
	}
	return $gateways;
}
add_filter('woocommerce_payment_gateways', 'vdt_add_no_payment_gateway');

/**
 * Make the No Payment gateway available only for the specified user
 *
 * @param array $available_gateways Available payment gateways
 * @return array Modified gateways array
 */
function vdt_filter_available_payment_gateways($available_gateways)
{
	if (!vdt_is_checkout_without_payment_user()) {
		// Remove the no payment gateway for all other users
		if (isset($available_gateways['vdt_no_payment'])) {
			unset($available_gateways['vdt_no_payment']);
		}
	} else {
		// For the specified user, make only the no payment gateway available
		$available_gateways = array_filter($available_gateways, function ($gateway) {
			return $gateway->id === 'vdt_no_payment';
		});
	}
	return $available_gateways;
}
add_filter('woocommerce_available_payment_gateways', 'vdt_filter_available_payment_gateways', 999);

/**
 * Automatically select the no payment gateway for the specified user
 */
function vdt_auto_select_no_payment_gateway()
{
	if (vdt_is_checkout_without_payment_user()) {
		WC()->session->set('chosen_payment_method', 'vdt_no_payment');
	}
}
add_action('woocommerce_before_checkout_form', 'vdt_auto_select_no_payment_gateway');
add_action('woocommerce_checkout_update_order_review', 'vdt_auto_select_no_payment_gateway');

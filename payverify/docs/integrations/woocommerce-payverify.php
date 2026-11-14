<?php
/**
 * Plugin Name: WooCommerce PayVerify Gateway
 * Description: Automated Commercial Bank of Ceylon payment verification for WooCommerce via PayVerify.
 * Version: 1.0.0
 * Author: PayVerify
 */

if (!defined('ABSPATH')) {
    exit;
}

add_action('plugins_loaded', 'init_payverify_gateway_class');
function init_payverify_gateway_class() {
    class WC_Gateway_PayVerify extends WC_Payment_Gateway {
        public function __construct() {
            $this->id = 'payverify';
            $this->icon = ''; // URL to ComBank or PayVerify logo
            $this->has_fields = true;
            $this->method_title = 'Commercial Bank Direct Deposit (PayVerify)';
            $this->method_description = 'Accept bank transfers and cash deposits with 100% automated verification via Commercial Bank SMS & WhatsApp Banking.';

            $this->supports = array('products');

            $this->init_form_fields();
            $this->init_settings();

            $this->title = $this->get_option('title');
            $this->description = $this->get_option('description');
            $this->enabled = $this->get_option('enabled');
            $this->api_url = rtrim($this->get_option('api_url', 'https://api.payverify.lk'), '/');
            $this->api_key = $this->get_option('api_key');
            $this->webhook_secret = $this->get_option('webhook_secret');

            add_action('woocommerce_update_options_payment_gateways_' . $this->id, array($this, 'process_admin_options'));
            add_action('woocommerce_api_payverify_webhook', array($this, 'handle_webhook'));
        }

        public function init_form_fields() {
            $this->form_fields = array(
                'enabled' => array(
                    'title'       => 'Enable/Disable',
                    'label'       => 'Enable PayVerify Bank Verification',
                    'type'        => 'checkbox',
                    'default'     => 'no',
                ),
                'title' => array(
                    'title'       => 'Title',
                    'type'        => 'text',
                    'description' => 'Payment method title that the customer will see on checkout',
                    'default'     => 'Commercial Bank Direct Transfer / CRM Deposit',
                ),
                'description' => array(
                    'title'       => 'Description',
                    'type'        => 'textarea',
                    'description' => 'Instructions displayed to the customer on checkout',
                    'default'     => 'Transfer the order amount to our Commercial Bank account. Automated verification verifies your order instantly.',
                ),
                'api_url' => array(
                    'title'       => 'PayVerify API Endpoint',
                    'type'        => 'text',
                    'default'     => 'https://api.payverify.lk',
                ),
                'api_key' => array(
                    'title'       => 'PayVerify API Key',
                    'type'        => 'password',
                    'description' => 'Secret API Key configured in PayVerify PV_EXTERNAL_API_KEY',
                ),
                'webhook_secret' => array(
                    'title'       => 'Webhook HMAC Secret',
                    'type'        => 'password',
                    'description' => 'Secret matching PV_EXTERNAL_WEBHOOK_SECRET for verifying webhook authenticity',
                ),
            );
        }

        public function process_payment($order_id) {
            $order = wc_get_order($order_id);
            $amount_minor = intval(round($order->get_total() * 100));

            // Call PayVerify to register checkout order and generate unique payment code
            $response = wp_remote_post($this->api_url . '/api/v1/external/orders', array(
                'headers' => array(
                    'Content-Type' => 'application/json',
                    'X-API-Key'    => $this->api_key,
                ),
                'body' => json_encode(array(
                    'invoice_no'      => 'WC-' . $order_id,
                    'amount_minor'    => $amount_minor,
                    'customer_email'  => $order->get_billing_email(),
                    'customer_name'   => $order->get_formatted_billing_full_name(),
                    'customer_phone'  => $order->get_billing_phone(),
                    'unique_amount'   => false,
                )),
                'timeout' => 15,
            ));

            if (is_wp_error($response)) {
                wc_add_notice('Unable to initialize payment verification: ' . $response->get_error_message(), 'error');
                return;
            }

            $body = json_decode(wp_remote_retrieve_body($response), true);
            $payment_code = isset($body['payment_code']) ? $body['payment_code'] : ('PV-' . $order_id);
            $instructions = isset($body['instructions']) ? $body['instructions'] : '';

            $order->update_meta_data('_payverify_payment_code', $payment_code);
            $order->update_meta_data('_payverify_instructions', $instructions);
            $order->update_status('on-hold', 'Awaiting Commercial Bank deposit with reference: ' . $payment_code);
            $order->save();

            WC()->cart->empty_cart();

            return array(
                'result'   => 'success',
                'redirect' => $this->get_return_url($order),
            );
        }

        public function handle_webhook() {
            $payload = file_get_contents('php://input');
            $signature_header = isset($_SERVER['HTTP_X_PAYVERIFY_SIGNATURE']) ? $_SERVER['HTTP_X_PAYVERIFY_SIGNATURE'] : '';

            // Verify HMAC-SHA256 signature
            if (!empty($this->webhook_secret)) {
                $expected_sig = 'sha256=' . hash_hmac('sha256', $payload, $this->webhook_secret);
                if (!hash_equals($expected_sig, $signature_header)) {
                    wp_die('Invalid HMAC signature', 'Webhook Error', array('response' => 401));
                }
            }

            $data = json_decode($payload, true);
            if (!$data || !isset($data['event']) || $data['event'] !== 'payment.verified') {
                wp_die('Ignored event', 'OK', array('response' => 200));
            }

            $order_info = isset($data['data']) ? $data['data'] : array();
            $invoice_no = isset($order_info['invoice_no']) ? $order_info['invoice_no'] : '';

            // Extract WC Order ID
            if (strpos($invoice_no, 'WC-') === 0) {
                $wc_order_id = intval(substr($invoice_no, 3));
                $order = wc_get_order($wc_order_id);
                if ($order && !$order->is_paid()) {
                    $order->payment_complete($order_info['reference'] ?? 'PAYVERIFY-BANK-MATCH');
                    $order->add_order_note(sprintf(
                        'Payment of %s automatically verified via Commercial Bank ledger match (Basis: %s, Ref: %s).',
                        $order_info['amount_formatted'] ?? 'LKR',
                        $order_info['verification_basis'] ?? 'bank_match',
                        $order_info['reference'] ?? 'N/A'
                    ));
                    $order->save();
                }
            }

            wp_send_json(array('received' => true));
        }
    }

    function add_payverify_gateway($methods) {
        $methods[] = 'WC_Gateway_PayVerify';
        return $methods;
    }
    add_filter('woocommerce_payment_gateways', 'add_payverify_gateway');
}


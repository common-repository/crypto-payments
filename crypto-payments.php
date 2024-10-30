<?php

/*
 *
 * Plugin Name: Crypto Payments - GreenWAVES
 * Plugin URI: https://grwv.app
 * Description: Peer to Peer Crypto Payments made easy, and free. Custom token listings for multiple chains like SOLANA, BINANCE, ETHEREUM are possible.
 * Version: 1.0.7
 * Author: GreenWAVES
 * Author URI: https://greenwav.es/
 * License: MIT
 * License URI: https://opensource.org/license/mit
 * © 2024 grwv.app. All rights reserved.
 *
 */

 use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

 if (in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
     function grwv_declare_cart_checkout_blocks_compatibility() {
         if (class_exists('\Automattic\WooCommerce\Utilities\FeaturesUtil')) {
             \Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility('cart_checkout_blocks', __FILE__, true);
         }
     }
 
     function grwv_wc_add_to_gateways($gateways) {
         $gateways[] = 'GRWV_WC_greenwaves';
         return $gateways;
     }
 
     function grwv_wc_plugin_links($links) {
         return array_merge(['<a href="' . admin_url('options-general.php?page=greenwaves-cloud') . '">Settings</a>'], $links);
     }
 
     function grwv_checkout_block_support() {
         if (class_exists('Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType') && file_exists(__DIR__ . '/config.php')) {
 
             final class GRWV_WC_greenwaves_Blocks extends AbstractPaymentMethodType {
 
                 private $gateway;
                 protected $name = 'greenwaves';
 
                 public function initialize() {
                     $this->settings = get_option('woocommerce_greenwaves_settings', []);
                     $this->gateway = new GRWV_WC_greenwaves();
                 }
 
                 public function is_active() {
                     return $this->gateway->is_available();
                 }
 
                 public function get_payment_method_script_handles() {
                     wp_register_script('greenwaves-blocks-integration', plugin_dir_url(__FILE__) . '/assets/checkout.js', ['wc-blocks-registry', 'wc-settings', 'wp-element', 'wp-html-entities', 'wp-i18n'], null, true);
                     if (function_exists('wp_set_script_translations')) {
                         wp_set_script_translations('greenwaves-blocks-integration');
                     }
                     return ['greenwaves-blocks-integration'];
                 }
 
                 public function get_payment_method_data() {
                     return ['title' => $this->gateway->title, 'description' => $this->gateway->description];
                 }
             }
 
             add_action('woocommerce_blocks_payment_method_type_registration', function (Automattic\WooCommerce\Blocks\Payments\PaymentMethodRegistry $payment_method_registry) {
                 $payment_method_registry->register(new GRWV_WC_greenwaves_Blocks);
             });
         }
     }
 
     function grwv_wc_init() {
         class GRWV_WC_greenwaves extends WC_Payment_Gateway {
             public function __construct() {
                 $settings = grwv_get_wp_settings();
                 $this->id = 'greenwaves';
                 $this->has_fields = false;
                 $this->method_title = 'GreenWAVES';
                 $this->method_description = 'Accept cryptocurrency payments.';
                 $this->title = __(grwv_isset($settings, 'greenwaves-payment-option-name', 'Pay with crypto'), 'greenwaves');
                 $this->description = __(grwv_isset($settings, 'greenwaves-payment-option-text', 'Pay via Bitcoin, Ethereum and other cryptocurrencies.'), 'greenwaves');
                 $this->init_form_fields();
                 $this->init_settings();
                 $icon = grwv_isset($settings, 'greenwaves-payment-option-icon');
                 if ($icon) {
                     $this->icon = $icon;
                 }
                 add_action('woocommerce_update_options_payment_gateways_' . $this->id, [$this, 'process_admin_options']);
             }
 
             public function process_payment($order_id) {
                 $settings = grwv_get_wp_settings();
                 $order = wc_get_order($order_id);
                 $order->update_status('pending');
                 wc_reduce_stock_levels($order_id);
                 return ['result' => 'success', 'redirect' => 'https://admin.grwv.app/pay.php?checkout_id=custom-wc-' . $order_id . '&price=' . $order->get_total() . '&currency=' . strtolower($order->get_currency()) . '&external_reference=' . grwv_wp_encryption($order_id . '|' . $this->get_return_url($order) . '|woo') . '&plugin=woocommerce&redirect=' . urlencode($this->get_return_url($order)) . '&cloud=' . grwv_isset($settings, 'greenwaves-cloud-key') . '&plugin=woocommerce&note=' . urlencode('WooCommerce order ID ' . $order_id)];
             }
 
             public function init_form_fields() {
                 $this->form_fields = apply_filters('wc_offline_form_fields', ['enabled' => ['title' => __('Enable/Disable', 'greenwaves'), 'type' => 'checkbox', 'label' => __('Enable GreenWAVES', 'greenwaves'), 'default' => 'yes']]);
             }
         }
     }
     add_filter('woocommerce_payment_gateways', 'grwv_wc_add_to_gateways');
     add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'grwv_wc_plugin_links');
     add_action('woocommerce_blocks_loaded', 'grwv_checkout_block_support');
     add_action('plugins_loaded', 'grwv_wc_init', 11);
     add_action('before_woocommerce_init', 'grwv_declare_cart_checkout_blocks_compatibility');
 }
 
 function grwv_edd_register_gateway($gateways) {
     $settings = grwv_get_wp_settings();
     $gateways['greenwaves'] = ['admin_label' => 'GreenWAVES', 'checkout_label' => __(grwv_isset($settings, 'greenwaves-payment-option-name', 'Pay with crypto'), 'greenwaves')];
     return $gateways;
 }
 
 function grwv_edd_process_payment($data) {
     if (!edd_get_errors()) {
         $settings = grwv_get_wp_settings();
         $payment_id = edd_insert_payment($data);
         $url = 'checkout_id=custom-edd-' . $payment_id . '&price=' . $data['price'] . '&currency=' . strtolower(edd_get_currency()) . '&external_reference=' . grwv_wp_encryption('edd|' . $payment_id) . '&redirect=' . urlencode(edd_get_success_page_uri()) . '&cloud=' . grwv_isset($settings, 'greenwaves-cloud-key') . '&note=' . urlencode('Easy Digital Download payment ID ' . $payment_id);
         edd_send_back_to_checkout($url);
     }
 }
 
 function grwv_wp_on_load() {
     if (function_exists('edd_is_checkout') && edd_is_checkout()) {
         echo '<script>var grwv_href = document.location.href; if (grwv_href.includes("custom-edd-")) { document.location = "https://admin.grwv.app/pay.php" + grwv_href.substring(grwv_href.indexOf("?")); }</script>';
     }
 }
 
 function grwv_edd_disable_gateway_cc_form() {
     return;
 }
 
 function grwv_set_admin_menu() {
     add_submenu_page('options-general.php', 'GreenWAVES', 'GreenWAVES', 'administrator', 'greenwaves-cloud', 'grwv_admin');
 }
 
 function grwv_enqueue_admin() {
     if (key_exists('page', $_GET) && $_GET['page'] == 'greenwaves-cloud') {
         wp_enqueue_style('grwv-cloud-admin-css', plugin_dir_url(__FILE__) . '/assets/style.css', [], '1.0', 'all');
     }
 }
 
 function grwv_wp_encryption($string, $encrypt = true) {
     $settings = grwv_get_wp_settings();
     $output = false;
     $encrypt_method = 'AES-256-CBC';
     $secret_key = grwv_isset($settings, 'greenwaves-key');
     $key = hash('sha256', $secret_key);
     $iv = substr(hash('sha256', grwv_isset($settings, 'greenwaves-cloud-key')), 0, 16);
     if ($encrypt) {
         $output = openssl_encrypt(is_string($string) ? $string : json_encode($string, JSON_INVALID_UTF8_IGNORE | JSON_UNESCAPED_UNICODE), $encrypt_method, $key, 0, $iv);
         $output = base64_encode($output);
         if (substr($output, -1) == '=')
             $output = substr($output, 0, -1);
     } else {
         $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key, 0, $iv);
     }
     return $output;
 }
 
 function grwv_get_wp_settings() {
     return json_decode(get_option('grwv-cloud-settings'), true);
 }
 
 function grwv_isset($array, $key, $default = '') {
     return !empty($array) && isset($array[$key]) && $array[$key] !== '' ? $array[$key] : $default;
 }
 
 function grwv_admin() {
     if (isset($_POST['grwv_submit'])) {
         if (!isset($_POST['grwv_nonce']) || !wp_verify_nonce($_POST['grwv_nonce'], 'grwv-nonce'))
             die('nonce-check-failed');
         $settings = [
             'greenwaves-key' => sanitize_text_field($_POST['greenwaves-key']),
             'greenwaves-cloud-key' => sanitize_text_field($_POST['greenwaves-cloud-key']),
             'greenwaves-payment-option-name' => sanitize_text_field($_POST['greenwaves-payment-option-name']),
             'greenwaves-payment-option-text' => sanitize_text_field($_POST['greenwaves-payment-option-text']),
             'greenwaves-payment-option-icon' => sanitize_text_field($_POST['greenwaves-payment-option-icon'])
         ];
         update_option('grwv-cloud-settings', json_encode($settings));
     }
     $settings = grwv_get_wp_settings();
     ?>
     <form method="post" action="">
         <div class="wrap">
             <h1>GreenWAVES Payments</h1>
             <div class="postbox-container">
                 <table class="form-table grwv-table">
                     <tbody>
                         <tr valign="top">
                             <th scope="row">
                                 <label for="name">Webhook secret key</label>
                             </th>
                             <td>
                                 <input type="password" id="greenwaves-key" name="greenwaves-key" value="<?php echo esc_html(grwv_isset($settings, 'greenwaves-key')) ?>" />
                                 <br />
                                 <p class="description">Enter the GreenWAVES webhook secret key. Get it from GRWV.APP > Settings > Webhook > Webhook secret key.</p>
                             </td>
                         </tr>
                         <tr valign="top">
                             <th scope="row">
                                 <label for="name">Cloud API key</label>
                             </th>
                             <td>
                                 <input type="password" id="greenwaves-cloud-key" name="greenwaves-cloud-key" value="<?php echo esc_html(grwv_isset($settings, 'greenwaves-cloud-key')) ?>" />
                                 <br />
                                 <p class="description">Enter the GreenWAVES API key. Get it from GRWV.APP > Account > API key.</p>
                             </td>
                         </tr>
                         <tr valign="top">
                             <th scope="row">
                                 <label for="name">Payment option name</label>
                             </th>
                             <td>
                                 <input type="text" id="greenwaves-payment-option-name" name="greenwaves-payment-option-name" value="<?php echo esc_html(grwv_isset($settings, 'greenwaves-payment-option-name')) ?>" />
                             </td>
                         </tr>
                         <tr valign="top">
                             <th scope="row">
                                 <label for="name">Payment option description</label>
                             </th>
                             <td>
                                 <input type="text" id="greenwaves-payment-option-text" name="greenwaves-payment-option-text" value="<?php echo esc_html(grwv_isset($settings, 'greenwaves-payment-option-text')) ?>" />
                             </td>
                         </tr>
                         <tr valign="top">
                             <th scope="row">
                                 <label for="name">Payment option icon URL</label>
                             </th>
                             <td>
                                 <input type="text" id="greenwaves-payment-option-icon" name="greenwaves-payment-option-icon" value="<?php echo esc_html(grwv_isset($settings, 'greenwaves-payment-option-icon')) ?>" />
                             </td>
                         </tr>
                         <tr valign="top">
                             <th scope="row">
                                 <label for="name">Webhook URL</label>
                             </th>
                             <td>
                                 <input type="text" readonly value="<?php echo site_url() ?>/wp-json/greenwaves/webhook" />
                             </td>
                         </tr>
                     </tbody>
                 </table>
                 <p class="submit">
                     <input type="hidden" name="grwv_nonce" id="grwv_nonce" value="<?php echo wp_create_nonce('grwv-nonce') ?>" />
                     <input type="submit" class="button-primary" name="grwv_submit" value="Save changes" />
                 </p>
             </div>
         </div>
     </form>
 <?php }
 
 function grwv_wp_webhook_callback($request) {
     $response = json_decode(file_get_contents('php://input'), true);
     if (!isset($response['key'])) {
         return;
     }
     $settings = grwv_get_wp_settings();
     if ($response['key'] !== $settings['greenwaves-key']) {
         return 'Invalid Webhook Key';
     }
     $transaction = grwv_isset($response, 'transaction');
     if ($transaction) {
         $external_reference = explode('|', grwv_wp_encryption($transaction['external_reference'], false));
         $text = 'GreenWAVES transaction ID: ' . $transaction['id'];
         if (in_array('woo', $external_reference)) {
             $order = wc_get_order($external_reference[0]);
             $amount_fiat = $transaction['amount_fiat'];
             if (($amount_fiat && floatval($amount_fiat) < floatval($order->get_total())) || (strtoupper($transaction['currency']) != strtoupper($order->get_currency()))) {
                 return 'Invalid amount or currency';
             }
             if ($order->get_status() == 'pending') {
                 $products = $order->get_items();
                 $is_virtual = true;
                 foreach ($products as $product) {
                     $product = wc_get_product($product->get_data()['product_id']);
                     if (!$product->is_virtual() && !$product->is_downloadable()) {
                         $is_virtual = false;
                         break;
                     }
                 }
                 if ($is_virtual) {
                     $order->payment_complete();
                 } else {
                     $order->update_status('processing');
                 }
                 $order->add_order_note($text);
                 return 'success';
             }
         } else if (in_array('edd', $external_reference)) {
             edd_update_payment_status($external_reference[0], 'complete');
             edd_insert_payment_note($external_reference[0], $text);
             return 'success';
         }
         return 'Invalid order status';
     }
     return 'Transaction not found';
 }
 
 function grwv_wp_on_user_logout($user_id) {
     if (!headers_sent()) {
         setcookie('grwv_LOGIN', '', time() - 3600);
     }
     return $user_id;
 }
 
 add_action('admin_menu', 'grwv_set_admin_menu');
 add_action('network_admin_menu', 'grwv_set_admin_menu');
 add_action('admin_enqueue_scripts', 'grwv_enqueue_admin');
 add_action('edd_gateway_greenwaves', 'grwv_edd_process_payment');
 add_action('edd_greenwaves_cc_form', 'grwv_edd_disable_gateway_cc_form');
 add_filter('edd_payment_gateways', 'grwv_edd_register_gateway');
 add_action('wp_logout', 'grwv_wp_on_user_logout');
 add_action('wp_head', 'grwv_wp_on_load');
 add_action('rest_api_init', function () {
     register_rest_route('greenwaves', '/webhook', [
         'methods' => 'POST',
         'callback' => 'grwv_wp_webhook_callback',
         'permission_callback' => '__return_true',
         'args' => [
             'id' => [
                 'validate_callback' => function ($param, $request, $key) {
                     return is_numeric($param);
                 }
             ]
         ]
     ]);
 });
 
 ?>
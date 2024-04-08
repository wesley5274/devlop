<?php

use Automattic\WooCommerce\Blocks\Payments\Integrations\AbstractPaymentMethodType;

class Wooecpay_Gateway_Block extends AbstractPaymentMethodType {

    protected $gateway;
    protected $name;
    protected $jsUrl = '/wp-content/plugins/ecpay-ecommerce-for-woocommerce/includes/block';

    public function __construct(string $name) {
        $this->name = $name;
        $this->settings = get_option('woocommerce_' . $this->name . '_settings', false);
    }

    public function initialize() {
        $this->gateway = new $this->name();
    }

    public function is_active() {
        return $this->gateway->is_available();
    }

    public function get_payment_method_script_handles() {
        $js_url = '';
        switch ($this->name) {
            case 'Wooecpay_Gateway_Credit':
                $js_url = $this->jsUrl . '/credit-checkout.js';
                break;
            case 'Wooecpay_Gateway_Credit_Installment':
                $js_url = $this->jsUrl . '/credit-installment-checkout.js';
                break;
            case 'Wooecpay_Gateway_Webatm':
                $js_url = $this->jsUrl . '/webatm-checkout.js';
                break;
            case 'Wooecpay_Gateway_Atm':
                $js_url = $this->jsUrl . '/atm-checkout.js';
                break;
            case 'Wooecpay_Gateway_Cvs':
                $js_url = $this->jsUrl . '/cvs-checkout.js';
                break;
            case 'Wooecpay_Gateway_Barcode':
                $js_url = $this->jsUrl . '/barcode-checkout.js';
                break;
            case 'Wooecpay_Gateway_Applepay':
                $js_url = $this->jsUrl . '/applepay-checkout.js';
                break;
            case 'Wooecpay_Gateway_Bnpl':
                $js_url = $this->jsUrl . '/bnpl-checkout.js';
                break;
            case 'Wooecpay_Gateway_Twqr':
                $js_url = $this->jsUrl . '/twqr-checkout.js';
                break;
            case 'Wooecpay_Gateway_Dca':
                $js_url = $this->jsUrl . '/dca-checkout.js';
                break;
        }

        wp_register_script(
            $this->name . '-blocks-integration',
            $js_url,
            [
                'wc-blocks-registry',
                'wc-settings',
                'wp-element',
                'wp-html-entities',
                'wp-i18n',
            ],
            null,
            true
        );

        if(function_exists('wp_set_script_translations')) {
            wp_set_script_translations($this->name . '-blocks-integration');
        }
        return [$this->name . '-blocks-integration'];
    }

    public function get_payment_method_data() {
        return [
            'title' => $this->gateway->title,
            'id' => $this->gateway->id,
        ];
    }
}

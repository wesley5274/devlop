<?php
namespace Helpers\Payment;

use Ecpay\Sdk\Factories\Factory;
use Ecpay\Sdk\Exceptions\RtnException;
use Ecpay\Sdk\Services\AesService;

class Wooecpay_Payment_Helper
{
    public function get_merchant_trade_no($order_id, $order_prefix = '')
    {
        $trade_no = $order_prefix . substr(str_pad($order_id, 8, '0', STR_PAD_LEFT), 0, 8) . 'SN' . substr(hash('sha256', (string) time()), -5);
        return substr($trade_no, 0, 20);
    }

    public function get_order_id_by_merchant_trade_no($info)
    {
        $order_prefix = get_option('wooecpay_payment_order_prefix') ;

        if (isset($info['MerchantTradeNo'])) {

            $order_id = substr($info['MerchantTradeNo'], strlen($order_prefix), strrpos($info['MerchantTradeNo'], 'SN'));
            $order_id = (int) $order_id;
            if ($order_id > 0) {
                return $order_id;
            }
        }

        return false;
    }

    public function get_ecpay_payment_api_info($action = '')
    {
        $api_payment_info = [
            'merchant_id'   => '',
            'hashKey'       => '',
            'hashIv'        => '',
            'action'        => '',
        ];

        if ('yes' === get_option('wooecpay_enabled_payment_stage', 'yes')) {

            $api_payment_info = [
                'merchant_id'   => '3002607',
                'hashKey'       => 'pwFHCqoQZGmho4w6',
                'hashIv'        => 'EkRm7iFT261dpevs',
            ];

        } else {

            $merchant_id    = get_option('wooecpay_payment_mid');
            $hash_key       = get_option('wooecpay_payment_hashkey');
            $hash_iv        = get_option('wooecpay_payment_hashiv');

            $api_payment_info = [
                'merchant_id'   => $merchant_id,
                'hashKey'       => $hash_key,
                'hashIv'        => $hash_iv,
            ];
        }

        // URL位置判斷
        if ('yes' === get_option('wooecpay_enabled_payment_stage', 'yes')) {

            switch ($action) {

                case 'QueryTradeInfo':
                    $api_payment_info['action'] = 'https://payment-stage.ecpay.com.tw/Cashier/QueryTradeInfo/V5';
                    break;

                case 'AioCheckOut':
                    $api_payment_info['action'] = 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5';
                    break;

                default:
                    break;
            }

        } else {

            switch ($action) {

                case 'QueryTradeInfo':
                    $api_payment_info['action'] = 'https://payment.ecpay.com.tw/Cashier/QueryTradeInfo/V5';
                    break;

                case 'AioCheckOut':
                    $api_payment_info['action'] = 'https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5';
                    break;

                default:
                    break;
            }
        }

        return $api_payment_info;
    }

    public function get_item_name($order)
    {
        $item_name = '';

        if (count($order->get_items())) {

            foreach ($order->get_items() as $item) {
                $item_name .= str_replace('#', '', trim($item->get_name())) . '#';
            }
        }

        $item_name = rtrim($item_name, '#');

        return $item_name;
    }

    public function add_type_info($input, $order)
    {
        $payment_type = $this->get_ChoosePayment($order->get_payment_method());
        
        switch ($payment_type) {

            case 'Credit':

                // 信用卡分期
                $number_of_periods = (int) $order->get_meta('_ecpay_payment_number_of_periods', true);
                if (in_array($number_of_periods, [3, 6, 12, 18, 24, 30])) {
                    $input['CreditInstallment'] = ($number_of_periods == 30) ? '30N' : $number_of_periods;
                    $order->add_order_note(sprintf(__('Credit installment to %d', 'ecpay-ecommerce-for-woocommerce'), $number_of_periods));

                    $order->save();
                }

                // 定期定額
                $dca = $order->get_meta('_ecpay_payment_dca');
                $dcaInfo = explode('_', $dca);
                if (count($dcaInfo) > 1) {
                    $input['PeriodAmount'] = $input['TotalAmount'];
                    $input['PeriodType'] = $dcaInfo[0];
                    $input['Frequency'] = (int)$dcaInfo[1];
                    $input['ExecTimes'] = (int)$dcaInfo[2];
                    $input['PeriodReturnURL'] = $input['ReturnURL'];
                }

                break;

            case 'ATM':

                $settings = get_option('woocommerce_Wooecpay_Gateway_Atm_settings', false);

                if(isset($settings['expire_date'])){
                    $expire_date = (int)$settings['expire_date'];
                } else {
                    $expire_date = 3;
                }

                $input['ExpireDate'] = $expire_date;

            break;

            case 'BARCODE':

                $settings = get_option('woocommerce_Wooecpay_Gateway_Barcode_settings', false);

                if(isset($settings['expire_date'])){
                    $expire_date = (int)$settings['expire_date'];
                } else {
                    $expire_date = 3;
                }

                $input['StoreExpireDate'] = $expire_date;

            break;

            case 'CVS':

                $settings = get_option('woocommerce_Wooecpay_Gateway_Cvs_settings', false);

                if(isset($settings['expire_date'])){
                    $expire_date = (int)$settings['expire_date'];
                } else {
                    $expire_date = 10080;
                }

                $input['StoreExpireDate'] = $expire_date;

            break;
        }

        return $input;
    }

    public function get_ChoosePayment($payment_method)
    {
        $choose_payment = '';

        switch ($payment_method) {
            case 'Wooecpay_Gateway_Credit':
            case 'Wooecpay_Gateway_Credit_Installment':
            case 'Wooecpay_Gateway_Dca':
                    $choose_payment = 'Credit';
                break;
            case 'Wooecpay_Gateway_Webatm':
                $choose_payment = 'WebATM';
                break;
            case 'Wooecpay_Gateway_Atm':
                $choose_payment = 'ATM';
                break;
            case 'Wooecpay_Gateway_Cvs':
                $choose_payment = 'CVS';
                break;
            case 'Wooecpay_Gateway_Barcode':
                $choose_payment = 'BARCODE';
                break;
            case 'Wooecpay_Gateway_Applepay':
                $choose_payment = 'ApplePay';
                break;
        }

        return $choose_payment;
    }
}

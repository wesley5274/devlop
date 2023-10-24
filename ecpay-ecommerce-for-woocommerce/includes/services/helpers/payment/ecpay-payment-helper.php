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
            case 'Wooecpay_Gateway_Twqr':
                $choose_payment = 'TWQR';
                break;
            case 'Wooecpay_Gateway_Bnpl':
                $choose_payment = 'BNPL';
                break;
        }

        return $choose_payment;
    }

    /**
     * 新增已付款的綠界金流特店交易編號
     *
     * @param  string $order_id
     * @param  array  $info
     * @return void
     */
    public function insert_ecpay_paid_merchant_trade_no($order_id, $info)
    {
        global $wpdb;

        $table_name      = $wpdb->prefix . 'ecpay_paid_merchant_trade_no';
        $isTableExists   = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;

        // Table 存在才能新增資料
        if ($isTableExists) {
            if (!$this->is_order_ecpay_paid_merchant_trade_no_exist($order_id, $info['MerchantTradeNo'])) {
                $insert = [
                    'order_id'          => $order_id,
                    'merchant_trade_no' => $info['MerchantTradeNo']
                ];

                $format = [
                    '%d',
                    '%s'
                ];

                $wpdb->insert($table_name, $insert, $format);
            }
        }
    }

    /**
     * 取得訂單已付款且沒有處理過的綠界金流特店交易編號
     *
     * @param  string            $order_id
     * @return array|object|null
     */
    public function get_order_ecpay_paid_merchant_trade_no($order_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ecpay_paid_merchant_trade_no';

        $ecpay_paid_merchant_trade_no = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT merchant_trade_no
                FROM $table_name
                WHERE order_id = %d AND is_completed_duplicate = 0
                ORDER BY id DESC",
                $order_id
            )
        );

        return $ecpay_paid_merchant_trade_no;
    }

    /**
     * 取得訂單已付款且沒有處理過的綠界金流特店交易編號
     *
     * @param  string $order_id
     * @param  string $merchant_trade_no
     * @return bool
     */
    public function is_order_ecpay_paid_merchant_trade_no_exist($order_id, $merchant_trade_no) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ecpay_paid_merchant_trade_no';

        $count = (int) $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(merchant_trade_no)
                FROM $table_name
                WHERE order_id = %d AND merchant_trade_no = %s",
                $order_id, $merchant_trade_no
            )
        );

        return ($count > 0);
    }

    /**
     * 更新訂單已付款的綠界金流特店交易編號為已處理
     *
     * @param  string            $order_id
     * @return array|object|null
     */
    public function set_order_ecpay_paid_merchant_trade_no_complete($order_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'ecpay_paid_merchant_trade_no';

        $result = $wpdb->get_results(
            $wpdb->prepare(
                "UPDATE $table_name
                SET is_completed_duplicate = 1, updated_at = CURRENT_TIMESTAMP
                WHERE order_id = %d AND is_completed_duplicate = 0",
                $order_id
            )
        );

        return $result;
    }


    /**
     * 取得綠界金流
     *
     * @return array
     */
    public function get_ecpay_payment_method()
    {
        return [
            'Wooecpay_Gateway_Credit',
			'Wooecpay_Gateway_Credit_Installment',
			'Wooecpay_Gateway_Webatm',
			'Wooecpay_Gateway_Atm',
			'Wooecpay_Gateway_Cvs',
			'Wooecpay_Gateway_Barcode',
			'Wooecpay_Gateway_Applepay',
			'Wooecpay_Gateway_Dca',
			'Wooecpay_Gateway_Twqr',
			'Wooecpay_Gateway_Bnpl'
        ];
    }

    /**
     * 判斷是否為綠界金流
     *
     * @param  string $payment_method
     * @return bool
     */
    public function is_ecpay_payment_method($payment_method)
    {
        return in_array($payment_method, $this->get_ecpay_payment_method());
    }

    /**
	 * 檢查訂單是否重複付款
	 *
     * @param  WC_Order $order
	 * @return array
	 */
	public function check_order_is_duplicate_payment($order)
	{
        $duplicate_payment = 0; // 0:不是異常訂單、1:是異常訂單
        $ecpay_paid_merchant_trade_no = [];

		// 取得訂單付款方式
		$payment_method = get_post_meta($order->get_id(), '_payment_method', true);

        // 檢查訂單當前是否使用綠界金流
		if ($this->is_ecpay_payment_method($payment_method)) {
            // 取得已付款的訂單
            $ecpay_paid_merchant_trade_no = $this->get_order_ecpay_paid_merchant_trade_no($order->get_id());
            $count_ecpay_paid_merchant_trade_no = count($ecpay_paid_merchant_trade_no);

			// 超過 1 筆已付款的綠界訂單
			if ($count_ecpay_paid_merchant_trade_no > 1) {
				$duplicate_payment = 1;
			}
		}

        return [
            'code' => $duplicate_payment,
            'merchant_trade_no'  => $ecpay_paid_merchant_trade_no
        ];
	}
}

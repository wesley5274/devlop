<?php

namespace Helpers\Logger;

class Wooecpay_Logger
{
    /**
     * 寫 Log
     *
     * @param  string|array $content
     * @param  string       $code
     * @param  string       $order_id
     * @return void
     */
    public function log($content, $code = '', $order_id = '') {
        // 啟用後台偵錯功能才能寫 Log
        if ('yes' === get_option('wooecpay_enabled_debug_log', 'no')) {

            // 檢查 Log 目錄是否存在
            $log_folder = WOOECPAY_PLUGIN_DIR . '/logs';
            if (!is_dir($log_folder)) {
                wp_mkdir_p($log_folder);
            }

            // 組合 Log 固定開頭
            $header = '[' . date_i18n('Y-m-d H:i:s') . '] [' . $code . '] [' . $order_id . ']: ';

            // 處理 content 參數格式
            if (gettype($content) === 'array') {
                $content = print_r($content, true);
            }
            $content = $header . $content;

            // 新增 Log
            // 注意：'外掛檔案編輯器'有限制允許編輯的檔案類型
            $debug_log_file_path = $log_folder . '/ecpay_debug_' . date_i18n('Ymd') . '.txt';
            file_put_contents($debug_log_file_path, ($content . PHP_EOL), FILE_APPEND);
        }
    }

    /**
     * Log 內容隱碼處理
     *
     * @param  string       $type
     * @param  string|array $data
     * @return string|array $data
     */
    public function replace_symbol($type, $data) {
        switch ($type) {
            case 'invoice':
                $data['Data']['CustomerName']  ?? '*';
                $data['Data']['CustomerAddr']  ?? '*';
                $data['Data']['CustomerPhone'] ?? '*';
                $data['Data']['CustomerEmail'] ?? '*';
                break;
        }

        return $data;
    }
}
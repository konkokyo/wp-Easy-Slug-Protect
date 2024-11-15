<?php
// 直接アクセス禁止
if (!defined('ABSPATH')) {
    exit;
}


class ESP_Option {
    /**
     * 現在の設定値を取得する
     * 
     * @param string 設定名の略称
     * @return array
     */
    public static function get_current_setting($key){
        return get_option(ESP_Config::OPTION_NAMES[$key], ESP_Config::OPTION_DEFAULTS[$key]);
    }

}

<?php
// 直接アクセス禁止
if (!defined('ABSPATH')) {
    exit;
}

class ESP_Config {
    const TEXT_DOMAIN = 'easy-slug-protect';
    const OPTION_KEY = 'esp_settings';

    // 略称から正しい設定名を取得できる定数
    const OPTION_NAMES = array(
        'path'     => 'esp_protected_paths',
        'brute'    => 'esp_bruteforce_settings',
        'remember' => 'esp_remember_settings',
        'mail'     => 'esp_mail_settings'
    );

    const OPTION_DEFAULTS = array(
        'path' => array(),
        'brute' => array(
            'attempts_threshold' => 5,  // 試行回数の上限
            'time_frame' => 10,         // 試行回数のカウント期間（分）
            'block_time_frame' => 60    // ブロック時間（分）
        ),
        'remember' => array(
            'time_frame' => 15,         // ログイン保持期間（日）
            'cookie_prefix' => 'esp'    // Cookieのプレフィックス
        ),
        'mail' => array(
            'enable_notifications' => true,
            'notifications' => array(
                'new_path' => true,
                'password_change' => true,
                'path_remove' => true,
                'brute_force' => false,
                'critical_error' => true
            )
        ),
        'mente' => array(
            'mente_mode' => false,
            'login_page' => '',
            'finish_date' => '',
            'password' => ''
        )
    );

    const DB_TABLES = array(
        'remember' => 'esp_login_remember',
        'brute' => 'esp_login_attempts'
    );
}
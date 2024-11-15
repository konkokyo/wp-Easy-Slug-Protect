<?php
// 直接アクセス禁止
if (!defined('ABSPATH')) {
    exit;
}

/**
 * セットアップクラス
 */
class ESP_Setup {
    public function activate() {
        $this->create_tables();
        $this->create_options();
        flush_rewrite_rules();
    }

    private function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        // ブルートフォース対策用テーブル
        $sql1 = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}esp_login_attempts` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `ip_address` varchar(45) NOT NULL,
            `path` varchar(255) NOT NULL,
            `time` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `ip_path_time` (`ip_address`, `path`, `time`)
        ) {$charset_collate};";

        // ログイン保持用テーブル
        $sql2 = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}esp_login_remember` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `path` varchar(255) NOT NULL,
            `user_id` varchar(32) NOT NULL,
            `token` varchar(64) NOT NULL,
            `created` datetime NOT NULL,
            `expires` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `user_token` (`user_id`, `token`),
            KEY `path_expires` (`path`, `expires`)
        ) {$charset_collate};";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql1);
        dbDelta($sql2);

        // エラーチェック（オプショナル）
        if ($wpdb->last_error) {
            error_log('ESP Table Creation Error: ' . $wpdb->last_error);
        }
    }

    private function create_options() {
        // 保護パス設定
        if (get_option('esp_protected_paths') === false) {
            add_option('esp_protected_paths', array());
        }

        // ブルートフォース対策設定
        if (get_option('esp_bruteforce_settings') === false) {
            add_option('esp_bruteforce_settings', array(
                'attempts_threshold' => 5,  // 試行回数の上限
                'time_frame' => 10,         // 試行回数のカウント期間（分）
                'block_time_frame' => 60    // ブロック時間（分）
            ));
        }

        // ログイン保持設定
        if (get_option('esp_remember_settings') === false) {
            add_option('esp_remember_settings', array(
                'time_frame' => 15,         // ログイン保持期間（日）
                'cookie_prefix' => 'esp'
            ));
        }

        // メール通知設定
        add_option('esp_mail_settings', array(
            'enable_notifications' => true,
            'notify_email' => get_option('admin_email'),
            'notifications' => array(
                'new_path' => true,
                'password_change' => true,
                'path_remove' => true,
                'brute_force' => true,
                'critical_error' => true
            )
        ));
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function uninstall() {
        global $wpdb;
        
        // テーブルの削除
        $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}esp_login_attempts`");
        $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}esp_login_remember`");

        // オプションの削除
        delete_option('esp_protected_paths');
        delete_option('esp_bruteforce_settings');
        delete_option('esp_remember_settings');
    }
}
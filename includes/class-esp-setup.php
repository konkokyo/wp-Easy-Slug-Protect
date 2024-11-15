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
        $sql1 = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}{ESP_Config::DB_TABLES['brute']}` (
            `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            `ip_address` varchar(45) NOT NULL,
            `path` varchar(255) NOT NULL,
            `time` datetime NOT NULL,
            PRIMARY KEY (`id`),
            KEY `ip_path_time` (`ip_address`, `path`, `time`)
        ) {$charset_collate};";

        // ログイン保持用テーブル
        $sql2 = "CREATE TABLE IF NOT EXISTS `{$wpdb->prefix}{ESP_Config::DB_TABLES['remember']}` (
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

    /**
     * コンフィグに基づいてオプション作成
     */
    private function create_options() {
        foreach(ESP_Config::OPTION_NAMES as $key => $option_name){
            if (get_option($option_name) === false) {
                add_option($option_name, ESP_Config::OPTION_DEFAULTS[$key]);
            }
        }
    }

    public function deactivate() {
        flush_rewrite_rules();
    }

    public function uninstall() {
        global $wpdb;
        
        // テーブルの削除
        foreach(ESP_Config::DB_TABLES as $table_name){
            $wpdb->query("DROP TABLE IF EXISTS `{$wpdb->prefix}{$table_name}`");
        }
        // オプションの削除
        foreach(ESP_Config::OPTION_NAMES as $option_name){
            delete_option($option_name);
        }
    }
}
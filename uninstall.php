<?php
// プラグインが直接呼び出された場合は終了
if (!defined('WP_UNINSTALL_PLUGIN')) {
    echo 'die';
    die;
}

// 安全のため、削除対象のプラグインか確認
$plugin_file = basename(dirname(__FILE__)) . '/easy-slug-protect.php';
if (WP_UNINSTALL_PLUGIN !== $plugin_file) {
    die;
}

// プラグインのデータを完全に削除
global $wpdb;

// テーブルの削除
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}esp_login_attempts");
$wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}esp_login_remember");

// オプションの削除
delete_option('esp_protected_paths');
delete_option('esp_bruteforce_settings');
delete_option('esp_remember_settings');

// データベースの最適化（オプショナル）
$wpdb->query("OPTIMIZE TABLE {$wpdb->prefix}options");

// WP-Cronのスケジュールを削除
wp_clear_scheduled_hook('esp_daily_cleanup');
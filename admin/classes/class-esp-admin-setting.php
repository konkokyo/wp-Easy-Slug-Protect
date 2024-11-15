<?php
if (!defined('ABSPATH')) {
    exit;
}

class ESP_Settings {
    
    const SETTING_NAMES = ESP_Config::OPTION_NAMES;
    const DEFAULT_SETTINGS = ESP_Config::OPTION_DEFAULS;

    /**
     * シングルトンインスタンス
     * @var ESP_Settings
     */
    private static $instance = null;
    /**
     * @var bool 初期化フラグ
     */
    private static $initialized = false;

    /**
     * @var ESP_Sanitize サニタイズクラスのインスタンス
     */
    private $sanitize;

    /**
     * @var ESP_Mail メールクラスのインスタンス
     */
    private $mail;

    /**
     * シングルトンのためprivateコンストラクタ
     */
    private function __construct() {
        $this->sanitize = new ESP_Sanitize();
        $this->mail = ESP_Mail::get_instance();
    }

    /**
     * インスタンスの取得
     * 
     * @return ESP_Settings
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 設定の初期化（実行は一度だけ）
     */
    public function init() {
        if (self::$initialized) {
            return;
        }

        // 設定の登録
        add_action('admin_init', [$this, 'register_settings']);

        // 各設定値が更新されたときの処理を登録
        foreach (self::SETTING_NAMES as $short_name => $setting_name) {
            add_action("update_option_{$setting_name}", function ($old_value, $value) use ($short_name) {
                $function_name = 'handle_update_' . $short_name;
                if (method_exists($this, $function_name)) {
                    call_user_func([$this, $function_name], $old_value, $value);
                }
            }, 10, 2);
        }

        self::$initialized = true;
    }

    /**
     * 設定の登録
     */
    public function register_settings() {
        // 保護パスの設定
        register_setting('esp_settings', self::SETTING_NAMES['path'], array(
            'type' => 'array',
            'sanitize_callback' => array($this->sanitize, 'sanitize_protected_paths')
        ));

        // ブルートフォース対策の設定
        register_setting('esp_settings', self::SETTING_NAMES['brute'], array(
            'type' => 'array',
            'sanitize_callback' => array($this->sanitize, 'sanitize_bruteforce_settings'),
            'default' => self::DEFAULT_SETTINGS['brute']
        ));

        // ログイン保持の設定
        register_setting('esp_settings', self::SETTING_NAMES['remember'], array(
            'type' => 'array',
            'sanitize_callback' => array($this->sanitize, 'sanitize_remember_settings'),
            'default' => self::DEFAULT_SETTINGS['remember']
        ));

        // メール通知の設定
        register_setting('esp_settings', self::SETTING_NAMES['mail'], array(
            'type' => 'array',
            'sanitize_callback' => array($this->sanitize, 'sanitize_mail_settings'),
            'default' => self::DEFAULT_SETTINGS['mail']
        ));

    }

    /**
     * パス設定が変更された時のハンドラー
     * 
     * @param array $old_value 古い設定値
     * @param array $new_value 新しい設定値
     */
    private function handle_update_path($old_value, $new_value) {
        if (!is_array($new_value) || !is_array($old_value)) {
            return;
        }

        $old_paths_map = array();
        foreach ($old_value as $old_path) {
            if (isset($old_path['path'])) {
                $old_paths_map[$old_path['path']] = $old_path;
            }
        }

        // メール通知用の一時データを取得
        $raw_passwords = get_transient('esp_raw_passwords');
        delete_transient('esp_raw_passwords'); // 取得後削除

        if (!is_array($raw_passwords)) {
            $raw_passwords = array();
        }

        $current_paths = array();
        // 新規追加と更新の処理
        foreach ($new_value as $new_path) {

            if (!isset($new_path['path'])) {
                continue;
            }

            $current_paths[] = $new_path['path'];
            $path_key = $new_path['path'];

            // 平文パスワードの取得
            if (!isset($raw_passwords[$path_key])) {
                continue; // パスワード変更がない場合はスキップ
            }

            $raw_password = $raw_passwords[$path_key];

            // 既存のパスかチェック
            if (isset($old_paths_map[$path_key])) {
                // パスワードが変更された場合のみ通知
                $this->mail->notify_password_change(
                    $path_key,
                    $raw_password
                );
            } else {
                // 新規パスの処理
                $this->mail->notify_new_protected_path(
                    $path_key,
                    $raw_password
                );
            }
        }

        // 削除されたパスの検出と通知
        foreach ($old_paths_map as $path => $old_path) {
            if (!in_array($path, $current_paths, true)) {
                $this->mail->notify_path_removed($path);
            }
        }
    }

    /**
     * brute
     */
    private function handle_update_brute($old_value, $value){
        return;
    }

    /**
     * remember
     */
    private function handle_update_remember($old_value, $value){
        return;
    }

    /**
     * mail
     */
    private function handle_update_mail($old_value, $value){
        return;
    }

}

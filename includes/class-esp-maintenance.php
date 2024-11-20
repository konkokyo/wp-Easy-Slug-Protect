<?php

// 直接アクセス禁止
if (!defined('ABSPATH')) {
    exit;
}

/**
 * メンテナンスモードクラス
 */
class ESP_Mente {
    private $auth;
    private $security;
    private $session;
    private $cookie;
    private static $instance = null;

    private $mente_settings;

    private function __construct() {
        $this->auth = new ESP_Auth();
        $this->security = new ESP_Security();
        $this->session = ESP_Session::get_instance();
        $this->cookie = ESP_Cookie::get_instance();
        $this->mente_settings = ESP_Option::get_current_setting('mente');
    }

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function check_and_render(){
        if (!$this->check_maintenance()) return;
        $this->display_maintenance_page();
        return; // 念のため
    }

    /**
     * メンテナンスモードの状態をチェック
     */
    private function check_maintenance() {        
        if (!$this->mente_settings['mente_mode']) {
            return false;
        }

        // 終了日時のチェック
        if (!empty($this->mente_settings['finish_date'])) {
            $finish_timestamp = strtotime($this->mente_settings['finish_date']);
            if ($finish_timestamp && time() > $finish_timestamp) {
                return false;
            }
        }

        // ログインチェック
        if ($this->auth->is_logged_in('maintenance')) {
            return false;
        }

        return true;
    }


    /**
     * ログインページの表示
     */
    private function display_maintenance_page() {
        // ログインページIDの取得
        $page_id = $this->mente_settings['login_page'];
        if (empty($page_id)) {
            wp_die(__('メンテナンス中です。', ESP_Config::TEXT_DOMAIN));
        }
        
        // テンプレートローダーの初期化
        global $wp_query;
        $wp_query->is_page = true;
        $wp_query->is_singular = true;
        $wp_query->is_home = false;
        $wp_query->is_archive = false;
        $wp_query->is_category = false;
        unset($wp_query->query['error']);
        $wp_query->query_vars['error'] = '';
        $wp_query->is_404 = false;
        
        // 対象のページデータをセット
        $page = get_post($page_id);
        $wp_query->queried_object = $page;
        $wp_query->queried_object_id = $page_id;
        
        // カスタムテンプレートの適用
        $template = get_page_template_slug($page_id);
        if (empty($template)) {
            $template = 'page.php';
        }
        
        // メンテナンス用のカスタムクラスを追加するフィルター
        add_filter('body_class', function($classes) {
            $classes[] = 'maintenance-mode';
            return $classes;
        });
        
        // メンテナンス中のメッセージをヘッダーに追加
        add_action('wp_head', function() {
            echo '<meta name="robots" content="noindex,nofollow">';
        });
        
        // HTTPステータスコードを503に設定
        status_header(503);
        header('Retry-After: 3600');
        
        // テンプレートの読み込み
        include(get_template_directory() . '/' . $template);
        exit;
    }
}
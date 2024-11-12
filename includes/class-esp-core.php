<?php
// 直接アクセス禁止
if (!defined('ABSPATH')) {
    exit;
}

/**
 * プラグインのコア機能を提供するクラス
 */
class ESP_Core {
    /**
     * @var ESP_Auth 認証クラスのインスタンス
     */
    private $auth;

    /**
     * @var ESP_Security セキュリティクラスのインスタンス
     */
    private $security;

    /**
     * @var ESP_Session セッション管理クラスのインスタンス
     */
    private $session;

    /**
     * @var ESP_Logout ログアウト処理クラスのインスタンス
     */
    private $logout;
    
    /**
     * @var ESP_Cookie ログアウト処理クラスのインスタンス
     */
    private $cookie;

    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->auth = new ESP_Auth();
        $this->logout = new ESP_Logout();
        $this->security = new ESP_Security();
        $this->session = ESP_Session::get_instance();
        $this->cookie = ESP_Cookie::get_instance();
    }

    /**
     * 初期化処理
     */
    public function init() {
        // アクションとフックの登録
        add_action('template_redirect', [$this, 'check_protected_page']);
        add_action('wp_ajax_esp_clean_old_data', [$this, 'clean_old_data']);
        add_action('wp_ajax_nopriv_esp_clean_old_data',[$this, 'clean_old_data']);
        //  ログアウト処理のハンドリング
        add_action('init', [$this, 'handle_logout']);
        // Cookie設定のハンドリング
        add_action('init', [$this, 'handle_cookies'], 1); // 優先度を高く設定
        
        // ショートコードの登録
        add_shortcode('esp_login_form', [$this, 'render_login_form']);
        add_shortcode('esp_logout_button', [$this, 'render_logout_button']);

        // WP-Cronのスケジュール設定
        if (!wp_next_scheduled('esp_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'esp_daily_cleanup');
        }
        add_action('esp_daily_cleanup', [$this, 'daily_cleanup']);
    }

    /**
     * Cookie設定のハンドリング
     */
    public function handle_cookies() {
        if ($this->cookie->has_pending_cookies()) {
            $this->cookie->set_pending_cookies();
        }
    }

    /**
     * 保護ページのチェックと制御
     */
    public function check_protected_page() {
        global $wp;
        global $post;

        // REST APIリクエストと管理画面は除外
        if ($this->is_excluded_request()) {
            return;
        }

        // 現在のパスを取得
        $current_path = '/' . trim($wp->request, '/') . '/';

        // 保護対象のパスを取得
        $protected_paths = get_option('esp_protected_paths', array());

        // 保護対象のパスかチェック
        $target_path = $this->get_matching_protected_path($current_path, $protected_paths);

        // 現在のページがログインページかチェック
        $is_login_page = false;
        if ($post) {
            foreach ($protected_paths as $path) {
                if ($post->ID == $path['login_page']) {
                    $is_login_page = true;
                    $target_path = $path; // ログインページに対応する保護パス設定を取得
                    break;
                }
            }
        }

        // 保護対象でもログインページでもない場合は処理終了
        if (!$target_path && !$is_login_page) {
            return;
        }

        // POSTリクエストの場合はログイン処理を優先
        if (isset($_POST['esp_password'])) {
            $this->handle_login_request($target_path);
            return;
        }

        // ログインページの場合は処理終了（フォームを表示）
        if ($is_login_page) {
            return;
        }

        // ログイン済みの場合は処理終了（アクセスを許可）
        if ($this->auth->is_logged_in($target_path['path'])) {
            return;
        }

        // 未ログインの保護ページアクセスはログインページへリダイレクト
        $this->redirect_to_login_page($target_path, $current_path);
    }

    /**
     * 除外するリクエストかどうかの判定
     * 
     * @return bool 除外する場合はtrue
     */
    private function is_excluded_request() {
        return (
            // REST APIリクエスト
            (defined('REST_REQUEST') && REST_REQUEST) ||
            // 管理画面
            is_admin() ||
            // WP-CLI
            (defined('WP_CLI') && WP_CLI) ||
            // AJAX
            wp_doing_ajax()
        );
    }

    /**
     * 保護対象のパスを取得
     * 
     * @param string $current_path 現在のパス
     * @param array $protected_paths 保護対象のパス一覧
     * @return array|false マッチしたパスの設定。ない場合はfalse
     */
    private function get_matching_protected_path($current_path, $protected_paths) {
        foreach ($protected_paths as $protected_path) {
            if (strpos($current_path, $protected_path['path']) === 0) {
                return $protected_path;
            }
        }
        return false;
    }

    /**
     * ログインリクエストの処理
     * 
     * @param array $path_settings パスの設定
     */
    private function handle_login_request($path_settings) {
        // CSRFチェック
        if (!isset($_POST['esp_nonce']) || 
            !$this->security->verify_nonce($_POST['esp_nonce'], $path_settings['path'])) {
            $this->session->set_error(__('不正なリクエストです。', 'easy-slug-protect'));
            $this->redirect_to_login_page($path_settings, $_SERVER['REQUEST_URI']);
            return;
        }

        // ログイン処理
        $password = isset($_POST['esp_password']) ? $_POST['esp_password'] : '';
        if ($this->auth->process_login($path_settings['path'], $password)) {
            // Cookie設定は handle_cookies で処理されるため、
            // ここでは直接呼び出さない
            
            // ログイン成功時は元のページへリダイレクト
            $redirect_to = isset($_POST['redirect_to']) ? 
                home_url($_POST['redirect_to']) : 
                home_url($path_settings['path']);

            // リダイレクト（cookieクラス使用でcookie適用させる）
            $this->cookie->do_redirect($redirect_to);
        }

        // ログイン失敗時はログインページへリダイレクト
        $this->redirect_to_login_page($path_settings, $_SERVER['REQUEST_URI']);
    }

    /**
     * ログインページへのリダイレクト
     * 
     * @param array $path_settings パスの設定
     * @param string $current_url 現在のURL
     */
    private function redirect_to_login_page($path_settings, $current_url) {
        $login_url = add_query_arg(
            array(
                'redirect_to' => urlencode($current_url)
            ),
            get_permalink($path_settings['login_page'])
        );
        $this->cookie->do_redirect($login_url);
    }

    /**
     * ログインフォームのレンダリング
     * ショートコード [esp_login_form] で使用
     * 
     * @param array $atts ショートコード属性
     * @return string ログインフォームのHTML
     */
    public function render_login_form($atts = array()) {
        // ショートコードの属性を取得（デフォルトで空の path）
        $atts = shortcode_atts(array(
            'path' => ''
        ), $atts);

        // 現在のページのパスを取得
        global $post;
        if (!$post) {
            return '';
        }

        // ショートコードの `path` 属性が指定されているか確認
        $lock_path = $atts['path'] ? '/' . trim($atts['path'], '/') . '/' : null;

        // 保護パス設定から対応するパスを検索
        $protected_paths = get_option('esp_protected_paths', array());
        $target_path = null;

        foreach ($protected_paths as $path) {
            // `path`属性が指定されている場合はそれを使用
            if ($lock_path && $path['path'] === $lock_path) {
                $target_path = $path;
                break;
            }
            // 指定がない場合は現在のページIDに基づいてパスを検索
            elseif (!$lock_path && $path['login_page'] == $post->ID) {
                $target_path = $path;
                break;
            }
        }

        if (!$target_path) {
            return '';
        }

        // リダイレクト先の取得
        $redirect_to = isset($_GET['redirect_to']) ? $_GET['redirect_to'] : '';
        return $this->auth->get_login_form($target_path['path'], $redirect_to);
    }


    /**
     * ログアウト処理のハンドリング
     */
    public function handle_logout() {
        if (isset($_POST['esp_action']) && $_POST['esp_action'] === 'logout') {
            $this->logout->process_logout();
        }
    }

    /**
     * 古いデータのクリーンアップ
     * WP-Cronで実行
     */
    public function daily_cleanup() {
        $this->security->cleanup_old_attempts();
        
        global $wpdb;
        // 期限切れのログイン保持情報を削除
        $wpdb->query(
            "DELETE FROM {$wpdb->prefix}esp_login_remember 
            WHERE expires < NOW()"
        );

        // 期限切れのCookieも削除
        if ($remember_cookies = $this->cookie->get_remember_cookies()) {
            $this->cookie->clear_remember_cookies();
        }
    }

}
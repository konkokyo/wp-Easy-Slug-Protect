<?php
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 保護されたページをクエリから除外するクラス
 */
class ESP_Filter {
    /**
     * @var ESP_Auth 認証クラスのインスタンス
     */
    private $auth;

    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->auth = new ESP_Auth();
    }

    /**
     * 初期化処理
     */
    public function init() {
        add_action('pre_get_posts', [$this, 'exclude_protected_posts']);
    }

    /**
     * 保護されたページをクエリから除外する
     */
    public function exclude_protected_posts($query) {
        // 管理画面やREST APIでは適用しない
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST)) {
            return;
        }

        // メインクエリのみ対象
        if (!$query->is_main_query()) {
            return;
        }

        // 検索、アーカイブ、タグ、フィード、サイトマップを対象
        if (
            $query->is_search() ||
            $query->is_archive() ||
            $query->is_tag() ||
            $query->is_feed() ||
            (function_exists('is_sitemap') && is_sitemap())
        ) {
            $excluded_post_ids = $this->get_protected_post_ids();

            if (!empty($excluded_post_ids)) {
                $query->set('post__not_in', array_merge($query->get('post__not_in', []), $excluded_post_ids));
            }
        }
    }

    /**
     * 保護されたページのIDを取得する
     * @return array 保護されたページのIDの配列
     */
    private function get_protected_post_ids() {
        $protected_paths = ESP_Option::get_current_setting('path');
        $excluded_post_ids = [];

        // ログインしていない保護パスを収集
        $paths_to_exclude = [];
        foreach ($protected_paths as $path_setting) {
            $path = $path_setting['path'];

            // ユーザーがこのパスに対してログインしているかをチェック
            if ($this->auth->is_logged_in($path)) {
                continue;
            }

            $paths_to_exclude[] = $path;
        }

        if (empty($paths_to_exclude)) {
            return [];
        }

        // すべての公開された投稿を取得
        $all_posts = get_posts([
            'post_type'   => 'any',
            'post_status' => 'publish',
            'numberposts' => -1,
            'fields'      => 'ids',
        ]);

        foreach ($all_posts as $post_id) {
            $permalink = get_permalink($post_id);
            $parsed_url = parse_url($permalink);
            $path = isset($parsed_url['path']) ? '/' . trim($parsed_url['path'], '/') . '/' : '/';

            foreach ($paths_to_exclude as $protected_path) {
                $protected_path = '/' . trim($protected_path, '/') . '/';
                if (strpos($path, $protected_path) === 0) {
                    $excluded_post_ids[] = $post_id;
                    break;
                }
            }
        }

        return $excluded_post_ids;
    }
}

<?php
// 直接アクセス禁止
if (!defined('ABSPATH')) {
    exit;
}

/**
 * 管理画面の設定を提供するクラス
 */
class ESP_Admin {
    /**
     * 初期化
     */
    public function __construct() {
        // 管理メニューの追加
        add_action('admin_menu', array($this, 'add_admin_menu'));
        // 設定の登録
        add_action('admin_init', array($this, 'register_settings'));
        // 管理画面用のスタイルとスクリプトを読み込み
        add_action('admin_enqueue_scripts', array($this, 'enqueue_admin_assets'));
    }

    /**
     * 管理メニューの追加
     */
    public function add_admin_menu() {
        add_menu_page(
            'Easy Slug Protect', // ページタイトル
            'Easy Slug Protect', // メニュータイトル
            'manage_options',    // 必要な権限
            'esp-settings',      // メニューのスラッグ
            array($this, 'render_settings_page'), // 表示用の関数
            'dashicons-lock',    // アイコン
            80                   // 位置
        );
    }

    /**
     * 設定の登録
     */
    public function register_settings() {
        // 保護パスの設定
        register_setting('esp_settings', 'esp_protected_paths', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_protected_paths')
        ));

        // ブルートフォース対策の設定
        register_setting('esp_settings', 'esp_bruteforce_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_bruteforce_settings'),
            'default' => array(
                'attempts_threshold' => 5,  // 試行回数の上限
                'time_frame' => 10,         // 試行回数のカウント期間（分）
                'block_time_frame' => 60    // ブロック時間（分）
            )
        ));

        // ログイン保持の設定
        register_setting('esp_settings', 'esp_remember_settings', array(
            'type' => 'array',
            'sanitize_callback' => array($this, 'sanitize_remember_settings'),
            'default' => array(
                'time_frame' => 15,         // ログイン保持期間（日）
                'cookie_prefix' => 'esp'    // Cookieのプレフィックス
            )
        ));
    }

    /**
     * 設定ページの表示
     */
    public function render_settings_page() {
        // 現在の設定値を取得
        $protected_paths = get_option('esp_protected_paths', array());
        $bruteforce_settings = get_option('esp_bruteforce_settings');
        $remember_settings = get_option('esp_remember_settings');

        ?>
        <div class="wrap">
            <h1><?php _e('Easy Slug Protect 設定', 'easy-slug-protect'); ?></h1>

            <form method="post" action="options.php" id="esp-settings-form">
                <?php settings_fields('esp_settings'); ?>

                <!-- 保護パスの設定セクション -->
                <div class="esp-section">
                    <h2><?php _e('保護するパスの設定', 'easy-slug-protect'); ?></h2>
                    <button type="button" class="button" id="esp-add-path">
                        <?php _e('保護パスを追加', 'easy-slug-protect'); ?>
                    </button>
                    <div class="esp-paths-container" id="esp-paths-container">
                        <?php if (!empty($protected_paths)): ?>
                            <?php foreach ($protected_paths as $index => $path): ?>
                                <div class="esp-path-item">
                                    <div class="esp-path-header">
                                        <h3><?php echo esc_html($path['path']); ?></h3>
                                        <button type="button" class="button esp-remove-path">削除</button>
                                    </div>
                                    <div class="esp-path-content">
                                        <p>
                                            <label><?php _e('パス:', 'easy-slug-protect'); ?></label>
                                            <input type="text" 
                                                name="esp_protected_paths[<?php echo $index; ?>][path]" 
                                                value="<?php echo esc_attr($path['path']); ?>"
                                                class="regular-text"
                                                placeholder="/example/"
                                                required>
                                        </p>
                                        <p>
                                            <label><?php _e('パスワード:', 'easy-slug-protect'); ?></label>
                                            <input type="password" 
                                                name="esp_protected_paths[<?php echo $index; ?>][password]" 
                                                class="regular-text"
                                                placeholder="<?php _e('変更する場合のみ入力', 'easy-slug-protect'); ?>">
                                            <span class="description">
                                                <?php _e('空白の場合、既存のパスワードが維持されます', 'easy-slug-protect'); ?>
                                            </span>
                                        </p>
                                        <p>
                                            <label><?php _e('ログインページ:', 'easy-slug-protect'); ?></label>
                                            <?php 
                                            wp_dropdown_pages(array(
                                                'name' => "esp_protected_paths[{$index}][login_page]",
                                                'selected' => $path['login_page'],
                                                'show_option_none' => __('選択してください', 'easy-slug-protect'),
                                                'option_none_value' => '0'
                                            )); 
                                            ?>
                                            <span class="description">
                                                <?php _e('選択したページに [esp_login_form] ショートコードを配置してください', 'easy-slug-protect'); ?>
                                            </span>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <?php submit_button(); ?>
                </div>

                <!-- ブルートフォース対策の設定セクション -->
                <div class="esp-section">
                    <h2><?php _e('ブルートフォース対策設定', 'easy-slug-protect'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="esp-attempts-threshold">
                                    <?php _e('試行回数の上限', 'easy-slug-protect'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" 
                                    id="esp-attempts-threshold"
                                    name="esp_bruteforce_settings[attempts_threshold]"
                                    value="<?php echo esc_attr($bruteforce_settings['attempts_threshold']); ?>"
                                    min="1"
                                    required>
                                <p class="description">
                                    <?php _e('この回数を超えるとアクセスがブロックされます', 'easy-slug-protect'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="esp-time-frame">
                                    <?php _e('試行回数のカウント期間', 'easy-slug-protect'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" 
                                    id="esp-time-frame"
                                    name="esp_bruteforce_settings[time_frame]"
                                    value="<?php echo esc_attr($bruteforce_settings['time_frame']); ?>"
                                    min="1"
                                    required>
                                <?php _e('分', 'easy-slug-protect'); ?>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <label for="esp-block-time-frame">
                                    <?php _e('ブロック時間', 'easy-slug-protect'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" 
                                    id="esp-block-time-frame"
                                    name="esp_bruteforce_settings[block_time_frame]"
                                    value="<?php echo esc_attr($bruteforce_settings['block_time_frame']); ?>"
                                    min="1"
                                    required>
                                <?php _e('分', 'easy-slug-protect'); ?>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </div>

                <!-- ログイン保持の設定セクション -->
                <div class="esp-section">
                    <h2><?php _e('ログイン保持設定', 'easy-slug-protect'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="esp-remember-time">
                                    <?php _e('ログイン保持期間', 'easy-slug-protect'); ?>
                                </label>
                            </th>
                            <td>
                                <input type="number" 
                                    id="esp-remember-time"
                                    name="esp_remember_settings[time_frame]"
                                    value="<?php echo esc_attr($remember_settings['time_frame']); ?>"
                                    min="1"
                                    required>
                                <?php _e('日', 'easy-slug-protect'); ?>
                            </td>
                        </tr>
                    </table>
                    <?php submit_button(); ?>
                </div>   
            </form>
        </div>
        <?php
    }

    /**
     * 保護パス設定のサニタイズ
     */
    public function sanitize_protected_paths($paths) {
        if (!is_array($paths)) {
            return array();
        }

        $sanitized = array();
        $existing_paths = get_option('esp_protected_paths', array());
        $unique_paths = array(); // 重複チェック用
        $login_pages = array();  // login_pageの重複チェック用

        foreach ($paths as $path) {
            if (empty($path['path']) || empty($path['login_page'])) {
                continue;
            }

            // パスの正規化と重複チェック
            $normalized_path = '/' . trim(sanitize_text_field($path['path']), '/') . '/';
            if (in_array($normalized_path, $unique_paths, true)) {
                // パスが重複している場合はスキップ
                continue;
            }
            $unique_paths[] = $normalized_path;

            // ログインページの重複チェック
            $login_page_id = absint($path['login_page']);
            if (in_array($login_page_id, $login_pages, true)) {
                add_settings_error('esp_protected_paths', 'duplicate_login_page', __('同じログインページが複数のパスに設定されています。各パスに一意のログインページを選択してください。', 'easy-slug-protect'));
                return new WP_Error('duplicate_login_page', __('同じログインページが複数のパスに設定されています。', 'easy-slug-protect'));
            }
            $login_pages[] = $login_page_id;

            // サニタイズされたパス情報を準備
            $sanitized_path = array(
                'path' => $normalized_path,
                'login_page' => $login_page_id
            );

            // パスワードが入力された場合のみハッシュ化
            if (!empty($path['password'])) {
                $sanitized_path['password'] = wp_hash_password($path['password']);
            } else {
                // 既存のパスワードを維持
                foreach ($existing_paths as $existing) {
                    if ($existing['path'] === $sanitized_path['path']) {
                        $sanitized_path['password'] = $existing['password'];
                        break;
                    }
                }
            }

            $sanitized[] = $sanitized_path;
        }

        return $sanitized;
    }


    /**
     * ブルートフォース対策設定のサニタイズ
     */
    public function sanitize_bruteforce_settings($settings) {
        return array(
            'attempts_threshold' => max(1, absint($settings['attempts_threshold'])),
            'time_frame' => max(1, absint($settings['time_frame'])),
            'block_time_frame' => max(1, absint($settings['block_time_frame']))
        );
    }

    /**
     * ログイン保持設定のサニタイズ
     */
    public function sanitize_remember_settings($settings) {
        return array(
            'time_frame' => max(1, absint($settings['time_frame'])),
            'cookie_prefix' => 'esp' // 固定値
        );
    }

    /**
     * 管理画面用のアセット読み込み
     */
    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_esp-settings' !== $hook) {
            return;
        }

        // CSSの読み込み
        wp_enqueue_style(
            'esp-admin-styles',
            ESP_URL . 'admin/esp-admin.css',
            array(),
            ESP_VERSION
        );

        // JavaScriptの読み込み
        wp_enqueue_script(
            'esp-admin-scripts',
            ESP_URL . 'admin/esp-admin.js',
            array('jquery'),
            "0.0.8",
            true
        );

        // ページ一覧の取得
        $pages = get_pages();
        $pages_options = '';
        foreach ($pages as $page) {
            $pages_options .= sprintf(
                '<option value="%d">%s</option>',
                $page->ID,
                esc_html($page->post_title)
            );
        }

        // JavaScriptに渡すデータ
        wp_localize_script(
            'esp-admin-scripts',
            'espAdminData',
            array(
                'pages_list' => $pages_options,
                'i18n' => array(
                    'confirmDelete' => __('この保護パスを削除してもよろしいですか？', 'easy-slug-protect'),
                    'confirmSave' => __('設定を保存してもよろしいですか？', 'easy-slug-protect'),
                    'unsavedChanges' => __('未保存の変更があります。このページを離れてもよろしいですか？', 'easy-slug-protect'),
                    'duplicatePath' => __('このパスは既に使用されています', 'easy-slug-protect'),
                    'show' => __('表示', 'easy-slug-protect'),
                    'hide' => __('非表示', 'easy-slug-protect'),
                    'selectPage' => __('選択してください', 'easy-slug-protect'),
                    'newProtectedPath' => __('新しい保護パス', 'easy-slug-protect'),
                    'delete' => __('削除', 'easy-slug-protect'),
                    'path' => __('パス:', 'easy-slug-protect'),
                    'password' => __('パスワード:', 'easy-slug-protect'),
                    'loginPage' => __('ログインページ:', 'easy-slug-protect'),
                    'shortcodeNotice' => __('[esp_login_form] ショートコードを配置してください', 'easy-slug-protect')
                )
            )
        );
    }
}
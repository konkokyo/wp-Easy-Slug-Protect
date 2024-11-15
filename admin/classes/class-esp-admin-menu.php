<?php
// 直接アクセス禁止
if (!defined('ABSPATH')) {
    exit;
}

class ESP_Admin_Menu {
    /**
     * @var ESP_Settings 設定操作クラスのインスタンス
     */
    private $settings;

    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        $this->settings = ESP_Settings::get_instance();
    }

    public function add_admin_menu() {
        add_menu_page(
            'Easy Slug Protect',
            'Easy Slug Protect',
            'manage_options',
            'esp-settings',
            array($this, 'render_settings_page'),
            'dashicons-lock',
            80
        );
    }

    public function render_settings_page() {
        // 現在の設定値を取得（デフォルト値を指定）
        $protected_paths = $this->settings->get_current_setting('path');
        $bruteforce_settings = $this->settings->get_current_setting('brute');
        $remember_settings = $this->settings->get_current_setting('remember');
        $mail_settings = $this->settings->get_current_setting('mail');

        // 設定名を取得
        $path_setting_name = ESP_Settings::SETTING_NAMES['path'];
        $brute_setting_name = ESP_Settings::SETTING_NAMES['brute'];
        $remember_setting_name = ESP_Settings::SETTING_NAMES['remember'];
        $mail_setting_name = ESP_Settings::SETTING_NAMES['mail'];

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
                                                name="<?php echo $path_setting_name; ?>[<?php echo $index; ?>][path]" 
                                                value="<?php echo esc_attr($path['path']); ?>"
                                                class="regular-text"
                                                placeholder="/example/"
                                                required>
                                        </p>
                                        <p>
                                            <label><?php _e('パスワード:', 'easy-slug-protect'); ?></label>
                                            <input type="password" 
                                                name="<?php echo $path_setting_name; ?>[<?php echo $index; ?>][password]" 
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
                                                'name' => "{$path_setting_name}[{$index}][login_page]",
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
                                    name="<?php echo $brute_setting_name; ?>[attempts_threshold]"
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
                                    name="<?php echo $brute_setting_name; ?>[time_frame]"
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
                                    name="<?php echo $brute_setting_name; ?>[block_time_frame]"
                                    value="<?php echo esc_attr($bruteforce_settings['block_time_frame']); ?>"
                                    min="1"
                                    required>
                                <?php _e('分', 'easy-slug-protect'); ?>
                            </td>
                        </tr>
                    </table>
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
                                    name="<?php echo $remember_setting_name; ?>[time_frame]"
                                    value="<?php echo esc_attr($remember_settings['time_frame']); ?>"
                                    min="1"
                                    required>
                                <?php _e('日', 'easy-slug-protect'); ?>
                            </td>
                        </tr>
                    </table>
                </div>

                <div class="esp-section">
                    <h2><?php _e('メール通知設定', 'easy-slug-protect'); ?></h2>
                    <table class="form-table">
                        <tr>
                            <th scope="row">
                                <label for="esp-enable-notifications">
                                    <?php _e('メール通知', 'easy-slug-protect'); ?>
                                </label>
                            </th>
                            <td>
                                <label>
                                    <input type="checkbox" 
                                        id="esp-enable-notifications"
                                        name="<?php echo $mail_setting_name; ?>[enable_notifications]"
                                        value="1"
                                        <?php checked($mail_settings['enable_notifications']); ?>>
                                    <?php _e('メール通知を有効にする', 'easy-slug-protect'); ?>
                                </label>
                                <p class="description">
                                    <?php _e('通知メールは管理者権限を持つすべてのユーザーに送信されます。', 'easy-slug-protect'); ?>
                                </p>
                            </td>
                        </tr>
                        <tr>
                            <th scope="row">
                                <?php _e('通知する項目', 'easy-slug-protect'); ?>
                            </th>
                            <td>
                                <fieldset class="esp-notification-items">
                                    <!-- デフォルトで通知項目配列が存在することを保証 -->
                                    <?php $notifications = isset($mail_settings['notifications']) ? $mail_settings['notifications'] : array(); ?>
                                    
                                    <label <?php echo !$mail_settings['enable_notifications'] ? 'class="esp-disabled"' : ''; ?>>
                                        <input type="checkbox" 
                                            name="<?php echo $mail_setting_name; ?>[notifications][new_path]"
                                            value="1"
                                            <?php checked(isset($notifications['new_path']) && $notifications['new_path']); ?>
                                            <?php disabled(!$mail_settings['enable_notifications']); ?>>
                                        <?php _e('新しい保護パスの追加', 'easy-slug-protect'); ?>
                                    </label>
                                    <br>
                                    
                                    <label <?php echo !$mail_settings['enable_notifications'] ? 'class="esp-disabled"' : ''; ?>>
                                        <input type="checkbox" 
                                            name="<?php echo $mail_setting_name; ?>[notifications][password_change]"
                                            value="1"
                                            <?php checked(isset($notifications['password_change']) && $notifications['password_change']); ?>
                                            <?php disabled(!$mail_settings['enable_notifications']); ?>>
                                        <?php _e('パスワードの変更', 'easy-slug-protect'); ?>
                                    </label>
                                    <br>
                                    
                                    <label <?php echo !$mail_settings['enable_notifications'] ? 'class="esp-disabled"' : ''; ?>>
                                        <input type="checkbox" 
                                            name="<?php echo $mail_setting_name; ?>[notifications][path_remove]"
                                            value="1"
                                            <?php checked(isset($notifications['path_remove']) && $notifications['path_remove']); ?>
                                            <?php disabled(!$mail_settings['enable_notifications']); ?>>
                                        <?php _e('保護パスの削除', 'easy-slug-protect'); ?>
                                    </label>
                                    <br>
                                    
                                    <label <?php echo !$mail_settings['enable_notifications'] ? 'class="esp-disabled"' : ''; ?>>
                                        <input type="checkbox" 
                                            name="<?php echo $mail_setting_name; ?>[notifications][brute_force]"
                                            value="1"
                                            <?php checked(isset($notifications['brute_force']) && $notifications['brute_force']); ?>
                                            <?php disabled(!$mail_settings['enable_notifications']); ?>>
                                        <?php _e('ブルートフォース攻撃の検知', 'easy-slug-protect'); ?>
                                    </label>
                                    <br>
                                    
                                    <label <?php echo !$mail_settings['enable_notifications'] ? 'class="esp-disabled"' : ''; ?>>
                                        <input type="checkbox" 
                                            name="<?php echo $mail_setting_name; ?>[notifications][critical_error]"
                                            value="1"
                                            <?php checked(isset($notifications['critical_error']) && $notifications['critical_error']); ?>
                                            <?php disabled(!$mail_settings['enable_notifications']); ?>>
                                        <?php _e('重大なエラーの発生', 'easy-slug-protect'); ?>
                                    </label>
                                </fieldset>
                                <p class="description">
                                    <?php _e('通知メールは管理者権限を持つすべてのユーザーに送信されます。', 'easy-slug-protect'); ?>
                                </p>
                            </td>
                        </tr>
                    </table>
                </div>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}

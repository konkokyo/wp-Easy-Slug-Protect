(function ($) {
	"use strict";

	// DOM読み込み完了時の処理
	$(document).ready(function () {
		const ESP_Admin = {
			// 初期化
			init: function () {
				this.pathCount = $(".esp-path-item").length;
				this.bindEvents();
				this.setupFormValidation();
				this.setupUnsavedChangesWarning();
			},

			// イベントのバインド
			bindEvents: function () {
				$("#esp-add-path").on("click", this.addNewPath.bind(this));
				$(document).on("click", ".esp-remove-path", this.removePath.bind(this));
				this.setupPasswordToggle();
			},

			/**
			 * 新しい保護パスの追加
			 * @param {Event} e イベントオブジェクト
			 */
			addNewPath: function (e) {
				e.preventDefault();
				const template = `
					<div class="esp-path-item" style="display: none;">
						<div class="esp-path-header">
							<h3>${espAdminData.i18n.newProtectedPath}</h3>
							<button type="button" class="button esp-remove-path">
								${espAdminData.i18n.delete}
							</button>
						</div>
						<div class="esp-path-content">
							<p>
								<label>${espAdminData.i18n.path}</label>
								<input type="text" 
									name="esp_protected_paths[${this.pathCount}][path]" 
									class="esp-path-input regular-text"
									placeholder="/example/"
									required>
								<span class="description">
									${wp.i18n.__("例: /members/ または /private/docs/", "easy-slug-protect")}
								</span>
							</p>
							<p>
								<label>${espAdminData.i18n.password}</label>
								<div class="esp-password-field">
									<input type="password" 
										name="esp_protected_paths[${this.pathCount}][password]" 
										class="regular-text"
										required>
									<button type="button" class="button esp-toggle-password">
										${espAdminData.i18n.show}
									</button>
								</div>
							</p>
							<p>
								<label>${espAdminData.i18n.loginPage}</label>
								${this.getPageSelectHTML(this.pathCount)}
								<span class="description">
									${espAdminData.i18n.shortcodeNotice}
								</span>
							</p>
						</div>
					</div>
				`;

				const $newPath = $(template);
				$("#esp-paths-container").append($newPath);
				$newPath.slideDown(300);
				this.pathCount++;
				this.markFormAsUnsaved();
			},

			/**
			 * ページ選択のセレクトボックスHTML生成
			 * @param {number} index インデックス
			 * @returns {string} セレクトボックスのHTML
			 */
			getPageSelectHTML: function (index) {
				return `<select name="esp_protected_paths[${index}][login_page]" required>
					<option value="">${espAdminData.i18n.selectPage}</option>
					${espAdminData.pages_list}
				</select>`;
			},

			/**
			 * 保護パスの削除
			 * @param {Event} e イベントオブジェクト
			 */
			removePath: function (e) {
				e.preventDefault();
				const $pathItem = $(e.target).closest(".esp-path-item");

				// 確認ダイアログ
				if (
					confirm(
						wp.i18n.__(
							"この保護パスを削除してもよろしいですか？",
							"easy-slug-protect"
						)
					)
				) {
					$pathItem.addClass("removing").slideUp(300, function () {
						$(this).remove();
					});
					this.markFormAsUnsaved();
				}
			},

			/**
			 * パスワード表示切り替えの設定
			 */
			setupPasswordToggle: function () {
				$(document).on("click", ".esp-toggle-password", function (e) {
					const $button = $(this);
					const $input = $button.siblings("input");

					// パスワード表示トグル
					if ($input.attr("type") === "password") {
						$input.attr("type", "text");
						$button.text(espAdminData.i18n.hide);
					} else {
						$input.attr("type", "password");
						$button.text(espAdminData.i18n.show);
					}
				});
			},

			/**
			 * フォームバリデーションの設定
			 */
			setupFormValidation: function () {
				$("#esp-settings-form").on("submit", function (e) {
					const $form = $(this);

					// HTML5バリデーション
					if (!this.checkValidity()) {
						e.preventDefault();
						return false;
					}

					// 数値の範囲チェック
					const numericalInputs = {
						attempts_threshold: { min: 1, max: 100 },
						time_frame: { min: 1, max: 1440 },
						block_time_frame: { min: 1, max: 10080 },
						remember_time: { min: 1, max: 365 },
					};

					let isValid = true;
					$.each(numericalInputs, function (name, range) {
						const $input = $form.find(`[name*="${name}"]`);
						const value = parseInt($input.val(), 10);

						if (value < range.min || value > range.max) {
							isValid = false;
							alert(
								wp.i18n.__(
									`${name}は${range.min}から${range.max}の間で設定してください`,
									"easy-slug-protect"
								)
							);
							$input.focus();
							return false;
						}
					});

					if (!isValid) {
						e.preventDefault();
						return false;
					}

					// pathの重複チェックと形式チェック
					const paths = [];
					const loginPages = new Set();
					$(".esp-path-input").each(function () {
						let path = $(this).val().trim();

						// パスの形式を正規化
						if (path && !path.startsWith("/")) {
							path = "/" + path;
						}
						if (path && !path.endsWith("/")) {
							path += "/";
						}
						$(this).val(path);

						// 重複チェック
						if (paths.includes(path)) {
							alert(
								wp.i18n.__(
									"パスが重複しています。各パスは一意でなければなりません。",
									"easy-slug-protect"
								)
							);
							$(this).focus();
							isValid = false;
							return false;
						}

						paths.push(path);
					});

					// ログインページの重複チェック
					$(".esp-path-content select[name*='[login_page]']").each(function () {
						const loginPage = $(this).val();
						if (loginPage && loginPages.has(loginPage)) {
							alert(
								wp.i18n.__(
									"同じログインページが複数のパスに設定されています。各パスに一意のログインページを選択してください。",
									"easy-slug-protect"
								)
							);
							$(this).focus();
							isValid = false;
							return false;
						}
						loginPages.add(loginPage);
					});

					// もし重複があれば送信をキャンセル
					if (!isValid) {
						e.preventDefault();
						return false;
					}

					// 保存前の確認
					return confirm(
						wp.i18n.__("設定を保存してもよろしいですか？", "easy-slug-protect")
					);
				});
			},

			/**
			 * 未保存の変更がある場合の警告設定
			 */
			setupUnsavedChangesWarning: function () {
				let hasUnsavedChanges = false;

				// フォームの変更を検知
				$("#esp-settings-form").on("change", "input, select", function () {
					hasUnsavedChanges = true;
					$(".esp-unsaved").slideDown(300);
				});

				// フォーム送信時にフラグをリセット
				$("#esp-settings-form").on("submit", function () {
					hasUnsavedChanges = false;
					$(".esp-unsaved").slideUp(300);
				});

				// ページ離脱時の警告
				$(window).on("beforeunload", function () {
					if (hasUnsavedChanges) {
						return wp.i18n.__(
							"未保存の変更があります。このページを離れてもよろしいですか？",
							"easy-slug-protect"
						);
					}
				});
			},

			/**
			 * フォームを未保存状態としてマーク
			 */
			markFormAsUnsaved: function () {
				$("#esp-settings-form").trigger("change");
			},
		};

		// 初期化実行
		ESP_Admin.init();
	});
})(jQuery);

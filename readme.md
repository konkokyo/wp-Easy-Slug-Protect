# 任意階層パスワードプラグイン

名称: Easy Slug Protect
略称: esp

## プラグインファイル構造

```
easy-slug-protect/
├── easy-slug-protect.php        # メインファイル
├── uninstall.php               # アンインストール処理
├── readme.txt                  # プラグイン説明
│
├── includes/                  # コアファイル
│   ├── class-esp-core.php     # コアクラス
│   ├── class-esp-auth.php     # 認証処理
│   ├── class-esp-cookie.php     # cookie操作
│   ├── class-esp-logout.php     # ログアウト操作
│   ├── class-esp-security      # 保護処理
│   └── class-esp-session.php  # セッション管理
│   ├── class-esp-setup.php    # セットアップ
│
├── admin/                      # 管理画面
│   ├── class-esp-admin.php    # 管理画面クラス
│   ├── esp-admin.js       # 管理画面JS
│   │   └──
│   └── esp-admin.css      # 管理画面CSS
│
└── languages/                  # 翻訳ファイル（未実装）
    ├── easy-slug-protect-ja.po
    └── easy-slug-protect-ja.mo
```

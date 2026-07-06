# spa-pen

A lightweight Single Page Application (SPA) playground and showcase tool powered by PHP and SQLite3.

<img width="1910" height="1023" alt="image" src="https://github.com/user-attachments/assets/e1718039-c65c-43d6-ad11-1f19958d9158" />


PHPとSQLite3で動作する、軽量なシングルページアプリケーション（SPA）の開発・保存・公開用プレイグラウンドです。
フロントエンドツール（HTML/Vanilla JS/CSS）を安全に蓄積し、第三者へ共有リンクで公開することができます。

## 🚀 特徴

- **安全なデータ展開**: `json_encode` を用いた堅牢なデータ展開により、コード内の文字列衝突による画面崩壊を防ぎます。
- **管理者モード（基本認証）**: `index.php` は管理者のみがアクセスできるよう基本認証でガード。
- **ショーケースモード**: `view.php` を通じて、認証を必要とせずに第三者へ作品を全画面プレビュー・共有可能。
- **依存ゼロ**: サーバー側はPHPの標準機能とSQLite3のみで動作し、npm等の重い外部依存はありません。

## 🛠️ インストール・設置方法

1. このリポジトリの `index.php`, `view.php`, `.htaccess` をサーバーの同一ディレクトリに配置します。
2. 同一ディレクトリ内に、基本認証用の `.htpasswd` ファイルを作成します。
3. `.htaccess` 内の `AuthUserFile` のパスを、あなたのサーバーの絶対パスに書き換えてください。
   ```apache
   AuthUserFile /home/users/.htpasswd
   ```
   
4. 設置ディレクトリに書き込み権限（SQLiteデータベースの自動生成用）があることを確認してください。
   ※初回アクセス時に playground.sqlite が自動的に作成されます。

## 📝 ライセンス

MIT License

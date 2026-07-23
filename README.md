# TGS Scout

PHP と JSON で構成した、スカウト候補者ディレクトリの最小プロジェクトです。

## 必要環境

- PHP 8.1 以上
- Git

## 起動

プロジェクトのルートで以下を実行します。

```powershell
php -S localhost:8000 -t public
```

ブラウザで http://localhost:8000 を開いてください。

## 構成

- `public/index.php`: HTML ページ
- `public/api/scouts.php`: JSON API
- `src/JsonRepository.php`: JSON ファイルの読み込み
- `data/scouts.json`: プロフィールデータ
- `public/assets/style.css`: 画面スタイル

プロフィールを追加する場合は `data/scouts.json` を編集します。API は `GET /api/scouts.php` で利用できます。

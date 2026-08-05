# TGS SCOUT 2026

## データ管理画面

`/admin.php` から学生・作品JSONを追加、編集できます。管理画面は環境変数
`TGS_ADMIN_PASSWORD` が設定されている場合だけ利用できます。

Apacheで `.htaccess` が使えるFTPサーバーでは、公開ディレクトリの `.htaccess` に
次のように設定できます（実際には十分長い固有のパスワードを使用してください）。

```apacheconf
SetEnv TGS_ADMIN_PASSWORD "change-this-password"
```

PHPプロセスに `data/` の書き込み権限が必要です。一般的なレンタルサーバーでは
ディレクトリを `755`、JSONファイルを `644` で開始し、書き込めない場合だけ
サーバー会社の推奨値（例: `775` / `664`）へ変更してください。`777` は避けます。

保存は同じディレクトリ内の一時ファイルへ排他書き込み後、元ファイルと置換します。
FTPへの再アップロードでサーバー上の更新を上書きしないよう、更新前には
`data/*.json` をダウンロードしてバックアップしてください。

TGS 2026の展示作品を起点に、学生クリエイターの担当箇所・技術・ポートフォリオを企業担当者へ紹介するPHPサイトです。

## 必要環境

- PHP 7.4以上（`mbstring`推奨）

## 起動

プロジェクト直下の `.env` に管理画面用パスワードを設定します。

```dotenv
TGS_ADMIN_PASSWORD=ローカル管理画面用のパスワード
```

```powershell
php -S localhost:8000 -t public
```

公開サイトは `http://localhost:8000`、管理画面は
`http://localhost:8000/admin.php` を開いてください。`.env` はローカル環境でのみ
自動的に読み込まれ、Webサーバー側ですでに設定されている環境変数は上書きしません。

ページ内リンクとアセットURLは実行中の公開パスから自動判定されます。同じファイル一式で、ローカルの `/` とFTP公開先の `/it-work/TGS_Scout/` の両方に対応します。

## ページ

- `/` トップ
- `/teams.php` 出展作品一覧
- `/team_detail.php?id=t01` 作品詳細（QRコード遷移先）
- `/students.php` 学生一覧・絞り込み
- `/student_detail.php?id=1` 学生プロフィール
- `/contact.php` 問い合わせUI（デモ版のため送信なし）
- `/api/scouts.php` 公開中の学生JSON

## データ更新

- `data/students.json` 学生情報
- `data/featured_students.json` トップページの注目学生情報
- `data/teams.json` 作品情報
- `data/mapping.json` 学生と作品の所属関係

`portfolio_url` にGoogle DriveのファイルURLを設定すると、学生詳細ページにPDFプレビューが表示されます。VIVIVITなどの外部作品ページも同じ項目を利用します。

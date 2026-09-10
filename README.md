# TGS SCOUT 2026

学生タブの写真アップロード・Google共有ドライブ設定は [学生プロフィール写真仕様書](docs/学生プロフィール写真仕様書.md) を参照してください。PHPのGD・cURL・OpenSSLとサービスアカウント設定が必要です。

## データ管理画面

`/admin.php` の共通フォームからログインできます。管理者はID `admin` と `TGS_ADMIN_PASSWORD`、
学生は自分の学生IDまたは氏名（漢字）と `TGS_STUDENT_PASSWORD`（学生共通）を入力します。
それぞれ未設定の区分はログインできません。ローカルでは非公開の `.env` に設定できます。
学生は自分のプロフィール・写真と参加作品のみ編集でき、公開状態・試遊台番号・参加メンバー・注目学生は管理者が管理します。
共通パスワードのため、学生IDを入力した人の本人確認は行いません。
詳細は [adminページ仕様書](docs/adminページ仕様書.md) を参照してください。

Apacheで `.htaccess` が使えるFTPサーバーでは、公開ディレクトリの `.htaccess` に
次のように設定できます（実際には十分長い固有のパスワードを使用してください）。

```apacheconf
SetEnv TGS_ADMIN_PASSWORD "change-this-password"
SetEnv TGS_STUDENT_PASSWORD "different-student-password"
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
- `/contact.php` 問い合わせフォーム（入力→確認→送信→完了）。メール送信設定は `docs/問い合わせ環境設定.md` を参照
- `/api/scouts.php` 公開中の学生JSON
- `/teams_admin.php` 作品情報の更新と作品QRコードの自動作成
- 作品管理の「03 プロジェクトメンバー」で最大8名の学生をプルダウンで選択し、役職を改行区切りで複数入力。「メンバーを保存する」で保存（未選択に戻すと所属解除）
- `/qr.php?code=...` 管理QRの固定リダイレクトURL

## データ更新

- `data/students.json` 学生情報
- `data/featured_students.json` トップページの注目学生情報
- `data/teams.json` 作品情報
- `data/mapping.json` 学生と作品の所属関係

資料URLはすべて任意で、入力済みの項目だけ表示します。全項目が空欄なら資料セクションを非表示にします。

| JSON項目 | 収集項目 |
| --- | --- |
| `portfolio_drive_url` | ポートフォリオ（Google Drive URL） |
| `portfolio_site_url` | ポートフォリオ（外部サイトURL） |
| `source_code_drive_url` | ソースコード（Google Drive URL） |
| `source_code_site_url` | ソースコード（外部サイトURL） |
| `public_work_url` | 公開可能な作品のURL |

管理画面から5項目を登録できます。項目名は `docs/学生データ仕様書.md` に準拠します。Google Driveファイル（PDFなど）とGoogleスライドのポートフォリオはプレビューを表示し、フォルダ・外部サイト・ソースコード・作品URLは別タブで開くリンクを表示します。資料の閲覧権限が必要です。未入力の項目は空文字列にします。

## エラー画面

`/error.php?status=404` または `/error.php?status=500` で共通エラー画面を表示します。
学生・作品が見つからない場合や無効なQRコードは404画面へ、予期しない処理例外・管理画面の保存失敗は500画面へ303リダイレクトします。遷移先は対応するHTTPステータスを返します。
入力不備・ログイン失敗はフォーム内で表示し、APIはエラーJSONを返します。
エラー画面はデータ読み込みに依存せず、データファイル障害時にも表示できます。
共通処理の読み込み前のPHP構文エラーや、存在しないファイルへのアクセスなどWebサーバーが返すエラーは対象外です。これらも共通画面にする場合は、公開先サーバーのエラーページ設定が別途必要です。

### サーバー設定なしでのエラー対応

管理画面のCSRF検証失敗は403画面へ遷移します。未ログイン時はログイン画面へ誘導し、通常の入力ミスはフォーム内に表示します。
403・404・500・503画面は `/error.php?status=403` のように確認できます。

メンテナンス時はプロジェクト直下の `.env` に `TGS_MAINTENANCE=true` を設定します。
公開ページ・管理画面は503画面へ遷移し、APIは503のJSONを返します。復旧時は `false` に変更するか設定を削除してください。
503応答には `Retry-After: 300` を付けます。エラー画面と静的ファイルは引き続き表示できます。
Webサーバーが同名の環境変数を設定している場合は、その値が優先されます。
PHPが実行されないアクセス拒否や存在しないファイルへのアクセスは、サーバー標準のエラー画面になります。

`.htaccess` に以下を設定することでサーバ内部の処理で表示できます
サーバ側で `ErrorDocument` の利用が許可されている必要があります

```
ErrorDocument 403 /it-work/TGS_Scout/error.php?status=403
ErrorDocument 404 /it-work/TGS_Scout/error.php?status=404
ErrorDocument 500 /it-work/TGS_Scout/error.php?status=500
ErrorDocument 503 /it-work/TGS_Scout/error.php?status=503
```

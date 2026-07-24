# TGS SCOUT 2026

TGS 2026の展示作品を起点に、学生クリエイターの担当箇所・技術・ポートフォリオを企業担当者へ紹介するPHPサイトです。

## 必要環境

- PHP 7.4以上（`mbstring`推奨）

## 起動

```powershell
php -S localhost:8000 -t public
```

`http://localhost:8000` を開いてください。

## ページ

- `/` トップ
- `/teams.php` 出展作品一覧
- `/team_detail.php?id=t01` 作品詳細（QRコード遷移先）
- `/students.php` 学生一覧・絞り込み
- `/student_detail.php?id=s001` 学生プロフィール
- `/contact.php` 問い合わせUI（デモ版のため送信なし）
- `/api/scouts.php` 公開中の学生JSON

## データ更新

- `data/students.json` 学生情報
- `data/teams.json` 作品情報
- `data/mapping.json` 学生と作品の所属関係

Google Driveプレビューは学生データの `drive_pdf_id` にファイルIDを設定すると表示されます。デザイナーの外部作品ページは `vivivit_url` を利用します。

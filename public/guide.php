<?php
declare(strict_types=1);
require_once is_file(__DIR__ . '/src/bootstrap.php') ? __DIR__ . '/src/bootstrap.php' : dirname(__DIR__) . '/src/bootstrap.php';
renderHeader('企業の方へ', 'guide');
?>
<section class="page-hero"><p class="section-number">FOR RECRUITERS / COMPANIES</p><h1>企業の方へ</h1><p>作品から学生の担当実績を確認し、学校を窓口として面談・採用・インターンをご相談いただけます。</p></section>
<section class="section-pad">
    <p class="section-number">HOW TO USE</p>
    <div class="steps-grid">
        <article><span>01</span><h2>作品を見る</h2><p>試遊台QRまたは作品一覧から、ゲーム情報と制作メンバーを確認します。</p></article>
        <article><span>02</span><h2>実績を確認する</h2><p>学生プロフィールで担当箇所、技術、ポートフォリオや企画書を確認します。</p></article>
        <article><span>03</span><h2>学校へ相談する</h2><p>気になる学生や作品を指定し、面談・採用・説明会についてお問い合わせください。</p></article>
    </div>
</section>
<section class="intro section-pad"><p class="section-number">CONTACT POLICY</p><div class="intro-grid"><h2>学生の連絡先は<br>公開していません。</h2><div><p>企業の皆さまと学生の双方が安心してやり取りできるよう、国際理工カレッジが連絡窓口となります。</p><a class="button button-primary" href="<?= e(url('contact.php')) ?>">学校へ問い合わせる <span>→</span></a></div></div></section>
<?php renderFooter(); ?>

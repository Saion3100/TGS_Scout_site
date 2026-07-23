<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/src/JsonRepository.php';

$repository = new JsonRepository(dirname(__DIR__) . '/data/scouts.json');
$scouts = $repository->all();
function escape(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="ja">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>TGS Scout | Talent directory</title>
    <link rel="stylesheet" href="/assets/style.css">
</head>
<body>
    <header class="site-header">
        <a class="brand" href="/">TGS<span>Scout</span></a>
        <span class="header-label">Talent directory / 2026</span>
    </header>

    <main>
        <section class="hero">
            <p class="eyebrow">Find the people who move ideas forward</p>
            <h1>チームの次の一手を、<br><em>人</em>から見つける。</h1>
            <p class="hero-copy">技術と視点を持つスカウト候補を、シンプルなデータで見つけるためのディレクトリ。</p>
            <div class="hero-meta"><strong><?= count($scouts) ?></strong> profiles indexed <span></span> JSON powered</div>
        </section>

        <section class="directory" aria-labelledby="directory-title">
            <div class="section-heading">
                <div>
                    <p class="eyebrow">Current roster</p>
                    <h2 id="directory-title">Scout profiles</h2>
                </div>
                <a class="api-link" href="/api/scouts.php">View JSON API <span>↗</span></a>
            </div>
            <div class="profile-grid">
                <?php foreach ($scouts as $scout): ?>
                    <article class="profile-card">
                        <div class="profile-top">
                            <span class="avatar"><?= escape(mb_substr((string) $scout['name'], 0, 1)) ?></span>
                            <span class="status"><?= escape((string) $scout['status']) ?></span>
                        </div>
                        <h3><?= escape((string) $scout['name']) ?></h3>
                        <p class="role"><?= escape((string) $scout['role']) ?></p>
                        <p class="location">◎ <?= escape((string) $scout['location']) ?></p>
                        <div class="skills">
                            <?php foreach ($scout['skills'] as $skill): ?>
                                <span><?= escape((string) $skill) ?></span>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    </main>

    <footer>Built with PHP + JSON <span>•</span> TGS Scout</footer>
</body>
</html>

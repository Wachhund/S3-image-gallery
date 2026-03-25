<?php
/** @var string $title */
/** @var string $content */
/** @var array $breadcrumbs */
/** @var array $flash */

$breadcrumbs = $breadcrumbs ?? [];
$flash = $flash ?? [];
$title = $title ?? 'S3 Image Gallery';
$pageTitle = ($title !== 'S3 Image Gallery') ? "{$title} — S3 Gallery" : 'S3 Image Gallery';
?>
<!DOCTYPE html>
<html lang="de" style="color-scheme: dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#141211">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="S3 Image Gallery — Modern photo gallery powered by PHP, Slim 4 and S3-compatible storage.">
    <link rel="stylesheet" href="/css/style.css">
</head>
<body>
    <a href="#main-content" class="skip-link">Zum Inhalt springen</a>

    <header class="site-header" role="banner">
        <div class="site-header__inner">
            <?php include __DIR__ . '/partials/header.php'; ?>
            <nav class="site-nav" role="navigation" aria-label="Hauptnavigation">
                <?php include __DIR__ . '/partials/nav.php'; ?>
            </nav>
        </div>
    </header>

    <main id="main-content" class="site-main" role="main">
        <?php if (!empty($breadcrumbs)): ?>
            <?php include __DIR__ . '/partials/breadcrumbs.php'; ?>
        <?php endif; ?>

        <?php if (!empty($flash)): ?>
            <?php include __DIR__ . '/partials/flash.php'; ?>
        <?php endif; ?>

        <?= $content ?>
    </main>

    <footer class="site-footer" role="contentinfo">
        <?php include __DIR__ . '/partials/footer.php'; ?>
    </footer>
</body>
</html>

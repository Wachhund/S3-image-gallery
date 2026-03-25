<?php
/** @var array $breadcrumbs — Array of ['label' => string, 'url' => string|null] */
$count = count($breadcrumbs);
if ($count === 0) return;
?>
<nav class="breadcrumbs" aria-label="Brotkrumen-Navigation">
    <?php foreach ($breadcrumbs as $i => $crumb): ?>
        <span class="breadcrumbs__item">
            <?php if ($i === $count - 1): ?>
                <span class="breadcrumbs__current" aria-current="page"
                      title="<?= htmlspecialchars($crumb['label']) ?>"
                ><?= htmlspecialchars($crumb['label']) ?></span>
            <?php else: ?>
                <a href="<?= htmlspecialchars($crumb['url'] ?? '#') ?>"
                   class="breadcrumbs__link"
                   title="<?= htmlspecialchars($crumb['label']) ?>"
                ><?= htmlspecialchars($crumb['label']) ?></a>
                <span class="breadcrumbs__separator" aria-hidden="true">/</span>
            <?php endif; ?>
        </span>
    <?php endforeach; ?>
</nav>

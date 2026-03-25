<?php
/** @var array $dir */
/** @var array $subdirs */
/** @var array $images */
/** @var int $imageCount */
/** @var int $page */
/** @var int $perPage */
/** @var int $totalPages */
?>

<h1><?= htmlspecialchars(basename($dir['dirname'])) ?></h1>

<?php if (!empty($subdirs)): ?>
    <section aria-label="Unterverzeichnisse">
        <div class="grid grid--wide">
            <?php foreach ($subdirs as $subdir): ?>
                <a href="/browse/<?= (int) $subdir['id'] ?>" class="card">
                    <div class="card__image">
                        <div class="placeholder">
                            <svg viewBox="0 0 48 48" fill="none" stroke="currentColor"
                                 stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <rect x="6" y="10" width="36" height="28" rx="3"/>
                                <circle cx="17" cy="21" r="3.5"/>
                                <path d="M42 30l-9.5-8.5a2 2 0 00-2.7.1L20 32"/>
                            </svg>
                        </div>
                    </div>
                    <div class="card__body">
                        <h2 class="card__title"><?= htmlspecialchars(basename($subdir['dirname'])) ?></h2>
                        <span class="card__meta"><?= (int) ($subdir['image_count'] ?? 0) ?> Bilder</span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
    <?php if (!empty($images)): ?>
        <hr class="rule">
    <?php endif; ?>
<?php endif; ?>

<?php if (!empty($images)): ?>
    <section aria-label="Bilder">
        <div class="grid">
            <?php foreach ($images as $image): ?>
                <a href="/image/<?= (int) $image['id'] ?>" class="card" target="_blank" rel="noopener">
                    <div class="card__image">
                        <?php if (!empty($image['thumb_name'])): ?>
                            <img src="/image/<?= (int) $image['id'] ?>?thumb=1"
                                 alt="<?= htmlspecialchars(basename($image['name'])) ?>"
                                 loading="lazy"
                                 width="300" height="225">
                        <?php else: ?>
                            <div class="placeholder">
                                <svg viewBox="0 0 48 48" fill="none" stroke="currentColor"
                                     stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <rect x="6" y="10" width="36" height="28" rx="3"/>
                                    <circle cx="17" cy="21" r="3.5"/>
                                    <path d="M42 30l-9.5-8.5a2 2 0 00-2.7.1L20 32"/>
                                </svg>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="card__body">
                        <span class="card__title"><?= htmlspecialchars(basename($image['name'])) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav class="pagination" aria-label="Seitennavigation">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="btn btn--secondary btn--sm">&larr; Zurück</a>
                <?php endif; ?>
                <span class="pagination__info text-muted text-sm">
                    Seite <?= $page ?> von <?= $totalPages ?>
                </span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="btn btn--secondary btn--sm">Weiter &rarr;</a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>

<?php elseif (empty($subdirs)): ?>
    <p class="text-muted">Keine Bilder in diesem Verzeichnis.</p>
<?php endif; ?>

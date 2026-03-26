<?php
/** @var array $dir */
/** @var array $subdirs */
/** @var array $images */
/** @var int $imageCount */
/** @var int $page */
/** @var int $perPage */
/** @var int $totalPages */

$isAuthenticated = !empty($_SESSION['authenticated']);
$csrfToken = $_SESSION['csrf_token'] ?? '';
$isEventDir = (int) ($dir['parent_id'] ?? 0) > 0;
?>

<div style="display: flex; align-items: center; justify-content: space-between; gap: var(--space-4); flex-wrap: wrap;">
    <h1 style="margin-bottom: 0;"><?= htmlspecialchars(basename($dir['dirname'])) ?></h1>
    <?php if ($isAuthenticated && $isEventDir): ?>
        <form method="post" action="/gallery/<?= (int) $dir['id'] ?>/delete" style="margin: 0;"
              onsubmit="return confirm('Galerie mit <?= $imageCount ?> Bildern wirklich löschen?')">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
            <button type="submit" class="btn btn--secondary btn--sm">Galerie löschen</button>
        </form>
    <?php endif; ?>
</div>
<div style="margin-bottom: var(--space-6);"></div>

<?php if (!empty($subdirs)): ?>
    <section aria-label="Unterverzeichnisse">
        <div class="grid grid--wide">
            <?php foreach ($subdirs as $subdir): ?>
                <a href="/browse/<?= (int) $subdir['id'] ?>" class="card">
                    <div class="card__image">
                        <?php if (!empty($subdir['preview_image_id'])): ?>
                            <img src="/image/<?= (int) $subdir['preview_image_id'] ?>?thumb=1"
                                 alt="<?= htmlspecialchars(basename($subdir['dirname'])) ?>"
                                 loading="lazy" width="300" height="225">
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
                <div class="card">
                    <a href="/image/<?= (int) $image['id'] ?>" target="_blank" rel="noopener">
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
                    </a>
                    <div class="card__body" style="display: flex; align-items: center; justify-content: space-between;">
                        <span class="card__title" style="flex: 1; min-width: 0;"><?= htmlspecialchars(basename($image['name'])) ?></span>
                        <?php if ($isAuthenticated): ?>
                            <form method="post" action="/image/<?= (int) $image['id'] ?>/delete" style="margin: 0; flex-shrink: 0;"
                                  onsubmit="return confirm('Bild wirklich löschen?')">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <button type="submit" class="btn btn--secondary btn--sm" title="Bild löschen" aria-label="<?= htmlspecialchars(basename($image['name'])) ?> löschen">&#x2715;</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
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

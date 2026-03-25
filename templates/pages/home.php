<?php
/** @var array $years — Array of year directories (empty for now) */
$years = $years ?? [];
?>

<?php if (empty($years)): ?>
    <section class="empty-state" aria-labelledby="empty-title">
        <svg class="empty-state__icon" viewBox="0 0 80 80" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
            <rect x="8" y="16" width="64" height="48" rx="6" stroke="currentColor" stroke-width="2"/>
            <circle cx="28" cy="34" r="7" stroke="currentColor" stroke-width="2"/>
            <path d="M72 50L55 36a4 4 0 00-5.2.2L32 56" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
            <path d="M48 58L38 50a4 4 0 00-4.8 0L8 70" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
        </svg>

        <h1 id="empty-title" class="empty-state__title">Noch keine Bilder</h1>
        <p class="empty-state__description">
            Die Galerie ist leer. Lade Bilder in den S3-Bucket hoch
            und starte den Scanner, um die Sammlung aufzubauen.
        </p>

        <div class="empty-state__hint">
            <code>docker-compose exec php php bin/scan.php</code>
        </div>
    </section>

<?php else: ?>
    <h1>Galerie</h1>
    <div class="grid grid--wide">
        <?php foreach ($years as $year): ?>
            <a href="/browse/<?= htmlspecialchars((string)($year['id'] ?? '')) ?>" class="card">
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
                    <h2 class="card__title"><?= htmlspecialchars($year['dirname'] ?? '') ?></h2>
                    <span class="card__meta"><?= (int)($year['count'] ?? 0) ?> Einträge</span>
                </div>
            </a>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

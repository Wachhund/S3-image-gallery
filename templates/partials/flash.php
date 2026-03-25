<?php
/** @var array $flash — Array of ['type' => 'success'|'error', 'message' => string] */
foreach ($flash as $msg):
    $type = $msg['type'] ?? 'success';
    $cssClass = ($type === 'error') ? 'flash--error' : 'flash--success';
    $iconPath = ($type === 'error')
        ? 'M12 9v4m0 4h.01M12 3a9 9 0 100 18 9 9 0 000-18z'
        : 'M9 12l2 2 4-4m5 2a9 9 0 11-18 0 9 9 0 0118 0z';
?>
    <div class="flash <?= $cssClass ?>" role="alert">
        <svg class="flash__icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="<?= $iconPath ?>"/>
        </svg>
        <span><?= htmlspecialchars($msg['message'] ?? '') ?></span>
    </div>
<?php endforeach; ?>

<?php
$currentPath = $_SERVER['REQUEST_URI'] ?? '/';
$currentPath = strtok($currentPath, '?');

$navItems = [
    ['url' => '/',        'label' => 'Galerie'],
    ['url' => '/upload',  'label' => 'Upload'],
];
?>
<?php foreach ($navItems as $item): ?>
    <a href="<?= htmlspecialchars($item['url']) ?>"
       class="site-nav__link"
       <?= ($currentPath === $item['url']) ? 'aria-current="page"' : '' ?>
    ><?= htmlspecialchars($item['label']) ?></a>
<?php endforeach; ?>

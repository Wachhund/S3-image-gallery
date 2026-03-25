<?php
$currentPath = $_SERVER['REQUEST_URI'] ?? '/';
$currentPath = strtok($currentPath, '?');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$isAuthenticated = !empty($_SESSION['authenticated']);

$navItems = [
    ['url' => '/', 'label' => 'Galerie', 'auth' => false],
];

if ($isAuthenticated) {
    $navItems[] = ['url' => '/gallery/create', 'label' => 'Neue Galerie', 'auth' => true];
    $navItems[] = ['url' => '/upload', 'label' => 'Upload', 'auth' => true];
    $navItems[] = ['url' => '/logout', 'label' => 'Abmelden', 'auth' => true];
} else {
    $navItems[] = ['url' => '/login', 'label' => 'Anmelden', 'auth' => false];
}
?>
<?php foreach ($navItems as $item): ?>
    <a href="<?= htmlspecialchars($item['url']) ?>"
       class="site-nav__link"
       <?= ($currentPath === $item['url']) ? 'aria-current="page"' : '' ?>
    ><?= htmlspecialchars($item['label']) ?></a>
<?php endforeach; ?>

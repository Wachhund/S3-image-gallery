<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->addRoutingMiddleware();
$app->addErrorMiddleware(
    displayErrorDetails: (bool) ($_ENV['APP_DEBUG'] ?? true),
    logErrors: true,
    logErrorDetails: true,
);

$templatePath = __DIR__ . '/../templates';
$renderer = new PhpRenderer($templatePath);
$renderer->setLayout('layout.php');

$app->get('/', function (Request $request, Response $response) use ($renderer): Response {
    return $renderer->render($response, 'pages/home.php', [
        'title' => 'S3 Image Gallery',
        'breadcrumbs' => [
            ['label' => 'Home', 'url' => '/'],
        ],
        'years' => [],
    ]);
});

$app->run();

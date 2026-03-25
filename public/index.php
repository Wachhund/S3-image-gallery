<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();

$app->addRoutingMiddleware();
$app->addErrorMiddleware(
    displayErrorDetails: (bool) ($_ENV['APP_DEBUG'] ?? true),
    logErrors: true,
    logErrorDetails: true,
);

$app->get('/', function (Request $request, Response $response): Response {
    $response->getBody()->write('S3 Image Gallery is running.');
    return $response;
});

$app->run();

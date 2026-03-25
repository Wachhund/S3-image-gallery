<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use S3Gallery\Service\DatabaseFactory;
use S3Gallery\Service\GalleryService;
use S3Gallery\Service\S3ClientFactory;

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

$db = DatabaseFactory::create();
$gallery = new GalleryService($db);

// Home — top-level directories
$app->get('/', function (Request $request, Response $response) use ($renderer, $gallery): Response {
    $years = $gallery->getTopLevelDirs();

    return $renderer->render($response, 'pages/home.php', [
        'title' => 'S3 Image Gallery',
        'breadcrumbs' => [['label' => 'Home', 'url' => null]],
        'years' => $years,
    ]);
});

// Browse — directory view with subdirs + images
$app->get('/browse/{id:\d+}', function (Request $request, Response $response, array $args) use ($renderer, $gallery): Response {
    $dirId = (int) $args['id'];
    $dir = $gallery->getDir($dirId);

    if ($dir === null) {
        return $response->withStatus(404);
    }

    $page = max(1, (int) ($request->getQueryParams()['page'] ?? 1));
    $perPage = 60;

    $subdirs = $gallery->getSubDirs($dirId);
    $images = $gallery->getImagesWithThumbs($dirId, $page, $perPage);
    $imageCount = $gallery->getImageCount($dirId);
    $totalPages = (int) ceil($imageCount / $perPage) ?: 1;

    return $renderer->render($response, 'pages/browse.php', [
        'title' => basename($dir['dirname']),
        'breadcrumbs' => $gallery->buildBreadcrumbs($dirId),
        'dir' => $dir,
        'subdirs' => $subdirs,
        'images' => $images,
        'imageCount' => $imageCount,
        'page' => $page,
        'perPage' => $perPage,
        'totalPages' => $totalPages,
    ]);
});

// Image proxy — streams image from S3
$app->get('/image/{id:\d+}', function (Request $request, Response $response, array $args) use ($gallery): Response {
    $imageId = (int) $args['id'];
    $image = $gallery->getImage($imageId);

    if ($image === null) {
        return $response->withStatus(404);
    }

    $isThumb = ($request->getQueryParams()['thumb'] ?? '') === '1';
    $s3Key = $isThumb && !empty($image['thumb_name'])
        ? $image['thumb_name']
        : $image['name'];

    try {
        $s3 = S3ClientFactory::create();
        $bucket = $_ENV['S3_BUCKET'] ?? 'gallery';

        $result = $s3->getObject([
            'Bucket' => $bucket,
            'Key' => $s3Key,
        ]);

        $contentType = $result['ContentType'] ?? 'image/jpeg';
        $body = $result['Body'];

        $response = $response
            ->withHeader('Content-Type', $contentType)
            ->withHeader('Cache-Control', 'public, max-age=86400')
            ->withHeader('X-Content-Type-Options', 'nosniff');

        if (isset($result['ETag'])) {
            $response = $response->withHeader('ETag', $result['ETag']);
        }

        $response->getBody()->write((string) $body);

        return $response;
    } catch (\Throwable $e) {
        return $response->withStatus(502);
    }
});

$app->run();

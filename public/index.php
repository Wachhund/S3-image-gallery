<?php

declare(strict_types=1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;
use Slim\Views\PhpRenderer;
use S3Gallery\Service\DatabaseFactory;
use S3Gallery\Service\GalleryService;
use S3Gallery\Service\PasskeyService;
use S3Gallery\Service\S3ClientFactory;
use S3Gallery\Service\UploadService;

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
$passkey = new PasskeyService($db);

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

// --- Auth: Passkey Registration ---

$app->get('/register', function (Request $request, Response $response) use ($renderer, $passkey): Response {
    if (!$passkey->isOtpConfigured()) {
        $status = 'disabled';
    } elseif ($passkey->hasPasskeys()) {
        $status = 'has_passkey';
    } elseif ($passkey->isOtpConsumed()) {
        $status = 'consumed';
    } else {
        $status = 'ready';
    }

    return $renderer->render($response, 'pages/register.php', [
        'title' => 'Passkey registrieren',
        'status' => $status,
    ]);
});

$app->post('/register/challenge', function (Request $request, Response $response) use ($passkey): Response {
    $body = json_decode((string) $request->getBody(), true) ?? [];
    $otp = $body['otp'] ?? '';

    if ($passkey->isOtpConsumed() || $passkey->hasPasskeys()) {
        $response->getBody()->write(json_encode(['error' => 'Registrierung nicht mehr verfügbar.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
    }

    if (!$passkey->verifyOtp($otp)) {
        $response->getBody()->write(json_encode(['error' => 'Ungültiges Einmal-Passwort.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
    }

    session_start();
    $createArgs = $passkey->getCreateArgs();
    $_SESSION['webauthn_challenge'] = $passkey->getChallenge();

    $response->getBody()->write(json_encode(['options' => $createArgs]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/register/complete', function (Request $request, Response $response) use ($passkey): Response {
    session_start();
    $challengeHex = $_SESSION['webauthn_challenge'] ?? '';
    unset($_SESSION['webauthn_challenge']);

    if ($challengeHex === '') {
        $response->getBody()->write(json_encode(['error' => 'Keine Challenge gefunden. Bitte erneut versuchen.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $body = json_decode((string) $request->getBody(), true) ?? [];

    try {
        $passkey->processRegistration(
            $body['clientDataJSON'] ?? '',
            $body['attestationObject'] ?? '',
            $challengeHex,
        );

        $response->getBody()->write(json_encode(['success' => true]));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
        $response->getBody()->write(json_encode(['error' => 'Registrierung fehlgeschlagen: ' . $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }
});

// --- Auth: Login ---

$app->get('/login', function (Request $request, Response $response) use ($renderer, $passkey): Response {
    session_start();
    if (!empty($_SESSION['authenticated'])) {
        return $response->withHeader('Location', '/')->withStatus(302);
    }

    return $renderer->render($response, 'pages/login.php', [
        'title' => 'Anmelden',
        'hasPasskeys' => $passkey->hasPasskeys(),
    ]);
});

$app->post('/login/challenge', function (Request $request, Response $response) use ($passkey): Response {
    if (!$passkey->hasPasskeys()) {
        $response->getBody()->write(json_encode(['error' => 'Kein Passkey registriert.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    session_start();
    $getArgs = $passkey->getGetArgs();
    $_SESSION['webauthn_challenge'] = $passkey->getChallenge();

    $response->getBody()->write(json_encode(['options' => $getArgs]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/login/complete', function (Request $request, Response $response) use ($passkey): Response {
    session_start();
    $challengeHex = $_SESSION['webauthn_challenge'] ?? '';
    unset($_SESSION['webauthn_challenge']);

    if ($challengeHex === '') {
        $response->getBody()->write(json_encode(['error' => 'Keine Challenge gefunden.']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $body = json_decode((string) $request->getBody(), true) ?? [];

    try {
        $ok = $passkey->processAuthentication(
            $body['id'] ?? '',
            $body['clientDataJSON'] ?? '',
            $body['authenticatorData'] ?? '',
            $body['signature'] ?? '',
            $challengeHex,
        );

        if (!$ok) {
            throw new \RuntimeException('Passkey nicht erkannt.');
        }

        $_SESSION['authenticated'] = true;
        $response->getBody()->write(json_encode(['success' => true, 'redirect' => '/']));
        return $response->withHeader('Content-Type', 'application/json');
    } catch (\Throwable $e) {
        $response->getBody()->write(json_encode(['error' => 'Anmeldung fehlgeschlagen: ' . $e->getMessage()]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
    }
});

$app->get('/logout', function (Request $request, Response $response): Response {
    session_start();
    session_destroy();
    return $response->withHeader('Location', '/')->withStatus(302);
});

// --- Auth-protected: Event Gallery Management ---

$app->get('/gallery/create', function (Request $request, Response $response) use ($renderer): Response {
    session_start();
    if (empty($_SESSION['authenticated'])) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;

    return $renderer->render($response, 'pages/gallery-create.php', [
        'title' => 'Event-Galerie anlegen',
        'csrfToken' => $csrfToken,
    ]);
});

$app->post('/gallery/create', function (Request $request, Response $response) use ($renderer, $gallery): Response {
    session_start();
    if (empty($_SESSION['authenticated'])) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    $data = $request->getParsedBody() ?? [];
    $csrfToken = $data['csrf_token'] ?? '';
    $eventDate = trim($data['event_date'] ?? '');
    $eventName = trim($data['event_name'] ?? '');

    $errors = [];

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        $errors[] = 'Ungültiges Formular-Token. Bitte erneut versuchen.';
    }

    $dateError = GalleryService::validateDate($eventDate);
    if ($dateError) {
        $errors[] = $dateError;
    }

    $nameError = GalleryService::validateEventName($eventName);
    if ($nameError) {
        $errors[] = $nameError;
    }

    if (empty($errors) && $gallery->eventGalleryExists($eventDate, $eventName)) {
        $errors[] = 'Eine Galerie mit diesem Datum und Namen existiert bereits.';
    }

    if (!empty($errors)) {
        $newCsrf = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $newCsrf;

        return $renderer->render($response, 'pages/gallery-create.php', [
            'title' => 'Event-Galerie anlegen',
            'csrfToken' => $newCsrf,
            'errors' => $errors,
            'old' => ['event_date' => $eventDate, 'event_name' => $eventName],
        ]);
    }

    $bucket = $_ENV['S3_BUCKET'] ?? 'gallery';
    $dirId = $gallery->createEventGallery($eventDate, $eventName, $bucket);

    return $response->withHeader('Location', '/browse/' . $dirId)->withStatus(302);
});

// --- Auth-protected: Image Upload ---

$app->get('/upload', function (Request $request, Response $response) use ($renderer, $gallery): Response {
    session_start();
    if (empty($_SESSION['authenticated'])) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    $csrfToken = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $csrfToken;

    $allDirs = $gallery->getAllDirs();

    return $renderer->render($response, 'pages/upload.php', [
        'title' => 'Bilder hochladen',
        'csrfToken' => $csrfToken,
        'dirs' => $allDirs,
    ]);
});

$app->post('/upload', function (Request $request, Response $response) use ($renderer, $gallery): Response {
    session_start();
    if (empty($_SESSION['authenticated'])) {
        return $response->withHeader('Location', '/login')->withStatus(302);
    }

    $data = $request->getParsedBody() ?? [];
    $csrfToken = $data['csrf_token'] ?? '';
    $dirId = (int) ($data['dir_id'] ?? 0);

    $errors = [];
    $successes = [];

    if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrfToken)) {
        $errors[] = 'Ungültiges Formular-Token. Bitte erneut versuchen.';
    }

    $dir = $gallery->getDir($dirId);
    if ($dir === null) {
        $errors[] = 'Ungültiges Zielverzeichnis.';
    }

    $files = $_FILES['images'] ?? [];

    if (empty($errors) && !empty($files['name'][0])) {
        $bucket = $_ENV['S3_BUCKET'] ?? 'gallery';
        $s3 = S3ClientFactory::create();
        $upload = new UploadService($s3, DatabaseFactory::create(), $bucket);

        $fileCount = count($files['name']);
        for ($i = 0; $i < $fileCount; $i++) {
            $file = [
                'name' => $files['name'][$i],
                'type' => $files['type'][$i],
                'tmp_name' => $files['tmp_name'][$i],
                'error' => $files['error'][$i],
                'size' => $files['size'][$i],
            ];

            $validationError = $upload->validateFile($file);
            if ($validationError !== null) {
                $errors[] = "{$file['name']}: {$validationError}";
                continue;
            }

            try {
                $result = $upload->processUpload($file, $dirId, $dir['dirname']);
                $successes[] = "{$file['name']} erfolgreich hochgeladen.";
            } catch (\Throwable $e) {
                $errors[] = "{$file['name']}: Upload fehlgeschlagen.";
            }
        }
    } elseif (empty($errors)) {
        $errors[] = 'Keine Dateien ausgewählt.';
    }

    if (!empty($successes) && empty($errors)) {
        return $response->withHeader('Location', '/browse/' . $dirId)->withStatus(302);
    }

    $newCsrf = bin2hex(random_bytes(32));
    $_SESSION['csrf_token'] = $newCsrf;

    return $renderer->render($response, 'pages/upload.php', [
        'title' => 'Bilder hochladen',
        'csrfToken' => $newCsrf,
        'dirs' => $gallery->getAllDirs(),
        'errors' => $errors,
        'successes' => $successes,
        'selectedDir' => $dirId,
    ]);
});

$app->run();

<?php
$projectRoot = realpath(dirname(__DIR__));
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
$requestPath = is_string($requestPath) ? rawurldecode($requestPath) : '/';
$relativePath = ltrim($requestPath, '/');

if ($relativePath === '' || substr($relativePath, -1) === '/') {
    $relativePath .= 'index.php';
}

$target = realpath($projectRoot . DIRECTORY_SEPARATOR . $relativePath);
if ($target !== false && is_dir($target)) {
    $relativePath = rtrim($relativePath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'index.php';
    $target = realpath($projectRoot . DIRECTORY_SEPARATOR . $relativePath);
}
$rootPrefix = $projectRoot . DIRECTORY_SEPARATOR;

if ($target === false || strpos($target, $rootPrefix) !== 0 || !is_file($target)) {
    http_response_code(404);
    exit('Not found.');
}

if (strtolower(pathinfo($target, PATHINFO_EXTENSION)) === 'php') {
    $allowedPages = array(
        'index.php',
        'index1.php',
        'login.php',
        'forgot_password.php',
        'logout.php',
        'change_password.php',
        'scan.php',
        'api/scan.php',
        'api/check_student.php',
    );
    $isAllowedPage = in_array($relativePath, $allowedPages, true)
        || (strpos($relativePath, 'admin/') === 0
            && preg_match('/^admin\/(?!_layout\.php$)[a-z0-9_]+\.php$/i', $relativePath));

    if (!$isAllowedPage || $target === __FILE__) {
        http_response_code(404);
        exit('Not found.');
    }

    $_SERVER['SCRIPT_FILENAME'] = $target;
    $_SERVER['SCRIPT_NAME'] = $requestPath;
    $_SERVER['PHP_SELF'] = $requestPath;
    require $target;
    exit;
}

$staticRoots = array('assets', 'logo', 'Admin Pictures');
$isStaticAsset = false;
foreach ($staticRoots as $staticRoot) {
    if (strpos($relativePath, $staticRoot . '/') === 0) {
        $isStaticAsset = true;
        break;
    }
}

$contentTypes = array(
    'css' => 'text/css; charset=utf-8',
    'gif' => 'image/gif',
    'ico' => 'image/x-icon',
    'jpeg' => 'image/jpeg',
    'jpg' => 'image/jpeg',
    'js' => 'text/javascript; charset=utf-8',
    'png' => 'image/png',
    'svg' => 'image/svg+xml',
    'webp' => 'image/webp',
);
$extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));

if (!$isStaticAsset || !isset($contentTypes[$extension])) {
    http_response_code(404);
    exit('Not found.');
}

header('Content-Type: ' . $contentTypes[$extension]);
header('Cache-Control: public, max-age=86400');
readfile($target);

<?php

$minPhpVersion = '8.2';
if (version_compare(PHP_VERSION, $minPhpVersion, '<')) {
    header('HTTP/1.1 503 Service Unavailable.', true, 503);
    echo 'PHP ' . $minPhpVersion . ' or higher is required.';
    exit(1);
}

define('FCPATH', __DIR__ . DIRECTORY_SEPARATOR);
chdir(FCPATH);

// Load Composer autoloader first
$composerAutoload = FCPATH . '../vendor/autoload.php';
if (!is_file($composerAutoload)) {
    die('Composer autoload not found. Run "composer install".');
}
require $composerAutoload;

require FCPATH . '../app/Config/Paths.php';

$paths = new Config\Paths();

require $paths->systemDirectory . '/Boot.php';

exit(\CodeIgniter\Boot::bootWeb($paths));

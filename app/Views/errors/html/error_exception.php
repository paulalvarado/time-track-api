<?php
$errorId = uniqid('error', true);
?>
<!doctype html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= esc($title) ?> - <?= esc($exception->getCode()) ?></title>
</head>
<body>
    <h1><?= esc($title) ?></h1>
    <h2><?= esc($exception::class) ?></h2>
    <p><?= nl2br(esc($exception->getMessage())) ?></p>
    <p><b><?= esc($file) ?></b> at line <b><?= esc($line) ?></b></p>
    <pre><?= esc($exception->getTraceAsString()) ?></pre>
    <hr>
    <p>Error ID: <?= esc($errorId) ?></p>
</body>
</html>

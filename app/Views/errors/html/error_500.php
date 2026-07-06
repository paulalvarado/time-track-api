<!DOCTYPE html>
<html lang="en">
<head><meta charset="utf-8"><title>500 Internal Server Error</title></head>
<body>
<h1>500 Internal Server Error</h1>
<?php if (isset($exception) && $exception): ?>
<pre><?= esc($exception->getMessage()) ?></pre>
<pre><?= esc($exception->getFile()) ?>:<?= esc($exception->getLine()) ?></pre>
<pre><?= esc($exception->getTraceAsString()) ?></pre>
<?php endif; ?>
</body>
</html>

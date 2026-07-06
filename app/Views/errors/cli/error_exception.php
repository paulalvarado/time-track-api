<?php
echo '[' . $exception::class . ']' . "\n";
echo $message . "\n";
echo 'at ' . $file . ':' . $line . "\n\n";
if ($previous = $exception->getPrevious()) {
    echo '  Caused by: [' . $previous::class . '] ' . $previous->getMessage() . "\n";
}

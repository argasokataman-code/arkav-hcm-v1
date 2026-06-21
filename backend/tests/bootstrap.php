<?php

$cacheDir = __DIR__.'/../bootstrap/cache';

foreach (['config.php'] as $cacheFile) {
    $cachePath = $cacheDir.'/'.$cacheFile;

    if (is_file($cachePath)) {
        @unlink($cachePath);
    }
}

require __DIR__.'/../vendor/autoload.php';

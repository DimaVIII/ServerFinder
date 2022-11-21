<?php
require __DIR__ . '/vendor/autoload.php';

use App\ServerFinder;

$app = new ServerFinder();
$app->run();
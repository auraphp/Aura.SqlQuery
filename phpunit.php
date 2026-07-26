<?php
error_reporting(E_ALL);
require __DIR__ . '/autoload.php';

$load_env = require __DIR__ . '/tests/load-env.php';
$load_env(__DIR__ . '/.env');

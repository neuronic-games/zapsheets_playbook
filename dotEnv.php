<?php

// https://matthewsetter.com/blog/item/set-environment-variables-php-dotenv

require __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/');
$dotenv->load();
$dotenv->required([
  'ENVIRONMENT',
  'PYTHON'
]);

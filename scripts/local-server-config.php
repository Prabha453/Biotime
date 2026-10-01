<?php

// Read settings with CodeIgniter's own .env parser (including quoted values).
require dirname(__DIR__) . '/vendor/autoload.php';
(new CodeIgniter\Config\DotEnv(dirname(__DIR__)))->load();
$url = getenv('app.baseURL') ?: getenv('app_baseURL') ?: 'http://localhost:8080/';
echo json_encode(['url' => $url], JSON_THROW_ON_ERROR);

<?php

// Database configuration
$config['database'] = [
    'host' => getenv('MYSQL_HOST') ?: getenv('DB_HOST') ?: 'localhost',
    'database' => getenv('MYSQL_DATABASE') ?: getenv('DB_NAME') ?: 'numok',
    'username' => getenv('MYSQL_USER') ?: getenv('DB_USER') ?: 'DBUSER',
    'password' => getenv('MYSQL_PASSWORD') ?: getenv('DB_PASS') ?: 'DBPASS',
];

// Initialize database connection
\Numok\Database\Database::setConfig($config['database']);

// Application configuration
$config['app'] = [
    'name' => 'Numok',
    'url' => getenv('APP_URL') ?: 'http://localhost',
    'debug' => getenv('APP_DEBUG') ?: true
];

// API access configuration
$config['api'] = [
    // Comma separated list of tokens or hashed tokens generated with password_hash
    'tokens' => array_filter(array_map('trim', explode(',', getenv('API_TOKENS') ?: '')))
];

// Time zone
date_default_timezone_set('UTC');

return $config;
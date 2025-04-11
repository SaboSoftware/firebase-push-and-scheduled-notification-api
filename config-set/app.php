<?php

return [
    'firebase' => [
        'credentials' => __DIR__ . '/firebase-credentials.json'
    ],
    'database' => [
        'dsn' => 'mysql:host=localhost; dbname=your_database_name;charset=utf8mb4',
        'username' => 'your_database_user',
        'password' => 'your_database_password',
        'options' => [
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_STRINGIFY_FETCHES => false
        ]
    ],
    'displayErrors' => true,
    'debug' => true
]; 
<?php

return [
    // Разрешённые Origin для CORS (через запятую в переменной CORS_ORIGINS)
    'corsOrigins' => array_filter(array_map(
        'trim',
        explode(',', getenv('CORS_ORIGINS') ?: 'http://localhost:5173')
    )),
];

<?php
require __DIR__ . '/config.php';
try {
    db()->query('SELECT 1');
    echo 'ok';
} catch (Throwable $ex) {
    http_response_code(500);
    echo 'db error';
}

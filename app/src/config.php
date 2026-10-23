<?php
declare(strict_types=1);

$secure = (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
session_set_cookie_params([
    'lifetime' => 0, 'path' => '/', 'secure' => $secure,
    'httponly' => true, 'samesite' => 'Lax',
]);
session_start();

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4',
            getenv('DB_HOST') ?: 'mysql', getenv('DB_NAME') ?: 'forum');
        $pdo = new PDO($dsn, (string)getenv('DB_USER'), (string)getenv('DB_PASS'), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_check(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', (string)($_POST['csrf'] ?? ''))) {
        http_response_code(403);
        exit('Token CSRF không hợp lệ.');
    }
}

function current_user(): ?array {
    return isset($_SESSION['uid']) ? ['id' => $_SESSION['uid'], 'username' => $_SESSION['uname']] : null;
}

function require_login(): void {
    if (!current_user()) {
        header('Location: /login.php');
        exit;
    }
}

function client_ip(): string {
    return preg_replace('/[^0-9a-fA-F:.]/', '', $_SERVER['HTTP_X_REAL_IP'] ?? $_SERVER['REMOTE_ADDR'] ?? '-');
}

function header_html(string $title): void {
    $u = current_user();
    echo '<!doctype html><html lang="vi"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' - Diễn đàn DevOps</title>';
    echo '<link rel="stylesheet" href="/style.css"></head><body>';
    echo '<header><a class="brand" href="/">Diễn đàn DevOps</a><nav>';
    if ($u) {
        echo '<span>Xin chào, <b>' . e($u['username']) . '</b></span>';
        echo '<form method="post" action="/logout.php" class="inline">';
        echo '<input type="hidden" name="csrf" value="' . e(csrf_token()) . '">';
        echo '<button type="submit">Đăng xuất</button></form>';
    } else {
        echo '<a href="/login.php">Đăng nhập</a><a href="/register.php">Đăng ký</a>';
    }
    echo '</nav></header><main>';
}

function footer_html(): void {
    echo '</main><footer>Đề 15 - Forum | Docker Compose</footer></body></html>';
}

function app_log(string $msg): void {
    file_put_contents('php://stderr', $msg . "\n");
}

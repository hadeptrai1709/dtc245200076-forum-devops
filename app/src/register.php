<?php
require __DIR__ . '/config.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $u)) {
        $error = 'Tên đăng nhập 3-30 ký tự: chữ, số, dấu gạch dưới.';
    } elseif (strlen($p) < 8) {
        $error = 'Mật khẩu phải có ít nhất 8 ký tự.';
    } else {
        try {
            db()->prepare('INSERT INTO users (username, password_hash) VALUES (?, ?)')
                ->execute([$u, password_hash($p, PASSWORD_DEFAULT)]);
            header('Location: /login.php?registered=1');
            exit;
        } catch (PDOException $ex) {
            $error = ($ex->getCode() === '23000') ? 'Tên đăng nhập đã tồn tại.' : 'Lỗi hệ thống.';
            if ($error === 'Lỗi hệ thống.') { error_log('register_error: ' . $ex->getMessage()); }
        }
    }
}
header_html('Đăng ký');
?>
<div class="card">
  <h2>Đăng ký</h2>
  <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Tên đăng nhập</label><input type="text" name="username" required>
    <label>Mật khẩu (≥ 8 ký tự)</label><input type="password" name="password" required>
    <button type="submit">Tạo tài khoản</button>
  </form>
</div>
<?php footer_html();

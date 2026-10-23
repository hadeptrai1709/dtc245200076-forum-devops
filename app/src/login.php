<?php
require __DIR__ . '/config.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $u = trim($_POST['username'] ?? '');
    $p = (string)($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT id, username, password_hash FROM users WHERE username = ?');
    $stmt->execute([$u]);
    $row = $stmt->fetch();
    if ($row && password_verify($p, $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['uid'] = (int)$row['id'];
        $_SESSION['uname'] = $row['username'];
        app_log('login_success user=' . $row['username'] . ' ip=' . client_ip());
        header('Location: /');
        exit;
    }
    $safe = substr(preg_replace('/[^\w.-]/', '_', $u), 0, 50);
    app_log('login_failed user=' . $safe . ' ip=' . client_ip());
    $error = 'Sai tên đăng nhập hoặc mật khẩu.';
}
header_html('Đăng nhập');
?>
<div class="card">
  <h2>Đăng nhập</h2>
  <?php if (isset($_GET['registered'])): ?><p>Đăng ký thành công, hãy đăng nhập.</p><?php endif; ?>
  <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Tên đăng nhập</label><input type="text" name="username" required>
    <label>Mật khẩu</label><input type="password" name="password" required>
    <button type="submit">Đăng nhập</button>
  </form>
</div>
<?php footer_html();

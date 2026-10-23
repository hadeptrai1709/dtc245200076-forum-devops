<?php
require __DIR__ . '/config.php';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    csrf_check();
    $title = trim($_POST['title'] ?? '');
    $body  = trim($_POST['body'] ?? '');
    if ($title === '' || mb_strlen($title) > 200 || $body === '') {
        $error = 'Tiêu đề (tối đa 200 ký tự) và nội dung không được để trống.';
    } else {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare('INSERT INTO topics (title, user_id) VALUES (?, ?)')->execute([$title, $_SESSION['uid']]);
        $tid = (int)$pdo->lastInsertId();
        $pdo->prepare('INSERT INTO posts (topic_id, user_id, body) VALUES (?, ?, ?)')->execute([$tid, $_SESSION['uid'], $body]);
        $pdo->commit();
        header('Location: /topic.php?id=' . $tid);
        exit;
    }
}
$topics = db()->query(
    'SELECT t.id, t.title, t.created_at, u.username,
            (SELECT COUNT(*) FROM posts p WHERE p.topic_id = t.id) AS post_count
     FROM topics t JOIN users u ON u.id = t.user_id
     ORDER BY t.id DESC LIMIT 50'
)->fetchAll();
header_html('Trang chủ');
?>
<h2>Chủ đề</h2>
<?php foreach ($topics as $t): ?>
  <div class="card">
    <a href="/topic.php?id=<?= (int)$t['id'] ?>"><b><?= e($t['title']) ?></b></a>
    <div class="meta">bởi <?= e($t['username']) ?> · <?= e($t['created_at']) ?> · <?= (int)$t['post_count'] ?> bài viết</div>
  </div>
<?php endforeach; ?>
<?php if (!$topics): ?><p>Chưa có chủ đề nào.</p><?php endif; ?>

<?php if (current_user()): ?>
<div class="card">
  <h3>Tạo chủ đề mới</h3>
  <?php if ($error): ?><div class="error"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="text" name="title" placeholder="Tiêu đề" maxlength="200" required>
    <textarea name="body" rows="4" placeholder="Nội dung bài viết đầu tiên" required></textarea>
    <button type="submit">Đăng chủ đề</button>
  </form>
</div>
<?php else: ?>
<p><a href="/login.php">Đăng nhập</a> để tạo chủ đề và bình luận.</p>
<?php endif; ?>
<?php footer_html();

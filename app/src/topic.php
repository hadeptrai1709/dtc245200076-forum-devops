<?php
require __DIR__ . '/config.php';
$id = (int)($_GET['id'] ?? 0);
$pdo = db();

$stmt = $pdo->prepare('SELECT t.id, t.title, u.username FROM topics t JOIN users u ON u.id = t.user_id WHERE t.id = ?');
$stmt->execute([$id]);
$topic = $stmt->fetch();
if (!$topic) {
    http_response_code(404);
    header_html('Không tìm thấy');
    echo '<div class="card">Không tìm thấy chủ đề.</div>';
    footer_html();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_login();
    csrf_check();
    $body = trim($_POST['body'] ?? '');
    $action = $_POST['action'] ?? '';
    if ($body !== '') {
        if ($action === 'post') {
            $pdo->prepare('INSERT INTO posts (topic_id, user_id, body) VALUES (?, ?, ?)')
                ->execute([$id, $_SESSION['uid'], $body]);
        } elseif ($action === 'comment') {
            $pid = (int)($_POST['post_id'] ?? 0);
            $chk = $pdo->prepare('SELECT id FROM posts WHERE id = ? AND topic_id = ?');
            $chk->execute([$pid, $id]);
            if ($chk->fetch()) {
                $pdo->prepare('INSERT INTO comments (post_id, user_id, body) VALUES (?, ?, ?)')
                    ->execute([$pid, $_SESSION['uid'], $body]);
            }
        }
    }
    header('Location: /topic.php?id=' . $id);
    exit;
}

$ps = $pdo->prepare('SELECT p.id, p.body, p.created_at, u.username FROM posts p JOIN users u ON u.id = p.user_id WHERE p.topic_id = ? ORDER BY p.id');
$ps->execute([$id]);
$posts = $ps->fetchAll();

$cs = $pdo->prepare('SELECT c.post_id, c.body, c.created_at, u.username FROM comments c JOIN users u ON u.id = c.user_id WHERE c.post_id IN (SELECT id FROM posts WHERE topic_id = ?) ORDER BY c.id');
$cs->execute([$id]);
$comments = [];
foreach ($cs->fetchAll() as $c) { $comments[$c['post_id']][] = $c; }

header_html($topic['title']);
?>
<h2><?= e($topic['title']) ?></h2>
<div class="meta">Chủ đề của <?= e($topic['username']) ?> · <a href="/">← Quay lại</a></div>

<?php foreach ($posts as $p): ?>
  <div class="card">
    <div><?= nl2br(e($p['body'])) ?></div>
    <div class="meta">— <?= e($p['username']) ?> · <?= e($p['created_at']) ?></div>
    <?php foreach ($comments[$p['id']] ?? [] as $c): ?>
      <div class="comment"><?= nl2br(e($c['body'])) ?>
        <div class="meta">— <?= e($c['username']) ?> · <?= e($c['created_at']) ?></div></div>
    <?php endforeach; ?>
    <?php if (current_user()): ?>
      <form method="post">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
        <input type="hidden" name="action" value="comment">
        <input type="hidden" name="post_id" value="<?= (int)$p['id'] ?>">
        <input type="text" name="body" placeholder="Viết bình luận..." required>
        <button type="submit">Bình luận</button>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach; ?>

<?php if (current_user()): ?>
<div class="card">
  <h3>Trả lời chủ đề</h3>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <input type="hidden" name="action" value="post">
    <textarea name="body" rows="4" required></textarea>
    <button type="submit">Gửi bài viết</button>
  </form>
</div>
<?php endif; ?>
<?php footer_html();

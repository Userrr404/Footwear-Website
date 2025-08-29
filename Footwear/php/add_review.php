<?php
// php/add_review.php
session_start();
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
header('Content-Type: application/json');

$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
  echo json_encode(['success'=>false,'error'=>'not_logged_in']);
  exit;
}

$product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
$rating = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

if (!$product_id || $rating < 1 || $rating > 5 || strlen($comment) < 5) {
  echo json_encode(['success'=>false,'error'=>'invalid_input']);
  exit;
}

// insert review
$ins = $connection->prepare("INSERT INTO reviews (product_id, user_id, rating, comment, reviewed_at) VALUES (?, ?, ?, ?, NOW())");
$ins->bind_param("iiis", $product_id, $user_id, $rating, $comment);
if (!$ins->execute()) {
  echo json_encode(['success'=>false,'error'=>'db_error']);
  exit;
}

// fetch newly inserted review with username
$rid = $connection->insert_id;
$fetch = $connection->prepare("SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id=u.user_id WHERE r.review_id = ?");
$fetch->bind_param("i",$rid);
$fetch->execute();
$newReview = $fetch->get_result()->fetch_assoc();

// fetch updated stats
$stats_stmt = $connection->prepare("
  SELECT 
    COUNT(*) AS total,
    IFNULL(AVG(rating),0) AS avg_rating,
    SUM(rating = 5) AS r5,
    SUM(rating = 4) AS r4,
    SUM(rating = 3) AS r3,
    SUM(rating = 2) AS r2,
    SUM(rating = 1) AS r1
  FROM reviews
  WHERE product_id = ?");
$stats_stmt->bind_param("i",$product_id);
$stats_stmt->execute();
$stats = $stats_stmt->get_result()->fetch_assoc();

// render review HTML (server-side) so JS can prepend it
ob_start();
?>
<article class="review-card" data-review-id="<?= (int)$newReview['review_id'] ?>">
  <header class="rc-head">
    <div class="avatar"><?= strtoupper(substr($newReview['username'],0,1)) ?></div>
    <div class="meta">
      <div class="name"><?= htmlspecialchars($newReview['username']) ?></div>
      <div class="rating">
        <?php for ($i=1;$i<=5;$i++): ?>
          <span class="star <?= $i <= (int)$newReview['rating'] ? 'filled' : '' ?>">★</span>
        <?php endfor; ?>
        <time class="time"><?= htmlspecialchars(date('M j, Y', strtotime($newReview['reviewed_at']))) ?></time>
      </div>
    </div>
  </header>
  <div class="rc-body"><?= nl2br(htmlspecialchars($newReview['comment'])) ?></div>
</article>
<?php
$html = ob_get_clean();

echo json_encode(['success'=>true,'reviewHtml'=>$html,'stats'=>$stats]);
exit;
?>
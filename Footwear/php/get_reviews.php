<?php
// php/get_reviews.php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';
header('Content-Type: application/json');

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;
$offset = isset($_GET['offset']) ? intval($_GET['offset']) : 0;
$limit = isset($_GET['limit']) ? intval($_GET['limit']) : 6;
$sort = $_GET['sort'] ?? 'recent';

$order = "r.reviewed_at DESC";
if ($sort === 'top') $order = "r.rating DESC, r.reviewed_at DESC";
if ($sort === 'low') $order = "r.rating ASC, r.reviewed_at DESC";

$stmt = $connection->prepare("SELECT r.*, u.username FROM reviews r JOIN users u ON r.user_id=u.user_id WHERE r.product_id = ? ORDER BY $order LIMIT ?, ?");
$stmt->bind_param("iii", $product_id, $offset, $limit);
$stmt->execute();
$res = $stmt->get_result();

$html = '';
while ($review = $res->fetch_assoc()){
  ob_start();
  ?>
  <article class="review-card" data-review-id="<?= (int)$review['review_id'] ?>">
    <header class="rc-head">
      <div class="avatar"><?= strtoupper(substr($review['username'],0,1)) ?></div>
      <div class="meta">
        <div class="name"><?= htmlspecialchars($review['username']) ?></div>
        <div class="rating">
          <?php for ($i=1;$i<=5;$i++): ?>
            <span class="star <?= $i <= (int)$review['rating'] ? 'filled' : '' ?>">★</span>
          <?php endfor; ?>
          <time class="time"><?= htmlspecialchars(date('M j, Y', strtotime($review['reviewed_at']))) ?></time>
        </div>
      </div>
    </header>
    <div class="rc-body"><?= nl2br(htmlspecialchars($review['comment'])) ?></div>
  </article>
  <?php
  $html .= ob_get_clean();
}

echo json_encode(['success'=>true, 'html'=>$html]);
exit;
?>
<?php
require_once '../config.php';
require_once INCLUDES_PATH . 'db_connection.php';

// ----------------- CONFIG -----------------
$PER_PAGE = 4; // Always show minimum 40 products per page
$page      = max(1, isset($_GET['page']) ? (int)$_GET['page'] : 1);
$offset    = ($page - 1) * $PER_PAGE;

// ----------------- COUNT TOTAL -----------------
$countSql = "SELECT COUNT(*) FROM products";
$countRes = $connection->query($countSql);
$total_rows = $countRes->fetch_row()[0] ?? 0;
$total_pages = max(1, ceil($total_rows / $PER_PAGE));

// ----------------- FETCH PRODUCTS -----------------
$sql = "SELECT p.product_id, p.product_name, p.selling_price, 
               COALESCE(pi.image_url, '') AS image_url
        FROM products p
        LEFT JOIN product_images pi 
          ON p.product_id = pi.product_id AND pi.is_default = 1
        ORDER BY p.created_at DESC, p.product_id DESC
        LIMIT ?, ?";
$stmt = $connection->prepare($sql);
$stmt->bind_param("ii", $offset, $PER_PAGE);
$stmt->execute();
$result = $stmt->get_result();

$products = [];
while ($row = $result->fetch_assoc()) {
    $products[] = $row;
}

function h($str) { return htmlspecialchars($str, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Products | Elite Footwear</title>
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body class="bg-gray-50 text-gray-800 min-h-screen flex flex-col">

<?php require_once INCLUDES_PATH . 'header.php'; ?>

<main class="flex-grow">
  <section class="max-w-7xl mx-auto px-4 md:px-6 lg:px-8 py-8">

    <!-- Section Heading -->
    <div class="mb-6 text-center">
      <h1 class="text-2xl md:text-3xl font-bold tracking-tight">Our Products</h1>
        <p class="text-gray-600 mt-2">Explore our wide range of footwear</p>
    </div>

    <!-- Product Grid -->
    <?php if (empty($products)): ?> 
      <div class="bg-white rounded-2xl p-10 border text-center">
        <p class="text-lg">No products available right now.</p>
      </div>
    <?php else: ?>
      <div class="grid gap-4 md:gap-6 justify-center"
     style="grid-template-columns: repeat(auto-fill, minmax(230px, 1fr));">
        <?php foreach ($products as $p): ?>
          <?php
            $img = $p['image_url'] ? (UPLOADS_URL . $p['image_url']) : (BASE_URL . 'assets/img/placeholder.webp');
          ?>
          <a href="<?= BASE_URL ?>views/product_details.php?id=<?= (int)$p['product_id'] ?>"
             class="bg-white rounded-2xl border overflow-hidden hover:shadow-lg transition group block">
            <div class="aspect-[4/5] bg-gray-100 overflow-hidden">
              <img src="<?= h($img) ?>" alt="<?= h($p['product_name']) ?>"
                   class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-200"
                   loading="lazy" />
            </div>
            <div class="p-3 md:p-4">
              <h3 class="font-semibold text-sm md:text-base line-clamp-2 min-h-[2.5rem]">
                <?= h($p['product_name']) ?>
              </h3>

              <div class="mt-2 flex items-center gap-2">
                <span class="text-base md:text-lg font-bold">₹<?= number_format((float)$p['selling_price'], 2) ?></span>
              </div>

              <div class="mt-3">
                <span class="inline-flex w-full items-center justify-center rounded-xl border px-3 py-2 text-sm font-medium group-hover:bg-gray-50">
                  View Details
                </span>
              </div>
            </div>
          </a>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <div class="flex items-center justify-between mt-10">
        <div class="text-sm text-gray-500">
          Page <?= $page ?> of <?= $total_pages ?>
        </div>
        <div class="flex gap-2">
          <?php
          function pageUrl($pg) {
            $qs = $_GET; $qs['page'] = $pg;
            return '?' . http_build_query($qs);
          }
          ?>
          <a href="<?= $page > 1 ? pageUrl($page-1) : '#' ?>"
             class="px-4 py-2 rounded-xl border <?= $page>1 ? 'hover:bg-gray-50' : 'opacity-50 cursor-not-allowed' ?>">Prev</a>
          <a href="<?= $page < $total_pages ? pageUrl($page+1) : '#' ?>"
             class="px-4 py-2 rounded-xl border <?= $page<$total_pages ? 'hover:bg-gray-50' : 'opacity-50 cursor-not-allowed' ?>">Next</a>
        </div>
      </div>
    <?php endif; ?>
  </section>
</main>

<?php require_once INCLUDES_PATH . 'footer.php'; ?>
</body>
</html>

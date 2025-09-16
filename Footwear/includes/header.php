<?php
if (!defined('BASE_URL')) {
    require_once '../config.php';
}
require_once INCLUDES_PATH . 'db_connection.php';
session_start();

// Wishlist Count
$wishlist_count = 0;
if(isset($_SESSION['user_id'])) {
  $stmt = $connection->prepare("SELECT COUNT(*) FROM wishlist WHERE user_id = ?");
  $stmt->bind_param("i", $_SESSION['user_id']);
  $stmt->execute();
  $stmt->bind_result($wishlist_count);
  $stmt->fetch();
  $stmt->close();
}

// Cart Count
$cart_count = 0;
if(isset($_SESSION['user_id'])) {
  $stmt = $connection->prepare("SELECT COUNT(*) FROM cart WHERE user_id = ?");
  $stmt->bind_param("i", $_SESSION['user_id']);
  $stmt->execute();
  $stmt->bind_result($cart_count);
  $stmt->fetch();
  $stmt->close();
}
?>

<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/7.0.0/css/all.min.css" crossorigin="anonymous" referrerpolicy="no-referrer" />

<!-- Header -->
<header class="h-[66px] bg-gray-200 border-b border-gray-200 shadow-sm sticky top-0 z-50">
  <div class="mx-auto flex items-center justify-between px-4 sm:px-6 lg:px-12 py-3">

    <!-- Logo -->
    <div class="flex-shrink-0">
      <a href="<?= BASE_URL ?>views/index.php" class="text-2xl font-extrabold tracking-tight">
        <span class="text-black">STEP</span><span class="text-red-600">UP</span>
      </a>
    </div>

    <!-- Desktop Navigation -->
    <nav class="hidden lg:flex items-center space-x-8 font-medium">
      <a href="<?= BASE_URL ?>views/index.php" class="hover:text-red-600 transition">Home</a>
      <a href="<?= BASE_URL ?>views/categories.php" class="hover:text-red-600 transition">Categories</a>
      <a href="<?= BASE_URL ?>views/new_arrivals.php" class="hover:text-red-600 transition">New Arrivals</a>
      <a href="<?= BASE_URL ?>views/sale.php" class="hover:text-red-600 transition">Sale</a>
      <a href="<?= BASE_URL ?>views/men.php" class="hover:text-red-600 transition">Men</a>
      <a href="<?= BASE_URL ?>views/women.php" class="hover:text-red-600 transition">Women</a>
    </nav>

    <!-- Search (desktop only) -->
    <div class="hidden md:flex flex-grow max-w-md mx-6">
      <form action="<?= BASE_URL ?>views/search.php" method="get" class="w-full">
        <div class="flex">
          <input 
            type="text" name="query" placeholder="Search..."
            class="w-full px-3 py-2 border border-gray-300 rounded-l-md focus:outline-none focus:border-red-600">
          <button class="px-3 border border-gray-300 border-l-0 rounded-r-md bg-gray-50 hover:bg-red-50">
            <i class="fa-solid fa-magnifying-glass text-gray-600 hover:text-red-600"></i>
          </button>
        </div>
      </form>
    </div>

    <!-- Icons -->
    <div class="flex items-center space-x-5">
      <!-- Wishlist -->
      <a href="<?= BASE_URL ?>views/wishlist.php" class="relative hidden sm:block">
        <i class="fa-regular fa-heart text-gray-700 hover:text-red-600 text-lg"></i>
        <?php if ($wishlist_count > 0): ?>
          <span class="absolute -top-2 -right-3 bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
            <?= $wishlist_count ?>
          </span>
        <?php endif; ?>
      </a>

      <!-- Cart -->
      <a href="<?= BASE_URL ?>views/cart.php" class="relative hidden sm:block">
        <i class="fa-solid fa-cart-shopping text-gray-700 hover:text-red-600 text-lg"></i>
        <?php if ($cart_count > 0): ?>
          <span class="absolute -top-2 -right-3 bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
            <?= $cart_count ?>
          </span>
        <?php endif; ?>
      </a>

      <!-- User -->
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="<?= BASE_URL ?>views/dashboard.php" class="hidden sm:block font-medium hover:text-red-600">
          <?= htmlspecialchars($_SESSION['username']) ?>
        </a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>views/login.php" class="hidden sm:block">
          <i class="fa-solid fa-user text-gray-700 hover:text-red-600 text-lg"></i>
        </a>
      <?php endif; ?>

      <!-- Mobile Menu Button -->
      <button id="toggleSidebar" class="lg:hidden">
        <i id="toggleIcon" class="fa-solid fa-bars text-gray-700 hover:text-red-600 text-xl"></i>
      </button>
    </div>
  </div>
</header>

<!-- Mobile Sidebar -->
<div id="mobileMenu" class="fixed top-0 right-0 h-full w-64 bg-white shadow-lg transform translate-x-full transition-transform duration-300 lg:hidden z-50">
  <div class="p-4 flex justify-between items-center border-b">
    <span class="text-xl font-bold">Menu</span>
    <button id="closeSidebar"><i class="fa-solid fa-xmark text-xl hover:text-red-600 transition"></i></button>
  </div>
  <nav class="flex flex-col p-4 space-y-4 text-gray-700 font-medium">
    <a href="<?= BASE_URL ?>views/index.php" class="hover:text-red-600">Home</a>
    <a href="<?= BASE_URL ?>views/categories.php" class="hover:text-red-600">Categories</a>
    <a href="<?= BASE_URL ?>views/new_arrivals.php" class="hover:text-red-600">New Arrivals</a>
    <a href="<?= BASE_URL ?>views/sale.php" class="hover:text-red-600">Sale</a>
    <a href="<?= BASE_URL ?>views/men.php" class="hover:text-red-600">Men</a>
    <a href="<?= BASE_URL ?>views/women.php" class="hover:text-red-600">Women</a>
    <div class="flex items-center space-x-6 pt-4 border-t">
      <a href="<?= BASE_URL ?>views/wishlist.php" class="relative">
        <i class="fa-regular fa-heart text-lg hover:text-red-600"></i>
        <?php if ($wishlist_count > 0): ?>
          <span class="absolute -top-2 -right-3 bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
            <?= $wishlist_count ?>
          </span>
        <?php endif; ?>
      </a>
      <a href="<?= BASE_URL ?>views/cart.php" class="relative">
        <i class="fa-solid fa-cart-shopping text-lg hover:text-red-600"></i>
        <?php if ($cart_count > 0): ?>
          <span class="absolute -top-2 -right-3 bg-red-600 text-white text-xs font-bold px-2 py-0.5 rounded-full">
            <?= $cart_count ?>
          </span>
        <?php endif; ?>
      </a>
      <?php if (isset($_SESSION['user_id'])): ?>
        <a href="<?= BASE_URL ?>views/dashboard.php" class="font-medium hover:text-red-600">
          <?= htmlspecialchars($_SESSION['username']) ?>
        </a>
      <?php else: ?>
        <a href="<?= BASE_URL ?>views/login.php"><i class="fa-solid fa-user text-lg hover:text-red-600"></i></a>
      <?php endif; ?>
    </div>
  </nav>
</div>

<!-- Scripts -->
<script>
  // Mobile menu toggle
  const sidebar = document.getElementById("mobileMenu");
  const toggleIcon = document.getElementById("toggleIcon");
  const toggleButton = document.getElementById("toggleSidebar");
  const closeButton = document.getElementById("closeSidebar");
  
  toggleButton.onclick = () => {
    toggleIcon.classList.replace("fa-bars", "fa-xmark");
    sidebar.classList.remove("translate-x-full");
  }
  
  closeButton.onclick = () => {
    toggleIcon.classList.replace("fa-xmark", "fa-bars");
    sidebar.classList.add("translate-x-full");
  }
</script>

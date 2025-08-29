<?php
// ========== Site Configuration ==========
define('SITE_NAME', 'Footwear Store');
define('CURRENCY', '₹');

// ========== URL Paths ==========
define('BASE_URL', 'http://localhost/Footwear-Website/Footwear/');
define('UPLOADS_URL', BASE_URL . 'uploads/products/');
define('UPLOADS_PROFILE_URL', BASE_URL . 'uploads_profile/');

// ========== File Paths ==========
define('ROOT_PATH', dirname(__FILE__) . '/');
define('UPLOADS_PATH', ROOT_PATH . 'uploads/');
define('UPLOADS_PROFILE_PATH', ROOT_PATH . 'uploads_profile/');
define('INCLUDES_PATH', ROOT_PATH . 'includes/');

// ========== Database Configuration ==========
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'footwear_db');

// ========== Error Reporting ==========
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
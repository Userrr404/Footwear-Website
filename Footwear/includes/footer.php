<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>assets/css/footer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
</head>
<body>
    <!-- Footer Start -->
<footer class="footer">

  <div class="footer-container">
    
    <!-- Company Info -->
    <div>
      <h3>Elite Footwear</h3>
      <p>We blend premium materials, comfort, and bold design — redefining how India steps forward.</p>
      <p>&copy; <?= date("Y") ?> Elite Footwear. All rights reserved.</p>
    </div>

    <!-- Quick Links -->
    <div>
      <h4>Quick Links</h4>
      <a href="<?= BASE_URL ?>views/index.php">Home</a>
      <a href="<?= BASE_URL ?>views/products.php">Shop</a>
      <a href="<?= BASE_URL ?>views/about.php">About Us</a>
      <a href="<?= BASE_URL ?>views/contact.php">Contact</a>
      <a href="<?= BASE_URL ?>views/policy.php">Privacy Policy</a>
    </div>

    <!-- Contact Info -->
    <div>
      <h4>Contact Us</h4>
      <a href="mailto:support@elitefootwear.com"><i class="fas fa-envelope"></i> support@elitefootwear.com</a>
      <a href="tel:+919999999999"><i class="fas fa-phone-alt"></i> +91 99999 99999</a>
      <p><i class="fas fa-map-marker-alt"></i> Mumbai, Maharashtra, India</p>
    </div>

    <!-- Social Media -->
    <div>
      <h4>Follow Us</h4>
      <div class="footer-social">
        <a href="#"><i class="fab fa-facebook-f"></i></a>
        <a href="#"><i class="fab fa-instagram"></i></a>
        <a href="#"><i class="fab fa-twitter"></i></a>
        <a href="#"><i class="fab fa-youtube"></i></a>
      </div>
    </div>

  </div>

  <div class="footer-bottom">
    Powered by Passion. Crafted for Performance.
  </div>
</footer>
<!-- Footer End -->


</body>
</html>
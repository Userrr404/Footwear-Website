
<script src="https://cdn.tailwindcss.com"></script>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">


<!-- Footer Start -->
<footer class="bg-gray-100 border-t border-gray-200 text-gray-700">
  <div class="max-w-7xl mx-auto px-6 py-10 grid gap-10 sm:grid-cols-2 lg:grid-cols-4 text-center">

    <!-- Brand / About -->
    <div>
      <h3 class="text-xl font-bold text-gray-900">Elite Footwear</h3>
      <p class="mt-3 text-sm text-gray-600 max-w-xs mx-auto">
        Blending premium materials, comfort, and bold design — redefining how India steps forward.
      </p>
      <p class="mt-4 text-xs text-gray-500">&copy; <?= date("Y") ?> Elite Footwear. All rights reserved.</p>
    </div>

    <!-- Quick Links -->
    <div>
      <h4 class="text-lg font-semibold text-gray-900 mb-3">Quick Links</h4>
      <ul class="space-y-2 text-sm">
        <li><a href="<?= BASE_URL ?>views/index.php" class="hover:text-orange-500 transition">Home</a></li>
        <li><a href="<?= BASE_URL ?>views/products.php" class="hover:text-orange-500 transition">Shop</a></li>
        <li><a href="<?= BASE_URL ?>views/about.php" class="hover:text-orange-500 transition">About Us</a></li>
        <li><a href="<?= BASE_URL ?>views/contact.php" class="hover:text-orange-500 transition">Contact</a></li>
        <li><a href="<?= BASE_URL ?>views/policy.php" class="hover:text-orange-500 transition">Privacy Policy</a></li>
      </ul>
    </div>

    <!-- Contact Info -->
    <div>
      <h4 class="text-lg font-semibold text-gray-900 mb-3">Contact Us</h4>
      <ul class="space-y-2 text-sm">
        <li><a href="mailto:support@elitefootwear.com" class="hover:text-orange-500 transition">
          <i class="fas fa-envelope mr-2"></i>support@elitefootwear.com
        </a></li>
        <li><a href="tel:+919999999999" class="hover:text-orange-500 transition">
          <i class="fas fa-phone-alt mr-2"></i>+91 99999 99999
        </a></li>
        <li class="flex justify-center">
          <i class="fas fa-map-marker-alt mr-2 mt-1"></i> Mumbai, Maharashtra, India
        </li>
      </ul>
    </div>

    <!-- Social Media -->
    <div>
      <h4 class="text-lg font-semibold text-gray-900 mb-3">Follow Us</h4>
      <div class="flex justify-center space-x-4">
        <a href="#" class="text-gray-600 hover:text-orange-500 text-xl transition"><i class="fab fa-facebook-f"></i></a>
        <a href="#" class="text-gray-600 hover:text-orange-500 text-xl transition"><i class="fab fa-instagram"></i></a>
        <a href="#" class="text-gray-600 hover:text-orange-500 text-xl transition"><i class="fab fa-twitter"></i></a>
        <a href="#" class="text-gray-600 hover:text-orange-500 text-xl transition"><i class="fab fa-youtube"></i></a>
      </div>
    </div>

  </div>

  <!-- Footer Bottom -->
  <div class="border-t border-gray-200 text-center py-4">
    <p class="text-sm text-gray-500">Powered by Passion. Crafted for Performance.</p>
  </div>
</footer>


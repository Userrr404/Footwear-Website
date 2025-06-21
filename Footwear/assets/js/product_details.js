function switchImage(thumbnail) {
    const mainImage = document.getElementById('mainImage');
    mainImage.src = thumbnail.src;

    // remove active class from others
    document.querySelectorAll('.thumbnails img').forEach(img => img.classList.remove('active'));
    thumbnail.classList.add('active');
  }
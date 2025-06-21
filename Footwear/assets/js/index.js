const heroImage = document.getElementById('heroImage');
    const heroImages = [
      '../assets/images/hero_image1.jpeg',
      '../assets/images/hero_image2.jpg',
      '../assets/images/hero_image3.jpg'
    ];

    let currentIndex = 0;

    setInterval(() => {
      heroImage.style.opacity = 0;

      setTimeout(() => {
        currentIndex = (currentIndex + 1) % heroImages.length;
        heroImage.src = heroImages[currentIndex];
        heroImage.style.opacity = 1;
      }, 1000);
    }, 4000);
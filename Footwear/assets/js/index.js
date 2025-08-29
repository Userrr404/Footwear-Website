const heroImage = document.getElementById('heroImage');
const heroTitle = document.getElementById('heroTitle');
const heroDescription = document.getElementById('heroDescription');

if (typeof banners !== "undefined" && banners.length > 1) {
  let currentIndex = 0;

  setInterval(() => {
    heroImage.style.opacity = 0;
    heroTitle.style.opacity = 0;
    heroDescription.style.opacity = 0;

    setTimeout(() => {
      currentIndex = (currentIndex + 1) % banners.length;

      heroImage.src = banners[currentIndex].image;
      heroTitle.textContent = banners[currentIndex].title;
      heroDescription.textContent = banners[currentIndex].description;

      heroImage.style.opacity = 1;
      heroTitle.style.opacity = 1;
      heroDescription.style.opacity = 1;
    }, 500);
  }, 2000); // 2 sec interval
}

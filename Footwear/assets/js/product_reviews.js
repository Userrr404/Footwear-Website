// assets/js/product_reviews.js
document.addEventListener('DOMContentLoaded', () => {
  const cfg = window.REVIEW_CONFIG || {};
  if (!cfg.productId) return;

  const reviewsList = document.getElementById('reviewsList');
  const loadBtn = document.getElementById('loadMoreReviews');
  const sortSelect = document.getElementById('reviewSort');
  const writeToggle = document.getElementById('writeReviewToggle');
  const writePanel = document.getElementById('writeReview');
  const addForm = document.getElementById('addReviewForm');

  // toggle write panel
  writeToggle?.addEventListener('click', () => {
    const visible = writePanel.getAttribute('aria-hidden') === 'false';
    writePanel.setAttribute('aria-hidden', visible ? 'true' : 'false');
    if (!visible) {
      document.getElementById('reviewComment')?.focus();
    }
  });

  // cancel
  document.getElementById('cancelReview')?.addEventListener('click', () => {
    writePanel.setAttribute('aria-hidden', 'true');
  });

  // Load more reviews
  loadBtn?.addEventListener('click', () => {
    const offset = parseInt(reviewsList.dataset.offset || 0, 10);
    const limit = cfg.perPage || 6;
    const sort = sortSelect?.value || 'recent';

    loadBtn.disabled = true;
    loadBtn.textContent = 'Loading...';

    fetch(`${cfg.baseUrl}php/get_reviews.php?product_id=${cfg.productId}&offset=${offset}&limit=${limit}&sort=${encodeURIComponent(sort)}`)
      .then(r => r.json())
      .then(data => {
        if (data.success && data.html) {
          const tmp = document.createElement('div');
          tmp.innerHTML = data.html;
          while (tmp.firstChild) {
            reviewsList.appendChild(tmp.firstChild);
          }
          reviewsList.dataset.offset = offset + limit;
          // simple heuristic: if less html was returned than limit, hide button
          if (!data.html || data.html.trim().length < 10) {
            loadBtn.style.display = 'none';
          } else {
            loadBtn.disabled = false;
            loadBtn.textContent = 'Load more reviews';
          }
        } else {
          loadBtn.style.display = 'none';
        }
      })
      .catch(err => {
        console.error(err);
        loadBtn.disabled = false;
        loadBtn.textContent = 'Load more reviews';
      });
  });

  // Sorting: clear list and fetch fresh
  sortSelect?.addEventListener('change', () => {
    const sort = sortSelect.value;
    // reset offset & fetch first page
    reviewsList.dataset.offset = cfg.perPage || 6;
    fetch(`${cfg.baseUrl}php/get_reviews.php?product_id=${cfg.productId}&offset=0&limit=${cfg.perPage}&sort=${encodeURIComponent(sort)}`)
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          reviewsList.innerHTML = data.html;
          // show load more again
          loadBtn.style.display = 'inline-block';
        }
      })
      .catch(console.error);
  });

  // Submit review (AJAX)
  if (addForm) {
    addForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const formData = new FormData(addForm);
      const msgBox = document.getElementById('reviewMessage');
      const btn = addForm.querySelector('button[type="submit"]');
      btn.disabled = true;
      btn.textContent = 'Submitting...';
      msgBox.textContent = '';

      fetch(addForm.action, {
        method: 'POST',
        body: formData,
        credentials: 'same-origin'
      })
      .then(r => r.json())
      .then(json => {
        if (json.success) {
          // prepend new review
          const tmp = document.createElement('div');
          tmp.innerHTML = json.reviewHtml;
          reviewsList.insertBefore(tmp.firstChild, reviewsList.firstChild);
          // update rating summary: easiest is to refresh page fragment via reload stats,
          // but backend returned stats; update averages & histogram if provided:
          if (json.stats) updateStats(json.stats);
          msgBox.textContent = 'Thanks — your review is live!';
          addForm.reset();
          writePanel.setAttribute('aria-hidden','true');
        } else {
          msgBox.textContent = 'Could not submit review. Please try again.';
        }
      })
      .catch(err => {
        console.error(err);
        document.getElementById('reviewMessage').textContent = 'Network error.';
      })
      .finally(() => {
        btn.disabled = false;
        btn.textContent = 'Submit review';
      });
    });
  }

  function updateStats(stats) {
    // update avg number
    const avgNum = document.querySelector('.avg-number');
    if (avgNum) avgNum.textContent = Math.round(stats.avg_rating*10)/10;
    const totalEl = document.querySelector('.total-reviews');
    if (totalEl) totalEl.textContent = stats.total + ' reviews';
    // update bars
    for (let r=1; r<=5; r++){
      const row = document.querySelector(`.breakdown .row:nth-child(${6-r}) .bar-fill`);
      if (row) {
        const pct = Math.round((stats['r'+r] / Math.max(1,stats.total)) * 100);
        row.style.width = pct + '%';
      }
      const countEl = document.querySelector(`.breakdown .row:nth-child(${6-r}) .row-count`);
      if (countEl) {
        countEl.textContent = stats['r'+r];
      }
    }
    // stars by avg
    const filled = Math.floor(stats.avg_rating);
    document.querySelectorAll('.avg-stars .star').forEach((el, idx) => {
      if (idx < filled) el.classList.add('filled'); else el.classList.remove('filled');
    });
  }

  // graceful fallback: if JS disabled the form still posts to add_review.php (full page submit)
});

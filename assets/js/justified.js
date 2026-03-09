document.addEventListener('DOMContentLoaded', function () {
  const grid = document.getElementById('photo-grid');
  if (!grid) return;
  const sentinel = document.getElementById('photo-grid-sentinel');
  const status = document.getElementById('photo-grid-status');
  const config = window.photoblogGrid || {};
  let currentPage = parseInt(grid.dataset.currentPage || '1', 10);
  let maxPages = parseInt(grid.dataset.maxPages || '1', 10);
  let isLoading = false;
  let observer = null;
  let scrollFallbackAttached = false;

  // Configuration: minimum row height and responsive breakpoints
  const MIN_ROW_HEIGHT = 120; // px - never go below this
  const DEFAULT_ROW_HEIGHT = 180; // px - desired target
  const MAX_ROW_HEIGHT = 380; // px - reasonable maximum row height

  function getTargetHeight() {
    const vw = Math.max(document.documentElement.clientWidth || 0, window.innerWidth || 0);
    if (vw < 480) return Math.max(MIN_ROW_HEIGHT, 120);
    if (vw < 900) return Math.max(MIN_ROW_HEIGHT, 140);
    return Math.max(MIN_ROW_HEIGHT, DEFAULT_ROW_HEIGHT);
  }

  function getItems() {
    return Array.from(grid.querySelectorAll('.photo-item'));
  }

  function updateStatus(message, state) {
    if (!status) return;

    const label = status.querySelector('.photo-grid-status__message');
    status.dataset.state = state || '';
    status.hidden = !message;

    if (label) {
      label.textContent = message || '';
    }
  }

  function stopInfiniteScroll() {
    if (observer) {
      observer.disconnect();
      observer = null;
    }

    if (scrollFallbackAttached) {
      window.removeEventListener('scroll', onScrollFallback);
      scrollFallbackAttached = false;
    }
  }

  function attachImageListeners(scope) {
    const images = Array.from((scope || grid).querySelectorAll('img'));

    images.forEach(img => {
      if (img.dataset.layoutBound === 'true') return;
      img.dataset.layoutBound = 'true';

      if (!img.complete) {
        img.addEventListener('load', scheduleCompute, { once: true });
        img.addEventListener('error', scheduleCompute, { once: true });
      }
    });
  }

  // compute a justified layout: group items into rows and increase row height so row widths fill container
  function computeLayout() {
    const items = getItems();
    if (!items.length) return;

    const gap = parseInt(getComputedStyle(grid).gap) || 8;
    const containerWidth = grid.clientWidth * 0.95;
    const MIN_W = 60;

    // remember the height of the last fully-justified (non-final) row
    // so the final row on desktop can match its height for visual consistency
    let lastJustifiedRowHeight = null;

    // collect ratios (w/h) for each item; if image not loaded yet, estimate 1
    const data = items.map(item => {
      const img = item.querySelector('img');
      const ratio = img && img.naturalWidth && img.naturalHeight ? (img.naturalWidth / img.naturalHeight) : 1;
      return { item, ratio, img };
    });

    let row = [];
    let sumRatios = 0;

    function flushRow(final) {
      if (row.length === 0) return;
      const totalGap = gap * (row.length - 1);
      // Compute row height. Normally we scale the row so it exactly fills
      // the container width. For the final row on desktop (wide viewports)
      // we prefer a left-justified row at the target height instead of
      // stretching items to fill the full width.
      let rowH;
      const isDesktop = (window.innerWidth || document.documentElement.clientWidth) >= 900;
      if (final && isDesktop && lastJustifiedRowHeight) {
        // On desktop, if we have a previous justified row height, reuse it so
        // the final row visually matches the other rows. Clamp to allowed range.
        rowH = Math.max(MIN_ROW_HEIGHT, Math.min(MAX_ROW_HEIGHT, Math.round(lastJustifiedRowHeight)));
      } else if (final && isDesktop) {
        // Fallback to target height when no previous height is available
        rowH = getTargetHeight();
        rowH = Math.max(MIN_ROW_HEIGHT, Math.min(MAX_ROW_HEIGHT, rowH));
      } else {
        // desired height to exactly fill width
        rowH = (containerWidth - totalGap) / sumRatios;
        // clamp
        rowH = Math.max(getTargetHeight(), Math.min(MAX_ROW_HEIGHT, rowH));

        // If this is the final row and rowH < getTargetHeight(), allow smaller height but not below MIN
        if (final && rowH < getTargetHeight()) {
          rowH = Math.max(MIN_ROW_HEIGHT, rowH);
        }
      }

      // apply sizes; distribute rounding remainder so row exactly fills container
      const widths = row.map(d => Math.max(MIN_W, Math.round(d.ratio * rowH)));
      const sumWidths = widths.reduce((s, v) => s + v, 0);
      const remaining = (containerWidth - totalGap) - sumWidths;
      // On desktop final row we do NOT distribute remaining pixels; keep items
      // at their natural scaled widths so the row is left-justified.
      if (!(final && isDesktop)) {
        if (remaining !== 0 && widths.length) {
          // add remaining pixels to the last item (could be negative)
          widths[widths.length - 1] = Math.max(MIN_W, widths[widths.length - 1] + remaining);
        }
      }

      row.forEach((d, idx) => {
        const w = widths[idx];
        d.item.style.width = w + 'px';
        d.item.style.height = rowH + 'px';
        d.item.style.flex = '0 0 ' + w + 'px';
      });

      // If this row was a non-final, fully-justified row, remember its height
      if (!final) {
        lastJustifiedRowHeight = rowH;
      }

      // reset
      row = [];
      sumRatios = 0;
    }

    for (let i = 0; i < data.length; i++) {
      const d = data[i];
      row.push(d);
      sumRatios += d.ratio;
      const totalGap = gap * (row.length - 1);
      const trialH = (containerWidth - totalGap) / sumRatios;
      // once trialH is less than or equal to MAX_ROW_HEIGHT, we can justify this row to full width
      if (trialH <= MAX_ROW_HEIGHT) {
        flushRow(false);
      } else {
        // otherwise keep adding items until it fits or until last item
        continue;
      }
    }

    // flush any remaining items (make them fill width if possible)
    if (row.length) flushRow(true);
  }

  // Recompute when images load and on resize
  function scheduleCompute() {
    clearTimeout(window._photoblog_compute_timer);
    window._photoblog_compute_timer = setTimeout(computeLayout, 80);
  }

  async function loadNextPage() {
    if (isLoading || currentPage >= maxPages || !config.ajaxUrl) {
      return;
    }

    isLoading = true;
    updateStatus('Loading more photos...', 'loading');

    try {
      const nextPage = currentPage + 1;
      const body = new URLSearchParams();
      body.set('action', 'photoblog_load_more_photos');
      body.set('nonce', config.nonce || '');
      body.set('page', String(nextPage));

      if (grid.dataset.taxonomy) {
        body.set('taxonomy', grid.dataset.taxonomy);
      }

      if (grid.dataset.termSlug) {
        body.set('termSlug', grid.dataset.termSlug);
      }

      if (grid.dataset.tagSlug) {
        body.set('tagSlug', grid.dataset.tagSlug);
      }

      const response = await fetch(config.ajaxUrl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
        },
        body: body.toString()
      });

      if (!response.ok) {
        throw new Error('Request failed');
      }

      const payload = await response.json();
      if (!payload || !payload.success || !payload.data) {
        throw new Error('Invalid response');
      }

      const wrapper = document.createElement('div');
      wrapper.innerHTML = payload.data.html || '';

      const newItems = Array.from(wrapper.children).filter(node => {
        return node.nodeType === Node.ELEMENT_NODE && node.classList.contains('photo-item');
      });

      currentPage = nextPage;
      maxPages = parseInt(payload.data.maxPages || maxPages, 10) || maxPages;

      if (newItems.length) {
        const fragment = document.createDocumentFragment();
        newItems.forEach(item => fragment.appendChild(item));
        grid.appendChild(fragment);
        attachImageListeners(grid);
        scheduleCompute();
      }

      if (!payload.data.hasMore || currentPage >= maxPages) {
        stopInfiniteScroll();
      }

      updateStatus('', 'idle');
    } catch (error) {
      updateStatus('Could not load more photos. Keep scrolling to retry.', 'error');
    } finally {
      isLoading = false;
    }
  }

  function onScrollFallback() {
    if (!sentinel || isLoading || currentPage >= maxPages) {
      return;
    }

    const threshold = window.innerHeight * 1.5;
    const sentinelTop = sentinel.getBoundingClientRect().top;

    if (sentinelTop <= threshold) {
      loadNextPage();
    }
  }

  function startInfiniteScroll() {
    if (!sentinel || currentPage >= maxPages) {
      return;
    }

    if ('IntersectionObserver' in window) {
      observer = new IntersectionObserver(entries => {
        entries.forEach(entry => {
          if (entry.isIntersecting) {
            loadNextPage();
          }
        });
      }, {
        rootMargin: '600px 0px'
      });

      observer.observe(sentinel);
      return;
    }

    window.addEventListener('scroll', onScrollFallback, { passive: true });
    scrollFallbackAttached = true;
  }

  attachImageListeners(grid);

  computeLayout();
  startInfiniteScroll();

  window.addEventListener('resize', scheduleCompute);
  window.addEventListener('orientationchange', scheduleCompute);
});


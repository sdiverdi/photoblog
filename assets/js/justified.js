document.addEventListener('DOMContentLoaded', function () {
  const grid = document.getElementById('photo-grid');
  if (!grid) return;

  const items = Array.from(grid.querySelectorAll('.photo-item'));

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

  // compute a justified layout: group items into rows and increase row height so row widths fill container
  function computeLayout() {
    const gap = parseInt(getComputedStyle(grid).gap) || 8;
    const containerWidth = grid.clientWidth * 0.95;
    const MIN_W = 60;

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
      // desired height to exactly fill width
      let rowH = (containerWidth - totalGap) / sumRatios;
      // clamp
      rowH = Math.max(getTargetHeight(), Math.min(MAX_ROW_HEIGHT, rowH));

      // If this is the final row and rowH < getTargetHeight(), allow smaller height but not below MIN
      if (final && rowH < getTargetHeight()) {
        rowH = Math.max(MIN_ROW_HEIGHT, rowH);
      }

      // apply sizes; distribute rounding remainder so row exactly fills container
      const widths = row.map(d => Math.max(MIN_W, Math.round(d.ratio * rowH)));
      const sumWidths = widths.reduce((s, v) => s + v, 0);
      const remaining = (containerWidth - totalGap) - sumWidths;
      if (remaining !== 0 && widths.length) {
        // add remaining pixels to the last item (could be negative)
        widths[widths.length - 1] = Math.max(MIN_W, widths[widths.length - 1] + remaining);
      }

      row.forEach((d, idx) => {
        const w = widths[idx];
        d.item.style.width = w + 'px';
        d.item.style.height = rowH + 'px';
        d.item.style.flex = '0 0 ' + w + 'px';
      });

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

  items.forEach(d => {
    const img = d.querySelector ? d.querySelector('img') : null;
    if (img && !img.complete) img.addEventListener('load', scheduleCompute);
  });

  computeLayout();

  window.addEventListener('resize', scheduleCompute);
  window.addEventListener('orientationchange', scheduleCompute);
});


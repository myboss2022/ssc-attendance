(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var body = document.body;
    body.classList.remove('opacity-0', 'translate-y-1');

    var style = document.createElement('style');
    style.textContent = [
      '#pageLoadingOverlay{position:fixed;inset:0;z-index:9999;display:flex;align-items:center;justify-content:center;background:rgba(15,23,42,.28);opacity:0;visibility:hidden;transition:opacity .12s ease,visibility .12s ease}',
      '#pageLoadingOverlay.is-visible{opacity:1;visibility:visible}',
      '#pageLoadingOverlay .page-loading-spinner{width:3.25rem;height:3.25rem;border:.35rem solid rgba(255,255,255,.55);border-top-color:#4f46e5;border-radius:50%;background:#fff;box-shadow:0 8px 24px rgba(15,23,42,.2);animation:page-loading-spin .75s linear infinite}',
      '@keyframes page-loading-spin{to{transform:rotate(360deg)}}',
      '@media(prefers-reduced-motion:reduce){#pageLoadingOverlay .page-loading-spinner{animation-duration:1.5s}}'
    ].join('');
    document.head.appendChild(style);

    var overlay = document.createElement('div');
    overlay.id = 'pageLoadingOverlay';
    overlay.setAttribute('role', 'status');
    overlay.setAttribute('aria-live', 'polite');
    overlay.setAttribute('aria-label', 'Loading page');
    overlay.setAttribute('aria-hidden', 'true');
    overlay.innerHTML = '<span class="page-loading-spinner" aria-hidden="true"></span>';
    body.appendChild(overlay);

    function hideLoading() {
      overlay.classList.remove('is-visible');
      overlay.setAttribute('aria-hidden', 'true');
      body.removeAttribute('aria-busy');
    }

    function showLoading() {
      if (overlay.classList.contains('is-visible')) return;
      overlay.classList.add('is-visible');
      overlay.setAttribute('aria-hidden', 'false');
      body.setAttribute('aria-busy', 'true');
    }

    document.addEventListener('click', function (event) {
      if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
      var link = event.target.closest('a[href]');
      if (!link || link.hasAttribute('download') || link.hasAttribute('data-no-loading') ||
          link.target === '_blank' || link.getAttribute('aria-disabled') === 'true') return;

      var destination;
      try {
        destination = new URL(link.href, window.location.href);
      } catch (error) {
        return;
      }
      if (destination.origin !== window.location.origin ||
          (destination.pathname === window.location.pathname &&
           destination.search === window.location.search && destination.hash)) return;

      showLoading();
      window.setTimeout(function () {
        if (event.defaultPrevented) hideLoading();
      }, 0);
    });

    document.addEventListener('submit', function (event) {
      var form = event.target;
      if (form.target === '_blank' || form.hasAttribute('data-no-loading') ||
          (form.method && form.method.toLowerCase() === 'dialog')) return;

      showLoading();
      window.setTimeout(function () {
        if (event.defaultPrevented) hideLoading();
      }, 0);
    });

    window.addEventListener('pageshow', function (event) {
      if (event.persisted) hideLoading();
    });
  });
})();

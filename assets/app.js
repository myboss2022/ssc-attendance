(function () {
  document.addEventListener('DOMContentLoaded', function () {
    var body = document.body;
    body.classList.remove('opacity-0', 'translate-y-1');

    var style = document.createElement('style');
    style.textContent = [
      '#pageNavigationProgress{position:fixed;top:0;left:0;z-index:9999;width:0;height:3px;pointer-events:none;background:linear-gradient(90deg,#4f46e5,#06b6d4);box-shadow:0 0 8px rgba(79,70,229,.45);opacity:0;transition:width .2s ease,opacity .15s ease}',
      '#pageNavigationProgress.is-visible{width:82%;opacity:1}',
      '@media(prefers-reduced-motion:reduce){#pageNavigationProgress{transition:none}}'
    ].join('');
    document.head.appendChild(style);

    var progress = document.createElement('div');
    progress.id = 'pageNavigationProgress';
    progress.setAttribute('role', 'progressbar');
    progress.setAttribute('aria-label', 'Loading page');
    progress.setAttribute('aria-valuemin', '0');
    progress.setAttribute('aria-valuemax', '100');
    progress.setAttribute('aria-valuenow', '82');
    progress.setAttribute('aria-hidden', 'true');
    body.appendChild(progress);

    var attendanceDockContent = document.getElementById('mobileAttendanceDockContent');
    var hideAttendanceDock = document.getElementById('hideMobileAttendanceDock');
    var showAttendanceDock = document.getElementById('showMobileAttendanceDock');

    if (attendanceDockContent && hideAttendanceDock && showAttendanceDock) {
      hideAttendanceDock.addEventListener('click', function () {
        attendanceDockContent.hidden = true;
        hideAttendanceDock.setAttribute('aria-expanded', 'false');
        showAttendanceDock.hidden = false;
        showAttendanceDock.setAttribute('aria-expanded', 'false');
      });

      showAttendanceDock.addEventListener('click', function () {
        attendanceDockContent.hidden = false;
        hideAttendanceDock.setAttribute('aria-expanded', 'true');
        showAttendanceDock.hidden = true;
        showAttendanceDock.setAttribute('aria-expanded', 'true');
      });
    }

    function hideProgress() {
      progress.classList.remove('is-visible');
      progress.setAttribute('aria-hidden', 'true');
      body.removeAttribute('aria-busy');
    }

    function showLoading() {
      if (progress.classList.contains('is-visible')) return;
      progress.classList.add('is-visible');
      progress.setAttribute('aria-hidden', 'false');
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
        if (event.defaultPrevented) hideProgress();
      }, 0);
    });

    document.addEventListener('submit', function (event) {
      var form = event.target;
      if (form.target === '_blank' || form.hasAttribute('data-no-loading') ||
          (form.method && form.method.toLowerCase() === 'dialog')) return;

      showLoading();
      window.setTimeout(function () {
        if (event.defaultPrevented) hideProgress();
      }, 0);
    });

    window.addEventListener('pageshow', function (event) {
      if (event.persisted) hideProgress();
    });
  });
})();

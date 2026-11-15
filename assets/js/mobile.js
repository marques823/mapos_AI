(function () {
  'use strict';

  var sidebar = document.getElementById('sidebar');
  if (!sidebar) {
    return;
  }

  var backdrop = document.createElement('div');
  backdrop.id = 'drawer-backdrop';
  document.body.appendChild(backdrop);

  function isMobile() {
    return window.innerWidth <= 768;
  }

  function sync() {
    var open = isMobile() && sidebar.classList.contains('open');
    document.body.classList.toggle('drawer-open', open);
  }

  function closeDrawer() {
    sidebar.classList.remove('open');
    sync();
  }

  var contents = document.querySelectorAll('#content .widget-content');
  Array.prototype.forEach.call(contents, function (element) {
    if (element.querySelector('table')) {
      element.classList.add('mobile-table-scroll');
    }
  });

  if (window.MutationObserver) {
    new MutationObserver(sync).observe(sidebar, {
      attributes: true,
      attributeFilter: ['class']
    });
  }

  window.addEventListener('resize', sync);

  backdrop.addEventListener('click', closeDrawer);

  sidebar.addEventListener('click', function (event) {
    if (!isMobile()) {
      return;
    }

    var link = event.target.closest ? event.target.closest('a[href]') : null;
    if (!link) {
      return;
    }

    if (link.classList.contains('visible-phone')) {
      return;
    }

    closeDrawer();
  });

  sync();
})();

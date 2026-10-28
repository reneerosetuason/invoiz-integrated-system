(function () {
      var sb = document.getElementById('sidebar');
      var hb = document.querySelector('.hamburger');
      var collapsed = localStorage.getItem('invSidebarCollapsed') === '1';
      if (window.matchMedia('(max-width: 1023.98px)').matches) collapsed = true;
      if (collapsed) sb.classList.add('collapsed', 'sidebar-collapsed');
      hb.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      hb.addEventListener('click', function () {
        var collapsed = sb.classList.toggle('collapsed');
        sb.classList.toggle('sidebar-collapsed', collapsed);
        localStorage.setItem('invSidebarCollapsed', collapsed ? '1' : '0');
        hb.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
      });
    })();

    (function () {
      var path = location.pathname.replace(/\/+$/, '') || '/';
      var best = null;
      document.querySelectorAll('.nav-link[href]').forEach(function (a) {
        a.classList.remove('active');
        a.removeAttribute('aria-current');
        var href = a.getAttribute('href').replace(/\/+$/, '');
        if (!href || href === '#') return;
        if ((path === href || path.indexOf(href + '/') === 0) &&
            (!best || href.length > best.getAttribute('href').replace(/\/+$/, '').length)) {
          best = a;
        }
      });
      if (best) {
        best.classList.add('active');
        best.setAttribute('aria-current', 'page');
      }
    })();

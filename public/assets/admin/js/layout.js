try {
      if (sessionStorage.getItem('invAdminSidebarIntroSeen') === '1') {
        document.documentElement.classList.add('admin-sidebar-seen');
      } else {
        sessionStorage.setItem('invAdminSidebarIntroSeen', '1');
      }
    } catch (e) {
      document.documentElement.classList.add('admin-sidebar-seen');
    }

(function () {
      var overlay = document.getElementById('confirmModal');
      var titleEl = document.getElementById('confirmTitle');
      var msgEl = document.getElementById('confirmMsg');
      var okBtn = document.getElementById('confirmOk');
      var cancelBtn = document.getElementById('confirmCancel');
      var iconEl = document.getElementById('confirmIcon');
      var pendingForm = null;
      window.openConfirm = function (opts) {
        titleEl.textContent = opts.title || 'Confirm action';
        msgEl.textContent = opts.message || 'Are you sure?';
        okBtn.textContent = opts.okText || 'OK';
        okBtn.className = 'btn ' + (opts.okClass || 'btn-danger');
        okBtn.style.minWidth = '110px';
        iconEl.className = 'confirm-icon' + (opts.iconClass ? ' ' + opts.iconClass : '');
        pendingForm = opts.form || null;
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';
        // trap focus
        cancelBtn.focus();
        if (opts.onConfirm) {
          pendingForm = null;
          okBtn._cb = opts.onConfirm;
        } else {
          okBtn._cb = null;
        }
      };
      function closeConfirm() {
        overlay.classList.remove('open');
        document.body.style.overflow = '';
        pendingForm = null;
        okBtn._cb = null;
      }
      cancelBtn.addEventListener('click', closeConfirm);
      overlay.addEventListener('click', function (e) { if (e.target === overlay) closeConfirm(); });
      document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && overlay.classList.contains('open')) closeConfirm(); });
      okBtn.addEventListener('click', function () {
        var cb = okBtn._cb;
        var form = pendingForm;
        closeConfirm();
        if (cb) cb();
        else if (form) form.submit();
      });
      // Intercept forms with data-confirm
      document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!form.hasAttribute('data-confirm')) return;
        e.preventDefault();
        var msg = form.getAttribute('data-confirm');
        var title = form.getAttribute('data-confirm-title') || 'Confirm action';
        var okText = form.getAttribute('data-confirm-ok') || 'OK';
        var okClass = form.getAttribute('data-confirm-class') || 'btn-danger';
        var iconClass = form.getAttribute('data-confirm-icon') || '';
        openConfirm({ title: title, message: msg, okText: okText, okClass: okClass, iconClass: iconClass, form: form });
      });
    })();

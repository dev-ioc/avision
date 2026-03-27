// External JS for documentation modal (bypasses inline-script CSP)
(function() {
  function onReady(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
    } else {
      fn();
    }
  }

  onReady(function() {
    try {
      console.log('documentation-modal.js loaded');
      var btn = document.getElementById('openAddAttachmentModalButton');
      var modalEl = document.getElementById('addAttachmentModal');
      if (!btn) {
        console.warn('openAddAttachmentModalButton not found');
        return;
      }
      if (!modalEl) {
        console.warn('addAttachmentModal not found');
        return;
      }

      function showModal() {
        if (window.bootstrap && window.bootstrap.Modal) {
          try {
            var modal = new window.bootstrap.Modal(modalEl);
            modal.show();
          } catch (err) {
            console.error('Failed to show modal:', err);
          }
        } else {
          console.warn('Bootstrap not ready yet, retrying...');
          setTimeout(showModal, 100);
        }
      }

      btn.addEventListener('click', function(e) {
        e.preventDefault();
        console.log('openAddAttachmentModalButton clicked');
        showModal();
      });
    } catch (e) {
      console.error('documentation-modal init error:', e);
    }
  });
})();



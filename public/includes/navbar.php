<!-- Layout container -->
<div class="layout-page">
  <!-- Navbar -->

  <nav class="layout-navbar container-fluid navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
    id="layout-navbar">
    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
      <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
        <i class="bi bi-list icon-md"></i>
      </a>
    </div>

    <div class="navbar-nav-right d-flex align-items-center justify-content-end" id="navbar-collapse">
      <!-- Breadcrumbs -->
      <?php
      $breadcrumbs = generateBreadcrumbs();
      if (!empty($breadcrumbs)):
        ?>
        <nav aria-label="breadcrumb" class="d-none d-xl-flex align-items-center me-3 ms-3 breadcrumb-nav">
          <ol class="breadcrumb mb-0"
            style="overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; -ms-overflow-style: none;">
            <?php foreach ($breadcrumbs as $index => $crumb): ?>
              <?php if (isset($crumb['active']) && $crumb['active']): ?>
                <li class="breadcrumb-item active" aria-current="page"><?php echo $crumb['label']; ?></li>
              <?php else: ?>
                <li class="breadcrumb-item">
                  <a href="<?php echo h($crumb['url']); ?>"><?php echo $crumb['label']; ?></a>
                </li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ol>
        </nav>
      <?php endif; ?>

      <div class="navbar-nav-right d-flex align-items-center justify-content-end">
        <ul class="navbar-nav ms-lg-auto">
          <?php if (isImpersonating()): ?>
            <li class="nav-item d-flex align-items-center me-3">
              <span class="badge bg-warning text-dark me-2">
                <i class="bi bi-eye me-1"></i>
                Mode client (lecture seule) · admin :
                <?php echo h($_SESSION['impersonator']['email'] ?? ''); ?>
              </span>
              <form method="POST" action="<?php echo BASE_URL; ?>user/stop-impersonation" class="mb-0">
                <?= csrf_field() ?>
                <button type="submit" class="btn btn-sm btn-dark">Revenir à mon compte admin</button>
              </form>
            </li>
          <?php endif; ?>
          <li class="nav-item">
            <a class="nav-link" href="javascript:void(0)"><i class="navbar-icon bi bi-person"></i>
              <?php echo ($_SESSION['user']['first_name'] ?? '') . ' ' . ($_SESSION['user']['last_name'] ?? ''); ?></a>
          </li>
        </ul>
      </div>


    </div>
  </nav>

  <!-- / Navbar -->

  <!-- Content wrapper -->
  <div class="content-wrapper">
    <?php if (!empty($_SESSION['impersonation_notice'])): ?>
      <div class="container-fluid pt-3">
        <div class="alert alert-warning alert-dismissible mb-0" role="alert">
          <i class="bi bi-eye me-1"></i>
          <?php echo h($_SESSION['impersonation_notice']); ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fermer"></button>
        </div>
      </div>
      <?php unset($_SESSION['impersonation_notice']); ?>
    <?php endif; ?>
    <?php if (isImpersonating()): ?>
      <style>
        .impersonation-locked {
          opacity: .45;
          cursor: not-allowed !important;
        }

        #imp-toast {
          position: fixed;
          top: 70px;
          right: 20px;
          z-index: 99999;
          max-width: 380px;
        }
      </style>
      <script>
        (function () {
          var MSG = "Mode « connecté en tant que » : consultation uniquement.";
          var WRITE = /^(add|create|edit|update|store|delete|remove|toggle|close|archive|activate|deactivate|reset|send|setPrimary|save|assign|reopen|import|upload|bulk|renew|quick)/i;
          var ALLOW_FORM = /(stop-impersonation|auth\/logout)/i;

          function isWriteUrl(href) {
            try {
              var u = new URL(href, window.location.href);
              if (u.origin !== window.location.origin) return false;
              return u.pathname.split('/').filter(Boolean).some(function (s) { return WRITE.test(s); });
            } catch (e) { return false; }
          }

          function lock(el) {
            if (el.dataset.impLocked) return;
            el.dataset.impLocked = '1';
            el.classList.add('impersonation-locked');
            el.setAttribute('aria-disabled', 'true');
            el.setAttribute('title', MSG);
            el.setAttribute('tabindex', '-1');
          }

          function toast() {
            var old = document.getElementById('imp-toast');
            if (old) old.remove();
            var d = document.createElement('div');
            d.id = 'imp-toast';
            d.className = 'alert alert-warning shadow mb-0';
            d.textContent = MSG;
            document.body.appendChild(d);
            setTimeout(function () { d.remove(); }, 3500);
          }

          function scan(root) {
            if (!root.querySelectorAll) return;
            root.querySelectorAll('a[href]').forEach(function (a) {
              if (a.closest('#layout-navbar')) return;
              if (isWriteUrl(a.getAttribute('href'))) lock(a);
            });
            root.querySelectorAll('form').forEach(function (f) {
              if (f.closest('#layout-navbar') || f.hasAttribute('data-impersonation-allow')) return;
              if (ALLOW_FORM.test(f.getAttribute('action') || '')) return;
              if ((f.getAttribute('method') || 'get').toLowerCase() !== 'post') return;
              f.dataset.impBlocked = '1';
              f.querySelectorAll('button[type=submit], input[type=submit], button:not([type])').forEach(lock);
            });
            root.querySelectorAll('[data-write-action]').forEach(lock);
          }

          // Capture : bloque avant les onclick inline et les handlers de la page
          document.addEventListener('click', function (e) {
            var el = e.target.closest('.impersonation-locked');
            if (el) { e.preventDefault(); e.stopImmediatePropagation(); toast(); }
          }, true);
          document.addEventListener('submit', function (e) {
            if (e.target.dataset && e.target.dataset.impBlocked) {
              e.preventDefault(); e.stopImmediatePropagation(); toast();
            }
          }, true);

          document.addEventListener('DOMContentLoaded', function () {
            scan(document);
            new MutationObserver(function (muts) {
              muts.forEach(function (m) { m.addedNodes.forEach(function (n) { if (n.nodeType === 1) scan(n); }); });
            }).observe(document.body, { childList: true, subtree: true });
          });
        })();
      </script>
        <?php endif; ?>
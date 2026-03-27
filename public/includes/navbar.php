        <!-- Layout container -->
        <div class="layout-page">
          <!-- Navbar -->

          <nav
            class="layout-navbar container-fluid navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
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
                <ol class="breadcrumb mb-0" style="overflow-x: auto; -webkit-overflow-scrolling: touch; scrollbar-width: none; -ms-overflow-style: none;">
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
                  <li class="nav-item">
                    <a class="nav-link" href="javascript:void(0)"><i class="navbar-icon bi bi-person"></i> <?php echo ($_SESSION['user']['first_name'] ?? '') . ' ' . ($_SESSION['user']['last_name'] ?? ''); ?></a>
                  </li>
                </ul>
              </div>


            </div>
          </nav>

          <!-- / Navbar -->

          <!-- Content wrapper -->
          <div class="content-wrapper"> 

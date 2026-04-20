<?php
require_once __DIR__ . '/../../includes/functions.php';

/**
 * Vue de la liste des interventions
 */

if (!isset($_SESSION['user'])) {
  header('Location: ' . BASE_URL . 'auth/login');
  exit;
}

// CSRF
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$userType = $_SESSION['user']['user_type'] ?? null;

$currentUrl = $_SERVER['REQUEST_URI'] ?? '';
$isPreventivePage = strpos($currentUrl, '/interventions/preventives') !== false;

$pageTitle = $isPreventivePage ? 'Interventions Préventives' : 'Interventions Curatives';

setPageVariables($pageTitle, 'interventions');

$GLOBALS['customBreadcrumbs'] = generateInterventionsListBreadcrumbs($isPreventivePage);

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

/**
 * PAGINATION POUR MOBILE UNIQUEMENT
 * Pour desktop, c'est DataTables qui gère la pagination
 */
$perPage = 15;
$page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
$offset = ($page - 1) * $perPage;

// Récupérer toutes les interventions (pour mobile)
$allInterventions = $interventions ?? [];

// Paginer manuellement pour mobile
$totalInterventions = $totalInterventions ?? count($allInterventions);
$totalPages = max(1, ceil($totalInterventions / $perPage));

// Extraire uniquement les interventions de la page courante pour mobile
$paginatedInterventions = array_slice($allInterventions, $offset, $perPage);

// Conserver les paramètres GET existants
$queryParams = $_GET;
unset($queryParams['page']);
$queryString = http_build_query($queryParams);
$baseUrlWithParams = $queryString ? '?' . $queryString . '&page=' : '?page=';
?>

<div class="container-fluid flex-grow-1 container-p-y">

  <!-- HEADER -->
  <div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight">
      <h4 class="py-4 mb-6">
        <?php if ($isPreventivePage): ?>
          <i class="bi bi-shield-check me-2"></i>Interventions Préventives
        <?php else: ?>
          <i class="bi bi-tools me-2"></i>Interventions Curatives
        <?php endif; ?>
      </h4>
    </div>

    <div class="ms-auto p-2 bd-highlight">
      <?php if (canModifyInterventions()): ?>
        <a href="<?= BASE_URL ?>interventions/add" class="btn btn-primary">
          <i class="bi bi-plus me-1"></i> Ajouter une intervention
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- TABLE DESKTOP - DataTables gère la pagination -->
  <div class="table-responsive d-none d-md-block">
    <table id="interventionsTable" class="table table-striped table-hover">
      <thead>
        <tr>
          <th>Référence</th>
          <th>Titre</th>
          <th>Client</th>
          <th>Site</th>
          <th>Salle</th>
          <th>Statut</th>
          <th>Priorité</th>
          <th>Date</th>
        </tr>
      </thead>
      <tbody>
        <?php if (empty($allInterventions)): ?>
          <tr>
            <td colspan="8" class="text-center">Aucune intervention trouvée</td>
          </tr>
        <?php else: ?>
          <?php foreach ($allInterventions as $intervention): ?>
            <tr>
              <td>
                <a href="<?= BASE_URL ?>interventions/view/<?= $intervention['id'] ?>">
                  <?= htmlspecialchars($intervention['reference'] ?? '-') ?>
                </a>
              </td>
              <td>
                <?= htmlspecialchars($intervention['title'] ?? '-') ?>
              </td>
              <td>
                <?= htmlspecialchars($intervention['client_name'] ?? '-') ?>
              </td>
              <td>
                <?= htmlspecialchars($intervention['site_name'] ?? '-') ?>
              </td>
              <td>
                <?= htmlspecialchars($intervention['room_name'] ?? '-') ?>
              </td>
              <td>
                <span class="badge" style="background: <?= $intervention['status_color'] ?? '#ccc' ?>">
                  <?= htmlspecialchars($intervention['status_name'] ?? '-') ?>
                </span>
              </td>
              <td>
                <span class="badge" style="background: <?= $intervention['priority_color'] ?? '#ccc' ?>">
                  <?= htmlspecialchars($intervention['priority_name'] ?? '-') ?>
                </span>
              </td>
              <td>
                <?= !empty($intervention['created_at'])
                  ? date('d/m/Y H:i', strtotime($intervention['created_at']))
                  : '-' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- 📱 MOBILE CARDS - Pagination manuelle -->
  <div class="mobile-interventions d-block d-md-none">
    <?php if (empty($paginatedInterventions)): ?>
      <div class="text-center py-4">
        <p>Aucune intervention trouvée</p>
      </div>
    <?php else: ?>
      <?php foreach ($paginatedInterventions as $intervention): ?>
        <div class="intervention-card">
          <!-- En-tête avec référence et titre -->
          <div class="intervention-header">
            <div class="intervention-reference">
              <a href="<?= BASE_URL ?>interventions/view/<?= $intervention['id'] ?>">
                <?= htmlspecialchars($intervention['reference'] ?? '-') ?>
              </a>
            </div>
            <div class="intervention-title">
              <?= htmlspecialchars($intervention['title'] ?? '-') ?>
            </div>
          </div>

          <!-- Client et site -->
          <div class="intervention-location">
            <div class="client-info">
              <i class="bi bi-building"></i>
              <?= htmlspecialchars($intervention['client_name'] ?? '-') ?>
            </div>
            <?php if (!empty($intervention['site_name']) && $intervention['site_name'] != '-'): ?>
              <div class="site-info">
                <i class="bi bi-geo-alt"></i>
                <?= htmlspecialchars($intervention['site_name'] ?? '-') ?>
                <?php if (!empty($intervention['room_name']) && $intervention['room_name'] != '-'): ?>
                  /
                  <?= htmlspecialchars($intervention['room_name'] ?? '-') ?>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Badges statut et priorité -->
          <div class="intervention-badges">
            <span class="badge" style="background: <?= $intervention['status_color'] ?? '#6c757d' ?>; color: #fff;">
              <i class="bi bi-circle-fill me-1" style="font-size: 8px;"></i>
              <?= htmlspecialchars($intervention['status_name'] ?? '-') ?>
            </span>
            <span class="badge" style="background: <?= $intervention['priority_color'] ?? '#6c757d' ?>; color: #fff;">
              <i class="bi bi-flag-fill me-1"></i>
              <?= htmlspecialchars($intervention['priority_name'] ?? '-') ?>
            </span>
          </div>

          <!-- Date de création -->
          <div class="intervention-date">
            <i class="bi bi-calendar3"></i>
            <?= !empty($intervention['created_at'])
              ? date('d/m/Y H:i', strtotime($intervention['created_at']))
              : '-' ?>
          </div>

          <!-- Salle (si pas déjà affichée) -->
          <?php if (empty($intervention['site_name']) && !empty($intervention['room_name']) && $intervention['room_name'] != '-'): ?>
            <div class="intervention-room">
              <i class="bi bi-door-closed"></i>
              Salle:
              <?= htmlspecialchars($intervention['room_name'] ?? '-') ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>

    <!-- PAGINATION MOBILE -->
    <?php if ($totalPages > 1): ?>
      <div class="pagination-mobile text-center mt-4">
        <div class="btn-group" role="group">
          <?php if ($page > 1): ?>
            <a href="<?= $baseUrlWithParams . ($page - 1) ?>" class="btn btn-outline-primary">
              <i class="bi bi-chevron-left"></i>
            </a>
          <?php endif; ?>

          <button class="btn btn-primary" disabled>
            Page
            <?= $page ?> /
            <?= $totalPages ?>
          </button>

          <?php if ($page < $totalPages): ?>
            <a href="<?= $baseUrlWithParams . ($page + 1) ?>" class="btn btn-outline-primary">
              <i class="bi bi-chevron-right"></i>
            </a>
          <?php endif; ?>
        </div>

        <!-- Sélecteur de page rapide -->
        <?php if ($totalPages > 5): ?>
          <div class="mt-2">
            <select class="form-select form-select-sm d-inline-block w-auto" id="mobilePageSelect">
              <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <option value="<?= $i ?>" <?= $i == $page ? 'selected' : '' ?>>
                  Aller à la page
                  <?= $i ?>
                </option>
              <?php endfor; ?>
            </select>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- STYLE MOBILE -->
<style>
  @media (max-width: 768px) {
    .intervention-card {
      border: 1px solid var(--bs-border-color);
      border-radius: 12px;
      padding: 14px;
      margin-bottom: 12px;
      background: var(--bs-body-bg);
      transition: all 0.3s ease;
      box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .intervention-card:hover {
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
      transform: translateY(-2px);
    }

    .intervention-header {
      margin-bottom: 10px;
      border-bottom: 1px dashed var(--bs-border-color);
      padding-bottom: 8px;
    }

    .intervention-reference {
      font-size: 13px;
      color: var(--bs-primary);
      margin-bottom: 4px;
    }

    .intervention-reference a {
      font-weight: 600;
      text-decoration: none;
      color: var(--bs-primary);
    }

    .intervention-title {
      font-weight: 600;
      font-size: 15px;
      color: var(--bs-body-color);
      line-height: 1.4;
    }

    .intervention-location {
      margin-bottom: 10px;
    }

    .client-info,
    .site-info {
      font-size: 13px;
      color: var(--bs-secondary-color);
      margin-bottom: 4px;
    }

    .client-info i,
    .site-info i,
    .intervention-date i,
    .intervention-room i {
      margin-right: 6px;
      font-size: 12px;
    }

    .intervention-badges {
      display: flex;
      gap: 8px;
      margin: 10px 0;
      flex-wrap: wrap;
    }

    .intervention-badges .badge {
      padding: 5px 10px;
      font-weight: 500;
      font-size: 11px;
      border-radius: 20px;
    }

    .intervention-date {
      font-size: 12px;
      margin-top: 8px;
      padding-top: 8px;
      border-top: 1px solid var(--bs-border-color);
      color: var(--bs-secondary-color);
    }

    .intervention-room {
      font-size: 12px;
      margin-top: 6px;
      color: var(--bs-secondary-color);
    }

    .pagination-mobile select {
      max-width: 150px;
    }
  }
</style>

<!-- SCRIPT POUR LA NAVIGATION RAPIDE -->
<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Navigation par select sur mobile
    const pageSelect = document.getElementById('mobilePageSelect');
    if (pageSelect) {
      pageSelect.addEventListener('change', function () {
        const page = this.value;
        window.location.href = '<?= $baseUrlWithParams ?>' + page;
      });
    }
  });

  window.BASE_URL = '<?= BASE_URL ?>';
  window.csrfToken = '<?= $_SESSION['csrf_token'] ?>';
</script>

<script src="<?= BASE_URL ?>assets/js/datatable-persistence.js"></script>
<script src="<?= BASE_URL ?>assets/js/interventions-datatable.js"></script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
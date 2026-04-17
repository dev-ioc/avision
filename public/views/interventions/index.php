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
                  ? formatDateFrench($intervention['created_at'])
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
          <div class="intervention-title">
            <a href="<?= BASE_URL ?>interventions/view/<?= $intervention['id'] ?>">
              <?= htmlspecialchars($intervention['reference'] ?? '-') ?>
            </a>
            —
            <?= htmlspecialchars($intervention['title'] ?? '-') ?>
          </div>

          <div class="intervention-meta">
            <?= htmlspecialchars($intervention['client_name'] ?? '-') ?>
            •
            <?= htmlspecialchars($intervention['site_name'] ?? '-') ?>
          </div>

          <div class="intervention-badges">
            <span class="badge" style="background: <?= $intervention['status_color'] ?? '#ccc' ?>">
              <?= htmlspecialchars($intervention['status_name'] ?? '-') ?>
            </span>

            <span class="badge" style="background: <?= $intervention['priority_color'] ?? '#ccc' ?>">
              <?= htmlspecialchars($intervention['priority_name'] ?? '-') ?>
            </span>
          </div>

          <div class="intervention-date">
            📅
            <?= !empty($intervention['created_at'])
              ? formatDateFrench($intervention['created_at'])
              : '-' ?>
          </div>
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
      </div>
    <?php endif; ?>
  </div>

</div>

<!-- STYLE MOBILE -->
<style>
  @media (max-width: 768px) {
    .intervention-card {
      border: 1px solid #ddd;
      border-radius: 10px;
      padding: 12px;
      margin-bottom: 10px;
      background: var(--bs-body-bg);
      transition: box-shadow 0.3s ease;
    }

    .intervention-card:hover {
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    }

    .intervention-title {
      font-weight: 600;
      font-size: 15px;
      margin-bottom: 6px;
    }

    .intervention-title a {
      text-decoration: none;
      color: var(--bs-primary);
    }

    .intervention-meta {
      font-size: 13px;
      color: #666;
      margin-bottom: 8px;
    }

    .intervention-badges {
      display: flex;
      gap: 6px;
      margin-top: 6px;
      flex-wrap: wrap;
    }

    .intervention-date {
      font-size: 12px;
      margin-top: 8px;
      color: #999;
    }
  }
</style>

<script>
  window.BASE_URL = '<?= BASE_URL ?>';
  window.csrfToken = '<?= $_SESSION['csrf_token'] ?>';
</script>

<script src="<?= BASE_URL ?>assets/js/datatable-persistence.js"></script>
<script src="<?= BASE_URL ?>assets/js/interventions-datatable.js"></script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de la liste des interventions
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
  header('Location: ' . BASE_URL . 'auth/login');
  exit;
}

// Générer un token CSRF s'il n'existe pas
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Déterminer le type d'intervention depuis l'URL
$currentUrl = $_SERVER['REQUEST_URI'] ?? '';
$isPreventivePage = strpos($currentUrl, '/interventions/preventives') !== false;
$pageTitle = $isPreventivePage ? 'Interventions Préventives' : 'Interventions Curatives';

setPageVariables(
  $pageTitle,
  'interventions'
);

// Définir les breadcrumbs personnalisés pour les pages interventions curatives/préventives
$GLOBALS['customBreadcrumbs'] = generateInterventionsListBreadcrumbs($isPreventivePage);

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

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
        <a href="<?php echo BASE_URL; ?>interventions/add" class="btn btn-primary">
          <i class="bi bi-plus me-1"></i> Ajouter une intervention
        </a>
      <?php endif; ?>
    </div>
  </div>

  <!-- Filtres par staff et statut -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="card status-filter-card">
        <div class="card-body">
          <!-- Filtres rapides par statut et priorité -->
          <div class="d-flex flex-wrap gap-2 align-items-center">
            <!-- Filtres par statut -->
            <div class="d-flex flex-wrap gap-2">
              <?php
              $currentRoute = $isPreventivePage ? 'interventions/preventives' : 'interventions/curatives';
              $allUrl = BASE_URL . $currentRoute;
              ?>
              <a href="<?php echo $allUrl; ?>"
                class="btn btn-outline-secondary btn-sm status-filter-btn <?php echo (!isset($_GET['status_id'])) ? 'active' : ''; ?>">
                <span class="badge bg-secondary me-1">
                  <?php echo array_sum(array_column($statsByStatus, 'count')); ?>
                </span>
                Tous les statuts
              </a>

              <?php foreach ($statsByStatus as $statusStat): ?>
                <?php
                $statusUrl = BASE_URL . $currentRoute . '?status_id=' . $statusStat['id'];
                ?>
                <a href="<?php echo $statusUrl; ?>"
                  class="btn btn-outline-secondary btn-sm status-filter-btn <?php echo (isset($_GET['status_id']) && $_GET['status_id'] == $statusStat['id']) ? 'active' : ''; ?>">
                  <span class="badge me-1" style="background-color: <?php echo $statusStat['color']; ?>">
                    <?php echo $statusStat['count']; ?>
                  </span>
                  <?php echo h($statusStat['name']); ?>
                </a>
              <?php endforeach; ?>
            </div>

            <!-- Séparateur -->
            <div class="vr mx-2"></div>

            <!-- Filtres par priorité -->
            <?php if (!$isPreventivePage): ?>
              <div class="d-flex flex-wrap gap-2">
                <?php
                $currentRoute = 'interventions/curatives';
                $allPriorityUrl = BASE_URL . $currentRoute;
                $params = [];
                if (isset($_GET['status_id']) && !empty($_GET['status_id'])) {
                  $params[] = 'status_id=' . $_GET['status_id'];
                }
                if (!empty($params)) {
                  $allPriorityUrl .= '?' . implode('&', $params);
                }
                ?>
                <a href="<?php echo $allPriorityUrl; ?>"
                  class="btn btn-outline-secondary btn-sm priority-filter-btn <?php echo (!isset($_GET['priority_id'])) ? 'active' : ''; ?>">
                  Toutes les priorités
                </a>

                <?php foreach ($priorities as $priority): ?>
                  <?php
                  if (stripos($priority['name'], 'préventif') !== false || stripos($priority['name'], 'preventive') !== false) {
                    continue;
                  }
                  $priorityUrl = BASE_URL . $currentRoute . '?priority_id=' . $priority['id'];
                  if (isset($_GET['status_id']) && !empty($_GET['status_id'])) {
                    $priorityUrl .= '&status_id=' . $_GET['status_id'];
                  }
                  ?>
                  <a href="<?php echo $priorityUrl; ?>"
                    class="btn btn-outline-secondary btn-sm priority-filter-btn <?php echo (isset($_GET['priority_id']) && $_GET['priority_id'] == $priority['id']) ? 'active' : ''; ?>">
                    <span class="badge me-1" style="background-color: <?php echo $priority['color']; ?>">
                      <?php echo h($priority['name']); ?>
                    </span>
                  </a>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="table-responsive">
    <table id="interventionsTable" class="table table-striped table-hover dt-responsive">
      <thead>
        <tr>
          <th>Référence</th>
          <th>Titre</th>
          <th>Client</th>
          <th>Site</th>
          <th>Salle</th>
          <th>Statut</th>
          <th>Priorité</th>
          <th>Date création</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!empty($interventions)): ?>
          <?php foreach ($interventions as $intervention): ?>
            <tr>
              <td>
                <a href="<?= BASE_URL; ?>interventions/view/<?= $intervention['id']; ?>">
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
              <td data-order="<?= $intervention['status_id'] ?? 0 ?>">
                <span class="badge" style="background-color: <?= $intervention['status_color'] ?? '#ccc' ?>">
                  <?= htmlspecialchars($intervention['status_name'] ?? '-') ?>
                </span>
              </td>
              <td data-order="<?= $intervention['priority_id'] ?? 0 ?>">
                <span class="badge" style="background-color: <?= $intervention['priority_color'] ?? '#ccc' ?>">
                  <?= htmlspecialchars($intervention['priority_name'] ?? '-') ?>
                </span>
              </td>
              <td data-order="<?= isset($intervention['created_at']) ? strtotime($intervention['created_at']) : 0 ?>">
                <?= !empty($intervention['created_at'])
                  ? formatDateFrench($intervention['created_at']) . ' ' . date('H:i', strtotime($intervention['created_at']))
                  : '-' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Définir BASE_URL pour JavaScript -->
<script>
  window.BASE_URL = '<?php echo BASE_URL; ?>';
  window.csrfToken = '<?php echo $_SESSION['csrf_token']; ?>';
</script>

<!-- DataTable Persistence -->
<script src="<?php echo BASE_URL; ?>assets/js/datatable-persistence.js"></script>

<!-- Page JS -->
<script src="<?php echo BASE_URL; ?>assets/js/interventions-datatable.js"></script>

<!-- Select2 CSS -->

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?>
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
  <div class="d-flex bd-highlight mb-3 justify-content-between">
    <div class="p-2 bd-highlight">
      <h4 class="py-4 mb-6">
        <?php if ($isPreventivePage): ?>
          <i class="bi bi-shield-check me-2"></i>Interventions Préventives
        <?php else: ?>
          <i class="bi bi-tools me-2"></i>Interventions Curatives
        <?php endif; ?>
      </h4>
    </div>

    <div>
      <?php if (canModifyInterventions()): ?>
        <?php if (!$isPreventivePage): ?>
          <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#flashInterventionModal">
            <i class="bi bi-lightning-charge me-1"></i> Flash Intervention
          </button>
        <?php endif; ?>
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
<?php if (!$isPreventivePage && canModifyInterventions()): ?>
  <!-- Modale Flash Intervention -->
  <div class="modal fade" id="flashInterventionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-success text-white mb-3">
          <h5 class="modal-title"><i class="bi bi-lightning-charge me-2"></i>Flash Intervention</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Création rapide d'une intervention de type <strong>Assistance téléphonique</strong> (30 min)
          </div>
          <form id="flashInterventionForm">
            <?= csrf_field() ?>
            <div class="mb-3">
              <label class="form-label fw-bold">Client *</label>
              <select class="form-select" id="flash_client_id" name="client_id" required>
                <option value="">Sélectionner un client</option>
                <?php foreach ($clients as $client): ?>
                  <option value="<?= $client['id'] ?>">
                    <?= htmlspecialchars($client['name'] ?? '') ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Veuillez sélectionner un client</div>
            </div>
            <div class="mb-3">
              <label class="form-label fw-bold">Sujet (optionnel)</label>
              <input type="text" class="form-control" id="flash_title" name="title"
                placeholder="Ex: Problème de connexion">
              <small class="text-muted">Laissez vide pour un titre automatique</small>
            </div>
            <div class="alert alert-warning">
              <i class="bi bi-exclamation-triangle me-2"></i>
              <strong>Note :</strong> L'intervention sera créée comme <strong>incomplète</strong>.<br>
              Vous devrez compléter le lieu, le sujet et la description après création.
            </div>
          </form>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
          <button type="button" class="btn btn-success" id="confirmFlashBtn">
            <span class="spinner-border spinner-border-sm d-none" id="flashSpinner"></span>
            <i class="bi bi-lightning-charge me-1"></i> Créer l'intervention flash
          </button>
        </div>
      </div>
    </div>
  </div>

  <script>
    document.addEventListener('DOMContentLoaded', function () {
      const flashBtn = document.getElementById('confirmFlashBtn');
      const flashClient = document.getElementById('flash_client_id');
      const flashSpinner = document.getElementById('flashSpinner');

      if (!flashBtn) return;

      flashBtn.addEventListener('click', function () {
        const clientId = flashClient.value;
        if (!clientId) {
          flashClient.classList.add('is-invalid');
          flashClient.focus();
          return;
        }
        flashClient.classList.remove('is-invalid');
        flashSpinner.classList.remove('d-none');
        flashBtn.disabled = true;

        const formData = new URLSearchParams();
        formData.append('client_id', clientId);
        formData.append('title', document.getElementById('flash_title').value);
        formData.append('csrf_token', '<?= csrf_token() ?>');

        fetch('<?= BASE_URL ?>interventions/flash', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            'X-CSRF-Token': '<?= csrf_token() ?>'
          },
          body: formData
        })
          .then(r => r.json())
          .then(data => {
            if (data.success) {
              const overlay = document.createElement('div');
              overlay.style.cssText = `
          position:fixed; top:0; left:0; width:100%; height:100%;
          background:rgba(0,0,0,0.5); z-index:10000;
          display:flex; align-items:center; justify-content:center;
        `;
              overlay.innerHTML = `
          <div style="background:white; border-radius:16px; padding:30px;
                      text-align:center; min-width:400px;
                      box-shadow:0 20px 60px rgba(0,0,0,0.3);">
            <div style="background:#d4edda; border-radius:12px; padding:5px; margin-bottom:20px;">
              <i class="bi bi-check-circle-fill" style="font-size:64px; color:#28a745; display:block;"></i>
            </div>
            <h3 style="color:#155724; margin-bottom:10px;">Succès !</h3>
            <p style="color:#155724; margin-bottom:20px;">
              L'intervention rapide a été créée avec succès.
            </p>
          </div>
        `;
              document.body.appendChild(overlay);

              const modal = bootstrap.Modal.getInstance(document.getElementById('flashInterventionModal'));
              if (modal) modal.hide();

              setTimeout(() => window.location.reload(), 2000);
            } else {
              alert(data.error || 'Une erreur est survenue');
              flashSpinner.classList.add('d-none');
              flashBtn.disabled = false;
            }
          })
          .catch(err => {
            console.error('Erreur:', err);
            alert('Une erreur est survenue lors de la création flash');
            flashSpinner.classList.add('d-none');
            flashBtn.disabled = false;
          });
      });
    });
  </script>
<?php endif; ?>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controllers/PreferencesController.php';

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
  <!-- <header>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
    <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
  </header>
  <div class="card mb-4">
    <div class="card-header py-2">
      <h6 class="card-title mb-0">Filtres</h6>
    </div>
    <div class="card-body py-2">
      <form method="get" action="" id="interventionFilterForm">
        <div class="row g-2 align-items-end">
          <div class="col-12 col-md-3 col-lg-2">
            <label for="client_id" class="form-label fw-bold mb-1">
              Client
            </label>
            <select class="form-select bg-body text-body" id="client_id" name="client_id">
            </select>
          </div>
          <div class="col-12 col-md-3 col-lg-2">
            <label for="site_id" class="form-label fw-bold mb-1">
              Site
            </label>
            <select class="form-select bg-body text-body" id="site_id" name="site_id">
            </select>
          </div>
          <div class="col-12 col-md-3 col-lg-2">
            <label for="building_id" class="form-label fw-bold mb-1">
              Bâtiment
            </label>
            <select class="form-select bg-body text-body" id="building_id" name="building_id">
            </select>
          </div>
          <div class="col-12 col-md-3 col-lg-2">
            <label for="room_id" class="form-label fw-bold mb-1">
              Salle
            </label>
            <select class="form-select bg-body text-body" id="room_id" name="room_id">
            </select>
          </div>
          <div class="col-12 col-md-auto">
            <a href="<?= BASE_URL ?><?= $isPreventivePage ? 'interventions/preventives' : 'interventions/curatives' ?>"
              class="btn btn-outline-secondary">
              <i class="bi bi-x-lg me-1"></i>
              Réinitialiser
            </a>
          </div>

        </div>
      </form>
    </div>
  </div> -->
  <div class="table-responsive d-none d-md-block">
    <table id="interventionsTable" class="table table-striped table-hover">
      <thead>
        <tr>
          <th>Date de création</th>
          <th>Date prévisionnelle</th>
          <th>Référence</th>
          <th>Titre</th>
          <th>Client</th>
          <th>Site</th>
          <th>Salle</th>
          <th>Statut</th>
          <th>Priorité</th>
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
                <?= !empty($intervention['created_at'])
                  ? date('d/m/Y', strtotime($intervention['created_at']))
                  : '-' ?>
              </td>
              <td>
                <?= !empty($intervention['planned_date'])
                  ? date('d/m/Y', strtotime($intervention['planned_date']))
                  : '-' ?>
              </td>
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
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
  <div class="mobile-interventions d-block d-md-none">
    <?php if (empty($paginatedInterventions)): ?>
      <div class="text-center py-4">
        <p>Aucune intervention trouvée</p>
      </div>
    <?php else: ?>
      <?php foreach ($paginatedInterventions as $intervention): ?>
        <div class="intervention-card">
          <div class="intervention-date">
            <i class="bi bi-calendar3"></i>
            <?= !empty($intervention['created_at'])
              ? date('d/m/Y', strtotime($intervention['created_at']))
              : '-' ?>
          </div>
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

  /* =========================================================
   TOM SELECT
   ========================================================= */

  .ts-wrapper {
    width: 100%;
  }

  .ts-dropdown {
    z-index: 99999 !important;
    box-sizing: border-box !important;

    /* Taille par défaut au premier chargement */
    width: 350px !important;
    height: 300px !important;

    min-width: 250px !important;
    min-height: 50px !important;

    max-width: none !important;
    max-height: none !important;

    overflow: hidden !important;
  }

  /* Contenu du dropdown */
  .ts-dropdown .ts-dropdown-content {
    width: 100% !important;
    height: 100% !important;

    max-height: none !important;

    overflow-x: auto !important;
    overflow-y: auto !important;

    box-sizing: border-box !important;
  }

  /* Options */
  .ts-dropdown .option {
    white-space: normal !important;
    word-break: break-word;
  }

  /* Ne pas couper le dropdown */
  #filterForm,
  #filterForm .row,
  #filterForm .col-md-2,
  #filterForm .ts-wrapper {
    overflow: visible !important;
  }


  /* =========================================================
   POIGNÉE DE REDIMENSIONNEMENT
   ========================================================= */

  .filter-dropdown-resizer {
    position: absolute;

    right: 0;
    bottom: 0;

    width: 18px;
    height: 18px;

    cursor: nwse-resize;

    z-index: 100000;

    background:
      linear-gradient(135deg,
        transparent 0%,
        transparent 45%,
        #999 46%,
        #999 52%,
        transparent 53%),
      linear-gradient(135deg,
        transparent 0%,
        transparent 62%,
        #999 63%,
        #999 69%,
        transparent 70%);

    opacity: 0.7;
  }

  .filter-dropdown-resizer:hover {
    opacity: 1;
  }
</style>
<script>
  document.addEventListener('DOMContentLoaded', function () {
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
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const pageSelect = document.getElementById('mobilePageSelect');
    if (pageSelect) {
      pageSelect.addEventListener('change', function () {
        window.location.href = '<?= $baseUrlWithParams ?>' + this.value;
      });
    }
  });

  window.BASE_URL = '<?= BASE_URL ?>';
  window.csrfToken = '<?= $_SESSION['csrf_token'] ?>';
  window.serverSavedSettings = {
    interventionsTable_pageLength:
      <?= json_encode((int) getUserPreference('datatable_interventionsTable_pageLength', 10)) ?>
  };
</script>
<!-- <script>
  (function () {
    const tomSelects = {};

    function formatLocationOption(contextParts) {
      return function (data, escape) {
        const ctx = contextParts(data).filter(Boolean).join(' — ');
        return `<div>
          <span class="fw-bold">${escape(data.text)}</span>
          ${ctx ? `<br><small class="text-muted">${escape(ctx)}</small>` : ''}
        </div>`;
      };
    }

    function makeFilterDropdownResizable(fieldId, dropdown) {
      if (!dropdown) return;

      let resizer = dropdown.querySelector('.filter-dropdown-resizer');
      if (!resizer) {
        resizer = document.createElement('div');
        resizer.className = 'filter-dropdown-resizer';
        dropdown.appendChild(resizer);
      }

      const savedWidth = localStorage.getItem('filter-dropdown-width-' + fieldId);
      const savedHeight = localStorage.getItem('filter-dropdown-height-' + fieldId);

      const DEFAULT_WIDTH = 350;
      const DEFAULT_HEIGHT = 300;

      dropdown.style.setProperty('width', (savedWidth || DEFAULT_WIDTH) + 'px', 'important');
      dropdown.style.setProperty('height', (savedHeight || DEFAULT_HEIGHT) + 'px', 'important');

      if (resizer.dataset.initialized === 'true') {
        return;
      }
      resizer.dataset.initialized = 'true';

      resizer.addEventListener('mousedown', function (event) {
        event.preventDefault();
        event.stopPropagation();

        const startX = event.clientX;
        const startY = event.clientY;
        const startWidth = dropdown.offsetWidth;
        const startHeight = dropdown.offsetHeight;

        function onMouseMove(moveEvent) {
          const dx = moveEvent.clientX - startX;
          const dy = moveEvent.clientY - startY;

          let newWidth = startWidth + dx;
          let newHeight = startHeight + dy;

          newWidth = Math.max(250, newWidth);
          newHeight = Math.max(50, newHeight);

          const rect = dropdown.getBoundingClientRect();
          const maxWidth = window.innerWidth - rect.left - 20;
          const maxHeight = window.innerHeight - rect.top - 20;

          newWidth = Math.min(newWidth, maxWidth);
          newHeight = Math.min(newHeight, maxHeight);

          dropdown.style.setProperty('width', newWidth + 'px', 'important');
          dropdown.style.setProperty('height', newHeight + 'px', 'important');
        }

        function onMouseUp() {
          localStorage.setItem('filter-dropdown-width-' + fieldId, Math.round(dropdown.offsetWidth));
          localStorage.setItem('filter-dropdown-height-' + fieldId, Math.round(dropdown.offsetHeight));
          document.removeEventListener('mousemove', onMouseMove);
          document.removeEventListener('mouseup', onMouseUp);
          document.body.style.userSelect = '';
          document.body.style.cursor = '';
        }

        document.body.style.userSelect = 'none';
        document.body.style.cursor = 'nwse-resize';
        document.addEventListener('mousemove', onMouseMove);
        document.addEventListener('mouseup', onMouseUp);
      });
    }

    function initFilterTomSelect(fieldId, searchFields, renderFn) {
      if (tomSelects[fieldId]) {
        tomSelects[fieldId].destroy();
      }

      tomSelects[fieldId] = new TomSelect('#' + fieldId, {
        valueField: 'value',
        labelField: 'text',
        searchField: searchFields,
        placeholder: 'Rechercher...',
        allowEmptyOption: true,
        render: {
          option: renderFn,
          item: (data, escape) => `<div>${escape(data.text)}</div>`
        },
        onChange: submitInterventionFilters,
        onDropdownOpen: function (dropdown) {
          requestAnimationFrame(() => {
            if (!dropdown) return;
            dropdown.style.setProperty('box-sizing', 'border-box', 'important');
            dropdown.style.setProperty('overflow', 'hidden', 'important');
            makeFilterDropdownResizable(fieldId, dropdown);
          });
        }
      });
    }

    function loadOptionsInto(fieldId, url, mapFn, preserveSelection) {
      fetch(url)
        .then(res => res.json())
        .then(rows => {
          if (!Array.isArray(rows)) return;
          const ts = tomSelects[fieldId];
          ts.clearOptions();
          ts.addOption({ value: '', text: 'Tous' });
          rows.forEach(r => ts.addOption(mapFn(r)));
          ts.refreshOptions(false);
          if (preserveSelection) ts.setValue(preserveSelection, true);
        })
        .catch(err => console.error('Erreur chargement ' + fieldId + ':', err));
    }

    function initAllInterventionFilters() {
      const currentValues = {
        client_id: <?= json_encode($_GET['client_id'] ?? '') ?>,
        site_id: <?= json_encode($_GET['site_id'] ?? '') ?>,
        building_id: <?= json_encode($_GET['building_id'] ?? '') ?>,
        room_id: <?= json_encode($_GET['room_id'] ?? '') ?>,
      };
      const baseUrl = window.BASE_URL;

      initFilterTomSelect('client_id', ['text'], (data, escape) => `<div>${escape(data.text)}</div>`);
      {
        const params = new URLSearchParams();
        if (currentValues.site_id) params.set('site_id', currentValues.site_id);
        if (currentValues.building_id) params.set('building_id', currentValues.building_id);
        if (currentValues.room_id) params.set('room_id', currentValues.room_id);
        loadOptionsInto('client_id', baseUrl + 'interventions/get_all_clients?' + params.toString(),
          r => ({ value: r.id, text: r.name }), currentValues.client_id);
      }

      initFilterTomSelect('site_id', ['text', 'client_name'], formatLocationOption(d => [d.client_name]));
      {
        const params = new URLSearchParams();
        if (currentValues.client_id) params.set('client_id', currentValues.client_id);
        if (currentValues.building_id) params.set('building_id', currentValues.building_id);
        if (currentValues.room_id) params.set('room_id', currentValues.room_id);
        loadOptionsInto('site_id', baseUrl + 'interventions/get_all_sites?' + params.toString(),
          r => ({ value: r.id, text: r.name, client_id: r.client_id, client_name: r.client_name }), currentValues.site_id);
      }

      initFilterTomSelect('building_id', ['text', 'site_name', 'client_name'], formatLocationOption(d => [d.client_name, d.site_name]));
      {
        const params = new URLSearchParams();
        if (currentValues.client_id) params.set('client_id', currentValues.client_id);
        if (currentValues.site_id) params.set('site_id', currentValues.site_id);
        if (currentValues.room_id) params.set('room_id', currentValues.room_id);
        loadOptionsInto('building_id', baseUrl + 'interventions/get_all_buildings?' + params.toString(),
          r => ({ value: r.id, text: r.name, site_id: r.site_id, site_name: r.site_name, client_id: r.client_id, client_name: r.client_name }), currentValues.building_id);
      }

      initFilterTomSelect('room_id', ['text', 'building_name', 'site_name', 'client_name'], formatLocationOption(d => [d.client_name, d.site_name, d.building_name]));
      {
        const params = new URLSearchParams();
        if (currentValues.client_id) params.set('client_id', currentValues.client_id);
        if (currentValues.site_id) params.set('site_id', currentValues.site_id);
        if (currentValues.building_id) params.set('building_id', currentValues.building_id);
        loadOptionsInto('room_id', baseUrl + 'interventions/get_all_rooms?' + params.toString(),
          r => ({ value: r.id, text: r.name, building_id: r.building_id, building_name: r.building_name, site_id: r.site_id, site_name: r.site_name, client_id: r.client_id, client_name: r.client_name }), currentValues.room_id);
      }
    }

    function submitInterventionFilters() {
      const clientId = document.getElementById('client_id').value;
      const siteId = document.getElementById('site_id').value;
      const buildingId = document.getElementById('building_id').value;
      const roomId = document.getElementById('room_id').value;
      const basePath = <?= json_encode($isPreventivePage ? 'interventions/preventives' : 'interventions/curatives') ?>;
      const params = [];
      if (clientId) params.push('client_id=' + clientId);
      if (siteId) params.push('site_id=' + siteId);
      if (buildingId) params.push('building_id=' + buildingId);
      if (roomId) params.push('room_id=' + roomId);
      window.location.href = window.BASE_URL + basePath + (params.length ? '?' + params.join('&') : '');
    }

    document.addEventListener('DOMContentLoaded', initAllInterventionFilters);
  })();
</script> -->
<script src="<?= BASE_URL ?>assets/js/interventions-datatable.js"></script>
<script src="<?= BASE_URL ?>assets/js/datatable-persistence.js"></script>

<?php if (!$isPreventivePage && canModifyInterventions()): ?>
  <div class="modal fade" id="flashInterventionModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header text-white mb-3">
          <h5 class="modal-title mb-3">Flash Intervention</h5>
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
  <script>
    document.addEventListener('DOMContentLoaded', function () {
      document.querySelectorAll('.modal').forEach(function (modal) {
        modal.addEventListener('hidden.bs.modal', function () {
          const dialog = modal.querySelector('.modal-dialog');
          if (dialog) {
            dialog.style.position = '';
            dialog.style.left = '';
            dialog.style.top = '';
            dialog.style.margin = '';
            dialog.style.width = '';
            dialog.style.maxWidth = '';
          }
        });

        modal.addEventListener('shown.bs.modal', function () {
          const dialog = modal.querySelector('.modal-dialog');
          const header = modal.querySelector('.modal-header');
          if (!dialog || !header) return;
          if (header.dataset.draggable) return;
          header.dataset.draggable = 'true';

          header.style.cursor = 'grab';

          let isDragging = false;
          let startX, startY, startLeft, startTop;

          header.addEventListener('mousedown', function (e) {
            if (e.target.closest('button')) return;

            isDragging = true;
            header.style.cursor = 'grabbing';

            const rect = dialog.getBoundingClientRect();
            startX = e.clientX;
            startY = e.clientY;
            startLeft = rect.left;
            startTop = rect.top;
            dialog.style.width = rect.width + 'px';
            dialog.style.maxWidth = 'none';
            dialog.style.position = 'fixed';
            dialog.style.left = startLeft + 'px';
            dialog.style.top = startTop + 'px';
            dialog.style.margin = '0';
          });

          document.addEventListener('mousemove', function (e) {
            if (!isDragging) return;
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            dialog.style.left = (startLeft + dx) + 'px';
            dialog.style.top = (startTop + dy) + 'px';
          });

          document.addEventListener('mouseup', function () {
            if (isDragging) {
              isDragging = false;
              header.style.cursor = 'grab';
            }
          });
        });

      });
    });
  </script>

<?php endif; ?>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de la liste de la documentation
 * Affiche la liste des documents regroupés par site/bâtiment/salle avec filtres
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
  header('Location: ' . BASE_URL . 'auth/login');
  exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
  'Documentation',
  'documentation'
);

// Définir la page courante pour le menu
$currentPage = 'documentation';
$hasAnyFilter = !empty($filters['client_id'])
  || !empty($filters['site_id'])
  || !empty($filters['building_id'])
  || !empty($filters['salle_id']);
// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer les données depuis le contrôleur
$documentation_list = $documentation_list ?? [];
$clients = $clients ?? [];
$sites = $sites ?? [];
$buildings = $buildings ?? [];
$salles = $salles ?? [];
$filters = $filters ?? [];

// Organiser la documentation par client/site/bâtiment/salle
$documentation_organise = [];
foreach ($documentation_list as $doc) {
  $client_id = $doc['client_nom'] ?? 'Sans client';
  $site_id = $doc['site_nom'] ?? 'Sans site';
  $building_id = $doc['building_nom'] ?? 'Sans bâtiment';
  $salle_id = $doc['salle_nom'] ?? 'Sans salle';

  if (!isset($documentation_organise[$client_id])) {
    $documentation_organise[$client_id] = [];
  }
  if (!isset($documentation_organise[$client_id][$site_id])) {
    $documentation_organise[$client_id][$site_id] = [];
  }
  if (!isset($documentation_organise[$client_id][$site_id][$building_id])) {
    $documentation_organise[$client_id][$site_id][$building_id] = [];
  }
  if (!isset($documentation_organise[$client_id][$site_id][$building_id][$salle_id])) {
    $documentation_organise[$client_id][$site_id][$building_id][$salle_id] = [];
  }

  $documentation_organise[$client_id][$site_id][$building_id][$salle_id][] = $doc;
}
?>

<style>
  .documentation-row {
    background-color: var(--bs-body-bg);
  }

  .documentation-row .card {
    border: 1px solid var(--bs-border-color);
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
  }

  .documentation-list .list-group-item {
    border: 1px solid var(--bs-border-color);
    border-radius: 0.375rem;
    transition: all 0.2s ease-in-out;
    background-color: var(--bs-body-bg);
    color: var(--bs-body-color);
    padding: 0.75rem;
    margin-bottom: 0.5rem;
  }

  .documentation-list .list-group-item:hover {
    background-color: var(--bs-secondary-bg);
    border-color: var(--bs-primary);
    box-shadow: 0 2px 4px rgba(var(--bs-primary-rgb), 0.15);
  }

  .btn-action {
    transition: all 0.2s ease-in-out;
  }

  .btn-action:hover {
    transform: scale(1.05);
  }

  .documentation-row td {
    border-top: none;
    border-bottom: 1px solid var(--bs-border-color);
  }

  .min-w-0 {
    min-width: 0;
  }

  .documentation-list .btn-group {
    flex-shrink: 0;
  }

  .file-icon {
    font-size: 1.2rem;
    margin-right: 0.5rem;
  }

  .file-type-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
  }

  .document-link {
    color: var(--bs-primary);
    text-decoration: none;
    font-weight: 500;
  }

  .document-link:hover {
    color: var(--bs-primary);
    text-decoration: underline;
  }
</style>
<header>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
</header>
<div class="container-fluid flex-grow-1 container-p-y">
  <!-- En-tête avec titre et bouton d'ajout -->
  <div class="row mb-4">
    <div class="col-12">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h4 class="fw-bold mb-1">
            <i class="bi bi-file-text me-2 me-1"></i>Documentation
          </h4>
          <p class="text-muted mb-0">Gestion et consultation de la documentation par site, bâtiment et salle</p>
        </div>
        <div class="d-flex gap-2">
          <?php
          if (!empty($filters['client_id'])) {
            $clientId = $filters['client_id'];
            echo '<a href="' . BASE_URL . 'clients/view/' . $clientId . '" class="btn btn-secondary me-2">';
            echo '<i class="bi bi-arrow-left me-1"></i> Retour au client';
            echo '</a>';
          }

          $addParams = [];
          if (!empty($filters['client_id'])) {
            $addParams['client_id'] = $filters['client_id'];
          }
          if (!empty($filters['site_id'])) {
            $addParams['site_id'] = $filters['site_id'];
          }
          if (!empty($filters['building_id'])) {
            $addParams['building_id'] = $filters['building_id'];
          }
          if (!empty($filters['salle_id'])) {
            $addParams['salle_id'] = $filters['salle_id'];
          }

          $addUrl = BASE_URL . 'documentation/add';
          if (!empty($addParams)) {
            $addUrl .= '?' . http_build_query($addParams);
          }
          ?>
          <a href="<?= $addUrl ?>" class="btn btn-primary">
            <i class="bi bi-plus me-2 me-1"></i>Ajouter un Document
          </a>
        </div>
      </div>
    </div>
  </div>
  <div class="card mb-4">
    <div class="card-body py-2">
      <div class="input-group">
        <span class="input-group-text bg-primary text-white">
          <i class="bi bi-search"></i>
        </span>
        <input type="text" class="form-control form-control-lg" id="globalSearch"
          style="border-top-right-radius:8px; border-bottom-right-radius:8px;"
          placeholder="Rechercher dans TOUTE la documentation (nom, description, client, site, salle...)"
          autocomplete="off">
        <button class="btn btn-outline-secondary" type="button" id="clearGlobalSearch" style="display:none;">
          <i class="bi bi-x-lg"></i>
        </button>
      </div>
      <div class="mt-2" id="globalSearchInfo" style="display:none;"></div>
    </div>
  </div>
  <!-- Filtres -->
  <div class="card mb-4">
    <div class="card-header py-2">
      <h6 class="card-title mb-0">Filtres</h6>
    </div>
    <div class="card-body py-2">
      <form method="get" action="" id="filterForm">
        <div class="row g-3 align-items-end">
          <div class="col-md-2">
            <label for="client_id" class="form-label fw-bold mb-0">Client</label>
            <select class="form-select bg-body text-body" id="client_id" name="client_id">
              <option value=""></option>
              <?php if (isset($clients) && is_array($clients)): ?>
                <?php foreach ($clients as $client): ?>
                  <option value="<?= $client['id'] ?>" <?= ($filters['client_id'] ?? '') == $client['id'] ? 'selected' : '' ?>>
                    <?= h($client['name']) ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>

          <div class="col-md-2">
            <label for="site_id" class="form-label fw-bold mb-0">Site</label>
            <select class="form-select bg-body text-body" id="site_id" name="site_id">
            </select>
          </div>

          <div class="col-md-2">
            <label for="building_id" class="form-label fw-bold mb-0">Bâtiment</label>
            <select class="form-select bg-body text-body" id="building_id" name="building_id">
            </select>
          </div>

          <div class="col-md-2">
            <label for="salle_id" class="form-label fw-bold mb-0">Salle</label>
            <select class="form-select bg-body text-body" id="salle_id" name="salle_id">
            </select>
          </div>

          <div class="col-md-4 d-flex justify-content-end">
            <a href="<?= BASE_URL ?>documentation" class="btn btn-outline-secondary">
              <i class="bi bi-x-lg me-1"></i>Réinitialiser
            </a>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Liste de la documentation organisée -->
  <div id="documentationResultsContainer">
    <?php if (!$hasAnyFilter): ?>
      <div class="card">
        <div class="card-body text-center py-5">
          <i class="fas fa-filter fa-3x text-muted mb-3"></i>
          <h5 class="text-muted">Sélectionnez un filtre pour voir la documentation</h5>
          <p class="text-muted mb-3">Choisissez un client, un site, un bâtiment ou une salle
            dans le filtre ci-dessus pour afficher la documentation correspondante.</p>
          <div class="row justify-content-center">
            <div class="col-md-6">
              <div class="alert alert-info">
                <i class="bi bi-info-circle me-2 me-1"></i>
                <strong>Astuce :</strong> chaque filtre fonctionne indépendamment —
                vous pouvez chercher directement une salle sans connaître son
                client ni son site.
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php elseif (empty($documentation_organise)): ?>
      <div class="card">
        <div class="card-body text-center py-5">
          <i class="bi bi-file-text fa-3x text-muted mb-3 me-1"></i>
          <h5 class="text-muted">Aucune documentation trouvée</h5>
          <p class="text-muted mb-3">Aucun document ne correspond aux critères sélectionnés.</p>
          <a href="<?= $addUrl ?>" class="btn btn-primary">
            <i class="bi bi-plus me-2 me-1"></i>Ajouter un Document
          </a>
        </div>
      </div>
    <?php else: ?>
      <?php foreach ($documentation_organise as $client_nom => $sites_data): ?>
        <div class="card mb-4">
          <div class="card-header bg-body-secondary">
            <h5 class="card-title mb-0">
              <i class="bi bi-building me-2 text-primary me-1"></i>
              <?= h($client_nom) ?>
            </h5>
          </div>
          <div class="card-body p-0">
            <?php foreach ($sites_data as $site_nom => $buildings_data): ?>
              <div class="border-bottom">
                <div class="p-3 bg-body-secondary bg-opacity-10">
                  <h6 class="mb-0">
                    <i class="bi bi-geo-alt me-2 text-success me-1"></i>
                    <?= h($site_nom) ?>
                  </h6>
                </div>
                <?php foreach ($buildings_data as $building_nom => $salles_data): ?>
                  <div class="border-bottom">
                    <div class="p-3 bg-body-secondary bg-opacity-5">
                      <h6 class="mb-0">
                        <i class="bi bi-building me-2 text-warning me-1"></i>
                        <?= h($building_nom) ?>
                      </h6>
                    </div>
                    <?php foreach ($salles_data as $salle_nom => $documents): ?>
                      <div class="border-bottom">
                        <div class="p-3">
                          <h6 class="mb-3">
                            <i class="bi bi-door-open me-2 text-info me-1"></i>
                            <?= h($salle_nom) ?>
                            <span class="badge bg-secondary ms-2">
                              <?= count($documents) ?> document(s)
                            </span>
                          </h6>

                          <div class="table-responsive">
                            <table class="table table-hover">
                              <thead class="table-light">
                                <tr>
                                  <th style="width: 40%;">Document</th>
                                  <th style="width: 15%;">Type</th>
                                  <th style="width: 10%;">Taille</th>
                                  <th style="width: 10%;">Visibilité</th>
                                  <th style="width: 15%;">Date</th>
                                  <th style="width: 10%;">User</th>
                                  <th style="width: 10%;">Actions</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php foreach ($documents as $doc): ?>
                                  <tr>
                                    <td>
                                      <div class="d-flex align-items-center">
                                        <i class="<?= getFileIcon($doc['type_fichier'] ?? '') ?> text-primary me-2"></i>
                                        <div class="flex-grow-1 min-w-0">
                                          <div class="fw-bold text-primary d-flex align-items-center gap-2">
                                            <span class="editable-name" data-id="<?= $doc['id'] ?>"
                                              data-current-name="<?= h($doc['nom_personnalise'] ?? $doc['nom_fichier'] ?? 'Document sans nom') ?>"
                                              style="cursor: pointer;" title="Double-clic pour modifier">
                                              <?= h($doc['nom_personnalise'] ?? $doc['nom_fichier'] ?? 'Document sans nom') ?>
                                            </span>
                                            <button type="button" class="btn btn-sm btn-link p-0 edit-name-btn"
                                              data-id="<?= $doc['id'] ?>"
                                              data-current-name="<?= h($doc['nom_personnalise'] ?? $doc['nom_fichier'] ?? 'Document sans nom') ?>"
                                              title="Modifier le nom" style="font-size: 0.75rem; line-height: 1;">
                                              <i class="bi bi-pencil"></i>
                                            </button>
                                          </div>
                                          <?php if (!empty($doc['nom_personnalise']) && $doc['nom_personnalise'] !== $doc['nom_fichier']): ?>
                                            <small class="text-muted">
                                              <i class="bi bi-file-earmark me-1"></i>
                                              <?= h($doc['nom_fichier']) ?>
                                            </small>
                                          <?php endif; ?>
                                          <?php if (!empty($doc['commentaire'])): ?>
                                            <small class="text-muted d-block">
                                              <i class="bi bi-chat-text me-1"></i>
                                              <?= h($doc['commentaire']) ?>
                                            </small>
                                          <?php endif; ?>
                                        </div>
                                      </div>
                                    </td>
                                    <td>
                                      <?php if (!empty($doc['type_fichier'])): ?>
                                        <span class="badge bg-info">
                                          <?= strtoupper($doc['type_fichier']) ?>
                                        </span>
                                      <?php endif; ?>
                                    </td>
                                    <td>
                                      <?php if (!empty($doc['taille_fichier']) && $doc['taille_fichier'] > 0): ?>
                                        <small class="text-muted">
                                          <?= formatFileSize($doc['taille_fichier']) ?>
                                        </small>
                                      <?php endif; ?>
                                    </td>
                                    <td>
                                      <?php if ($doc['masque_client']): ?>
                                        <span class="badge bg-warning">
                                          <i class="bi bi-eye-slash me-1"></i>Masqué
                                        </span>
                                      <?php else: ?>
                                        <span class="badge bg-success">
                                          <i class="bi bi-eye me-1"></i>Visible
                                        </span>
                                      <?php endif; ?>
                                    </td>
                                    <td>
                                      <small class="text-muted">
                                        <i class="bi bi-calendar me-1"></i>
                                        <?= formatDateFrench($doc['date_creation'] ?? '') ?>
                                      </small>
                                    </td>
                                    <td>
                                      <?php if (!empty($doc['uploader_name'])): ?>
                                        <small class="text-muted">
                                          <i class="bi bi-person me-1"></i>
                                          <?= h($doc['uploader_name']) ?>
                                        </small>
                                      <?php endif; ?>
                                    </td>
                                    <td>
                                      <div class="d-flex gap-1">
                                        <?php
                                        $fileType = strtolower($doc['type_fichier'] ?? '');
                                        $canPreview = in_array($fileType, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp']);
                                        ?>
                                        <?php if ($canPreview && !empty($doc['chemin_fichier'])): ?>
                                          <button type="button" class="btn btn-sm btn-outline-info btn-action" title="Aperçu"
                                            data-bs-toggle="modal" data-bs-target="#previewModal<?= $doc['id'] ?>">
                                            <i class="bi bi-eye"></i>
                                          </button>
                                        <?php endif; ?>

                                        <?php if (!empty($doc['chemin_fichier'])): ?>
                                          <a href="<?= BASE_URL ?>documentation/download/<?= $doc['id'] ?>"
                                            class="btn btn-sm btn-outline-success btn-action" title="Télécharger">
                                            <i class="bi bi-download"></i>
                                          </a>
                                        <?php endif; ?>

                                        <?php if (canManageDocumentation()): ?>
                                          <a href="<?= BASE_URL ?>documentation/toggleAttachmentVisibility/<?= $doc['id'] ?>"
                                            class="btn btn-sm btn-outline-warning btn-action"
                                            title="<?= ($doc['masque_client'] ?? 0) == 1 ? 'Rendre visible aux clients' : 'Masquer aux clients' ?>"
                                            onclick="return confirm('<?= ($doc['masque_client'] ?? 0) == 1 ? 'Rendre ce document visible aux clients ?' : 'Masquer ce document aux clients ?' ?>');">
                                            <i class="bi <?= ($doc['masque_client'] ?? 0) == 1 ? 'bi-eye' : 'bi-eye-slash' ?>"></i>
                                          </a>
                                        <?php endif; ?>

                                        <?php if (canDeleteDocumentation()): ?>
                                          <button type="button" class="btn btn-sm btn-outline-danger btn-action delete-document"
                                            data-id="<?= (int) $doc['id'] ?>"
                                            data-name="<?= h($doc['nom_personnalise'] ?? $doc['nom_fichier'] ?? 'Document') ?>">
                                            <i class="bi bi-trash"></i>
                                          </button>
                                        <?php endif; ?>
                                      </div>
                                    </td>
                                  </tr>

                                  <!-- Modal d'aperçu pour ce document -->
                                  <?php if ($canPreview && !empty($doc['chemin_fichier'])): ?>
                                    <div class="modal fade" id="previewModal<?= $doc['id'] ?>" tabindex="-1" aria-hidden="true">
                                      <div class="modal-dialog modal-xl">
                                        <div class="modal-content">
                                          <div class="modal-header">
                                            <h5 class="modal-title">
                                              <?= h($doc['nom_personnalise'] ?? $doc['nom_fichier']) ?>
                                            </h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal"
                                              aria-label="Close"></button>
                                          </div>
                                          <div class="modal-body p-0">
                                            <div class="preview-container" id="previewContainer<?= $doc['id'] ?>">
                                              <?php if ($fileType === 'pdf'): ?>
                                                <!-- Détection iOS côté JS, rendu différent -->
                                                <div class="pdf-preview-wrapper"
                                                  data-preview-url="<?= BASE_URL ?>documentation/preview/<?= $doc['id'] ?>"
                                                  data-download-url="<?= BASE_URL ?>documentation/download/<?= $doc['id'] ?>"
                                                  data-filename="<?= h($doc['nom_personnalise'] ?? $doc['nom_fichier']) ?>"
                                                  style="height: 75vh;">
                                                </div>
                                              <?php elseif (in_array($fileType, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                                <div class="text-center p-3" style="max-height: 75vh; overflow: auto;">
                                                  <img src="<?= BASE_URL ?>documentation/preview/<?= $doc['id'] ?>" class="img-fluid"
                                                    style="max-width: 100%; cursor: zoom-in;"
                                                    alt="<?= h($doc['nom_personnalise'] ?? $doc['nom_fichier']) ?>"
                                                    onerror="handleImageError(this, <?= $doc['id'] ?>, '<?= h($doc['nom_fichier']) ?>')">
                                                </div>
                                              <?php endif; ?>
                                            </div>
                                          </div>
                                          <div class="modal-footer">
                                            <a href="<?= BASE_URL ?>documentation/download/<?= $doc['id'] ?>" class="btn btn-primary"
                                              target="_blank">
                                              <i class="bi bi-download me-1"></i> Télécharger
                                            </a>
                                            <?php if ($fileType === 'pdf'): ?>
                                              <a href="<?= BASE_URL ?>documentation/preview/<?= $doc['id'] ?>"
                                                class="btn btn-outline-secondary" target="_blank">
                                                <i class="bi bi-arrows-fullscreen me-1"></i> Agrandir
                                              </a>
                                            <?php endif; ?>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                          </div>
                                        </div>
                                      </div>
                                    </div>
                                  <?php endif; ?>
                                <?php endforeach; ?>
                              </tbody>
                            </table>
                          </div>
                        </div>
                      </div>
                    <?php endforeach; ?>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

<script>
  const baseUrl = '<?= BASE_URL ?>';

  const tomSelectsDoc = {};

  function formatDocLocationOption(contextParts) {
    return function (data, escape) {
      const ctx = contextParts(data).filter(Boolean).join(' — ');
      return `<div>
      <span class="fw-bold">${escape(data.text)}</span>
      ${ctx ? `<br><small class="text-muted">${escape(ctx)}</small>` : ''}
    </div>`;
    };
  }

  function initDocFilterTomSelect(fieldId, searchFields, renderFn) {
    if (tomSelectsDoc[fieldId]) { tomSelectsDoc[fieldId].destroy(); }
    tomSelectsDoc[fieldId] = new TomSelect('#' + fieldId, {
      valueField: 'value',
      labelField: 'text',
      searchField: searchFields,
      placeholder: 'Rechercher...',
      allowEmptyOption: true,
      render: {
        option: renderFn,
        item: (data, escape) => `<div>${escape(data.text)}</div>`
      },
      onChange: submitDocFilters
    });
  }

  function loadDocOptionsInto(fieldId, url, mapFn, preserveSelection) {
    fetch(url)
      .then(res => res.json())
      .then(rows => {
        if (!Array.isArray(rows)) return;
        const ts = tomSelectsDoc[fieldId];
        ts.clearOptions();
        ts.addOption({ value: '', text: 'Tous' });
        rows.forEach(r => ts.addOption(mapFn(r)));
        ts.refreshOptions(false);
        if (preserveSelection) ts.setValue(preserveSelection, true);
      })
      .catch(err => console.error('Erreur chargement ' + fieldId + ':', err));
  }

  function submitDocFilters() {
    const clientId = document.getElementById('client_id').value;
    const siteId = document.getElementById('site_id').value;
    const buildingId = document.getElementById('building_id').value;
    const salleId = document.getElementById('salle_id').value;
    let url = baseUrl + 'documentation?';
    const params = [];
    if (clientId) params.push('client_id=' + clientId);
    if (siteId) params.push('site_id=' + siteId);
    if (buildingId) params.push('building_id=' + buildingId);
    if (salleId) params.push('salle_id=' + salleId);
    window.location.href = url + params.join('&');
  }

  function initAllDocFilters() {
    const currentValues = {
      client_id: document.getElementById('client_id').value,
      site_id: '<?= h($filters['site_id'] ?? '') ?>',
      building_id: '<?= h($filters['building_id'] ?? '') ?>',
      salle_id: '<?= h($filters['salle_id'] ?? '') ?>',
    };

    initDocFilterTomSelect('client_id', ['text'], (data, escape) =>
      `<div>${escape(data.text)}</div>`);

    initDocFilterTomSelect('site_id', ['text', 'client_name'],
      formatDocLocationOption(d => [d.client_name]));
    loadDocOptionsInto('site_id', baseUrl + 'documentation/get_all_sites', r => ({
      value: r.id, text: r.name, client_id: r.client_id, client_name: r.client_name
    }), currentValues.site_id);

    initDocFilterTomSelect('building_id', ['text', 'site_name', 'client_name'],
      formatDocLocationOption(d => [d.client_name, d.site_name]));
    loadDocOptionsInto('building_id', baseUrl + 'documentation/get_all_buildings', r => ({
      value: r.id, text: r.name, site_id: r.site_id, site_name: r.site_name,
      client_id: r.client_id, client_name: r.client_name
    }), currentValues.building_id);

    initDocFilterTomSelect('salle_id', ['text', 'building_name', 'site_name', 'client_name'],
      formatDocLocationOption(d => [d.client_name, d.site_name, d.building_name]));
    loadDocOptionsInto('salle_id', baseUrl + 'documentation/get_all_rooms', r => ({
      value: r.id, text: r.name, building_id: r.building_id, building_name: r.building_name,
      site_id: r.site_id, site_name: r.site_name, client_id: r.client_id, client_name: r.client_name
    }), currentValues.salle_id);
  }
  function handleImageError(img, attachmentId, fileName) {
    const container = img.parentElement;
    container.innerHTML = `
        <div class="alert alert-warning text-center">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Impossible d'afficher l'aperçu de l'image</strong><br>
            <small class="text-muted">${fileName}</small><br><br>
            <a href="<?= BASE_URL ?>documentation/download/${attachmentId}" 
               class="btn btn-sm btn-outline-primary" 
               target="_blank">
                <i class="bi bi-download me-1"></i> Télécharger le fichier
            </a>
        </div>
    `;
  }

  function handleIframeLoad(iframe) {
    iframe.style.minHeight = '500px';
  }

  function confirmDeleteDocument(documentId, documentName) {
    const safeName = documentName || 'ce document';
    if (confirm(`Êtes-vous sûr de vouloir supprimer "${safeName}" ?\n\nCette action est irréversible.`)) {
      window.location.href = `${baseUrl}documentation/delete/${documentId}`;
    }
  }

  function editDocumentName(element) {
    const currentName = element.getAttribute('data-current-name');
    const docId = element.getAttribute('data-id');
    const span = element;
    const parent = span.parentElement;
    const editBtn = parent.querySelector('.edit-name-btn');

    if (parent.querySelector('input.editing-name-input')) {
      return;
    }

    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control form-control-sm editing-name-input';
    input.value = currentName;
    input.style.minWidth = '200px';
    input.style.display = 'inline-block';

    span.style.display = 'none';
    if (editBtn) editBtn.style.display = 'none';

    parent.insertBefore(input, span.nextSibling);
    input.focus();
    input.select();

    const saveEdit = () => {
      const newName = input.value.trim();
      if (newName === currentName) {
        input.remove();
        span.style.display = '';
        if (editBtn) editBtn.style.display = '';
        return;
      }

      input.disabled = true;
      const nomToSend = newName || '';
      fetch('<?= BASE_URL ?>documentation/updateName', {
        method: 'POST',
        headers: {
          'X-CSRF-Token': '<?= csrf_token() ?>',
          'Content-Type': 'application/x-www-form-urlencoded',
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: `attachment_id=${docId}&nom_personnalise=${encodeURIComponent(nomToSend)}`
      })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            const displayName = data.display_name || newName || currentName;
            span.setAttribute('data-current-name', displayName);
            span.textContent = displayName;
            if (editBtn) editBtn.setAttribute('data-current-name', displayName);
            input.remove();
            span.style.display = '';
            if (editBtn) editBtn.style.display = '';
            window.location.reload();
          } else {
            input.disabled = false;
            alert('Erreur : ' + (data.error || 'Erreur inconnue'));
            input.focus();
          }
        })
        .catch(error => {
          console.error('Erreur:', error);
          input.disabled = false;
          alert('Erreur de connexion');
          input.focus();
        });
    };

    const cancelEdit = () => {
      input.remove();
      span.style.display = '';
      if (editBtn) editBtn.style.display = '';
    };

    input.addEventListener('blur', saveEdit);
    input.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        e.preventDefault();
        input.blur();
      } else if (e.key === 'Escape') {
        e.preventDefault();
        cancelEdit();
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.delete-document').forEach(btn => {
      btn.addEventListener('click', function () {
        confirmDeleteDocument(this.dataset.id, this.dataset.name);
      });
    });

    document.querySelectorAll('.editable-name').forEach(element => {
      element.addEventListener('dblclick', function () {
        editDocumentName(this);
      });
    });

    document.querySelectorAll('.edit-name-btn').forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        const span = this.parentElement.querySelector('.editable-name');
        if (span) {
          editDocumentName(span);
        }
      });
    });
  });
  // --- Initialisation des previews PDF selon l'appareil ---
  function isIOS() {
    return /iPad|iPhone|iPod/.test(navigator.userAgent) && !window.MSStream;
  }

  function isMobile() {
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
  }

  document.addEventListener('DOMContentLoaded', function () {

    document.querySelectorAll('.pdf-preview-wrapper').forEach(function (wrapper) {
      const previewUrl = wrapper.dataset.previewUrl;
      const downloadUrl = wrapper.dataset.downloadUrl;
      const filename = wrapper.dataset.filename;

      if (isIOS()) {
        wrapper.innerHTML = `
        <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; height:100%; padding:2rem; text-align:center; background:#f8f9fa;">
          <div style="font-size:3rem; margin-bottom:1rem;">📄</div>
          <p style="font-size:1rem; color:#6c757d; margin-bottom:1.5rem;">
            L'aperçu PDF n'est pas disponible sur iOS.<br>
            Ouvrez le fichier dans un nouvel onglet pour le consulter.
          </p>
          <a href="${previewUrl}" target="_blank" class="btn btn-primary">
            <i class="bi bi-box-arrow-up-right me-1"></i> Ouvrir le PDF
          </a>
          <a href="${downloadUrl}" class="btn btn-outline-secondary mt-2">
            <i class="bi bi-download me-1"></i> Télécharger
          </a>
        </div>`;
      } else {
        wrapper.innerHTML = `
        <iframe 
          src="${previewUrl}#toolbar=1&navpanes=0&scrollbar=1&zoom=page-fit"
          width="100%"
          height="100%"
          style="min-height:75vh; border:none; display:block;"
          title="${filename}">
          <p>Votre navigateur ne supporte pas l'aperçu PDF. 
            <a href="${downloadUrl}">Téléchargez le fichier</a>.
          </p>
        </iframe>`;
      }
    });

    document.querySelectorAll('.preview-container img').forEach(function (img) {
      let zoomed = false;
      img.addEventListener('click', function () {
        if (zoomed) {
          this.style.maxWidth = '100%';
          this.style.cursor = 'zoom-in';
          this.style.transform = '';
          zoomed = false;
        } else {
          this.style.maxWidth = 'none';
          this.style.cursor = 'zoom-out';
          this.style.transform = 'scale(1)';
          zoomed = true;
        }
      });
    });

    document.querySelectorAll('.delete-document').forEach(btn => {
      btn.addEventListener('click', function () {
        confirmDeleteDocument(this.dataset.id, this.dataset.name);
      });
    });

    document.querySelectorAll('.editable-name').forEach(element => {
      element.addEventListener('dblclick', function () {
        editDocumentName(this);
      });
    });

    document.querySelectorAll('.edit-name-btn').forEach(btn => {
      btn.addEventListener('click', function (e) {
        e.stopPropagation();
        const span = this.parentElement.querySelector('.editable-name');
        if (span) editDocumentName(span);
      });
    });
  });
  let searchDebounceTimer = null;
  let searchAbortController = null;
  let initialDocResultsHtml = null;

  function escapeHtmlDoc(text) {
    const d = document.createElement('div');
    d.textContent = text ?? '';
    return d.innerHTML;
  }

  function formatFileSizeDoc(bytes) {
    if (!bytes) return '';
    const k = 1024, sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  function formatDateDoc(dateStr) {
    if (!dateStr) return '-';
    const d = new Date(dateStr);
    if (isNaN(d)) return dateStr;
    return d.toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric' });
  }

  function fileIconClassDoc(type) {
    const t = (type || '').toLowerCase();
    if (t === 'pdf') return 'bi-file-earmark-pdf text-danger';
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(t)) return 'bi-file-earmark-image text-info';
    if (['doc', 'docx'].includes(t)) return 'bi-file-earmark-word text-primary';
    if (['xls', 'xlsx'].includes(t)) return 'bi-file-earmark-excel text-success';
    return 'bi-file-earmark text-secondary';
  }

  function organizeDocuments(list) {
    const organise = {};
    list.forEach(d => {
      const client = d.client_nom || 'Sans client';
      const site = d.site_nom || 'Sans site';
      const building = d.building_nom || 'Sans bâtiment';
      const salle = d.salle_nom || 'Sans salle';
      organise[client] ??= {};
      organise[client][site] ??= {};
      organise[client][site][building] ??= {};
      organise[client][site][building][salle] ??= [];
      organise[client][site][building][salle].push(d);
    });
    return organise;
  }

  function renderDocumentRow(doc) {
    const fileType = (doc.type_fichier || '').toLowerCase();
    const canPreview = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp'].includes(fileType);
    const displayName = doc.nom_personnalise || doc.nom_fichier || 'Document sans nom';

    return `
      <tr>
        <td>
          <div class="d-flex align-items-center">
            <i class="bi ${fileIconClassDoc(doc.type_fichier)} me-2"></i>
            <div class="flex-grow-1 min-w-0">
              <div class="fw-bold text-primary">${escapeHtmlDoc(displayName)}</div>
              ${doc.commentaire ? `<small class="text-muted d-block"><i class="bi bi-chat-text me-1"></i>${escapeHtmlDoc(doc.commentaire)}</small>` : ''}
            </div>
          </div>
        </td>
        <td>${doc.type_fichier ? `<span class="badge bg-info">${escapeHtmlDoc(doc.type_fichier.toUpperCase())}</span>` : ''}</td>
        <td><small class="text-muted">${formatFileSizeDoc(doc.taille_fichier)}</small></td>
        <td>
          ${doc.masque_client == 1
        ? '<span class="badge bg-warning"><i class="bi bi-eye-slash me-1"></i>Masqué</span>'
        : '<span class="badge bg-success"><i class="bi bi-eye me-1"></i>Visible</span>'}
        </td>
        <td><small class="text-muted"><i class="bi bi-calendar me-1"></i>${formatDateDoc(doc.date_creation)}</small></td>
        <td><small class="text-muted">${doc.uploader_name ? escapeHtmlDoc(doc.uploader_name) : ''}</small></td>
        <td>
          <div class="d-flex gap-1">
            ${canPreview ? `<a href="${baseUrl}documentation/preview/${doc.id}" target="_blank" class="btn btn-sm btn-outline-info" title="Aperçu"><i class="bi bi-eye"></i></a>` : ''}
            <a href="${baseUrl}documentation/download/${doc.id}" class="btn btn-sm btn-outline-success" title="Télécharger"><i class="bi bi-download"></i></a>
          </div>
        </td>
      </tr>`;
  }

  function renderDocumentationSearchResults(documents, term) {
    const container = document.getElementById('documentationResultsContainer');
    const organise = organizeDocuments(documents || []);

    if (Object.keys(organise).length === 0) {
      container.innerHTML = `
        <div class="card">
          <div class="card-body text-center py-5">
            <i class="bi bi-file-text fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Aucun document trouvé</h5>
            <p class="text-muted mb-3">Aucun document ne correspond à votre recherche "<strong>${escapeHtmlDoc(term)}</strong>".</p>
          </div>
        </div>`;
      return;
    }

    let html = '';
    for (const clientNom in organise) {
      html += `<div class="card mb-4">
        <div class="card-header bg-body-secondary">
          <h5 class="card-title mb-0"><i class="bi bi-building me-2 text-primary"></i>${escapeHtmlDoc(clientNom)}</h5>
        </div>
        <div class="card-body p-0">`;

      for (const siteNom in organise[clientNom]) {
        html += `<div class="border-bottom">
          <div class="p-3 bg-body-secondary bg-opacity-10">
            <h6 class="mb-0"><i class="bi bi-geo-alt me-2 text-success"></i>${escapeHtmlDoc(siteNom)}</h6>
          </div>`;

        for (const buildingNom in organise[clientNom][siteNom]) {
          html += `<div class="border-bottom">
            <div class="p-3 bg-body-secondary bg-opacity-5">
              <h6 class="mb-0"><i class="bi bi-building me-2 text-warning"></i>${escapeHtmlDoc(buildingNom)}</h6>
            </div>`;

          for (const salleNom in organise[clientNom][siteNom][buildingNom]) {
            const docs = organise[clientNom][siteNom][buildingNom][salleNom];
            html += `<div class="border-bottom">
              <div class="p-3">
                <h6 class="mb-3"><i class="bi bi-door-open me-2 text-info"></i>${escapeHtmlDoc(salleNom)}
                  <span class="badge bg-secondary ms-2">${docs.length} document(s)</span>
                </h6>
                <div class="table-responsive">
                  <table class="table table-hover">
                    <thead class="table-light">
                      <tr>
                        <th style="width:40%;">Document</th>
                        <th style="width:15%;">Type</th>
                        <th style="width:10%;">Taille</th>
                        <th style="width:10%;">Visibilité</th>
                        <th style="width:15%;">Date</th>
                        <th style="width:10%;">User</th>
                        <th style="width:10%;">Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      ${docs.map(renderDocumentRow).join('')}
                    </tbody>
                  </table>
                </div>
              </div>
            </div>`;
          }
          html += `</div>`;
        }
        html += `</div>`;
      }
      html += `</div></div>`;
    }

    container.innerHTML = html;
  }

  function updateDocSearchInfoBanner(term, count) {
    const info = document.getElementById('globalSearchInfo');
    if (!info) return;
    if (!term) {
      info.style.display = 'none';
      info.innerHTML = '';
      return;
    }
    info.style.display = 'block';
    info.innerHTML = `
      <span class="badge bg-info text-white"><i class="bi bi-search me-1"></i>${count} résultat(s)</span>
      <span class="text-muted ms-2">Recherche globale : "<strong>${escapeHtmlDoc(term)}</strong>"</span>`;
  }

  function performDocGlobalSearchAjax(term) {
    const clearBtn = document.getElementById('clearGlobalSearch');
    clearBtn.style.display = term ? 'inline-block' : 'none';

    if (!term || term.length < 2) {
      if (initialDocResultsHtml !== null) {
        document.getElementById('documentationResultsContainer').innerHTML = initialDocResultsHtml;
      }
      updateDocSearchInfoBanner('', 0);
      return;
    }

    if (searchAbortController) searchAbortController.abort();
    searchAbortController = new AbortController();

    document.getElementById('documentationResultsContainer').innerHTML = `
      <div class="text-center py-5">
        <div class="spinner-border text-primary"></div>
        <p class="mt-2 text-muted">Recherche en cours...</p>
      </div>`;

    fetch(baseUrl + 'documentation/search_api?search=' + encodeURIComponent(term), {
      signal: searchAbortController.signal
    })
      .then(res => res.json())
      .then(json => {
        if (!json.success) {
          console.error(json.error || 'Erreur de recherche');
          return;
        }
        renderDocumentationSearchResults(json.documents, term);
        updateDocSearchInfoBanner(term, (json.documents || []).length);
      })
      .catch(err => {
        if (err.name === 'AbortError') return;
        console.error(err);
      });
  }

  document.addEventListener('DOMContentLoaded', function () {
    initAllDocFilters();
    initialDocResultsHtml = document.getElementById('documentationResultsContainer').innerHTML;

    const searchInput = document.getElementById('globalSearch');
    const clearBtn = document.getElementById('clearGlobalSearch');

    if (searchInput) {
      searchInput.addEventListener('input', function () {
        const term = this.value.trim();
        clearBtn.style.display = term ? 'inline-block' : 'none';
        if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
        searchDebounceTimer = setTimeout(() => performDocGlobalSearchAjax(term), 400);
      });

      searchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
          e.preventDefault();
          if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
          performDocGlobalSearchAjax(this.value.trim());
        }
      });
    }

    if (clearBtn) {
      clearBtn.addEventListener('click', function () {
        if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
        searchInput.value = '';
        performDocGlobalSearchAjax('');
      });
    }
  });
</script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.modal').forEach(function (modal) {

      // Réinitialiser la position à la fermeture
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

        // Éviter d'attacher plusieurs fois le listener
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

          // Figer la largeur AVANT de passer en fixed
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
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
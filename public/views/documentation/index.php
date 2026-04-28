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
            <select class="form-select bg-body text-body" id="client_id" name="client_id"
              onchange="updateSitesAndSubmit()">
              <option value="">Tous les clients</option>
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
            <select class="form-select bg-body text-body" id="site_id" name="site_id"
              onchange="updateBuildingsAndSubmit()">
              <option value="">Tous les sites</option>
              <?php if (isset($sites) && is_array($sites)): ?>
                <?php foreach ($sites as $site): ?>
                  <option value="<?= $site['id'] ?>" <?= ($filters['site_id'] ?? '') == $site['id'] ? 'selected' : '' ?>>
                    <?= h($site['name']) ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>

          <div class="col-md-2">
            <label for="building_id" class="form-label fw-bold mb-0">Bâtiment</label>
            <select class="form-select bg-body text-body" id="building_id" name="building_id"
              onchange="updateRoomsAndSubmit()">
              <option value="">Tous les bâtiments</option>
              <?php if (isset($buildings) && is_array($buildings)): ?>
                <?php foreach ($buildings as $building): ?>
                  <option value="<?= $building['id'] ?>" <?= ($filters['building_id'] ?? '') == $building['id'] ? 'selected' : '' ?>>
                    <?= h($building['name']) ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </div>

          <div class="col-md-2">
            <label for="salle_id" class="form-label fw-bold mb-0">Salle</label>
            <select class="form-select bg-body text-body" id="salle_id" name="salle_id"
              onchange="document.getElementById('filterForm').submit();">
              <option value="">Toutes les salles</option>
              <?php if (isset($salles) && is_array($salles)): ?>
                <?php foreach ($salles as $salle): ?>
                  <option value="<?= $salle['id'] ?>" <?= ($filters['salle_id'] ?? '') == $salle['id'] ? 'selected' : '' ?>>
                    <?= h($salle['name']) ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
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
  <?php if (empty($filters['client_id'])): ?>
    <div class="card">
      <div class="card-body text-center py-5">
        <i class="fas fa-filter fa-3x text-muted mb-3"></i>
        <h5 class="text-muted">Sélectionnez un client pour voir la documentation</h5>
        <p class="text-muted mb-3">Choisissez un client dans le filtre ci-dessus pour afficher la documentation associée.
        </p>
        <div class="row justify-content-center">
          <div class="col-md-6">
            <div class="alert alert-info">
              <i class="bi bi-info-circle me-2 me-1"></i>
              <strong>Astuce :</strong> Commencez par sélectionner un client, puis un site, un bâtiment et enfin une salle
              pour affiner votre recherche.
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
                                          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                          <div class="preview-container">
                                            <?php if ($fileType === 'pdf'): ?>
                                              <iframe src="<?= BASE_URL ?>documentation/preview/<?= $doc['id'] ?>" width="100%"
                                                height="600px" frameborder="0">
                                              </iframe>
                                            <?php elseif (in_array($fileType, ['jpg', 'jpeg', 'png', 'gif', 'webp'])): ?>
                                              <img src="<?= BASE_URL ?>documentation/preview/<?= $doc['id'] ?>" class="img-fluid"
                                                alt="<?= h($doc['nom_personnalise'] ?? $doc['nom_fichier']) ?>">
                                            <?php else: ?>
                                              <div class="alert alert-info">
                                                <i class="bi bi-info-circle me-1"></i>
                                                Ce type de fichier ne peut pas être prévisualisé.
                                                <a href="<?= BASE_URL ?>documentation/download/<?= $doc['id'] ?>" class="alert-link"
                                                  target="_blank">
                                                  Télécharger le fichier
                                                </a>
                                              </div>
                                            <?php endif; ?>
                                          </div>
                                        </div>
                                        <div class="modal-footer">
                                          <a href="<?= BASE_URL ?>documentation/download/<?= $doc['id'] ?>" class="btn btn-primary"
                                            target="_blank">
                                            <i class="bi bi-download me-1"></i> Télécharger
                                          </a>
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

<script>
  const baseUrl = '<?= BASE_URL ?>';

  function updateSitesAndSubmit() {
    const clientId = document.getElementById('client_id').value;
    if (clientId) {
      fetch('<?= BASE_URL ?>documentation/get_sites?client_id=' + clientId)
        .then(response => response.json())
        .then(data => {
          const siteSelect = document.getElementById('site_id');
          siteSelect.innerHTML = '<option value="">Tous les sites</option>';
          if (Array.isArray(data)) {
            data.forEach(site => {
              const option = document.createElement('option');
              option.value = site.id;
              option.textContent = site.name;
              siteSelect.appendChild(option);
            });
          }
          document.getElementById('building_id').innerHTML = '<option value="">Tous les bâtiments</option>';
          document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
          document.getElementById('filterForm').submit();
        })
        .catch(error => console.error('Erreur:', error));
    } else {
      document.getElementById('site_id').innerHTML = '<option value="">Tous les sites</option>';
      document.getElementById('building_id').innerHTML = '<option value="">Tous les bâtiments</option>';
      document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
      document.getElementById('filterForm').submit();
    }
  }

  function updateBuildingsAndSubmit() {
    const siteId = document.getElementById('site_id').value;
    if (siteId) {
      fetch('<?= BASE_URL ?>documentation/get_buildings?site_id=' + siteId)
        .then(response => response.json())
        .then(data => {
          const buildingSelect = document.getElementById('building_id');
          buildingSelect.innerHTML = '<option value="">Tous les bâtiments</option>';
          if (Array.isArray(data)) {
            data.forEach(building => {
              const option = document.createElement('option');
              option.value = building.id;
              option.textContent = building.name;
              buildingSelect.appendChild(option);
            });
          }
          document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
          document.getElementById('filterForm').submit();
        })
        .catch(error => console.error('Erreur:', error));
    } else {
      document.getElementById('building_id').innerHTML = '<option value="">Tous les bâtiments</option>';
      document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
      document.getElementById('filterForm').submit();
    }
  }

  function updateRoomsAndSubmit() {
    const buildingId = document.getElementById('building_id').value;
    if (buildingId) {
      fetch('<?= BASE_URL ?>documentation/get_rooms_by_building?building_id=' + buildingId)
        .then(response => response.json())
        .then(data => {
          const roomSelect = document.getElementById('salle_id');
          roomSelect.innerHTML = '<option value="">Toutes les salles</option>';
          if (Array.isArray(data)) {
            data.forEach(room => {
              const option = document.createElement('option');
              option.value = room.id;
              option.textContent = room.name;
              roomSelect.appendChild(option);
            });
          }
          document.getElementById('filterForm').submit();
        })
        .catch(error => console.error('Erreur:', error));
    } else {
      document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
      document.getElementById('filterForm').submit();
    }
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
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
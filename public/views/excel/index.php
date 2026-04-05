<?php
// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
  'Matériel',
  'materiel'
);

// Définir la page courante pour le menu
$currentPage = 'materiel';

// Récupérer les données depuis le contrôleur
$materiel_list = $materiel_list ?? [];
$clients = $clients ?? [];
$sites = $sites ?? [];
$salles = $salles ?? [];
$visibilites_champs = $visibilites_champs ?? [];
$pieces_jointes_count = $pieces_jointes_count ?? [];
$filters = $filters ?? [];
$materiel_organise = [];
foreach ($materiel_list as $materiel) {
  $client_id = $materiel['client_nom'] ?? 'Sans client';
  $site_id = $materiel['site_nom'] ?? 'Sans site';
  $salle_id = $materiel['salle_nom'] ?? 'Sans salle';

  if (!isset($materiel_organise[$client_id])) {
    $materiel_organise[$client_id] = [];
  }
  if (!isset($materiel_organise[$client_id][$site_id])) {
    $materiel_organise[$client_id][$site_id] = [];
  }
  if (!isset($materiel_organise[$client_id][$site_id][$salle_id])) {
    $materiel_organise[$client_id][$site_id][$salle_id] = [];
  }

  $materiel_organise[$client_id][$site_id][$salle_id][] = $materiel;
}
// Définir les breadcrumbs personnalisés pour la page matériel index
if (isset($filters) && !empty($filters)) {
  $GLOBALS['customBreadcrumbs'] = generateMaterielIndexBreadcrumbs($filters, $clients, $sites, $salles);
}
?>

<!DOCTYPE html>
<html lang="fr">

<head>
  <meta charset="UTF-8">
  <title>Matériel Excel Éditable</title>

  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.css">
  <script src="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js"></script>

  <style>
    body {
      background: #f8f9fa;
      font-family: Arial;
    }

    .handsontable {
      background: #fff;
      color: #000;
      border: 1px solid #ddd;
    }

    .handsontable th {
      background: #0d6efd;
      color: #fff;
      text-align: center;
    }

    .handsontable td {
      background: #fff;
    }

    .handsontable td:hover {
      background: #eef3ff;
    }

    .handsontable button {
      width: 100%;
      border-radius: 6px;
    }
  </style>
</head>

<body class="p-4">
  <?php foreach ($materiel_organise as $client_nom => $sites): ?>
    <div class="card mb-4">
      <div class="card-header bg-body-secondary">
        <h5 class="card-title mb-0">
          <i class="bi bi-building me-2 text-primary me-1"></i>
          <?= h($client_nom) ?>
        </h5>
      </div>
      <div class="card-body p-0">
        <?php foreach ($sites as $site_nom => $salles): ?>
          <?php foreach ($salles as $salle_nom => $materiels):

            $salle_id = rand(1000, 9999);
            ?>
            <div class="card mb-4 shadow-sm">
              <div class="p-3 bg-body-darkgray">
                <h6 class="mb-0">
                  <i class="bi bi-geo-alt me-2 text-success me-1"></i>
                  <?= h($site_nom) ?>
                </h6>
              </div>
              <div class="card-header d-flex justify-content-between align-items-center bg-body-secondary bg-opacity-10">
                <div>
                  <i class="bi bi-door-open me-2 text-info me-1"></i>
                  <?= $salle_nom ?>
                  <span class="badge bg-secondary">
                    <?= count($materiels) ?> équipement(s)
                  </span>
                </div>
              </div>
              <div class="table-wrapper">
                <div id="excelTable-<?= $salle_id ?>"></div>
              </div>
            </div>

            <script>
              document.addEventListener("DOMContentLoaded", function () {

                const container = document.getElementById("excelTable-<?= $salle_id ?>");

                // Données venant de PHP
                const data = <?= json_encode(array_map(function ($m) use ($pieces_jointes_count) {
                  return [
                    ($m['marque'] ?? '') . "\n" . ($m['modele'] ?? ''),
                    $m['type_nom'] ?? '',
                    $m['numero_serie'] ?? '',
                    $m['version_firmware'] ?? '',
                    $m['adresse_ip'] ?? '',
                    $m['adresse_mac'] ?? '',
                    $m['date_fin_maintenance'] ?? '',
                    [
                      'count' => $pieces_jointes_count[$m['id']] ?? 0,
                      'id' => $m['id'],
                      'name' => ($m['marque'] ?? '') . ' ' . ($m['modele'] ?? '')
                    ]
                  ];
                }, $materiels)); ?>;

                // Création de Handsontable
                const hot = new Handsontable(container, {
                  data: data,
                  colHeaders: [
                    "Équipement",
                    "Type",
                    "S/N",
                    "Firmware",
                    "IP",
                    "MAC",
                    "Expiration",
                    "Pièces jointes"
                  ],
                  rowHeaders: false,
                  licenseKey: "non-commercial-and-evaluation",
                  filters: true,
                  dropdownMenu: false,
                  contextMenu: true,
                  columnSorting: true,
                  stretchH: "all",
                  height: 400,

                  columns: [
                    {

                      renderer: function (instance, td, row, col, prop, value) {
                        if (!value) value = '';

                        const parts = value.split('\n');
                        const marque = parts[0] || '';
                        const modele = parts[1] || '';

                        td.innerHTML = `${marque}<br><span style="font-size:0.75rem; font-weight: none">${modele}</span>`;
                      }
                    },
                    {}, {}, {}, {}, {}, {},
                    {
                      // Colonne Pièces jointes : bouton
                      renderer: function (instance, td, row, col, prop, value) {
                        const count = value?.count ?? 0;
                        const id = value?.id;
                        const name = value?.name ?? '';

                        td.innerHTML = `
            <button 
              class="btn btn-sm ${count > 0 ? 'btn-outline-info' : 'btn-outline-secondary'}"
              onclick="openAttachmentsModal(${id}, '${name.replace(/'/g, "\\'")}')"
            >
             <i class="<?php echo getIcon('attachment', 'bi bi-paperclip'); ?>"></i>
              <span class="badge ${count > 0 ? 'bg-info' : 'bg-secondary'}">
                ${count}
              </span>
            </button>
          `;
                }
              }
            ]
          });

        });
      </script>

      <?php endforeach; ?>
      <?php endforeach; ?>
    </div>
  </div>

  <?php endforeach; ?>

  <!-- Modale des pièces jointes -->
  <div class="modal fade" id="attachmentsModal" tabindex="-1" aria-labelledby="attachmentsModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="attachmentsModalLabel">Pièces jointes</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="attachmentsModalContent">
          <!-- Le contenu sera chargé dynamiquement -->
        </div>
      </div>
    </div>
  </div>

  <!-- Modale de prévisualisation -->
  <div class="modal fade" id="previewAttachmentModal" tabindex="-1" aria-labelledby="previewAttachmentModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="previewAttachmentModalLabel"></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="previewAttachmentModalBody">
          <!-- Le contenu sera chargé dynamiquement -->
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
        </div>
      </div>
    </div>
  </div>

  <?php
  require_once __DIR__ . '/../../includes/FileUploadValidator.php';
  ?>

  <!-- Modale Ajout de pièces jointes avec Drag & Drop -->
  <div class="modal fade" id="addAttachmentModal" tabindex="-1" aria-labelledby="addAttachmentModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="dragDropForm" method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="modal-header">
            <h5 class="modal-title" id="addAttachmentModalLabel">
              <i class="bi bi-cloud-upload me-2"></i>
              Ajouter des pièces jointes
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <!-- Zone de Drag & Drop -->
            <div class="drop-zone" id="dropZone">
              <div class="drop-message">
                <i class="bi bi-cloud-upload me-1"></i>
                Glissez-déposez vos fichiers ici<br>
                <small class="text-muted">ou cliquez pour sélectionner</small>
              </div>

              <input type="file" id="fileInput" multiple style="display: none;"
                accept="<?= FileUploadValidator::getAcceptAttribute($GLOBALS['db']) ?>">

              <div class="file-list" id="fileList"></div>

              <div class="stats" id="stats" style="display: none;">
                <div class="row">
                  <div class="col-6">
                    <strong>Fichiers valides:</strong> <span id="validCount">0</span>
                  </div>
                  <div class="col-6">
                    <strong>Fichiers rejetés:</strong> <span id="invalidCount">0</span>
                  </div>
                </div>
                <div class="progress-bar">
                  <div class="progress-fill" id="progressFill"></div>
                </div>
              </div>
            </div>

            <!-- Liste des fichiers avec options individuelles -->
            <div id="filesOptions" style="display: none;">
              <h6 class="mt-3 mb-2">Options par fichier :</h6>
              <div id="filesOptionsList"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="button" class="btn btn-warning" id="clearAllBtn" style="display: none;">
              <i class="bi bi-trash me-1"></i> Tout effacer
            </button>
            <button type="button" class="btn btn-primary" id="uploadValidBtn" style="display: none;">
              <i class="bi bi-upload me-1"></i> Uploader les fichiers valides
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <style>
    .drop-zone {
      border: 2px dashed var(--bs-border-color);
      border-radius: 8px;
      padding: 30px;
      text-align: center;
      background-color: var(--bs-body-bg);
      transition: all 0.3s ease;
      min-height: 150px;
      display: flex;
      flex-direction: column;
      justify-content: center;
      align-items: center;
    }

    .drop-zone.dragover {
      border-color: var(--bs-primary);
      background-color: var(--bs-primary-bg-subtle);
    }

    .drop-zone.dragover .drop-message {
      color: var(--bs-primary);
    }

    .drop-message {
      font-size: 1.1em;
      color: var(--bs-secondary-color);
      margin-bottom: 15px;
    }

    .drop-message i {
      font-size: 2.5em;
      margin-bottom: 10px;
      display: block;
    }

    .file-list {
      margin-top: 15px;
      max-height: 200px;
      overflow-y: auto;
    }

    .file-item {
      display: flex;
      align-items: center;
      padding: 8px;
      margin: 3px 0;
      border-radius: 5px;
      border: 1px solid var(--bs-border-color);
      background-color: var(--bs-body-bg);
    }

    .file-item.valid {
      background-color: var(--bs-success-bg-subtle);
      border-color: var(--bs-success-border-subtle);
    }

    .file-item.invalid {
      background-color: var(--bs-danger-bg-subtle);
      border-color: var(--bs-danger-border-subtle);
    }

    .file-name {
      flex: 1;
      font-weight: 500;
      font-size: 0.9em;
      color: var(--bs-body-color);
    }

    .file-size {
      color: var(--bs-secondary-color);
      font-size: 0.8em;
      margin: 0 8px;
    }

    .error-message {
      color: var(--bs-danger);
      font-size: 0.8em;
      margin-left: 8px;
    }

    .remove-file {
      background: none;
      border: none;
      color: var(--bs-danger);
      font-size: 1.1em;
      cursor: pointer;
      padding: 0 4px;
    }

    .remove-file:hover {
      color: var(--bs-danger-hover);
    }

    .stats {
      margin-top: 10px;
      padding: 8px;
      background-color: var(--bs-secondary-bg);
      border-radius: 5px;
      font-size: 0.9em;
      color: var(--bs-body-color);
    }

    .progress-bar {
      height: 3px;
      background-color: var(--bs-secondary-bg);
      border-radius: 2px;
      overflow: hidden;
      margin-top: 8px;
    }

    .progress-fill {
      height: 100%;
      background-color: var(--bs-primary);
      width: 0%;
      transition: width 0.3s ease;
    }

    .file-options {
      margin-top: 8px;
      padding: 8px 12px;
      background-color: var(--bs-secondary-bg);
      border-radius: 5px;
      border: 1px solid var(--bs-border-color);
    }

    .file-options .form-control {
      font-size: 0.85em;
      background-color: var(--bs-body-bg);
      border-color: var(--bs-border-color);
      color: var(--bs-body-color);
      height: 32px;
    }

    .file-options .form-control:focus {
      background-color: var(--bs-body-bg);
      border-color: var(--bs-primary);
      color: var(--bs-body-color);
    }

    .file-options .form-check {
      margin: 0;
    }

    .file-options strong {
      font-size: 0.9em;
      color: var(--bs-body-color);
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }
  </style>

  <script>
    // Classe Drag & Drop Uploader pour la modale
    class DragDropUploader {
      constructor(materielId) {
        this.materielId = materielId;
        this.dropZone = document.getElementById('dropZone');
        this.fileInput = document.getElementById('fileInput');
        this.fileList = document.getElementById('fileList');
        this.stats = document.getElementById('stats');
        this.validCount = document.getElementById('validCount');
        this.invalidCount = document.getElementById('invalidCount');
        this.progressFill = document.getElementById('progressFill');
        this.uploadValidBtn = document.getElementById('uploadValidBtn');
        this.clearAllBtn = document.getElementById('clearAllBtn');
        this.filesOptions = document.getElementById('filesOptions');
        this.filesOptionsList = document.getElementById('filesOptionsList');
        this.dragDropForm = document.getElementById('dragDropForm');

        this.files = [];
        this.allowedExtensions = [];
        this.maxSize = parsePhpSize('<?php echo ini_get("upload_max_filesize"); ?>');

        this.init();
      }

      async init() {
        await this.loadAllowedExtensions();
        this.setupEventListeners();
      }

      async loadAllowedExtensions() {
        try {
          const response = await fetch('<?php echo BASE_URL; ?>settings/getAllowedExtensions');
          const data = await response.json();
          this.allowedExtensions = data.extensions || [];
        } catch (error) {
          console.error('Erreur lors du chargement des extensions autorisées:', error);
        }
      }

      setupEventListeners() {
        // Drag & Drop events
        this.dropZone.addEventListener('dragover', (e) => {
          e.preventDefault();
          this.dropZone.classList.add('dragover');
        });

        this.dropZone.addEventListener('dragleave', (e) => {
          e.preventDefault();
          this.dropZone.classList.remove('dragover');
        });

        this.dropZone.addEventListener('drop', (e) => {
          e.preventDefault();
          this.dropZone.classList.remove('dragover');
          const files = Array.from(e.dataTransfer.files);
          this.handleFiles(files);
        });

        // Click to select files
        this.dropZone.addEventListener('click', () => {
          this.fileInput.click();
        });

        this.fileInput.addEventListener('change', (e) => {
          const files = Array.from(e.target.files);
          this.handleFiles(files);
        });

        // Action buttons
        this.uploadValidBtn.addEventListener('click', () => {
          this.uploadValidFiles();
        });

        this.clearAllBtn.addEventListener('click', () => {
          this.clearAllFiles();
        });
      }

      handleFiles(newFiles) {
        const validatedFiles = this.validateFiles(newFiles);
        this.files = [...this.files, ...validatedFiles];
        this.displayFiles();
        this.updateStats();
        this.updateFilesOptions();
      }

      validateFiles(files) {
        return files.map(file => {
          const extension = file.name.split('.').pop().toLowerCase();
          const isValid = this.allowedExtensions.includes(extension);
          const isSizeValid = file.size <= this.maxSize;

          let error = null;
          if (!isSizeValid) {
            error = `Le fichier est trop volumineux (${this.formatFileSize(file.size)}). Taille maximale autorisée : ${this.formatFileSize(this.maxSize)}.`;
          } else if (!isValid) {
            error = 'Ce format n\'est pas accepté, rapprochez-vous de l\'administrateur du site, ou utilisez un format compressé.';
          }

          return {
            file,
            isValid: isValid && isSizeValid,
            extension,
            error
          };
        });
      }

      displayFiles() {
        this.fileList.innerHTML = '';

        this.files.forEach((fileData, index) => {
          const fileItem = document.createElement('div');
          fileItem.className = `file-item ${fileData.isValid ? 'valid' : 'invalid'}`;

          fileItem.innerHTML = `
                <span class="file-name">${fileData.file.name}</span>
                <span class="file-size">${this.formatFileSize(fileData.file.size)}</span>
                ${fileData.error ? `<span class="error-message">${fileData.error}</span>` : ''}
                <button type="button" class="remove-file" onclick="uploader.removeFile(${index})">×</button>
            `;

          this.fileList.appendChild(fileItem);
        });
      }

      updateFilesOptions() {
        const validFiles = this.files.filter(f => f.isValid);

        if (validFiles.length > 0) {
          this.filesOptions.style.display = 'block';
          this.filesOptionsList.innerHTML = '';

          validFiles.forEach((fileData, index) => {
            const fileOptionsDiv = document.createElement('div');
            fileOptionsDiv.className = 'file-options mb-2';
            fileOptionsDiv.innerHTML = `
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <div class="d-flex align-items-center">
                                <strong class="me-3" style="min-width: 120px;">${fileData.file.name}</strong>
                                <input type="text" class="form-control form-control-sm" name="file_description[${index}]" 
                                       placeholder="Titre ou description (optionnel)" style="max-width: 200px;">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="file_masque_client[${index}]" value="1" id="masque_${index}">
                                <label class="form-check-label" for="masque_${index}">
                                    <i class="bi bi-eye-slash text-warning me-1"></i>
                                    Masquer aux clients
                                </label>
                            </div>
                        </div>
                    </div>
                `;

            this.filesOptionsList.appendChild(fileOptionsDiv);
          });
        } else {
          this.filesOptions.style.display = 'none';
        }
      }

      removeFile(index) {
        this.files.splice(index, 1);
        this.displayFiles();
        this.updateStats();
        this.updateFilesOptions();
      }

      updateStats() {
        const validFiles = this.files.filter(f => f.isValid);
        const invalidFiles = this.files.filter(f => !f.isValid);

        this.validCount.textContent = validFiles.length;
        this.invalidCount.textContent = invalidFiles.length;

        if (this.files.length > 0) {
          this.stats.style.display = 'block';
          this.uploadValidBtn.style.display = 'inline-block';
          this.clearAllBtn.style.display = 'inline-block';

          const progress = (validFiles.length / this.files.length) * 100;
          this.progressFill.style.width = `${progress}%`;
        } else {
          this.stats.style.display = 'none';
          this.uploadValidBtn.style.display = 'none';
          this.clearAllBtn.style.display = 'none';
        }
      }

      clearAllFiles() {
        this.files = [];
        this.displayFiles();
        this.updateStats();
        this.updateFilesOptions();
        this.fileInput.value = '';
      }

      formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
      }

      async uploadValidFiles() {
        const validFiles = this.files.filter(f => f.isValid);

        if (validFiles.length === 0) {
          alert('Aucun fichier valide à uploader');
          return;
        }

        // Préparer les données du formulaire
        const formData = new FormData();
        formData.append('materiel_id', this.materielId);

        // Ajouter les fichiers et leurs options
        validFiles.forEach((fileData, index) => {
          formData.append(`files[${index}]`, fileData.file);

          // Ajouter les options individuelles
          const descriptionInput = document.querySelector(`input[name="file_description[${index}]"]`);
          const masqueClientInput = document.querySelector(`input[name="file_masque_client[${index}]"]`);

          if (descriptionInput && descriptionInput.value) {
            formData.append(`descriptions[${index}]`, descriptionInput.value);
          }
          if (masqueClientInput && masqueClientInput.checked) {
            formData.append(`masque_client[${index}]`, '1');
          }
        });

        // Désactiver le bouton pendant l'upload
        this.uploadValidBtn.disabled = true;
        this.uploadValidBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-1"></i>Upload en cours...';

        try {
          const response = await fetch('<?php echo BASE_URL; ?>materiel/uploadAttachment', {
            method: 'POST',
            headers: {
              'X-CSRF-Token': '<?= csrf_token() ?>'
            },
            body: formData
          });

          const result = await response.json();

          if (result.success) {
            alert(`${validFiles.length} fichier(s) uploadé(s) avec succès !`);
            // Fermer la modale d'ajout
            const addModal = bootstrap.Modal.getInstance(document.getElementById('addAttachmentModal'));
            addModal.hide();

            // Recharger les pièces jointes dans la modale principale si elle est ouverte
            const attachmentsModal = document.getElementById('attachmentsModal');
            const materielIdFromMain = attachmentsModal.getAttribute('data-materiel-id');
            const modalContent = document.getElementById('attachmentsModalContent');
            if (materielIdFromMain && modalContent) {
              loadAttachments(materielIdFromMain, modalContent);
            } else {
              // Sinon, recharger la page pour mettre à jour les compteurs
              window.location.reload();
            }
          } else {
            alert(`Erreur lors de l'upload : ${result.error || 'Erreur inconnue'}`);
          }
        } catch (error) {
          console.error('Erreur lors de l\'upload:', error);
          alert('Erreur lors de l\'upload des fichiers');
        } finally {
          this.uploadValidBtn.disabled = false;
          this.uploadValidBtn.innerHTML = '<i class="bi bi-upload me-1"></i> Uploader les fichiers valides';
        }
      }
    }

    // Fonction pour parser la taille PHP (ex: "8M" -> bytes)
    function parsePhpSize(size) {
      const units = { 'K': 1024, 'M': 1024 * 1024, 'G': 1024 * 1024 * 1024 };
      const match = size.match(/^(\d+)([KMG])?$/i);
      if (!match) return 0;
      const value = parseInt(match[1]);
      const unit = match[2] ? match[2].toUpperCase() : '';
      return value * (units[unit] || 1);
    }

    // Fonction pour ouvrir la modale d'ajout
    function openAddAttachmentModal(materielId) {
      const addModal = new bootstrap.Modal(document.getElementById('addAttachmentModal'));

      // Stocker l'ID du matériel dans la modale d'ajout
      document.getElementById('addAttachmentModal').setAttribute('data-materiel-id', materielId);

      addModal.show();
    }

    // Initialiser l'uploader quand la modale d'ajout s'ouvre
    let uploader;
    document.getElementById('addAttachmentModal').addEventListener('shown.bs.modal', function () {
      const addModal = document.getElementById('addAttachmentModal');
      const materielId = addModal.getAttribute('data-materiel-id');

      if (materielId) {
        uploader = new DragDropUploader(materielId);
      }
    });

    // Réinitialiser l'uploader quand la modale se ferme
    document.getElementById('addAttachmentModal').addEventListener('hidden.bs.modal', function () {
      if (uploader) {
        uploader.clearAllFiles();
      }
    });
  </script>
  <style>
    body {
      background: #f4f6f9;
      font-family: "Segoe UI", sans-serif;
    }

    /* Empêche le débordement */
    .card-body {
      overflow: hidden;
    }

    /* Wrapper scroll horizontal propre */
    .table-wrapper {
      overflow: auto;
    }

    .handsontable {
      width: auto !important;
      background-color: none !important;
      overflow: hidden;
    }

    /* Scrollbar propre */
    .table-wrapper::-webkit-scrollbar {
      height: 8px;
    }

    .table-wrapper::-webkit-scrollbar-thumb {
      background: #ced4da;
      border-radius: 4px;
    }

    /* HEADER */
    .handsontable th {
      background-color: #f1f3f5 !important;
      color: #495057;
      font-weight: 600;
      text-align: center;
      border: none;
      padding: 10px;
      max-width: 150px;
    }

    /* CELLULES */
    .handsontable td {
      background-color: #ffffff;
      border-bottom: 1px solid #dee2e6;
      padding: 8px;
      vertical-align: middle;
    }

    /* ===== COLONNE ÉQUIPEMENT (colonne 1) ===== */
    .handsontable td:nth-child(1) {
      background-color: #ffffff !important;
      color: #0d6efd !important;
      font-weight: 600;
    }

    /* ===== COLONNES MILIEU (beige) ===== */
    .handsontable td:nth-child(2),
    .handsontable td:nth-child(3),
    .handsontable td:nth-child(4),
    .handsontable td:nth-child(5),
    .handsontable td:nth-child(6),
    .handsontable td:nth-child(7) {
      background-color: #f3e1b5 !important;
    }

    /* ===== COLONNE PIÈCES JOINTES (colonne 8) ===== */
    .handsontable td:nth-child(8) {
      background-color: #f8f9fa !important;
      text-align: right;
      align-items: center;
      width: auto;
    }

    .handsontable th:nth-child(8) {
      width: auto;
      overflow: hidden;
    }

    /* HOVER */
    .handsontable tbody tr:hover td {
      background-color: #eef3ff !important;
    }

    /* BADGE */
    .handsontable .badge {
      font-size: 11px;
      padding: 3px 6px;
    }

    /* SCROLLBAR */
    .handsontable .wtHolder::-webkit-scrollbar {
      height: 8px;
      width: 8px;
    }

    .handsontable .wtHolder::-webkit-scrollbar-thumb {
      background: #ced4da;
      border-radius: 4px;
    }

    /* BOUTON PIÈCES JOINTES */
    .handsontable .btn {
      border-radius: 6px;
      font-size: 12px;
      display: inline-flex;
      align-items: center;
      justify-content: space-evenly;
      gap: 2px;
      min-width: 80px;
      width: 60px;
    }

    /* BADGE */
    .handsontable .badge {
      font-size: 11px;
      padding: 3px 6px;
    }

    /* ROW HEADER (numéros à gauche) */
    .handsontable .ht_clone_left th {
      background-color: whitesmoke !important;
      color: #6c757d;
    }

    /* SUPPRIMER LE STYLE SOMBRE */
    .handsontable .wtHolder {
      background-color: #fff !important;
    }

    /* SCROLLBAR propre */
    .handsontable .wtHolder::-webkit-scrollbar {
      height: 8px;
      width: 8px;
    }

    .handsontable .wtHolder::-webkit-scrollbar-thumb {
      background: #ced4da;
      border-radius: 4px;
    }

    /* BOUTON PJ */
    .pj-btn {
      width: 20%;
      padding: 5px;
      border-radius: 6px;
      border: 1px solid #dee2e6;
      background: #f8f9fa;
      cursor: pointer;
    }

    .pj-btn.has-file {
      background: #e7f1ff;
      border-color: #0d6efd;
      color: #0d6efd;
    }

    .pj-btn:hover {
      background: #dbeafe;
    }

    /* INPUT */
    input.form-control {
      border-radius: 8px;
    }
  </style>
</body>

</html>
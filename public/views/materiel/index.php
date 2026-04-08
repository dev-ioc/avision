<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controllers/MaterielController.php';
/**
 * Vue de la liste du matériel
 * Affiche la liste du matériel regroupé par site/salle avec filtres
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
  header('Location: ' . BASE_URL . 'auth/login');
  exit;
}

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

// Définir les breadcrumbs personnalisés pour la page matériel index
if (isset($filters) && !empty($filters)) {
  $GLOBALS['customBreadcrumbs'] = generateMaterielIndexBreadcrumbs($filters, $clients, $sites, $salles);
}

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer les statistiques
$stats = [];
if (isset($materielModel)) {
  $stats = $materielModel->getStats();
}

// Organiser le matériel par client/site/salle
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

// Définir toutes les colonnes disponibles avec leurs configurations
$allColumns = [
  ['label' => 'Équipement', 'field' => 'equipement', 'default' => true],
  ['label' => 'Type', 'field' => 'type_nom', 'default' => true],
  ['label' => 'S/N', 'field' => 'numero_serie', 'default' => true],
  ['label' => 'Firmware', 'field' => 'version_firmware', 'default' => true],
  ['label' => 'IP', 'field' => 'adresse_ip', 'default' => true],
  ['label' => 'MAC', 'field' => 'adresse_mac', 'default' => true],
  ['label' => 'Expiration', 'field' => 'date_fin_maintenance', 'default' => true],
  ['label' => 'Pièces jointes', 'field' => 'pieces_jointes', 'default' => true],
  ['label' => 'Référence', 'field' => 'reference', 'default' => false],
  ['label' => 'Usage', 'field' => 'usage_materiel', 'default' => false],
  ['label' => 'Marque', 'field' => 'marque', 'default' => false],
  ['label' => 'Modèle', 'field' => 'modele', 'default' => false],
  ['label' => 'Ancien Firmware', 'field' => 'ancien_firmware', 'default' => false],
  ['label' => 'Masque', 'field' => 'masque', 'default' => false],
  ['label' => 'Passerelle', 'field' => 'passerelle', 'default' => false],
  ['label' => 'Login', 'field' => 'login', 'default' => false],
  ['label' => 'Password', 'field' => 'password', 'default' => false],
  ['label' => 'ID Matériel', 'field' => 'id', 'default' => false],
  ['label' => 'IP Primaire', 'field' => 'ip_primaire', 'default' => false],
  ['label' => 'MAC Primaire', 'field' => 'mac_primaire', 'default' => false],
  ['label' => 'IP Secondaire', 'field' => 'ip_secondaire', 'default' => false],
  ['label' => 'MAC Secondaire', 'field' => 'mac_secondaire', 'default' => false],
  ['label' => 'AES67 Reçu', 'field' => 'stream_aes67_recu', 'default' => false],
  ['label' => 'AES67 Transmis', 'field' => 'stream_aes67_transmis', 'default' => false],
  ['label' => 'SSID', 'field' => 'ssid', 'default' => false],
  ['label' => 'Cryptage', 'field' => 'type_cryptage', 'default' => false],
  ['label' => 'Password WiFi', 'field' => 'password_wifi', 'default' => false],
  ['label' => 'Libellé Salle', 'field' => 'libelle_pa_salle', 'default' => false],
  ['label' => 'Port Switch', 'field' => 'numero_port_switch', 'default' => false],
  ['label' => 'VLAN', 'field' => 'vlan', 'default' => false],
  ['label' => 'Date Garantie', 'field' => 'date_fin_garantie', 'default' => false],
  ['label' => 'Dernière Inter', 'field' => 'date_derniere_inter', 'default' => false],
  ['label' => 'Commentaire', 'field' => 'commentaire', 'default' => false],
  ['label' => 'GitHub', 'field' => 'url_github', 'default' => false],
];

// Colonnes cachées par défaut
$hiddenColumns = [];
foreach ($allColumns as $i => $col) {
  if (!$col['default']) {
    $hiddenColumns[] = $i;
  }
}

// Headers
$colHeaders = array_map(fn($c) => $c['label'], $allColumns);

$allData = [];
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.css">
  <script src="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js"></script>
</head>

<body>
  <div class="container-fluid grow container-y">
    <!-- En-tête avec titre et bouton d'ajout -->
    <div class="row mb-4">
      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
          <div>
            <h4 class="fw-bold mb-1">
              <i class="bi bi-hdd-network me-2 me-1"></i>Liste du Matériel
            </h4>
            <p class="text-muted mb-0">Gestion et suivi du matériel par site et salle</p>
          </div>
          <div class="d-flex gap-2">
            <?php
            // Bouton retour vers le client si on vient d'un client
            if (!empty($filters['client_id'])) {
              $clientId = $filters['client_id'];
              echo '<a href="' . BASE_URL . 'clients/view/' . $clientId . '" class="btn btn-secondary me-2">';
              echo '<i class="bi bi-arrow-left me-1"></i> Retour au client';
              echo '</a>';
            }

            // Construire l'URL d'ajout avec les paramètres de filtres
            $addParams = [];
            if (!empty($filters['client_id'])) {
              $addParams['client_id'] = $filters['client_id'];
            }
            if (!empty($filters['site_id'])) {
              $addParams['site_id'] = $filters['site_id'];
            }
            if (!empty($filters['salle_id'])) {
              $addParams['salle_id'] = $filters['salle_id'];
            }

            $addUrl = BASE_URL . 'materiel/add';
            if (!empty($addParams)) {
              $addUrl .= '?' . http_build_query($addParams);
            }
            ?>
            <a href="<?= $addUrl ?>" class="btn btn-primary">
              <i class="bi bi-plus me-2 me-1"></i>Ajouter du Matériel
            </a>

            <?php if (canImportMateriel()): ?>
              <a href="<?= BASE_URL ?>materiel_bulk<?= !empty($bulkParams) ? '?' . http_build_query($bulkParams) : '' ?>"
                class="btn btn-info">
                <i class="bi bi-arrow-left-right me-2 me-1"></i>Import/Export en Masse
              </a>
            <?php endif; ?>
            <?php if (canDeleteDocumentation()): ?>
              <a href="<?= BASE_URL ?>materiel_bulk/bulk_delete<?= !empty($bulkDeleteParams) ? '?' . http_build_query($bulkDeleteParams) : '' ?>"
                class="btn btn-outline-danger">
                <i class="bi bi-trash me-2 me-1"></i>Supprimer en masse
              </a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>

    <!-- Statistiques -->
    <?php if (!empty($stats)): ?>
      <div class="row mb-4">
        <div class="col-12">
          <div class="row g-3">
            <div class="col-md-3">
              <div class="card border-0 bg-primary bg-opacity-10">
                <div class="card-body p-3">
                  <div class="d-flex align-items-center">
                    <div class="shrink-0">
                      <i class="bi bi-hdd-network fa-2x text-primary me-1"></i>
                    </div>
                    <div class="grow ms-3">
                      <h6 class="mb-1 text-primary fw-bold">
                        <?= $stats['total'] ?? 0 ?>
                      </h6>
                      <small class="text-muted">Total Matériel</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card border-0 bg-success bg-opacity-10">
                <div class="card-body p-3">
                  <div class="d-flex align-items-center">
                    <div class="shrink-0">
                      <i class="fas fa-wifi fa-2x text-success"></i>
                    </div>
                    <div class="grow ms-3">
                      <h6 class="mb-1 text-success fw-bold">
                        <?= $stats['online'] ?? 0 ?>
                      </h6>
                      <small class="text-muted">En Ligne</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card border-0 bg-warning bg-opacity-10">
                <div class="card-body p-3">
                  <div class="d-flex align-items-center">
                    <div class="shrink-0">
                      <i class="bi bi-tools fa-2x text-warning me-1"></i>
                    </div>
                    <div class="grow ms-3">
                      <h6 class="mb-1 text-warning fw-bold">
                        <?= $stats['maintenance_expired'] ?? 0 ?>
                      </h6>
                      <small class="text-muted">Maintenance Expirée</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            <div class="col-md-3">
              <div class="card border-0 bg-danger bg-opacity-10">
                <div class="card-body p-3">
                  <div class="d-flex align-items-center">
                    <div class="shrink-0">
                      <i class="fas fa-certificate fa-2x text-danger"></i>
                    </div>
                    <div class="grow ms-3">
                      <h6 class="mb-1 text-danger fw-bold">
                        <?= $stats['garantie_expired'] ?? 0 ?>
                      </h6>
                      <small class="text-muted">Garantie Expirée</small>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>

    <!-- Filtres -->
    <div class="card mb-4">
      <div class="card-header py-2">
        <h6 class="card-title mb-0">Filtres</h6>
      </div>
      <div class="card-body py-2">
        <form method="get" action="" class="row g-3 align-items-end" id="filterForm">
          <div class="col-md-3">
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
          <div class="col-md-3">
            <label for="site_id" class="form-label fw-bold mb-0">Site</label>
            <select class="form-select bg-body text-body" id="site_id" name="site_id" onchange="updateRoomsAndSubmit()">
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
          <div class="col-md-3">
            <label for="salle_id" class="form-label fw-bold mb-0">Salle</label>
            <select class="form-select bg-body text-body" id="salle_id" name="salle_id"
              onchange="document.getElementById('filterForm').submit();">
              <option value="">Toutes les salles</option>
              <?php foreach ($salles as $salle): ?>
                <option value="<?= $salle['id'] ?>" <?= ($filters['salle_id'] ?? '') == $salle['id'] ? 'selected' : '' ?>>
                  <?= h($salle['name']) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3 d-flex align-items-end">
            <a href="<?= BASE_URL ?>materiel" class="btn btn-outline-secondary">
              <i class="bi bi-x-lg me-2 me-1"></i>Réinitialiser
            </a>
          </div>
        </form>
      </div>
    </div>

    <!-- Liste du matériel organisée -->
    <?php if (empty($filters['client_id'])): ?>
      <div class="card">
        <div class="card-body text-center py-5">
          <i class="fas fa-filter fa-3x text-muted mb-3"></i>
          <h5 class="text-muted">Sélectionnez un client pour voir le matériel</h5>
          <p class="text-muted mb-3">Choisissez un client dans le filtre ci-dessus pour afficher le matériel associé.</p>
        </div>
      </div>
    <?php elseif (empty($materiel_organise)): ?>
      <div class="card">
        <div class="card-body text-center py-5">
          <i class="bi bi-hdd-network fa-3x text-muted mb-3 me-1"></i>
          <h5 class="text-muted">Aucun matériel trouvé</h5>
          <p class="text-muted mb-3">Aucun matériel ne correspond aux critères sélectionnés.</p>
          <a href="<?= BASE_URL ?>materiel/add<?= !empty($addParams) ? '?' . http_build_query($addParams) : '' ?>"
            class="btn btn-primary">
            <i class="bi bi-plus me-2 me-1"></i>Ajouter du Matériel
          </a>
        </div>
      </div>
    <?php else: ?>
      <style>
        .card,
        .card-body,
        .accordion-body,
        .table-wrapper {
          overflow: visible !important;
        }

        .dropdown-menu {
          z-index: 9999 !important;
        }

        .handsontable td {
          transition: background-color 0.2s;
        }

        .handsontable tr.hidden-row {
          display: none !important;
        }
      </style>

      <!-- Recherche globale et contrôles -->
      <div class="card mb-4">
        <div class="card-body">
          <div class="row align-items-center">
            <div class="col-md-6">
              <label for="globalSearch" class="form-label fw-bold mb-2">
                <i class="bi bi-search me-2"></i>Recherche globale
              </label>
              <input type="text" class="form-control" id="globalSearch" placeholder="Rechercher dans tous les tableaux..."
                autocomplete="off">
              <small class="text-muted">La recherche s'applique à tous les tableaux de toutes les salles</small>
            </div>
            <div class="col-md-6 text-end">
              <div class="d-flex gap-2 justify-content-end align-items-end">
                <button type="button" class="btn btn-outline-secondary" id="clearGlobalSearch" style="display: none;">
                  <i class="bi bi-x-lg me-1"></i>Effacer
                </button>
                <div class="btn-group">
                  <button class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"
                    data-bs-auto-close="outside">
                    <i class="bi bi-list-check me-1"></i>Colonnes
                  </button>
                  <ul class="dropdown-menu dropdown-menu-end" style="max-height:500px;overflow:auto;">
                    <?php foreach ($allColumns as $i => $col): ?>
                      <li>
                        <label class="dropdown-item">
                          <input type="checkbox" class="global-colvis-checkbox me-2" data-col="<?= $i ?>" <?= $col['default'] ? 'checked' : '' ?>>
                          <?= h($col['label']) ?>
                        </label>
                      </li>
                    <?php endforeach; ?>
                  </ul>
                </div>
                <button type="button" class="btn btn-outline-primary" id="openAllAccordions">
                  <i class="bi bi-chevron-down me-1"></i>Ouvrir tout
                </button>
                <button type="button" class="btn btn-outline-secondary" id="closeAllAccordions">
                  <i class="bi bi-chevron-up me-1"></i>Fermer tout
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <div id="accordionContainer">
        <?php foreach ($materiel_organise as $client_nom => $sites): ?>
          <div class="card mb-4">
            <div class="card-header bg-body-secondary d-flex align-items-center justify-content-between">
              <h5 class="card-title mb-0 d-flex align-items-center">
                <i class="bi bi-building text-primary me-2"></i>
                <?= h($client_nom) ?>
              </h5>

              <button type="button" class="btn btn-sm btn-outline-primary" onclick="saveAllTablesData()">
                <i class="bi bi-save-all me-1"></i>
                Sauvegarder toutes les modifications
              </button>
            </div>
            <div class="card-body p-0">
              <?php foreach ($sites as $site_nom => $salles): ?>
                <?php foreach ($salles as $salle_nom => $materiels):
                  $salle_id = 'salle_' . md5($client_nom . $site_nom . $salle_nom);
                  $accordion_id = 'accordion_' . $salle_id;
                  ?>
                  <div class="accordion mb-3" id="<?= $accordion_id ?>">
                    <div class="accordion-item">
                      <h2 class="accordion-header">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                          data-bs-target="#collapse_<?= $salle_id ?>">
                          <div class="d-flex justify-content-between w-100 me-3">
                            <span><i class="bi bi-door-open me-2 text-info"></i><strong>
                                <?= h($salle_nom) ?>
                              </strong></span>
                            <span class="badge bg-secondary ms-3">
                              <?= count($materiels) ?> équipement(s)
                            </span>

                          </div>
                        </button>
                      </h2>
                      <div id="collapse_<?= $salle_id ?>" class="accordion-collapse collapse"
                        data-bs-parent="#accordionContainer">
                        <div class="accordion-body p-0">
                          <div class="table-wrapper">
                            <div id="excelTable-<?= $salle_id ?>"></div>
                          </div>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>

  <!-- Modales -->
  <div class="modal fade" id="attachmentsModal" tabindex="-1" aria-labelledby="attachmentsModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="attachmentsModalLabel">Pièces jointes</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="attachmentsModalContent"></div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="previewAttachmentModal" tabindex="-1" aria-labelledby="previewAttachmentModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="previewAttachmentModalLabel"></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body" id="previewAttachmentModalBody"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
        </div>
      </div>
    </div>
  </div>

  <?php require_once __DIR__ . '/../../includes/FileUploadValidator.php'; ?>

  <div class="modal fade" id="addAttachmentModal" tabindex="-1" aria-labelledby="addAttachmentModalLabel"
    aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="dragDropForm" method="post" enctype="multipart/form-data">
          <?= csrf_field() ?>
          <div class="modal-header">
            <h5 class="modal-title" id="addAttachmentModalLabel"><i class="bi bi-cloud-upload me-2"></i>Ajouter des
              pièces jointes</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="drop-zone" id="dropZone">
              <div class="drop-message">
                <i class="bi bi-cloud-upload me-1"></i>Glissez-déposez vos fichiers ici<br>
                <small class="text-muted">ou cliquez pour sélectionner</small>
              </div>
              <input type="file" id="fileInput" multiple style="display: none;"
                accept="<?= FileUploadValidator::getAcceptAttribute($GLOBALS['db']) ?>">
              <div class="file-list" id="fileList"></div>
              <div class="stats" id="stats" style="display: none;">
                <div class="row">
                  <div class="col-6"><strong>Fichiers valides:</strong> <span id="validCount">0</span></div>
                  <div class="col-6"><strong>Fichiers rejetés:</strong> <span id="invalidCount">0</span></div>
                </div>
                <div class="progress-bar">
                  <div class="progress-fill" id="progressFill"></div>
                </div>
              </div>
            </div>
            <div id="filesOptions" style="display: none;">
              <h6 class="mt-3 mb-2">Options par fichier :</h6>
              <div id="filesOptionsList"></div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
            <button type="button" class="btn btn-warning" id="clearAllBtn" style="display: none;"><i
                class="bi bi-trash me-1"></i>Tout effacer</button>
            <button type="button" class="btn btn-primary" id="uploadValidBtn" style="display: none;"><i
                class="bi bi-upload me-1"></i>Uploader</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <style>
    body {
      background: #f4f6f9;
      font-family: "Segoe UI", sans-serif;
    }

    .card-body {
      overflow: hidden;
    }

    .table-wrapper {
      overflow-x: auto;
    }

    .handsontable {
      width: auto !important;
    }

    .handsontable th {
      background-color: #f1f3f5 !important;
      color: #495057;
      font-weight: 600;
      text-align: center;
    }

    .handsontable td {
      background-color: #ffffff;
      border-bottom: 1px solid #dee2e6;
      padding: 8px;
      vertical-align: middle;
    }

    /* Style de base pour toutes les cellules */
    .handsontable td {
      background-color: #ffffff;
      border-bottom: 1px solid #dee2e6;
      padding: 8px;
      vertical-align: middle;
    }

    /* Style pour la colonne Équipement (toujours à l'index 0 après rendu) */
    .handsontable td:first-child {
      background-color: #ffffff !important;
      color: #0d6efd !important;
      font-weight: 600;
    }

    /* Style pour toutes les cellules sauf la première et la dernière (pièces jointes) */
    .handsontable td:not(:first-child) {
      background-color: #f3e1b5 !important;
    }

    /* Style pour la colonne Pièces jointes (toujours à la dernière position après rendu) */
    .handsontable td:nth-child(8) {
      background-color: #f8f9fa !important;
      text-align: center;
    }

    /* Hover */
    .handsontable tbody tr:hover td {
      background-color: #eef3ff !important;
    }

    .handsontable tbody tr:hover td {
      background-color: #eef3ff !important;
    }

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
    }

    .file-item.valid {
      background-color: var(--bs-success-bg-subtle);
    }

    .file-item.invalid {
      background-color: var(--bs-danger-bg-subtle);
    }
  </style>

  <script>
    const baseUrl = '<?= BASE_URL ?>';
    let currentSearchTerm = '';
    let hotInstances = {};

    function updateSitesAndSubmit() {
      const clientId = document.getElementById('client_id').value;
      if (clientId) {
        fetch('<?= BASE_URL ?>materiel/get_sites?client_id=' + clientId)
          .then(res => res.json())
          .then(data => {
            const siteSelect = document.getElementById('site_id');
            siteSelect.innerHTML = '<option value="">Tous les sites</option>';
            data.forEach(site => {
              const option = document.createElement('option');
              option.value = site.id;
              option.textContent = site.name;
              siteSelect.appendChild(option);
            });
            document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
            document.getElementById('filterForm').submit();
          })
          .catch(err => console.error('Erreur:', err));
      } else {
        document.getElementById('site_id').innerHTML = '<option value="">Tous les sites</option>';
        document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
        document.getElementById('filterForm').submit();
      }
    }

    function updateRoomsAndSubmit() {
      const siteId = document.getElementById('site_id').value;
      if (siteId) {
        fetch('<?= BASE_URL ?>materiel/get_rooms?site_id=' + siteId)
          .then(res => res.json())
          .then(data => {
            const roomSelect = document.getElementById('salle_id');
            roomSelect.innerHTML = '<option value="">Toutes les salles</option>';
            data.forEach(room => {
              const option = document.createElement('option');
              option.value = room.id;
              option.textContent = room.name;
              roomSelect.appendChild(option);
            });
            document.getElementById('filterForm').submit();
          })
          .catch(err => console.error('Erreur:', err));
      } else {
        document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
        document.getElementById('filterForm').submit();
      }
    }

    function applyGlobalSearch() {
      const searchTerm = document.getElementById('globalSearch').value.toLowerCase();
      currentSearchTerm = searchTerm;

      const clearBtn = document.getElementById('clearGlobalSearch');
      clearBtn.style.display = searchTerm.length > 0 ? 'inline-block' : 'none';

      // Parcourir toutes les instances Handsontable
      Object.keys(hotInstances).forEach(tableId => {
        const hot = hotInstances[tableId];
        if (!hot) return;

        const data = hot.getData();
        const totalRows = data.length;

        for (let i = 0; i < totalRows; i++) {
          let rowMatches = false;
          const rowData = data[i];

          for (let j = 0; j < rowData.length; j++) {
            // Ignorer la colonne pièces jointes (index 7)
            if (j === 7) continue;
            const cellValue = rowData[j];
            if (cellValue && typeof cellValue === 'object') continue;
            if (cellValue && cellValue.toString().toLowerCase().includes(searchTerm)) {
              rowMatches = true;
              break;
            }
          }

          const visualRowIndex = hot.toVisualRow(i);
          const rowElement = hot.getCell(visualRowIndex, 0);
          if (rowElement) {
            const tr = rowElement.parentNode;
            if (tr) {
              tr.style.display = (!searchTerm || rowMatches) ? '' : 'none';
            }
          }
        }

        hot.render();
      });

      updateAccordionsVisibility(searchTerm);
    }

    function updateAccordionsVisibility(searchTerm) {
      if (!searchTerm || searchTerm.trim().length === 0) {
        document.querySelectorAll('.accordion-item').forEach(item => item.style.display = '');
        return;
      }

      document.querySelectorAll('.accordion-item').forEach(accordionItem => {
        const collapseDiv = accordionItem.querySelector('.accordion-collapse');
        if (!collapseDiv) return;

        const tableId = collapseDiv.id.replace('collapse_', 'excelTable-');
        const hot = hotInstances[tableId];
        let hasVisibleRow = false;

        if (hot) {
          const data = hot.getData();
          for (let i = 0; i < data.length; i++) {
            let rowMatches = false;
            for (let j = 0; j < data[i].length; j++) {
              if (j === 7) continue;
              const cellValue = data[i][j];
              if (cellValue && typeof cellValue !== 'object' && cellValue.toString().toLowerCase().includes(searchTerm)) {
                rowMatches = true;
                break;
              }
            }
            if (rowMatches) {
              hasVisibleRow = true;
              break;
            }
          }
        }

        accordionItem.style.display = hasVisibleRow ? '' : 'none';
      });
    }

    function openAllAccordions() {
      document.querySelectorAll('.accordion-collapse').forEach(collapse => {
        if (!collapse.classList.contains('show')) {
          new bootstrap.Collapse(collapse, { toggle: false }).show();
        }
      });
    }

    function closeAllAccordions() {
      document.querySelectorAll('.accordion-collapse.show').forEach(collapse => {
        bootstrap.Collapse.getInstance(collapse)?.hide();
      });
    }

    function saveColumnVisibility() {
      const state = {};
      document.querySelectorAll('.global-colvis-checkbox').forEach(cb => {
        state[parseInt(cb.dataset.col)] = cb.checked;
      });
      localStorage.setItem('materiel_columns_visibility', JSON.stringify(state));
    }

    function restoreColumnVisibility() {
      const saved = localStorage.getItem('materiel_columns_visibility');
      if (saved) {
        try {
          const state = JSON.parse(saved);
          document.querySelectorAll('.global-colvis-checkbox').forEach(cb => {
            const col = parseInt(cb.dataset.col);
            if (state.hasOwnProperty(col)) cb.checked = state[col];
          });
          return state;
        } catch (e) { console.error(e); }
      }
      return null;
    }

    function applyColumnVisibility(colIndex, isVisible) {
      Object.values(hotInstances).forEach(hot => {
        const plugin = hot.getPlugin('hiddenColumns');
        if (isVisible) plugin.showColumn(colIndex);
        else plugin.hideColumn(colIndex);
        hot.render();
      });
    }

    function openAttachmentsModal(materielId, materielName) {
      const modal = new bootstrap.Modal(document.getElementById('attachmentsModal'));
      document.getElementById('attachmentsModalLabel').textContent = `Pièces jointes - ${materielName}`;
      const content = document.getElementById('attachmentsModalContent');
      content.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary"></div><p class="mt-2">Chargement...</p></div>';
      document.getElementById('attachmentsModal').setAttribute('data-materiel-id', materielId);
      modal.show();
      loadAttachments(materielId, content);
    }

    function loadAttachments(materielId, container) {
      fetch('<?= BASE_URL ?>materiel/getAttachments/' + materielId)
        .then(res => res.json())
        .then(data => {
          if (data.success && data.attachments) renderAttachments(data.attachments, container, materielId);
          else container.innerHTML = `<div class="alert alert-danger">${data.error || 'Erreur de chargement'}</div>`;
        })
        .catch(err => { console.error(err); container.innerHTML = '<div class="alert alert-danger">Erreur de chargement</div>'; });
    }

    function renderAttachments(attachments, container, materielId) {
      let html = '<div class="mb-3">';
      if (attachments.length === 0) html += '<div class="text-center py-4"><i class="bi bi-inbox fs-1 text-muted"></i><p class="mt-3">Aucune pièce jointe</p></div>';
      else {
        attachments.sort((a, b) => new Date(b.date_creation) - new Date(a.date_creation));
        html += '<div class="list-group">';
        attachments.forEach(att => {
          const isPdf = att.type_fichier?.toLowerCase() === 'pdf';
          const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(att.type_fichier?.toLowerCase());
          const size = formatFileSize(att.taille_fichier || 0);
          const date = att.date_creation ? new Date(att.date_creation).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '-';
          html += `<div class="list-group-item ${att.masque_client == 1 ? 'bg-light-warning' : ''}">
        <div class="d-flex justify-content-between align-items-start">
          <div class="flex-grow-1">
            <div class="d-flex align-items-center mb-1">${att.masque_client == 1 ? '<i class="bi bi-eye-slash text-warning me-2"></i>' : ''}<strong>${escapeHtml(att.nom_fichier)}</strong></div>
            ${att.commentaire ? `<small class="text-muted d-block">${escapeHtml(att.commentaire)}</small>` : ''}
            <small class="text-muted">${size} • ${date}${att.created_by_name ? ' • ' + escapeHtml(att.created_by_name) : ''}</small>
          </div>
          <div class="ms-3">
            ${isPdf || isImage ? `<button class="btn btn-sm btn-outline-info me-1" onclick="previewAttachment(${att.id},'${escapeHtml(att.nom_fichier)}','${att.type_fichier}')"><i class="bi bi-eye"></i></button>` : ''}
            <a href="<?= BASE_URL ?>materiel/download/${att.id}" class="btn btn-sm btn-outline-success me-1"><i class="bi bi-download"></i></a>
            <a href="<?= BASE_URL ?>materiel/toggleAttachmentVisibility/${materielId}/${att.id}" class="btn btn-sm btn-outline-warning me-1"><i class="bi ${att.masque_client == 1 ? 'bi-eye' : 'bi-eye-slash'}"></i></a>
            <a href="<?= BASE_URL ?>materiel/deleteAttachment/${materielId}/${att.id}" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a>
          </div>
        </div>
      </div>`;
        });
        html += '</div>';
      }
      html += '</div><div class="d-flex justify-content-end"><button class="btn btn-primary" onclick="openAddAttachmentModal(' + materielId + ')"><i class="bi bi-plus me-1"></i>Ajouter</button></div>';
      container.innerHTML = html;
    }

    function previewAttachment(id, name, type) {
      const modal = new bootstrap.Modal(document.getElementById('previewAttachmentModal'));
      document.getElementById('previewAttachmentModalLabel').textContent = name;
      const body = document.getElementById('previewAttachmentModalBody');
      const ext = type?.toLowerCase() || '';
      if (ext === 'pdf') body.innerHTML = `<iframe src="<?= BASE_URL ?>materiel/preview/${id}" width="100%" height="600px" frameborder="0"></iframe>`;
      else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) body.innerHTML = `<img src="<?= BASE_URL ?>materiel/preview/${id}" class="img-fluid">`;
      else body.innerHTML = `<div class="alert alert-info">Prévisualisation non disponible. <a href="<?= BASE_URL ?>materiel/download/${id}" target="_blank">Télécharger</a></div>`;
      modal.show();
    }

    function formatFileSize(bytes) {
      if (!bytes) return '0 Bytes';
      const k = 1024, sizes = ['Bytes', 'KB', 'MB', 'GB'];
      const i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    function openAddAttachmentModal(materielId) {
      const modal = new bootstrap.Modal(document.getElementById('addAttachmentModal'));
      document.getElementById('addAttachmentModal').setAttribute('data-materiel-id', materielId);
      modal.show();
    }

    class DragDropUploader {
      constructor(materielId) {
        this.materielId = materielId;
        this.files = [];
        this.allowedExtensions = [];
        this.maxSize = parsePhpSize('<?php echo ini_get("upload_max_filesize"); ?>');
        this.dropZone = document.getElementById('dropZone');
        this.fileInput = document.getElementById('fileInput');
        this.fileList = document.getElementById('fileList');
        this.stats = document.getElementById('stats');
        this.validCount = document.getElementById('validCount');
        this.invalidCount = document.getElementById('invalidCount');
        this.progressFill = document.getElementById('progressFill');
        this.uploadBtn = document.getElementById('uploadValidBtn');
        this.clearBtn = document.getElementById('clearAllBtn');
        this.filesOptions = document.getElementById('filesOptions');
        this.filesOptionsList = document.getElementById('filesOptionsList');
        this.init();
      }

      async init() {
        try {
          const res = await fetch('<?= BASE_URL ?>settings/getAllowedExtensions');
          const data = await res.json();
          this.allowedExtensions = data.extensions || [];
        } catch (e) { console.error(e); }
        this.setupEvents();
      }

      setupEvents() {
        this.dropZone.addEventListener('dragover', e => { e.preventDefault(); this.dropZone.classList.add('dragover'); });
        this.dropZone.addEventListener('dragleave', e => { e.preventDefault(); this.dropZone.classList.remove('dragover'); });
        this.dropZone.addEventListener('drop', e => { e.preventDefault(); this.dropZone.classList.remove('dragover'); this.handleFiles(Array.from(e.dataTransfer.files)); });
        this.dropZone.addEventListener('click', () => this.fileInput.click());
        this.fileInput.addEventListener('change', e => this.handleFiles(Array.from(e.target.files)));
        this.uploadBtn.addEventListener('click', () => this.upload());
        this.clearBtn.addEventListener('click', () => this.clearAll());
      }

      handleFiles(newFiles) {
        this.files.push(...this.validateFiles(newFiles));
        this.render();
      }

      validateFiles(files) {
        return files.map(f => {
          const ext = f.name.split('.').pop().toLowerCase();
          const validExt = this.allowedExtensions.includes(ext);
          const validSize = f.size <= this.maxSize;
          let error = null;
          if (!validSize) error = `Trop volumineux (${this.formatFileSize(f.size)}). Max: ${this.formatFileSize(this.maxSize)}`;
          else if (!validExt) error = 'Format non accepté';
          return { file: f, isValid: validExt && validSize, error };
        });
      }

      render() {
        this.fileList.innerHTML = '';
        this.files.forEach((f, i) => {
          const div = document.createElement('div');
          div.className = `file-item ${f.isValid ? 'valid' : 'invalid'}`;
          div.innerHTML = `<span class="file-name">${f.file.name}</span>
        <span class="file-size">${this.formatFileSize(f.file.size)}</span>
        ${f.error ? `<span class="error-message">${f.error}</span>` : ''}
        <button class="remove-file btn btn-sm btn-link" onclick="uploader.removeFile(${i})">×</button>`;
          this.fileList.appendChild(div);
        });
        this.updateStats();
        this.updateOptions();
      }

      updateOptions() {
        const valid = this.files.filter(f => f.isValid);
        if (valid.length) {
          this.filesOptions.style.display = 'block';
          this.filesOptionsList.innerHTML = '';
          valid.forEach((f, i) => {
            const div = document.createElement('div');
            div.className = 'file-options mb-2 p-2 border rounded';
            div.innerHTML = `<div class="row align-items-center">
          <div class="col-md-8"><strong>${f.file.name}</strong><input type="text" class="form-control form-control-sm mt-1" name="desc_${i}" placeholder="Description"></div>
          <div class="col-md-4"><div class="form-check"><input class="form-check-input" type="checkbox" name="hide_${i}" value="1" id="hide_${i}"><label for="hide_${i}"><i class="bi bi-eye-slash me-1"></i>Masquer client</label></div></div>
        </div>`;
            this.filesOptionsList.appendChild(div);
          });
        } else this.filesOptions.style.display = 'none';
      }

      updateStats() {
        const valid = this.files.filter(f => f.isValid).length;
        const invalid = this.files.length - valid;
        this.validCount.textContent = valid;
        this.invalidCount.textContent = invalid;
        if (this.files.length) {
          this.stats.style.display = 'block';
          this.uploadBtn.style.display = 'inline-block';
          this.clearBtn.style.display = 'inline-block';
          this.progressFill.style.width = (valid / this.files.length * 100) + '%';
        } else {
          this.stats.style.display = 'none';
          this.uploadBtn.style.display = 'none';
          this.clearBtn.style.display = 'none';
        }
      }

      removeFile(index) {
        this.files.splice(index, 1);
        this.render();
      }

      clearAll() {
        this.files = [];
        this.render();
        this.fileInput.value = '';
      }

      formatFileSize(bytes) {
        if (!bytes) return '0 Bytes';
        const k = 1024, sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
      }

      async upload() {
        const valid = this.files.filter(f => f.isValid);
        if (!valid.length) return alert('Aucun fichier valide');
        const fd = new FormData();
        fd.append('materiel_id', this.materielId);
        valid.forEach((f, i) => {
          fd.append(`files[${i}]`, f.file);
          const desc = document.querySelector(`input[name="desc_${i}"]`);
          const hide = document.querySelector(`input[name="hide_${i}"]`);
          if (desc?.value) fd.append(`descriptions[${i}]`, desc.value);
          if (hide?.checked) fd.append(`masque_client[${i}]`, '1');
        });
        this.uploadBtn.disabled = true;
        this.uploadBtn.innerHTML = '<i class="bi bi-arrow-clockwise spin me-1"></i>Upload...';
        try {
          const res = await fetch('<?= BASE_URL ?>materiel/uploadAttachment', {
            method: 'POST',
            headers: { 'X-CSRF-Token': '<?= csrf_token() ?>' },
            body: fd
          });
          const result = await res.json();
          if (result.success) {
            alert('Upload réussi !');
            bootstrap.Modal.getInstance(document.getElementById('addAttachmentModal')).hide();
            location.reload();
          } else alert('Erreur: ' + (result.error || 'Inconnue'));
        } catch (e) { console.error(e); alert('Erreur réseau'); }
        finally {
          this.uploadBtn.disabled = false;
          this.uploadBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Uploader';
        }
      }
    }

    function parsePhpSize(size) {
      const units = { K: 1024, M: 1048576, G: 1073741824 };
      const match = String(size).match(/^(\d+)([KMG])?$/i);
      if (!match) return 0;
      return parseInt(match[1]) * (units[match[2]?.toUpperCase()] || 1);
    }

    let uploader;
    document.getElementById('addAttachmentModal').addEventListener('shown.bs.modal', function () {
      const id = this.getAttribute('data-materiel-id');
      if (id) uploader = new DragDropUploader(id);
    });
    document.getElementById('addAttachmentModal').addEventListener('hidden.bs.modal', function () {
      if (uploader) uploader.clearAll();
    });

    document.addEventListener('DOMContentLoaded', function () {
      <?php if (!empty($filters['client_id']) && !empty($materiel_organise)): ?>
        <?php foreach ($materiel_organise as $client_nom => $sites): ?>
          <?php foreach ($sites as $site_nom => $salles): ?>
            <?php foreach ($salles as $salle_nom => $materiels):
              $salle_id = 'salle_' . md5($client_nom . $site_nom . $salle_nom);
              ?>
                (function () {
                  const container = document.getElementById('excelTable-<?= $salle_id ?>');
                  const data = <?= json_encode(array_map(function ($m) use ($allColumns, $pieces_jointes_count) {
                    return array_map(function ($col) use ($m, $pieces_jointes_count) {
                      if ($col['field'] === 'equipement')
                        return ($m['marque'] ?? '') . "\n" . ($m['modele'] ?? '');
                      if ($col['field'] === 'pieces_jointes')
                        return ['count' => $pieces_jointes_count[$m['id']] ?? 0, 'id' => $m['id'], 'name' => ($m['marque'] ?? '') . ' ' . ($m['modele'] ?? '')];
                      return $m[$col['field']] ?? '';
                    }, $allColumns);
                  }, $materiels)); ?>;

                  const hot = new Handsontable(container, {
                    data: data,
                    colHeaders: <?= json_encode($colHeaders) ?>,
                    hiddenColumns: { columns: <?= json_encode($hiddenColumns) ?>, indicators: true },
                    rowHeaders: false,
                    licenseKey: 'non-commercial-and-evaluation',
                    stretchH: 'all',
                    height: 300,
                    cells: function (row, col) {
                      const header = this.colHeaders[col];
                      if (header === 'Équipement') {
                        return {
                          renderer: function (instance, td, row, col, prop, value) {
                            const parts = (value || '').split('\n');
                            td.innerHTML = parts[0] + '<br><small>' + (parts[1] || '') + '</small>';
                          }
                        };
                      }
                      if (header === 'Pièces jointes') {
                        return {
                          renderer: function (instance, td, row, col, prop, value) {
                            const count = value?.count ?? 0;
                            const id = value?.id;
                            const name = value?.name ?? '';
                            td.innerHTML = `<button class="flex gap-4 btn btn-sm ${count > 0 ? 'btn-outline-info' : 'btn-outline-secondary'}" onclick="openAttachmentsModal(${id}, '${name.replace(/'/g, "\\'")}')"><i class="bi bi-paperclip"></i><span class="badge ${count > 0 ? 'bg-info' : 'bg-secondary'}">${count}</span></button>`;
                    td.style.textAlign = 'center';
                  }
                };
              }
              return {};
            }
          });
          hotInstances['excelTable-<?= $salle_id ?>'] = hot;
        })();
      <?php endforeach; ?>
      <?php endforeach; ?>
      <?php endforeach; ?>
      <?php endif; ?>

      const saved = restoreColumnVisibility();
      if (saved) Object.keys(saved).forEach(col => applyColumnVisibility(parseInt(col), saved[col]));

      const searchInput = document.getElementById('globalSearch');
      const clearBtn = document.getElementById('clearGlobalSearch');
      const openBtn = document.getElementById('openAllAccordions');
      const closeBtn = document.getElementById('closeAllAccordions');

      if (searchInput) searchInput.addEventListener('keyup', applyGlobalSearch);
      if (clearBtn) clearBtn.addEventListener('click', () => { searchInput.value = ''; applyGlobalSearch(); });
      if (openBtn) openBtn.addEventListener('click', openAllAccordions);
      if (closeBtn) closeBtn.addEventListener('click', closeAllAccordions);

      document.querySelectorAll('.global-colvis-checkbox').forEach(cb => {
        cb.addEventListener('change', function () {
          const col = parseInt(this.dataset.col);
          const visible = this.checked;
          Object.values(hotInstances).forEach(hot => {
            const plugin = hot.getPlugin('hiddenColumns');
            if (visible) plugin.showColumn(col);
            else plugin.hideColumn(col);
            hot.render();
          });
          saveColumnVisibility();
        });
      });
    });
  </script>
  <script>
    window.saveAllTablesData = function () {
      let totalSaved = 0;
      let totalErrors = 0;
      const savePromises = [];

      Object.keys(hotInstances).forEach(tableId => {
        const hot = hotInstances[tableId];
        if (!hot) return;

        const allData = hot.getData();

        // 🔴 garder seulement lignes avec ID
        const validData = allData.filter(row => {
          return row[17] !== null && row[17] !== undefined && row[17] !== '';
        });

        if (validData.length === 0) return;

        const formattedData = validData.map(row => {
          console.log("DEBUG ROW", row);

          return {
            id: row[17] ?? null,
            marque: row[10] ?? null,
            type_nom: row[1] ?? null,
            numero_serie: row[2] ?? null,
            version_firmware: row[3] ?? null,
            adresse_ip: row[4] ?? null,
            adresse_mac: row[5] ?? null,
            date_fin_maintenance: row[6] || null,
            reference: row[8] ?? null,
            usage_materiel: row[9] ?? null,
            modele: row[11] ?? null,
            ancien_firmware: row[12] ?? null,
            masque: row[13] ?? null,
            passerelle: row[14] ?? null,
            login: row[15] ?? null,
            password: row[16] ?? null,
            ip_primaire: row[18] ?? null,
            mac_primaire: row[19] ?? null,
            ip_secondaire: row[20] ?? null,
            mac_secondaire: row[21] ?? null,
            stream_aes67_recu: row[22] ?? null,
            stream_aes67_transmis: row[23] ?? null,
            ssid: row[24] ?? null,
            type_cryptage: row[25] ?? null,
            password_wifi: row[26] ?? null,
            libelle_pa_salle: row[27] ?? null,
            numero_port_switch: row[28] ?? null,
            vlan: row[29] ?? null,
            date_fin_garantie: row[30] || null,
            date_derniere_inter: row[31] || null,
            commentaire: row[32] ?? null,
            url_github: row[33] ?? null
          };
        });

        console.log("DATA ENVOYÉE", formattedData);

        const promise = fetch('<?= BASE_URL ?>views/excel/excel_save.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': '<?= csrf_token() ?>'
          },
          body: JSON.stringify({
            table_id: tableId,
            salle_id: 23,
            data: formattedData
          })
        })
          .then(response => response.json())
          .then(result => {
            if (result.status === 'success' || result.status === 'partial') {
              totalSaved++;
              console.log(`${tableId}: ${result.message}`);
            } else {
              totalErrors++;
              console.error(`${tableId}:`, result.message);
            }
          })
          .catch(error => {
            totalErrors++;
            console.error(`${tableId}:`, error);
          });

        savePromises.push(promise);
      });

      if (savePromises.length === 0) {
        alert('Aucune donnée à sauvegarder');
        return;
      }

      Promise.all(savePromises).then(() => {
        alert(`Sauvegarde terminée : ${totalSaved} tableau(x), ${totalErrors} erreur(s)`);
      });
    };
  </script>
  <style>
    @keyframes spin {
      from {
        transform: rotate(0deg);
      }

      to {
        transform: rotate(360deg);
      }
    }

    .bi-arrow-clockwise.spin {
      animation: spin 1s linear infinite;
      display: inline-block;
    }

    .btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }
  </style>
</body>

</html>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
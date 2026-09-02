<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controllers/MaterielController.php';
/**
 * Vue de la liste du matériel
 * Affiche la liste du matériel regroupé par site/salle avec filtres
 * La recherche globale se fait désormais en AJAX (voir materiel/search_api),
 * sans rechargement de la page.
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
$isGlobalSearch = $isGlobalSearch ?? false;
$globalSearchTerm = $globalSearchTerm ?? '';

// Définir les breadcrumbs personnalisés pour la page matériel index
if (isset($filters) && !empty($filters)) {
  $GLOBALS['customBreadcrumbs'] = generateMaterielIndexBreadcrumbs($filters, $clients, $sites, $salles);
}
$hasAnyFilter = $isGlobalSearch
  || !empty($filters['client_id'])
  || !empty($filters['site_id'])
  || !empty($filters['building_id'])
  || !empty($filters['salle_id']);

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer les statistiques
$stats = [];
if (isset($materielModel)) {
  $stats = $materielModel->getStats();
}

// Organiser le matériel par client -> site -> bâtiment -> salle
$materiel_organise = [];
$salle_ids_map = [];
foreach ($materiel_list as $materiel) {
  $client_nom = $materiel['client_nom'] ?? 'Sans client';
  $site_nom = $materiel['site_nom'] ?? 'Sans site';
  $building_nom = $materiel['building_nom'] ?? 'Sans bâtiment';
  $salle_nom = $materiel['salle_nom'] ?? 'Sans salle';

  if (!isset($materiel_organise[$client_nom]))
    $materiel_organise[$client_nom] = [];
  if (!isset($materiel_organise[$client_nom][$site_nom]))
    $materiel_organise[$client_nom][$site_nom] = [];
  if (!isset($materiel_organise[$client_nom][$site_nom][$building_nom]))
    $materiel_organise[$client_nom][$site_nom][$building_nom] = [];
  if (!isset($materiel_organise[$client_nom][$site_nom][$building_nom][$salle_nom]))
    $materiel_organise[$client_nom][$site_nom][$building_nom][$salle_nom] = [];

  $materiel_organise[$client_nom][$site_nom][$building_nom][$salle_nom][] = $materiel;

  $salle_key = md5($client_nom . $site_nom . $building_nom . $salle_nom);
  if (!empty($materiel['salle_id'])) {
    $salle_ids_map[$salle_key] = $materiel['salle_id'];
  }
}
if (!empty($filters['salle_id'])) {
  $salleDejaPresente = in_array($filters['salle_id'], $salle_ids_map);
  if (!$salleDejaPresente) {
    $selectedSalle = null;
    foreach ($salles as $s) {
      if ($s['id'] == $filters['salle_id']) {
        $selectedSalle = $s;
        break;
      }
    }
    if ($selectedSalle) {
      $selectedClient = null;
      foreach ($clients as $c) {
        if ($c['id'] == ($filters['client_id'] ?? null)) {
          $selectedClient = $c;
          break;
        }
      }
      $selectedSite = null;
      foreach ($sites as $s) {
        if ($s['id'] == ($filters['site_id'] ?? null)) {
          $selectedSite = $s;
          break;
        }
      }
      $selectedBuilding = null;
      if (!empty($buildings)) {
        foreach ($buildings as $b) {
          if ($b['id'] == ($filters['building_id'] ?? null)) {
            $selectedBuilding = $b;
            break;
          }
        }
      }

      $client_nom = $selectedClient['name'] ?? 'Sans client';
      $site_nom = $selectedSite['name'] ?? 'Sans site';
      $building_nom = $selectedBuilding['name'] ?? 'Sans bâtiment';
      $salle_nom = $selectedSalle['name'] ?? 'Sans salle';

      $materiel_organise[$client_nom][$site_nom][$building_nom][$salle_nom] = [];
      $salle_ids_map[md5($client_nom . $site_nom . $building_nom . $salle_nom)] = $selectedSalle['id'];
    }
  }
}
// Définir toutes les colonnes disponibles avec leurs configurations
$allColumns = [
  ['label' => 'Marque', 'field' => 'marque', 'default' => true],
  ['label' => 'Modèle', 'field' => 'modele', 'default' => true],
  ['label' => 'Type', 'field' => 'type_materiel', 'default' => true],
  ['label' => 'S/N', 'field' => 'numero_serie', 'default' => true],
  ['label' => 'Firmware', 'field' => 'version_firmware', 'default' => true],
  ['label' => 'IP', 'field' => 'adresse_ip', 'default' => true],
  ['label' => 'MAC', 'field' => 'adresse_mac', 'default' => true],
  ['label' => 'Expiration', 'field' => 'date_fin_maintenance', 'default' => true],
  ['label' => 'Pièces jointes', 'field' => 'pieces_jointes', 'default' => true],
  ['label' => 'Référence', 'field' => 'reference', 'default' => false],
  ['label' => 'Usage', 'field' => 'usage_materiel', 'default' => false],
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

// Calcul des index pour JavaScript
$marqueIndex = array_search('marque', array_column($allColumns, 'field'));
$modeleIndex = array_search('modele', array_column($allColumns, 'field'));
$idIndex = array_search('id', array_column($allColumns, 'field'));
$piecesJointesIndex = array_search('pieces_jointes', array_column($allColumns, 'field'));

/**
 * Petit helper local pour générer le bloc HTML d'un accordéon "client"
 * (utilisé pour l'affichage initial côté PHP — la recherche AJAX régénère
 * la même structure côté JS via renderSearchResults()).
 */
function renderMaterielAccordions(array $materiel_organise, array $salle_ids_map = []): void
{
  foreach ($materiel_organise as $client_nom => $sites): ?>
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
        <?php foreach ($sites as $site_nom => $buildings): ?>
          <?php foreach ($buildings as $building_nom => $salles): ?>
            <?php foreach ($salles as $salle_nom => $materiels):
              $salle_key = md5($client_nom . $site_nom . $building_nom . $salle_nom);
              $salle_id = 'salle_' . $salle_key;
              $accordion_id = 'accordion_' . $salle_id;
              $locationString = h($site_nom) . ' - ' . h($building_nom) . ' - ' . h($salle_nom);
              $dbSalleId = $materiels[0]['salle_id'] ?? ($salle_ids_map[$salle_key] ?? null);
              ?>
              <div class="accordion mb-3" id="<?= $accordion_id ?>">
                <div class="accordion-item">
                  <h2 class="accordion-header">
                    <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                      data-bs-target="#collapse_<?= $salle_id ?>">
                      <div class="d-flex justify-content-between w-100 me-3 align-items-center">
                        <span>
                          <i class="bi bi-door-open me-2 text-info"></i>
                          <strong>
                            <?= $locationString ?>
                          </strong>
                        </span>
                        <span class="badge bg-secondary ms-3">
                          <?= count($materiels) ?> équipement(s)
                        </span>
                      </div>
                    </button>
                  </h2>
                  <div id="collapse_<?= $salle_id ?>" class="accordion-collapse collapse" data-bs-parent="#accordionContainer">
                    <div class="accordion-body p-0">
                      <div class="d-flex justify-content-end p-2 border-bottom bg-light">
                        <button type="button" class="btn btn-sm btn-success"
                          onclick="addNewRowToTable('excelTable-<?= $salle_id ?>', '<?= addslashes($locationString) ?>', <?= json_encode($dbSalleId) ?>)">
                          <i class="bi bi-plus-circle me-1"></i>Ajouter un équipement
                        </button>
                      </div>
                      <div class="table-wrapper">
                        <div id="excelTable-<?= $salle_id ?>"></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </div>
  <?php endforeach;
}

/**
 * Émet le JS d'initialisation Handsontable (appels à createSalleTable) pour
 * la structure $materiel_organise donnée. Doit être appelé DANS un <script>.
 */
function renderMaterielTableInitJs(array $materiel_organise, array $pieces_jointes_count, array $allColumns, array $salle_ids_map = []): void
{
  foreach ($materiel_organise as $client_nom => $sites):
    foreach ($sites as $site_nom => $buildings):
      foreach ($buildings as $building_nom => $salles):
        foreach ($salles as $salle_nom => $materiels):
          $salle_id = 'salle_' . md5($client_nom . $site_nom . $building_nom . $salle_nom);
          $dbSalleId = $materiels[0]['salle_id'] ?? ($salle_ids_map[md5($client_nom . $site_nom . $building_nom . $salle_nom)] ?? null);
          $rows = array_map(function ($m) use ($allColumns, $pieces_jointes_count) {
            $rowData = [];
            foreach ($allColumns as $col) {
              if ($col['field'] === 'pieces_jointes') {
                $rowData[] = [
                  'count' => $pieces_jointes_count[$m['id']] ?? 0,
                  'id' => $m['id'],
                  'name' => ($m['marque'] ?? '') . ' ' . ($m['modele'] ?? ''),
                ];
              } else {
                $rowData[] = $m[$col['field']] ?? '';
              }
            }
            return $rowData;
          }, $materiels);
          ?>
          createSalleTable(
          <?= json_encode('excelTable-' . $salle_id) ?>,
          <?= json_encode($rows) ?>,
          <?= json_encode($dbSalleId) ?>);
          <?php
        endforeach;
      endforeach;
    endforeach;
  endforeach;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.css">
  <script src="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css">
  <script src="https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js"></script>
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
        <div class="row mb-3">
          <div class="col-md-12">
            <div class="input-group">
              <span class="input-group-text bg-primary text-white">
                <i class="bi bi-search"></i>
              </span>
              <input type="text" class="form-control form-control-lg " id="globalSearch"
                style="border-top-right-radius:8px; border-bottom-right-radius:8px;"
                placeholder="Rechercher dans TOUT le matériel (marque, modèle, série, IP, MAC, client, site, salle...)"
                autocomplete="off" value="<?= h($globalSearchTerm) ?>">

              <button class="btn btn-outline-secondary" type="button" id="clearGlobalSearch"
                style="display: <?= $isGlobalSearch ? 'inline-block' : 'none' ?>;">
                <i class="bi bi-x-lg"></i>
              </button>
            </div>
            <?php if ($isGlobalSearch): ?>
              <div class="mt-2" id="globalSearchInfo">
                <span class="badge bg-info text-white">
                  <i class="bi bi-search me-1"></i>
                  <?= count($materiel_list) ?> résultat(s)
                </span>
                <span class="text-muted ms-2">Recherche globale : "<strong>
                    <?= h($globalSearchTerm) ?>
                  </strong>"</span>
              </div>
            <?php else: ?>
              <div class="mt-2" id="globalSearchInfo" style="display:none;"></div>
            <?php endif; ?>
          </div>
        </div>

        <form method="get" action="" id="filterForm">
          <div class="row g-3 align-items-end">
            <div class="col-md-2">
              <label for="client_id" class="form-label fw-bold mb-0">Client</label>
              <select class="form-select bg-body text-body" id="client_id" name="client_id">
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
            <div class="col-md-4 d-flex justify-content-end gap-2">
              <a href="<?= BASE_URL ?>materiel" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg me-1"></i>Réinitialiser
              </a>
            </div>
          </div>
        </form>
      </div>
    </div>

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

        min-width: 100px !important;
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

    <div class="card mb-4" id="columnControlsCard" style="display: <?= $hasAnyFilter ? 'block' : 'none' ?>;">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-md-12 text-end">
            <div class="d-flex gap-2 justify-content-end align-items-end">
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
    <div id="materielResultsContainer">
      <?php if (!$hasAnyFilter): ?>
        <div class="card">
          <div class="card-body text-center py-5">
            <i class="fas fa-filter fa-3x text-muted mb-3"></i>
            <h5 class="text-muted">Sélectionnez un filtre pour voir le matériel</h5>
            <p class="text-muted mb-3">Choisissez un client, un site, un bâtiment ou une salle
              ci-dessus pour afficher le matériel correspondant, ou utilisez la recherche globale.</p>
          </div>
        </div>
      <?php elseif (!empty($materiel_organise)): ?>
        <div id="accordionContainer">
          <?php renderMaterielAccordions($materiel_organise); ?>
        </div>
      <?php else: ?>
        <div class="card">
          <div class="card-body text-center py-5">
            <i class="bi bi-hdd-network fa-3x text-muted mb-3 me-1"></i>
            <h5 class="text-muted">Aucun matériel trouvé</h5>
            <p class="text-muted mb-3">
              <?php if ($isGlobalSearch): ?>
                Aucun équipement ne correspond à votre recherche "<strong>
                  <?= h($globalSearchTerm) ?>
                </strong>".
              <?php else: ?>
                Aucun matériel ne correspond aux critères sélectionnés.
              <?php endif; ?>
            </p>
            <a href="<?= BASE_URL ?>materiel/add<?= !empty($addParams) ? '?' . http_build_query($addParams) : '' ?>"
              class="btn btn-primary">
              <i class="bi bi-plus me-2 me-1"></i>Ajouter du Matériel
            </a>
          </div>
        </div>
      <?php endif; ?>
    </div>
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

    .handsontable td:nth-child(2) {
      background-color: #ffffff !important;
      color: #000000 !important;
      font-weight: normal;
    }

    .handsontable td {
      background-color: #ffffff;
      border-bottom: 1px solid #dee2e6;
      padding: 8px;
      vertical-align: middle;
    }

    .handsontable td:not(:first-child) {
      background-color: #f3e1b5 !important;
    }

    .handsontable td:nth-child(7) {
      background-color: #f8f9fa !important;
      text-align: center;
    }

    .handsontable tbody tr:hover td {
      background-color: #eef3ff !important;
    }

    .handsontable td:first-child {
      background-color: #f8f9fa !important;
      text-align: center;
      vertical-align: middle;
      width: 100px;
      min-width: 100px;
    }

    .handsontable td:first-child button {
      white-space: nowrap;
      font-size: 12px;
      padding: 4px 8px;
    }

    .handsontable col:first-child {
      width: 100px;
    }

    .handsontable td.htInvalid {
      background-color: #ffe0e0 !important;
      border: 1px solid #dc3545 !important;
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
    let hotInstances = {};

    const MARQUE_INDEX = <?= $marqueIndex ?>;
    const MODELE_INDEX = <?= $modeleIndex ?>;
    const ID_INDEX = <?= $idIndex ?>;
    const PIECES_JOINTES_INDEX = <?= $piecesJointesIndex ?>;
    const FIELD_VALIDATORS = {
      date_fin_maintenance: { regex: /^\d{4}-\d{2}-\d{2}$/, label: 'Expiration', example: '2026-12-31' },
      date_fin_garantie: { regex: /^\d{4}-\d{2}-\d{2}$/, label: 'Date Garantie', example: '2026-12-31' },
      date_derniere_inter: { regex: /^\d{4}-\d{2}-\d{2}$/, label: 'Dernière Inter', example: '2026-12-31' },
    };
    const allColumnFields = <?= json_encode(array_column($allColumns, 'field')) ?>;
    const colHeadersGlobal = <?= json_encode($colHeaders) ?>;
    const DEFAULT_HIDDEN_COLUMNS = <?= json_encode($hiddenColumns) ?>;

    function validateRow(row, rowIndex) {
      const errors = [];
      allColumnFields.forEach((field, colIndex) => {
        const rule = FIELD_VALIDATORS[field];
        if (!rule) return;
        const value = row[colIndex];
        if (!value || value === '') return;
        if (!rule.regex.test(value)) {
          errors.push(`Ligne ${rowIndex + 1} — <strong>${rule.label}</strong> : "<em>${value}</em>" invalide (ex: ${rule.example})`);
        }
      });
      return errors;
    }

    function showToast(message, type) {
      document.querySelectorAll('.custom-toast').forEach(t => t.remove());
      const toastDiv = document.createElement('div');
      toastDiv.className = 'custom-toast position-fixed top-0 end-0 m-3';
      toastDiv.style.cssText = 'z-index:9999;min-width:300px;max-width:400px;border-radius:8px;box-shadow:0 4px 12px rgba(0,0,0,0.15);padding:12px 16px;';
      const colors = {
        success: { bg: '#d4edda', border: '#28a745', icon: '#28a745' },
        danger: { bg: '#f8d7da', border: '#dc3545', icon: '#dc3545' },
        info: { bg: '#d1ecf1', border: '#17a2b8', icon: '#17a2b8' }
      };
      const color = colors[type] || colors.info;
      toastDiv.style.backgroundColor = color.bg;
      toastDiv.style.borderLeft = `4px solid ${color.border}`;
      toastDiv.innerHTML = `
        <div class="d-flex align-items-center">
          <div class="me-3" style="color:${color.icon}">
            <i class="bi ${type === 'success' ? 'bi-check-circle-fill' : type === 'danger' ? 'bi-x-circle-fill' : 'bi-info-circle-fill'} fs-4"></i>
          </div>
          <div class="flex-grow-1" style="font-size:14px;">
            <strong>${type === 'success' ? 'Succès' : type === 'danger' ? 'Erreur' : 'Information'}</strong>
            <div class="mt-1">${message}</div>
          </div>
          <button type="button" class="btn-close ms-2" style="font-size:12px;"
                  onclick="this.closest('.custom-toast').remove()"></button>
        </div>`;
      document.body.appendChild(toastDiv);
      const duration = type === 'danger' ? 7000 : 4000;
      setTimeout(() => {
        if (toastDiv.parentNode) {
          toastDiv.style.opacity = '0';
          toastDiv.style.transition = 'opacity 0.3s ease';
          setTimeout(() => toastDiv.remove(), 300);
        }
      }, duration);
    }

    function escapeHtml(text) {
      const d = document.createElement('div');
      d.textContent = text ?? '';
      return d.innerHTML;
    }

    function addNewRowToTable(tableId, locationName, salleId) {
      const hot = hotInstances[tableId];
      if (!hot) return;
      hot.__salleId = salleId;
      const existingData = hot.getSourceData();
      const data = existingData.map(row => row.map(cell =>
        (cell && typeof cell === 'object') ? { ...cell } : cell
      ));
      const colCount = allColumnFields.length;
      const newRow = Array(colCount).fill('');
      newRow[PIECES_JOINTES_INDEX] = { count: 0, id: null, name: '' };
      data.push(newRow);
      hot.loadData(data);
      const newRowIndex = data.length - 1;
      hot.scrollViewportTo(newRowIndex, 0);
      hot.selectCell(newRowIndex, 0);
      showToast('Nouvelle ligne ajoutée. Remplissez les informations puis sauvegardez.', 'info');
    }

    function submitFilters() {
      const clientId = document.getElementById('client_id').value;
      const siteId = document.getElementById('site_id').value;
      const buildingId = document.getElementById('building_id').value;
      const salleId = document.getElementById('salle_id').value;
      let url = baseUrl + 'materiel?';
      const params = [];
      if (clientId) params.push('client_id=' + clientId);
      if (siteId) params.push('site_id=' + siteId);
      if (buildingId) params.push('building_id=' + buildingId);
      if (salleId) params.push('salle_id=' + salleId);
      window.location.href = url + params.join('&');
    }
    function refreshFilterOptions() {
      const clientId = tomSelects['client_id'] ? tomSelects['client_id'].getValue() : '';
      const siteId = tomSelects['site_id'] ? tomSelects['site_id'].getValue() : '';
      const buildingId = tomSelects['building_id'] ? tomSelects['building_id'].getValue() : '';
      const salleId = tomSelects['salle_id'] ? tomSelects['salle_id'].getValue() : '';

      {
        const params = new URLSearchParams();
        if (siteId) params.set('site_id', siteId);
        if (buildingId) params.set('building_id', buildingId);
        if (salleId) params.set('salle_id', salleId);
        loadOptionsInto('client_id', baseUrl + 'materiel/get_all_clients?' + params.toString(),
          r => ({ value: r.id, text: r.name }), clientId);
      }
      {
        const params = new URLSearchParams();
        if (clientId) params.set('client_id', clientId);
        if (buildingId) params.set('building_id', buildingId);
        if (salleId) params.set('salle_id', salleId);
        loadOptionsInto('site_id', baseUrl + 'materiel/get_all_sites?' + params.toString(),
          r => ({ value: r.id, text: r.name, client_id: r.client_id, client_name: r.client_name }), siteId);
      }
      {
        const params = new URLSearchParams();
        if (clientId) params.set('client_id', clientId);
        if (siteId) params.set('site_id', siteId);
        if (salleId) params.set('salle_id', salleId);
        loadOptionsInto('building_id', baseUrl + 'materiel/get_all_buildings?' + params.toString(),
          r => ({ value: r.id, text: r.name, site_id: r.site_id, site_name: r.site_name, client_id: r.client_id, client_name: r.client_name }), buildingId);
      }
      {
        const params = new URLSearchParams();
        if (clientId) params.set('client_id', clientId);
        if (siteId) params.set('site_id', siteId);
        if (buildingId) params.set('building_id', buildingId);
        loadOptionsInto('salle_id', baseUrl + 'materiel/get_all_rooms?' + params.toString(),
          r => ({ value: r.id, text: r.name, building_id: r.building_id, building_name: r.building_name, site_id: r.site_id, site_name: r.site_name, client_id: r.client_id, client_name: r.client_name }), salleId);
      }
    }
    function onFilterChange() {
      refreshFilterOptions();

      const searchInput = document.getElementById('globalSearch');
      const term = searchInput ? searchInput.value.trim() : '';
      if (term) {
        if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
        performGlobalSearchAjax(term);
      } else {
        submitFilters();
      }
    }
    const tomSelects = {};

    function formatLocationOption(mainLabel, contextParts) {
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

      let resizer = dropdown.querySelector(
        '.filter-dropdown-resizer'
      );

      if (!resizer) {

        resizer = document.createElement('div');

        resizer.className = 'filter-dropdown-resizer';

        dropdown.appendChild(resizer);
      }

      /*
       * Dimensions sauvegardées
       */
      const savedWidth = localStorage.getItem(
        'filter-dropdown-width-' + fieldId
      );

      const savedHeight = localStorage.getItem(
        'filter-dropdown-height-' + fieldId
      );

      /*
       * Dimensions par défaut
       * utilisées uniquement au premier chargement
       */
      const DEFAULT_WIDTH = 350;
      const DEFAULT_HEIGHT = 300;

      dropdown.style.setProperty(
        'width',
        (savedWidth || DEFAULT_WIDTH) + 'px',
        'important'
      );

      dropdown.style.setProperty(
        'height',
        (savedHeight || DEFAULT_HEIGHT) + 'px',
        'important'
      );

      /*
       * Éviter de créer plusieurs événements
       */
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

          const deltaX = moveEvent.clientX - startX;
          const deltaY = moveEvent.clientY - startY;

          let newWidth = startWidth + deltaX;
          let newHeight = startHeight + deltaY;
          /*
           * Taille minimale
           */
          newWidth = Math.max(100, newWidth);
          newHeight = Math.max(50, newHeight);

          /*
           * Taille maximale selon l'écran
           */
          const rect = dropdown.getBoundingClientRect();

          const maxWidth =
            window.innerWidth - rect.left - 20;

          const maxHeight =
            window.innerHeight - rect.top - 20;

          newWidth = Math.min(newWidth, maxWidth);
          newHeight = Math.min(newHeight, maxHeight);

          dropdown.style.setProperty(
            'width',
            newWidth + 'px',
            'important'
          );

          dropdown.style.setProperty(
            'height',
            newHeight + 'px',
            'important'
          );
        }

        function onMouseUp() {

          /*
           * Sauvegarder la nouvelle taille
           */
          localStorage.setItem(
            'filter-dropdown-width-' + fieldId,
            Math.round(dropdown.offsetWidth)
          );

          localStorage.setItem(
            'filter-dropdown-height-' + fieldId,
            Math.round(dropdown.offsetHeight)
          );

          document.removeEventListener(
            'mousemove',
            onMouseMove
          );

          document.removeEventListener(
            'mouseup',
            onMouseUp
          );

          document.body.style.userSelect = '';
          document.body.style.cursor = '';
        }

        document.body.style.userSelect = 'none';
        document.body.style.cursor = 'nwse-resize';

        document.addEventListener(
          'mousemove',
          onMouseMove
        );

        document.addEventListener(
          'mouseup',
          onMouseUp
        );
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
        maxOptions: null,
        render: {
          option: renderFn,
          item: (data, escape) => `<div>${escape(data.text)}</div>`
        },

        onChange: onFilterChange,

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
          ts.addOption({ value: '', text: ts.settings.placeholderText || 'Tous' });
          rows.forEach(r => ts.addOption(mapFn(r)));
          ts.refreshOptions(false);
          if (preserveSelection) ts.setValue(preserveSelection, true);
        })
        .catch(err => console.error('Erreur chargement ' + fieldId + ':', err));
    }

    function initAllFilters() {
      const currentValues = {
        client_id: '<?= h($filters['client_id'] ?? '') ?>',
        site_id: '<?= h($filters['site_id'] ?? '') ?>',
        building_id: '<?= h($filters['building_id'] ?? '') ?>',
        salle_id: '<?= h($filters['salle_id'] ?? '') ?>',
      };
      initFilterTomSelect('client_id', ['text'], (data, escape) =>
        `<div>${escape(data.text)}</div>`);
      {
        const params = new URLSearchParams();
        if (currentValues.site_id) params.set('site_id', currentValues.site_id);
        if (currentValues.building_id) params.set('building_id', currentValues.building_id);
        if (currentValues.salle_id) params.set('salle_id', currentValues.salle_id);
        loadOptionsInto('client_id', baseUrl + 'materiel/get_all_clients?' + params.toString(), r => ({
          value: r.id, text: r.name
        }), currentValues.client_id);
      }
      initFilterTomSelect('site_id', ['text', 'client_name'],
        formatLocationOption('text', d => [d.client_name]));
      {
        const params = new URLSearchParams();
        if (currentValues.client_id) params.set('client_id', currentValues.client_id);
        if (currentValues.building_id) params.set('building_id', currentValues.building_id);
        if (currentValues.salle_id) params.set('salle_id', currentValues.salle_id);
        loadOptionsInto('site_id', baseUrl + 'materiel/get_all_sites?' + params.toString(), r => ({
          value: r.id, text: r.name, client_id: r.client_id, client_name: r.client_name
        }), currentValues.site_id);
      }
      initFilterTomSelect('building_id', ['text', 'site_name', 'client_name'],
        formatLocationOption('text', d => [d.client_name, d.site_name]));
      {
        const params = new URLSearchParams();
        if (currentValues.client_id) params.set('client_id', currentValues.client_id);
        if (currentValues.site_id) params.set('site_id', currentValues.site_id);
        if (currentValues.salle_id) params.set('salle_id', currentValues.salle_id);
        loadOptionsInto('building_id', baseUrl + 'materiel/get_all_buildings?' + params.toString(), r => ({
          value: r.id, text: r.name, site_id: r.site_id, site_name: r.site_name,
          client_id: r.client_id, client_name: r.client_name
        }), currentValues.building_id);
      }
      initFilterTomSelect('salle_id', ['text', 'building_name', 'site_name', 'client_name'],
        formatLocationOption('text', d => [d.client_name, d.site_name, d.building_name]));
      {
        const params = new URLSearchParams();
        if (currentValues.client_id) params.set('client_id', currentValues.client_id);
        if (currentValues.site_id) params.set('site_id', currentValues.site_id);
        if (currentValues.building_id) params.set('building_id', currentValues.building_id);
        loadOptionsInto('salle_id', baseUrl + 'materiel/get_all_rooms?' + params.toString(), r => ({
          value: r.id, text: r.name, building_id: r.building_id, building_name: r.building_name,
          site_id: r.site_id, site_name: r.site_name, client_id: r.client_id, client_name: r.client_name
        }), currentValues.salle_id);
      }
    }
    let searchDebounceTimer = null;
    let searchAbortController = null;
    let initialResultsHtml = null;

    function organizeMateriel(list) {
      const organise = {};
      list.forEach(m => {
        const client = m.client_nom || 'Sans client';
        const site = m.site_nom || 'Sans site';
        const building = m.building_nom || 'Sans bâtiment';
        const salle = m.salle_nom || 'Sans salle';
        organise[client] ??= {};
        organise[client][site] ??= {};
        organise[client][site][building] ??= {};
        organise[client][site][building][salle] ??= [];
        organise[client][site][building][salle].push(m);
      });
      return organise;
    }

    function stableKey(str) {
      let hash = 0;
      for (let i = 0; i < str.length; i++) {
        hash = ((hash << 5) - hash) + str.charCodeAt(i);
        hash |= 0;
      }
      return 'h' + Math.abs(hash);
    }

    function destroyAllTables() {
      Object.values(hotInstances).forEach(hot => {
        try { hot.destroy(); } catch (e) { /* ignore */ }
      });
      hotInstances = {};
    }

    function renderSearchResults(materielList, piecesJointesCount, term) {
      destroyAllTables();

      const container = document.getElementById('materielResultsContainer');
      const columnControls = document.getElementById('columnControlsCard');
      const organise = organizeMateriel(materielList || []);

      if (Object.keys(organise).length === 0) {
        columnControls.style.display = 'none';
        container.innerHTML = `
          <div class="card">
            <div class="card-body text-center py-5">
              <i class="bi bi-hdd-network fa-3x text-muted mb-3"></i>
              <h5 class="text-muted">Aucun matériel trouvé</h5>
              <p class="text-muted mb-3">Aucun équipement ne correspond à votre recherche "<strong>${escapeHtml(term)}</strong>".</p>
            </div>
          </div>`;
        return;
      }

      columnControls.style.display = 'block';

      const salleRefs = [];
      let html = '<div id="accordionContainer">';

      for (const clientNom in organise) {
        html += `<div class="card mb-4">
          <div class="card-header bg-body-secondary d-flex align-items-center justify-content-between">
            <h5 class="card-title mb-0 d-flex align-items-center"><i class="bi bi-building text-primary me-2"></i>${escapeHtml(clientNom)}</h5>
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="saveAllTablesData()">
              <i class="bi bi-save-all me-1"></i>Sauvegarder toutes les modifications
            </button>
          </div>
          <div class="card-body p-0">`;

        for (const siteNom in organise[clientNom]) {
          for (const buildingNom in organise[clientNom][siteNom]) {
            for (const salleNom in organise[clientNom][siteNom][buildingNom]) {
              const materiels = organise[clientNom][siteNom][buildingNom][salleNom];
              const salleId = 'salle_' + stableKey(clientNom + siteNom + buildingNom + salleNom);
              const tableId = 'excelTable-' + salleId;
              const location = `${escapeHtml(siteNom)} - ${escapeHtml(buildingNom)} - ${escapeHtml(salleNom)}`;
              const dbSalleId = materiels[0]?.salle_id ?? null;

              html += `
                <div class="accordion mb-3" id="accordion_${salleId}">
                  <div class="accordion-item">
                    <h2 class="accordion-header">
                      <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse_${salleId}">
                        <div class="d-flex justify-content-between w-100 me-3 align-items-center">
                          <span><i class="bi bi-door-open me-2 text-info"></i><strong>${location}</strong></span>
                          <span class="badge bg-secondary ms-3">${materiels.length} équipement(s)</span>
                        </div>
                      </button>
                    </h2>
                    <div id="collapse_${salleId}" class="accordion-collapse collapse show">
                      <div class="accordion-body p-0">
                        <div class="d-flex justify-content-end p-2 border-bottom bg-light">
                          <button type="button" class="btn btn-sm btn-success"
                            onclick="addNewRowToTable('${tableId}', '${location.replace(/'/g, "\\'")}', ${dbSalleId ?? 'null'})">
                            <i class="bi bi-plus-circle me-1"></i>Ajouter un équipement
                          </button>
                        </div>
                        <div class="table-wrapper"><div id="${tableId}"></div></div>
                      </div>
                    </div>
                  </div>
                </div>`;

              salleRefs.push({ tableId, materiels, dbSalleId });
            }
          }
        }
        html += `</div></div>`;
      }
      html += '</div>';
      container.innerHTML = html;

      salleRefs.forEach(({ tableId, materiels, dbSalleId }) => {
        const rows = materiels.map(m => allColumnFields.map(field => {
          if (field === 'pieces_jointes') {
            return { count: (piecesJointesCount && piecesJointesCount[m.id]) || 0, id: m.id, name: `${m.marque || ''} ${m.modele || ''}` };
          }
          return m[field] ?? '';
        }));
        createSalleTable(tableId, rows, dbSalleId);
      });

      requestAnimationFrame(() => Object.values(hotInstances).forEach(hot => hot.render()));

      const applied = restoreColumnVisibility();
      if (applied) {
        Object.keys(applied).forEach(col =>
          applyColumnVisibility(parseInt(col), applied[col] === true || applied[col] === 'true')
        );
      }
    }

    function updateSearchInfoBanner(term, count) {
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
        <span class="text-muted ms-2">Recherche globale : "<strong>${escapeHtml(term)}</strong>"</span>`;
    }

    function performGlobalSearchAjax(term) {
      const clearBtn = document.getElementById('clearGlobalSearch');
      clearBtn.style.display = term ? 'inline-block' : 'none';

      if (!term || term.length < 2) {
        if (initialResultsHtml !== null) {
          document.getElementById('materielResultsContainer').innerHTML = initialResultsHtml;
        }
        updateSearchInfoBanner('', 0);
        const url = new URL(window.location.href);
        url.searchParams.delete('search');
        history.pushState({}, '', url.toString());
        return;
      }

      if (searchAbortController) searchAbortController.abort();
      searchAbortController = new AbortController();

      document.getElementById('materielResultsContainer').innerHTML = `
        <div class="text-center py-5">
          <div class="spinner-border text-primary"></div>
          <p class="mt-2 text-muted">Recherche en cours...</p>
        </div>`;

      const params = new URLSearchParams();
      if (term) params.set('search', term);
      const clientId = document.getElementById('client_id').value;
      const siteId = document.getElementById('site_id').value;
      const buildingId = document.getElementById('building_id').value;
      const salleId = document.getElementById('salle_id').value;
      if (clientId) params.set('client_id', clientId);
      if (siteId) params.set('site_id', siteId);
      if (buildingId) params.set('building_id', buildingId);
      if (salleId) params.set('salle_id', salleId);

      fetch(baseUrl + 'materiel/search_api?' + params.toString(), { signal: searchAbortController.signal })
        .then(res => res.json())
        .then(json => {
          if (!json.success) {
            showToast(json.error || 'Erreur de recherche', 'danger');
            return;
          }
          renderSearchResults(json.materiels, json.pieces_jointes_count, term);
          updateSearchInfoBanner(term, (json.materiels || []).length);

          const url = new URL(window.location.href);
          url.searchParams.set('search', term);
          if (clientId) url.searchParams.set('client_id', clientId); else url.searchParams.delete('client_id');
          if (siteId) url.searchParams.set('site_id', siteId); else url.searchParams.delete('site_id');
          if (buildingId) url.searchParams.set('building_id', buildingId); else url.searchParams.delete('building_id');
          if (salleId) url.searchParams.set('salle_id', salleId); else url.searchParams.delete('salle_id');
          history.pushState({ search: term }, '', url.toString());
        })
        .catch(err => {
          if (err.name === 'AbortError') return;
          console.error(err);
          showToast('Erreur réseau lors de la recherche', 'danger');
        });
    }

    function openAllAccordions() {
      document.querySelectorAll('.accordion-collapse').forEach(c => {
        if (!c.classList.contains('show')) new bootstrap.Collapse(c, { toggle: false }).show();
      });
    }
    function closeAllAccordions() {
      document.querySelectorAll('.accordion-collapse.show').forEach(c =>
        bootstrap.Collapse.getInstance(c)?.hide()
      );
    }

    function saveColumnVisibility() {
      const state = {};
      document.querySelectorAll('.global-colvis-checkbox').forEach(cb => {
        state[parseInt(cb.dataset.col)] = cb.checked;
      });
      localStorage.setItem('materiel_columns_visibility', JSON.stringify(state));
    }
    function getResizeStorage(key) {
      const saved = localStorage.getItem(key);
      try { return saved ? JSON.parse(saved) : {}; } catch (e) { return {}; }
    }
    function getRowHeightsFromStorage(tableId) {
      const all = getResizeStorage('materiel_row_heights');
      return all[tableId] || null;
    }
    function saveRowHeight(tableId, row, height) {
      const all = getResizeStorage('materiel_row_heights');
      if (!all[tableId]) all[tableId] = {};
      all[tableId][row] = height;
      localStorage.setItem('materiel_row_heights', JSON.stringify(all));
    }
    function getColumnWidthsFromStorage(tableId) {
      const all = getResizeStorage('materiel_column_widths');
      return all[tableId] || null;
    }
    function saveColumnWidth(tableId, col, width) {
      const all = getResizeStorage('materiel_column_widths');
      if (!all[tableId]) all[tableId] = {};
      all[tableId][col] = width;
      localStorage.setItem('materiel_column_widths', JSON.stringify(all));
    }
    function restoreColumnVisibility() {
      const saved = localStorage.getItem('materiel_columns_visibility');
      if (!saved) return null;
      try {
        const state = JSON.parse(saved);
        document.querySelectorAll('.global-colvis-checkbox').forEach(cb => {
          const col = parseInt(cb.dataset.col);
          if (state.hasOwnProperty(col)) cb.checked = state[col];
        });
        return state;
      } catch (e) { return null; }
    }

    function applyColumnVisibility(colIndex, isVisible) {
      Object.values(hotInstances).forEach(hot => {
        const p = hot.getPlugin('hiddenColumns');
        isVisible ? p.showColumn(colIndex) : p.hideColumn(colIndex);
        hot.render();
      });
    }

    const COLUMN_FORMATS = {
      'date_fin_maintenance': { type: 'date', dateFormat: 'YYYY-MM-DD' },
      'date_fin_garantie': { type: 'date', dateFormat: 'YYYY-MM-DD' },
      'date_derniere_inter': { type: 'date', dateFormat: 'YYYY-MM-DD' },
    };
    const COLUMN_PLACEHOLDERS = {
      'date_fin_maintenance': 'YYYY-MM-DD',
      'date_fin_garantie': 'YYYY-MM-DD',
      'date_derniere_inter': 'YYYY-MM-DD',
      'numero_serie': 'Ex: 21MX2237200108',
      'adresse_ip': '192.168.1.1',
      'ip_primaire': '192.168.1.1',
      'ip_secondaire': '192.168.1.1',
      'passerelle': '172.24.158.230',
      'masque': '255.255.255.0',
      'adresse_mac': '00:0E:DD:FA:65:88',
      'mac_primaire': '00:0E:DD:FA:65:88',
      'mac_secondaire': '00:0E:DD:FA:65:88',
      'version_firmware': '10.0.8',
      'ancien_firmware': '10.0.8',
    };

    function makePlaceholderRenderer(placeholder) {
      return function (instance, td, row, col, prop, value, cellProperties) {
        Handsontable.renderers.TextRenderer.apply(this, arguments);
        if (!value || value === '') {
          td.innerHTML = `<span style="color:#adb5bd;font-style:italic;pointer-events:none;">${placeholder}</span>`;
        } else {
          td.style.color = '#000000';
          td.style.fontStyle = 'normal';
        }
      };
    }

    function buildHandsontableColumns() {
      return allColumnFields.map(field => {
        const fmt = COLUMN_FORMATS[field];
        const ph = COLUMN_PLACEHOLDERS[field];
        if (!fmt) return ph ? { type: 'text', renderer: makePlaceholderRenderer(ph) } : { type: 'text' };
        if (fmt.type === 'date') {
          return {
            type: 'date',
            dateFormat: fmt.dateFormat,
            correctFormat: true,
            defaultDate: '',
            renderer: makePlaceholderRenderer(ph || 'YYYY-MM-DD'),
            datePickerConfig: {
              firstDay: 1,
              showWeekNumber: true,
              i18n: {
                previousMonth: 'Mois préc.',
                nextMonth: 'Mois suiv.',
                months: ['Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin', 'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre'],
                weekdays: ['Dim', 'Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'],
                weekdaysShort: ['Di', 'Lu', 'Ma', 'Me', 'Je', 'Ve', 'Sa']
              }
            }
          };
        }
        return { type: 'text' };
      });
    }

    function getHiddenColumnsFromStorage() {
      const saved = localStorage.getItem('materiel_columns_visibility');
      if (!saved) return DEFAULT_HIDDEN_COLUMNS;
      try {
        const state = JSON.parse(saved);
        return Object.keys(state).map(k => parseInt(k)).filter(k => state[k] === false);
      } catch (e) { return DEFAULT_HIDDEN_COLUMNS; }
    }

    function hotCellsFn(row, col) {
      const header = this.colHeaders[col];

      if (header === 'Marque') {
        return {
          renderer: function (instance, td, row, col, prop, value) {
            const id = instance.getDataAtCell(row, ID_INDEX);
            td.innerHTML = '';
            if (id) {
              const urlParams = new URLSearchParams(window.location.search);
              const link = document.createElement('a');
              link.href = baseUrl + 'materiel/view/' + id + (urlParams.toString() ? '?' + urlParams : '');
              link.className = 'text-decoration-none fw-bold text-primary';
              link.onclick = e => e.stopPropagation();
              link.textContent = value || '';
              td.appendChild(link);
            } else {
              const span = document.createElement('span');
              span.className = 'fw-bold text-primary';
              span.textContent = value || '';
              td.appendChild(span);
            }
            td.style.cursor = 'default';
          },
          editor: 'text'
        };
      }

      if (header === 'Modèle') {
        return {
          renderer: function (instance, td, row, col, prop, value) {
            td.style.color = '#000000';
            td.style.fontWeight = 'normal';
            td.style.backgroundColor = '#f3e1b5';
            td.textContent = value || '';
            td.style.cursor = 'default';
          },
          editor: 'text'
        };
      }

      if (header === 'Pièces jointes') {
        return {
          renderer: function (instance, td, row, col, prop, value) {
            const count = value?.count ?? 0;
            const id = value?.id;
            const name = value?.name ?? '';
            td.innerHTML = `<button class="btn btn-sm ${count > 0 ? 'btn-outline-info' : 'btn-outline-secondary'}"
              onclick="openAttachmentsModal(${id},'${name.replace(/'/g, "\\'")}')">
              <i class="bi bi-paperclip"></i>
              <span class="badge ${count > 0 ? 'bg-info' : 'bg-secondary'} ms-1">${count}</span>
            </button>`;
            td.style.textAlign = 'center';
          }
        };
      }

      return {};
    }

    function createSalleTable(tableId, rows, dbSalleId) {
      const container = document.getElementById(tableId);
      if (!container) return null;

      const savedHeights = getRowHeightsFromStorage(tableId);
      const savedWidths = getColumnWidthsFromStorage(tableId);

      const hot = new Handsontable(container, {
        data: rows,
        colHeaders: colHeadersGlobal,
        columns: buildHandsontableColumns(),
        hiddenColumns: { columns: getHiddenColumnsFromStorage(), indicators: true },
        rowHeaders: false,
        licenseKey: 'non-commercial-and-evaluation',
        stretchH: 'all',
        height: 'auto',
        manualRowResize: true,
        manualColumnResize: true,
        rowHeights: savedHeights ? (row => savedHeights[row]) : undefined,
        colWidths: savedWidths ? (col => savedWidths[col]) : undefined,
        wordWrap: true,
        cells: hotCellsFn,
        afterRowResize: function (newSize, row) {
          saveRowHeight(tableId, row, newSize);
        },
        afterColumnResize: function (newSize, column) {
          saveColumnWidth(tableId, column, newSize);
        }
      });

      hot.__salleId = dbSalleId ?? null;
      hotInstances[tableId] = hot;
      return hot;
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
      fetch(baseUrl + 'materiel/getAttachments/' + materielId)
        .then(res => res.json())
        .then(data => {
          if (data.success && data.attachments) renderAttachments(data.attachments, container, materielId);
          else container.innerHTML = `<div class="alert alert-danger">${data.error || 'Erreur de chargement'}</div>`;
        })
        .catch(() => { container.innerHTML = '<div class="alert alert-danger">Erreur de chargement</div>'; });
    }
    function renderAttachments(attachments, container, materielId) {
      let html = '<div class="mb-3">';
      if (!attachments.length) {
        html += '<div class="text-center py-4"><i class="bi bi-inbox fs-1 text-muted"></i><p class="mt-3">Aucune pièce jointe</p></div>';
      } else {
        attachments.sort((a, b) => new Date(b.date_creation) - new Date(a.date_creation));
        html += '<div class="list-group">';
        attachments.forEach(att => {
          const isPdf = att.type_fichier?.toLowerCase() === 'pdf';
          const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(att.type_fichier?.toLowerCase());
          const size = formatFileSize(att.taille_fichier || 0);
          const date = att.date_creation
            ? new Date(att.date_creation).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
            : '-';
          html += `<div class="list-group-item ${att.masque_client == 1 ? 'bg-light-warning' : ''}">
            <div class="d-flex justify-content-between align-items-start">
              <div class="flex-grow-1">
                <div class="d-flex align-items-center mb-1">
                  ${att.masque_client == 1 ? '<i class="bi bi-eye-slash text-warning me-2"></i>' : ''}
                  <strong>${escapeHtml(att.nom_fichier)}</strong>
                </div>
                ${att.commentaire ? `<small class="text-muted d-block">${escapeHtml(att.commentaire)}</small>` : ''}
                <small class="text-muted">${size} • ${date}${att.created_by_name ? ' • ' + escapeHtml(att.created_by_name) : ''}</small>
              </div>
              <div class="ms-3">
                ${isPdf || isImage ? `<button class="btn btn-sm btn-outline-info me-1" onclick="previewAttachment(${att.id},'${escapeHtml(att.nom_fichier)}','${att.type_fichier}')"><i class="bi bi-eye"></i></button>` : ''}
                <a href="${baseUrl}materiel/download/${att.id}" class="btn btn-sm btn-outline-success me-1"><i class="bi bi-download"></i></a>
                <a href="${baseUrl}materiel/toggleAttachmentVisibility/${materielId}/${att.id}" class="btn btn-sm btn-outline-warning me-1"><i class="bi ${att.masque_client == 1 ? 'bi-eye' : 'bi-eye-slash'}"></i></a>
                <a href="${baseUrl}materiel/deleteAttachment/${materielId}/${att.id}" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a>
              </div>
            </div>
          </div>`;
        });
        html += '</div>';
      }
      html += `</div><div class="d-flex justify-content-end"><button class="btn btn-primary" onclick="openAddAttachmentModal(${materielId})"><i class="bi bi-plus me-1"></i>Ajouter</button></div>`;
      container.innerHTML = html;
    }
    function previewAttachment(id, name, type) {
      const modal = new bootstrap.Modal(document.getElementById('previewAttachmentModal'));
      document.getElementById('previewAttachmentModalLabel').textContent = name;
      const body = document.getElementById('previewAttachmentModalBody');
      const ext = type?.toLowerCase() || '';
      if (ext === 'pdf') body.innerHTML = `<iframe src="${baseUrl}materiel/preview/${id}" width="100%" height="600px" frameborder="0"></iframe>`;
      else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) body.innerHTML = `<img src="${baseUrl}materiel/preview/${id}" class="img-fluid">`;
      else body.innerHTML = `<div class="alert alert-info">Prévisualisation non disponible. <a href="${baseUrl}materiel/download/${id}" target="_blank">Télécharger</a></div>`;
      modal.show();
    }
    function formatFileSize(bytes) {
      if (!bytes) return '0 Bytes';
      const k = 1024, sizes = ['Bytes', 'KB', 'MB', 'GB'], i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
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
          const res = await fetch(baseUrl + 'settings/getAllowedExtensions');
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
      handleFiles(newFiles) { this.files.push(...this.validateFiles(newFiles)); this.render(); }
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
      removeFile(index) { this.files.splice(index, 1); this.render(); }
      clearAll() { this.files = []; this.render(); this.fileInput.value = ''; }
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
          const res = await fetch(baseUrl + 'materiel/uploadAttachment', {
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
        } catch (e) { alert('Erreur réseau'); }
        finally {
          this.uploadBtn.disabled = false;
          this.uploadBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Uploader';
        }
      }
    }

    function parsePhpSize(size) {
      const units = { K: 1024, M: 1048576, G: 1073741824 };
      const match = String(size).match(/^(\d+)([KMG])?$/i);
      return match ? parseInt(match[1]) * (units[match[2]?.toUpperCase()] || 1) : 0;
    }

    let uploader;
    document.getElementById('addAttachmentModal').addEventListener('shown.bs.modal', function () {
      const id = this.getAttribute('data-materiel-id');
      if (id) uploader = new DragDropUploader(id);
    });
    document.getElementById('addAttachmentModal').addEventListener('hidden.bs.modal', function () {
      if (uploader) uploader.clearAll();
    });

    window.saveAllTablesData = function () {
      let totalUpdated = 0;
      let totalCreated = 0;
      let totalErrors = 0;
      const savePromises = [];
      const errorDetails = [];
      const missingFieldsErrors = [];

      const allValidationErrors = [];
      Object.keys(hotInstances).forEach(tableId => {
        const hot = hotInstances[tableId];
        if (!hot) return;
        hot.getSourceData().forEach((row, i) => {
          if (!row[MARQUE_INDEX] && !row[MODELE_INDEX]) return;
          allValidationErrors.push(...validateRow(row, i));
        });
      });

      if (allValidationErrors.length > 0) {
        showToast(
          `<strong>Format invalide — sauvegarde annulée</strong><br><br>` +
          `Corrigez les champs suivants avant de sauvegarder :<br><br>` +
          allValidationErrors.slice(0, 6).join('<br>') +
          (allValidationErrors.length > 6 ? `<br><em>... et ${allValidationErrors.length - 6} autre(s) erreur(s)</em>` : ''),
          'danger'
        );
        return;
      }

      Object.keys(hotInstances).forEach(tableId => {
        const hot = hotInstances[tableId];
        if (!hot) return;

        const allData = hot.getSourceData();
        const filters = <?= json_encode($filters ?? []) ?>;
        let globalSalleId = hot.__salleId || filters.salle_id || null;

        if (!globalSalleId && allData.length > 0) {
          for (const row of allData) {
            if (row[ID_INDEX]) { globalSalleId = filters.salle_id || null; break; }
          }
        }

        if (!globalSalleId) {
          showToast('Impossible de sauvegarder : salle non identifiée. Filtrez par salle d\'abord.', 'danger');
          return;
        }

        const existingRows = [];
        const newRows = [];
        for (let i = 0; i < allData.length; i++) {
          const row = allData[i];
          if (row[ID_INDEX] && row[ID_INDEX] !== '' && row[ID_INDEX] !== null) {
            existingRows.push({ row, originalIndex: i });
          } else {
            newRows.push({ row, originalIndex: i });
          }
        }

        if (existingRows.length > 0) {
          const formattedData = existingRows.map(item => {
            const row = item.row;
            const obj = {};
            obj['id'] = row[ID_INDEX];
            <?php foreach ($allColumns as $col): ?>
            <?php if ($col['field'] === 'pieces_jointes')
              continue; ?>
            obj['<?= $col['field'] ?>'] = row[<?= array_search($col['field'], array_column($allColumns, 'field')) ?>] || null;
            <?php endforeach; ?>
            return obj;
          });

          savePromises.push(
            fetch(baseUrl + 'views/excel/excel_save.php', {
              method: 'POST',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '<?= csrf_token() ?>' },
              body: JSON.stringify({ table_id: tableId, salle_id: globalSalleId, data: formattedData })
            })
              .then(r => r.json())
              .then(result => {
                if (result.status === 'success' || result.status === 'partial') {
                  totalUpdated += result.updated || 0;
                  if (result.errors?.length > 0) errorDetails.push(...result.errors);
                } else if (result.updated > 0) {
                  totalUpdated += result.updated || 0;
                } else {
                  totalErrors++;
                  errorDetails.push('Mise à jour échouée');
                }
              })
              .catch(() => { totalErrors++; errorDetails.push('Erreur réseau lors de la mise à jour'); })
          );
        }

        for (let idx = 0; idx < newRows.length; idx++) {
          const row = newRows[idx].row;
          const marque = row[MARQUE_INDEX];
          const modele = row[MODELE_INDEX];

          if (!marque?.trim() || !modele?.trim()) {
            const manquants = [];
            if (!marque?.trim()) manquants.push('Marque');
            if (!modele?.trim()) manquants.push('Modèle');
            missingFieldsErrors.push(`• Équipement sans ${manquants.join(' et ')}`);
            totalErrors++;
            continue;
          }

          const fd = new FormData();
          fd.append('salle_id', globalSalleId);
          <?php foreach ($allColumns as $col): ?>
          <?php if ($col['field'] === 'pieces_jointes')
            continue; ?>
          fd.append('<?= $col['field'] ?>', row[<?= array_search($col['field'], array_column($allColumns, 'field')) ?>] || '');
          <?php endforeach; ?>
          if (filters.client_id) fd.append('return_client_id', filters.client_id);
          if (filters.site_id) fd.append('return_site_id', filters.site_id);
          if (filters.salle_id) fd.append('return_salle_id', filters.salle_id);

          const marqueRef = marque, modeleRef = modele;
          savePromises.push(
            fetch(baseUrl + 'materiel/store', {
              method: 'POST',
              headers: { 'X-CSRF-Token': '<?= csrf_token() ?>' },
              body: fd
            })
              .then(async response => {
                if (response.status >= 200 && response.status < 400) {
                  totalCreated++;
                } else {
                  totalErrors++;
                  errorDetails.push(`Échec création "${marqueRef} ${modeleRef}" : ${response.status}`);
                }
              })
              .catch(() => { totalErrors++; errorDetails.push(`Erreur réseau pour "${marqueRef} ${modeleRef}"`); })
          );
        }
      });

      if (savePromises.length === 0 && totalErrors === 0) {
        showToast('Aucune donnée à sauvegarder', 'info');
        return;
      }

      Promise.all(savePromises).then(() => {
        const successParts = [];
        if (totalUpdated > 0) successParts.push(`${totalUpdated} mise(s) à jour`);
        if (totalCreated > 0) successParts.push(`${totalCreated} équipement(s) créé(s)`);
        const successMessage = successParts.join(', ');

        let errorMessage = '';
        if (totalErrors > 0) {
          if (missingFieldsErrors.length > 0) {
            errorMessage = `<br><br><strong>Problèmes détectés :</strong><br>`;
            errorMessage += missingFieldsErrors.slice(0, 5).join('<br>');
            if (missingFieldsErrors.length > 5) errorMessage += `<br><em>... et ${missingFieldsErrors.length - 5} autre(s)</em>`;
            errorMessage += `<br><br><strong>Solution :</strong><br>Remplissez la <strong>Marque</strong> et le <strong>Modèle</strong> avant de sauvegarder.`;
          } else if (errorDetails.length > 0) {
            errorMessage = `<br><br><strong>Erreurs rencontrées :</strong><br>`;
            errorMessage += errorDetails.slice(0, 3).join('<br>');
            if (errorDetails.length > 3) errorMessage += `<br><em>... et ${errorDetails.length - 3} autre(s)</em>`;
          }
        }

        if (totalErrors === 0 && (totalUpdated > 0 || totalCreated > 0)) {
          showToast(`<strong>Sauvegarde réussie !</strong><br><br>${successMessage}<br><br>Toutes les modifications ont été enregistrées.`, 'success');
          if (totalCreated > 0) setTimeout(() => window.location.reload(), 2000);
        } else if (totalErrors > 0 && (totalUpdated > 0 || totalCreated > 0)) {
          showToast(`<strong>Sauvegarde partielle</strong><br><br>${successMessage}<br>${totalErrors} erreur(s)${errorMessage}`, 'danger');
          if (totalCreated > 0) setTimeout(() => window.location.reload(), 3000);
        } else if (totalErrors > 0) {
          let mainMessage = `<strong>Sauvegarde impossible</strong><br><br>`;
          if (missingFieldsErrors.length > 0) {
            mainMessage += `${missingFieldsErrors.length} ligne(s) avec informations manquantes.<br><br>`;
            mainMessage += `<strong>Comment corriger :</strong><br>`;
            mainMessage += `• Remplissez la <strong>Marque</strong> et le <strong>Modèle</strong><br>`;
            mainMessage += `• Utilisez <strong>"Ajouter un équipement"</strong> pour créer une nouvelle ligne<br><br>`;
            mainMessage += `<strong>Lignes concernées :</strong><br>`;
            mainMessage += missingFieldsErrors.slice(0, 3).join('<br>');
            if (missingFieldsErrors.length > 3) mainMessage += `<br><em>... et ${missingFieldsErrors.length - 3} autre(s)</em>`;
          } else {
            mainMessage += `Aucune donnée sauvegardée.<br><br><strong>Vérifiez :</strong><br>• Champs obligatoires remplis<br>• Connexion internet stable<br>• Droits suffisants`;
            if (errorDetails.length > 0) mainMessage += `<br><br><strong>Détails :</strong><br>${errorDetails.slice(0, 2).join('<br>')}`;
          }
          showToast(mainMessage, 'danger');
        } else {
          showToast('ℹ<strong>Aucune modification détectée</strong><br><br>Aucune donnée n\'a été modifiée ou ajoutée.', 'info');
        }
      }).catch(() => {
        showToast(
          `<strong>Erreur système</strong><br><br>Une erreur inattendue s'est produite.<br><br>` +
          `<strong>Actions recommandées :</strong><br>• Rafraîchissez la page (F5)<br>• Vérifiez votre connexion<br>• Contactez le support`,
          'danger'
        );
      });
    };

    document.addEventListener('DOMContentLoaded', function () {
      initAllFilters();
      <?php if ($hasAnyFilter && !empty($materiel_organise)): ?>
      <?php renderMaterielTableInitJs($materiel_organise, $pieces_jointes_count, $allColumns); ?>
      <?php endif; ?>

      requestAnimationFrame(() => {
        document.querySelectorAll('.accordion-collapse').forEach(c => c.classList.add('show'));
        requestAnimationFrame(() => {
          Object.values(hotInstances).forEach(hot => hot.render());
          <?php if (!$isGlobalSearch): ?>
          document.querySelectorAll('.accordion-collapse').forEach(c => {
            c.classList.remove('show');
            const btn = c.closest('.accordion-item')?.querySelector('.accordion-button');
            if (btn) btn.classList.add('collapsed');
          });
          <?php endif; ?>
        });
      });
      initialResultsHtml = document.getElementById('materielResultsContainer').innerHTML;

      const saved = restoreColumnVisibility();
      if (saved) {
        setTimeout(() => {
          Object.keys(saved).forEach(col =>
            applyColumnVisibility(parseInt(col), saved[col] === true || saved[col] === 'true')
          );
        }, 100);
      }

      const searchInput = document.getElementById('globalSearch');
      const clearBtn = document.getElementById('clearGlobalSearch');
      const openBtn = document.getElementById('openAllAccordions');
      const closeBtn = document.getElementById('closeAllAccordions');

      if (searchInput) {
        searchInput.addEventListener('input', function () {
          const term = this.value.trim();
          clearBtn.style.display = term ? 'inline-block' : 'none';
          if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
          searchDebounceTimer = setTimeout(() => performGlobalSearchAjax(term), 400);
        });

        searchInput.addEventListener('keydown', function (e) {
          if (e.key === 'Enter') {
            e.preventDefault();
            if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
            performGlobalSearchAjax(this.value.trim());
          }
        });
      }

      if (clearBtn) {
        clearBtn.addEventListener('click', function () {
          if (searchDebounceTimer) clearTimeout(searchDebounceTimer);
          searchInput.value = '';
          performGlobalSearchAjax('');
        });
      }

      if (openBtn) openBtn.addEventListener('click', openAllAccordions);
      if (closeBtn) closeBtn.addEventListener('click', closeAllAccordions);

      document.querySelectorAll('.global-colvis-checkbox').forEach(cb => {
        cb.addEventListener('change', function () {
          const col = parseInt(this.dataset.col);
          const visible = this.checked;
          Object.values(hotInstances).forEach(hot => {
            const p = hot.getPlugin('hiddenColumns');
            visible ? p.showColumn(col) : p.hideColumn(col);
            hot.render();
          });
          saveColumnVisibility();
        });
      });
      window.addEventListener('popstate', function () {
        const term = new URL(window.location.href).searchParams.get('search') || '';
        const input = document.getElementById('globalSearch');
        if (input) input.value = term;
        performGlobalSearchAjax(term);
      });
    });
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
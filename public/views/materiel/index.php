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
$isGlobalSearch = $isGlobalSearch ?? false;
$globalSearchTerm = $globalSearchTerm ?? '';

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

// Organiser le matériel par client -> site -> bâtiment -> salle
$materiel_organise = [];
foreach ($materiel_list as $materiel) {
  $client_nom = $materiel['client_nom'] ?? 'Sans client';
  $site_nom = $materiel['site_nom'] ?? 'Sans site';
  $building_nom = $materiel['building_nom'] ?? 'Sans bâtiment';
  $salle_nom = $materiel['salle_nom'] ?? 'Sans salle';

  if (!isset($materiel_organise[$client_nom])) {
    $materiel_organise[$client_nom] = [];
  }
  if (!isset($materiel_organise[$client_nom][$site_nom])) {
    $materiel_organise[$client_nom][$site_nom] = [];
  }
  if (!isset($materiel_organise[$client_nom][$site_nom][$building_nom])) {
    $materiel_organise[$client_nom][$site_nom][$building_nom] = [];
  }
  if (!isset($materiel_organise[$client_nom][$site_nom][$building_nom][$salle_nom])) {
    $materiel_organise[$client_nom][$site_nom][$building_nom][$salle_nom] = [];
  }

  $materiel_organise[$client_nom][$site_nom][$building_nom][$salle_nom][] = $materiel;
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
              <div class="mt-2">
                <span class="badge bg-info text-white">
                  <i class="bi bi-search me-1"></i>
                  <?= count($materiel_list) ?> résultat(s)
                </span>
                <span class="text-muted ms-2">Recherche globale : "<strong>
                    <?= h($globalSearchTerm) ?>
                  </strong>"</span>
                <a href="<?= BASE_URL ?>materiel" class="btn btn-sm btn-link">
                  <i class="bi bi-x-circle me-1"></i>Effacer
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>

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
            <div class="col-md-4 d-flex justify-content-end gap-2">
              <a href="<?= BASE_URL ?>materiel" class="btn btn-outline-secondary">
                <i class="bi bi-x-lg me-1"></i>Réinitialiser
              </a>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- Liste du matériel organisée -->
    <?php if (empty($filters['client_id']) && !$isGlobalSearch): ?>
      <!-- Message "Sélectionnez un client" - UNIQUEMENT si pas de recherche -->
      <div class="card">
        <div class="card-body text-center py-5">
          <i class="fas fa-filter fa-3x text-muted mb-3"></i>
          <h5 class="text-muted">Sélectionnez un client pour voir le matériel</h5>
          <p class="text-muted mb-3">Choisissez un client dans le filtre ci-dessus pour afficher le matériel associé, ou
            utilisez la recherche globale ci-dessous.</p>
        </div>
      </div>

    <?php elseif ($isGlobalSearch && !empty($materiel_organise)): ?>
      <!-- RÉSULTATS DE LA RECHERCHE GLOBALE -->
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

      <!-- Contrôles des colonnes -->
      <div class="card mb-4">
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
              <?php foreach ($sites as $site_nom => $buildings): ?>
                <?php foreach ($buildings as $building_nom => $salles): ?>
                  <?php foreach ($salles as $salle_nom => $materiels):
                    $salle_id = 'salle_' . md5($client_nom . $site_nom . $building_nom . $salle_nom);
                    $accordion_id = 'accordion_' . $salle_id;
                    $locationString = h($site_nom) . ' - ' . h($building_nom) . ' - ' . h($salle_nom);
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
                        <div id="collapse_<?= $salle_id ?>" class="accordion-collapse collapse"
                          data-bs-parent="#accordionContainer">
                          <div class="accordion-body p-0">
                            <div class="d-flex justify-content-end p-2 border-bottom bg-light">
                              <button type="button" class="btn btn-sm btn-success"
                                onclick="addNewRowToTable('excelTable-<?= $salle_id ?>', '<?= addslashes($locationString) ?>', <?= $materiels[0]['salle_id'] ?? 'null' ?>)">
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
        <?php endforeach; ?>
      </div>

    <?php elseif (empty($materiel_organise) && ($isGlobalSearch || !empty($filters['client_id']))): ?>
      <!-- Aucun résultat -->
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

    <?php elseif (!empty($materiel_organise) && !$isGlobalSearch && !empty($filters['client_id'])): ?>
      <!-- Affichage NORMAL avec un client sélectionné -->
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

      <!-- Contrôles des colonnes -->
      <div class="card mb-4">
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
              <?php foreach ($sites as $site_nom => $buildings): ?>
                <?php foreach ($buildings as $building_nom => $salles): ?>
                  <?php foreach ($salles as $salle_nom => $materiels):
                    $salle_id = 'salle_' . md5($client_nom . $site_nom . $building_nom . $salle_nom);
                    $accordion_id = 'accordion_' . $salle_id;
                    $locationString = h($site_nom) . ' - ' . h($building_nom) . ' - ' . h($salle_nom);
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
                        <div id="collapse_<?= $salle_id ?>" class="accordion-collapse collapse"
                          data-bs-parent="#accordionContainer">
                          <div class="accordion-body p-0">
                            <div class="d-flex justify-content-end p-2 border-bottom bg-light">
                              <button type="button" class="btn btn-sm btn-success"
                                onclick="addNewRowToTable('excelTable-<?= $salle_id ?>', '<?= addslashes($locationString) ?>', <?= $materiels[0]['salle_id'] ?? 'null' ?>)">
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
    let currentSearchTerm = '';
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

    // ── showToast ────────────────────────────────────────────────────────────────
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

    function addNewRowToTable(tableId, locationName, salleId) {
      const hot = hotInstances[tableId];
      if (!hot) return;
      hot.__salleId = salleId;
      const existingData = hot.getSourceData();
      const data = existingData.map(row => row.map(cell =>
        (cell && typeof cell === 'object') ? { ...cell } : cell
      ));
      const colCount = <?= count($allColumns) ?>;
      const newRow = Array(colCount).fill('');
      newRow[PIECES_JOINTES_INDEX] = { count: 0, id: null, name: '' };
      data.push(newRow);
      hot.loadData(data);
      const newRowIndex = data.length - 1;
      hot.scrollViewportTo(newRowIndex, 0);
      hot.selectCell(newRowIndex, 0);
      showToast('Nouvelle ligne ajoutée. Remplissez les informations puis sauvegardez.', 'info');
    }

    // ── submitFilters ─────────────────────────────────────────────────────────────
    function submitFilters() {
      const clientId = document.getElementById('client_id').value;
      const siteId = document.getElementById('site_id').value;
      const buildingId = document.getElementById('building_id').value;
      const salleId = document.getElementById('salle_id').value;
      let url = '<?= BASE_URL ?>materiel?';
      const params = [];
      if (clientId) params.push('client_id=' + clientId);
      if (siteId) params.push('site_id=' + siteId);
      if (buildingId) params.push('building_id=' + buildingId);
      if (salleId) params.push('salle_id=' + salleId);
      window.location.href = url + params.join('&');
    }

    function updateSitesAndSubmit() {
      const clientId = document.getElementById('client_id').value;
      if (clientId) {
        fetch('<?= BASE_URL ?>materiel/get_sites?client_id=' + clientId)
          .then(res => res.json())
          .then(data => {
            const siteSelect = document.getElementById('site_id');
            siteSelect.innerHTML = '<option value="">Tous les sites</option>';
            if (Array.isArray(data)) data.forEach(site => {
              const o = document.createElement('option');
              o.value = site.id; o.textContent = site.name;
              siteSelect.appendChild(o);
            });
            document.getElementById('building_id').innerHTML = '<option value="">Tous les bâtiments</option>';
            document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
            submitFilters();
          })
          .catch(err => console.error('Erreur chargement sites:', err));
      } else {
        document.getElementById('site_id').innerHTML = '<option value="">Tous les sites</option>';
        document.getElementById('building_id').innerHTML = '<option value="">Tous les bâtiments</option>';
        document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
        submitFilters();
      }
    }

    function updateBuildingsAndSubmit() {
      const clientId = document.getElementById('client_id').value;
      const siteId = document.getElementById('site_id').value;
      if (siteId && clientId) {
        fetch('<?= BASE_URL ?>materiel/get_buildings?site_id=' + siteId)
          .then(res => res.json())
          .then(data => {
            const buildingSelect = document.getElementById('building_id');
            buildingSelect.innerHTML = '<option value="">Tous les bâtiments</option>';
            if (Array.isArray(data)) data.forEach(b => {
              const o = document.createElement('option');
              o.value = b.id; o.textContent = b.name;
              buildingSelect.appendChild(o);
            });
            document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
            submitFilters();
          })
          .catch(err => console.error('Erreur chargement bâtiments:', err));
      } else {
        document.getElementById('building_id').innerHTML = '<option value="">Tous les bâtiments</option>';
        document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
        submitFilters();
      }
    }

    function updateRoomsAndSubmit() {
      const clientId = document.getElementById('client_id').value;
      const siteId = document.getElementById('site_id').value;
      const buildingId = document.getElementById('building_id').value;
      const loadRooms = (url) => fetch(url)
        .then(res => res.json())
        .then(data => {
          const roomSelect = document.getElementById('salle_id');
          roomSelect.innerHTML = '<option value="">Toutes les salles</option>';
          if (Array.isArray(data)) data.forEach(r => {
            const o = document.createElement('option');
            o.value = r.id; o.textContent = r.name;
            roomSelect.appendChild(o);
          });
          submitFilters();
        })
        .catch(err => console.error('Erreur chargement salles:', err));

      if (buildingId && siteId && clientId) {
        loadRooms('<?= BASE_URL ?>materiel/get_rooms_by_building?building_id=' + buildingId);
      } else if (siteId && clientId) {
        loadRooms('<?= BASE_URL ?>materiel/get_rooms_by_site?site_id=' + siteId);
      } else {
        document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
        submitFilters();
      }
    }

    // ── RECHERCHE GLOBALE EN TEMPS RÉEL ──────────────────────────────────────
    let searchDebounceTimer = null;

    function performGlobalSearch(term) {
      if (!term || term.length < 2) {
        // Si le terme est vide ou trop court, on efface la recherche
        const currentUrl = new URL(window.location.href);
        currentUrl.searchParams.delete('search');
        window.location.href = currentUrl.toString();
        return;
      }

      // Rediriger vers la page avec le paramètre de recherche
      const currentUrl = new URL(window.location.href);
      currentUrl.searchParams.set('search', term);
      // Supprimer les filtres client/site/batiment/salle car la recherche est globale
      currentUrl.searchParams.delete('client_id');
      currentUrl.searchParams.delete('site_id');
      currentUrl.searchParams.delete('building_id');
      currentUrl.searchParams.delete('salle_id');

      window.location.href = currentUrl.toString();
    }

    function applyGlobalSearch() {
      const searchTerm = document.getElementById('globalSearch').value.toLowerCase();
      currentSearchTerm = searchTerm;
      document.getElementById('clearGlobalSearch').style.display = searchTerm ? 'inline-block' : 'none';

      // Filtrer les lignes dans chaque tableau HOT
      Object.keys(hotInstances).forEach(tableId => {
        const hot = hotInstances[tableId];
        if (!hot) return;
        const data = hot.getData();
        for (let i = 0; i < data.length; i++) {
          let matches = false;
          for (let j = 0; j < data[i].length; j++) {
            if (j === PIECES_JOINTES_INDEX) continue;
            const v = data[i][j];
            if (v && typeof v !== 'object' && v.toString().toLowerCase().includes(searchTerm)) {
              matches = true; break;
            }
          }
          const el = hot.getCell(hot.toVisualRow(i), 0);
          if (el && el.parentNode) el.parentNode.style.display = (!searchTerm || matches) ? '' : 'none';
        }
        hot.render();
      });

      updateAccordionsVisibility(searchTerm);

      if (searchTerm) {
        document.querySelectorAll('.accordion-item').forEach(item => {
          const isVisible = item.style.display !== 'none';
          const collapse = item.querySelector('.accordion-collapse');
          if (!collapse) return;
          const btn = item.querySelector('.accordion-button');

          if (isVisible) {
            collapse.classList.add('show');
            if (btn) btn.classList.remove('collapsed');
            const tableId = collapse.id.replace('collapse_', 'excelTable-');
            const hot = hotInstances[tableId];
            if (hot) requestAnimationFrame(() => hot.render());
          } else {
            collapse.classList.remove('show');
            if (btn) btn.classList.add('collapsed');
          }
        });
      } else {
        document.querySelectorAll('.accordion-collapse').forEach(c => {
          c.classList.remove('show');
          const btn = c.closest('.accordion-item')?.querySelector('.accordion-button');
          if (btn) btn.classList.add('collapsed');
        });
      }
    }

    function updateAccordionsVisibility(searchTerm) {
      if (!searchTerm) {
        document.querySelectorAll('.accordion-item').forEach(i => i.style.display = '');
        return;
      }
      document.querySelectorAll('.accordion-item').forEach(item => {
        const collapse = item.querySelector('.accordion-collapse');
        if (!collapse) return;
        const hot = hotInstances[collapse.id.replace('collapse_', 'excelTable-')];
        let found = false;
        if (hot) {
          const data = hot.getData();
          outer: for (let i = 0; i < data.length; i++)
            for (let j = 0; j < data[i].length; j++) {
              if (j === PIECES_JOINTES_INDEX) continue;
              const v = data[i][j];
              if (v && typeof v !== 'object' && v.toString().toLowerCase().includes(searchTerm)) {
                found = true; break outer;
              }
            }
        }
        item.style.display = found ? '' : 'none';
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

    // ── column visibility ─────────────────────────────────────────────────────────
    function saveColumnVisibility() {
      const state = {};
      document.querySelectorAll('.global-colvis-checkbox').forEach(cb => {
        state[parseInt(cb.dataset.col)] = cb.checked;
      });
      localStorage.setItem('materiel_columns_visibility', JSON.stringify(state));
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

    // ── attachments ───────────────────────────────────────────────────────────────
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
                <a href="<?= BASE_URL ?>materiel/download/${att.id}" class="btn btn-sm btn-outline-success me-1"><i class="bi bi-download"></i></a>
                <a href="<?= BASE_URL ?>materiel/toggleAttachmentVisibility/${materielId}/${att.id}" class="btn btn-sm btn-outline-warning me-1"><i class="bi ${att.masque_client == 1 ? 'bi-eye' : 'bi-eye-slash'}"></i></a>
                <a href="<?= BASE_URL ?>materiel/deleteAttachment/${materielId}/${att.id}" class="btn btn-sm btn-outline-danger" onclick="return confirm('Supprimer ?')"><i class="bi bi-trash"></i></a>
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
      if (ext === 'pdf') body.innerHTML = `<iframe src="<?= BASE_URL ?>materiel/preview/${id}" width="100%" height="600px" frameborder="0"></iframe>`;
      else if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(ext)) body.innerHTML = `<img src="<?= BASE_URL ?>materiel/preview/${id}" class="img-fluid">`;
      else body.innerHTML = `<div class="alert alert-info">Prévisualisation non disponible. <a href="<?= BASE_URL ?>materiel/download/${id}" target="_blank">Télécharger</a></div>`;
      modal.show();
    }
    function formatFileSize(bytes) {
      if (!bytes) return '0 Bytes';
      const k = 1024, sizes = ['Bytes', 'KB', 'MB', 'GB'], i = Math.floor(Math.log(bytes) / Math.log(k));
      return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    function escapeHtml(text) {
      const d = document.createElement('div'); d.textContent = text; return d.innerHTML;
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

    // ── DOMContentLoaded ──────────────────────────────────────────────────────────
    document.addEventListener('DOMContentLoaded', function () {

      <?php if (!empty($filters['client_id']) && !empty($materiel_organise) && !$isGlobalSearch): ?>
        <?php foreach ($materiel_organise as $client_nom => $sites): ?>
          <?php foreach ($sites as $site_nom => $buildings): ?>
            <?php foreach ($buildings as $building_nom => $salles): ?>
              <?php foreach ($salles as $salle_nom => $materiels):
                $salle_id = 'salle_' . md5($client_nom . $site_nom . $building_nom . $salle_nom);
                $locationString = h($site_nom) . ' - ' . h($building_nom) . ' - ' . h($salle_nom);
                ?>
                  (function () {
                    const container = document.getElementById('excelTable-<?= $salle_id ?>');
                    if (!container) return;

                    const data = <?= json_encode(array_map(function ($m) use ($allColumns, $pieces_jointes_count) {
                      $rowData = [];
                      foreach ($allColumns as $col) {
                        if ($col['field'] === 'pieces_jointes') {
                          $rowData[] = ['count' => $pieces_jointes_count[$m['id']] ?? 0, 'id' => $m['id'], 'name' => ($m['marque'] ?? '') . ' ' . ($m['modele'] ?? '')];
                        } else {
                          $rowData[] = $m[$col['field']] ?? '';
                        }
                      }
                      return $rowData;
                    }, $materiels)); ?>;

                    const COLUMN_FORMATS = {
                      'date_fin_maintenance': { type: 'date', dateFormat: 'YYYY-MM-DD' },
                      'date_fin_garantie': { type: 'date', dateFormat: 'YYYY-MM-DD' },
                      'date_derniere_inter': { type: 'date', dateFormat: 'YYYY-MM-DD' },
                    };
                    const COLUMN_PLACEHOLDERS = {
                      'date_fin_maintenance': 'YYYY-MM-DD',
                      'date_fin_garantie': 'YYYY-MM-DD',
                      'date_derniere_inter': 'YYYY-MM-DD',
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
                        }
                      };
                    }

                    const allColumnFields = <?= json_encode(array_column($allColumns, 'field')) ?>;

                    const columns = allColumnFields.map(field => {
                      const fmt = COLUMN_FORMATS[field];
                      const ph = COLUMN_PLACEHOLDERS[field];
                      if (!fmt) {
                        if (!ph) return { type: 'text' };
                        return { type: 'text', renderer: makePlaceholderRenderer(ph) };
                      }
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
                      if (fmt.validator) {
                        return {
                          type: 'text',
                          renderer: makePlaceholderRenderer(ph),
                          validator: function (value, callback) {
                            if (!value || value === '') return callback(true);
                            callback(fmt.validator.test(value));
                          },
                          allowInvalid: true
                        };
                      }
                      return { type: 'text' };
                    });

                    const hot = new Handsontable(container, {
                      data: data,
                      colHeaders: <?= json_encode($colHeaders) ?>,
                      columns: columns,
                      hiddenColumns: {
                        columns: (function () {
                          const saved = localStorage.getItem('materiel_columns_visibility');
                          if (!saved) return <?= json_encode($hiddenColumns) ?>;
                          try {
                            const state = JSON.parse(saved);
                            return Object.keys(state).map(k => parseInt(k)).filter(k => state[k] === false);
                          } catch (e) { return <?= json_encode($hiddenColumns) ?>; }
                        })(),
                        indicators: true
                      },
                      rowHeaders: false,
                      licenseKey: 'non-commercial-and-evaluation',
                      stretchH: 'all',
                      height: 'auto',
                      cells: function (row, col) {
                        const header = this.colHeaders[col];

                        if (header === 'Marque') {
                          return {
                            renderer: function (instance, td, row, col, prop, value) {
                              const id = instance.getDataAtCell(row, ID_INDEX);
                              td.innerHTML = '';
                              if (id) {
                                const urlParams = new URLSearchParams(window.location.search);
                                const link = document.createElement('a');
                                link.href = '<?= BASE_URL ?>materiel/view/' + id + (urlParams.toString() ? '?' + urlParams : '');
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
          });

          hot.__salleId = <?= $materiels[0]['salle_id'] ?? 'null' ?>;
          hotInstances['excelTable-<?= $salle_id ?>'] = hot;
        })();
      <?php endforeach; ?>
      <?php endforeach; ?>
      <?php endforeach; ?>
      <?php endforeach; ?>

      // ── CORRECTION : initialisation silencieuse des tableaux HOT ─────────────
      requestAnimationFrame(() => {
        document.querySelectorAll('.accordion-collapse').forEach(c => c.classList.add('show'));
        requestAnimationFrame(() => {
          Object.values(hotInstances).forEach(hot => hot.render());
          document.querySelectorAll('.accordion-collapse').forEach(c => {
            c.classList.remove('show');
            const btn = c.closest('.accordion-item')?.querySelector('.accordion-button');
            if (btn) btn.classList.add('collapsed');
          });
        });
      });

      <?php elseif ($isGlobalSearch && !empty($materiel_organise)): ?>
      // Initialisation pour les résultats de recherche
      <?php foreach ($materiel_organise as $client_nom => $sites): ?>
      <?php foreach ($sites as $site_nom => $buildings): ?>
      <?php foreach ($buildings as $building_nom => $salles): ?>
      <?php foreach ($salles as $salle_nom => $materiels):
            $salle_id = 'salle_' . md5($client_nom . $site_nom . $building_nom . $salle_nom);
            $locationString = h($site_nom) . ' - ' . h($building_nom) . ' - ' . h($salle_nom);
            ?>
        (function () {
          const container = document.getElementById('excelTable-<?= $salle_id ?>');
          if (!container) return;

          const data = <?= json_encode(array_map(function ($m) use ($allColumns, $pieces_jointes_count) {
            $rowData = [];
            foreach ($allColumns as $col) {
              if ($col['field'] === 'pieces_jointes') {
                $rowData[] = ['count' => $pieces_jointes_count[$m['id']] ?? 0, 'id' => $m['id'], 'name' => ($m['marque'] ?? '') . ' ' . ($m['modele'] ?? '')];
              } else {
                $rowData[] = $m[$col['field']] ?? '';
              }
            }
            return $rowData;
          }, $materiels)); ?>;

          const COLUMN_FORMATS = {
            'date_fin_maintenance': { type: 'date', dateFormat: 'YYYY-MM-DD' },
            'date_fin_garantie': { type: 'date', dateFormat: 'YYYY-MM-DD' },
            'date_derniere_inter': { type: 'date', dateFormat: 'YYYY-MM-DD' },
          };
          const COLUMN_PLACEHOLDERS = {
            'date_fin_maintenance': 'YYYY-MM-DD',
            'date_fin_garantie': 'YYYY-MM-DD',
            'date_derniere_inter': 'YYYY-MM-DD',
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
              }
            };
          }

          const allColumnFields = <?= json_encode(array_column($allColumns, 'field')) ?>;

          const columns = allColumnFields.map(field => {
            const fmt = COLUMN_FORMATS[field];
            const ph = COLUMN_PLACEHOLDERS[field];
            if (!fmt) {
              if (!ph) return { type: 'text' };
              return { type: 'text', renderer: makePlaceholderRenderer(ph) };
            }
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
            if (fmt.validator) {
              return {
                type: 'text',
                renderer: makePlaceholderRenderer(ph),
                validator: function (value, callback) {
                  if (!value || value === '') return callback(true);
                  callback(fmt.validator.test(value));
                },
                allowInvalid: true
              };
            }
            return { type: 'text' };
          });

          const hot = new Handsontable(container, {
            data: data,
            colHeaders: <?= json_encode($colHeaders) ?>,
            columns: columns,
            hiddenColumns: {
              columns: (function () {
                const saved = localStorage.getItem('materiel_columns_visibility');
                if (!saved) return <?= json_encode($hiddenColumns) ?>;
                try {
                  const state = JSON.parse(saved);
                  return Object.keys(state).map(k => parseInt(k)).filter(k => state[k] === false);
                } catch (e) { return <?= json_encode($hiddenColumns) ?>; }
              })(),
              indicators: true
            },
            rowHeaders: false,
            licenseKey: 'non-commercial-and-evaluation',
            stretchH: 'all',
            height: 'auto',
            cells: function (row, col) {
              const header = this.colHeaders[col];

              if (header === 'Marque') {
                return {
                  renderer: function (instance, td, row, col, prop, value) {
                    const id = instance.getDataAtCell(row, ID_INDEX);
                    td.innerHTML = '';
                    if (id) {
                      const urlParams = new URLSearchParams(window.location.search);
                      const link = document.createElement('a');
                      link.href = '<?= BASE_URL ?>materiel/view/' + id + (urlParams.toString() ? '?' + urlParams : '');
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
          });

          hot.__salleId = <?= $materiels[0]['salle_id'] ?? 'null' ?>;
          hotInstances['excelTable-<?= $salle_id ?>'] = hot;
        })();
      <?php endforeach; ?>
      <?php endforeach; ?>
      <?php endforeach; ?>
      <?php endforeach; ?>

      // Initialisation des tableaux pour la recherche
      requestAnimationFrame(() => {
        document.querySelectorAll('.accordion-collapse').forEach(c => c.classList.add('show'));
        requestAnimationFrame(() => {
          Object.values(hotInstances).forEach(hot => hot.render());
        });
      });

      <?php endif; ?>

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
      const searchBtn = document.getElementById('searchBtn');
      const openBtn = document.getElementById('openAllAccordions');
      const closeBtn = document.getElementById('closeAllAccordions');

      // ── RECHERCHE EN TEMPS RÉEL AVEC DEBOUNCE ──────────────────────────────
      if (searchInput) {
        // Événement 'input' déclenché à chaque frappe
        searchInput.addEventListener('input', function (e) {
          const term = this.value.trim();

          // Afficher/masquer le bouton clear
          clearBtn.style.display = term ? 'inline-block' : 'none';

          // Annuler le timeout précédent
          if (searchDebounceTimer) {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = null;
          }

          // Si le terme est vide ou trop court, effacer la recherche
          if (!term || term.length < 2) {
            // Attendre un peu pour voir si l'utilisateur continue de taper
            searchDebounceTimer = setTimeout(() => {
              const currentUrl = new URL(window.location.href);
              if (currentUrl.searchParams.has('search')) {
                currentUrl.searchParams.delete('search');
                window.location.href = currentUrl.toString();
              }
            }, 300);
            return;
          }

          // Lancer la recherche après le délai (400ms)
          searchDebounceTimer = setTimeout(() => {
            performGlobalSearch(term);
          }, 400);
        });
      }

      // Bouton "Effacer" - supprime la recherche
      if (clearBtn) {
        clearBtn.addEventListener('click', function () {
          const currentUrl = new URL(window.location.href);
          currentUrl.searchParams.delete('search');
          window.location.href = currentUrl.toString();
        });
      }

      // Bouton "Rechercher" - recherche manuelle forcée
      if (searchBtn) {
        searchBtn.addEventListener('click', function () {
          const term = searchInput.value.trim();
          if (searchDebounceTimer) {
            clearTimeout(searchDebounceTimer);
            searchDebounceTimer = null;
          }
          performGlobalSearch(term);
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
    });

    // ── saveAllTablesData ─────────────────────────────────────────────────────────
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
            fetch('<?= BASE_URL ?>views/excel/excel_save.php', {
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
            fetch('<?= BASE_URL ?>materiel/store', {
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
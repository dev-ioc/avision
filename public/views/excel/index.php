<?php
require_once __DIR__ . '/../../includes/functions.php';
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
$allColumnsConfig = [
  0 => ['label' => 'Équipement', 'field' => 'equipement', 'default' => true, 'orderable' => true, 'searchable' => true],
  1 => ['label' => 'Type', 'field' => 'type_materiel', 'default' => true, 'orderable' => true, 'searchable' => true],
  2 => ['label' => 'S/N', 'field' => 'numero_serie', 'default' => true, 'orderable' => true, 'searchable' => true],
  3 => ['label' => 'Firmware', 'field' => 'version_firmware', 'default' => true, 'orderable' => true, 'searchable' => true],
  4 => ['label' => 'IP', 'field' => 'adresse_ip', 'default' => true, 'orderable' => true, 'searchable' => true],
  5 => ['label' => 'MAC', 'field' => 'adresse_mac', 'default' => true, 'orderable' => true, 'searchable' => true],
  6 => ['label' => 'Expiration', 'field' => 'expiration', 'default' => true, 'orderable' => true, 'searchable' => true],
  7 => ['label' => 'Pièces jointes', 'field' => 'pieces_jointes', 'default' => true, 'orderable' => false, 'searchable' => false],
  // Colonnes supplémentaires
  8 => ['label' => 'Référence', 'field' => 'reference', 'default' => false, 'orderable' => true, 'searchable' => true],
  9 => ['label' => 'Usage', 'field' => 'usage_materiel', 'default' => false, 'orderable' => true, 'searchable' => true],
  10 => ['label' => 'Marque', 'field' => 'marque', 'default' => false, 'orderable' => true, 'searchable' => true],
  11 => ['label' => 'Modèle', 'field' => 'modele', 'default' => false, 'orderable' => true, 'searchable' => true],
  12 => ['label' => 'Ancien Firmware', 'field' => 'ancien_firmware', 'default' => false, 'orderable' => true, 'searchable' => true],
  13 => ['label' => 'Masque', 'field' => 'masque', 'default' => false, 'orderable' => true, 'searchable' => true],
  14 => ['label' => 'Passerelle', 'field' => 'passerelle', 'default' => false, 'orderable' => true, 'searchable' => true],
  15 => ['label' => 'Login', 'field' => 'login', 'default' => false, 'orderable' => true, 'searchable' => true],
  16 => ['label' => 'Password', 'field' => 'password', 'default' => false, 'orderable' => true, 'searchable' => true],
  17 => ['label' => 'ID Matériel', 'field' => 'id_materiel', 'default' => false, 'orderable' => true, 'searchable' => true],
  18 => ['label' => 'IP Primaire', 'field' => 'ip_primaire', 'default' => false, 'orderable' => true, 'searchable' => true],
  19 => ['label' => 'MAC Primaire', 'field' => 'mac_primaire', 'default' => false, 'orderable' => true, 'searchable' => true],
  20 => ['label' => 'IP Secondaire', 'field' => 'ip_secondaire', 'default' => false, 'orderable' => true, 'searchable' => true],
  21 => ['label' => 'MAC Secondaire', 'field' => 'mac_secondaire', 'default' => false, 'orderable' => true, 'searchable' => true],
  22 => ['label' => 'Stream AES67 Reçu', 'field' => 'stream_aes67_recu', 'default' => false, 'orderable' => true, 'searchable' => true],
  23 => ['label' => 'Stream AES67 Transmis', 'field' => 'stream_aes67_transmis', 'default' => false, 'orderable' => true, 'searchable' => true],
  24 => ['label' => 'SSID WiFi', 'field' => 'ssid', 'default' => false, 'orderable' => true, 'searchable' => true],
  25 => ['label' => 'Type Cryptage WiFi', 'field' => 'type_cryptage', 'default' => false, 'orderable' => true, 'searchable' => true],
  26 => ['label' => 'Password WiFi', 'field' => 'password_wifi', 'default' => false, 'orderable' => true, 'searchable' => true],
  27 => ['label' => 'Libellé PA Salle', 'field' => 'libelle_pa_salle', 'default' => false, 'orderable' => true, 'searchable' => true],
  28 => ['label' => 'N° Port Switch', 'field' => 'numero_port_switch', 'default' => false, 'orderable' => true, 'searchable' => true],
  29 => ['label' => 'VLAN', 'field' => 'vlan', 'default' => false, 'orderable' => true, 'searchable' => true],
  30 => ['label' => 'Date Fin Maintenance', 'field' => 'date_fin_maintenance', 'default' => false, 'orderable' => true, 'searchable' => true],
  31 => ['label' => 'Date Fin Garantie', 'field' => 'date_fin_garantie', 'default' => false, 'orderable' => true, 'searchable' => true],
  32 => ['label' => 'Date Dernière Inter', 'field' => 'date_derniere_inter', 'default' => false, 'orderable' => true, 'searchable' => true],
  33 => ['label' => 'Commentaire', 'field' => 'commentaire', 'default' => false, 'orderable' => true, 'searchable' => true],
  34 => ['label' => 'URL GitHub', 'field' => 'url_github', 'default' => false, 'orderable' => true, 'searchable' => true],
];
?>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.css">
<script src="https://cdn.jsdelivr.net/npm/handsontable/dist/handsontable.full.min.js"></script>
</div>
<div class="container-fluid grow container-p-y">
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
            <!-- Bouton pour l'import/export en masse -->
            <?php
            $bulkParams = [];
            if (!empty($filters['client_id'])) {
              $bulkParams['client_id'] = $filters['client_id'];
            }
            if (!empty($filters['site_id'])) {
              $bulkParams['site_id'] = $filters['site_id'];
            }

            $bulkUrl = BASE_URL . 'materiel_bulk';
            if (!empty($bulkParams)) {
              $bulkUrl .= '?' . http_build_query($bulkParams);
            }
            ?>
            <a href="<?= $bulkUrl ?>" class="btn btn-info">
              <i class="bi bi-arrow-left-right me-2 me-1"></i>Import/Export en Masse
            </a>
          <?php endif; ?>
          <?php if (canDeleteDocumentation()): ?>
            <!-- Bouton suppression en masse -->
            <?php
            $bulkDeleteParams = [];
            if (!empty($filters['client_id'])) {
              $bulkDeleteParams['client_id'] = $filters['client_id'];
            }
            if (!empty($filters['site_id'])) {
              $bulkDeleteParams['site_id'] = $filters['site_id'];
            }
            if (!empty($filters['salle_id'])) {
              $bulkDeleteParams['salle_id'] = $filters['salle_id'];
            }
            $bulkDeleteUrl = BASE_URL . 'materiel_bulk/bulk_delete';
            if (!empty($bulkDeleteParams)) {
              $bulkDeleteUrl .= '?' . http_build_query($bulkDeleteParams);
            }
            ?>
            <a href="<?= $bulkDeleteUrl ?>" class="btn btn-outline-danger">
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
    <!-- Message d'instruction quand aucun client n'est sélectionné -->
    <div class="card">
      <div class="card-body text-center py-5">
        <i class="fas fa-filter fa-3x text-muted mb-3"></i>
        <h5 class="text-muted">Sélectionnez un client pour voir le matériel</h5>
        <p class="text-muted mb-3">Choisissez un client dans le filtre ci-dessus pour afficher le matériel associé.</p>
        <div class="row justify-content-center">
          <div class="col-md-6">
            <div class="alert alert-info">
              <i class="bi bi-info-circle me-2 me-1"></i>
              <strong>Astuce :</strong> Commencez par sélectionner un client, puis un site et enfin une salle pour affiner
              votre recherche.
            </div>
          </div>
        </div>
      </div>
    </div>
  <?php elseif (empty($materiel_organise)): ?>
    <div class="card">
      <div class="card-body text-center py-5">
        <i class="bi bi-hdd-network fa-3x text-muted mb-3 me-1"></i>
        <h5 class="text-muted">Aucun matériel trouvé</h5>
        <p class="text-muted mb-3">Aucun matériel ne correspond aux critères sélectionnés.</p>
        <?php
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
      </div>
    </div>
  <?php else: ?>
    <!-- Recherche globale et contrôles -->
    <div class="card mb-4">
      <div class="card-body">
        <div class="row align-items-center">
          <div class="col-md-6">
            <label for="globalSearch" class="form-label fw-bold mb-2">
              <i class="bi bi-search me-2"></i>Recherche globale
            </label>
            <input type="text" class="form-control" id="globalSearch" placeholder="Rechercher dans tous les tableaux..."
              autocomplete="off" style="pointer-events: auto; z-index: 1;">
            <small class="text-muted">La recherche s'applique à tous les tableaux de toutes les salles</small>
          </div>
          <div class="col-md-6 text-end">
            <div class="d-flex gap-2 justify-content-end align-items-end">
              <button type="button" class="btn btn-outline-secondary" id="clearGlobalSearch" style="display: none;">
                <i class="bi bi-x-lg me-1"></i>Effacer
              </button>
              <div class="btn-group">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown"
                  aria-expanded="false" id="globalColvisBtn">
                  <i class="bi bi-list-check me-1"></i>Colonnes
                </button>
                <ul class="dropdown-menu dropdown-menu-end" id="globalColvisMenu"
                  style="max-height: 500px; overflow-y: auto;">
                  <li>
                    <h6 class="dropdown-header">Afficher/Masquer les colonnes</h6>
                  </li>
                  <li>
                    <hr class="dropdown-divider">
                  </li>
                  <?php
                  // Définir toutes les colonnes disponibles avec leurs libellés
                  $allColumns = [
                    0 => ['label' => 'Équipement', 'field' => 'equipement', 'default' => true],
                    1 => ['label' => 'Type', 'field' => 'type_materiel', 'default' => true],
                    2 => ['label' => 'S/N', 'field' => 'numero_serie', 'default' => true],
                    3 => ['label' => 'Firmware', 'field' => 'version_firmware', 'default' => true],
                    4 => ['label' => 'IP', 'field' => 'adresse_ip', 'default' => true],
                    5 => ['label' => 'MAC', 'field' => 'adresse_mac', 'default' => true],
                    6 => ['label' => 'Expiration', 'field' => 'expiration', 'default' => true],
                    7 => ['label' => 'Pièces jointes', 'field' => 'pieces_jointes', 'default' => true],
                    // Colonnes supplémentaires disponibles
                    8 => ['label' => 'Référence', 'field' => 'reference', 'default' => false],
                    9 => ['label' => 'Usage', 'field' => 'usage_materiel', 'default' => false],
                    10 => ['label' => 'Marque', 'field' => 'marque', 'default' => false],
                    11 => ['label' => 'Modèle', 'field' => 'modele', 'default' => false],
                    12 => ['label' => 'Ancien Firmware', 'field' => 'ancien_firmware', 'default' => false],
                    13 => ['label' => 'Masque', 'field' => 'masque', 'default' => false],
                    14 => ['label' => 'Passerelle', 'field' => 'passerelle', 'default' => false],
                    15 => ['label' => 'Login', 'field' => 'login', 'default' => false],
                    16 => ['label' => 'Password', 'field' => 'password', 'default' => false],
                    17 => ['label' => 'ID Matériel', 'field' => 'id_materiel', 'default' => false],
                    18 => ['label' => 'IP Primaire', 'field' => 'ip_primaire', 'default' => false],
                    19 => ['label' => 'MAC Primaire', 'field' => 'mac_primaire', 'default' => false],
                    20 => ['label' => 'IP Secondaire', 'field' => 'ip_secondaire', 'default' => false],
                    21 => ['label' => 'MAC Secondaire', 'field' => 'mac_secondaire', 'default' => false],
                    22 => ['label' => 'Stream AES67 Reçu', 'field' => 'stream_aes67_recu', 'default' => false],
                    23 => ['label' => 'Stream AES67 Transmis', 'field' => 'stream_aes67_transmis', 'default' => false],
                    24 => ['label' => 'SSID WiFi', 'field' => 'ssid', 'default' => false],
                    25 => ['label' => 'Type Cryptage WiFi', 'field' => 'type_cryptage', 'default' => false],
                    26 => ['label' => 'Password WiFi', 'field' => 'password_wifi', 'default' => false],
                    27 => ['label' => 'Libellé PA Salle', 'field' => 'libelle_pa_salle', 'default' => false],
                    28 => ['label' => 'N° Port Switch', 'field' => 'numero_port_switch', 'default' => false],
                    29 => ['label' => 'VLAN', 'field' => 'vlan', 'default' => false],
                    30 => ['label' => 'Date Fin Maintenance', 'field' => 'date_fin_maintenance', 'default' => false],
                    31 => ['label' => 'Date Fin Garantie', 'field' => 'date_fin_garantie', 'default' => false],
                    32 => ['label' => 'Date Dernière Inter', 'field' => 'date_derniere_inter', 'default' => false],
                    33 => ['label' => 'Commentaire', 'field' => 'commentaire', 'default' => false],
                    34 => ['label' => 'URL GitHub', 'field' => 'url_github', 'default' => false],
                  ];

                  foreach ($allColumns as $colIndex => $colInfo):
                    $checked = $colInfo['default'] ? 'checked' : '';
                    ?>
                    <li><label class="dropdown-item"><input type="checkbox"
                          class="form-check-input me-2 global-colvis-checkbox" data-col="<?= $colIndex ?>"
                          data-field="<?= $colInfo['field'] ?>" <?= $checked ?>>
                        <?= h($colInfo['label']) ?>
                      </label></li>
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

    <!-- Container pour tous les accordéons -->
    <div id="accordionContainer">
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
                $salle_id = 'salle_' . md5($client_nom . $site_nom . $salle_nom);
                $accordion_id = 'accordion_' . $salle_id;
                ?>
                <div class="accordion mb-3" id="<?= $accordion_id ?>">
                  <div class="accordion-item">
                    <h2 class="accordion-header">
                      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#collapse_<?= $salle_id ?>" aria-expanded="false"
                        aria-controls="collapse_<?= $salle_id ?>">
                        <div class="d-flex justify-content-between w-100 me-3">
                          <span>
                            <i class="bi bi-door-open me-2 text-info"></i>
                            <strong>
                              <?= h($salle_nom) ?>
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
                        <div class="table-wrapper">
                          <div id="excelTable-<?= $salle_id ?>"></div>
                        </div>
                      </div>
                    </div>
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
                    height: 'auto',
                    width: '100%',
                    manualColumnResize: true,
                    columns: [
                      {
                        renderer: function (instance, td, row, col, prop, value) {
                          if (!value) value = '';
                          const parts = value.split('\n');
                          const marque = parts[0] || '';
                          const modele = parts[1] || '';
                          td.innerHTML = `${marque}<br><span style="font-size:0.75rem; font-weight: none">${modele}</span>`;
                          td.style.whiteSpace = 'normal';
                          td.style.wordWrap = 'break-word';
                        }
                      },
                      {}, {}, {}, {}, {}, {},
                      {
                        renderer: function (instance, td, row, col, prop, value) {
                          const count = value?.count ?? 0;
                          const id = value?.id;
                          const name = value?.name ?? '';
                          td.innerHTML = `
                                          <button 
                                            class="btn btn-sm ${count > 0 ? 'btn-outline-info' : 'btn-outline-secondary'}"
                                            onclick="openAttachmentsModal(${id}, '${name.replace(/'/g, "\\'")}')"
                                            style="white-space: nowrap;"
                                          >
                                            <i class="bi bi-paperclip"></i>
                                            <span class="badge ${count > 0 ? 'bg-info' : 'bg-secondary'}">
                                              ${count}
                                            </span>
                                          </button>
                                        `;
                  td.style.textAlign = 'center';
                }
              }
            ]
          });

          // Stocker l'instance Handsontable pour la recherche
          if (!window.hotInstances) window.hotInstances = {};
          window.hotInstances['excelTable-<?= $salle_id ?>'] = hot;
                              });
        </script>

        <?php endforeach; ?>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>

<!-- Modales -->
<div class="modal fade" id="attachmentsModal" tabindex="-1" aria-labelledby="attachmentsModalLabel" aria-hidden="true">
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
    border: none;
    padding: 10px;
  }

  .handsontable td {
    background-color: #ffffff;
    border-bottom: 1px solid #dee2e6;
    padding: 8px;
    vertical-align: middle;
  }

  .handsontable td:nth-child(1) {
    background-color: #ffffff !important;
    color: #0d6efd !important;
    font-weight: 600;
  }

  .handsontable td:nth-child(2),
  .handsontable td:nth-child(3),
  .handsontable td:nth-child(4),
  .handsontable td:nth-child(5),
  .handsontable td:nth-child(6),
  .handsontable td:nth-child(7) {
    background-color: #f3e1b5 !important;
  }

  .handsontable td:nth-child(8) {
    background-color: #f8f9fa !important;
    text-align: center;
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

  // Fonctions pour les filtres
  function updateSitesAndSubmit() {
    const clientId = document.getElementById('client_id').value;
    if (clientId) {
      fetch('<?= BASE_URL ?>materiel/get_sites?client_id=' + clientId)
        .then(response => response.json())
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
        .catch(error => console.error('Erreur:', error));
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
        .then(response => response.json())
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
        .catch(error => console.error('Erreur:', error));
    } else {
      document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
      document.getElementById('filterForm').submit();
    }
  }

  // RECHERCHE GLOBALE
  function applyGlobalSearch() {
    const searchTerm = document.getElementById('globalSearch').value.toLowerCase();
    currentSearchTerm = searchTerm;

    // Afficher/masquer le bouton effacer
    const clearBtn = document.getElementById('clearGlobalSearch');
    if (searchTerm.length > 0) {
      clearBtn.style.display = 'inline-block';
    } else {
      clearBtn.style.display = 'none';
    }

    // Parcourir toutes les instances Handsontable
    if (window.hotInstances) {
      Object.keys(window.hotInstances).forEach(tableId => {
        const hot = window.hotInstances[tableId];
        if (hot && typeof hot.getData === 'function') {
          const data = hot.getData();
          const searchResults = [];

          // Chercher dans chaque ligne
          for (let i = 0; i < data.length; i++) {
            let rowMatches = false;
            const rowData = data[i];

            for (let j = 0; j < rowData.length; j++) {
              const cellValue = rowData[j];
              // Ignorer la colonne pièces jointes (objet)
              if (j === 7) continue;

              if (cellValue && cellValue.toString().toLowerCase().includes(searchTerm)) {
                rowMatches = true;
                break;
              }
            }
            searchResults.push(rowMatches);
          }

          // Appliquer le filtre
          if (hot.getPlugin && hot.getPlugin('filters')) {
            const filtersPlugin = hot.getPlugin('filters');
            if (searchTerm === '') {
              filtersPlugin.clearConditions();
              filtersPlugin.filter();
            } else {
              // Réinitialiser puis appliquer le filtre personnalisé
              filtersPlugin.clearConditions();
              hot.render();

              // Masquer les lignes qui ne correspondent pas
              for (let i = 0; i < data.length; i++) {
                const row = hot.getCell(i, 0);
                if (row) {
                  const tr = row.parentNode;
                  if (tr) {
                    tr.style.display = searchResults[i] ? '' : 'none';
                  }
                }
              }
            }
          }
        }
      });
    }

    // Mettre à jour la visibilité des accordéons
    updateAccordionsVisibility(searchTerm);
  }

  function updateAccordionsVisibility(searchTerm) {
    if (!searchTerm || searchTerm.trim().length === 0) {
      document.querySelectorAll('.accordion-item').forEach(item => {
        item.style.display = '';
      });
      return;
    }

    document.querySelectorAll('.accordion-item').forEach(accordionItem => {
      const collapseDiv = accordionItem.querySelector('.accordion-collapse');
      if (!collapseDiv) return;

      const tableId = collapseDiv.id.replace('collapse_', 'excelTable-');
      const hot = window.hotInstances && window.hotInstances[tableId];

      let hasVisibleRow = false;

      if (hot && typeof hot.getData === 'function') {
        const data = hot.getData();
        for (let i = 0; i < data.length; i++) {
          let rowMatches = false;
          for (let j = 0; j < data[i].length; j++) {
            if (j === 7) continue;
            const cellValue = data[i][j];
            if (cellValue && cellValue.toString().toLowerCase().includes(searchTerm)) {
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

  // OUVRI
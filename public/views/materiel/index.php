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
    <?php include_once __DIR__ . '/../excel/index.php' ?>
  <?php endif; ?>
</div>

<script>
  // Variable globale pour l'URL de base
  const baseUrl = '<?= BASE_URL ?>';

  // Fonction pour mettre à jour les sites selon le client sélectionné ET soumettre le formulaire
  function updateSitesAndSubmit() {
    const clientId = document.getElementById('client_id').value;
    console.log('updateSitesAndSubmit appelé avec clientId:', clientId);

    if (clientId) {
      const url = '<?= BASE_URL ?>materiel/get_sites?client_id=' + clientId;
      console.log('URL de la requête:', url);

      fetch(url)
        .then(response => {
          console.log('Réponse reçue:', response);
          if (!response.ok) {
            throw new Error('Erreur HTTP: ' + response.status);
          }
          return response.json();
        })
        .then(data => {
          console.log('Données reçues:', data);
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

          // Réinitialiser les salles
          document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';

          // Soumettre le formulaire après la mise à jour
          document.getElementById('filterForm').submit();
        })
        .catch(error => {
          console.error('Erreur lors de la mise à jour des sites:', error);
          alert('Erreur lors de la mise à jour des sites: ' + error.message);
        });
    } else {
      document.getElementById('site_id').innerHTML = '<option value="">Tous les sites</option>';
      document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';

      // Soumettre le formulaire même si aucun client n'est sélectionné
      document.getElementById('filterForm').submit();
    }
  }

  // Fonction pour mettre à jour les salles selon le site sélectionné ET soumettre le formulaire
  function updateRoomsAndSubmit() {
    const siteId = document.getElementById('site_id').value;
    console.log('updateRoomsAndSubmit appelé avec siteId:', siteId);

    if (siteId) {
      const url = '<?= BASE_URL ?>materiel/get_rooms?site_id=' + siteId;
      console.log('URL de la requête:', url);

      fetch(url)
        .then(response => {
          console.log('Réponse reçue:', response);
          if (!response.ok) {
            throw new Error('Erreur HTTP: ' + response.status);
          }
          return response.json();
        })
        .then(data => {
          console.log('Données reçues:', data);
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

          // Soumettre le formulaire après la mise à jour
          document.getElementById('filterForm').submit();
        })
        .catch(error => {
          console.error('Erreur lors de la mise à jour des salles:', error);
          alert('Erreur lors de la mise à jour des salles: ' + error.message);
        });
    } else {
      document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';

      // Soumettre le formulaire même si aucun site n'est sélectionné
      document.getElementById('filterForm').submit();
    }
  }

  // Fonction pour supprimer un matériel
  function deleteMateriel(materielId) {
    if (confirm('Êtes-vous sûr de vouloir supprimer ce matériel ? Cette action est irréversible.')) {
      const currentUrl = new URL(window.location.href);
      const params = new URLSearchParams(currentUrl.search);

      // Filtrer seulement les paramètres de filtres
      const filterParams = new URLSearchParams();
      if (params.has('client_id')) {
        filterParams.set('client_id', params.get('client_id'));
      }
      if (params.has('site_id')) {
        filterParams.set('site_id', params.get('site_id'));
      }
      if (params.has('salle_id')) {
        filterParams.set('salle_id', params.get('salle_id'));
      }

      window.location.href = `<?= BASE_URL ?>materiel/delete/${materielId}?${filterParams.toString()}`;
    }
  }

  // ===== INITIALISATION DATATABLES DANS LES ACCORDÉONS =====

  // Stocker les instances DataTables pour éviter la réinitialisation
  const dataTablesInstances = {};

  // Fonction pour initialiser DataTables sur un tableau
  function initializeDataTable(tableId) {
    // Vérifier si DataTables est déjà initialisé sur ce tableau
    if (dataTablesInstances[tableId]) {
      return dataTablesInstances[tableId];
    }

    const table = document.getElementById(tableId);
    if (!table) {
      console.warn('Tableau non trouvé:', tableId);
      return null;
    }

    // Vérifier si DataTables est disponible
    if (typeof DataTable === 'undefined') {
      console.error('DataTables n\'est pas chargé');
      return null;
    }

    // Vérifier si DataTables est déjà initialisé
    if (table.classList.contains('dataTable')) {
      console.log('DataTables déjà initialisé pour:', tableId);
      return null;
    }

    // Vérifier que le tableau a des en-têtes et des lignes
    const thead = table.querySelector('thead');
    const tbody = table.querySelector('tbody');
    if (!thead || !tbody) {
      console.warn('Tableau incomplet (pas de thead ou tbody):', tableId);
      return null;
    }

    const headerCells = thead.querySelectorAll('th');
    const rows = tbody.querySelectorAll('tr');

    if (headerCells.length === 0) {
      console.warn('Tableau sans colonnes:', tableId);
      return null;
    }

    // Si le tableau est vide, on peut quand même initialiser DataTables
    if (rows.length === 0) {
      console.log('Tableau vide, initialisation DataTables quand même:', tableId);
    } else {
      // Vérifier que toutes les lignes ont le bon nombre de colonnes
      const expectedCols = headerCells.length;
      let hasInvalidRows = false;
      rows.forEach(function (row, index) {
        const cells = row.querySelectorAll('td');
        if (cells.length !== expectedCols) {
          console.warn(`Ligne ${index} du tableau ${tableId} a ${cells.length} cellules au lieu de ${expectedCols}`);
          hasInvalidRows = true;
        }
      });

      if (hasInvalidRows) {
        console.error('Tableau avec lignes invalides:', tableId);
        return null;
      }
    }

    // Initialiser DataTables
    try {
      // Compter les colonnes pour s'assurer qu'elles correspondent
      const headerCells = table.querySelectorAll('thead th');
      const numCols = headerCells.length;

      console.log(`Initialisation DataTables pour ${tableId}: ${numCols} colonnes détectées`);

      // Configuration simple : laisser DataTables détecter automatiquement les colonnes depuis le HTML
      // Définir la visibilité par défaut des colonnes
      const columnDefs = [];
      headerCells.forEach(function (th, index) {
        const colIndex = parseInt(th.getAttribute('data-col-index'));
        const isDefault = !th.classList.contains('d-none');

        // Si la colonne n'est pas visible par défaut, la masquer dans DataTables
        if (!isDefault) {
          columnDefs.push({
            targets: index,
            visible: false
          });
        }

        // Désactiver le tri sur la colonne pièces jointes
        if (th.getAttribute('data-field') === 'pieces_jointes') {
          columnDefs.push({
            targets: index,
            orderable: false,
            searchable: false
          });
        }
      });

      const dt = new DataTable(table, {
        paging: false, // Désactiver la pagination
        order: [[0, 'asc']], // Trier par équipement par défaut
        language: {
          url: (window.BASE_URL || '<?= BASE_URL ?>') + 'assets/json/locales/datatables-fr.json',
          info: 'Affichage de _TOTAL_ entrée(s)',
          infoEmpty: 'Aucune entrée à afficher',
          infoFiltered: '(filtré à partir de _MAX_ entrées au total)'
        },
        layout: {
          topStart: {
            search: {
              placeholder: 'Rechercher...'
            }
          },
          bottomStart: {
            features: ['info']
          }
        },
        // Utiliser uniquement le layout, pas le DOM par défaut
        dom: 'rt',
        responsive: true,
        // Forcer DataTables à lire depuis le HTML
        processing: false,
        serverSide: false,
        // Configuration des colonnes
        columnDefs: columnDefs,
        // S'assurer que toutes les colonnes sont correctement détectées
        autoWidth: false
      });

      // Stocker l'instance
      dataTablesInstances[tableId] = dt;

      console.log(`DataTables initialisé avec succès pour ${tableId}`);

      // Attacher l'événement column-visibility pour synchroniser avec le menu global
      dt.on('column-visibility', function (e, settings, column, state) {
        const checkbox = document.querySelector('.global-colvis-checkbox[data-col="' + column + '"]');
        if (checkbox && checkbox.checked !== state) {
          checkbox.checked = state;
        }
      });


      // Attacher le bouton de sélection de colonnes au bouton personnalisé
      const colvisBtn = document.getElementById('colvis-btn-' + tableId.replace('table-materiel-', ''));
      if (colvisBtn && dt.buttons) {
        const colvisButton = dt.buttons('colvis:name').nodes()[0];
        if (colvisButton) {
          colvisBtn.addEventListener('click', function () {
            colvisButton.click();
          });
        }
      }

      // Appliquer l'état sauvegardé des colonnes avant le premier draw
      const savedState = localStorage.getItem('materiel_colvis_state');
      if (savedState) {
        try {
          const state = JSON.parse(savedState);
          console.log('Application de l\'état sauvegardé au tableau', tableId, state);
          Object.keys(state).forEach(function (colIndex) {
            const isVisible = state[colIndex];
            const colIndexInt = parseInt(colIndex);
            const column = dt.column(colIndexInt);
            if (column) {
              column.visible(isVisible, false);
              console.log('Colonne', colIndexInt, 'mise à', isVisible, 'pour le tableau', tableId);
            }
            // Mettre à jour aussi les classes CSS dans le HTML
            const table = document.getElementById(tableId);
            if (table) {
              table.querySelectorAll('thead th[data-col-index="' + colIndexInt + '"]').forEach(function (th) {
                if (isVisible) {
                  th.classList.remove('d-none');
                } else {
                  th.classList.add('d-none');
                }
              });
              table.querySelectorAll('tbody td[data-col-index="' + colIndexInt + '"]').forEach(function (td) {
                if (isVisible) {
                  td.classList.remove('d-none');
                } else {
                  td.classList.add('d-none');
                }
              });
            }
          });
          // Mettre à jour les checkboxes pour refléter l'état
          Object.keys(state).forEach(function (colIndex) {
            const checkbox = document.querySelector('.global-colvis-checkbox[data-col="' + colIndex + '"]');
            if (checkbox) {
              checkbox.checked = state[colIndex];
            }
          });
        } catch (e) {
          console.error('Erreur lors de l\'application de l\'état sauvegardé:', e);
        }
      }

      // Forcer l'affichage de toutes les lignes après l'initialisation
      setTimeout(function () {
        try {
          dt.draw(false); // Redessiner sans réinitialiser
        } catch (e) {
          console.error('Erreur lors du redessin:', e);
        }
      }, 50);

      // Ne pas appliquer la recherche automatiquement ici
      // La recherche sera appliquée par l'événement shown.bs.collapse si nécessaire

      return dt;
    } catch (error) {
      console.error('Erreur lors de l\'initialisation de DataTables pour', tableId, error);
      console.error('Détails de l\'erreur:', error.message, error.stack);
      // Afficher les détails du tableau pour le débogage
      const thead = table.querySelector('thead');
      const tbody = table.querySelector('tbody');
      if (thead) {
        const headerCells = thead.querySelectorAll('th');
        console.error(`Nombre de colonnes dans thead: ${headerCells.length}`);
      }
      if (tbody) {
        const rows = tbody.querySelectorAll('tr');
        console.error(`Nombre de lignes dans tbody: ${rows.length}`);
        if (rows.length > 0) {
          const firstRowCells = rows[0].querySelectorAll('td');
          console.error(`Nombre de cellules dans la première ligne: ${firstRowCells.length}`);
        }
      }
      return null;
    }
  }

  // Écouter l'ouverture des accordéons pour initialiser DataTables
  document.addEventListener('DOMContentLoaded', function () {
    // Initialiser les tableaux dans les accordéons déjà ouverts au chargement
    document.querySelectorAll('.accordion-collapse.show').forEach(function (collapse) {
      const tableId = collapse.getAttribute('data-table-id');
      if (tableId) {
        // Petit délai pour s'assurer que l'accordéon est complètement rendu
        setTimeout(function () {
          initializeDataTable(tableId);
        }, 100);
      }
    });

    // Écouter l'ouverture des accordéons
    document.querySelectorAll('.accordion-collapse').forEach(function (collapse) {
      collapse.addEventListener('shown.bs.collapse', function () {
        const tableId = this.getAttribute('data-table-id');
        if (tableId) {
          // Petit délai pour s'assurer que l'accordéon est complètement rendu
          setTimeout(function () {
            const dt = initializeDataTable(tableId);
            // Si une recherche globale existe, l'appliquer après l'initialisation
            // Mais seulement si DataTables est bien initialisé
            if (dt) {
              const globalSearchInput = document.getElementById('globalSearch');
              if (globalSearchInput && globalSearchInput.value && globalSearchInput.value.trim() !== '') {
                // Attendre que DataTables soit complètement initialisé et rendu
                setTimeout(function () {
                  try {
                    // Vérifier que DataTables est toujours valide
                    if (dt && typeof dt.search === 'function') {
                      dt.search(globalSearchInput.value).draw();
                      // Ne PAS appeler updateAccordionsVisibility ici car l'accordéon vient d'être ouvert manuellement
                    }
                  } catch (e) {
                    console.error('Erreur lors de l\'application de la recherche:', e);
                  }
                }, 200);
              }
              // S'assurer que l'accordéon reste visible après l'initialisation
              const accordionItem = collapse.closest('.accordion-item');
              if (accordionItem) {
                accordionItem.style.display = '';
                // Forcer l'affichage des lignes du tableau
                setTimeout(function () {
                  try {
                    if (dt && typeof dt.search === 'function') {
                      dt.search('').draw(false);
                    }
                  } catch (e) {
                    console.error('Erreur lors du forçage de l\'affichage:', e);
                  }
                }, 200);
              }
            }
          }, 100);
        }
      });
    });

    // ===== RECHERCHE GLOBALE =====

    const globalSearchInput = document.getElementById('globalSearch');
    const clearGlobalSearchBtn = document.getElementById('clearGlobalSearch');

    // Fonction pour afficher/masquer les accordéons selon les résultats
    function updateAccordionsVisibility(searchValue) {
      // Si pas de recherche active, ne rien faire (afficher tous les accordéons)
      if (!searchValue || searchValue.trim().length === 0) {
        document.querySelectorAll('.accordion-item').forEach(function (accordionItem) {
          accordionItem.style.display = '';
        });
        return;
      }

      // Parcourir tous les accordéons, même ceux dont le tableau n'est pas encore initialisé
      document.querySelectorAll('.accordion-item').forEach(function (accordionItem) {
        const accordionCollapse = accordionItem.querySelector('.accordion-collapse');
        if (!accordionCollapse) return;

        // Ne JAMAIS masquer un accordéon qui est actuellement ouvert par l'utilisateur
        const isCurrentlyOpen = accordionCollapse.classList.contains('show');

        // Si l'accordéon est ouvert, ne pas le toucher du tout
        if (isCurrentlyOpen) {
          return; // Sortir de cette itération sans modifier l'accordéon
        }

        const tableId = accordionCollapse.getAttribute('data-table-id');
        if (!tableId) return;

        const dt = dataTablesInstances[tableId];

        if (dt) {
          // Tableau déjà initialisé : vérifier les résultats
          // Ne masquer que si une recherche est active
          if (searchValue && searchValue.trim().length > 0) {
            try {
              const visibleRows = dt.rows({ search: 'applied' }).count();
              const totalRows = dt.rows().count();

              // Si le tableau a des lignes mais qu'aucune n'est visible après recherche, masquer
              // Mais seulement si le tableau a vraiment des données
              if (totalRows > 0 && visibleRows === 0) {
                // Masquer l'accordéon s'il n'y a pas de résultats, SAUF s'il est actuellement ouvert
                if (!isCurrentlyOpen) {
                  accordionItem.style.display = 'none';
                }
              } else {
                // Afficher l'accordéon s'il y a des résultats ou si le tableau est vide (pour éviter de masquer par erreur)
                accordionItem.style.display = '';
                // Ouvrir l'accordéon s'il est fermé (sans animation pour éviter les blocages)
                if (accordionCollapse.classList.contains('collapse') && !accordionCollapse.classList.contains('show')) {
                  // Utiliser directement les classes au lieu de Bootstrap.Collapse pour éviter les conflits
                  accordionCollapse.classList.remove('collapse');
                  accordionCollapse.classList.add('show');
                }
              }
            } catch (e) {
              // En cas d'erreur, afficher l'accordéon pour éviter de le masquer par erreur
              console.error('Erreur lors de la vérification des lignes:', e);
              accordionItem.style.display = '';
            }
          } else {
            // Pas de recherche active : afficher tous les accordéons
            accordionItem.style.display = '';
          }
        } else {
          // Tableau pas encore initialisé : vérifier manuellement dans le HTML
          const table = document.getElementById(tableId);
          if (!table) return;

          const tbody = table.querySelector('tbody');
          if (!tbody) return;

          const rows = tbody.querySelectorAll('tr');
          let hasMatch = false;

          if (searchValue && searchValue.length > 0) {
            const searchLower = searchValue.toLowerCase();
            rows.forEach(function (row) {
              const text = row.textContent.toLowerCase();
              if (text.includes(searchLower)) {
                hasMatch = true;
              }
            });
          } else {
            hasMatch = rows.length > 0;
          }

          if (hasMatch || !searchValue || searchValue.trim().length === 0) {
            // Afficher si correspondance trouvée OU si pas de recherche active
            accordionItem.style.display = '';
          } else {
            // Ne pas masquer si l'accordéon est actuellement ouvert
            if (!isCurrentlyOpen) {
              accordionItem.style.display = 'none';
            }
          }
        }
      });
    }

    if (globalSearchInput) {
      // Fonction pour appliquer la recherche
      function applySearch() {
        const searchValue = globalSearchInput.value;

        // Afficher/masquer le bouton effacer
        if (searchValue.length > 0) {
          clearGlobalSearchBtn.style.display = 'inline-block';
        } else {
          clearGlobalSearchBtn.style.display = 'none';
        }

        // Appliquer la recherche à toutes les instances DataTables
        Object.keys(dataTablesInstances).forEach(function (tableId) {
          const dt = dataTablesInstances[tableId];
          if (dt) {
            dt.search(searchValue).draw();
          }
        });

        // Mettre à jour la visibilité des accordéons
        updateAccordionsVisibility(searchValue);
      }

      // Appliquer la recherche uniquement sur Entrée
      globalSearchInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
          e.preventDefault();
          applySearch();
        }
      });

      // Mettre à jour l'affichage du bouton effacer en temps réel
      globalSearchInput.addEventListener('input', function () {
        if (this.value.length > 0) {
          clearGlobalSearchBtn.style.display = 'inline-block';
        } else {
          clearGlobalSearchBtn.style.display = 'none';
        }
      });

      // Effacer la recherche globale
      if (clearGlobalSearchBtn) {
        clearGlobalSearchBtn.addEventListener('click', function () {
          globalSearchInput.value = '';
          this.style.display = 'none';

          // Effacer la recherche dans tous les tableaux
          Object.keys(dataTablesInstances).forEach(function (tableId) {
            const dt = dataTablesInstances[tableId];
            if (dt) {
              dt.search('').draw();
            }
          });

          // Réafficher tous les accordéons
          updateAccordionsVisibility('');
        });
      }
    }

    // ===== BOUTONS OUVRIR/FERMER TOUT =====

    const openAllBtn = document.getElementById('openAllAccordions');
    const closeAllBtn = document.getElementById('closeAllAccordions');

    // Bouton "Ouvrir tout"
    if (openAllBtn) {
      openAllBtn.addEventListener('click', function () {
        const allCollapses = document.querySelectorAll('.accordion-collapse');

        allCollapses.forEach(function (collapse) {
          if (!collapse.classList.contains('show')) {
            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapse);
            bsCollapse.show();

            // Initialiser DataTables après ouverture
            const tableId = collapse.getAttribute('data-table-id');
            if (tableId) {
              collapse.addEventListener('shown.bs.collapse', function initTable() {
                setTimeout(function () {
                  initializeDataTable(tableId);
                }, 100);
                collapse.removeEventListener('shown.bs.collapse', initTable);
              }, { once: true });
            }
          }
        });
      });
    }

    // Bouton "Fermer tout"
    if (closeAllBtn) {
      closeAllBtn.addEventListener('click', function () {
        const allCollapses = document.querySelectorAll('.accordion-collapse');

        allCollapses.forEach(function (collapse) {
          if (collapse.classList.contains('show')) {
            const bsCollapse = bootstrap.Collapse.getOrCreateInstance(collapse);
            bsCollapse.hide();
          }
        });
      });
    }

    // ===== BOUTON GLOBAL DE SÉLECTION DE COLONNES =====

    const STORAGE_KEY = 'materiel_colvis_state';

    // Fonction pour sauvegarder l'état des colonnes
    function saveColumnVisibility() {
      const state = {};
      const checkboxes = document.querySelectorAll('.global-colvis-checkbox');
      checkboxes.forEach(function (checkbox) {
        const colIndex = parseInt(checkbox.getAttribute('data-col'));
        state[colIndex] = checkbox.checked;
      });
      localStorage.setItem(STORAGE_KEY, JSON.stringify(state));
      console.log('État sauvegardé:', state);
    }

    // Fonction pour restaurer l'état des colonnes
    function restoreColumnVisibility() {
      const savedState = localStorage.getItem(STORAGE_KEY);
      if (savedState) {
        try {
          const state = JSON.parse(savedState);
          console.log('État restauré depuis localStorage:', state);
          const checkboxes = document.querySelectorAll('.global-colvis-checkbox');
          checkboxes.forEach(function (checkbox) {
            const colIndex = parseInt(checkbox.getAttribute('data-col'));
            if (state.hasOwnProperty(colIndex)) {
              checkbox.checked = state[colIndex];
            }
          });
          return state;
        } catch (e) {
          console.error('Erreur lors de la restauration de l\'état des colonnes:', e);
        }
      } else {
        console.log('Aucun état sauvegardé trouvé');
      }
      return null;
    }

    // Fonction pour appliquer la visibilité des colonnes à tous les tableaux
    function applyColumnVisibility(colIndex, isVisible) {
      console.log('Application de la visibilité colonne', colIndex, 'à', isVisible);

      // Mettre à jour DataTables EN PREMIER avec redessin immédiat
      Object.keys(dataTablesInstances).forEach(function (tableId) {
        const dt = dataTablesInstances[tableId];
        if (dt) {
          try {
            const column = dt.column(colIndex);
            if (column) {
              // Mettre à jour DataTables avec redessin immédiat
              column.visible(isVisible, true);
              console.log('Colonne', colIndex, 'du tableau', tableId, 'mise à', isVisible);

              // Pour l'affichage, forcer un ajustement supplémentaire des colonnes
              if (isVisible) {
                // Utiliser requestAnimationFrame pour s'assurer que le DOM est à jour
                requestAnimationFrame(function () {
                  try {
                    dt.columns.adjust();
                    console.log('Ajustement des colonnes effectué pour', tableId);
                  } catch (e) {
                    console.error('Erreur lors de l\'ajustement des colonnes:', e);
                  }
                });
              }
            } else {
              console.warn('Colonne', colIndex, 'non trouvée dans DataTables pour', tableId);
            }
          } catch (e) {
            console.error('Erreur lors de la modification de la visibilité de la colonne', colIndex, 'dans', tableId, ':', e);
          }
        }
      });

      // Ensuite mettre à jour les classes CSS dans le HTML
      requestAnimationFrame(function () {
        document.querySelectorAll('thead th[data-col-index="' + colIndex + '"]').forEach(function (th) {
          if (isVisible) {
            th.classList.remove('d-none');
          } else {
            th.classList.add('d-none');
          }
        });

        document.querySelectorAll('tbody td[data-col-index="' + colIndex + '"]').forEach(function (td) {
          if (isVisible) {
            td.classList.remove('d-none');
          } else {
            td.classList.add('d-none');
          }
        });
      });
    }

    const globalColvisCheckboxes = document.querySelectorAll('.global-colvis-checkbox');

    if (globalColvisCheckboxes.length > 0) {
      // Restaurer l'état sauvegardé au chargement (met à jour les checkboxes)
      const restoredState = restoreColumnVisibility();

      // Écouter les changements sur les checkboxes
      globalColvisCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', function () {
          const colIndex = parseInt(this.getAttribute('data-col'));
          const isVisible = this.checked;

          console.log('Checkbox changée - Colonne', colIndex, 'à', isVisible);

          // Appliquer à tous les tableaux
          applyColumnVisibility(colIndex, isVisible);

          // Sauvegarder l'état immédiatement
          saveColumnVisibility();
        });
      });

      // Si un état a été restauré, l'appliquer aux tableaux déjà initialisés
      if (restoredState) {
        console.log('Application de l\'état restauré aux tableaux existants:', restoredState);
        Object.keys(restoredState).forEach(function (colIndex) {
          const isVisible = restoredState[colIndex];
          // Attendre un peu que les tableaux soient prêts
          setTimeout(function () {
            applyColumnVisibility(parseInt(colIndex), isVisible);
          }, 200);
        });
      }
    }
  });

  // ===== MODALE PIÈCES JOINTES =====

  // Fonction pour ouvrir la modale des pièces jointes
  function openAttachmentsModal(materielId, materielName) {
    const modal = new bootstrap.Modal(document.getElementById('attachmentsModal'));
    const modalTitle = document.getElementById('attachmentsModalLabel');
    const modalContent = document.getElementById('attachmentsModalContent');

    modalTitle.textContent = `Pièces jointes - ${materielName}`;
    modalContent.innerHTML = `
        <div class="text-center py-3">
            <div class="spinner-border text-primary" role="status">
                <span class="visually-hidden">Chargement...</span>
            </div>
            <p class="mt-2 text-muted">Chargement des pièces jointes...</p>
        </div>
    `;

    // Stocker l'ID du matériel dans la modale pour l'upload
    document.getElementById('attachmentsModal').setAttribute('data-materiel-id', materielId);

    modal.show();

    // Charger les pièces jointes
    loadAttachments(materielId, modalContent);
  }

  // Fonction pour charger les pièces jointes
  function loadAttachments(materielId, container) {
    fetch('<?= BASE_URL ?>materiel/getAttachments/' + materielId)
      .then(response => response.json())
      .then(data => {
        if (data.success && data.attachments) {
          renderAttachments(data.attachments, container, materielId);
        } else {
          container.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        ${data.error || 'Erreur lors du chargement des pièces jointes'}
                    </div>
                `;
        }
      })
      .catch(error => {
        console.error('Erreur:', error);
        container.innerHTML = `
                <div class="alert alert-danger">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    Erreur lors du chargement des pièces jointes
                </div>
            `;
      });
  }

  // Fonction pour afficher les pièces jointes
  function renderAttachments(attachments, container, materielId) {
    let html = '<div class="mb-3">';

    if (attachments.length === 0) {
      html += `
            <div class="text-center py-4">
                <i class="bi bi-inbox fs-1 text-muted"></i>
                <p class="text-muted mt-3">Aucune pièce jointe disponible</p>
            </div>
        `;
    } else {
      // Trier par date (plus récent en premier)
      attachments.sort((a, b) => new Date(b.date_creation) - new Date(a.date_creation));

      html += '<div class="list-group">';
      attachments.forEach(attachment => {
        const isPdf = attachment.type_fichier && attachment.type_fichier.toLowerCase() === 'pdf';
        const isImage = attachment.type_fichier && ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(attachment.type_fichier.toLowerCase());
        const fileSize = formatFileSize(attachment.taille_fichier || 0);
        const dateCreation = attachment.date_creation ? new Date(attachment.date_creation).toLocaleDateString('fr-FR', {
          day: '2-digit',
          month: '2-digit',
          year: 'numeric',
          hour: '2-digit',
          minute: '2-digit'
        }) : '-';

        html += `
                <div class="list-group-item ${attachment.masque_client == 1 ? 'bg-light-warning' : ''}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="grow">
                            <div class="d-flex align-items-center mb-1">
                                ${attachment.masque_client == 1 ? '<i class="bi bi-eye-slash text-warning me-2" title="Masqué aux clients"></i>' : ''}
                                <strong>${escapeHtml(attachment.nom_fichier || 'Fichier sans nom')}</strong>
                            </div>
                            ${attachment.commentaire ? `<small class="text-muted d-block">${escapeHtml(attachment.commentaire)}</small>` : ''}
                            <small class="text-muted">
                                ${fileSize} • ${dateCreation}
                                ${attachment.created_by_name ? ' • ' + escapeHtml(attachment.created_by_name) : ''}
                            </small>
                        </div>
                        <div class="ms-3">
                            ${isPdf || isImage ? `
                                <button type="button" class="btn btn-sm btn-outline-info me-1" 
                                        onclick="previewAttachment(${attachment.id}, '${escapeHtml(attachment.nom_fichier)}', '${attachment.type_fichier}')"
                                        title="Aperçu">
                                    <i class="bi bi-eye"></i>
                                </button>
                            ` : ''}
                            <a href="<?= BASE_URL ?>materiel/download/${attachment.id}" 
                               class="btn btn-sm btn-outline-success me-1" 
                               title="Télécharger">
                                <i class="bi bi-download"></i>
                            </a>
                            <a href="<?= BASE_URL ?>materiel/toggleAttachmentVisibility/${materielId}/${attachment.id}" 
                               class="btn btn-sm btn-outline-warning me-1" 
                               title="${attachment.masque_client == 1 ? 'Rendre visible aux clients' : 'Masquer aux clients'}"
                               data-confirm="${attachment.masque_client == 1 ? 'Rendre cette pièce jointe visible aux clients ?' : 'Masquer cette pièce jointe aux clients ?'}">
                                <i class="bi ${attachment.masque_client == 1 ? 'bi-eye' : 'bi-eye-slash'}"></i>
                            </a>
                            <a href="<?= BASE_URL ?>materiel/deleteAttachment/${materielId}/${attachment.id}" 
                               class="btn btn-sm btn-outline-danger" 
                               title="Supprimer"
                               data-confirm="Êtes-vous sûr de vouloir supprimer cette pièce jointe ?">
                                <i class="bi bi-trash"></i>
                            </a>
                        </div>
                    </div>
                </div>
            `;
      });
      html += '</div>';
    }

    html += '</div>';

    // Ajouter le bouton pour ajouter des PJ
    html += `
        <div class="d-flex justify-content-end">
            <button type="button" class="btn btn-primary" onclick="openAddAttachmentModal(${materielId})">
                <i class="bi bi-plus me-1"></i> Ajouter des pièces jointes
            </button>
        </div>
    `;

    container.innerHTML = html;

    // Gérer les actions sur les pièces jointes avec délégation d'événements
    // Supprimer l'ancien gestionnaire s'il existe pour éviter les doublons
    const oldHandler = container._attachmentClickHandler;
    if (oldHandler) {
      container.removeEventListener('click', oldHandler);
    }

    // Créer un nouveau gestionnaire
    const clickHandler = function (e) {
      const link = e.target.closest('a[href*="deleteAttachment"], a[href*="toggleAttachmentVisibility"]');
      if (link) {
        e.preventDefault();
        e.stopPropagation();

        const confirmMsg = link.getAttribute('data-confirm') || link.getAttribute('title') || 'Confirmer cette action ?';

        // Si l'utilisateur annule, ne rien faire
        if (!confirm(confirmMsg)) {
          return false;
        }

        // Le backend utilise GET pour deleteAttachment et toggleAttachmentVisibility
        // Récupérer le token CSRF si disponible (pour compatibilité future)
        const csrfToken = window.AppConfig?.csrfToken || window.CSRF_TOKEN || '';
        const headers = {
          'X-Requested-With': 'XMLHttpRequest'
        };
        if (csrfToken) {
          headers['X-CSRF-Token'] = csrfToken;
        }

        fetch(link.href, {
          method: 'GET',
          headers: headers,
          credentials: 'same-origin'
        })
          .then(response => {
            if (response.ok || response.redirected) {
              loadAttachments(materielId, container);
            } else {
              alert('Erreur lors de l\'opération');
            }
          })
          .catch((error) => {
            console.error('Erreur lors de la suppression:', error);
            alert('Erreur lors de l\'opération');
          });

        return false;
      }
    };

    // Stocker la référence du gestionnaire pour pouvoir le supprimer plus tard
    container._attachmentClickHandler = clickHandler;
    container.addEventListener('click', clickHandler);
  }

  // Fonction pour prévisualiser une pièce jointe
  function previewAttachment(attachmentId, fileName, fileType) {
    const extension = fileType ? fileType.toLowerCase() : '';
    const isPdf = extension === 'pdf';
    const isImage = ['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension);

    const previewModal = new bootstrap.Modal(document.getElementById('previewAttachmentModal'));
    const previewTitle = document.getElementById('previewAttachmentModalLabel');
    const previewBody = document.getElementById('previewAttachmentModalBody');

    previewTitle.textContent = fileName;

    if (isPdf) {
      previewBody.innerHTML = `
            <iframe src="<?= BASE_URL ?>materiel/preview/${attachmentId}" 
                    width="100%" 
                    height="600px" 
                    frameborder="0">
            </iframe>
        `;
    } else if (isImage) {
      previewBody.innerHTML = `
            <img src="<?= BASE_URL ?>materiel/preview/${attachmentId}" 
                 class="img-fluid" 
                 alt="${escapeHtml(fileName)}">
        `;
    } else {
      previewBody.innerHTML = `
            <div class="alert alert-info">
                <i class="bi bi-info-circle me-1"></i> 
                Ce type de fichier ne peut pas être prévisualisé. 
                <a href="<?= BASE_URL ?>materiel/download/${attachmentId}" 
                   class="alert-link" 
                   target="_blank">
                    Télécharger le fichier
                </a>
            </div>
        `;
    }

    previewModal.show();
  }

  // Fonction utilitaire pour formater la taille des fichiers
  function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  // Fonction utilitaire pour échapper le HTML
  function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
  }

</script>


<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?>
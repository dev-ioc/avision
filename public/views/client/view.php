<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de détail du client
 * Affiche les informations complètes d'un client
 */

// Vérification de l'accès
if (!isset($_SESSION['user'])) {
  header('Location: ' . BASE_URL . 'auth/login');
  exit;
}

// Récupération des données
$client = $client ?? null;
$sites = $sites ?? [];
$contracts = $contracts ?? [];
$contacts = $contacts ?? []; // Assurez-vous que cette variable est définie dans le contrôleur

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Récupérer l'ID du client depuis l'URL
$clientId = isset($client['id']) ? $client['id'] : '';

setPageVariables(
  'Client',
  'clients' . ($clientId ? '_view_' . $clientId : '')
);

// Définir la page courante pour le menu
$currentPage = 'clients';

// Définir les breadcrumbs personnalisés pour la vue client
if (isset($client) && !empty($client)) {
  $GLOBALS['customBreadcrumbs'] = generateClientViewBreadcrumbs($client);
}

// Vérifier si l'utilisateur a les droits pour modifier un client
$canModifyClient = canModifyClients();

// Déterminer l'URL de retour dynamiquement
$returnTo = $_GET['return_to'] ?? null;
if ($returnTo === 'contracts') {
  // Si on vient de la liste des contrats, retourner à cette liste
  $returnUrl = BASE_URL . 'contracts';
} else {
  // Par défaut, retourner à la liste des clients
  $returnUrl = BASE_URL . 'clients';
}

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

  <div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight">
      <h4 class="py-4 mb-6">Détails du client</h4>
    </div>

    <div class="ms-auto p-2 bd-highlight">
      <a href="<?php echo $returnUrl; ?>" class="btn btn-secondary me-2">
        <i class="bi bi-arrow-left me-1"></i> Retour
      </a>
      <a href="<?php echo BASE_URL; ?>documentation?client_id=<?php echo $client['id'] ?? ''; ?>"
        class="btn btn-info me-2">
        <i class="bi bi-book me-1"></i> Documentation
      </a>
      <a href="<?php echo BASE_URL; ?>materiel?client_id=<?php echo $client['id'] ?? ''; ?>"
        class="btn btn-primary me-2">
        <i class="bi bi-box-seam me-1"></i> Matériel
      </a>
      <?php if ($canModifyClient): ?>
        <a href="<?php echo BASE_URL; ?>clients/edit/<?php echo $client['id'] ?? ''; ?>" class="btn btn-warning me-2"
          id="editClientBtn">
          Modifier
        </a>
      <?php else: ?>
        <button type="button" class="btn btn-secondary me-2" disabled
          title="Vous n'avez pas les droits pour modifier ce client">
          Modifier
        </button>
      <?php endif; ?>
      <?php if (isAdmin()): ?>
        <button type="button" class="btn btn-outline-danger btn-sm"
          onclick="confirmDelete(<?php echo $client['id'] ?? 0; ?>, '<?php echo htmlspecialchars($client['name'] ?? ''); ?>')"
          title="Supprimer le client">
          <i class="bi bi-trash"></i>
        </button>
      <?php endif; ?>
    </div>
  </div>

  <?php if (isset($_SESSION['error'])): ?>
    <div class="alert alert-danger">
      <?php
      echo $_SESSION['error'];
      unset($_SESSION['error']);
      ?>
    </div>
  <?php endif; ?>

  <?php if (isset($_SESSION['success'])): ?>
    <div class="alert alert-success">
      <?php
      echo $_SESSION['success'];
      unset($_SESSION['success']);
      ?>
    </div>
  <?php endif; ?>

  <?php if ($client): ?>
    <!-- En-tête du client -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h4 class="mb-0"><?php echo htmlspecialchars($client['name'] ?? ''); ?></h4>
      <span class="badge bg-<?php echo ($client['status'] ?? 0) == 1 ? 'success' : 'danger'; ?> fs-6">
        <?php echo ($client['status'] ?? 0) == 1 ? 'Actif' : 'Inactif'; ?>
      </span>
    </div>

    <!-- Onglets pour les différentes sections -->
    <div class="row g-2 mb-4" id="clientTabs" role="tablist">
      <div class="col">
        <div class="card tab-card active" id="materiel-tab" data-bs-toggle="tab" data-bs-target="#materiel" role="tab"
          aria-controls="materiel" aria-selected="true" style="cursor: pointer; border: 2px solid #007bff;">
          <div class="card-body text-center p-2">
            <div class="mb-1">
              <i class="bi bi-hdd-stack fs-3 text-info"></i>
            </div>
            <h6 class="card-title mb-1 fs-6">Matériel</h6>
            <small class="text-muted d-block">
              <?php echo $stats['materiel_count'] ?? 0; ?> équipement(s)
            </small>
          </div>
        </div>
        <?php if ($canModifyClient): ?>
          <div class="text-center mt-2">
            <a href="<?php echo BASE_URL; ?>materiel/add?client_id=<?php echo $client['id']; ?>&return_to=view"
              class="btn btn-sm btn-custom-add">
              <i class="bi bi-plus me-1"></i> Ajouter du matériel
            </a>
          </div>
        <?php endif; ?>
      </div>
      <div class="col">
        <div class="card tab-card" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" role="tab"
          aria-controls="info" aria-selected="false" style="cursor: pointer; border: 2px solid transparent;">
          <div class="card-body text-center p-2">
            <div class="mb-1">
              <i class="<?php echo getIcon('info', 'bi bi-info-circle'); ?> fs-3 text-primary"></i>
            </div>
            <h6 class="card-title mb-1 fs-6">Informations</h6>
            <small class="text-muted d-block">Détails du client</small>
          </div>
        </div>
      </div>
      <div class="col">
        <div class="card tab-card" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" role="tab"
          aria-controls="contacts" aria-selected="false" style="cursor: pointer; border: 2px solid transparent;">
          <div class="card-body text-center p-2">
            <div class="mb-1">
              <i class="<?php echo getIcon('contact', 'bi bi-person-lines-fill'); ?> fs-3 text-success"></i>
            </div>
            <h6 class="card-title mb-1 fs-6">Contacts</h6>
            <small class="text-muted d-block"><?php echo count($contacts); ?> contact(s)</small>
          </div>
        </div>
        <?php if ($canModifyClient): ?>
          <div class="text-center mt-2">
            <a href="<?php echo BASE_URL; ?>contacts/add/<?php echo $client['id']; ?>?return_to=view"
              class="btn btn-sm btn-custom-add">
              <i class="bi bi-plus me-1"></i> Ajouter un contact
            </a>
          </div>
        <?php endif; ?>
      </div>
      <div class="col">
        <div class="card tab-card" id="sites-tab" data-bs-toggle="tab" data-bs-target="#sites" role="tab"
          aria-controls="sites" aria-selected="false" style="cursor: pointer; border: 2px solid transparent;">
          <div class="card-body text-center p-2">
            <div class="mb-1">
              <i class="<?php echo getIcon('site', 'bi bi-building'); ?> fs-3 text-warning"></i>
            </div>
            <h6 class="card-title mb-1 fs-6">Sites</h6>
            <small class="text-muted d-block"><?php echo $stats['site_count'] ?? 0; ?> site(s) •
              <?php echo $stats['building_count'] ?? 0; ?> bâtiment(s)
            </small>
            <?php echo $stats['room_count'] ?? 0; ?> salle(s)</small>
          </div>
        </div>
        <?php if ($canModifyClient): ?>
          <div class="text-center mt-2 d-flex gap-2 justify-content-center">
            <a href="<?php echo BASE_URL; ?>site/add/<?php echo $client['id']; ?>?return_to=view"
              class="btn btn-sm btn-custom-add">
              <i class="bi bi-plus me-1"></i> Ajouter un site
            </a>
            <a href="<?php echo BASE_URL; ?>building/add/0?client_id=<?php echo $client['id']; ?>&return_to=view"
              class="btn btn-sm btn-custom-add">
              <i class="bi bi-plus me-1"></i>Ajouter un bâtiment
            </a>
            <?php

            // Déterminer l'URL pour ajouter une salle
            $roomAddUrl = '';
            if (!empty($sites)) {
              // Toujours passer le client_id pour afficher la liste déroulante des sites
              // Cela permet de choisir le site même s'il n'y en a qu'un seul
              $roomAddUrl = BASE_URL . 'room/add/0?client_id=' . $client['id'] . '&return_to=view';
            } else {
              // Si aucun site, rediriger vers la page d'édition pour créer d'abord un site
              $roomAddUrl = BASE_URL . 'clients/edit/' . $client['id'] . '?active_tab=sites-tab#sites';
            }
            ?>
            <a href="<?php echo $roomAddUrl; ?>" class="btn btn-sm btn-custom-add">
              <i class="bi bi-plus me-1"></i> Ajouter une salle
            </a>
          </div>
        <?php endif; ?>
      </div>
      <div class="col">
        <div class="card tab-card" id="contracts-tab" data-bs-toggle="tab" data-bs-target="#contracts" role="tab"
          aria-controls="contracts" aria-selected="false" style="cursor: pointer; border: 2px solid transparent;">
          <div class="card-body text-center p-2">
            <div class="mb-1">
              <i class="<?php echo getIcon('contract', 'bi bi-file-earmark-text'); ?> fs-3 text-info"></i>
            </div>
            <h6 class="card-title mb-1 fs-6">Contrats</h6>
            <small class="text-muted d-block">
              <?php
              $contractCount = $stats['contract_count'] ?? 0;
              echo $contractCount . ' contrat' . ($contractCount > 1 ? 's' : '') . ' actif' . ($contractCount > 1 ? 's' : '');
              ?>
              <?php
              // Calculer la somme des tickets restants pour les contrats avec tickets
              $totalTicketsRemaining = 0;
              $contractsWithTickets = 0;
              if (!empty($contracts)) {
                foreach ($contracts as $contract) {
                  if (isContractTicketById($contract['id'])) {
                    $totalTicketsRemaining += ($contract['tickets_remaining'] ?? 0);
                    $contractsWithTickets++;
                  }
                }
              }
              if ($contractsWithTickets > 0) {
                echo ' • ' . $totalTicketsRemaining . ' ticket(s)';
              }
              ?>
            </small>
          </div>
        </div>
        <?php if (canManageContracts()): ?>
          <div class="text-center mt-2">
            <a href="<?php echo BASE_URL; ?>contracts/add/<?php echo $client['id']; ?>?return_to=view"
              class="btn btn-sm btn-custom-add">
              <i class="bi bi-plus me-1"></i> Ajouter un contrat
            </a>
          </div>
        <?php endif; ?>
      </div>
      <div class="col">
        <div class="card tab-card" id="interventions-tab" data-bs-toggle="tab" data-bs-target="#interventions" role="tab"
          aria-controls="interventions" aria-selected="false" style="cursor: pointer; border: 2px solid transparent;">
          <div class="card-body text-center p-2">
            <div class="mb-1">
              <i class="<?php echo getIcon('intervention', 'bi bi-tools'); ?> fs-3 text-danger"></i>
            </div>
            <h6 class="card-title mb-1 fs-6">Interventions</h6>
            <small class="text-muted d-block">
              <?php
              $preventiveCount = 0;
              $correctiveCount = 0;
              if (!empty($interventionsGrouped)) {
                foreach ($interventionsGrouped as $contractGroup) {
                  $preventiveCount += count($contractGroup['preventive']);
                  $correctiveCount += count($contractGroup['corrective']);
                }
              }
              if ($preventiveCount > 0 || $correctiveCount > 0) {
                echo $preventiveCount . ' préventive(s) • ' . $correctiveCount . ' corrective(s)';
              } else {
                echo 'Aucune intervention';
              }
              ?>
            </small>
          </div>
        </div>
        <?php if (canModifyInterventions()): ?>
          <div class="text-center mt-2">
            <a href="<?php echo BASE_URL; ?>interventions/add?client_id=<?php echo $client['id']; ?>&return_to=view"
              class="btn btn-sm btn-custom-add">
              <i class="bi bi-plus me-1"></i> Ajouter une intervention
            </a>
          </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="tab-content" id="clientTabsContent">
      <div class="tab-pane fade " id="info" role="tabpanel" aria-labelledby="info-tab">
        <div class="card">
          <div class="card-header py-2">
            <h5 class="card-title mb-0">Informations générales</h5>
          </div>
          <div class="card-body py-2">
            <div class="row">
              <div class="col-md-6">
                <table class="table table-bordered">
                  <tr>
                    <th style="width: 30%">Nom</th>
                    <td>
                      <?php echo htmlspecialchars($client['name'] ?? ''); ?>
                  </tr>
                  <tr>
                    <th>Ville</th>
                    <td>
                      <?php echo htmlspecialchars($client['city'] ?? ''); ?>
                  </tr>
                  <tr>
                    <th>Adresse</th>
                    <td>
                      <?php echo htmlspecialchars($client['address'] ?? ''); ?>
                  </tr>
                  <tr>
                    <th>Code Postal</th>
                    <td>
                      <?php echo htmlspecialchars($client['postal_code'] ?? ''); ?>
                  </tr>
                </table>
              </div>
              <div class="col-md-6">
                <table class="table table-bordered">
                  <tr>
                    <th style="width: 30%">Email</th>
                    <td>
                      <?php echo htmlspecialchars($client['email'] ?? ''); ?>
                  </tr>
                  <tr>
                    <th>Téléphone</th>
                    <td>
                      <?php echo htmlspecialchars($client['phone'] ?? ''); ?>
                  </tr>
                  <tr>
                    <th>Site Web</th>
                    <td>
                      <?php echo htmlspecialchars($client['website'] ?? ''); ?>
                  </tr>
                </table>
              </div>
            </div>
            <div class="row mt-3">
              <div class="col-12">
                <div class="card">
                  <div class="card-header py-2">
                    <h5 class="card-title mb-0">Commentaire</h5>
                  </div>
                  <div class="card-body py-2">
                    <p class="card-text">
                      <?php echo nl2br(htmlspecialchars($client['comment'] ?? '')); ?>
                    </p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Onglet Contacts -->
      <div class="tab-pane fade" id="contacts" role="tabpanel" aria-labelledby="contacts-tab">
        <div class="card">
          <div class="card-header py-2">
            <h5 class="card-title mb-0">Contacts</h5>
          </div>
          <div class="card-body py-2">
            <?php if (!empty($contacts)): ?>
              <div class="table-responsive">
                <table class="table table-striped" id="contactsTable">
                  <thead>
                    <tr>
                      <th class="sortable" data-sort="first_name">Prénom <i class="bi bi-arrow-down-up sort-icon"></i>
                      </th>
                      <th class="sortable" data-sort="last_name">Nom <i class="bi bi-arrow-down-up sort-icon"></i></th>
                      <th class="sortable" data-sort="fonction">Fonction <i class="bi bi-arrow-down-up sort-icon"></i>
                      </th>
                      <th class="sortable" data-sort="phone1">Téléphone fixe <i class="bi bi-arrow-down-up sort-icon"></i>
                      </th>
                      <th class="sortable" data-sort="phone2">Mobile <i class="bi bi-arrow-down-up sort-icon"></i></th>
                      <th class="sortable" data-sort="email">Email <i class="bi bi-arrow-down-up sort-icon"></i></th>
                      <th class="sortable" data-sort="has_user_account">Compte utilisateur <i
                          class="bi bi-arrow-down-up sort-icon"></i></th>
                      <th class="sortable" data-sort="is_vip">VIP <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($contacts as $contact): ?>
                      <tr>
                        <td data-label="Prénom"
                          data-sort-value="<?php echo htmlspecialchars(strtolower($contact['first_name'] ?? '')); ?>">
                          <?php echo htmlspecialchars($contact['first_name'] ?? ''); ?>
                        </td>
                        <td data-label="Nom"
                          data-sort-value="<?php echo htmlspecialchars(strtolower($contact['last_name'] ?? '')); ?>">
                          <?php echo htmlspecialchars($contact['last_name'] ?? ''); ?>
                        </td>
                        <td data-label="Fonction"
                          data-sort-value="<?php echo htmlspecialchars(strtolower($contact['fonction'] ?? '')); ?>">
                          <?php echo htmlspecialchars($contact['fonction'] ?? ''); ?>
                        </td>
                        <td data-label="Téléphone fixe"
                          data-sort-value="<?php echo htmlspecialchars(strtolower($contact['phone1'] ?? '')); ?>">
                          <?php echo htmlspecialchars($contact['phone1'] ?? ''); ?>
                        </td>
                        <td data-label="Mobile"
                          data-sort-value="<?php echo htmlspecialchars(strtolower($contact['phone2'] ?? '')); ?>">
                          <?php echo htmlspecialchars($contact['phone2'] ?? ''); ?>
                        </td>
                        <td data-label="Email"
                          data-sort-value="<?php echo htmlspecialchars(strtolower($contact['email'] ?? '')); ?>">
                          <?php echo htmlspecialchars($contact['email'] ?? ''); ?>
                        </td>
                        <td data-label="Compte utilisateur"
                          data-sort-value="<?php echo $contact['has_user_account'] ? '1' : '0'; ?>">
                          <?php if ($contact['has_user_account']): ?>
                            <span class="badge bg-success">Oui</span>
                            <?php if ($contact['user_username']): ?>
                              <br><small>
                                <?php echo h($contact['user_username']); ?>
                              </small>
                            <?php endif; ?>
                          <?php else: ?>
                            <span class="badge bg-secondary">Non</span>
                          <?php endif; ?>
                        </td>
                        <td data-label="VIP" data-sort-value="<?php echo !empty($contact['is_vip']) ? '1' : '0'; ?>">
                          <?php if (!empty($contact['is_vip'])): ?>
                            <i class="bi bi-check-circle-fill text-success" title="Contact VIP"></i>
                          <?php endif; ?>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted">Aucun contact enregistré pour ce client.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <div class="tab-pane fade" id="sites" role="tabpanel" aria-labelledby="sites-tab">
        <div class="card">
          <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Sites</h5>
            <?php if (!empty($sites)): ?>
              <div class="btn-group" role="group" aria-label="Contrôles accordéon">
                <button type="button" class="btn btn-sm btn-outline-primary" id="expandAllSites" title="Déplier tout">
                  <i class="bi bi-arrows-expand me-1"></i> Déplier tout
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="collapseAllSites" title="Replier tout">
                  <i class="bi bi-arrows-collapse me-1"></i> Replier tout
                </button>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-body py-2">
            <?php if (!empty($sites)): ?>
              <div class="accordion" id="sitesAccordion">
                <?php foreach ($sites as $siteIndex => $site): ?>
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="siteHeading<?php echo $site['id']; ?>">
                      <div class="d-flex justify-content-between align-items-center w-100">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                          data-bs-target="#siteCollapse<?php echo $site['id']; ?>" aria-expanded="false"
                          aria-controls="siteCollapse<?php echo $site['id']; ?>">
                          <?php echo h($site['name']); ?>
                          <span class="badge bg-secondary ms-2">
                            <?php echo count($site['buildings'] ?? []); ?> bâtiment(s)
                          </span>
                          <span class="badge bg-info ms-2">
                            <?php echo $site['rooms_count'] ?? 0; ?> salle(s)
                          </span>
                        </button>
                        <div class="me-3">
                          <a href="<?php echo BASE_URL; ?>qrcode/generate/site/<?php echo $site['id']; ?>"
                            class="btn btn-sm btn-outline-primary" title="Générer les QR codes des salles">
                            <i class="bi bi-qr-code me-1"></i> QR Codes
                          </a>
                        </div>
                      </div>
                    </h2>
                    <div id="siteCollapse<?php echo $site['id']; ?>" class="accordion-collapse collapse"
                      aria-labelledby="siteHeading<?php echo $site['id']; ?>" data-bs-parent="#sitesAccordion">
                      <div class="accordion-body">
                        <!-- Contenu du site existant -->
                        <div class="row">
                          <div class="col-md-6">
                            <table class="table table-bordered">
                              <tr>
                                <th>Adresse</th>
                                <td>
                                  <?php echo htmlspecialchars($site['address'] ?? ''); ?>
                                </td>
                              </tr>
                              <tr>
                                <th>Code Postal</th>
                                <td>
                                  <?php echo htmlspecialchars($site['postal_code'] ?? ''); ?>
                                </td>
                              </tr>
                              <tr>
                                <th>Ville</th>
                                <td>
                                  <?php echo htmlspecialchars($site['city'] ?? ''); ?>
                                </td>
                              </tr>
                              <tr>
                                <th>Téléphone</th>
                                <td>
                                  <?php echo htmlspecialchars($site['phone'] ?? ''); ?>
                                </td>
                              </tr>
                              <tr>
                                <th>Email</th>
                                <td>
                                  <?php echo htmlspecialchars($site['email'] ?? ''); ?>
                                </td>
                              </tr>
                            </table>
                          </div>
                          <div class="col-md-6">
                            <div class="card">
                              <div class="card-header py-2">
                                <h6 class="card-title mb-0">Commentaire</h6>
                              </div>
                              <div class="card-body py-2">
                                <p class="card-text">
                                  <?php echo nl2br(htmlspecialchars($site['comment'] ?? '')); ?>
                                </p>
                              </div>
                            </div>
                          </div>
                        </div>

                        <!-- Bâtiments et Salles -->
                        <div class="mt-4">
                          <h6 class="fw-bold mb-3"><i class="bi bi-building text-warning me-2"></i>Bâtiments et Salles</h6>
                          <?php if (!empty($site['buildings'])): ?>
                            <?php foreach ($site['buildings'] as $building): ?>
                              <div class="card mb-3">
                                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                  <strong><i class="bi bi-building text-warning me-2"></i>
                                    <?php echo h($building['name']); ?>
                                  </strong>
                                  <span class="badge bg-info">
                                    <?php echo count($building['rooms'] ?? []); ?> salle(s)
                                  </span>
                                </div>
                                <div class="card-body py-2">
                                  <?php if (!empty($building['rooms'])): ?>
                                    <div class="table-responsive">
                                      <table class="table table-striped table-bordered">
                                        <thead>
                                          <tr>
                                            <th>Nom</th>
                                            <th>Contact principal</th>
                                            <th>Statut</th>
                                            <th>QR Code édité</th>
                                            <th>Commentaire</th>
                                          </tr>
                                        </thead>
                                        <tbody>
                                          <?php foreach ($building['rooms'] as $room): ?>
                                            <tr>
                                              <td>
                                                <?php echo h($room['name']); ?>
                                              </td>
                                              <td>
                                                <?php echo (!empty($room['first_name']) && !empty($room['last_name'])) ? htmlspecialchars($room['first_name'] . ' ' . $room['last_name']) : '<span class="text-muted">Aucun contact</span>'; ?>
                                              </td>
                                              <td><span
                                                  class="badge bg-<?php echo ($room['status'] ?? 0) == 1 ? 'success' : 'danger'; ?>">
                                                  <?php echo ($room['status'] ?? 0) == 1 ? 'Actif' : 'Inactif'; ?>
                                                </span>
                                              </td>
                                              <td class="text-center">
                                                <div class="form-check form-switch d-flex justify-content-center">
                                                  <input class="form-check-input qr-code-toggle" type="checkbox"
                                                    data-room-id="<?php echo $room['id']; ?>" <?php echo !empty($room['qr_code_edited']) ? 'checked' : ''; ?>
                                                  <?php echo !$canModifyClient ? 'disabled title="Vous n\'avez pas les droits pour modifier ce champ"' : ''; ?>>
                                                </div>
                                              </td>
                                              <td>
                                                <?php echo !empty($room['comment']) ? h($room['comment']) : '<span class="text-muted">Aucun commentaire</span>'; ?>
                                              </td>
                                            </tr>
                                          <?php endforeach; ?>
                                        </tbody>
                                      </table>
                                    </div>
                                  <?php else: ?>
                                    <div class="alert alert-info mb-0"><i class="bi bi-info-circle me-2"></i> Aucune salle dans ce
                                      bâtiment.</div>
                                  <?php endif; ?>
                                </div>
                              </div>
                            <?php endforeach; ?>
                          <?php else: ?>
                            <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i> Aucun
                              bâtiment pour ce site.</div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <p class="text-muted">Aucun site enregistré pour ce client.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Onglet Contrats -->
      <div class="tab-pane fade" id="contracts" role="tabpanel" aria-labelledby="contracts-tab">
        <div class="card">
          <div class="card-header py-2">
            <h5 class="card-title mb-0">Contrats</h5>
          </div>
          <div class="card-body py-2">
            <?php if (!empty($contracts)): ?>
              <div class="table-responsive">
                <table class="table table-striped table-hover" id="contractsTable">
                  <thead>
                    <tr>
                      <th class="sortable" data-sort="name">Nom <i class="bi bi-arrow-down-up sort-icon"></i></th>
                      <th class="sortable" data-sort="type">Type de contrat <i class="bi bi-arrow-down-up sort-icon"></i>
                      </th>
                      <th class="sortable" data-sort="end_date">Date de fin <i class="bi bi-arrow-down-up sort-icon"></i>
                      </th>
                      <th class="sortable" data-sort="tickets_number">Tickets initiaux <i
                          class="bi bi-arrow-down-up sort-icon"></i></th>
                      <th class="sortable" data-sort="tickets_remaining">Tickets restants <i
                          class="bi bi-arrow-down-up sort-icon"></i></th>
                      <th class="sortable" data-sort="status">Statut <i class="bi bi-arrow-down-up sort-icon"></i></th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($contracts as $contract): ?>
                      <tr>
                        <td data-label="Nom"
                          data-sort-value="<?php echo htmlspecialchars(strtolower($contract['name'] ?? '')); ?>">
                          <a href="<?php echo BASE_URL; ?>contracts/view/<?php echo $contract['id']; ?>?return_to=client&client_id=<?php echo $client['id']; ?>&active_tab=contracts-tab"
                            class="text-decoration-none fw-bold">
                            <?php echo htmlspecialchars($contract['name'] ?? '-'); ?>
                          </a>
                        </td>
                        <td data-label="Type de contrat"
                          data-sort-value="<?php echo htmlspecialchars(strtolower($contract['contract_type_name'] ?? '')); ?>">
                          <?php echo htmlspecialchars($contract['contract_type_name'] ?? '-'); ?>
                        </td>
                        <td data-label="Date de fin" data-sort-value="<?php echo strtotime($contract['end_date']); ?>">
                          <?php echo formatDateFrench($contract['end_date']); ?>
                        </td>
                        <td data-label="Tickets initiaux" data-sort-value="<?php echo $contract['tickets_number']; ?>">
                          <?php if (isContractTicketById($contract['id'])): ?>
                            <span class="badge bg-info">
                              <?php echo $contract['tickets_number']; ?>
                            </span>
                          <?php else: ?>
                            <span class="text-muted">--</span>
                          <?php endif; ?>
                        </td>
                        <td data-label="Tickets restants" data-sort-value="<?php echo $contract['tickets_remaining']; ?>">
                          <?php if (isContractTicketById($contract['id'])): ?>
                            <span class="badge bg-<?php echo $contract['tickets_remaining'] > 3 ? 'success' : 'danger'; ?>">
                              <?php echo $contract['tickets_remaining']; ?>
                            </span>
                          <?php else: ?>
                            <span class="text-muted">--</span>
                          <?php endif; ?>
                        </td>
                        <td data-label="Statut"
                          data-sort-value="<?php echo htmlspecialchars(strtolower($contract['status'])); ?>">
                          <span
                            class="badge bg-<?php echo $contract['status'] === 'actif' ? 'success' : ($contract['status'] === 'inactif' ? 'danger' : ($contract['status'] === 'en_attente' ? 'warning' : 'secondary')); ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $contract['status'])); ?>
                          </span>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <p class="text-muted">Aucun contrat enregistré pour ce client.</p>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Onglet Interventions -->
      <div class="tab-pane fade" id="interventions" role="tabpanel" aria-labelledby="interventions-tab">
        <div class="card">
          <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Interventions</h5>
            <?php if (!empty($interventionsGrouped)): ?>
              <div class="btn-group" role="group" aria-label="Contrôles accordéon">
                <button type="button" class="btn btn-sm btn-outline-primary" id="expandAllInterventions"
                  title="Déplier tout">
                  <i class="bi bi-arrows-expand me-1"></i> Déplier tout
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="collapseAllInterventions"
                  title="Replier tout">
                  <i class="bi bi-arrows-collapse me-1"></i> Replier tout
                </button>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-body py-2">
            <?php if (!empty($interventionsGrouped)): ?>
              <?php
              // Tableau de correspondance des statuts
              $statusMap = [
                1 => ['class' => 'info', 'label' => 'Nouveau'],
                2 => ['class' => 'warning', 'label' => 'En cours'],
                3 => ['class' => 'primary', 'label' => 'En attente client'],
                4 => ['class' => 'success', 'label' => 'Résolu'],
                5 => ['class' => 'danger', 'label' => 'Annulée'],
                6 => ['class' => 'secondary', 'label' => 'Fermée'],
              ];
              ?>
              <div class="accordion" id="interventionsAccordion">
                <?php foreach ($interventionsGrouped as $contractId => $contractGroup): ?>
                  <div class="accordion-item">
                    <h2 class="accordion-header" id="contractHeading<?php echo $contractId; ?>">
                      <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                        data-bs-target="#contractCollapse<?php echo $contractId; ?>" aria-expanded="false"
                        aria-controls="contractCollapse<?php echo $contractId; ?>">
                        <div class="d-flex justify-content-between align-items-center w-100 me-3">
                          <span>
                            <?php echo h($contractGroup['contract_name'] ?? 'Contrat sans nom'); ?>
                          </span>
                          <div class="d-flex gap-2">
                            <?php
                            $preventiveCount = isset($contractGroup['preventive']) ? count($contractGroup['preventive']) : 0;
                            $correctiveCount = isset($contractGroup['corrective']) ? count($contractGroup['corrective']) : 0;
                            ?>
                            <?php if ($preventiveCount > 0): ?>
                              <span class="badge bg-success">
                                <?php echo $preventiveCount; ?> préventive(s)
                              </span>
                            <?php endif; ?>
                            <?php if ($correctiveCount > 0): ?>
                              <span class="badge bg-warning">
                                <?php echo $correctiveCount; ?> Correctives(s)
                              </span>
                            <?php endif; ?>
                            <?php if ($preventiveCount === 0 && $correctiveCount === 0): ?>
                              <span class="badge bg-secondary">Aucune intervention</span>
                            <?php endif; ?>
                          </div>
                        </div>
                      </button>
                    </h2>
                    <div id="contractCollapse<?php echo $contractId; ?>" class="accordion-collapse collapse"
                      aria-labelledby="contractHeading<?php echo $contractId; ?>" data-bs-parent="#interventionsAccordion">
                      <div class="accordion-body">
                        <!-- Interventions préventives -->
                        <?php
                        $preventives = isset($contractGroup['preventive']) ? $contractGroup['preventive'] : [];
                        if (!empty($preventives)):
                          ?>
                          <h6 class="fw-bold mb-3"><i class="bi bi-check-circle-fill text-success me-2"></i>Préventives</h6>
                          <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                              <thead>
                                <tr>
                                  <th>DATE</th>
                                  <th>REFFERENCE</th>
                                  <th>TITRE</th>
                                  <th>SITE</th>
                                  <th>SALLE</th>
                                  <th>STATUT</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php foreach ($preventives as $intervention): ?>
                                  <tr>
                                    <td>
                                      <?= !empty($intervention['created_at'])
                                        ? date('d/m/Y', strtotime($intervention['created_at']))
                                        : '-' ?>
                                    </td>
                                    <td>
                                      <a
                                        href="<?= BASE_URL ?>interventions/view/<?= $intervention['id'] ?>?return_to=client&client_id=<?= $client['id'] ?>&active_tab=interventions-tab">
                                        <?= htmlspecialchars($intervention['reference'] ?? '-') ?>
                                      </a>
                                    </td>
                                    <?php echo h($intervention['title'] ?? '-'); ?>
                                    <td>
                                      <?= htmlspecialchars($intervention['site_name'] ?? '-') ?>
                                    </td>
                                    <td>
                                      <?= htmlspecialchars($intervention['room_name'] ?? '-') ?>
                                    </td>
                                    <td>
                                      <?php
                                      $statusId = $intervention['status_id'] ?? 0;
                                      $statusInfo = $statusMap[$statusId] ?? ['class' => 'secondary', 'label' => $intervention['status'] ?? 'Inconnu'];
                                      ?>
                                      <span class="badge bg-<?php echo $statusInfo['class']; ?>">
                                        <?php echo htmlspecialchars($statusInfo['label']); ?>
                                      </span>
                                    </td>
                                  </tr>
                                <?php endforeach; ?>
                              </tbody>
                            </table>
                          </div>
                        <?php endif; ?>
                        <!-- Interventions correctives -->
                        <?php
                        $correctives = isset($contractGroup['corrective']) ? $contractGroup['corrective'] : [];
                        if (!empty($correctives)):
                          ?>
                          <h6 class="fw-bold mb-3 mt-4">Correctives</h6>
                          <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                              <thead>
                                <tr>
                                  <th>DATE</th>
                                  <th>REFFERENCE</th>
                                  <th>TITRE</th>
                                  <th>SITE</th>
                                  <th>SALLE</th>
                                  <th>STATUT</th>
                                </tr>
                              </thead>
                              <tbody>
                                <?php foreach ($correctives as $intervention): ?>
                                  <tr>
                                    <td>
                                      <?= !empty($intervention['created_at'])
                                        ? date('d/m/Y', strtotime($intervention['created_at']))
                                        : '-' ?>
                                    </td>
                                    <td>
                                      <a
                                        href="<?= BASE_URL ?>interventions/view/<?= $intervention['id'] ?>?return_to=client&client_id=<?= $client['id'] ?>&active_tab=interventions-tab">
                                        <?= htmlspecialchars($intervention['reference'] ?? '-') ?>
                                      </a>
                                    </td>
                                    <td><?php echo h($intervention['title'] ?? '-'); ?>
                                    <td>
                                      <?= htmlspecialchars($intervention['site_name'] ?? '-') ?>
                                    </td>
                                    <td>
                                      <?= htmlspecialchars($intervention['room_name'] ?? '-') ?>
                                    </td>
                                    <td>
                                      <?php
                                      $statusId = $intervention['status_id'] ?? 0;
                                      $statusInfo = $statusMap[$statusId] ?? ['class' => 'secondary', 'label' => $intervention['status'] ?? 'Inconnu'];
                                      ?>
                                      <span class="badge bg-<?php echo $statusInfo['class']; ?>">
                                        <?php echo htmlspecialchars($statusInfo['label']); ?>
                                      </span>
                                    </td>
                                  </tr>
                                <?php endforeach; ?>
                              </tbody>
                            </table>
                          </div>
                        <?php endif; ?>

                        <!-- Message si aucune intervention dans ce contrat -->
                        <?php if (empty($preventives) && empty($correctives)): ?>
                          <div class="alert alert-info mb-0">
                            <i class="bi bi-info-circle me-2"></i> Aucune intervention pour ce contrat.
                          </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            <?php else: ?>
              <div class="alert alert-info"><i class="bi bi-info-circle me-2"></i> Aucune intervention enregistrée
                pour ce client.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <!-- Onglet Matériel -->
      <div class="tab-pane fade show active" id="materiel" role="tabpanel" aria-labelledby="materiel-tab">
        <div class="card">
          <div class="card-header py-2 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0">Liste du matériel</h5>
            <?php if (!empty($materielList)): ?>
              <div class="d-flex align-items-center gap-2">
                <label for="materielPerPage" class="mb-0 small text-muted">Afficher</label>
                <select class="form-select form-select-sm" id="materielPerPage" style="width: auto;">
                  <option value="10" selected>10</option>
                  <option value="25">25</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
                </select>
              </div>
            <?php endif; ?>
          </div>
          <div class="card-body py-2">
            <?php if (!empty($materielList)): ?>
              <div class="table-responsive">
                <table class="table table-stripe table-hover" id="materielTable">
                  <thead>
                    <tr>
                      <!-- <th>Site</th>
                                            <th>Bâtiment</th> -->
                      <th class="sortable" data-sort="salle_nom">Salle <i class="bi bi-arrow-down-up sort-icon"></i></th>
                      <th class="sortable" data-sort="marque">Marque <i class="bi bi-arrow-down-up sort-icon"></i></th>
                      <th class="sortable" data-sort="modele">Modèle <i class="bi bi-arrow-down-up sort-icon"></i></th>
                      <th class="sortable" data-sort="type_materiel">Type <i class="bi bi-arrow-down-up sort-icon"></i></th>
                      <th class="sortable" data-sort="numero_serie">S/N <i class="bi bi-arrow-down-up sort-icon"></i></th>
                      <th class="sortable" data-sort="adresse_ip">IP <i class="bi bi-arrow-down-up sort-icon"></i></th>
                      <th class="sortable" data-sort="adresse_mac">MAC <i class="bi bi-arrow-down-up sort-icon"></i></th>
                      <!-- <th>Actions</th> -->
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($materielList as $item): ?>
                      <tr>
                        <!-- <td>
                                                    <?php echo htmlspecialchars($item['site_nom'] ?? '-'); ?>
                                                </td>
                                                <td>
                                                    <?php echo htmlspecialchars($item['building_nom'] ?? '-'); ?>
                                                </td> -->
                        <td data-sort-value="<?php echo htmlspecialchars(strtolower($item['salle_nom'] ?? '')); ?>">
                          <?php echo htmlspecialchars($item['salle_nom'] ?? '-'); ?>
                        </td>
                        <td data-sort-value="<?php echo htmlspecialchars(strtolower($item['marque'] ?? '')); ?>">
                          <a href="<?php echo BASE_URL; ?>materiel/view/<?php echo $item['id']; ?>?return_to=client&client_id=<?php echo $client['id']; ?>"
                            class="text-decoration-none fw-bold">
                            <?php echo htmlspecialchars($item['marque'] ?? '-'); ?>
                          </a>
                        </td>
                        <td data-sort-value="<?php echo htmlspecialchars(strtolower($item['modele'] ?? '')); ?>">
                          <?php echo htmlspecialchars($item['modele'] ?? '-'); ?>
                        </td>
                        <td data-sort-value="<?php echo htmlspecialchars(strtolower($item['type_materiel'] ?? '')); ?>">
                          <?php echo htmlspecialchars($item['type_materiel'] ?? '-'); ?>
                        </td>
                        <td data-sort-value="<?php echo htmlspecialchars(strtolower($item['numero_serie'] ?? '')); ?>">
                          <?php echo htmlspecialchars($item['numero_serie'] ?? '-'); ?>
                        </td>
                        <td data-sort-value="<?php echo htmlspecialchars(strtolower($item['adresse_ip'] ?? '')); ?>">
                          <?php echo htmlspecialchars($item['adresse_ip'] ?? '-'); ?>
                        </td>
                        <td data-sort-value="<?php echo htmlspecialchars(strtolower($item['adresse_mac'] ?? '')); ?>">
                          <?php echo htmlspecialchars($item['adresse_mac'] ?? '-'); ?>
                        </td>
                      <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
              <div class="d-flex justify-content-end mt-3">
                <nav aria-label="Pagination matériel">
                  <ul class="pagination pagination-sm mb-0" id="materielPagination"></ul>
                </nav>
              </div>
            <?php else: ?>
              <div class="alert alert-info">
                <i class="bi bi-info-circle me-2"></i> Aucun matériel enregistré pour ce client.
                <?php if ($canModifyClient): ?>
                  <a href="<?php echo BASE_URL; ?>materiel/add?client_id=<?php echo $client['id']; ?>&return_to=view"
                    class="alert-link">Ajouter du matériel</a>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  <?php else: ?>
    <div class="alert alert-danger">
      Client introuvable.
    </div>
  <?php endif; ?>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

<style>
  .sortable {
    cursor: pointer;
    user-select: none;
    position: relative;
  }

  .sortable:hover {
    background-color: rgba(0, 0, 0, 0.05);
  }

  .sort-icon {
    font-size: 0.8em;
    margin-left: 5px;
    opacity: 0.5;
  }

  .sortable.sort-asc .sort-icon::before {
    content: "\F12C";
    /* bi-arrow-up */
    opacity: 1;
  }

  .sortable.sort-desc .sort-icon::before {
    content: "\F12F";
    /* bi-arrow-down */
    opacity: 1;
  }

  .sortable.sort-asc,
  .sortable.sort-desc {
    background-color: rgba(0, 123, 255, 0.1);
  }

  /* Styles pour les cards d'onglets */
  .tab-card {
    transition: all 0.3s ease;
    border: 2px solid transparent !important;
  }

  .tab-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    border-color: #dee2e6 !important;
  }

  .tab-card.active {
    border-color: #007bff !important;
    box-shadow: 0 4px 12px rgba(0, 123, 255, 0.15);
    background-color: rgba(0, 123, 255, 0.05);
  }

  .tab-card.active:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0, 123, 255, 0.2);
  }

  .filter-row th {
    padding: 4px 6px;
    background-color: #fff;
  }

  .filter-row input {
    font-weight: normal;
  }
</style>

<script>
  function confirmDelete(contractId, contractName) {
    if (confirm('Êtes-vous sûr de vouloir supprimer le contrat "' + contractName + '" ?')) {
      window.location.href = '<?php echo BASE_URL; ?>contracts/delete/' + contractId;
    }
  }
</script>

<script>
  initBaseUrl('<?php echo BASE_URL; ?>');

  console.log('Client:', <?php echo json_encode($client); ?>);
  console.log('Sites:', <?php echo json_encode($sites); ?>);
  console.log('Stats:', <?php echo json_encode($stats); ?>);
  console.log('Interventions Grouped:', <?php echo json_encode($interventionsGrouped); ?>);

  function loadRoomsForSite(siteId, callback) {
    if (typeof loadRooms === 'function') {
      loadRooms(siteId, null, null, callback);
    }
  }
  document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.qr-code-toggle').forEach(function (checkbox) {
  checkbox.addEventListener('change', function () {
  const roomId = this.dataset.roomId;
  const edited = this.checked;
  const originalState = !edited;
  const csrfToken = '<?= csrf_token() ?>'; // grab it once as a JS string

  this.disabled = true;

  fetch(BASE_URL + 'room/toggle-qr/' + roomId, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': csrfToken
    },
    body: JSON.stringify({ edited: edited, csrf_token: csrfToken })
  })
    .then(response => response.json())
    .then(data => {
      if (!data.success) {
        alert(data.message || 'Erreur lors de la mise à jour.');
        this.checked = originalState;
      }
    })
    .catch(() => {
      alert('Erreur réseau lors de la mise à jour.');
      this.checked = originalState;
    })
    .finally(() => {
      this.disabled = !<?php echo $canModifyClient ? 'true' : 'false'; ?>;
    });
});
});
    function initSortableTable(tableId) {
      const table = document.getElementById(tableId);
      if (!table) return;

      let currentSortColumn = null;
      let currentSortDirection = 'asc';
      function sortTable(columnIndex, direction) {
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));

        rows.sort((a, b) => {
          const aValue = a.cells[columnIndex].getAttribute('data-sort-value') || a.cells[columnIndex].textContent.trim();
          const bValue = b.cells[columnIndex].getAttribute('data-sort-value') || b.cells[columnIndex].textContent.trim();

          const aNum = parseFloat(aValue);
          const bNum = parseFloat(bValue);

          if (!isNaN(aNum) && !isNaN(bNum)) {
            return direction === 'asc' ? aNum - bNum : bNum - aNum;
          }

          if (aValue.length === 10 && bValue.length === 10) {
            const aDate = parseInt(aValue);
            const bDate = parseInt(bValue);
            if (!isNaN(aDate) && !isNaN(bDate)) {
              return direction === 'asc' ? aDate - bDate : bDate - aDate;
            }
          }

          const aLower = aValue.toLowerCase();
          const bLower = bValue.toLowerCase();

          if (aLower < bLower) return direction === 'asc' ? -1 : 1;
          if (aLower > bLower) return direction === 'asc' ? 1 : -1;
          return 0;
        });

        rows.forEach(row => tbody.appendChild(row));
      }
      table.querySelectorAll('th.sortable').forEach((header, index) => {
        header.addEventListener('click', function () {
          const sortType = this.getAttribute('data-sort');

          table.querySelectorAll('th.sortable').forEach(th => {
            th.classList.remove('sort-asc', 'sort-desc');
          });

          let direction = 'asc';
          if (currentSortColumn === index && currentSortDirection === 'asc') {
            direction = 'desc';
          }

          sortTable(index, direction);

          this.classList.add(direction === 'asc' ? 'sort-asc' : 'sort-desc');
          currentSortColumn = index;
          currentSortDirection = direction;
        });
      });
    }

    initSortableTable('contractsTable');
    initSortableTable('contactsTable');
    initSortableTable('materielTable');

    const materielTableEl = document.getElementById('materielTable');
    if (materielTableEl) {
      materielTableEl.querySelectorAll('th.sortable').forEach(th => {
        th.addEventListener('click', function () {
          if (typeof window.__materielRenderPage === 'function') {
            window.__materielRenderPage();
          }
        });
      });
    }

    const expandAllBtn = document.getElementById('expandAllInterventions');
    const collapseAllBtn = document.getElementById('collapseAllInterventions');

    if (expandAllBtn) {
      expandAllBtn.addEventListener('click', function () {
        const accordion = document.getElementById('interventionsAccordion');
        if (accordion) {
          const collapseElements = accordion.querySelectorAll('.accordion-collapse');
          collapseElements.forEach(collapse => {
            const bsCollapse = new bootstrap.Collapse(collapse, {
              show: true
            });
          });
        }
      });
    }

    if (collapseAllBtn) {
      collapseAllBtn.addEventListener('click', function () {
        const accordion = document.getElementById('interventionsAccordion');
        if (accordion) {
          const collapseElements = accordion.querySelectorAll('.accordion-collapse.show');
          collapseElements.forEach(collapse => {
            const bsCollapse = bootstrap.Collapse.getInstance(collapse);
            if (bsCollapse) {
              bsCollapse.hide();
            } else {
              const newBsCollapse = new bootstrap.Collapse(collapse, {
                show: false
              });
              newBsCollapse.hide();
            }
          });
        }
      });
    }
    const expandAllSitesBtn = document.getElementById('expandAllSites');
    const collapseAllSitesBtn = document.getElementById('collapseAllSites');

    if (expandAllSitesBtn) {
      expandAllSitesBtn.addEventListener('click', function () {
        const accordion = document.getElementById('sitesAccordion');
        if (accordion) {
          const collapseElements = accordion.querySelectorAll('.accordion-collapse');
          collapseElements.forEach(collapse => {
            const bsCollapse = new bootstrap.Collapse(collapse, {
              show: true
            });
          });
        }
      });
    }

    if (collapseAllSitesBtn) {
      collapseAllSitesBtn.addEventListener('click', function () {
        const accordion = document.getElementById('sitesAccordion');
        if (accordion) {
          const collapseElements = accordion.querySelectorAll('.accordion-collapse.show');
          collapseElements.forEach(collapse => {
            const bsCollapse = bootstrap.Collapse.getInstance(collapse);
            if (bsCollapse) {
              bsCollapse.hide();
            } else {
              const newBsCollapse = new bootstrap.Collapse(collapse, {
                show: false
              });
              newBsCollapse.hide();
            }
          });
        }
      });
    }

    const urlParams = new URLSearchParams(window.location.search);
    const activeTabFromUrl = urlParams.get('active_tab');
    const activeTabToUse = activeTabFromUrl || localStorage.getItem('clientActiveTab');
    if (activeTabToUse) {
      const targetCard = document.getElementById(activeTabToUse);
      if (targetCard) {
        document.querySelectorAll('.tab-card').forEach(c => {
          c.classList.remove('active');
          c.style.border = '2px solid transparent';
        });

        targetCard.classList.add('active');
        targetCard.style.border = '2px solid #007bff';
        const targetPane = document.querySelector(targetCard.getAttribute('data-bs-target'));
        if (targetPane) {
          document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.classList.remove('show', 'active');
          });
          targetPane.classList.add('show', 'active');
        }
        localStorage.setItem('clientActiveTab', activeTabToUse);
      }
    }
    document.querySelectorAll('.tab-card').forEach(card => {
      card.addEventListener('click', function () {
        document.querySelectorAll('.tab-card').forEach(c => {
          c.classList.remove('active');
          c.style.border = '2px solid transparent';
        });

        this.classList.add('active');
        this.style.border = '2px solid #007bff';

        const activeTab = this.id;
        localStorage.setItem('clientActiveTab', activeTab);
      });
    });

    const editBtn = document.getElementById('editClientBtn');
    if (editBtn) {
      editBtn.addEventListener('click', function (e) {
        e.preventDefault();
        const activeTab = document.querySelector('.tab-card.active')?.id || 'materiel-tab';
        const baseUrl = this.href;
        const separator = baseUrl.includes('?') ? '&' : '?';
        const newUrl = baseUrl + separator + 'active_tab=' + activeTab;
        window.location.href = newUrl;
      });
    }
  });

  function confirmDelete(clientId, clientName) {
    if (confirm('Êtes-vous sûr de vouloir supprimer le client "' + clientName + '" ?\n\nCette action est irréversible et supprimera définitivement le client et toutes ses données associées (sites, salles, matériel, contrats, etc.).')) {
      window.location.href = '<?php echo BASE_URL; ?>clients/delete/' + clientId;
    }
  }
  (function () {
    const perPageSelect = document.getElementById('materielPerPage');
    const table = document.getElementById('materielTable');
    const paginationContainer = document.getElementById('materielPagination');
    if (!table || !perPageSelect || !paginationContainer) return;

    const tbody = table.querySelector('tbody');
    const filterInputs = table.querySelectorAll('.materiel-filter');
    let currentPage = 1;
    let perPage = parseInt(perPageSelect.value, 10);

    function getAllRows() {
      return Array.from(tbody.querySelectorAll('tr'));
    }

    function getFilteredRows() {
      const filters = Array.from(filterInputs).map(input => ({
        col: parseInt(input.dataset.col, 10),
        value: input.value.trim().toLowerCase()
      })).filter(f => f.value !== '');

      return getAllRows().filter(row => {
        return filters.every(f => {
          const cell = row.cells[f.col];
          if (!cell) return false;
          const cellValue = (cell.getAttribute('data-sort-value') || cell.textContent).toLowerCase();
          return cellValue.includes(f.value);
        });
      });
    }

    function renderPage() {
      const allRows = getAllRows();
      const filteredRows = getFilteredRows();
      const filteredSet = new Set(filteredRows);

      // Cacher toutes les lignes qui ne matchent pas les filtres
      allRows.forEach(row => {
        if (!filteredSet.has(row)) {
          row.style.display = 'none';
        }
      });

      const totalRows = filteredRows.length;
      const totalPages = Math.max(1, Math.ceil(totalRows / perPage));
      if (currentPage > totalPages) currentPage = totalPages;

      const start = (currentPage - 1) * perPage;
      const end = start + perPage;

      filteredRows.forEach((row, index) => {
        row.style.display = (index >= start && index < end) ? '' : 'none';
      });

      renderPagination(totalPages, totalRows);
    }

    function createPageItem(label, page, disabled, active) {
      const li = document.createElement('li');
      li.className = 'page-item' + (disabled ? ' disabled' : '') + (active ? ' active' : '');
      const a = document.createElement('a');
      a.className = 'page-link';
      a.href = '#';
      a.textContent = label;
      a.addEventListener('click', function (e) {
        e.preventDefault();
        if (!disabled && page !== currentPage) {
          currentPage = page;
          renderPage();
        }
      });
      li.appendChild(a);
      return li;
    }

    function createEllipsis() {
      const li = document.createElement('li');
      li.className = 'page-item disabled';
      li.innerHTML = '<span class="page-link">…</span>';
      return li;
    }

    function renderPagination(totalPages, totalRows) {
      paginationContainer.innerHTML = '';
      if (totalRows === 0) {
        const li = document.createElement('li');
        li.className = 'page-item disabled';
        li.innerHTML = '<span class="page-link">Aucun résultat</span>';
        paginationContainer.appendChild(li);
        return;
      }
      if (totalPages <= 1) return;

      paginationContainer.appendChild(createPageItem('«', currentPage - 1, currentPage === 1, false));

      const maxButtons = 5;
      let startPage = Math.max(1, currentPage - Math.floor(maxButtons / 2));
      let endPage = Math.min(totalPages, startPage + maxButtons - 1);
      if (endPage - startPage < maxButtons - 1) {
        startPage = Math.max(1, endPage - maxButtons + 1);
      }

      if (startPage > 1) {
        paginationContainer.appendChild(createPageItem('1', 1, false, false));
        if (startPage > 2) paginationContainer.appendChild(createEllipsis());
      }

      for (let p = startPage; p <= endPage; p++) {
        paginationContainer.appendChild(createPageItem(p, p, false, p === currentPage));
      }

      if (endPage < totalPages) {
        if (endPage < totalPages - 1) paginationContainer.appendChild(createEllipsis());
        paginationContainer.appendChild(createPageItem(totalPages, totalPages, false, false));
      }

      paginationContainer.appendChild(createPageItem('»', currentPage + 1, currentPage === totalPages, false));
    }

    perPageSelect.addEventListener('change', function () {
      perPage = parseInt(this.value, 10);
      currentPage = 1;
      renderPage();
    });

    filterInputs.forEach(input => {
      input.addEventListener('input', function () {
        currentPage = 1;
        renderPage();
      });
      input.addEventListener('click', function (e) {
        e.stopPropagation();
      });
    });
    window.__materielRenderPage = renderPage;
    renderPage();
  })();
</script>
<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../controllers/QRCodeController.php';
/**
 * Vue d'édition d'un client
 * Permet de modifier les informations d'un client
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
  header('Location: ' . BASE_URL . 'auth/login');
  exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Vérifier les permissions de manière sécurisée
$canEditClient = canModifyClients();

// Vérifier si l'utilisateur a les droits pour gérer les contrats
$canManageContracts = $canEditClient;

if (!$canEditClient) {
  $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour modifier ce client.";
  header('Location: ' . BASE_URL . 'clients/view/' . ($client['id'] ?? ''));
  exit;
}

// Récupération des données
$client = $client ?? null;
$sites = $sites ?? [];
$contracts = $contracts ?? [];
$contacts = $contacts ?? [];
$contractTypes = $contractTypes ?? [];
$interventionsGrouped = $interventionsGrouped ?? [];

setPageVariables(
  'Modification du client',
  'clients'
);

// Définir la page courante pour le menu
$currentPage = 'clients';

$qrcodeController = new QRCodeController();
$masterQR = $client ? $qrcodeController->generateQRCodeBase64(
  $qrcodeController->generateMasterQRUrl($client['id']),
  130
) : null;
// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<head>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/intl-tel-input@23/build/css/intlTelInput.css">
  <script src="https://cdn.jsdelivr.net/npm/intl-tel-input@23/build/js/intlTelInputWithUtils.min.js"></script>
</head>
<div class="container-fluid flex-grow-1 container-p-y">
  <!-- En-tête avec actions -->
  <div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight">
      <h4 class="py-4 mb-6">Modification du client</h4>
    </div>

    <div class="ms-auto p-2 bd-highlight">
      <a href="<?php echo BASE_URL; ?>clients/view/<?php echo $client['id'] ?? ''; ?>" class="btn btn-secondary me-2"
        id="backToViewBtn">
        <i class="bi bi-arrow-left me-1"></i> Retour
      </a>
      <button type="submit" form="clientForm" class="btn btn-primary">
        Enregistrer
      </button>
      <?php if (isAdmin()): ?>
        <a href="<?php echo BASE_URL; ?>clients/delete/<?php echo $client['id']; ?>" class="btn btn-danger ms-2"
          onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce client ?');">
          Supprimer
        </a>
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

  <?php if ($client): ?>
    <form id="clientForm" action="<?php echo BASE_URL; ?>clients/update/<?php echo $client['id']; ?>" method="POST">
      <?= csrf_field() ?>

      <!-- Onglets -->
      <ul class="nav nav-tabs mb-4" id="clientTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button"
            role="tab" aria-controls="info" aria-selected="true">
            <i class="bi bi-info-circle me-1"></i> Informations
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button"
            role="tab" aria-controls="contacts" aria-selected="false">
            <i class="fas fa-address-book me-2"></i> Contacts
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="sites-tab" data-bs-toggle="tab" data-bs-target="#sites" type="button" role="tab"
            aria-controls="sites" aria-selected="false">
            <i class="bi bi-building me-1"></i> Sites
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="contracts-tab" data-bs-toggle="tab" data-bs-target="#contracts" type="button"
            role="tab" aria-controls="contracts" aria-selected="false">
            <i class="bi bi-file-earmark-text me-1"></i> Contrats
          </button>
        </li>
        <!-- <li class="nav-item" role="presentation">
          <button class="nav-link" id="interventions-tab" data-bs-toggle="tab" data-bs-target="#interventions"
            type="button" role="tab" aria-controls="interventions" aria-selected="false">
            <i class="bi bi-tools me-1"></i> Interventions
          </button>
        </li> -->
      </ul>

      <div class="tab-content" id="clientTabsContent">

        <!-- ===================== Onglet Informations ===================== -->
        <div class="tab-pane fade show active" id="info" role="tabpanel" aria-labelledby="info-tab">
          <div class="card">
            <div class="card-header py-2">
              <h5 class="card-title mb-0">Informations générales</h5>
            </div>
            <div class="card-body py-2">
              <div class="row">
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="name" class="form-label">Nom <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="name" name="name"
                      value="<?php echo htmlspecialchars($client['name'] ?? ''); ?>" required>
                  </div>
                  <div class="mb-3">
                    <label for="city" class="form-label">Ville</label>
                    <input type="text" class="form-control" id="city" name="city"
                      value="<?php echo htmlspecialchars($client['city'] ?? ''); ?>">
                  </div>
                  <div class="mb-3">
                    <label for="address" class="form-label">Adresse</label>
                    <textarea class="form-control" id="address" name="address"
                      rows="3"><?php echo htmlspecialchars($client['address'] ?? ''); ?></textarea>
                  </div>
                  <div class="mb-3">
                    <label for="postal_code" class="form-label">Code Postal</label>
                    <input type="text" class="form-control" id="postal_code" name="postal_code"
                      value="<?php echo htmlspecialchars($client['postal_code'] ?? ''); ?>">
                  </div>
                </div>
                <div class="col-md-6">
                  <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email"
                      value="<?php echo htmlspecialchars($client['email'] ?? ''); ?>">
                  </div>
                  <div class="mb-3" style="display: flex; flex-direction: column; gap: 2; align-items: start;">
                    <label class="form-label">Téléphone</label>
                    <input type="tel" class="form-control" id="phone" name="phone_display"
                      value="<?= htmlspecialchars($client['phone'] ?? '') ?>">
                    <input type="hidden" name="phone" id="phone_full">
                    <div class="form-text" id="phone_error" style="display:none;"></div>
                  </div>
                  <div class="mb-3">
                    <label for="website" class="form-label">Site Web</label>
                    <input type="text" class="form-control" id="website" name="website"
                      value="<?php echo htmlspecialchars($client['website'] ?? ''); ?>" placeholder="https://exemple.com">
                  </div>
                  <div class="mb-3">
                    <label for="status" class="form-label">Statut</label>
                    <select class="form-select" id="status" name="status">
                      <option value="1" <?php echo ($client['status'] ?? 0) == 1 ? 'selected' : ''; ?>>
                        Actif</option>
                      <option value="0" <?php echo ($client['status'] ?? 0) == 0 ? 'selected' : ''; ?>>
                        Inactif</option>
                    </select>
                  </div>
                </div>
              </div>
              <div class="row mt-3">
                <div class="col-12">
                  <div class="card">
                    <div class="card-header py-2">
                      <h5 class="card-title mb-0">Commentaire</h5>
                    </div>
                    <div class="card-body py-2">
                      <textarea class="form-control" id="comment" name="comment"
                        rows="4"><?php echo htmlspecialchars($client['comment'] ?? ''); ?></textarea>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div><!-- /#info -->

        <!-- ===================== Onglet Contacts ===================== -->
        <div class="tab-pane fade" id="contacts" role="tabpanel" aria-labelledby="contacts-tab">
          <div class="card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
              <h5 class="card-title mb-0">Contacts</h5>
              <a href="<?php echo BASE_URL; ?>contacts/add/<?php echo $client['id']; ?>"
                class="btn btn-sm btn-custom-add">
                <i class="bi bi-plus me-1"></i> Ajouter un contact
              </a>
            </div>
            <div class="card-body py-2">
              <?php if (!empty($contacts)): ?>
                <div class="table-responsive">
                  <table class="table table-striped">
                    <thead>
                      <tr>
                        <th>Prénom</th>
                        <th>Nom</th>
                        <th>Fonction</th>
                        <th>Téléphone fixe</th>
                        <th>Mobile</th>
                        <th>Email</th>
                        <th>Compte utilisateur</th>
                        <th>Commentaire</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($contacts as $contact): ?>
                        <tr>
                          <td><?php echo htmlspecialchars($contact['first_name'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($contact['last_name'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($contact['fonction'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($contact['phone1'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($contact['phone2'] ?? ''); ?></td>
                          <td><?php echo htmlspecialchars($contact['email'] ?? ''); ?></td>
                          <td>
                            <?php if ($contact['has_user_account']): ?>
                              <span class="badge bg-success">Oui</span>
                              <?php if ($contact['first_name']): ?>
                                <br><small><?php echo h($contact['first_name']); ?></small>
                              <?php endif; ?>
                            <?php else: ?>
                              <span class="badge bg-secondary">Non</span>
                            <?php endif; ?>
                          </td>
                          <td><?php echo htmlspecialchars($contact['comment'] ?? ''); ?></td>
                          <td>
                            <a href="<?php echo BASE_URL; ?>contacts/edit/<?php echo $contact['id']; ?>"
                              class="btn btn-sm btn-outline-warning btn-action" title="Modifier">
                              <i class="bi bi-pencil me-1"></i>
                            </a>
                            <?php if (isAdmin()): ?>
                              <a href="<?php echo BASE_URL; ?>contacts/delete/<?php echo $contact['id']; ?>"
                                class="btn btn-sm btn-outline-danger btn-action" title="Supprimer"
                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce contact ?');">
                                <i class="bi bi-trash me-1"></i>
                              </a>
                            <?php endif; ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </tbody>
                  </table>
                </div>
              <?php else: ?>
                <p class="text-muted">Aucun contact trouvé.</p>
              <?php endif; ?>
            </div>
          </div>
        </div><!-- /#contacts -->

        <!-- ===================== Onglet Sites ===================== -->
        <div class="tab-pane fade" id="sites" role="tabpanel" aria-labelledby="sites-tab">
          <div class="card">
            <div class="card-header py-2 d-flex justify-content-between align-items-center">
              <h5 class="card-title mb-0">Sites, Bâtiments et Salles</h5>
              <a href="<?php echo BASE_URL; ?>site/add/<?php echo $client['id']; ?>" class="btn btn-sm btn-custom-add">
                <i class="bi bi-plus me-1"></i> Ajouter un site
              </a>
            </div>
            <div class="card-body py-2">
              <?php if (!empty($sites)): ?>
                <div class="accordion" id="sitesAccordion">
                  <?php foreach ($sites as $index => $site): ?>
                    <?php $isOpenSite = isset($_GET['open_site_id']) && $_GET['open_site_id'] == $site['id']; ?>
                    <div class="accordion-item">
                      <h2 class="accordion-header" id="siteHeading<?php echo $site['id']; ?>">
                        <button class="accordion-button <?php echo $isOpenSite ? '' : 'collapsed'; ?>" type="button"
                          data-bs-toggle="collapse" data-bs-target="#siteCollapse<?php echo $site['id']; ?>"
                          aria-expanded="<?php echo $isOpenSite ? 'true' : 'false'; ?>"
                          aria-controls="siteCollapse<?php echo $site['id']; ?>">
                          <i class="bi bi-building me-2"></i>
                          <?php echo htmlspecialchars($site['name'] ?? ''); ?>
                          <span class="badge bg-primary ms-2">
                            <?php echo count($site['buildings'] ?? []); ?> bâtiment(s)
                          </span>
                          <span class="badge bg-info ms-1">
                            <?php echo count($site['rooms'] ?? []); ?> salle(s)
                          </span>
                        </button>
                      </h2>
                      <div id="siteCollapse<?php echo $site['id']; ?>"
                        class="accordion-collapse collapse <?php echo $isOpenSite ? 'show' : ''; ?>"
                        aria-labelledby="siteHeading<?php echo $site['id']; ?>" data-bs-parent="#sitesAccordion">
                        <div class="accordion-body">
                          <div class="d-flex justify-content-end mb-3 gap-2">
                            <a href="<?php echo BASE_URL; ?>building/add/<?php echo $site['id']; ?>?client_id=<?php echo $client['id']; ?>&return_to=edit"
                              class="btn btn-sm btn-outline-warning" title="Ajouter un bâtiment">
                              <i class="bi bi-building me-1"></i> Ajouter un bâtiment
                            </a>
                            <a href="<?php echo BASE_URL; ?>qrcode/generate/site/<?php echo $site['id']; ?>"
                              class="btn btn-sm btn-outline-primary btn-action" title="Générer les QR codes des salles">
                              <i class="bi bi-qr-code me-1"></i> QR Codes
                            </a>
                            <a href="<?php echo BASE_URL; ?>site/edit/<?php echo $site['id']; ?>"
                              class="btn btn-sm btn-outline-warning btn-action" title="Modifier le site">
                              <i class="bi bi-pencil me-1"></i>
                            </a>
                            <?php if (isAdmin()): ?>
                              <a href="<?php echo BASE_URL; ?>site/delete/<?php echo $site['id']; ?>"
                                class="btn btn-sm btn-outline-danger btn-action" title="Supprimer le site"
                                onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce site ? Cette action supprimera également tous les bâtiments et salles associés.');">
                                <i class="bi bi-trash me-1"></i>
                              </a>
                            <?php endif; ?>
                          </div>

                          <!-- Informations du site -->
                          <div class="row mb-3">
                            <div class="col-md-6">
                              <table class="table table-sm">
                                <tr>
                                  <th style="width: 30%">Adresse</th>
                                  <td><?php echo htmlspecialchars($site['address'] ?? ''); ?></td>
                                </tr>
                                <tr>
                                  <th>Code Postal</th>
                                  <td><?php echo htmlspecialchars($site['postal_code'] ?? ''); ?>
                                  </td>
                                </tr>
                                <tr>
                                  <th>Ville</th>
                                  <td><?php echo htmlspecialchars($site['city'] ?? ''); ?></td>
                                </tr>
                                <tr>
                                  <th>Téléphone</th>
                                  <td><?php echo htmlspecialchars($site['phone'] ?? ''); ?></td>
                                </tr>
                                <tr>
                                  <th>Email</th>
                                  <td><?php echo htmlspecialchars($site['email'] ?? ''); ?></td>
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

                              <?php if (!empty($site['primary_contact'])): ?>
                                <div class="card mt-3">
                                  <div class="card-header py-2">
                                    <h6 class="card-title mb-0">Contact principal</h6>
                                  </div>
                                  <div class="card-body py-2">
                                    <div class="d-flex align-items-center">
                                      <div class="flex-shrink-0">
                                        <i class="fas fa-user-circle fa-2x text-light"></i>
                                      </div>
                                      <div class="flex-grow-1 ms-3">
                                        <h6 class="mb-1">
                                          <?php echo htmlspecialchars($site['primary_contact']['first_name'] . ' ' . $site['primary_contact']['last_name']); ?>
                                        </h6>
                                        <?php if (!empty($site['primary_contact']['phone1'])): ?>
                                          <p class="mb-1 small">
                                            <i class="fas fa-phone-alt me-1"></i>
                                            <?php echo htmlspecialchars($site['primary_contact']['phone1']); ?>
                                          </p>
                                        <?php endif; ?>
                                        <?php if (!empty($site['primary_contact']['email'])): ?>
                                          <p class="mb-0 small">
                                            <i class="bi bi-envelope me-1"></i>
                                            <?php echo htmlspecialchars($site['primary_contact']['email']); ?>
                                          </p>
                                        <?php endif; ?>
                                      </div>
                                    </div>
                                  </div>
                                </div>
                              <?php endif; ?>
                            </div>
                          </div>

                          <!-- Bâtiments du site -->
                          <div class="mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                              <h6 class="mb-0">
                                <i class="bi bi-building text-warning me-2"></i>Bâtiments
                              </h6>
                            </div>

                            <?php if (!empty($site['buildings'])): ?>
                              <?php foreach ($site['buildings'] as $building): ?>
                                <div class="card mb-3">
                                  <div class="card-header py-2 d-flex justify-content-between align-items-center">
                                    <strong><?php echo h($building['name']); ?></strong>
                                    <div class="btn-group">
                                      <a href="<?php echo BASE_URL; ?>building/edit/<?php echo $building['id']; ?>?return_to=edit&client_id=<?php echo $client['id']; ?>"
                                        class="btn btn-sm btn-outline-warning" title="Modifier le bâtiment">
                                        <i class="bi bi-pencil"></i>
                                      </a>
                                      <?php if (isAdmin()): ?>
                                        <button type="button" class="btn btn-sm btn-outline-danger" title="Supprimer le bâtiment"
                                          onclick="confirmDeleteBuilding(<?php echo $building['id']; ?>, '<?php echo h($building['name']); ?>', <?php echo $client['id']; ?>)">
                                          <i class="bi bi-trash"></i>
                                        </button>
                                      <?php endif; ?>
                                      <button type="button" class="btn btn-sm btn-outline-info toggle-rooms-btn"
                                        data-building-id="<?php echo $building['id']; ?>" title="Afficher/Masquer les salles">
                                        <i class="bi bi-chevron-down"></i>
                                      </button>
                                    </div>
                                  </div>
                                  <div class="card-body py-2">
                                    <div class="row">
                                      <div class="col-md-12">
                                        <strong>Commentaire :</strong>
                                        <?php echo nl2br(htmlspecialchars($building['comment'] ?? '')); ?>
                                      </div>
                                    </div>
                                  </div>
                                  <div class="building-rooms-<?php echo $building['id']; ?>" style="display: none;">
                                    <div class="card-body py-2 bg-light">
                                      <div class="d-flex justify-content-between align-items-center mb-2">
                                        <h6 class="mb-0">
                                          <i class="bi bi-door-open text-info me-2"></i>Salles
                                        </h6>
                                        <a href="<?php echo BASE_URL; ?>room/add/0?building_id=<?php echo $building['id']; ?>&client_id=<?php echo $client['id']; ?>&return_to=edit"
                                          class="btn btn-sm btn-custom-add">
                                          <i class="bi bi-plus me-1"></i> Ajouter une salle
                                        </a>
                                      </div>
                                      <?php if (!empty($building['rooms'])): ?>
                                        <div class="table-responsive">
                                          <table class="table table-sm table-striped">
                                            <thead>
                                              <tr>
                                                <th>Nom</th>
                                                <th>Contact principal</th>
                                                <th>Commentaire</th>
                                                <th>Actions</th>
                                              </tr>
                                            </thead>
                                            <tbody>
                                              <?php foreach ($building['rooms'] as $room): ?>
                                                <tr>
                                                  <td><?php echo htmlspecialchars($room['name'] ?? ''); ?>
                                                  </td>
                                                  <td>
                                                    <?php
                                                    if (!empty($room['first_name']) && !empty($room['last_name'])) {
                                                      echo htmlspecialchars($room['first_name'] . ' ' . $room['last_name']);
                                                    } else {
                                                      echo '<span class="text-muted">Aucun contact</span>';
                                                    }
                                                    ?>
                                                  </td>
                                                  <td><?php echo nl2br(htmlspecialchars($room['comment'] ?? '')); ?>
                                                  </td>
                                                  <td>
                                                    <div class="btn-group">
                                                      <a href="<?php echo BASE_URL; ?>room/edit/<?php echo $room['id']; ?>"
                                                        class="btn btn-sm btn-outline-warning" title="Modifier la salle">
                                                        <i class="bi bi-pencil"></i>
                                                      </a>
                                                      <?php if (isAdmin()): ?>
                                                        <a href="<?php echo BASE_URL; ?>room/delete/<?php echo $room['id']; ?>"
                                                          class="btn btn-sm btn-outline-danger" title="Supprimer la salle"
                                                          onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette salle ?');">
                                                          <i class="bi bi-trash"></i>
                                                        </a>
                                                      <?php endif; ?>
                                                    </div>
                                                  </td>
                                                </tr>
                                              <?php endforeach; ?>
                                            </tbody>
                                          </table>
                                        </div>
                                      <?php else: ?>
                                        <div class="alert alert-info mt-2">
                                          Aucune salle dans ce bâtiment.
                                          <a href="<?php echo BASE_URL; ?>room/add/0?building_id=<?php echo $building['id']; ?>&client_id=<?php echo $client['id']; ?>&return_to=edit"
                                            class="alert-link">
                                            Ajouter une salle
                                          </a>
                                        </div>
                                      <?php endif; ?>
                                    </div><!-- /.card-body bg-light -->
                                  </div><!-- /.building-rooms -->
                                </div><!-- /.card mb-3 -->
                              <?php endforeach; ?>
                            <?php else: ?>
                              <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i> Aucun bâtiment pour ce site.
                                <a href="<?php echo BASE_URL; ?>building/add/<?php echo $site['id']; ?>?client_id=<?php echo $client['id']; ?>&return_to=edit"
                                  class="alert-link">
                                  Ajouter un bâtiment
                                </a>
                              </div>
                            <?php endif; ?>
                          </div><!-- /.mt-4 -->
                        </div><!-- /.accordion-body -->
                      </div><!-- /.accordion-collapse -->
                    </div><!-- /.accordion-item -->
                  <?php endforeach; ?>
                </div><!-- /#sitesAccordion -->
              <?php else: ?>
                <p class="text-muted">Aucun site trouvé.</p>
                <div class="text-center mt-3">
                  <a href="<?php echo BASE_URL; ?>site/add/<?php echo $client['id']; ?>" class="btn btn-primary">
                    <i class="bi bi-plus me-1"></i> Ajouter un premier site
                  </a>
                </div>
              <?php endif; ?>
            </div><!-- /.card-body -->
          </div><!-- /.card -->
        </div><!-- /#sites -->

        <!-- ===================== Onglet Contrats ===================== -->
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
                        <th>Nom</th>
                        <th>Type de contrat</th>
                        <th>Date de fin</th>
                        <th>Tickets initiaux</th>
                        <th>Tickets restants</th>
                        <th>Statut</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php foreach ($contracts as $contract): ?>
                        <tr>
                          <td data-label="Nom">
                            <?php
                            // Déterminer l'URL selon le type de contrat
                            $contractName = strtolower($contract['name'] ?? '');
                            if (strpos($contractName, 'hors contrat non facturable') !== false) {
                              $viewUrl = BASE_URL . 'hors_contrat_non_facturable/view/' . $contract['id'];
                            } elseif (strpos($contractName, 'hors contrat facturable') !== false) {
                              $viewUrl = BASE_URL . 'hors_contrat_facturable/view/' . $contract['id'];
                            } else {
                              $viewUrl = BASE_URL . 'contracts/view/' . $contract['id'];
                            }
                            ?>
                            <a href="<?php echo $viewUrl; ?>?return_to=client&client_id=<?php echo $client['id']; ?>&active_tab=contracts-tab"
                              class="text-decoration-none fw-bold" title="Voir le contrat">
                              <?php echo htmlspecialchars($contract['name'] ?? '-'); ?>
                            </a>
                          </td>
                          <td data-label="Type de contrat">
                            <?php echo htmlspecialchars($contract['contract_type_name'] ?? '-'); ?>
                          </td>
                          <td data-label="Date de fin">
                            <?php echo formatDateFrench($contract['end_date']); ?>
                          </td>
                          <td data-label="Tickets initiaux">
                            <?php if (isContractTicketById($contract['id'])): ?>
                              <span class="badge bg-info">
                                <?php echo $contract['tickets_number']; ?>
                              </span>
                            <?php else: ?>
                              <span class="text-muted" title="Sans tickets">--</span>
                            <?php endif; ?>
                          </td>
                          <td data-label="Tickets restants">
                            <?php if (isContractTicketById($contract['id'])): ?>
                              <span class="badge bg-<?php echo $contract['tickets_remaining'] > 3 ? 'success' : 'danger'; ?>">
                                <?php echo $contract['tickets_remaining']; ?>
                              </span>
                            <?php else: ?>
                              <span class="text-muted">--</span>
                            <?php endif; ?>
                          </td>
                          <td data-label="Statut">
                            <span class="badge bg-<?php
                            echo $contract['status'] === 'actif' ? 'success' :
                              ($contract['status'] === 'inactif' ? 'danger' :
                                ($contract['status'] === 'en_attente' ? 'warning' : 'secondary'));
                            ?>">
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
        </div><!-- /#contracts -->

        <!-- ===================== Onglet Interventions ===================== -->
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
                <div class="accordion" id="interventionsAccordion">
                  <?php foreach ($interventionsGrouped as $contractId => $contractGroup): ?>
                    <div class="accordion-item">
                      <h2 class="accordion-header" id="contractHeading<?php echo $contractId; ?>">
                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse"
                          data-bs-target="#contractCollapse<?php echo $contractId; ?>" aria-expanded="false"
                          aria-controls="contractCollapse<?php echo $contractId; ?>">
                          <div class="d-flex justify-content-between align-items-center w-100 me-3">
                            <span><?php echo h($contractGroup['contract_name'] ?? 'Contrat sans nom'); ?></span>
                            <div class="d-flex gap-2">
                              <?php if (!empty($contractGroup['preventive'])): ?>
                                <span class="badge bg-success"><?php echo count($contractGroup['preventive']); ?>
                                  préventive(s)</span>
                              <?php endif; ?>
                              <?php if (!empty($contractGroup['corrective'])): ?>
                                <span class="badge bg-warning"><?php echo count($contractGroup['corrective']); ?>
                                  corrective(s)</span>
                              <?php endif; ?>
                            </div>
                          </div>
                        </button>
                      </h2>
                      <div id="contractCollapse<?php echo $contractId; ?>" class="accordion-collapse collapse"
                        aria-labelledby="contractHeading<?php echo $contractId; ?>" data-bs-parent="#interventionsAccordion">
                        <div class="accordion-body">

                          <?php
                          // Préventives puis correctives : même tableau, deux passes
                          $interventionTypes = [
                            'preventive' => ['title' => 'Interventions Préventives', 'class' => 'text-success', 'icon' => 'bi-shield-check'],
                            'corrective' => ['title' => 'Interventions Correctives', 'class' => 'text-warning', 'icon' => 'bi-tools'],
                          ];
                          ?>
                          <?php foreach ($interventionTypes as $typeKey => $typeInfo): ?>
                            <?php if (!empty($contractGroup[$typeKey])): ?>
                              <div class="mb-4">
                                <h6 class="fw-bold <?php echo $typeInfo['class']; ?> mb-3">
                                  <i class="bi <?php echo $typeInfo['icon']; ?> me-2"></i><?php echo $typeInfo['title']; ?>
                                  (<?php echo count($contractGroup[$typeKey]); ?>)
                                </h6>
                                <div class="table-responsive">
                                  <table class="table table-sm table-striped">
                                    <thead>
                                      <tr>
                                        <th>Référence</th>
                                        <th>Titre</th>
                                        <th>Date</th>
                                        <th>Technicien</th>
                                        <th>Durée</th>
                                        <th>Tickets utilisés</th>
                                        <th>Statut</th>
                                      </tr>
                                    </thead>
                                    <tbody>
                                      <?php foreach ($contractGroup[$typeKey] as $intervention): ?>
                                        <tr>
                                          <td>
                                            <a href="<?php echo BASE_URL; ?>interventions/view/<?php echo $intervention['id']; ?>?return_to=client&client_id=<?php echo $client['id']; ?>&active_tab=interventions-tab"
                                              class="text-decoration-none fw-bold" title="Voir l'intervention">
                                              <?php echo htmlspecialchars($intervention['reference'] ?? 'INT-' . $intervention['id']); ?>
                                            </a>
                                          </td>
                                          <td><small><?php echo htmlspecialchars($intervention['title'] ?? '-'); ?></small>
                                          </td>
                                          <td><small><?php echo formatDateFrench($intervention['created_at']); ?></small>
                                          </td>
                                          <td>
                                            <small><?php echo htmlspecialchars($intervention['technician_name'] ?? '-'); ?></small>
                                          </td>
                                          <td><small><?php echo $intervention['duration'] ?? '-'; ?>h</small>
                                          </td>
                                          <td>
                                            <small>
                                              <?php if (($intervention['tickets_used'] ?? 0) > 0): ?>
                                                <span class="badge bg-info"><?php echo $intervention['tickets_used']; ?></span>
                                              <?php else: ?>
                                                <span class="text-muted">-</span>
                                              <?php endif; ?>
                                            </small>
                                          </td>
                                          <td>
                                            <span class="badge rounded-pill"
                                              style="background-color: <?php echo htmlspecialchars($intervention['status_color'] ?? '#6c757d'); ?>">
                                              <?php echo htmlspecialchars($intervention['status_name'] ?? '-'); ?>
                                            </span>
                                          </td>
                                        </tr>
                                      <?php endforeach; ?>
                                    </tbody>
                                  </table>
                                </div>
                              </div>
                            <?php endif; ?>
                          <?php endforeach; ?>

                          <?php if (empty($contractGroup['preventive']) && empty($contractGroup['corrective'])): ?>
                            <div class="alert alert-info mb-0">
                              <i class="bi bi-info-circle me-2"></i> Aucune intervention enregistrée pour
                              ce contrat.
                            </div>
                          <?php endif; ?>
                        </div><!-- /.accordion-body -->
                      </div><!-- /.accordion-collapse -->
                    </div><!-- /.accordion-item -->
                  <?php endforeach; ?>
                </div><!-- /#interventionsAccordion -->
              <?php else: ?>
                <div class="alert alert-info mb-0">
                  <i class="bi bi-info-circle me-2"></i> Aucune intervention enregistrée pour ce client.
                </div>
              <?php endif; ?>
            </div><!-- /.card-body -->
          </div><!-- /.card -->
        </div><!-- /#interventions -->

      </div><!-- /.tab-content : fermé ICI, après tous les panneaux -->
    </form>
  <?php else: ?>
    <div class="alert alert-warning">
      Client non trouvé.
    </div>
  <?php endif; ?>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function () {
    // Onglet actif via ?active_tab=xxx-tab, sinon via #hash
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('active_tab');

    if (activeTab) {
      const tab = document.getElementById(activeTab);
      if (tab) {
        new bootstrap.Tab(tab).show();
      }
    } else {
      const hash = window.location.hash.substring(1);
      if (hash) {
        const tab = document.querySelector('button[data-bs-target="#' + hash + '"]');
        if (tab) {
          new bootstrap.Tab(tab).show();
        }
      }
    }

    // Bouton Retour : conserve l'onglet actif
    const backBtn = document.getElementById('backToViewBtn');
    if (backBtn && activeTab) {
      backBtn.addEventListener('click', function (e) {
        e.preventDefault();
        const separator = this.href.includes('?') ? '&' : '?';
        window.location.href = this.href + separator + 'active_tab=' + activeTab;
      });
    }

    // Déplier / replier tout (interventions)
    const accordion = document.getElementById('interventionsAccordion');
    const expandBtn = document.getElementById('expandAllInterventions');
    const collapseBtn = document.getElementById('collapseAllInterventions');

    if (accordion && expandBtn) {
      expandBtn.addEventListener('click', function () {
        accordion.querySelectorAll('.accordion-collapse').forEach(function (el) {
          bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show();
        });
      });
    }
    if (accordion && collapseBtn) {
      collapseBtn.addEventListener('click', function () {
        accordion.querySelectorAll('.accordion-collapse.show').forEach(function (el) {
          bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).hide();
        });
      });
    }
  });
</script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const phoneInput = document.querySelector('#phone');

    const iti = window.intlTelInput(phoneInput, {
      initialCountry: 'fr',
      preferredCountries: ['fr', 'be', 'ch', 'ca'],
      separateDialCode: true,
      utilsScript: 'https://cdn.jsdelivr.net/npm/intl-tel-input@23/build/js/utils.js'
    });

    <?php if (!empty($client['phone'])): ?>
      iti.setNumber(<?= json_encode($client['phone']) ?>);
    <?php endif; ?>

    const form = phoneInput.closest('form');
    const phoneFullInput = document.getElementById('phone_full');
    const phoneError = document.getElementById('phone_error');

    form.addEventListener('submit', function (e) {
      const phoneValue = phoneInput.value.trim();

      if (phoneValue === '') {
        phoneFullInput.value = '';
        phoneError.style.display = 'none';
        return;
      }

      if (!iti.isValidNumber()) {
        e.preventDefault();
        phoneError.textContent = 'Numéro de téléphone invalide pour le pays sélectionné.';
        phoneError.classList.add('text-danger');
        phoneError.style.display = 'block';
        return;
      }
      phoneFullInput.value = iti.getNumber();
      phoneError.style.display = 'none';
    });
  });
</script>
<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?>
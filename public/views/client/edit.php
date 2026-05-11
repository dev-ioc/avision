<?php
require_once __DIR__ . '/../../includes/functions.php';
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

setPageVariables(
    'Modification du client',
    'clients'
);

// Définir la page courante pour le menu
$currentPage = 'clients';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">Modification du client</h4>
        </div>

        <div class="ms-auto p-2 bd-highlight">
            <a href="<?php echo BASE_URL; ?>clients/view/<?php echo $client['id'] ?? ''; ?>"
                class="btn btn-secondary me-2" id="backToViewBtn">
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
            <!-- Onglets pour les différentes sections -->
            <ul class="nav nav-tabs mb-4" id="clientTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" type="button"
                        role="tab" aria-controls="info" aria-selected="true">
                        <i class="bi bi-info-circle me-2 me-1"></i> Informations
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" type="button"
                        role="tab" aria-controls="contacts" aria-selected="false">
                        <i class="fas fa-address-book me-2"></i> Contacts
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="sites-tab" data-bs-toggle="tab" data-bs-target="#sites" type="button"
                        role="tab" aria-controls="sites" aria-selected="false">
                        <i class="bi bi-building me-2 me-1"></i> Sites
                    </button>
                </li>
                <!-- Onglet Contrats (visible pour tous les utilisateurs connectés) -->
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="contracts-tab" data-bs-toggle="tab" data-bs-target="#contracts"
                        type="button" role="tab" aria-controls="contracts" aria-selected="false">
                        <i class="bi bi-file-earmark-text me-2 me-1"></i> Contrats
                    </button>
                </li>
            </ul>

            <div class="tab-content" id="clientTabsContent">
                <!-- Onglet Informations -->
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
                                    <div class="mb-3">
                                        <label for="phone" class="form-label">Téléphone</label>
                                        <input type="text" class="form-control" id="phone" name="phone"
                                            value="<?php echo htmlspecialchars($client['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="website" class="form-label">Site Web</label>
                                        <input type="text" class="form-control" id="website" name="website"
                                            value="<?php echo htmlspecialchars($client['website'] ?? ''); ?>"
                                            placeholder="https://exemple.com">
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
                </div>

                <!-- Onglet Contacts -->
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
                                                            <?php if ($contact['user_username']): ?>
                                                                <br><small><?php echo h($contact['user_username']); ?></small>
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
                </div>

                <!-- Onglet Sites -->
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
                                                <div class="accordion-item">
                                                    <h2 class="accordion-header" id="siteHeading<?php echo $site['id']; ?>">
                                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" 
                                                            data-bs-target="#siteCollapse<?php echo $site['id']; ?>" 
                                                            aria-expanded="false" aria-controls="siteCollapse<?php echo $site['id']; ?>"
                                                            <?php echo (isset($_GET['open_site_id']) && $_GET['open_site_id'] == $site['id']) ? '' : ''; ?>>
                                                            <i class="bi bi-building me-2 me-1"></i>
                                                            <?php echo htmlspecialchars($site['name'] ?? ''); ?>
                                                            <span class="badge bg-primary ms-2"><?php echo count($site['buildings'] ?? []); ?> bâtiment(s)</span>
                                                            <span class="badge bg-info ms-1"><?php echo count($site['rooms'] ?? []); ?> salle(s)</span>
                                                        </button>
                                                    </h2>
                                                    <div id="siteCollapse<?php echo $site['id']; ?>" class="accordion-collapse collapse" 
                                                        aria-labelledby="siteHeading<?php echo $site['id']; ?>" 
                                                        data-bs-parent="#sitesAccordion"
                                                        <?php echo (isset($_GET['open_site_id']) && $_GET['open_site_id'] == $site['id']) ? 'class="accordion-collapse collapse show"' : ''; ?>>
                                                        <div class="accordion-body">
                                                            <div class="d-flex justify-content-end mb-3 gap-2">
                                                                <a href="<?php echo BASE_URL; ?>building/add/<?php echo $site['id']; ?>?client_id=<?php echo $client['id']; ?>&return_to=edit" class="btn btn-sm btn-outline-warning" title="Ajouter un bâtiment">
                                                                    <i class="bi bi-building me-1"></i> Ajouter un bâtiment
                                                                </a>
                                                                <a href="<?php echo BASE_URL; ?>qrcode/generate/site/<?php echo $site['id']; ?>" class="btn btn-sm btn-outline-primary btn-action" title="Générer les QR codes des salles">
                                                                    <i class="bi bi-qr-code me-1"></i> QR Codes
                                                                </a>
                                                                <a href="<?php echo BASE_URL; ?>site/edit/<?php echo $site['id']; ?>" class="btn btn-sm btn-outline-warning btn-action" title="Modifier le site">
                                                                    <i class="bi bi-pencil me-1"></i>
                                                                </a>
                                                                <?php if (isAdmin()): ?>
                                                                        <a href="<?php echo BASE_URL; ?>site/delete/<?php echo $site['id']; ?>" class="btn btn-sm btn-outline-danger btn-action" title="Supprimer le site" onclick="return confirm('Êtes-vous sûr de vouloir supprimer ce site ? Cette action supprimera également tous les bâtiments et salles associés.');">
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
                                                                            <td><?php echo htmlspecialchars($site['postal_code'] ?? ''); ?></td>
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
                                                                            <p class="card-text"><?php echo nl2br(htmlspecialchars($site['comment'] ?? '')); ?></p>
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
                                                                                            <h6 class="mb-1"><?php echo htmlspecialchars($site['primary_contact']['first_name'] . ' ' . $site['primary_contact']['last_name']); ?></h6>
                                                                                            <?php if (!empty($site['primary_contact']['phone1'])): ?>
                                                                                                        <p class="mb-1 small">
                                                                                                            <i class="fas fa-phone-alt me-1"></i> <?php echo htmlspecialchars($site['primary_contact']['phone1']); ?>
                                                                                                        </p>
                                                                                            <?php endif; ?>
                                                                                            <?php if (!empty($site['primary_contact']['email'])): ?>
                                                                                                        <p class="mb-0 small">
                                                                                                            <i class="bi bi-envelope me-1 me-1"></i> <?php echo htmlspecialchars($site['primary_contact']['email']); ?>
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
                                                                                                    <a href="<?php echo BASE_URL; ?>building/edit/<?php echo $building['id']; ?>?return_to=edit&client_id=<?php echo $client['id']; ?>" class="btn btn-sm btn-outline-warning" title="Modifier le bâtiment">
                                                                                                        <i class="bi bi-pencil"></i>
                                                                                                    </a>
                                                                                                    <?php if (isAdmin()): ?>
                                                                                                            <button type="button" class="btn btn-sm btn-outline-danger" title="Supprimer le bâtiment" onclick="confirmDeleteBuilding(<?php echo $building['id']; ?>, '<?php echo h($building['name']); ?>', <?php echo $client['id']; ?>)">
                                                                                                                <i class="bi bi-trash"></i>
                                                                                                            </button>
                                                                                                    <?php endif; ?>
                                                                                                    <button type="button" class="btn btn-sm btn-outline-info toggle-rooms-btn" data-building-id="<?php echo $building['id']; ?>" title="Afficher/Masquer les salles">
                                                                                                        <i class="bi bi-chevron-down"></i>
                                                                                                    </button>
                                                                                                </div>
                                                                                            </div>
                                                                                            <div class="card-body py-2">
                                                                                                <div class="row">
                                                                                                    <div class="col-md-12">
                                                                                                        <strong>Commentaire :</strong> <?php echo nl2br(htmlspecialchars($building['comment'] ?? '')); ?>
                                                                                                    </div>
                                                                                                </div>
                                                                                            </div>
                                                                                            <div class="building-rooms-<?php echo $building['id']; ?>" style="display: none;">
                                                                                                <div class="card-body py-2 bg-light">
                                                                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                                                                        <h6 class="mb-0">
                                                                                                            <i class="bi bi-door-open text-info me-2"></i>Salles
                                                                                                        </h6>
                                                                                                        <a href="<?php echo BASE_URL; ?>room/add/0?building_id=<?php echo $building['id']; ?>&client_id=<?php echo $client['id']; ?>&return_to=edit" class="btn btn-sm btn-custom-add">
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
                                                                                                                                            <td><?php echo htmlspecialchars($room['name'] ?? ''); ?></td>
                                                                                                                                            <td>
                                                                                                                                                <?php
                                                                                                                                                if (!empty($room['first_name']) && !empty($room['last_name'])) {
                                                                                                                                                    echo htmlspecialchars($room['first_name'] . ' ' . $room['last_name']);
                                                                                                                                                } else {
                                                                                                                                                    echo '<span class="text-muted">Aucun contact</span>';
                                                                                                                                                }
                                                                                                                                                ?>
                                                                                                                                            </td>
                                                                                                                                            <td><?php echo nl2br(htmlspecialchars($room['comment'] ?? '')); ?></td>
                                                                                                                                            <td>
                                                                                                                                                <div class="btn-group">
                                                                                                                                                    <a href="<?php echo BASE_URL; ?>room/edit/<?php echo $room['id']; ?>" class="btn btn-sm btn-outline-warning" title="Modifier la salle">
                                                                                                                                                        <i class="bi bi-pencil"></i>
                                                                                                                                                    </a>
                                                                                                                                                    <?php if (isAdmin()): ?>
                                                                                                                                                            <a href="<?php echo BASE_URL; ?>room/delete/<?php echo $room['id']; ?>" class="btn btn-sm btn-outline-danger" title="Supprimer la salle" onclick="return confirm('Êtes-vous sûr de vouloir supprimer cette salle ?');">
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
                                                                                                                    <a href="<?php echo BASE_URL; ?>room/add/0?building_id=<?php echo $building['id']; ?>&client_id=<?php echo $client['id']; ?>&return_to=edit" class="alert-link">
                                                                                                                        Ajouter une salle
                                                                                                                    </a>
                                                                                                                </div>
                                                                                                    <?php endif; ?>
                                                                                                </div>
                                                                                            </div>
                                                                                        </div>
                                                                            <?php endforeach; ?>
                                                                <?php else: ?>
                                                                            <div class="alert alert-info">
                                                                                <i class="bi bi-info-circle me-2"></i> Aucun bâtiment pour ce site.
                                                                                <a href="<?php echo BASE_URL; ?>building/add/<?php echo $site['id']; ?>?client_id=<?php echo $client['id']; ?>&return_to=edit" class="alert-link">
                                                                                    Ajouter un bâtiment
                                                                                </a>
                                                                            </div>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                    <?php endforeach; ?>
                                </div>
                    <?php else: ?>
                                <p class="text-muted">Aucun site trouvé.</p>
                                <div class="text-center mt-3">
                                    <a href="<?php echo BASE_URL; ?>site/add/<?php echo $client['id']; ?>" class="btn btn-primary">
                                        <i class="bi bi-plus me-1"></i> Ajouter un premier site
                                    </a>
                                </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
                </form>
    <?php else: ?>
                <div class="alert alert-warning">
                    Client non trouvé.
                </div>
    <?php endif; ?>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Récupérer le paramètre active_tab de l'URL
        const urlParams = new URLSearchParams(window.location.search);
        const activeTab = urlParams.get('active_tab');

        // Si un paramètre active_tab est présent, activer l'onglet correspondant
        if (activeTab) {
            const tab = document.querySelector(`#${activeTab}`);
            if (tab) {
                const tabInstance = new bootstrap.Tab(tab);
                tabInstance.show();
            }
        }

        // Récupérer le hash de l'URL (sans le #) comme fallback
        const hash = window.location.hash.substring(1);
        if (hash && !activeTab) {
            const tab = document.querySelector(`button[data-bs-target="#${hash}"]`);
            if (tab) {
                const tabInstance = new bootstrap.Tab(tab);
                tabInstance.show();
            }
        }

        // Gestion du bouton Retour avec persistance de l'onglet
        const backBtn = document.getElementById('backToViewBtn');
        if (backBtn && activeTab) {
            backBtn.addEventListener('click', function (e) {
                e.preventDefault();

                // Construire l'URL avec le paramètre de l'onglet
                const baseUrl = this.href;
                const separator = baseUrl.includes('?') ? '&' : '?';
                const newUrl = baseUrl + separator + 'active_tab=' + activeTab;

                // Rediriger vers la page de vue
                window.location.href = newUrl;
            });
        }
    });
</script>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const urlParams = new URLSearchParams(window.location.search);
        const openSiteId = urlParams.get('open_site_id');

        if (openSiteId) {
            const siteCollapseElement = document.getElementById('siteCollapse' + openSiteId);
            if (siteCollapseElement) {
                // Ensure other accordions are closed if you want only one open
                // This might require more complex logic if you have multiple accordions on the page
                // For now, just try to open the target one.
                const accordionButton = document.querySelector(`button[data-bs-target="#siteCollapse${openSiteId}"]`);
                if (accordionButton && accordionButton.classList.contains('collapsed')) {
                    new bootstrap.Collapse(siteCollapseElement).show();
                }
            }
            // Clean the URL parameter to prevent it from persisting on manual reloads/navigation
            // Or, if you want it to persist until next explicit navigation, comment this out
            // const newUrl = window.location.pathname + window.location.hash;
            // window.history.replaceState({}, document.title, newUrl);
        }
    });
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?>
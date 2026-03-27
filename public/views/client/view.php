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
    <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Détails du client</h4></div>

    <div class="ms-auto p-2 bd-highlight">
        <a href="<?php echo $returnUrl; ?>" class="btn btn-secondary me-2">
            <i class="bi bi-arrow-left me-1"></i> Retour
        </a>
        <a href="<?php echo BASE_URL; ?>documentation?client_id=<?php echo $client['id'] ?? ''; ?>" class="btn btn-info me-2">
            <i class="bi bi-book me-1"></i> Documentation
        </a>
        <a href="<?php echo BASE_URL; ?>materiel?client_id=<?php echo $client['id'] ?? ''; ?>" class="btn btn-primary me-2">
            <i class="bi bi-box-seam me-1"></i> Matériel
        </a>
        <?php if ($canModifyClient): ?>
            <a href="<?php echo BASE_URL; ?>clients/edit/<?php echo $client['id'] ?? ''; ?>" class="btn btn-warning me-2" id="editClientBtn">
                Modifier
            </a>
        <?php else: ?>
            <button type="button" class="btn btn-secondary me-2" disabled title="Vous n'avez pas les droits pour modifier ce client">
                Modifier
            </button>
        <?php endif; ?>
        <?php if (isAdmin()): ?>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="confirmDelete(<?php echo $client['id'] ?? 0; ?>, '<?php echo htmlspecialchars($client['name'] ?? ''); ?>')" title="Supprimer le client">
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
                <div class="card tab-card active" id="info-tab" data-bs-toggle="tab" data-bs-target="#info" role="tab" aria-controls="info" aria-selected="true" style="cursor: pointer; border: 2px solid #007bff;">
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
                <div class="card tab-card" id="contacts-tab" data-bs-toggle="tab" data-bs-target="#contacts" role="tab" aria-controls="contacts" aria-selected="false" style="cursor: pointer; border: 2px solid transparent;">
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
                        <a href="<?php echo BASE_URL; ?>contacts/add/<?php echo $client['id']; ?>?return_to=view" class="btn btn-sm btn-custom-add">
                            <i class="bi bi-plus me-1"></i> Ajouter un contact
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col">
                <div class="card tab-card" id="sites-tab" data-bs-toggle="tab" data-bs-target="#sites" role="tab" aria-controls="sites" aria-selected="false" style="cursor: pointer; border: 2px solid transparent;">
                    <div class="card-body text-center p-2">
                        <div class="mb-1">
                            <i class="<?php echo getIcon('site', 'bi bi-building'); ?> fs-3 text-warning"></i>
                        </div>
                        <h6 class="card-title mb-1 fs-6">Sites</h6>
                        <small class="text-muted d-block"><?php echo $stats['site_count'] ?? 0; ?> site(s) • <?php echo $stats['room_count'] ?? 0; ?> salle(s)</small>
                    </div>
                </div>
                <?php if ($canModifyClient): ?>
                    <div class="text-center mt-2 d-flex gap-2 justify-content-center">
                        <a href="<?php echo BASE_URL; ?>site/add/<?php echo $client['id']; ?>?return_to=view" class="btn btn-sm btn-custom-add">
                            <i class="bi bi-plus me-1"></i> Ajouter un site
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
                <div class="card tab-card" id="contracts-tab" data-bs-toggle="tab" data-bs-target="#contracts" role="tab" aria-controls="contracts" aria-selected="false" style="cursor: pointer; border: 2px solid transparent;">
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
                        <a href="<?php echo BASE_URL; ?>contracts/add/<?php echo $client['id']; ?>?return_to=view" class="btn btn-sm btn-custom-add">
                            <i class="bi bi-plus me-1"></i> Ajouter un contrat
                        </a>
                    </div>
                <?php endif; ?>
            </div>
            <div class="col">
                <div class="card tab-card" id="interventions-tab" data-bs-toggle="tab" data-bs-target="#interventions" role="tab" aria-controls="interventions" aria-selected="false" style="cursor: pointer; border: 2px solid transparent;">
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
                        <a href="<?php echo BASE_URL; ?>interventions/add?client_id=<?php echo $client['id']; ?>&return_to=view" class="btn btn-sm btn-custom-add">
                            <i class="bi bi-plus me-1"></i> Ajouter une intervention
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

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
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 30%">Nom</th>
                                        <td><?php echo htmlspecialchars($client['name'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Ville</th>
                                        <td><?php echo htmlspecialchars($client['city'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Adresse</th>
                                        <td><?php echo htmlspecialchars($client['address'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Code Postal</th>
                                        <td><?php echo htmlspecialchars($client['postal_code'] ?? ''); ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <table class="table table-bordered">
                                    <tr>
                                        <th style="width: 30%">Email</th>
                                        <td><?php echo htmlspecialchars($client['email'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Téléphone</th>
                                        <td><?php echo htmlspecialchars($client['phone'] ?? ''); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Site Web</th>
                                        <td><?php echo htmlspecialchars($client['website'] ?? ''); ?></td>
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
                                        <p class="card-text"><?php echo nl2br(htmlspecialchars($client['comment'] ?? '')); ?></p>
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
                                            <th class="sortable" data-sort="first_name">
                                                Prénom <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="last_name">
                                                Nom <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="fonction">
                                                Fonction <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="phone1">
                                                Téléphone fixe <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="phone2">
                                                Mobile <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="email">
                                                Email <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="has_user_account">
                                                Compte utilisateur <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($contacts as $contact): ?>
                                            <tr>
                                                <td data-label="Prénom" data-sort-value="<?php echo htmlspecialchars(strtolower($contact['first_name'] ?? '')); ?>">
                                                    <?php echo htmlspecialchars($contact['first_name'] ?? ''); ?>
                                                </td>
                                                <td data-label="Nom" data-sort-value="<?php echo htmlspecialchars(strtolower($contact['last_name'] ?? '')); ?>">
                                                    <?php echo htmlspecialchars($contact['last_name'] ?? ''); ?>
                                                </td>
                                                <td data-label="Fonction" data-sort-value="<?php echo htmlspecialchars(strtolower($contact['fonction'] ?? '')); ?>">
                                                    <?php echo htmlspecialchars($contact['fonction'] ?? ''); ?>
                                                </td>
                                                <td data-label="Téléphone fixe" data-sort-value="<?php echo htmlspecialchars(strtolower($contact['phone1'] ?? '')); ?>">
                                                    <?php echo htmlspecialchars($contact['phone1'] ?? ''); ?>
                                                </td>
                                                <td data-label="Mobile" data-sort-value="<?php echo htmlspecialchars(strtolower($contact['phone2'] ?? '')); ?>">
                                                    <?php echo htmlspecialchars($contact['phone2'] ?? ''); ?>
                                                </td>
                                                <td data-label="Email" data-sort-value="<?php echo htmlspecialchars(strtolower($contact['email'] ?? '')); ?>">
                                                    <?php echo htmlspecialchars($contact['email'] ?? ''); ?>
                                                </td>
                                                <td data-label="Compte utilisateur" data-sort-value="<?php echo $contact['has_user_account'] ? '1' : '0'; ?>">
                                                    <?php if ($contact['has_user_account']): ?>
                                                        <span class="badge bg-success">Oui</span>
                                                        <?php if ($contact['user_username']): ?>
                                                            <br><small><?php echo h($contact['user_username']); ?></small>
                                                        <?php endif; ?>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Non</span>
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

            <!-- Onglet Sites -->
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
                                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#siteCollapse<?php echo $site['id']; ?>" aria-expanded="false" aria-controls="siteCollapse<?php echo $site['id']; ?>">
                                                    <?php echo h($site['name']); ?>
                                                    <span class="badge bg-info ms-2"><?php echo count($site['rooms'] ?? []); ?> salles</span>
                                                </button>
                                                <div class="me-3">
                                                    <a href="<?php echo BASE_URL; ?>qrcode/generate/site/<?php echo $site['id']; ?>" class="btn btn-sm btn-outline-primary" title="Générer les QR codes des salles">
                                                        <i class="bi bi-qr-code me-1"></i> QR Codes
                                                    </a>
                                                </div>
                                            </div>
                                        </h2>
                                        <div id="siteCollapse<?php echo $site['id']; ?>" class="accordion-collapse collapse" aria-labelledby="siteHeading<?php echo $site['id']; ?>" data-bs-parent="#sitesAccordion">
                                            <div class="accordion-body">
                                                <div class="row">
                                                    <div class="col-md-6">
                                                        <table class="table table-bordered">
                                                            <tr>
                                                                <th>Adresse</th>
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
                                                        
                                                        <!-- Contact principal du site -->
                                                        <?php if (!empty($site['primary_contact'])): ?>
                                                        <div class="card mt-3">
                                                            <div class="card-header py-2">
                                                                <h6 class="card-title mb-0">Contact principal</h6>
                                                            </div>
                                                            <div class="card-body py-2">
                                                                <div class="d-flex">
                                                                    <div class="avatar avatar-sm me-2">
                                                                        <div class="avatar-initial rounded-circle bg-label-primary">
                                                                            <?php 
                                                                            $initials = substr($site['primary_contact']['first_name'], 0, 1) . substr($site['primary_contact']['last_name'], 0, 1);
                                                                            echo strtoupper($initials);
                                                                            ?>
                                                                        </div>
                                                                    </div>
                                                                    <div class="flex-grow-1 ms-3">
                                                                        <h6 class="mb-1"><?php echo htmlspecialchars($site['primary_contact']['first_name'] . ' ' . $site['primary_contact']['last_name']); ?></h6>
                                                                        <?php if (!empty($site['primary_contact']['phone1'])) : ?>
                                                                            <p class="mb-1 small">
                                                                                <i class="<?php echo getIcon('phone', 'bi bi-telephone'); ?> me-1"></i> <?php echo htmlspecialchars($site['primary_contact']['phone1']); ?>
                                                                            </p>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($site['primary_contact']['email'])) : ?>
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

                                                <!-- Salles du site -->
                                                <?php if (!empty($site['rooms'])): ?>
                                                <div class="mt-4">
                                                    <h6 class="fw-bold mb-3">Salles</h6>
                                                    <div class="table-responsive">
                                                        <table class="table table-striped table-bordered">
                                                            <thead>
                                                                <tr>
                                                                    <th>Nom</th>
                                                                    <th>Contact principal</th>
                                                                    <th>Statut</th>
                                                                    <th>Commentaire</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                <?php foreach ($site['rooms'] as $room): ?>
                                                                <tr>
                                                                    <td><?php echo h($room['name']); ?></td>
                                                                    <td>
                                                                        <?php 
                                                                        if (!empty($room['first_name']) && !empty($room['last_name'])) {
                                                                            echo htmlspecialchars($room['first_name'] . ' ' . $room['last_name']);
                                                                        } else {
                                                                            echo '<span class="text-muted">Aucun contact</span>';
                                                                        }
                                                                        ?>
                                                                    </td>
                                                                    <td>
                                                                        <span class="badge bg-<?php echo ($room['status'] ?? 0) == 1 ? 'success' : 'danger'; ?>">
                                                                            <?php echo ($room['status'] ?? 0) == 1 ? 'Actif' : 'Inactif'; ?>
                                                                        </span>
                                                                    </td>
                                                                    <td>
                                                                        <?php 
                                                                        if (!empty($room['comment'])) {
                                                                            echo h($room['comment']);
                                                                        } else {
                                                                            echo '<span class="text-muted">Aucun commentaire</span>';
                                                                        }
                                                                        ?>
                                                                    </td>
                                                                </tr>
                                                                <?php endforeach; ?>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>
                                                <?php else: ?>
                                                <div class="alert alert-info mt-3">
                                                    <i class="bi bi-info-circle me-2 me-1"></i> Aucune salle enregistrée pour ce site.
                                                </div>
                                                <?php endif; ?>
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
                                            <th class="sortable" data-sort="name">
                                                Nom <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="type">
                                                Type de contrat <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="end_date">
                                                Date de fin <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="tickets_number">
                                                Tickets initiaux <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="tickets_remaining">
                                                Tickets restants <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                            <th class="sortable" data-sort="status">
                                                Statut <i class="bi bi-arrow-down-up sort-icon"></i>
                                            </th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($contracts as $contract): ?>
                                            <tr>
                                                <td data-label="Nom" data-sort-value="<?php echo htmlspecialchars(strtolower($contract['name'] ?? '')); ?>">
                                                    <?php 
                                                    // Déterminer l'URL selon le type de contrat
                                                    $contractName = $contract['name'] ?? '';
                                                    $isHorsContratFacturable = (strpos(strtolower($contractName), 'hors contrat facturable') !== false);
                                                    $isHorsContratNonFacturable = (strpos(strtolower($contractName), 'hors contrat non facturable') !== false);
                                                    
                                                    if ($isHorsContratFacturable) {
                                                        $viewUrl = BASE_URL . 'hors_contrat_facturable/view/' . $contract['id'];
                                                    } elseif ($isHorsContratNonFacturable) {
                                                        $viewUrl = BASE_URL . 'hors_contrat_non_facturable/view/' . $contract['id'];
                                                    } else {
                                                        $viewUrl = BASE_URL . 'contracts/view/' . $contract['id'];
                                                    }
                                                    ?>
                                                    <a href="<?php echo $viewUrl; ?>?return_to=client&client_id=<?php echo $client['id']; ?>&active_tab=contracts-tab" class="text-decoration-none fw-bold" title="Voir le contrat">
                                                        <?php echo htmlspecialchars($contract['name'] ?? '-'); ?>
                                                    </a>
                                                </td>
                                                <td data-label="Type de contrat" data-sort-value="<?php echo htmlspecialchars(strtolower($contract['contract_type_name'] ?? '')); ?>">
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
                                                        <span class="no-tickets-indicator" title="Sans tickets">
                                                            <i class="no-tickets-icon"></i>
                                                        </span>
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
                                                <td data-label="Statut" data-sort-value="<?php echo htmlspecialchars(strtolower($contract['status'])); ?>">
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
            </div>

            <!-- Onglet Interventions -->
            <div class="tab-pane fade" id="interventions" role="tabpanel" aria-labelledby="interventions-tab">
                <div class="card">
                    <div class="card-header py-2 d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Interventions</h5>
                        <?php if (!empty($interventionsGrouped)): ?>
                            <div class="btn-group" role="group" aria-label="Contrôles accordéon">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="expandAllInterventions" title="Déplier tout">
                                    <i class="bi bi-arrows-expand me-1"></i> Déplier tout
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="collapseAllInterventions" title="Replier tout">
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
                                            <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#contractCollapse<?php echo $contractId; ?>" aria-expanded="false" aria-controls="contractCollapse<?php echo $contractId; ?>">
                                                <div class="d-flex justify-content-between align-items-center w-100 me-3">
                                                    <span><?php echo h($contractGroup['contract_name']); ?></span>
                                                    <div class="d-flex gap-2">
                                                        <?php if (!empty($contractGroup['preventive'])): ?>
                                                            <span class="badge bg-success"><?php echo count($contractGroup['preventive']); ?> préventive(s)</span>
                                                        <?php endif; ?>
                                                        <?php if (!empty($contractGroup['corrective'])): ?>
                                                            <span class="badge bg-warning"><?php echo count($contractGroup['corrective']); ?> corrective(s)</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </button>
                                        </h2>
                                        <div id="contractCollapse<?php echo $contractId; ?>" class="accordion-collapse collapse" aria-labelledby="contractHeading<?php echo $contractId; ?>" data-bs-parent="#interventionsAccordion">
                                            <div class="accordion-body">
                                                <!-- Interventions Préventives -->
                                                <?php if (!empty($contractGroup['preventive'])): ?>
                                                    <div class="mb-4">
                                                        <h6 class="fw-bold text-success mb-3">
                                                            <i class="bi bi-shield-check me-2"></i>Interventions Préventives (<?php echo count($contractGroup['preventive']); ?>)
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
                                                                    <?php foreach ($contractGroup['preventive'] as $intervention): ?>
                                                                        <tr>
                                                                            <td>
                                                                                <a href="<?php echo BASE_URL; ?>interventions/view/<?php echo $intervention['id']; ?>?return_to=client&client_id=<?php echo $client['id']; ?>&active_tab=interventions-tab" class="text-decoration-none fw-bold" title="Voir l'intervention">
                                                                                    <?php echo htmlspecialchars($intervention['reference'] ?? 'INT-' . $intervention['id']); ?>
                                                                                </a>
                                                                            </td>
                                                                            <td>
                                                                                <small><?php echo htmlspecialchars($intervention['title'] ?? '-'); ?></small>
                                                                            </td>
                                                                            <td>
                                                                                <small><?php echo formatDateFrench($intervention['created_at']); ?></small>
                                                                            </td>
                                                                            <td>
                                                                                <small><?php echo htmlspecialchars($intervention['technician_name'] ?? '-'); ?></small>
                                                                            </td>
                                                                            <td>
                                                                                <small><?php echo $intervention['duration'] ?? '-'; ?>h</small>
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
                                                                                <span class="badge rounded-pill" style="background-color: <?php echo $intervention['status_color'] ?? '#6c757d'; ?>">
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

                                                <!-- Interventions Correctives -->
                                                <?php if (!empty($contractGroup['corrective'])): ?>
                                                    <div class="mb-4">
                                                        <h6 class="fw-bold text-warning mb-3">
                                                            <i class="bi bi-tools me-2"></i>Interventions Correctives (<?php echo count($contractGroup['corrective']); ?>)
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
                                                                    <?php foreach ($contractGroup['corrective'] as $intervention): ?>
                                                                        <tr>
                                                                            <td>
                                                                                <a href="<?php echo BASE_URL; ?>interventions/view/<?php echo $intervention['id']; ?>?return_to=client&client_id=<?php echo $client['id']; ?>&active_tab=interventions-tab" class="text-decoration-none fw-bold" title="Voir l'intervention">
                                                                                    <?php echo htmlspecialchars($intervention['reference'] ?? 'INT-' . $intervention['id']); ?>
                                                                                </a>
                                                                            </td>
                                                                            <td>
                                                                                <small><?php echo htmlspecialchars($intervention['title'] ?? '-'); ?></small>
                                                                            </td>
                                                                            <td>
                                                                                <small><?php echo formatDateFrench($intervention['created_at']); ?></small>
                                                                            </td>
                                                                            <td>
                                                                                <small><?php echo htmlspecialchars($intervention['technician_name'] ?? '-'); ?></small>
                                                                            </td>
                                                                            <td>
                                                                                <small><?php echo $intervention['duration'] ?? '-'; ?>h</small>
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
                                                                                <span class="badge rounded-pill" style="background-color: <?php echo $intervention['status_color'] ?? '#6c757d'; ?>">
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

                                                <?php if (empty($contractGroup['preventive']) && empty($contractGroup['corrective'])): ?>
                                                    <div class="alert alert-info">
                                                        <i class="bi bi-info-circle me-2"></i> Aucune intervention enregistrée pour ce contrat.
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle me-2"></i> Aucune intervention enregistrée pour ce client.
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
    content: "\F12C"; /* bi-arrow-up */
    opacity: 1;
}

.sortable.sort-desc .sort-icon::before {
    content: "\F12F"; /* bi-arrow-down */
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
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
    border-color: #dee2e6 !important;
}

.tab-card.active {
    border-color: #007bff !important;
    box-shadow: 0 4px 12px rgba(0,123,255,0.15);
    background-color: rgba(0,123,255,0.05);
}

.tab-card.active:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 16px rgba(0,123,255,0.2);
}
</style>

<!-- Script pour la confirmation de suppression -->
<script>
function confirmDelete(contractId, contractName) {
    if (confirm('Êtes-vous sûr de vouloir supprimer le contrat "' + contractName + '" ?')) {
        window.location.href = '<?php echo BASE_URL; ?>contracts/delete/' + contractId;
    }
}
</script>

<!-- Scripts JavaScript -->
<script>
// Initialiser BASE_URL pour JavaScript
initBaseUrl('<?php echo BASE_URL; ?>');

// Debug des données
console.log('Client:', <?php echo json_encode($client); ?>);
console.log('Sites:', <?php echo json_encode($sites); ?>);
console.log('Stats:', <?php echo json_encode($stats); ?>);
console.log('Interventions Grouped:', <?php echo json_encode($interventionsGrouped); ?>);

// Fonction pour charger les salles d'un site via AJAX si nécessaire
function loadRoomsForSite(siteId, callback) {
    if (typeof loadRooms === 'function') {
        loadRooms(siteId, null, null, callback);
    }
}

// Script de tri pour les tables
document.addEventListener('DOMContentLoaded', function() {
    // Fonction de tri générique
    function initSortableTable(tableId) {
        const table = document.getElementById(tableId);
        if (!table) return;

        let currentSortColumn = null;
        let currentSortDirection = 'asc';

        // Fonction de tri
        function sortTable(columnIndex, direction) {
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            
            rows.sort((a, b) => {
                const aValue = a.cells[columnIndex].getAttribute('data-sort-value') || a.cells[columnIndex].textContent.trim();
                const bValue = b.cells[columnIndex].getAttribute('data-sort-value') || b.cells[columnIndex].textContent.trim();
                
                // Gestion des valeurs numériques
                const aNum = parseFloat(aValue);
                const bNum = parseFloat(bValue);
                
                if (!isNaN(aNum) && !isNaN(bNum)) {
                    return direction === 'asc' ? aNum - bNum : bNum - aNum;
                }
                
                // Gestion des dates (timestamp)
                if (aValue.length === 10 && bValue.length === 10) {
                    const aDate = parseInt(aValue);
                    const bDate = parseInt(bValue);
                    if (!isNaN(aDate) && !isNaN(bDate)) {
                        return direction === 'asc' ? aDate - bDate : bDate - aDate;
                    }
                }
                
                // Tri alphabétique
                const aLower = aValue.toLowerCase();
                const bLower = bValue.toLowerCase();
                
                if (aLower < bLower) return direction === 'asc' ? -1 : 1;
                if (aLower > bLower) return direction === 'asc' ? 1 : -1;
                return 0;
            });
            
            // Réorganiser les lignes
            rows.forEach(row => tbody.appendChild(row));
        }

        // Gestionnaire d'événements pour les en-têtes triables
        table.querySelectorAll('th.sortable').forEach((header, index) => {
            header.addEventListener('click', function() {
                const sortType = this.getAttribute('data-sort');
                
                // Réinitialiser tous les en-têtes de cette table
                table.querySelectorAll('th.sortable').forEach(th => {
                    th.classList.remove('sort-asc', 'sort-desc');
                });
                
                // Déterminer la direction de tri
                let direction = 'asc';
                if (currentSortColumn === index && currentSortDirection === 'asc') {
                    direction = 'desc';
                }
                
                // Appliquer le tri
                sortTable(index, direction);
                
                // Mettre à jour l'état visuel
                this.classList.add(direction === 'asc' ? 'sort-asc' : 'sort-desc');
                
                // Mettre à jour les variables globales
                currentSortColumn = index;
                currentSortDirection = direction;
            });
        });
    }

    // Initialiser le tri pour les tables
    initSortableTable('contractsTable');
    initSortableTable('contactsTable');

    // Gestion des boutons déplier/replier tout pour les interventions
    const expandAllBtn = document.getElementById('expandAllInterventions');
    const collapseAllBtn = document.getElementById('collapseAllInterventions');
    
    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', function() {
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
        collapseAllBtn.addEventListener('click', function() {
            const accordion = document.getElementById('interventionsAccordion');
            if (accordion) {
                const collapseElements = accordion.querySelectorAll('.accordion-collapse.show');
                collapseElements.forEach(collapse => {
                    const bsCollapse = bootstrap.Collapse.getInstance(collapse);
                    if (bsCollapse) {
                        bsCollapse.hide();
                    } else {
                        // Si l'instance n'existe pas, créer une nouvelle instance et cacher
                        const newBsCollapse = new bootstrap.Collapse(collapse, {
                            show: false
                        });
                        newBsCollapse.hide();
                    }
                });
            }
        });
    }

    // Gestion des boutons déplier/replier tout pour les sites
    const expandAllSitesBtn = document.getElementById('expandAllSites');
    const collapseAllSitesBtn = document.getElementById('collapseAllSites');
    
    if (expandAllSitesBtn) {
        expandAllSitesBtn.addEventListener('click', function() {
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
        collapseAllSitesBtn.addEventListener('click', function() {
            const accordion = document.getElementById('sitesAccordion');
            if (accordion) {
                const collapseElements = accordion.querySelectorAll('.accordion-collapse.show');
                collapseElements.forEach(collapse => {
                    const bsCollapse = bootstrap.Collapse.getInstance(collapse);
                    if (bsCollapse) {
                        bsCollapse.hide();
                    } else {
                        // Si l'instance n'existe pas, créer une nouvelle instance et cacher
                        const newBsCollapse = new bootstrap.Collapse(collapse, {
                            show: false
                        });
                        newBsCollapse.hide();
                    }
                });
            }
        });
    }

    // Récupérer le paramètre active_tab de l'URL au chargement
    const urlParams = new URLSearchParams(window.location.search);
    const activeTabFromUrl = urlParams.get('active_tab');
    
    // Activer l'onglet spécifié dans l'URL si présent
    if (activeTabFromUrl) {
        const targetCard = document.getElementById(activeTabFromUrl);
        if (targetCard) {
            // Retirer la classe active de toutes les cards
            document.querySelectorAll('.tab-card').forEach(c => {
                c.classList.remove('active');
                c.style.border = '2px solid transparent';
            });
            
            // Ajouter la classe active à la card ciblée
            targetCard.classList.add('active');
            targetCard.style.border = '2px solid #007bff';
            
            // Activer le contenu de l'onglet correspondant
            const targetPane = document.querySelector(targetCard.getAttribute('data-bs-target'));
            if (targetPane) {
                document.querySelectorAll('.tab-pane').forEach(pane => {
                    pane.classList.remove('show', 'active');
                });
                targetPane.classList.add('show', 'active');
            }
        }
    }

    // Gestion des cards d'onglets
    document.querySelectorAll('.tab-card').forEach(card => {
        card.addEventListener('click', function() {
            // Retirer la classe active de toutes les cards
            document.querySelectorAll('.tab-card').forEach(c => {
                c.classList.remove('active');
                c.style.border = '2px solid transparent';
            });
            
            // Ajouter la classe active à la card cliquée
            this.classList.add('active');
            this.style.border = '2px solid #007bff';
            
            // Stocker l'onglet actif dans le localStorage
            const activeTab = this.id;
            localStorage.setItem('clientActiveTab', activeTab);
        });
    });

    // Gestion du bouton Modifier avec persistance de l'onglet
    const editBtn = document.getElementById('editClientBtn');
    if (editBtn) {
        editBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            // Récupérer l'onglet actif
            const activeTab = document.querySelector('.tab-card.active')?.id || 'info-tab';
            
            // Construire l'URL avec le paramètre de l'onglet
            const baseUrl = this.href;
            const separator = baseUrl.includes('?') ? '&' : '?';
            const newUrl = baseUrl + separator + 'active_tab=' + activeTab;
            
            // Rediriger vers la page d'édition
            window.location.href = newUrl;
        });
    }
});

function confirmDelete(clientId, clientName) {
    if (confirm('Êtes-vous sûr de vouloir supprimer le client "' + clientName + '" ?\n\nCette action est irréversible et supprimera définitivement le client et toutes ses données associées (sites, salles, matériel, contrats, etc.).')) {
        window.location.href = '<?php echo BASE_URL; ?>clients/delete/' + clientId;
    }
}
</script> 
<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de gestion des contacts du client
 * Affiche les contacts associés aux localisations autorisées
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Gestion des contacts',
    'contactClient'
);

// Définir la page courante pour le menu
$currentPage = 'contactClient';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">
                Gestion des contacts
                <?php if (isset($currentSite)): ?>
                    - <?= h($currentSite['name']) ?>
                <?php endif; ?>
                <?php if (isset($currentRoom)): ?>
                    - <?= h($currentRoom['name']) ?>
                <?php endif; ?>
            </h4>
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

    <div class="row">
        <div class="col-lg-12 mb-4 order-0">
            <div class="card">
                <div class="card-header py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Contacts</h5>
                        <div>
                            <?php if (isset($_GET['site_id'])): ?>
                                <a href="<?= BASE_URL ?>sites_client" class="btn btn-outline-secondary btn-sm me-2">
                                    <i class="bi bi-arrow-left"></i> Retour aux sites
                                </a>
                            <?php endif; ?>
                            <a href="<?= BASE_URL ?>contactClient/add<?= isset($_GET['site_id']) ? '?site_id=' . $_GET['site_id'] : '' ?><?= isset($_GET['room_id']) ? '&room_id=' . $_GET['room_id'] : '' ?>" class="btn btn-primary btn-sm">
                                <i class="bi bi-plus"></i> Ajouter un contact
                            </a>
                        </div>
                    </div>
                </div>
                <div class="card-body">

                            <?php if (empty($contacts)): ?>
                                <div class="text-center py-4">
                                    <i class="bi bi-people text-muted" style="font-size: 3rem;"></i>
                                    <p class="text-muted mt-2">Aucun contact trouvé</p>
                                    <a href="<?= BASE_URL ?>contactClient/add" class="btn btn-primary">
                                        <i class="bi bi-plus"></i> Ajouter votre premier contact
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover">
                                        <thead>
                                            <tr>
                                                <th>Nom</th>
                                                <th>Prénom</th>
                                                <th>Email</th>
                                                <th>Téléphone</th>
                                                <th>Fonction</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($contacts as $contact): ?>
                                                <tr>
                                                    <td><?= htmlspecialchars($contact['last_name'] ?? '') ?></td>
                                                    <td><?= htmlspecialchars($contact['first_name'] ?? '') ?></td>
                                                    <td>
                                                        <?php if (!empty($contact['email'])): ?>
                                                            <a href="mailto:<?= h($contact['email']) ?>">
                                                                <?= h($contact['email']) ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">Non renseigné</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php if (!empty($contact['phone1'])): ?>
                                                            <a href="tel:<?= h($contact['phone1']) ?>">
                                                                <?= h($contact['phone1']) ?>
                                                            </a>
                                                        <?php else: ?>
                                                            <span class="text-muted">Non renseigné</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?= htmlspecialchars($contact['fonction'] ?? 'Non renseignée') ?></td>
                                                    <td>
                                                        <a href="<?= BASE_URL ?>contactClient/edit/<?= $contact['id'] ?>" 
                                                           class="btn btn-sm btn-outline-primary" title="Modifier">
                                                            <i class="bi bi-pencil"></i>
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>

                </div>
            </div>
        </div>
    </div>
</div>



<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

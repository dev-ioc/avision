<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue d'ajout de contact pour le client
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Ajouter un contact',
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
            <h4 class="py-4 mb-6">Ajouter un contact</h4>
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

    <div class="row">
        <div class="col-lg-12 mb-4 order-0">
            <div class="card">
                <div class="card-header py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">Ajouter un contact</h5>
                        <a href="<?= BASE_URL ?>contactClient<?= isset($_GET['site_id']) ? '?site_id=' . $_GET['site_id'] : '' ?><?= isset($_GET['room_id']) ? '&room_id=' . $_GET['room_id'] : '' ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="bi bi-arrow-left"></i> Retour
                        </a>
                    </div>
                </div>
                <div class="card-body">

                            <form method="POST" action="<?= BASE_URL ?>contactClient/add">
                                <?= csrf_field() ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="last_name" class="form-label">Nom <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="first_name" class="form-label">Prénom <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email</label>
                                            <input type="email" class="form-control" id="email" name="email">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">Téléphone</label>
                                            <input type="tel" class="form-control" id="phone" name="phone">
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="function" class="form-label">Fonction</label>
                                            <input type="text" class="form-control" id="function" name="function" 
                                                   placeholder="ex: Responsable technique, Chef de projet...">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label class="form-label">Client</label>
                                            <div class="form-control-plaintext">
                                                <?php 
                                                // Afficher le nom du client principal de l'utilisateur
                                                $clientNames = [];
                                                foreach ($availableLocations as $location) {
                                                    $clientName = $location['client_name'];
                                                    if (!in_array($clientName, $clientNames)) {
                                                        $clientNames[] = $clientName;
                                                    }
                                                }
                                                echo htmlspecialchars(implode(', ', $clientNames));
                                                ?>
                                            </div>
                                            <small class="form-text text-muted">Le contact sera automatiquement associé à votre client</small>
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check"></i> Ajouter le contact
                                    </button>
                                    <a href="<?= BASE_URL ?>contactClient<?= isset($_GET['site_id']) ? '?site_id=' . $_GET['site_id'] : '' ?><?= isset($_GET['room_id']) ? '&room_id=' . $_GET['room_id'] : '' ?>" class="btn btn-secondary ms-2">
                                        <i class="bi bi-x"></i> Annuler
                                    </a>
                                </div>
                            </form>
                </div>
            </div>
        </div>
    </div>
</div>



<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

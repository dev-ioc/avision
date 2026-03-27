<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue d'ajout d'un site
 * Permet d'ajouter un nouveau site à un client
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Récupérer l'ID du client depuis les données du client
$clientId = isset($client['id']) ? $client['id'] : null;

setPageVariables(
    'Ajouter un Site',
    'clients'
);

// Définir la page courante pour le menu
$currentPage = 'clients';

// Définir les breadcrumbs personnalisés pour l'ajout de site
if (isset($client) && !empty($client)) {
    $GLOBALS['customBreadcrumbs'] = generateSiteAddBreadcrumbs($client);
}

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">Ajouter un Site</h4>
            <?php if (isset($client) && !empty($client['name'])): ?>
                <p class="text-muted mb-0">
                    <strong>Client :</strong> <?php echo htmlspecialchars($client['name']); ?>
                </p>
            <?php endif; ?>
        </div>

        <div class="ms-auto p-2 bd-highlight">
            <?php 
            $returnTo = $_GET['return_to'] ?? 'edit';
            $returnUrl = ($returnTo === 'view') ? 
                BASE_URL . 'clients/view/' . $clientId . '?active_tab=sites-tab' : 
                BASE_URL . 'clients/edit/' . $clientId . '#sites';
            ?>
            <a href="<?php echo $returnUrl; ?>" class="btn btn-secondary me-2">
                <i class="bi bi-arrow-left me-1"></i> Retour
            </a>
            <button type="submit" form="siteForm" class="btn btn-primary">
                <i class="<?php echo getIcon('save', 'bi bi-check-lg'); ?>"></i> Enregistrer
            </button>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])) : ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])) : ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header py-2">
            <h5 class="card-title mb-0">Informations du Site</h5>
        </div>
        <div class="card-body py-2">
            <form id="siteForm" action="<?= BASE_URL ?>site/add/<?= $clientId ?><?php echo isset($_GET['return_to']) ? '?return_to=' . $_GET['return_to'] : ''; ?>" method="POST">
                <?= csrf_field() ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3"> <!-- Remplacé form-group par mb-3 -->
                            <label for="name" class="form-label">Nom du site <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3"> <!-- Remplacé form-group par mb-3 -->
                            <label for="main_contact_id" class="form-label">Contact principal</label>
                            <select class="form-select select2" id="main_contact_id" name="main_contact_id"> <!-- Remplacé form-control par form-select pour les select -->
                                <option value="">Sélectionner un contact</option>
                                <?php if (!empty($contacts)): ?>
                                    <?php foreach ($contacts as $contact): ?>
                                        <option value="<?= $contact['id'] ?>">
                                            <?= htmlspecialchars($contact['first_name'] . ' ' . $contact['last_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3"> <!-- Remplacé form-group par mb-3 -->
                            <label for="address" class="form-label">Adresse</label>
                            <input type="text" class="form-control" id="address" name="address">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3"> <!-- Remplacé form-group par mb-3 -->
                            <label for="postal_code" class="form-label">Code postal</label>
                            <input type="text" class="form-control" id="postal_code" name="postal_code">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="mb-3"> <!-- Remplacé form-group par mb-3 -->
                            <label for="city" class="form-label">Ville</label>
                            <input type="text" class="form-control" id="city" name="city">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3"> <!-- Remplacé form-group par mb-3 -->
                            <label for="phone" class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" id="phone" name="phone">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="mb-3"> <!-- Remplacé form-group par mb-3 -->
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-12">
                        <div class="mb-3"> <!-- Remplacé form-group par mb-3 -->
                            <label for="comment" class="form-label">Commentaire</label>
                            <textarea class="form-control" id="comment" name="comment" rows="3"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Boutons de soumission retirés d'ici, car placés en haut -->
                <!-- <div class="row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Ajouter le site</button>
                        <a href="<?= BASE_URL ?>clients/edit/<?= $clientId ?>#sites" class="btn btn-secondary">Annuler</a>
                    </div>
                </div> -->
            </form>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?> 
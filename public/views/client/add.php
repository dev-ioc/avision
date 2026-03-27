<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue d'ajout d'un client
 * Permet d'ajouter un nouveau client
 */

// Vérifier si l'utilisateur est connecté et a les permissions
if (!isset($_SESSION['user']) || !canModifyClients()) {
    $_SESSION['error'] = "Vous n'avez pas les droits nécessaires pour créer un client.";
    header('Location: ' . BASE_URL . 'dashboard');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Récupération des données du formulaire en cas d'erreur
$formData = $_SESSION['form_data'] ?? [];
unset($_SESSION['form_data']);

setPageVariables(
    'Ajouter un client',
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
    <div class="row">
        <div class="col-12">
            <!-- En-tête avec actions -->
            <div class="d-flex bd-highlight mb-3">
                <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Ajouter un client</h4></div>

                <div class="ms-auto p-2 bd-highlight">
                    <a href="<?php echo BASE_URL; ?>clients" class="btn btn-secondary me-2">
                        <i class="bi bi-arrow-left me-1"></i> Retour
                    </a>
                    <button type="submit" form="clientForm" class="btn btn-primary">
                        Enregistrer
                    </button>
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

            <div class="card">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0">Informations du client</h5>
                </div>
                <div class="card-body py-2">
                    <form id="clientForm" action="<?php echo BASE_URL; ?>clients/store" method="POST">
                        <?= csrf_field() ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="name">Nom du client *</label>
                                    <input type="text" class="form-control" id="name" name="name" required 
                                           value="<?php echo htmlspecialchars($formData['name'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">Email</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo htmlspecialchars($formData['email'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone">Téléphone</label>
                                    <input type="tel" class="form-control" id="phone" name="phone"
                                           value="<?php echo htmlspecialchars($formData['phone'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="website">Site web</label>
                                    <input type="url" class="form-control" id="website" name="website"
                                           value="<?php echo htmlspecialchars($formData['website'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="address">Adresse</label>
                                    <input type="text" class="form-control" id="address" name="address"
                                           value="<?php echo htmlspecialchars($formData['address'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="postal_code">Code postal</label>
                                    <input type="text" class="form-control" id="postal_code" name="postal_code"
                                           value="<?php echo htmlspecialchars($formData['postal_code'] ?? ''); ?>">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="city">Ville</label>
                                    <input type="text" class="form-control" id="city" name="city"
                                           value="<?php echo htmlspecialchars($formData['city'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="comment">Commentaires</label>
                                    <textarea class="form-control" id="comment" name="comment" rows="3"><?php echo htmlspecialchars($formData['comment'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>

                        <?php if (isAdmin()): ?>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="status">Statut</label>
                                    <select class="form-control" id="status" name="status">
                                        <option value="1" <?php echo (isset($formData['status']) && $formData['status'] == 1) ? 'selected' : ''; ?>>Actif</option>
                                        <option value="0" <?php echo (isset($formData['status']) && $formData['status'] == 0) ? 'selected' : ''; ?>>Inactif</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 
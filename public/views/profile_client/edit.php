<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de modification du profil client
 * Permet à l'utilisateur de modifier ses informations personnelles
 */

// Vérification de l'accès
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Modifier mon profil',
    'profile_client'
);

// Définir la page courante pour le menu
$currentPage = 'profile_client';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-lg-12 mb-4 order-0">
            <div class="card">
                <div class="d-flex align-items-end row">
                    <div class="col-12">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="card-title text-primary">Modifier mon profil</h5>
                                <a href="<?= BASE_URL ?>profileClient" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left"></i> Retour
                                </a>
                            </div>

                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger alert-dismissible" role="alert">
                                    <?= $_SESSION['error'] ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                                </div>
                                <?php unset($_SESSION['error']); ?>
                            <?php endif; ?>

                            <form method="POST" action="<?= BASE_URL ?>profileClient/edit">
                                <?= csrf_field() ?>
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="last_name" class="form-label">Nom <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="last_name" name="last_name" 
                                                   value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="first_name" class="form-label">Prénom <span class="text-danger">*</span></label>
                                            <input type="text" class="form-control" id="first_name" name="first_name" 
                                                   value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="phone" class="form-label">Téléphone</label>
                                            <input type="tel" class="form-control" id="phone" name="phone" 
                                                   value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                        </div>
                                    </div>
                                </div>

                                <hr class="my-4">
                                <h6 class="text-primary mb-3">Changer le mot de passe (optionnel)</h6>
                                <p class="text-muted small">Laissez ces champs vides si vous ne souhaitez pas changer votre mot de passe.</p>

                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="current_password" class="form-label">Mot de passe actuel</label>
                                            <input type="password" class="form-control" id="current_password" name="current_password">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="new_password" class="form-label">Nouveau mot de passe</label>
                                            <input type="password" class="form-control" id="new_password" name="new_password" 
                                                   minlength="8">
                                            <div class="form-text">Minimum 8 caractères</div>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="confirm_password" class="form-label">Confirmer le nouveau mot de passe</label>
                                            <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                                        </div>
                                    </div>
                                </div>

                                <div class="mt-4">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-check"></i> Enregistrer les modifications
                                    </button>
                                    <a href="<?= BASE_URL ?>profileClient" class="btn btn-secondary ms-2">
                                        <i class="bi bi-x"></i> Annuler
                                    </a>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validation côté client pour les mots de passe
    const currentPassword = document.getElementById('current_password');
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const form = document.querySelector('form');

    form.addEventListener('submit', function(e) {
        // Vérifier si au moins un champ de mot de passe est rempli
        const hasPasswordField = currentPassword.value || newPassword.value || confirmPassword.value;
        
        if (hasPasswordField) {
            // Si un champ est rempli, tous doivent l'être
            if (!currentPassword.value || !newPassword.value || !confirmPassword.value) {
                e.preventDefault();
                alert('Si vous souhaitez changer votre mot de passe, tous les champs de mot de passe sont requis.');
                return;
            }

            // Vérifier que les nouveaux mots de passe correspondent
            if (newPassword.value !== confirmPassword.value) {
                e.preventDefault();
                alert('Le nouveau mot de passe et sa confirmation ne correspondent pas.');
                return;
            }

            // Vérifier la longueur du nouveau mot de passe
            if (newPassword.value.length < 8) {
                e.preventDefault();
                alert('Le nouveau mot de passe doit contenir au moins 8 caractères.');
                return;
            }
        }
    });
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

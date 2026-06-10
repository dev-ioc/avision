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
<?php
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables('Modifier mon profil', 'profile_client');
$currentPage = 'profile_client';

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- HEADER -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-0">Modifier mon profil</h4>
            <small class="text-muted">Mettez à jour vos informations personnelles</small>
        </div>

        <a href="<?= BASE_URL ?>profileClient" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left"></i> Retour
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $_SESSION['error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <form method="POST" action="<?= BASE_URL ?>profileClient/edit">
        <?= csrf_field() ?>

        <div class="row g-4">

            <!-- INFOS PROFIL -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">

                        <h6 class="text-primary mb-3">
                            <i class="bi bi-person"></i> Informations personnelles
                        </h6>

                        <div class="mb-3">
                            <label class="form-label">Nom *</label>
                            <input type="text" class="form-control" name="last_name"
                                value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Prénom *</label>
                            <input type="text" class="form-control" name="first_name"
                                value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email"
                                value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Téléphone</label>
                            <input type="tel" class="form-control" name="phone"
                                value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                        </div>

                    </div>
                </div>
            </div>

            <!-- MOT DE PASSE -->
            <div class="col-lg-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">

                        <h6 class="text-primary mb-3">
                            <i class="bi bi-shield-lock"></i> Sécurité
                        </h6>

                        <p class="text-muted small mb-3">
                            Laissez vide si vous ne souhaitez pas modifier votre mot de passe
                        </p>

                        <div class="mb-3">
                            <label class="form-label">Mot de passe actuel</label>
                            <input type="password" class="form-control" name="current_password">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nouveau mot de passe</label>
                            <input type="password" class="form-control" name="new_password" minlength="8">
                            <div class="form-text">Minimum 8 caractères</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirmer le mot de passe</label>
                            <input type="password" class="form-control" name="confirm_password">
                        </div>

                    </div>
                </div>
            </div>

        </div>

        <!-- ACTIONS -->
        <div class="mt-4 d-flex justify-content-end">
            <a href="<?= BASE_URL ?>profileClient" class="btn btn-light me-2">
                Annuler
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="bi bi-check"></i> Enregistrer
            </button>
        </div>

    </form>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('form');

        form.addEventListener('submit', function (e) {
            const current = form.querySelector('[name="current_password"]').value;
            const newPass = form.querySelector('[name="new_password"]').value;
            const confirm = form.querySelector('[name="confirm_password"]').value;

            const hasPassword = current || newPass || confirm;

            if (hasPassword) {
                if (!current || !newPass || !confirm) {
                    e.preventDefault();
                    alert('Tous les champs de mot de passe sont requis.');
                    return;
                }

                if (newPass !== confirm) {
                    e.preventDefault();
                    alert('Les mots de passe ne correspondent pas.');
                    return;
                }

                if (newPass.length < 8) {
                    e.preventDefault();
                    alert('Minimum 8 caractères.');
                    return;
                }
            }
        });
    });
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>


<script>
    document.addEventListener('DOMContentLoaded', function () {
        // Validation côté client pour les mots de passe
        const currentPassword = document.getElementById('current_password');
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');
        const form = document.querySelector('form');

        form.addEventListener('submit', function (e) {
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
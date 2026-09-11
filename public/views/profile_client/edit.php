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

setPageVariables('Modifier mon profil', 'profile_client');

// Définir la page courante pour le menu
$currentPage = 'profile_client';

// Inclure le header qui contient le menu latéral
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
                            <div class="input-group">
                                <input type="password" class="form-control" name="current_password"
                                    id="current_password">
                                <button class="btn btn-outline-secondary" type="button"
                                    onclick="togglePasswordVisibility('current_password', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Nouveau mot de passe</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="new_password" minlength="8"
                                    id="new_password">
                                <button class="btn btn-outline-secondary" type="button"
                                    onclick="togglePasswordVisibility('new_password', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                            <div class="form-text">Minimum 8 caractères</div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Confirmer le mot de passe</label>
                            <div class="input-group">
                                <input type="password" class="form-control" name="confirm_password"
                                    id="confirm_password">
                                <button class="btn btn-outline-secondary" type="button"
                                    onclick="togglePasswordVisibility('confirm_password', this)">
                                    <i class="bi bi-eye"></i>
                                </button>
                            </div>
                        </div>
                        <!-- 2FA -->
                        <!-- <div class="mb-3">
                            <div class="card border-0 shadow-sm">
                                <div class="card-body">
                                    <h6 class="text-primary mb-3">
                                        <i class="bi bi-shield-check"></i> Double authentification (2FA)
                                    </h6>

                                    <?php if (!empty($user['totp_enabled'])): ?>
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                            <div>
                                                <span class="badge bg-success mb-1">
                                                    <i class="bi bi-shield-check"></i> Activée
                                                </span>
                                                <p class="text-muted small mb-0">
                                                    Votre compte est protégé par une double authentification.
                                                </p>
                                            </div>
                                            <button type="button" class="btn btn-outline-danger btn-sm"
                                                data-bs-toggle="modal" data-bs-target="#disable2faModal">
                                                Désactiver
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
                                            <div>
                                                <span class="badge bg-light text-muted border mb-1">Désactivée</span>
                                                <p class="text-muted small mb-0">
                                                    Ajoutez une couche de sécurité supplémentaire à votre compte.
                                                </p>
                                            </div>
                                            <a href="<?= BASE_URL ?>auth/setup-2fa" class="btn btn-primary btn-sm">
                                                Activer la 2FA
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div> -->
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

<?php if (!empty($user['totp_enabled'])): ?>
    <div class="modal fade" id="disable2faModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" action="<?= BASE_URL ?>auth/disable-2fa">
                    <?= csrf_field() ?>
                    <div class="modal-header">
                        <h5 class="modal-title">Désactiver la double authentification</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <p class="mb-2">Pour confirmer, saisissez votre mot de passe actuel.</p>
                        <input type="password" name="password" class="form-control" required autocomplete="current-password"
                            placeholder="Mot de passe">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">Désactiver</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- Modale d'erreur stylée -->
<div class="modal fade" id="errorModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header border-0 pb-0">
                <div class="w-100 text-center">
                    <div class="mx-auto mb-2 d-flex align-items-center justify-content-center rounded-circle bg-danger bg-opacity-10"
                        style="width: 64px; height: 64px;">
                        <i class="bi bi-exclamation-triangle-fill text-danger fs-3"></i>
                    </div>
                </div>
                <button type="button" class="btn-close position-absolute top-0 end-0 m-3" data-bs-dismiss="modal"
                    aria-label="Fermer"></button>
            </div>
            <div class="modal-body text-center pt-0">
                <h5 class="mb-2">Erreur</h5>
                <p class="text-muted mb-0" id="errorModalMessage"></p>
            </div>
            <div class="modal-footer border-0 justify-content-center pt-0">
                <button type="button" class="btn btn-danger px-4" data-bs-dismiss="modal">Compris</button>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const form = document.querySelector('form');
        const currentPassword = document.getElementById('current_password');
        const newPassword = document.getElementById('new_password');
        const confirmPassword = document.getElementById('confirm_password');

        if (!form || !currentPassword || !newPassword || !confirmPassword) {
            console.warn('Un ou plusieurs éléments requis pour la validation du mot de passe sont introuvables.');
            return;
        }

        function showErrorModal(message) {
            document.getElementById('errorModalMessage').textContent = message;
            const modal = new bootstrap.Modal(document.getElementById('errorModal'));
            modal.show();
        }

        form.addEventListener('submit', function (e) {
            const hasPasswordField = currentPassword.value || newPassword.value || confirmPassword.value;

            if (hasPasswordField) {
                if (newPassword.value !== confirmPassword.value) {
                    e.preventDefault();
                    showErrorModal('Le nouveau mot de passe et sa confirmation ne correspondent pas.');
                    return;
                }

                if (newPassword.value.length < 8 && newPassword.value !== "") {
                    e.preventDefault();
                    showErrorModal('Le nouveau mot de passe doit contenir au moins 8 caractères.');
                    return;
                }
            }
        });
    });
</script>
<script>
    function togglePasswordVisibility(inputId, btn) {
        const input = document.getElementById(inputId);
        const icon = btn.querySelector('i');

        if (input.type === 'password') {
            input.type = 'text';
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        } else {
            input.type = 'password';
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
    }
</script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.modal').forEach(function (modal) {
            modal.addEventListener('hidden.bs.modal', function () {
                const dialog = modal.querySelector('.modal-dialog');
                if (dialog) {
                    dialog.style.position = '';
                    dialog.style.left = '';
                    dialog.style.top = '';
                    dialog.style.margin = '';
                    dialog.style.width = '';
                    dialog.style.maxWidth = '';
                }
            });

            modal.addEventListener('shown.bs.modal', function () {
                const dialog = modal.querySelector('.modal-dialog');
                const header = modal.querySelector('.modal-header');
                if (!dialog || !header) return;
                if (header.dataset.draggable) return;
                header.dataset.draggable = 'true';

                header.style.cursor = 'grab';

                let isDragging = false;
                let startX, startY, startLeft, startTop;

                header.addEventListener('mousedown', function (e) {
                    if (e.target.closest('button')) return;

                    isDragging = true;
                    header.style.cursor = 'grabbing';

                    const rect = dialog.getBoundingClientRect();
                    startX = e.clientX;
                    startY = e.clientY;
                    startLeft = rect.left;
                    startTop = rect.top;
                    dialog.style.width = rect.width + 'px';
                    dialog.style.maxWidth = 'none';
                    dialog.style.position = 'fixed';
                    dialog.style.left = startLeft + 'px';
                    dialog.style.top = startTop + 'px';
                    dialog.style.margin = '0';
                });

                document.addEventListener('mousemove', function (e) {
                    if (!isDragging) return;
                    const dx = e.clientX - startX;
                    const dy = e.clientY - startY;
                    dialog.style.left = (startLeft + dx) + 'px';
                    dialog.style.top = (startTop + dy) + 'px';
                });

                document.addEventListener('mouseup', function () {
                    if (isDragging) {
                        isDragging = false;
                        header.style.cursor = 'grab';
                    }
                });
            });

        });
    });
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
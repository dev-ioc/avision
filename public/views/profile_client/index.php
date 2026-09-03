<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue du profil client
 * Affiche les informations du profil de l'utilisateur connecté
 */

// Vérification de l'accès
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Mon Profil',
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
    <div class="card mb-4 shadow-sm border-0">
        <div class="card-body d-flex justify-content-between align-items-center">

            <div class="d-flex align-items-center">
                <!-- Avatar -->
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center me-3"
                    style="width:60px;height:60px;font-size:24px;">
                    <?= strtoupper(substr($user['first_name'] ?? 'U', 0, 1)) ?>
                </div>

                <div>
                    <h5 class="mb-0">
                        <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
                    </h5>
                    <small class="text-muted">
                        <?= htmlspecialchars($user['email'] ?? '') ?>
                    </small>
                </div>
            </div>

            <div>
                <?php if (canModifyOwnInfo()): ?>
                    <a href="<?= BASE_URL ?>profileClient/edit" class="btn btn-primary me-2">
                        <i class="bi bi-pencil"></i> Modifier
                    </a>
                <?php endif; ?>

            </div>
        </div>

        <!-- ALERTES -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <?= $_SESSION['success'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show">
                <?= $_SESSION['error'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- INFOS -->
        <div class="row g-4">

            <!-- Infos personnelles -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">

                        <h6 class="mb-3 text-primary">
                            <i class="bi bi-person"></i> Informations personnelles
                        </h6>

                        <div class="mb-3">
                            <label class="text-muted small">Nom</label>
                            <div class="fw-semibold">

                                <?= htmlspecialchars($user['last_name'] ?? '') ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted 
                            small">Prénom</label>
                            <div class="fw-semibold">
                                <?= htmlspecialchars($user['first_name'] ?? '') ?>
                            </div>
                        </div>

                        <!-- <div class="mb-3">
                            <label class="text-muted small">Nom d'utilisateur</label>
                            <div class="fw-semibold">
                                <?= htmlspecialchars($user['first_name'] ?? '') ?>
                            </div>
                        </div> -->

                    </div>
                </div>
            </div>

            <!-- Contact -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">

                        <h6 class="mb-3 text-primary">
                            <i class="bi bi-envelope"></i> Contact
                        </h6>

                        <div class="mb-3">
                            <label class="text-muted small">Email (Login)</label>
                            <div class="fw-semibold">
                                <?= htmlspecialchars($user['email'] ?? '') ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="text-muted small">Téléphone</label>
                            <div class="fw-semibold">
                                <?= htmlspecialchars($user['phone'] ?? 'Non renseigné') ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Sécurité / Double authentification -->
            <!-- <div class="col-lg-12">
                <div class="card shadow-sm border-0 h-100">
                    <div class="card-body">

                        <h6 class="mb-3 text-primary">
                            <i class="bi bi-shield-lock"></i> Sécurité
                        </h6>

                        <?php $totpEnabled = !empty($_SESSION['user']['totp_enabled']); ?>

                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <div class="fw-semibold">Double authentification</div>
                                <small class="text-muted">
                                    <?php if ($totpEnabled): ?>
                                        <span class="badge bg-success">Activée</span>
                                        Un code de votre application d'authentification vous sera demandé à chaque
                                        connexion.
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Désactivée</span>
                                        Ajoutez une protection supplémentaire avec un QR code à scanner.
                                    <?php endif; ?>
                                </small>
                            </div>

                            <div>
                                <?php if ($totpEnabled): ?>
                                    <button type="button" class="btn btn-outline-danger btn-sm" data-bs-toggle="modal"
                                        data-bs-target="#disable2faModal">
                                        Désactiver
                                    </button>
                                <?php else: ?>
                                    <a href="<?= BASE_URL ?>auth/setup-2fa" class="btn btn-outline-primary btn-sm">
                                        Activer la 2FA
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div>
                </div>
            </div> -->
            <!-- <div class="card shadow-sm mt-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-3">
                        <h2 class="h5 mb-0">Mes passkeys</h2>
                        <button type="button" class="btn btn-sm btn-primary" onclick="registerPasskey(this)">
                            + Ajouter une passkey
                        </button>
                    </div>
                    <p class="text-muted small">Connectez-vous sans mot de passe grâce à Face ID, Touch ID ou une
                        clé de sécurité.</p>

                    <ul id="passkey-list" class="list-group">
                        <!-- rempli en JS 
                    </ul>
                    <p id="passkey-empty" class="text-muted small d-none">Aucune passkey enregistrée.</p>
                </div>
            </div> -->

            <?= csrf_field() ?>
            <script>const BASE_URL = <?php echo json_encode(BASE_URL); ?>;</script>
            <script src="<?php echo BASE_URL; ?>assets/js/webauthn.js"></script>
            <script>
                async function loadPasskeys() {
                    const resp = await fetch(BASE_URL + 'auth/webauthn-credentials', {
                        headers: { 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const data = await resp.json();
                    const list = document.getElementById('passkey-list');
                    const empty = document.getElementById('passkey-empty');
                    list.innerHTML = '';

                    if (!data.success || data.credentials.length === 0) {
                        empty.classList.remove('d-none');
                        return;
                    }
                    empty.classList.add('d-none');

                    data.credentials.forEach(cred => {
                        const li = document.createElement('li');
                        li.className = 'list-group-item d-flex justify-content-between align-items-center';
                        li.innerHTML = `
            <span>
                <strong>${cred.name || 'Appareil sans nom'}</strong>
                <br><small class="text-muted">Ajoutée le ${new Date(cred.created_at).toLocaleDateString('fr-FR')}</small>
            </span>
            <button class="btn btn-sm btn-outline-danger">Supprimer</button>
        `;
                        li.querySelector('button').onclick = () => deletePasskey(cred.id, li);
                        list.appendChild(li);
                    });
                }
                loadPasskeys();
            </script>
        </div>


    </div>
</div>

<?php if (!empty($_SESSION['user']['totp_enabled'])): ?>
    <!-- Modal de confirmation pour désactiver la 2FA -->
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
                        <p class="text-muted">Pour confirmer, veuillez saisir votre mot de passe actuel.</p>
                        <div class="mb-3">
                            <label for="disable2fa_password" class="form-label">Mot de passe</label>
                            <input type="password" class="form-control" id="disable2fa_password" name="password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuler</button>
                        <button type="submit" class="btn btn-danger">Désactiver la 2FA</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
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
<?php
require_once __DIR__ . '/../../includes/functions.php';

if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables('Mon Profil', 'profile_client');
$currentPage = 'profile_client';

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
                            <label class="text-muted small">Email</label>
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

        </div>


    </div>
</div>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
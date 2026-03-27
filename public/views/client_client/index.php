<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue des sites et salles du client
 * Affiche les sites et salles associés au client connecté
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Mes Sites et Salles',
    'sites_client'
);

// Définir la page courante pour le menu
$currentPage = 'sites_client';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer les données depuis le contrôleur
$sites = $sites ?? [];
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">Mes Sites et Salles</h4>
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

    <?php if (!empty($sites)): ?>
        <!-- Liste des sites -->
        <div class="row">
            <?php foreach ($sites as $site): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0"><?php echo h($site['name']); ?></h5>
                                <span class="badge bg-primary">
                                    <?php echo count($site['rooms'] ?? []); ?> salles
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <strong>Client :</strong> <?php echo h($site['client_name']); ?>
                            </div>
                            
                            <div class="mb-3">
                                <strong>Adresse :</strong><br>
                                <?php echo htmlspecialchars($site['address'] ?? ''); ?><br>
                                <?php echo htmlspecialchars($site['postal_code'] ?? ''); ?> <?php echo htmlspecialchars($site['city'] ?? ''); ?>
                            </div>

                            <?php if (!empty($site['phone']) || !empty($site['email'])): ?>
                                <div class="mb-3">
                                    <?php if (!empty($site['phone'])): ?>
                                        <div><strong>Téléphone :</strong> <?php echo h($site['phone']); ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($site['email'])): ?>
                                        <div><strong>Email :</strong> <?php echo h($site['email']); ?></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Contact principal du site -->
                            <?php if (!empty($site['primary_contact'])): ?>
                                <div class="mb-3">
                                    <strong>Contact principal :</strong><br>
                                    <div class="d-flex align-items-center mt-1">
                                        <div class="avatar avatar-sm me-2">
                                            <div class="avatar-initial rounded-circle bg-label-primary">
                                                <?php 
                                                $initials = substr($site['primary_contact']['first_name'], 0, 1) . substr($site['primary_contact']['last_name'], 0, 1);
                                                echo strtoupper($initials);
                                                ?>
                                            </div>
                                        </div>
                                        <div>
                                            <?php echo htmlspecialchars($site['primary_contact']['first_name'] . ' ' . $site['primary_contact']['last_name']); ?>
                                            <?php if (!empty($site['primary_contact']['phone1'])): ?>
                                                <br><small><i class="bi bi-telephone me-1"></i><?php echo htmlspecialchars($site['primary_contact']['phone1']); ?></small>
                                            <?php endif; ?>
                                            <?php if (!empty($site['primary_contact']['email'])): ?>
                                                <br><small><i class="bi bi-envelope me-1"></i><?php echo htmlspecialchars($site['primary_contact']['email']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Liste des salles -->
                            <?php if (!empty($site['rooms'])): ?>
                                <div class="mb-3">
                                    <strong>Salles :</strong>
                                    <div class="mt-2">
                                        <?php foreach ($site['rooms'] as $room): ?>
                                            <div class="d-flex justify-content-between align-items-center py-1 border-bottom">
                                                <div class="d-flex align-items-center">
                                                    <span><?php echo h($room['name']); ?></span>
                                                </div>
                                                <span class="badge bg-<?php echo ($room['status'] ?? 0) == 1 ? 'success' : 'danger'; ?>">
                                                    <?php echo ($room['status'] ?? 0) == 1 ? 'Actif' : 'Inactif'; ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($site['comment'])): ?>
                                <div class="mb-3">
                                    <strong>Commentaire :</strong><br>
                                    <small class="text-muted"><?php echo nl2br(h($site['comment'])); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="<?php echo BASE_URL; ?>sites_client/view/<?php echo $site['id']; ?>" class="btn btn-primary btn-sm">
                                    <i class="bi bi-eye me-1"></i>Voir les détails
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Aucun site associé à votre compte pour le moment.
        </div>
    <?php endif; ?>
</div>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?>

<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue des contrats du client
 * Affiche les contrats associés aux localisations autorisées
 */

// Vérifier si l'utilisateur est connecté et est un client
if (!isset($_SESSION['user']) || !isClient()) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Mes contrats',
    'contracts_client'
);

// Définir la page courante pour le menu
$currentPage = 'contracts_client';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer les données depuis le contrôleur
$contracts = $contracts ?? [];
$stats = $stats ?? [];
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">Mes contrats</h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <a href="<?php echo BASE_URL; ?>dashboard" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Retour au tableau de bord
            </a>
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

    <!-- Statistiques rapides -->
    <?php if (!empty($stats)): ?>
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Total contrats</h6>
                                <h2 class="mb-0"><?= $stats['total'] ?? 0 ?></h2>
                            </div>
                            <i class="bi bi-file-text fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Contrats actifs</h6>
                                <h2 class="mb-0"><?= $stats['active_count'] ?? 0 ?></h2>
                            </div>
                            <i class="bi bi-check-circle fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="mb-0">Tickets restants</h6>
                                <h2 class="mb-0"><?= $stats['remaining_tickets'] ?? 0 ?></h2>
                            </div>
                            <i class="bi bi-ticket-perforated fs-1"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($contracts)): ?>
        <div class="row">
            <?php foreach ($contracts as $contract): ?>
                <div class="col-md-6 col-lg-4 mb-4">
                    <div class="card h-100">
                        <div class="card-header py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <h5 class="card-title mb-0"><?php echo h($contract['name']); ?></h5>
                                <span
                                    class="badge bg-<?php echo ($contract['status'] ?? 'inactif') === 'actif' ? 'success' : 'danger'; ?>">
                                    <?php echo ($contract['status'] ?? 'inactif') === 'actif' ? 'Actif' : 'Inactif'; ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Type de contrat -->
                            <div class="mb-2">
                                <strong>Type :</strong>
                                <span
                                    class="badge bg-info"><?php echo h($contract['contract_type_name'] ?? 'Standard'); ?></span>
                            </div>

                            <!-- Période -->
                            <div class="mb-2">
                                <strong>Période :</strong><br>
                                <?php
                                $startDate = !empty($contract['start_date']) ? date('d/m/Y', strtotime($contract['start_date'])) : 'Non définie';
                                $endDate = !empty($contract['end_date']) ? date('d/m/Y', strtotime($contract['end_date'])) : 'Non définie';
                                echo $startDate . ' → ' . $endDate;
                                ?>
                            </div>

                            <!-- Tickets -->
                            <?php if (!empty($contract['tickets_number']) && $contract['tickets_number'] > 0): ?>
                                <div class="mb-2">
                                    <strong>Tickets :</strong>
                                    <div class="progress mt-1" style="height: 20px;">
                                        <?php
                                        $percentage = ($contract['tickets_remaining'] / $contract['tickets_number']) * 100;
                                        $progressColor = $percentage < 20 ? 'bg-danger' : ($percentage < 50 ? 'bg-warning' : 'bg-success');
                                        ?>
                                        <div class="progress-bar <?= $progressColor ?>" role="progressbar"
                                            style="width: <?= $percentage ?>%;"
                                            aria-valuenow="<?= $contract['tickets_remaining'] ?>" aria-valuemin="0"
                                            aria-valuemax="<?= $contract['tickets_number'] ?>">
                                            <?= $contract['tickets_remaining'] ?> / <?= $contract['tickets_number'] ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Montant -->
                            <?php if (!empty($contract['tarif']) && $contract['tarif'] > 0): ?>
                                <div class="mb-2">
                                    <strong>Montant :</strong>
                                    <?= formatAmount($contract['tarif']) ?>
                                </div>
                            <?php endif; ?>

                            <!-- Localisations (salles associées) -->
                            <?php if (!empty($contract['rooms'])): ?>
                                <div class="mb-2">
                                    <strong>Localisations couvertes :</strong>
                                    <div class="mt-1">
                                        <?php
                                        $locations = [];
                                        foreach ($contract['rooms'] as $room) {
                                            $location = '';
                                            if (!empty($room['site_name']))
                                                $location .= $room['site_name'];
                                            if (!empty($room['building_name']))
                                                $location .= ' → ' . $room['building_name'];
                                            if (!empty($room['name']))
                                                $location .= ' → ' . $room['name'];
                                            $locations[] = $location;
                                        }
                                        $uniqueLocations = array_unique($locations);
                                        ?>
                                        <?php foreach ($uniqueLocations as $location): ?>
                                            <div class="small text-muted">
                                                <i class="bi bi-geo-alt me-1"></i> <?= h($location) ?>
                                            </div>
                                        <?php endforeach; ?>
                                        <?php if (count($locations) > 3): ?>
                                            <div class="small text-muted mt-1">
                                                <i class="bi bi-plus-circle me-1"></i> +<?= count($locations) - 3 ?> autre(s)
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Interventions associées -->
                            <?php if (!empty($contract['interventions_count']) && $contract['interventions_count'] > 0): ?>
                                <div class="mb-2">
                                    <strong>Interventions :</strong>
                                    <span class="badge bg-secondary"><?= $contract['interventions_count'] ?> intervention(s)</span>
                                    <?php if (!empty($contract['closed_interventions_count'])): ?>
                                        <span class="badge bg-success"><?= $contract['closed_interventions_count'] ?> terminée(s)</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <!-- Pièces jointes -->
                            <?php if (!empty($contract['attachments_count']) && $contract['attachments_count'] > 0): ?>
                                <div class="mb-2">
                                    <strong>Pièces jointes :</strong>
                                    <span class="badge bg-info"><?= $contract['attachments_count'] ?> fichier(s)</span>
                                </div>
                            <?php endif; ?>

                            <!-- Commentaire -->
                            <?php if (!empty($contract['comment'])): ?>
                                <div class="mb-2">
                                    <strong>Commentaire :</strong><br>
                                    <small class="text-muted"><?php echo nl2br(h($contract['comment'])); ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="card-footer">
                            <div class="d-flex justify-content-between align-items-center">
                                <a href="<?php echo BASE_URL; ?>contracts_client/view/<?php echo $contract['id']; ?>"
                                    class="btn btn-primary btn-sm">
                                    <i class="bi bi-eye me-1"></i> Voir les détails
                                </a>
                                <?php if (!empty($contract['tickets_remaining']) && $contract['tickets_remaining'] > 0): ?>
                                    <a href="<?php echo BASE_URL; ?>interventions_client/add?contract_id=<?php echo $contract['id']; ?>"
                                        class="btn btn-success btn-sm">
                                        <i class="bi bi-plus-circle me-1"></i> Créer intervention
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            <i class="bi bi-info-circle me-2"></i>
            Aucun contrat associé à votre compte pour le moment.
        </div>
    <?php endif; ?>
</div>

<style>
    .progress {
        border-radius: 10px;
        background-color: #e9ecef;
    }

    .progress-bar {
        border-radius: 10px;
        line-height: 20px;
        font-size: 0.75rem;
        font-weight: bold;
    }

    .card-header .badge {
        font-size: 0.7rem;
    }

    .card-footer .btn-sm {
        font-size: 0.75rem;
    }
</style>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
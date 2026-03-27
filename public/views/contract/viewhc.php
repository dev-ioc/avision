<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de détail du contrat hors contrat
 * Affiche les informations simplifiées d'un contrat hors contrat
 */

// Vérification de l'accès - seuls les utilisateurs connectés peuvent voir les contrats
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;
$isAdmin = isAdmin();

// Récupérer l'ID du contrat depuis l'URL
$contractId = isset($contract['id']) ? $contract['id'] : '';

setPageVariables(
    'Contrat Hors Contrat',
    'hors_contrat' . ($contractId ? '_view_' . $contractId : '')
);

// Définir la page courante pour le menu
$currentPage = 'hors_contrat';

// Déterminer l'URL de retour dynamiquement
$returnTo = $_GET['return_to'] ?? null;
$clientId = $_GET['client_id'] ?? null;
$activeTab = $_GET['active_tab'] ?? null;

if ($returnTo === 'client' && $clientId) {
    // Si on vient de la vue du client, retourner à cette vue avec l'onglet actif
    $returnUrl = BASE_URL . 'clients/view/' . $clientId;
    if ($activeTab) {
        $returnUrl .= '?active_tab=' . $activeTab;
    }
} else {
    // Par défaut, retourner à la liste des contrats hors contrat selon le type
    $contractName = $contract['name'] ?? '';
    $isHorsContratFacturable = (strpos(strtolower($contractName), 'hors contrat facturable') !== false);
    $isHorsContratNonFacturable = (strpos(strtolower($contractName), 'hors contrat non facturable') !== false);
    
    if ($isHorsContratFacturable) {
        $returnUrl = BASE_URL . 'hors_contrat_facturable';
    } elseif ($isHorsContratNonFacturable) {
        $returnUrl = BASE_URL . 'hors_contrat_non_facturable';
    } else {
        $returnUrl = BASE_URL . 'hors_contrat_facturable'; // Par défaut
    }
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
            <h4 class="py-4 mb-6">Détail du Contrat Hors Contrat</h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <a href="<?php echo $returnUrl; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left"></i> Retour
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

    <!-- Informations du contrat -->
    <div class="row">
        <div class="col-12">
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-file-text me-2"></i>
                        <?php echo htmlspecialchars($contract['name'] ?? 'Contrat sans nom'); ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Client :</strong> <?php echo htmlspecialchars($client['name'] ?? '-'); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Nombre d'interventions :</strong> 
                                <span class="badge bg-info">
                                    <?php echo count($interventions); ?>
                                </span>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Liste des interventions -->
    <div class="card mb-4">
        <div class="card-header py-2">
            <h5 class="card-title mb-0">
                <i class="bi bi-tools me-1 me-1"></i>
                Interventions associées
            </h5>
        </div>
        <div class="card-body py-2">
            <div class="table-responsive">
                <table class="table table-bordered table-striped">
                    <thead>
                        <tr>
                            <th>Référence</th>
                            <th>Titre</th>
                            <th>Date</th>
                            <th>Technicien</th>
                            <th>Durée</th>
                            <th>Tickets utilisés</th>
                            <th>Statut</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($interventions)): ?>
                            <?php foreach ($interventions as $intervention): ?>
                            <tr>
                                <td>
                                    <a href="<?= BASE_URL ?>interventions/view/<?= $intervention['id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($intervention['reference'] ?? '') ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($intervention['title'] ?? '') ?></td>
                                <td><?= !empty($intervention['date_planif']) ? date('d/m/Y', strtotime($intervention['date_planif'])) . (!empty($intervention['heure_planif']) ? ' ' . $intervention['heure_planif'] : '') : date('d/m/Y H:i', strtotime($intervention['created_at'])) ?></td>
                                <td><?= htmlspecialchars($intervention['technician_name'] ?? '') ?></td>
                                <td><?= $intervention['duration'] ?? 0 ?>h</td>
                                <td><?= $intervention['tickets_used'] ?? 0 ?></td>
                                <td>
                                    <span class="badge" style="background-color: <?= $intervention['status_color'] ?? '#6c757d' ?>">
                                        <?= htmlspecialchars($intervention['status_name'] ?? 'Non défini') ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">Aucune intervention associée</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?>

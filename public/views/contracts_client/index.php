<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de la liste des contrats client
 * Affiche la liste des contrats accessibles selon les localisations autorisées
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Mes Contrats',
    'contracts_client'
);

// Définir la page courante pour le menu
$currentPage = 'contracts_client';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <h4 class="py-4 mb-6">Mes Contrats</h4>

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

    <div class="table-responsive">
        <table id="contractsTable" class="table table-striped table-hover dt-responsive">
            <thead>
                <tr>
                    <th>Client</th>
                    <th>Type de contrat</th>
                    <th>Nom</th>
                    <th>Date de fin</th>
                    <th>Tickets restants</th>
                    <th>Pièces jointes</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($contracts)): ?>
                    <?php foreach ($contracts as $contract): ?>
                        <tr>
                            <td data-label="Client"><?= h($contract['client_name'] ?? '-') ?></td>
                            <td data-label="Type de contrat"><?= h($contract['contract_type_name'] ?? '-') ?></td>
                            <td data-label="Nom">
                                <a href="<?= BASE_URL ?>contracts_client/view/<?= $contract['id']; ?>" class="text-decoration-none">
                                    <?= h($contract['name'] ?? '-') ?>
                                </a>
                            </td>
                                            <td data-label="Date de fin" data-order="<?= strtotime($contract['end_date']); ?>">
                    <?= formatDateFrench($contract['end_date']); ?>
                            </td>
                            <td data-label="Tickets restants" data-order="<?= $contract['tickets_remaining']; ?>">
                                <?php if (isContractTicketById($contract['id'])): ?>
                                    <span class="badge bg-<?= $contract['tickets_remaining'] > 3 ? 'success' : 'danger'; ?>">
                                        <?= $contract['tickets_remaining']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">Pas de tickets</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Pièces jointes">
                                <?php if ($contract['attachments_count'] > 0): ?>
                                    <span class="badge bg-info">
                                        <i class="bi bi-paperclip me-1"></i><?= $contract['attachments_count']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Statut">
                                <span class="badge bg-<?= $contract['status'] === 'actif' ? 'success' : 'danger'; ?>">
                                    <?= ucfirst($contract['status']); ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- DataTable Persistence -->
<script src="<?= BASE_URL ?>assets/js/datatable-persistence.js"></script>

<!-- Page JS -->
<script src="<?= BASE_URL ?>assets/js/contracts-datatable.js"></script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 
<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de la liste des clients
 * Affiche la liste de tous les clients avec leurs statistiques
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Contrats',
    'contracts'
);

// Définir la page courante pour le menu
$currentPage = 'contracts';

$contractFilter = $current_contract_filter ?? 'all';

$filterParams = [
    'show_status' => $current_filter_view ?? 'all',
];

if (!empty($_GET['client_id'])) {
    $filterParams['client_id'] = $_GET['client_id'];
}

if (!empty($_GET['site_id'])) {
    $filterParams['site_id'] = $_GET['site_id'];
}

if (!empty($_GET['room_id'])) {
    $filterParams['room_id'] = $_GET['room_id'];
}

if (!empty($_GET['contract_type_id'])) {
    $filterParams['contract_type_id'] = $_GET['contract_type_id'];
}

function contractFilterUrl($filter, $filterParams)
{
    $params = array_merge(
        $filterParams,
        ['contract_filter' => $filter]
    );

    return BASE_URL . 'contracts?' . http_build_query($params);
}
function contractStatusUrl($status, $filterParams, $contractFilter)
{
    $params = array_merge(
        $filterParams,
        [
            'show_status' => $status,
            'contract_filter' => $contractFilter
        ]
    );

    return BASE_URL . 'contracts?' . http_build_query($params);
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
            <h4 class="py-4 mb-6">Gestion des Contrats</h4>
        </div>

        <div class="ms-auto p-2 bd-highlight">
            <?php if (canManageContracts()): ?>
                <a href="<?php echo BASE_URL; ?>contracts/add" class="btn btn-primary">
                    <i class="bi bi-plus me-1"></i> Nouveau contrat
                </a>
            <?php endif; ?>
        </div>
    </div>

    <!-- Filtres par statut -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card status-filter-card">
                <div class="card-body">
                    <!-- Filtres rapides par statut -->
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Tous les contrats -->
                        <?php
                        $totalCount = 0;
                        foreach ($statsByStatus as $stat) {
                            $totalCount += $stat['count'];
                        }
                        ?>
                        <a href="<?php echo htmlspecialchars(
                            contractStatusUrl('all', $filterParams, $contractFilter)
                        ); ?>"
                            class="btn btn-outline-secondary btn-sm status-filter-btn <?php echo ($current_filter_view ?? 'all') === 'all' ? 'active' : ''; ?>">
                            <span class="badge bg-secondary me-1">
                                <?php echo $totalCount; ?>
                            </span>
                            Tous
                        </a>

                        <!-- Filtres par statut -->
                        <?php foreach ($statsByStatus as $stat): ?>
                            <a href="<?php echo htmlspecialchars(
                                contractStatusUrl($stat['status'], $filterParams, $contractFilter)
                            ); ?>"
                                class="btn btn-outline-secondary btn-sm status-filter-btn <?php echo ($current_filter_view ?? 'actif') === $stat['status'] ? 'active' : ''; ?>">
                                <span class="badge <?php echo $stat['color']; ?> me-1">
                                    <?php echo $stat['count']; ?>
                                </span>
                                <?php echo h($stat['display_name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtre par catégorie de contrat -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card contract-filter-card">
                <div class="card-body">

                    <h6 class="card-title mb-3">
                        <i class="bi bi-funnel me-1"></i>
                        Filtre
                    </h6>

                    <div class="d-flex flex-wrap gap-2">

                        <!-- Tous -->
                        <a href="<?= htmlspecialchars(contractFilterUrl('all', $filterParams)) ?>"
                            class="btn btn-outline-secondary btn-sm contract-filter-btn <?= $contractFilter === 'all' ? 'active' : '' ?>">

                            <!-- <img src="<?= BASE_URL ?>assets/img/icons/contracts/svg/tous.svg" alt=""
                                class="contract-filter-icon"> -->

                            Tous
                        </a>

                        <!-- Tickets -->
                        <a href="<?= htmlspecialchars(contractFilterUrl('tickets', $filterParams)) ?>"
                            class="btn btn-outline-secondary btn-sm contract-filter-btn <?= $contractFilter === 'tickets' ? 'active' : '' ?>">

                            <img src="<?= BASE_URL ?>assets/img/icons/contrats/SVG/01_contrat_tickets.svg"
                                class="contract-filter-icon">
                            Tickets
                        </a>

                        <!-- Gold -->
                        <a href="<?= htmlspecialchars(contractFilterUrl('gold', $filterParams)) ?>"
                            class="btn btn-outline-secondary btn-sm contract-filter-btn <?= $contractFilter === 'gold' ? 'active' : '' ?>">

                            <img src="<?= BASE_URL ?>assets/img/icons/contrats/SVG/04_contrat_gold.svg" alt=""
                                class="contract-filter-icon">

                            Gold
                        </a>

                        <!-- Silver -->
                        <a href="<?= htmlspecialchars(contractFilterUrl('silver', $filterParams)) ?>"
                            class="btn btn-outline-secondary btn-sm contract-filter-btn <?= $contractFilter === 'silver' ? 'active' : '' ?>">

                            <img src="<?= BASE_URL ?>assets/img/icons/contrats/SVG/03_contrat_silver.svg" alt=""
                                class="contract-filter-icon">

                            Silver
                        </a>

                        <!-- Platinum -->
                        <a href="<?= htmlspecialchars(contractFilterUrl('platinum', $filterParams)) ?>"
                            class="btn btn-outline-secondary btn-sm contract-filter-btn <?= $contractFilter === 'platinum' ? 'active' : '' ?>">

                            <img src="<?= BASE_URL ?>assets/img/icons/contrats/SVG/05_contrat_platinium.svg" alt=""
                                class="contract-filter-icon">

                            Platinum
                        </a>

                        <!-- Base -->
                        <a href="<?= htmlspecialchars(contractFilterUrl('base', $filterParams)) ?>"
                            class="btn btn-outline-secondary btn-sm contract-filter-btn <?= $contractFilter === 'base' ? 'active' : '' ?>">

                            <img src="<?= BASE_URL ?>assets/img/icons/contrats/SVG/02_contrat_base.svg" alt=""
                                class="contract-filter-icon">

                            Base
                        </a>

                    </div>
                </div>
            </div>
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
    <div class="table-responsive">
        <table id="contractsTable" class="table table-striped table-hover dt-responsive">
            <thead>
                <tr>
                    <th>Référence</th>
                    <th>Nom</th>
                    <th>Client</th>
                    <th>Type de contrat</th>
                    <th>Date de fin</th>
                    <th>Tickets restants</th>
                    <th>Statut</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($contracts)): ?>
                    <?php foreach ($contracts as $contract): ?>
                        <tr>
                            <td data-label="Référence">
                                <?php if (!empty($contract['reference'])): ?>

                                    <?php echo htmlspecialchars($contract['reference']); ?>

                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Nom">
                                <a href="<?php echo BASE_URL; ?>contracts/view/<?php echo $contract['id']; ?>"
                                    class="text-decoration-none fw-bold" title="Voir le contrat">
                                    <?php echo htmlspecialchars($contract['name'] ?? '-'); ?>
                                </a>
                            </td>
                            <td data-label="Client">
                                <a href="<?php echo BASE_URL; ?>clients/view/<?php echo $contract['client_id']; ?>?return_to=contracts&active_tab=contracts-tab"
                                    class="text-decoration-none" title="Voir le client">
                                    <?php echo htmlspecialchars($contract['client_name'] ?? '-'); ?>
                                </a>
                            </td>
                            <td data-label="Type de contrat">
                                <?php echo htmlspecialchars($contract['contract_type_name'] ?? '-'); ?>
                            </td>
                            <td data-label="Date de fin" data-order="<?php echo strtotime($contract['end_date']); ?>">
                                <?php echo formatDateFrench($contract['end_date']); ?>
                            </td>
                            <td data-label="Tickets restants" data-order="<?php echo $contract['tickets_remaining']; ?>">
                                <?php if (isContractTicketById($contract['id'])): ?>
                                    <span class="badge bg-<?php echo $contract['tickets_remaining'] > 3 ? 'success' : 'danger'; ?>">
                                        <?php echo $contract['tickets_remaining']; ?>
                                    </span>
                                <?php else: ?>
                                    <span class="no-tickets-indicator" title="Sans tickets">
                                        <i class="no-tickets-icon"></i>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Statut">
                                <?php
                                $statusConfig = [
                                    'actif' => ['label' => 'Actif', 'color' => 'success'],
                                    'inactif' => ['label' => 'Inactif', 'color' => 'danger'],
                                    'en_attente' => ['label' => 'En attente', 'color' => 'warning'],
                                    'expire' => ['label' => 'Expiré', 'color' => 'dark'],
                                ];
                                $config = $statusConfig[$contract['status']] ?? ['label' => ucfirst($contract['status']), 'color' => 'secondary'];
                                ?>
                                <span class="badge bg-<?php echo $config['color']; ?>">
                                    <?php echo $config['label']; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <?php // Laisser tbody vide. DataTables utilisera language.emptyTable ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>




</div>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const pageSelect = document.getElementById('mobilePageSelect');

        if (pageSelect) {
            pageSelect.addEventListener('change', function () {
                const params = new URLSearchParams(
                    <?php echo json_encode($filterParams); ?>
                );

                // Conserver le filtre actuellement sélectionné
                params.set(
                    'contract_filter',
                    <?php echo json_encode($contractFilter); ?>
                );

                window.location.href =
                    '<?= BASE_URL ?>contracts?' + params.toString();
            });
        }
    });

    window.BASE_URL = '<?= BASE_URL ?>';
    window.csrfToken = '<?= $_SESSION['csrf_token'] ?>';

    window.serverSavedSettings = {
        contractsTable_pageLength:
            <?= json_encode((int) getUserPreference('datatable_contractsTable_pageLength', 10)) ?>
    };
</script>

<script src="<?php echo BASE_URL; ?>assets/js/datatable-persistence.js"></script>
<script src="<?php echo BASE_URL; ?>assets/js/contracts-datatable.js"></script>
<style>
    .contract-filter-btn,
    .status-filter-btn {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: all 0.2s ease-in-out;
    }

    /* Filtre de catégorie actif */
    a.contract-filter-btn.active,
    a.contract-filter-btn.active:hover,
    a.contract-filter-btn.active:focus,
    a.contract-filter-btn.active:active,
    a.contract-filter-btn.active:focus-visible {
        background-color: #5f61e6 !important;
        background-image: none !important;
        border-color: #5f61e6 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(95, 97, 230, 0.35);
    }

    /* Filtre de statut actif */
    a.status-filter-btn.active,
    a.status-filter-btn.active:hover,
    a.status-filter-btn.active:focus,
    a.status-filter-btn.active:active,
    a.status-filter-btn.active:focus-visible {
        background-color: #157347 !important;
        background-image: none !important;
        border-color: #157347 !important;
        color: #ffffff !important;
        box-shadow: 0 2px 6px rgba(21, 115, 71, 0.35);
    }

    /* Badge du statut actif : même couleur que le bouton */
    a.status-filter-btn.active .badge {
        background-color: #157347 !important;
        color: #ffffff !important;
    }

    .contract-filter-icon {
        width: 20px;
        height: 20px;
        object-fit: contain;
    }
</style>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?>
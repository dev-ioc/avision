<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de la liste des contrats hors contrat non facturable
 * Affiche la liste de tous les contrats hors contrat non facturable
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Contrats Hors Contrat Non Facturable',
    'hors_contrat_non_facturable'
);

// Définir la page courante pour le menu
$currentPage = 'hors_contrat_non_facturable';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight"><h4 class="py-4 mb-6">Contrats Hors Contrat Non Facturable</h4></div>
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
        <table id="horsContratNonFacturableTable" class="table table-striped table-hover dt-responsive">
            <thead>
                <tr>
                    <th>Nom du contrat</th>
                    <th>Client</th>
                    <th>Nombre d'interventions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($contracts)): ?>
                    <?php foreach ($contracts as $contract): ?>
                        <tr>
                            <td data-label="Nom du contrat">
                                <a href="<?php echo BASE_URL; ?>hors_contrat_non_facturable/view/<?php echo $contract['id']; ?>" 
                                   class="text-decoration-none fw-bold" 
                                   title="Voir le contrat">
                                    <?php echo htmlspecialchars($contract['name'] ?? '-'); ?>
                                </a>
                            </td>
                            <td data-label="Client"><?php echo htmlspecialchars($contract['client_name'] ?? '-'); ?></td>
                            <td data-label="Nombre d'interventions" data-order="<?php echo $contract['interventions_count']; ?>">
                                <span class="badge bg-info">
                                    <?php echo $contract['interventions_count'] ?? 0; ?>
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

<!-- DataTable Persistence -->
<script src="<?php echo BASE_URL; ?>assets/js/datatable-persistence.js"></script>

<!-- Page JS -->
<script>
$(document).ready(function() {
    $('#horsContratNonFacturableTable').DataTable({
        responsive: true,
        language: {
            url: '<?php echo BASE_URL; ?>assets/json/locales/datatables-fr.json'
        },
        columnDefs: [
            { targets: [0, 1, 2], orderable: true }
        ],
        order: [[0, 'asc']],
        pageLength: 25,
        lengthMenu: [[10, 25, 50, 100], [10, 25, 50, 100]]
    });
});
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 

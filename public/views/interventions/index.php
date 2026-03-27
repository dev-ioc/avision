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

// Déterminer le type d'intervention depuis l'URL
$currentUrl = $_SERVER['REQUEST_URI'] ?? '';
$isPreventivePage = strpos($currentUrl, '/interventions/preventives') !== false;
$pageTitle = $isPreventivePage ? 'Interventions Préventives' : 'Interventions Curatives';

setPageVariables(
    $pageTitle,
    'interventions'
);

// Définir les breadcrumbs personnalisés pour les pages interventions curatives/préventives
$GLOBALS['customBreadcrumbs'] = generateInterventionsListBreadcrumbs($isPreventivePage);

// Inclure le header qui contient le menu latéral

include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">

<div class="d-flex bd-highlight mb-3">
    <div class="p-2 bd-highlight">
        <h4 class="py-4 mb-6">
            <?php if ($isPreventivePage): ?>
                <i class="bi bi-shield-check me-2"></i>Interventions Préventives
            <?php else: ?>
                <i class="bi bi-tools me-2"></i>Interventions Curatives
            <?php endif; ?>
        </h4>
    </div>

    <div class="ms-auto p-2 bd-highlight">
        <?php if (canModifyInterventions()): ?>
            <a href="<?php echo BASE_URL; ?>interventions/add" class="btn btn-primary">
                <i class="bi bi-plus me-1 me-1"></i> Ajouter une intervention
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- Filtres par staff et statut -->
<div class="row mb-4">
    <div class="col-12">
        <div class="card status-filter-card">
            <div class="card-body">
                <!-- Filtre par staff -->
                <div class="row mb-3">
                    <div class="col-md-6">
                        <div class="d-flex align-items-end gap-3">
                            <div class="flex-grow-1">
                                <label for="technician_filter" class="form-label">Filtrer par technicien :</label>
                                <select id="technician_filter" class="form-select" onchange="filterByTechnician(this.value)">
                                    <option value="">Tous les techniciens</option>
                                    <?php foreach ($technicians as $technician): ?>
                                        <option value="<?php echo $technician['id']; ?>" 
                                                <?php echo (isset($_GET['technician_id']) && $_GET['technician_id'] == $technician['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($technician['first_name'] . ' ' . $technician['last_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Mes interventions (pour les techniciens) -->
                            <?php if ($userType === 'technicien' || $userType === 'admin'): ?>
                                <?php 
                                $currentRoute = $isPreventivePage ? 'interventions/preventives' : 'interventions/curatives';
                                $myInterventionsUrl = BASE_URL . $currentRoute . '?technician_id=' . $_SESSION['user']['id'];
                                if (isset($_GET['status_id'])) {
                                    $myInterventionsUrl .= '&status_id=' . $_GET['status_id'];
                                }
                                if (isset($_GET['priority_id'])) {
                                    $myInterventionsUrl .= '&priority_id=' . $_GET['priority_id'];
                                }
                                ?>
                                <div class="d-flex align-items-end">
                                    <a href="<?php echo $myInterventionsUrl; ?>" 
                                       class="btn btn-outline-primary <?php echo (isset($_GET['technician_id']) && $_GET['technician_id'] == $_SESSION['user']['id']) ? 'active' : ''; ?>">
                                        <i class="fas fa-user me-1"></i>
                                        Mes interventions
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Filtres rapides par statut et priorité -->
                <div class="d-flex flex-wrap gap-2 align-items-center">
                    <!-- Filtres par statut -->
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Tous les statuts -->
                        <?php 
                        $currentRoute = $isPreventivePage ? 'interventions/preventives' : 'interventions/curatives';
                        $allUrl = BASE_URL . $currentRoute;
                        
                        // Si un technicien est sélectionné, l'ajouter à l'URL
                        if (isset($_GET['technician_id']) && !empty($_GET['technician_id'])) {
                            $allUrl .= '?technician_id=' . $_GET['technician_id'];
                        }
                        ?>
                        <a href="<?php echo $allUrl; ?>" class="btn btn-outline-secondary btn-sm status-filter-btn <?php echo (!isset($_GET['status_id'])) ? 'active' : ''; ?>">
                            <span class="badge bg-secondary me-1"><?php echo array_sum(array_column($statsByStatus, 'count')); ?></span>
                            Tous les statuts
                        </a>
                        
                        <!-- Filtres par statut -->
                        <?php foreach ($statsByStatus as $statusStat): ?>
                            <?php 
                            $statusUrl = BASE_URL . $currentRoute . '?status_id=' . $statusStat['id'];
                            
                            // Si un technicien est sélectionné, l'ajouter à l'URL
                            if (isset($_GET['technician_id']) && !empty($_GET['technician_id'])) {
                                $statusUrl .= '&technician_id=' . $_GET['technician_id'];
                            }
                            ?>
                            <a href="<?php echo $statusUrl; ?>" 
                               class="btn btn-outline-secondary btn-sm status-filter-btn <?php echo (isset($_GET['status_id']) && $_GET['status_id'] == $statusStat['id']) ? 'active' : ''; ?>">
                                <span class="badge me-1" style="background-color: <?php echo $statusStat['color']; ?>">
                                    <?php echo $statusStat['count']; ?>
                                </span>
                                <?php echo h($statusStat['name']); ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Séparateur -->
                    <div class="vr mx-2"></div>
                    
                    <!-- Filtres par priorité -->
                    <?php if (!$isPreventivePage): ?>
                    <div class="d-flex flex-wrap gap-2">
                        <!-- Toutes les priorités -->
                        <?php 
                        $currentRoute = 'interventions/curatives';
                        $allPriorityUrl = BASE_URL . $currentRoute;
                        
                        // Conserver les filtres existants
                        $params = [];
                        if (isset($_GET['technician_id']) && !empty($_GET['technician_id'])) {
                            $params[] = 'technician_id=' . $_GET['technician_id'];
                        }
                        if (isset($_GET['status_id']) && !empty($_GET['status_id'])) {
                            $params[] = 'status_id=' . $_GET['status_id'];
                        }
                        if (!empty($params)) {
                            $allPriorityUrl .= '?' . implode('&', $params);
                        }
                        ?>
                        <a href="<?php echo $allPriorityUrl; ?>" class="btn btn-outline-secondary btn-sm priority-filter-btn <?php echo (!isset($_GET['priority_id'])) ? 'active' : ''; ?>">
                            Toutes les priorités
                        </a>
                        
                        <!-- Filtres par priorité (sauf Préventif) -->
                        <?php foreach ($priorities as $priority): ?>
                            <?php 
                            // Exclure la priorité Préventif car elle est gérée par les sous-menus
                            if (stripos($priority['name'], 'préventif') !== false || stripos($priority['name'], 'preventive') !== false) {
                                continue;
                            }
                            
                            $priorityUrl = BASE_URL . $currentRoute . '?priority_id=' . $priority['id'];
                            
                            // Conserver les autres filtres
                            if (isset($_GET['technician_id']) && !empty($_GET['technician_id'])) {
                                $priorityUrl .= '&technician_id=' . $_GET['technician_id'];
                            }
                            if (isset($_GET['status_id']) && !empty($_GET['status_id'])) {
                                $priorityUrl .= '&status_id=' . $_GET['status_id'];
                            }
                            ?>
                            <a href="<?php echo $priorityUrl; ?>" 
                               class="btn btn-outline-secondary btn-sm priority-filter-btn <?php echo (isset($_GET['priority_id']) && $_GET['priority_id'] == $priority['id']) ? 'active' : ''; ?>">
                                <span class="badge me-1" style="background-color: <?php echo $priority['color']; ?>">
                                    <?php echo h($priority['name']); ?>
                                </span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

                        <div class="table-responsive">
                            <table id="interventionsTable" class="table table-striped table-hover dt-responsive">
                                <thead>
                                    <tr>
                                        <th>Référence</th>
                                        <th>Titre</th>
                                        <th>Client</th>
                                        <th>Site</th>
                                        <th>Salle</th>
                                        <th>Statut</th>
                                        <th>Priorité</th>
                                        <th>Date planifiée</th>
                                        <th>Technicien</th>
                                        <th>Date création</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (isset($interventions) && !empty($interventions)): ?>
                                        <?php foreach ($interventions as $intervention): ?>
                                            <tr>
                                                <td data-label="Référence">
                                                    <a href="<?php echo BASE_URL; ?>interventions/view/<?php echo $intervention['id']; ?>" 
                                                       class="text-decoration-none fw-bold" 
                                                       title="Voir l'intervention">
                                                        <?php echo htmlspecialchars($intervention['reference'] ?? ''); ?>
                                                    </a>
                                                </td>
                                                <td data-label="Titre"><?php echo htmlspecialchars($intervention['title'] ?? ''); ?></td>
                                                <td data-label="Client"><?php echo htmlspecialchars($intervention['client_name'] ?? ''); ?></td>
                                                <td data-label="Site"><?php echo htmlspecialchars($intervention['site_name'] ?? '-'); ?></td>
                                                <td data-label="Salle"><?php echo htmlspecialchars($intervention['room_name'] ?? '-'); ?></td>
                                                <td data-label="Statut" data-order="<?php echo $intervention['status_id'] ?? 0; ?>">
                                                    <span class="badge rounded-pill" style="background-color: <?php echo $intervention['status_color'] ?? ''; ?>">
                                                        <?php echo htmlspecialchars($intervention['status_name'] ?? ''); ?>
                                                    </span>
                                                </td>
                                                <td data-label="Priorité" data-order="<?php echo $intervention['priority_id'] ?? 0; ?>">
                                                    <span class="badge rounded-pill" style="background-color: <?php echo $intervention['priority_color'] ?? ''; ?>">
                                                        <?php echo htmlspecialchars($intervention['priority_name'] ?? ''); ?>
                                                    </span>
                                                </td>
                                                                <td data-label="Date planifiée" data-order="<?php echo isset($intervention['date_planif']) ? strtotime($intervention['date_planif']) : 0; ?>">
                    <?php echo !empty($intervention['date_planif']) ? formatDateFrench($intervention['date_planif']) : '-'; ?>
                                                </td>
                                                <td data-label="Technicien"><?php echo htmlspecialchars($intervention['technician_first_name'] ?? '') . ' ' . htmlspecialchars($intervention['technician_last_name'] ?? ''); ?></td>
                                                                <td data-label="Date création" data-order="<?php echo isset($intervention['created_at']) ? strtotime($intervention['created_at']) : 0; ?>">
                    <?php echo formatDateFrench($intervention['created_at']) . ' ' . date('H:i', strtotime($intervention['created_at'] ?? '')); ?>
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


<!-- Définir BASE_URL pour JavaScript -->
<script>
  window.BASE_URL = '<?php echo BASE_URL; ?>';
</script>

<!-- DataTable Persistence -->
<script src="<?php echo BASE_URL; ?>assets/js/datatable-persistence.js"></script>

<!-- Page JS -->
<script src="<?php echo BASE_URL; ?>assets/js/interventions-datatable.js"></script>

<script>
function filterByTechnician(technicianId) {
    // Construire l'URL avec le filtre technicien
    let url = '<?php echo BASE_URL; ?><?php echo $isPreventivePage ? 'interventions/preventives' : 'interventions/curatives'; ?>';
    let params = [];
    
    if (technicianId) {
        params.push('technician_id=' + technicianId);
    }
    
    // Conserver les autres filtres existants
    <?php if (isset($_GET['status_id'])): ?>
        params.push('status_id=<?php echo $_GET['status_id']; ?>');
    <?php endif; ?>
    
    <?php if (isset($_GET['priority_id'])): ?>
        params.push('priority_id=<?php echo $_GET['priority_id']; ?>');
    <?php endif; ?>
    
    if (params.length > 0) {
        url += '?' + params.join('&');
    }
    
    // Rediriger vers la nouvelle URL
    window.location.href = url;
}
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 
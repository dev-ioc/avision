<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de confirmation des interventions préventives
 * Permet de confirmer et modifier les interventions préventives programmées
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Confirmation des interventions préventives',
    'contracts'
);

// Définir la page courante pour le menu
$currentPage = 'contracts';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <!-- En-tête avec actions -->
            <div class="d-flex bd-highlight mb-3">
                <div class="p-2 bd-highlight">
                    <h4 class="py-4 mb-6">Confirmation des interventions préventives</h4>
                </div>

                <div class="ms-auto p-2 bd-highlight">
                    <a href="<?php echo BASE_URL; ?>contracts" class="btn btn-secondary me-2">
                        Retour aux contrats
                    </a>
                    <a href="<?php echo BASE_URL; ?>contracts/ignorePreventiveInterventions" class="btn btn-danger me-2">
                        Ignorer
                    </a>
                    <button type="submit" form="preventiveInterventionsForm" class="btn btn-primary">
                        Créer les interventions
                    </button>
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

            <div class="card">
                <div class="card-header py-2">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-calendar-check me-2"></i>
                        Interventions préventives pour le contrat : <?php echo h($contractName); ?>
                    </h5>
                </div>
                <div class="card-body py-2">
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle me-2 me-1"></i>
                        <strong>Informations :</strong>
                        <ul class="mb-0 mt-2">
                            <li>Le système a programmé <?php echo $nbInterventions; ?> intervention(s) préventive(s) pour <?php echo $_SESSION['nb_rooms'] ?? 1; ?> salle(s)</li>
                            <li>Une intervention par salle a été créée pour chaque période préventive</li>
                            <li>Les dates ont été ajustées pour éviter les weekends et jours fériés</li>
                            <li>Vous pouvez modifier les dates, heures, techniciens et types d'intervention avant de créer les interventions</li>
                            <li>Les interventions seront créées avec le statut "Nouveau" et la priorité "Préventif"</li>
                            <li>Le technicien peut être assigné ultérieurement lors de la planification</li>
                            <?php if (isset($_SESSION['is_existing_contract']) && $_SESSION['is_existing_contract']): ?>
                            <li><strong>Note :</strong> Ces interventions seront ajoutées au contrat existant</li>
                            <?php endif; ?>
                        </ul>
                    </div>

                    <form id="preventiveInterventionsForm" action="<?php echo BASE_URL; ?>contracts/createPreventiveInterventions" method="POST">
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Titre</th>
                                        <th>Localisation</th>
                                        <th>Date</th>
                                        <th>Heure</th>
                                        <th>Technicien</th>
                                        <th>Type d'intervention</th>
                                        <th>Description</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($scheduledInterventions as $index => $intervention): ?>
                                        <tr>
                                            <td><?php echo $index + 1; ?></td>
                                            <td>
                                                <input type="text" 
                                                       class="form-control form-control-sm bg-body text-body" 
                                                       name="title[<?php echo $index; ?>]" 
                                                       value="<?php echo h($intervention['title']); ?>" 
                                                       required>
                                            </td>
                                            <td>
                                                <?php if (isset($intervention['site_name']) && isset($intervention['room_name'])): ?>
                                                    <span class="badge bg-info">
                                                        <i class="bi bi-building me-1"></i>
                                                        <?php echo h($intervention['site_name']); ?> : 
                                                        <?php echo h($intervention['room_name']); ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-muted">Non spécifié</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <input type="date" 
                                                       class="form-control form-control-sm bg-body text-body" 
                                                       name="date[<?php echo $index; ?>]" 
                                                       value="<?php echo $intervention['date']; ?>" 
                                                       required>
                                            </td>
                                            <td>
                                                <input type="time" 
                                                       class="form-control form-control-sm bg-body text-body" 
                                                       name="heure[<?php echo $index; ?>]" 
                                                       value="<?php echo $intervention['heure']; ?>" 
                                                       required>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm bg-body text-body" name="technician_id[<?php echo $index; ?>]">
                                                    <option value="">Non assigné</option>
                                                    <?php foreach ($technicians as $technician): ?>
                                                        <option value="<?php echo $technician['id']; ?>">
                                                            <?php echo htmlspecialchars($technician['first_name'] . ' ' . $technician['last_name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <select class="form-select form-select-sm bg-body text-body" name="type_id[<?php echo $index; ?>]" required>
                                                    <option value="">Sélectionner un type</option>
                                                    <?php foreach ($interventionTypes as $type): ?>
                                                        <option value="<?php echo $type['id']; ?>" <?php echo ($type['id'] == 2) ? 'selected' : ''; ?>>
                                                            <?php echo h($type['name']); ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <textarea class="form-control form-control-sm bg-body text-body" 
                                                          name="description[<?php echo $index; ?>]" 
                                                          rows="2"><?php echo h($intervention['description']); ?></textarea>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>


                    </form>
                </div>
            </div>

            <!-- Informations sur le contrat -->
            <div class="card mt-3">
                <div class="card-header py-2">
                    <h6 class="card-title mb-0">
                        <i class="bi bi-file-earmark-text me-2 me-1"></i>
                        Informations du contrat
                    </h6>
                </div>
                <div class="card-body py-2">
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Client :</strong> <?php echo h($client['name']); ?></p>
                            <p><strong>Contrat :</strong> <?php echo h($contractName); ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Date de début :</strong> <?php echo formatDateFrench($contract['start_date']); ?></p>
                            <p><strong>Date de fin :</strong> <?php echo formatDateFrench($contract['end_date']); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Validation du formulaire
    document.getElementById('preventiveInterventionsForm').addEventListener('submit', function(e) {
        const typeSelects = document.querySelectorAll('select[name^="type_id"]');
        
        let isValid = true;
        
        // Vérifier que tous les types sont sélectionnés
        typeSelects.forEach((select, index) => {
            if (!select.value) {
                alert(`Veuillez sélectionner un type d'intervention pour l'intervention ${index + 1}`);
                isValid = false;
                return;
            }
        });
        
        if (!isValid) {
            e.preventDefault();
        }
    });
    
    // Confirmation avant d'ignorer
    document.querySelector('a[href*="ignorePreventiveInterventions"]').addEventListener('click', function(e) {
        if (!confirm('Êtes-vous sûr de vouloir ignorer la création des interventions préventives ?')) {
            e.preventDefault();
        }
    });
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?> 
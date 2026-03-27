<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de confirmation pour le changement de niveau d'accès
 * Propose de mettre à jour la visibilité des matériels
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Récupérer les données de changement
$changeData = $_SESSION['access_level_change'] ?? null;
if (!$changeData) {
    header('Location: ' . BASE_URL . 'contracts');
    exit;
}

setPageVariables(
    'Confirmation - Changement de niveau d\'accès',
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
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">
                <i class="bi bi-exclamation-triangle text-warning me-2 me-1"></i>
                Confirmation - Changement de niveau d'accès
            </h4>
        </div>

        <div class="ms-auto p-2 bd-highlight">
            <a href="<?php echo BASE_URL; ?>contracts" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Retour aux contrats
            </a>
        </div>
    </div>

    <div class="row">
        <div class="col-md-8 mx-auto">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-level-up-alt me-2"></i>
                        Niveau d'accès modifié pour le contrat
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6 class="alert-heading">
                            <i class="bi bi-info-circle me-2 me-1"></i>
                            Changement détecté
                        </h6>
                        <p class="mb-0">
                            Le niveau d'accès du contrat <strong><?php echo h($contract['name']); ?></strong> 
                            a été modifié de <strong><?php echo htmlspecialchars($changeData['old_level']['name']); ?></strong> 
                            vers <strong><?php echo htmlspecialchars($changeData['new_level']['name']); ?></strong>.
                        </p>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="card border-warning">
                                <div class="card-header bg-warning text-dark">
                                    <h6 class="mb-0">
                                        <i class="fas fa-level-down-alt me-2"></i>
                                        Ancien niveau : <?php echo htmlspecialchars($changeData['old_level']['name']); ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($changeData['old_level']['description']); ?></p>
                                    <small class="text-muted">
                                        <i class="bi bi-eye me-1 me-1"></i>
                                        Visibilité par défaut des champs matériel
                                    </small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card border-success">
                                <div class="card-header bg-success text-white">
                                    <h6 class="mb-0">
                                        <i class="fas fa-level-up-alt me-2"></i>
                                        Nouveau niveau : <?php echo htmlspecialchars($changeData['new_level']['name']); ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <p class="text-muted mb-2"><?php echo htmlspecialchars($changeData['new_level']['description']); ?></p>
                                    <small class="text-muted">
                                        <i class="bi bi-eye me-1 me-1"></i>
                                        Visibilité par défaut des champs matériel
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="alert alert-warning mt-4">
                        <h6 class="alert-heading">
                            <i class="fas fa-question-circle me-2"></i>
                            Que souhaitez-vous faire ?
                        </h6>
                        <p class="mb-2">
                            Ce contrat possède <strong><?php echo $changeData['affected_materials']; ?> matériel(s)</strong> 
                            qui pourraient être affectés par ce changement de niveau d'accès.
                        </p>
                        <p class="mb-0">
                            Voulez-vous appliquer les nouvelles règles de visibilité à tous les matériels de ce contrat ?
                        </p>
                    </div>

                    <div class="d-flex justify-content-center gap-3 mt-4">
                        <a href="<?php echo BASE_URL; ?>contracts/apply_access_level_change/<?php echo $contract['id']; ?>" 
                           class="btn btn-success btn-lg">
                            <i class="bi bi-check me-2 me-1"></i>
                            Oui, mettre à jour les matériels
                        </a>
                        
                        <a href="<?php echo BASE_URL; ?>contracts/ignore_access_level_change/<?php echo $contract['id']; ?>" 
                           class="btn btn-outline-secondary btn-lg">
                            <i class="bi bi-x-lg me-2 me-1"></i>
                            Non, ignorer ce changement
                        </a>
                    </div>

                    <div class="mt-4">
                        <small class="text-muted">
                            <i class="bi bi-info-circle me-1 me-1"></i>
                            Note : Si vous choisissez d'ignorer, les matériels conserveront leurs paramètres de visibilité actuels. 
                            Vous pourrez les modifier manuellement plus tard si nécessaire.
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?> 
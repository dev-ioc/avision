<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user']) || !isAdmin()) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

setPageVariables('Configuration système', 'settings');

// Définir la page courante pour le menu
$currentPage = 'settings';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer les settings actuels
$config = Config::getInstance();
$tarif_ticket = $config->get('tarif_ticket', '90');
$coef_intervention = $config->get('coef_intervention', '0.4');
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">
                <i class="bi bi-gear me-2 me-1"></i>Configuration système
            </h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <a href="<?= BASE_URL ?>settings" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Retour aux paramètres
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

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-sliders text-primary me-2 me-1"></i>
                        Paramètres de configuration
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= BASE_URL ?>settings/saveConfiguration">
                        <?= csrf_field() ?>
                        <!-- Section Paramètres d'intervention -->
                        <div class="mb-4">
                            <h6 class="text-primary mb-3">
                                <i class="bi bi-tools me-2 me-1"></i>
                                Paramètres d'intervention
                            </h6>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="tarif_ticket" class="form-label">
                                            <strong>Tarif par défaut d'un ticket</strong>
                                        </label>
                                        <div class="input-group">
                                            <input type="number" 
                                                   class="form-control" 
                                                   id="tarif_ticket" 
                                                   name="tarif_ticket" 
                                                   value="<?= h($tarif_ticket) ?>" 
                                                   min="0" 
                                                   step="0.01"
                                                   required>
                                            <span class="input-group-text">€</span>
                                        </div>
                                        <div class="form-text">
                                            Tarif par défaut appliqué pour les tickets d'intervention
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="coef_intervention" class="form-label">
                                            <strong>Coefficient d'intervention</strong>
                                        </label>
                                        <input type="number" 
                                               class="form-control" 
                                               id="coef_intervention" 
                                               name="coef_intervention" 
                                               value="<?= h($coef_intervention) ?>" 
                                               min="0" 
                                               max="1" 
                                               step="0.01"
                                               required>
                                        <div class="form-text">
                                            Coefficient global pour le calcul des tickets d'intervention (0.0 à 1.0)
                                            <br><small class="text-muted">
                                                <strong>Formule :</strong> Tickets = Durée + Coef utilisateur + Coef intervention (+ 1 si déplacement)
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Boutons d'action -->
                        <div class="d-flex justify-content-end gap-2">
                            <a href="<?= BASE_URL ?>settings" class="btn btn-secondary">
                                <i class="bi bi-x-lg me-1"></i> Annuler
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> Sauvegarder
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Panneau d'information -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle text-info me-2 me-1"></i>
                        Informations
                    </h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <h6 class="alert-heading">
                            <i class="bi bi-lightbulb me-2 me-1"></i>
                            À propos des paramètres
                        </h6>
                        <p class="mb-0">
                            Ces paramètres affectent le calcul des tickets d'intervention. 
                            Les modifications sont appliquées immédiatement après sauvegarde.
                        </p>
                    </div>
                    
                    <div class="alert alert-warning">
                        <h6 class="alert-heading">
                            <i class="bi bi-exclamation-triangle me-2 me-1"></i>
                            Attention
                        </h6>
                        <p class="mb-0">
                            Modifiez ces paramètres avec précaution. 
                            Des valeurs incorrectes peuvent affecter le calcul des tickets d'intervention.
                        </p>
                    </div>
                    
                    <div class="alert alert-light">
                        <h6 class="alert-heading">
                            <i class="bi bi-info-circle me-2 me-1"></i>
                            Explication des paramètres
                        </h6>
                        <ul class="mb-0">
                            <li><strong>Tarif par défaut :</strong> Prix de base d'un ticket d'intervention</li>
                            <li><strong>Coefficient d'intervention :</strong> Valeur ajoutée dans la formule de calcul (0.0 à 1.0)</li>
                        </ul>
                        <div class="mt-2 p-2 bg-light rounded">
                            <small class="text-muted">
                                <strong>Formule complète :</strong><br>
                                <code>Tickets = Durée + Coef utilisateur + Coef intervention (+ 1 si déplacement)</code><br>
                                <em>Le résultat est arrondi à l'entier supérieur</em>
                            </small>
                        </div>
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

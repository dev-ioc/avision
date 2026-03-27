<?php
require_once __DIR__ . '/../../includes/init.php';
require_once __DIR__ . '/../../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user']) || !isAdmin()) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

setPageVariables('Gestion des templates email', 'settings');

// Définir la page courante pour le menu
$currentPage = 'settings';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Les données du template sont récupérées par le contrôleur

// Types de templates disponibles
$templateTypes = [
    'intervention_created' => 'Création d\'intervention',
    'intervention_closed' => 'Fermeture d\'intervention',
    'bon_intervention' => 'Bon d\'intervention'
];

// Variables disponibles pour les templates
$availableVariables = [
    'intervention_created' => [
        '{intervention_id}' => 'ID de l\'intervention',
        '{intervention_reference}' => 'Référence de l\'intervention',
        '{intervention_title}' => 'Titre de l\'intervention',
        '{client_name}' => 'Nom du client',
        '{demande_par}' => 'Demandé par (champ "Demande par")',
        '{ref_client}' => 'Référence client',
        '{contract_name}' => 'Contrat (nom du contrat lié à l\'intervention)',
        '{site_name}' => 'Nom du site',
        '{room_name}' => 'Nom de la salle',
        '{technician_name}' => 'Nom du technicien',
        '{intervention_description}' => 'Description de l\'intervention',
        '{intervention_duration}' => 'Durée de l\'intervention (heures)',
        '{intervention_priority}' => 'Priorité de l\'intervention',
        '{intervention_type}' => 'Type d\'intervention',
        '{intervention_status}' => 'Statut de l\'intervention',
        '{tickets_used}' => 'Nombre de tickets utilisés',
        '{intervention_url}' => 'URL admin vers l\'intervention',
        '{intervention_client_url}' => 'URL client vers l\'intervention',
        '{created_at}' => 'Date et heure de création',
        '{intervention_date}' => 'Date de création (format court)'
    ],
    'intervention_closed' => [
        '{intervention_id}' => 'ID de l\'intervention',
        '{intervention_reference}' => 'Référence de l\'intervention',
        '{intervention_title}' => 'Titre de l\'intervention',
        '{client_name}' => 'Nom du client',
        '{demande_par}' => 'Demandé par (champ "Demande par")',
        '{ref_client}' => 'Référence client',
        '{contract_name}' => 'Contrat (nom du contrat lié à l\'intervention)',
        '{site_name}' => 'Nom du site',
        '{room_name}' => 'Nom de la salle',
        '{technician_name}' => 'Nom du technicien',
        '{intervention_description}' => 'Description de l\'intervention',
        '{intervention_duration}' => 'Durée de l\'intervention (heures)',
        '{intervention_priority}' => 'Priorité de l\'intervention',
        '{intervention_type}' => 'Type d\'intervention',
        '{intervention_status}' => 'Statut de l\'intervention',
        '{tickets_used}' => 'Nombre de tickets utilisés',
        '{intervention_url}' => 'URL admin vers l\'intervention',
        '{intervention_client_url}' => 'URL client vers l\'intervention',
        '{created_at}' => 'Date et heure de création',
        '{closed_at}' => 'Date et heure de fermeture',
        '{intervention_date}' => 'Date de création (format court)',
        '{solution_comments}' => 'Commentaires solution (HTML formaté)'
    ],
    'bon_intervention' => [
        '{intervention_id}' => 'ID de l\'intervention',
        '{intervention_reference}' => 'Référence de l\'intervention',
        '{intervention_title}' => 'Titre de l\'intervention',
        '{client_name}' => 'Nom du client',
        '{demande_par}' => 'Demandé par (champ "Demande par")',
        '{ref_client}' => 'Référence client',
        '{contract_name}' => 'Contrat (nom du contrat lié à l\'intervention)',
        '{site_name}' => 'Nom du site',
        '{room_name}' => 'Nom de la salle',
        '{technician_name}' => 'Nom du technicien',
        '{intervention_description}' => 'Description de l\'intervention',
        '{intervention_duration}' => 'Durée de l\'intervention (heures)',
        '{intervention_priority}' => 'Priorité de l\'intervention',
        '{intervention_type}' => 'Type d\'intervention',
        '{intervention_status}' => 'Statut de l\'intervention',
        '{tickets_used}' => 'Nombre de tickets utilisés',
        '{intervention_url}' => 'URL admin vers l\'intervention',
        '{intervention_client_url}' => 'URL client vers l\'intervention',
        '{created_at}' => 'Date et heure de création',
        '{closed_at}' => 'Date et heure de fermeture',
        '{intervention_date}' => 'Date de création (format court)'
    ]
];
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec actions -->
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">
                <i class="bi bi-file-text me-2 me-1"></i>
                <?= $isEdit ? 'Modifier le template' : 'Nouveau template' ?>
            </h4>
        </div>
        <div class="ms-auto p-2 bd-highlight">
            <a href="<?= BASE_URL ?>settings/email" class="btn btn-secondary">
                <i class="bi bi-arrow-left me-1"></i> Retour
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
        <!-- Formulaire de template -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-pencil text-primary me-2 me-1"></i>
                        <?= $isEdit ? 'Modifier le template' : 'Créer un nouveau template' ?>
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?= BASE_URL ?>settings/saveEmailTemplate">
                        <?= csrf_field() ?>
                        <?php if ($isEdit): ?>
                            <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="mb-3">
                            <label for="name" class="form-label">Nom du template</label>
                            <input type="text" class="form-control" id="name" name="name" 
                                   value="<?= $isEdit ? h($template['name']) : '' ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="template_type" class="form-label">Type de template</label>
                            <select class="form-select" id="template_type" name="template_type" required>
                                <option value="">Sélectionner un type</option>
                                <?php foreach ($templateTypes as $value => $label): ?>
                                    <option value="<?= $value ?>" 
                                            <?= ($isEdit && $template['template_type'] == $value) ? 'selected' : '' ?>>
                                        <?= h($label) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="subject" class="form-label">Sujet de l'email</label>
                            <input type="text" class="form-control" id="subject" name="subject" 
                                   value="<?= $isEdit ? h($template['subject']) : '' ?>" required>
                            <div class="form-text">Utilisez les variables disponibles ci-contre</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="body" class="form-label">Corps de l'email</label>
                            <textarea class="form-control" id="body" name="body" rows="15" required><?= $isEdit ? h($template['body']) : '' ?></textarea>
                            <div class="form-text">HTML autorisé. Utilisez les variables disponibles ci-contre</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"><?= $isEdit ? h($template['description']) : '' ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="is_active" name="is_active" value="1" 
                                       <?= ($isEdit && $template['is_active']) || !$isEdit ? 'checked' : '' ?>>
                                <label class="form-check-label" for="is_active">
                                    Template actif
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-flex gap-2">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-lg me-1"></i> 
                                <?= $isEdit ? 'Mettre à jour' : 'Créer' ?>
                            </button>
                            <a href="<?= BASE_URL ?>settings/email" class="btn btn-secondary">
                                <i class="bi bi-x-lg me-1"></i> Annuler
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Variables disponibles -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle text-primary me-2 me-1"></i>
                        Variables disponibles
                    </h5>
                </div>
                <div class="card-body">
                    <div id="variables-list">
                        <p class="text-muted">Sélectionnez un type de template pour voir les variables disponibles.</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const templateTypeSelect = document.getElementById('template_type');
    const variablesList = document.getElementById('variables-list');
    const subjectEl = document.getElementById('subject');
    const bodyEl = document.getElementById('body');
    let lastFocusedField = null;
    
    const availableVariables = <?= json_encode($availableVariables) ?>;

    // Garder trace du dernier champ édité (car le clic sur la variable prend le focus)
    function setLastFocusedField(el) {
        if (!el) return;
        if (el === subjectEl || el === bodyEl) {
            lastFocusedField = el;
        }
    }
    [subjectEl, bodyEl].forEach(function(el) {
        if (!el) return;
        el.addEventListener('focus', function() { setLastFocusedField(el); });
        el.addEventListener('click', function() { setLastFocusedField(el); });
        el.addEventListener('keyup', function() { setLastFocusedField(el); });
    });
    
    function updateVariables() {
        const selectedType = templateTypeSelect.value;
        
        if (selectedType && availableVariables[selectedType]) {
            let html = '<h6>Variables pour ' + selectedType + ' :</h6><ul class="list-unstyled">';
            
            for (const [variable, description] of Object.entries(availableVariables[selectedType])) {
                html += '<li class="mb-2">';
                html += '<code class="text-primary">' + variable + '</code><br>';
                html += '<small class="text-muted">' + description + '</small>';
                html += '</li>';
            }
            
            html += '</ul>';
            html += '<div class="alert alert-info mt-3">';
            html += '<small><i class="bi bi-info-circle me-1"></i>';
            html += 'Cliquez sur une variable pour l\'insérer dans le sujet ou le corps de l\'email.</small>';
            html += '</div>';
            html += '<div class="alert alert-warning mt-2">';
            html += '<small><i class="bi bi-exclamation-triangle me-1"></i>';
            html += '<strong>Formats supportés :</strong><br>';
            html += '• <code>{variable}</code> (format standard)<br>';
            html += '• <code>#{variable}</code> (format avec dièse)</small>';
            html += '</div>';
            
            variablesList.innerHTML = html;
            
            // Ajouter les événements de clic
            variablesList.querySelectorAll('code').forEach(function(code) {
                code.style.cursor = 'pointer';
                // Utiliser mousedown pour insérer avant que le focus ne change
                code.addEventListener('mousedown', function(e) {
                    e.preventDefault();
                    const variable = this.textContent;
                    const target = lastFocusedField || bodyEl || subjectEl;
                    if (!target) return;

                    const start = target.selectionStart ?? target.value.length;
                    const end = target.selectionEnd ?? target.value.length;
                    const text = target.value ?? '';

                    target.value = text.substring(0, start) + variable + text.substring(end);
                    target.focus();
                    const cursorPos = start + variable.length;
                    if (typeof target.setSelectionRange === 'function') {
                        target.setSelectionRange(cursorPos, cursorPos);
                    }
                    setLastFocusedField(target);
                });
            });
        } else {
            variablesList.innerHTML = '<p class="text-muted">Sélectionnez un type de template pour voir les variables disponibles.</p>';
        }
    }
    
    templateTypeSelect.addEventListener('change', updateVariables);
    
    // Initialiser si on est en mode édition
    if (templateTypeSelect.value) {
        updateVariables();
    }
});
</script>

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

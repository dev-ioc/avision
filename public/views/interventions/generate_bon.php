<?php
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/ImageThumbnail.php';

// Vérification de l'accès - seuls les utilisateurs connectés peuvent voir les interventions
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Vérification des permissions
if (!canModifyInterventions()) {
    header('Location: ' . BASE_URL . 'interventions');
    exit;
}

// Vérification que l'intervention existe
if (!$intervention) {
    header('Location: ' . BASE_URL . 'interventions');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

// Récupérer l'ID de l'intervention depuis l'URL
$interventionId = isset($intervention['id']) ? $intervention['id'] : '';

setPageVariables(
    'Génération du bon d\'intervention',
    'interventions_generate_bon' . ($interventionId ? '_' . $interventionId : '')
);

// Définir la page courante pour le menu
$currentPage = 'interventions';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2 class="mb-1">Génération du bon d'intervention</h2>
                    <p class="text-muted mb-0">Sélectionnez les éléments à inclure dans le bon d'intervention</p>
                </div>
                <div>
                    <a href="<?php echo BASE_URL; ?>interventions/view/<?php echo $intervention['id']; ?>" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Retour à l'intervention
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Cartes de récapitulatif -->
    <div class="row mb-3">
        <!-- Carte 1: Informations du ticket -->
        <div class="col-md-4 mb-2">
            <div class="card compact-card">
                <div class="card-header py-1">
                    <h6 class="card-title mb-0 small fw-bold">Informations du ticket</h6>
                </div>
                <div class="card-body py-1">
                    <table class="table table-sm mb-0 compact-table">
                        <tr>
                            <td class="text-muted small" style="width: 45%;">Référence:</td>
                            <td class="small"><?= h($intervention['reference'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Date création:</td>
                            <td class="small"><?= !empty($intervention['created_at']) ? date('d/m/Y H:i', strtotime($intervention['created_at'])) : 'Non définie' ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Ref client:</td>
                            <td class="small"><?= h($intervention['ref_client'] ?? 'Non définie') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Date prévue:</td>
                            <td class="small">
                                <?php if (!empty($intervention['date_planif']) && !empty($intervention['heure_planif'])): ?>
                                    <?= formatDateFrench($intervention['date_planif']) ?> à <?= h($intervention['heure_planif']) ?>
                                <?php else: ?>
                                    Non définie
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Carte 2: Personnes et contrat -->
        <div class="col-md-4 mb-2">
            <div class="card compact-card">
                <div class="card-header py-1">
                    <h6 class="card-title mb-0 small fw-bold">Personnes et contrat</h6>
                </div>
                <div class="card-body py-1">
                    <table class="table table-sm mb-0 compact-table">
                        <tr>
                            <td class="text-muted small" style="width: 45%;">Déclarant:</td>
                            <td class="small"><?= h($intervention['demande_par'] ?? 'Non défini') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Intervenant:</td>
                            <td class="small"><?= h($intervention['assigned_to_name'] ?? 'Non assigné') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Contrat:</td>
                            <td class="small"><?= h($intervention['contract_name'] ?? 'Non défini') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Type:</td>
                            <td class="small"><?= h($intervention['contract_type_name'] ?? 'Non défini') ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Carte 3: Client et localisation -->
        <div class="col-md-4 mb-2">
            <div class="card compact-card">
                <div class="card-header py-1">
                    <h6 class="card-title mb-0 small fw-bold">Client et localisation</h6>
                </div>
                <div class="card-body py-1">
                    <table class="table table-sm mb-0 compact-table">
                        <tr>
                            <td class="text-muted small" style="width: 45%;">Client:</td>
                            <td class="small"><?= h($intervention['client_name'] ?? '') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Contact:</td>
                            <td class="small"><?= h($intervention['contact_client'] ?? 'Non défini') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Site:</td>
                            <td class="small"><?= h($intervention['site_name'] ?? 'Non défini') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Salle:</td>
                            <td class="small"><?= h($intervention['room_name'] ?? 'Non définie') ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="row mb-3">
        <!-- Carte 4: Durée et estimation -->
        <div class="col-md-4 mb-2">
            <div class="card compact-card">
                <div class="card-header py-1">
                    <h6 class="card-title mb-0 small fw-bold">Durée et estimation</h6>
                </div>
                <div class="card-body py-1">
                    <table class="table table-sm mb-0 compact-table">
                        <tr>
                            <td class="text-muted small" style="width: 45%;">Durée:</td>
                            <td class="small"><?= h($intervention['duration'] ?? '0') ?>h</td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Tickets utilisés:</td>
                            <td class="small"><?= h($intervention['tickets_used'] ?? '0') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Tickets restants:</td>
                            <td class="small">
                                <span class="badge bg-<?= ($intervention['tickets_remaining'] ?? 0) > 0 ? 'warning' : 'danger' ?> small">
                                    <?= h($intervention['tickets_remaining'] ?? '0') ?>
                                </span>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Carte 5: Adresse et contact -->
        <div class="col-md-4 mb-2">
            <div class="card compact-card">
                <div class="card-header py-1">
                    <h6 class="card-title mb-0 small fw-bold">Adresse et contact</h6>
                </div>
                <div class="card-body py-1">
                    <table class="table table-sm mb-0 compact-table">
                        <tr>
                            <td class="text-muted small" style="width: 45%;">Adresse:</td>
                            <td class="small"><?= h($intervention['site_address'] ?? 'Non définie') ?></td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Téléphone:</td>
                            <td class="small"><?= h($intervention['contact_phone'] ?? 'Non défini') ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Carte 6: Description de l'intervention -->
        <div class="col-md-4 mb-2">
            <div class="card compact-card">
                <div class="card-header py-1">
                    <h6 class="card-title mb-0 small fw-bold">Description</h6>
                </div>
                <div class="card-body py-1">
                    <?php if (!empty($intervention['description'])): ?>
                        <div class="description-content" style="max-height: 80px; overflow-y: auto;">
                            <p class="mb-0 small"><?= nl2br(h($intervention['description'])) ?></p>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0 small">Aucune description</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Sélection des éléments -->
    <div class="row">
        <!-- Sélection des commentaires -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">Commentaires à inclure</h6>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllComments()">
                            Tout sélectionner
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllComments()">
                            Tout désélectionner
                        </button>
                    </div>
                </div>
                <div class="card-body py-2">
                    <?php if (!empty($comments)): ?>
                        <div class="comments-selection">
                            <?php foreach ($comments as $comment): ?>
                                <div class="form-check mb-3 p-3 border rounded">
                                    <input class="form-check-input" type="checkbox" 
                                           id="comment_<?= $comment['id'] ?>" 
                                           name="selected_comments[]" 
                                           value="<?= $comment['id'] ?>"
                                           <?= $comment['pour_bon_intervention'] ? 'checked' : '' ?>>
                                    <label class="form-check-label w-100" for="comment_<?= $comment['id'] ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <strong><?= h($comment['created_by_name'] ?? 'Utilisateur inconnu') ?></strong>
                                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?></small>
                                            </div>
                                            <div>
                                                <?php if ($comment['is_solution']): ?>
                                                    <span class="badge bg-success">Solution</span>
                                                <?php endif; ?>
                                                <?php if ($comment['is_observation']): ?>
                                                    <span class="badge bg-warning">Observation</span>
                                                <?php endif; ?>
                                                <?php if ($comment['visible_by_client']): ?>
                                                    <span class="badge bg-info">Visible par le client</span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">Interne</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <p class="mb-0 text-muted"><?= nl2br(h($comment['comment'])) ?></p>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Aucun commentaire disponible.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Sélection des images -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header py-2 d-flex justify-content-between align-items-center">
                    <h6 class="card-title mb-0">Images à inclure</h6>
                    <div>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAllImages()">
                            Tout sélectionner
                        </button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAllImages()">
                            Tout désélectionner
                        </button>
                    </div>
                </div>
                <div class="card-body py-2">
                    <?php if (!empty($attachments)): ?>
                        <div class="images-selection">
                            <?php foreach ($attachments as $attachment): ?>
                                <?php 
                                // Utiliser nom_fichier pour la détection d'extension (nom physique)
                                $extension = strtolower(pathinfo($attachment['nom_fichier'], PATHINFO_EXTENSION));
                                $isImage = in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp', 'svg']);
                                ?>
                                <?php if ($isImage): ?>
                                    <?php 
                                    // Générer la miniature si nécessaire
                                    $originalPath = $attachment['chemin_fichier'];
                                    $thumbnailPath = ImageThumbnail::generateThumbnailIfNeeded($originalPath, 150, 150);
                                    ?>
                                    <div class="form-check mb-3 p-3 border rounded">
                                        <input class="form-check-input" type="checkbox" 
                                               id="attachment_<?= $attachment['id'] ?>" 
                                               name="selected_attachments[]" 
                                               value="<?= $attachment['id'] ?>"
                                               <?= $attachment['pour_bon_intervention'] ? 'checked' : '' ?>>
                                        <label class="form-check-label w-100" for="attachment_<?= $attachment['id'] ?>">
                                            <div class="d-flex align-items-start gap-3 mb-2">
                                                <!-- Miniature -->
                                                <div class="thumbnail-container" style="flex-shrink: 0;">
                                                    <?php if ($thumbnailPath && file_exists($thumbnailPath)): ?>
                                                        <img src="<?= BASE_URL . $thumbnailPath ?>" 
                                                             alt="Miniature" 
                                                             class="img-thumbnail" 
                                                             style="width: 80px; height: 80px; object-fit: cover; border-radius: 8px;">
                                                    <?php else: ?>
                                                        <div class="d-flex align-items-center justify-content-center bg-light border rounded" 
                                                             style="width: 80px; height: 80px;">
                                                            <i class="bi bi-image-fill text-muted" style="font-size: 24px;"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                                
                                                <!-- Informations du fichier -->
                                                <div class="flex-grow-1">
                                                    <div class="d-flex align-items-center gap-2 mb-1">
                                                        <i class="bi bi-image-fill text-primary"></i>
                                                        <div class="fw-bold"><?= h($attachment['nom_personnalise'] ?? $attachment['nom_fichier']) ?></div>
                                                    </div>
                                                    <?php if (!empty($attachment['nom_personnalise']) && $attachment['nom_personnalise'] !== $attachment['nom_fichier']): ?>
                                                        <div class="text-muted small mb-1"><?= h($attachment['nom_fichier']) ?></div>
                                                    <?php endif; ?>
                                                    <small class="text-muted"><?= date('d/m/Y H:i', strtotime($attachment['date_creation'])) ?></small>
                                                </div>
                                            </div>
                                            <?php if (!empty($attachment['description'])): ?>
                                                <p class="mb-0 text-muted small"><?= h($attachment['description']) ?></p>
                                            <?php endif; ?>
                                        </label>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mb-0">Aucune image disponible.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Générer le bon d'intervention</h6>
                            <p class="text-muted mb-0">Les éléments sélectionnés seront inclus dans le bon d'intervention</p>
                        </div>
                        <div>
                            <button type="button" class="btn btn-outline-secondary me-2" onclick="saveSelection()">
                                <i class="bi bi-save me-1"></i> Sauvegarder la sélection
                            </button>
                            <button type="button" class="btn btn-primary" onclick="generateBon()">
                                <i class="bi bi-file-pdf me-1"></i> Générer le bon d'intervention
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Fonctions pour la sélection des commentaires
function selectAllComments() {
    document.querySelectorAll('input[name="selected_comments[]"]').forEach(checkbox => {
        checkbox.checked = true;
    });
}

function deselectAllComments() {
    document.querySelectorAll('input[name="selected_comments[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
}

// Fonctions pour la sélection des images
function selectAllImages() {
    document.querySelectorAll('input[name="selected_attachments[]"]').forEach(checkbox => {
        checkbox.checked = true;
    });
}

function deselectAllImages() {
    document.querySelectorAll('input[name="selected_attachments[]"]').forEach(checkbox => {
        checkbox.checked = false;
    });
}

// Sauvegarder la sélection
function saveSelection() {
    const selectedComments = Array.from(document.querySelectorAll('input[name="selected_comments[]"]:checked')).map(cb => cb.value);
    const selectedAttachments = Array.from(document.querySelectorAll('input[name="selected_attachments[]"]:checked')).map(cb => cb.value);
    
    return fetch(`<?php echo BASE_URL; ?>interventions/saveBonSelection/<?php echo $intervention['id']; ?>`, {
        method: 'POST',
            headers: {
                'X-CSRF-Token': '<?= csrf_token() ?>',
                'Content-Type': 'application/json',
            },
        body: JSON.stringify({
            comments: selectedComments,
            attachments: selectedAttachments
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Sélection sauvegardée avec succès', 'success');
            return data;
        } else {
            showAlert('Erreur lors de la sauvegarde: ' + data.message, 'danger');
            throw new Error(data.message);
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        showAlert('Erreur lors de la sauvegarde', 'danger');
        throw error;
    });
}

// Générer le bon d'intervention
function generateBon() {
    const selectedComments = Array.from(document.querySelectorAll('input[name="selected_comments[]"]:checked')).map(cb => cb.value);
    const selectedAttachments = Array.from(document.querySelectorAll('input[name="selected_attachments[]"]:checked')).map(cb => cb.value);
    
    // Sauvegarder d'abord la sélection
    saveSelection().then(() => {
        // Puis générer le bon dans une nouvelle fenêtre
        window.open(`<?php echo BASE_URL; ?>interventions/generateBonPdf/<?php echo $intervention['id']; ?>`, '_blank');
    });
}

// Fonction pour afficher les alertes
function showAlert(message, type) {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
    alertDiv.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    const container = document.querySelector('.container-fluid');
    container.insertBefore(alertDiv, container.firstChild);
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        if (alertDiv.parentNode) {
            alertDiv.remove();
        }
    }, 5000);
}
</script>

<style>
/* Styles pour les cartes compactes */
.compact-card {
    border: 1px solid #dee2e6;
    border-radius: 6px;
}

.compact-card .card-header {
    background-color: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 0.5rem 0.75rem;
}

.compact-card .card-body {
    padding: 0.5rem 0.75rem;
}

.compact-table td {
    padding: 0.25rem 0.5rem;
    border: none;
    vertical-align: top;
}

.compact-table tr {
    border-bottom: 1px solid #f1f3f4;
}

.compact-table tr:last-child {
    border-bottom: none;
}

/* Styles pour les miniatures */
.thumbnail-container img {
    transition: transform 0.2s ease;
    cursor: pointer;
}

.thumbnail-container img:hover {
    transform: scale(1.05);
}

.images-selection .form-check {
    transition: background-color 0.2s ease;
}

.images-selection .form-check:hover {
    background-color: #f8f9fa;
}

.images-selection .form-check-input:checked + .form-check-label {
    background-color: #e3f2fd;
}

/* Amélioration de l'apparence des checkboxes */
.images-selection .form-check-input {
    margin-top: 0.5rem;
}

.images-selection .form-check-label {
    cursor: pointer;
    border-radius: 8px;
    padding: 0.5rem;
    margin-left: 0.5rem;
}
</style>

</div> <!-- Fin du container-fluid -->

<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

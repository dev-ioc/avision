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
                    <a href="<?php echo BASE_URL; ?>interventions/view/<?php echo $intervention['id']; ?>"
                        class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Retour à l'intervention
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Cartes de récapitulatif -->
    <div class="row mb-3">
        <!-- Carte 1: Informations du ticket -->
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
                            <td class="small">
                                <?= h($intervention['reference'] ?? '') ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Date création:</td>
                            <td class="small">
                                <?= !empty($intervention['created_at']) ? date('d/m/Y H:i', strtotime($intervention['created_at'])) : 'Non définie' ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Ref client:</td>
                            <td class="small">
                                <?= h($intervention['ref_client'] ?? 'Non définie') ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Date prévue:</td>
                            <td class="small">
                                <?php
                                // Utiliser la date calculée à partir des techniciens
                                if (!empty($technicians)) {
                                    $firstStart = null;
                                    foreach ($technicians as $tech) {
                                        if (!empty($tech['start_time']) && !$firstStart) {
                                            $firstStart = $tech['start_time'];
                                        }
                                    }
                                    if ($firstStart) {
                                        echo date('d/m/Y H:i', strtotime($firstStart));
                                    } else {
                                        echo 'Non définie';
                                    }
                                } else {
                                    echo 'Non définie';
                                }
                                ?>
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
                            <td class="small">
                                <?= h($intervention['demande_par'] ?? 'Non défini') ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Intervenant(s):</td>
                            <td class="small">
                                <?php if (!empty($technicians)): ?>
                                    <?php foreach ($technicians as $index => $tech): ?>
                                        <div class="p-1">
                                            <?= h($tech['full_name']) ?>
                                            <?php if ($tech['deplacement'] ?? 0): ?>
                                                <span class="badge bg-info ms-1"> Dépl.</span>
                                            <?php endif; ?>
                                            <?php if ($tech['is_qualified'] ?? 0): ?>
                                                <span class="badge bg-success ms-1"> Qualifié.</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger  ms-1"> Non qualifié</span>
                                            <?php endif; ?>
                                            <?php if ($index < count($technicians) - 1): ?><br>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <span class="text-muted">Non assigné</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Contrat:</td>
                            <td class="small">
                                <?= h($intervention['contract_name'] ?? 'Non défini') ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Type:</td>
                            <td class="small">
                                <?= h($intervention['contract_type_name'] ?? 'Non défini') ?>
                            </td>
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
        <!-- Carte 4: Durée et estimation -->
        <div class="col-md-4 mb-2">
            <div class="card compact-card">
                <div class="card-header py-1">
                    <h6 class="card-title mb-0 small fw-bold">Durée et estimation</h6>
                </div>
                <div class="card-body py-1">
                    <table class="table table-sm mb-0 compact-table">
                        <tr>
                            <td class="text-muted small" style="width: 45%;">Durée totale:</td>
                            <td class="small">
                                <?php
                                // Calculer la durée totale à partir des techniciens
                                $totalDuration = 0;
                                if (!empty($technicians)) {
                                    foreach ($technicians as $tech) {
                                        $totalDuration += (float) ($tech['temps_passe'] ?? 0);
                                    }
                                }
                                // Convertir minutes en heures décimales
                                $totalHours = $totalDuration / 60;
                                if ($totalHours > 0) {
                                    echo number_format($totalHours, 2, ',', ' ') . ' h';
                                } elseif (!empty($intervention['duration'])) {
                                    echo number_format($intervention['duration'], 2, ',', ' ') . ' h';
                                } else {
                                    echo 'Non définie';
                                }
                                ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Tickets utilisés:</td>
                            <td class="small">
                                <?= h($intervention['tickets_used'] ?? '0') ?>
                            </td>
                        </tr>
                        <tr>
                            <td class="text-muted small">Tickets restants:</td>
                            <td class="small">
                                <span
                                    class="badge bg-<?= ($intervention['tickets_remaining'] ?? 0) > 0 ? 'warning' : 'danger' ?> small">
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
                                    <input class="form-check-input" type="checkbox" id="comment_<?= $comment['id'] ?>"
                                        name="selected_comments[]" value="<?= $comment['id'] ?>"
                                        <?= $comment['pour_bon_intervention'] ? 'checked' : '' ?>>
                                    <label class="form-check-label w-100" for="comment_<?= $comment['id'] ?>">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <div class="d-flex align-items-center gap-2">
                                                <strong><?= h($comment['created_by_name'] ?? 'Utilisateur inconnu') ?></strong>
                                                <small
                                                    class="text-muted"><?= date('d/m/Y H:i', strtotime($comment['created_at'])) ?></small>
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
                                        <input class="form-check-input" type="checkbox" id="attachment_<?= $attachment['id'] ?>"
                                            name="selected_attachments[]" value="<?= $attachment['id'] ?>"
                                            <?= $attachment['pour_bon_intervention'] ? 'checked' : '' ?>>
                                        <label class="form-check-label w-100" for="attachment_<?= $attachment['id'] ?>">
                                            <div class="d-flex align-items-start gap-3 mb-2">
                                                <!-- Miniature -->
                                                <div class="thumbnail-container" style="flex-shrink: 0;">
                                                    <?php if ($thumbnailPath && file_exists($thumbnailPath)): ?>
                                                        <img src="<?= BASE_URL . $thumbnailPath ?>" alt="Miniature"
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
                                                        <div class="fw-bold">
                                                            <?= h($attachment['nom_personnalise'] ?? $attachment['nom_fichier']) ?>
                                                        </div>
                                                    </div>
                                                    <?php if (!empty($attachment['nom_personnalise']) && $attachment['nom_personnalise'] !== $attachment['nom_fichier']): ?>
                                                        <div class="text-muted small mb-1"><?= h($attachment['nom_fichier']) ?></div>
                                                    <?php endif; ?>
                                                    <small
                                                        class="text-muted"><?= date('d/m/Y H:i', strtotime($attachment['date_creation'])) ?></small>
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
                            <p class="text-muted mb-0">Les éléments sélectionnés seront inclus dans le bon
                                d'intervention</p>
                        </div>
                        <div>
                            <button type="button" class="btn btn-success" data-bs-toggle="modal"
                                data-bs-target="#signatureModal">
                                <i class="bi bi-pen"></i>
                                Envoyer pour signature
                            </button>
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

    // Fonction pour afficher les alertes stylisées
    function showAlert(message, type) {
        // Supprimer les alertes existantes pour éviter les doublons
        const existingAlerts = document.querySelectorAll('.custom-alert-floating');
        existingAlerts.forEach(alert => alert.remove());

        const alertDiv = document.createElement('div');
        alertDiv.className = `custom-alert-floating alert alert-${type} alert-dismissible fade show`;

        // Icônes selon le type
        const icons = {
            success: '<i class="bi bi-check-circle-fill me-2"></i>',
            danger: '<i class="bi bi-exclamation-triangle-fill me-2"></i>',
            warning: '<i class="bi bi-exclamation-circle-fill me-2"></i>',
            info: '<i class="bi bi-info-circle-fill me-2"></i>'
        };

        alertDiv.innerHTML = `
        <div class="d-flex align-items-center">
            <div class="flex-shrink-0">
                ${icons[type] || icons.info}
            </div>
            <div class="flex-grow-1 ms-2">
                <strong>${type === 'success' ? 'Succès !' : type === 'danger' ? 'Erreur !' : 'Information'}</strong>
                <div class="small mt-1">${message}</div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    `;

        // Styles CSS inline pour un meilleur rendu
        alertDiv.style.position = 'fixed';
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.minWidth = '320px';
        alertDiv.style.maxWidth = '450px';
        alertDiv.style.zIndex = '9999';
        alertDiv.style.boxShadow = '0 4px 12px rgba(0,0,0,0.15)';
        alertDiv.style.borderRadius = '8px';
        alertDiv.style.borderLeft = `4px solid ${type === 'success' ? '#28a745' : type === 'danger' ? '#dc3545' : type === 'warning' ? '#ffc107' : '#17a2b8'}`;
        alertDiv.style.animation = 'slideInRight 0.3s ease-out';

        // Ajouter l'animation CSS si elle n'existe pas
        if (!document.querySelector('#alert-styles')) {
            const style = document.createElement('style');
            style.id = 'alert-styles';
            style.textContent = `
            @keyframes slideInRight {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
            @keyframes slideOutRight {
                from {
                    transform: translateX(0);
                    opacity: 1;
                }
                to {
                    transform: translateX(100%);
                    opacity: 0;
                }
            }
            .custom-alert-floating {
                cursor: pointer;
                transition: all 0.3s ease;
            }
            .custom-alert-floating:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 16px rgba(0,0,0,0.2);
            }
        `;
            document.head.appendChild(style);
        }

        document.body.appendChild(alertDiv);

        // Barre de progression
        const progressBar = document.createElement('div');
        progressBar.style.position = 'absolute';
        progressBar.style.bottom = '0';
        progressBar.style.left = '0';
        progressBar.style.height = '3px';
        progressBar.style.backgroundColor = type === 'success' ? '#28a745' : type === 'danger' ? '#dc3545' : '#ffc107';
        progressBar.style.width = '100%';
        progressBar.style.borderRadius = '0 0 8px 8px';
        progressBar.style.transition = 'width 4s linear';
        alertDiv.appendChild(progressBar);

        // Animer la barre de progression
        setTimeout(() => {
            progressBar.style.width = '0%';
        }, 100);

        // Fermeture automatique après 4 secondes
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.style.animation = 'slideOutRight 0.3s ease-out forwards';
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 300);
            }
        }, 4000);

        // Fermeture au clic
        alertDiv.addEventListener('click', function (e) {
            if (!e.target.closest('.btn-close')) {
                alertDiv.style.animation = 'slideOutRight 0.3s ease-out forwards';
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 300);
            }
        });
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

    .images-selection .form-check-input:checked+.form-check-label {
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
<!-- Modal Signature -->
<div class="modal fade" id="signatureModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-pen me-2"></i>Envoyer pour signature
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <!-- Chargement -->
                <div id="signerLoading" class="text-center py-3">
                    <div class="spinner-border text-primary" role="status"></div>
                    <p class="mt-2 text-muted">Chargement des contacts...</p>
                </div>

                <!-- Choix du signataire -->
                <div id="signerForm" style="display:none;">
                    <?= csrf_field() ?>
                    <div class="mb-4">
                        <label class="form-label fw-semibold">Signataire</label>
                        <!-- Contact principal de l'intervention -->
                        <div class="form-check border rounded p-3 mb-2" id="optionPrincipal">
                            <input class="form-check-input" type="radio" name="signerChoice" id="signerPrincipal"
                                value="principal" checked>
                            <label class="form-check-label w-100" for="signerPrincipal">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-person-fill text-primary"></i>
                                    <div>
                                        <div class="fw-semibold" id="principalName">—</div>
                                        <small class="text-muted" id="principalEmail">—</small>
                                    </div>
                                    <span class="badge bg-primary ms-auto">Contact intervention</span>
                                </div>
                            </label>
                        </div>

                        <!-- Autre contact du client -->
                        <div class="form-check border rounded p-3 mb-2">
                            <input class="form-check-input" type="radio" name="signerChoice" id="signerOther"
                                value="other">
                            <label class="form-check-label w-100" for="signerOther">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-people-fill text-secondary"></i>
                                    <span class="fw-semibold">Autre contact du client</span>
                                </div>
                            </label>
                        </div>

                        <!-- Select contacts -->
                        <div id="otherContactWrapper" style="display:none;" class="ms-4 mt-2">
                            <select class="form-select" id="otherContactSelect">
                                <option value="">— Sélectionner un contact —</option>
                            </select>
                        </div>

                        <!-- Signataire manuel -->
                        <div class="form-check border rounded p-3 mb-2">
                            <input class="form-check-input" type="radio" name="signerChoice" id="signerManual"
                                value="manual">
                            <label class="form-check-label w-100" for="signerManual">
                                <div class="d-flex align-items-center gap-2">
                                    <i class="bi bi-pencil-fill text-warning"></i>
                                    <span class="fw-semibold">Saisir manuellement</span>
                                </div>
                            </label>
                        </div>

                        <!-- Champs manuels -->
                        <div id="manualFields" style="display:none;" class="ms-4 mt-2">
                            <div class="row g-2">
                                <div class="col-md-6">
                                    <input type="text" class="form-control" id="manualFirstname" placeholder="Prénom">
                                </div>
                                <div class="col-md-6">
                                    <input type="text" class="form-control" id="manualLastname" placeholder="Nom">
                                </div>
                                <div class="col-12">
                                    <input type="email" class="form-control" id="manualEmail" placeholder="Email *"
                                        required>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Téléphone pour SMS OTP -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">
                            Téléphone pour vérification SMS
                            <span class="text-muted fw-normal">(optionnel mais recommandé)</span>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="bi bi-phone"></i></span>
                            <input type="text" class="form-control" id="signerPhone" placeholder="+261 32 12 345 67">
                        </div>
                        <div class="form-text">
                            Si renseigné, SignNow enverra un code SMS au signataire pour authentification.
                        </div>
                    </div>

                    <!-- Récap -->
                    <div class="alert alert-info d-none" id="signerRecap">
                        <i class="bi bi-info-circle me-2"></i>
                        <span id="signerRecapText"></span>
                    </div>
                </div>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Annuler
                </button>
                <button type="button" class="btn btn-success" id="btnSendSignature" disabled>
                    <i class="bi bi-send me-1"></i> Envoyer la demande
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    const interventionId = <?= (int) $intervention['id'] ?>;
    const clientId = <?= (int) ($intervention['client_id'] ?? 0) ?>;

    const principalContact = {
        firstname: <?= json_encode($intervention['contact_first_name'] ?? '') ?>,
        lastname: <?= json_encode($intervention['contact_last_name'] ?? '') ?>,
        email: <?= json_encode($intervention['contact_client'] ?? '') ?>,
        phone: <?= json_encode($intervention['contact_phone'] ?? '') ?>,
    };

    let allContacts = [];

    document.getElementById('signatureModal')
        .addEventListener('show.bs.modal', () => { loadSignerData(); });

    async function loadSignerData() {
        document.getElementById('signerLoading').style.display = '';
        document.getElementById('signerForm').style.display = 'none';
        document.getElementById('btnSendSignature').disabled = true;

        const pName = [principalContact.firstname, principalContact.lastname]
            .filter(Boolean).join(' ') || '(non défini)';
        document.getElementById('principalName').textContent = pName;
        document.getElementById('principalEmail').textContent = principalContact.email || '(email manquant)';
        document.getElementById('signerPhone').value = principalContact.phone || '';

        if (!principalContact.email) {
            document.getElementById('signerPrincipal').disabled = true;
            document.getElementById('signerOther').checked = true;
        }

        try {
            const res = await fetch(`${BASE_URL}interventions/getContacts/${clientId}`);
            const data = await res.json();
            allContacts = Array.isArray(data) ? data : [];
        } catch (e) { allContacts = []; }

        const select = document.getElementById('otherContactSelect');
        select.innerHTML = '<option value="">— Sélectionner un contact —</option>';
        allContacts.forEach(c => {
            const opt = document.createElement('option');
            opt.value = c.id;
            opt.textContent = `${c.first_name} ${c.last_name}`.trim() + ` — ${c.email || 'sans email'}`;
            opt.dataset.email = c.email || '';
            opt.dataset.firstname = c.first_name || '';
            opt.dataset.lastname = c.last_name || '';
            select.appendChild(opt);
        });

        document.getElementById('signerLoading').style.display = 'none';
        document.getElementById('signerForm').style.display = '';
        refreshRecap();
    }

    document.querySelectorAll('input[name="signerChoice"]').forEach(radio => {
        radio.addEventListener('change', () => {
            document.getElementById('otherContactWrapper').style.display =
                radio.value === 'other' ? '' : 'none';
            document.getElementById('manualFields').style.display =
                radio.value === 'manual' ? '' : 'none';
            refreshRecap();
        });
    });

    document.getElementById('otherContactSelect').addEventListener('change', refreshRecap);
    document.getElementById('signerPhone').addEventListener('input', refreshRecap);
    ['manualFirstname', 'manualLastname', 'manualEmail'].forEach(id => {
        document.getElementById(id).addEventListener('input', refreshRecap);
    });

    function getSelectedSigner() {
        const choice = document.querySelector('input[name="signerChoice"]:checked')?.value;
        if (choice === 'principal') {
            return {
                email: principalContact.email, firstname: principalContact.firstname,
                lastname: principalContact.lastname, phone: document.getElementById('signerPhone').value.trim(),
            };
        }
        if (choice === 'other') {
            const sel = document.getElementById('otherContactSelect');
            const opt = sel.options[sel.selectedIndex];
            if (!opt || !opt.value) return null;
            return {
                email: opt.dataset.email, firstname: opt.dataset.firstname,
                lastname: opt.dataset.lastname, phone: document.getElementById('signerPhone').value.trim()
            };
        }
        if (choice === 'manual') {
            const email = document.getElementById('manualEmail').value.trim();
            if (!email) return null;
            return {
                email, firstname: document.getElementById('manualFirstname').value.trim(),
                lastname: document.getElementById('manualLastname').value.trim(),
                phone: document.getElementById('signerPhone').value.trim()
            };
        }
        return null;
    }

    function refreshRecap() {
        const signer = getSelectedSigner();
        const recap = document.getElementById('signerRecap');
        const btn = document.getElementById('btnSendSignature');
        if (!signer || !signer.email) {
            recap.classList.add('d-none'); btn.disabled = true; return;
        }
        const name = [signer.firstname, signer.lastname].filter(Boolean).join(' ') || signer.email;
        const sms = signer.phone ? ` — SMS: ${signer.phone}` : '';
        document.getElementById('signerRecapText').textContent = `Envoi à : ${name} <${signer.email}>${sms}`;
        recap.classList.remove('d-none');
        btn.disabled = false;
    }

    document.getElementById('btnSendSignature').addEventListener('click', sendForSignature);

    async function sendForSignature() {
        const signer = getSelectedSigner();
        if (!signer || !signer.email) {
            showAlert('Veuillez sélectionner un signataire valide', 'warning');
            return;
        }

        const btn = document.getElementById('btnSendSignature');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Envoi en cours...';

        try {
            await saveSelection();

            // Vérifier que le token CSRF existe
            if (!AppConfig.csrfToken) {
                throw new Error('Token CSRF manquant. Veuillez rafraîchir la page.');
            }

            console.log('Envoi de la requête avec token:', AppConfig.csrfToken.substring(0, 20) + '...');

            const response = await fetch(`${AppConfig.baseUrl}interventions/sendForSignature/${interventionId}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': AppConfig.csrfToken,
                    'Accept': 'application/json'  // ← Important pour indiquer qu'on attend du JSON
                },
                body: JSON.stringify({
                    contact_email: signer.email,
                    contact_phone: signer.phone,
                    contact_firstname: signer.firstname,
                    contact_lastname: signer.lastname,
                })
            });

            // Vérifier le status HTTP
            if (!response.ok) {
                const text = await response.text();
                console.error('HTTP Error:', response.status, text.substring(0, 200));

                if (response.status === 403) {
                    throw new Error('Token CSRF invalide. Veuillez rafraîchir la page.');
                } else if (response.status === 419) {
                    throw new Error('Session expirée. Veuillez rafraîchir la page.');
                } else {
                    throw new Error(`Erreur serveur (${response.status})`);
                }
            }

            // Lire la réponse comme JSON
            const data = await response.json();

            console.log('Réponse serveur:', data);

            if (data.success) {
                showAlert('Demande de signature envoyée avec succès', 'success');
                const modal = bootstrap.Modal.getInstance(document.getElementById('signatureModal'));
                if (modal) modal.hide();

                // Optionnel : recharger la page ou rafraîchir les données
                setTimeout(() => {
                    location.reload();
                }, 1500);
            } else {
                throw new Error(data.message || 'Erreur inconnue');
            }
        } catch (error) {
            console.error('Erreur détaillée:', error);
            showAlert('Erreur : ' + error.message, 'danger');
        } finally {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-send me-1"></i> Envoyer la demande';
        }
    }
</script>
<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
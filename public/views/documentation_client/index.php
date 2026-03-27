<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue de la liste de la documentation client
 * Affiche la liste des documents regroupés par site/salle avec filtres
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Ma Documentation',
    'documentation_client'
);

// Définir la page courante pour le menu
$currentPage = 'documentation_client';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer les données depuis le contrôleur
$documentation_list = $documentation_list ?? [];
$sites = $sites ?? [];
$salles = $salles ?? [];
$filters = $filters ?? [];

// Organiser la documentation par client/site/salle
$documentation_organise = [];
foreach ($documentation_list as $doc) {
    $client_id = $doc['client_nom'] ?? 'Sans client';
    $site_id = $doc['site_nom'] ?? 'Sans site';
    $salle_id = $doc['salle_nom'] ?? 'Sans salle';
    
    if (!isset($documentation_organise[$client_id])) {
        $documentation_organise[$client_id] = [];
    }
    if (!isset($documentation_organise[$client_id][$site_id])) {
        $documentation_organise[$client_id][$site_id] = [];
    }
    if (!isset($documentation_organise[$client_id][$site_id][$salle_id])) {
        $documentation_organise[$client_id][$site_id][$salle_id] = [];
    }
    
    $documentation_organise[$client_id][$site_id][$salle_id][] = $doc;
}
?>

<style>
.documentation-row {
    background-color: var(--bs-body-bg);
}

.documentation-row .card {
    border: 1px solid var(--bs-border-color);
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.documentation-list .list-group-item {
    border: 1px solid var(--bs-border-color);
    border-radius: 0.375rem;
    transition: all 0.2s ease-in-out;
    background-color: var(--bs-body-bg);
    color: var(--bs-body-color);
    padding: 0.75rem;
    margin-bottom: 0.5rem;
}

.documentation-list .list-group-item:hover {
    background-color: var(--bs-secondary-bg);
    border-color: var(--bs-primary);
    box-shadow: 0 2px 4px rgba(var(--bs-primary-rgb), 0.15);
}

.btn-action {
    transition: all 0.2s ease-in-out;
}

.btn-action:hover {
    transform: scale(1.05);
}

.documentation-row td {
    border-top: none;
    border-bottom: 1px solid var(--bs-border-color);
}

.min-w-0 {
    min-width: 0;
}

.documentation-list .btn-group {
    flex-shrink: 0;
}

/* Styles pour les icônes de type de fichier */
.file-icon {
    font-size: 1.2rem;
    margin-right: 0.5rem;
}

.file-type-badge {
    font-size: 0.75rem;
    padding: 0.25rem 0.5rem;
}

/* Styles pour les liens de documents */
.document-link {
    color: var(--bs-primary);
    text-decoration: none;
    font-weight: 500;
}

.document-link:hover {
    color: var(--bs-primary);
    text-decoration: underline;
}
</style>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec titre et bouton d'ajout -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bi bi-file-text me-2 me-1"></i>Ma Documentation
                    </h4>
                    <p class="text-muted mb-0">Consultation de la documentation par site et salle</p>
                </div>
                <div class="d-flex gap-2">
                    <?php
                    // Construire l'URL d'ajout avec les paramètres de filtres
                    $addParams = [];
                    if (!empty($filters['site_id'])) {
                        $addParams['site_id'] = $filters['site_id'];
                    }
                    if (!empty($filters['salle_id'])) {
                        $addParams['salle_id'] = $filters['salle_id'];
                    }
                    
                    $addUrl = BASE_URL . 'documentation_client/add';
                    if (!empty($addParams)) {
                        $addUrl .= '?' . http_build_query($addParams);
                    }
                    ?>
                    <?php if (hasPermission('client_add_documentation')): ?>
                        <a href="<?= $addUrl ?>" class="btn btn-primary">
                            <i class="bi bi-plus me-2 me-1"></i>Ajouter un Document
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Filtres -->
    <div class="card mb-4">
        <div class="card-header py-2">
            <h6 class="card-title mb-0">Filtres</h6>
        </div>
        <div class="card-body py-2">
            <form method="get" action="" class="row g-3 align-items-end" id="filterForm">
                <div class="col-md-4">
                    <label for="site_id" class="form-label fw-bold mb-0">Site</label>
                    <select class="form-select bg-body text-body" id="site_id" name="site_id" onchange="updateRoomsAndSubmit()">
                        <option value="">Tous les sites</option>
                        <?php if (isset($sites) && is_array($sites)): ?>
                            <?php foreach ($sites as $site): ?>
                                <option value="<?= $site['id'] ?>" <?= ($filters['site_id'] ?? '') == $site['id'] ? 'selected' : '' ?>>
                                    <?= h($site['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="col-md-4">
                    <label for="salle_id" class="form-label fw-bold mb-0">Salle</label>
                    <select class="form-select bg-body text-body" id="salle_id" name="salle_id" onchange="document.getElementById('filterForm').submit();">
                        <option value="">Toutes les salles</option>
                        <?php foreach ($salles as $salle): ?>
                            <option value="<?= $salle['id'] ?>" <?= ($filters['salle_id'] ?? '') == $salle['id'] ? 'selected' : '' ?>>
                                <?= h($salle['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-4 d-flex align-items-end">
                    <a href="<?= BASE_URL ?>documentation_client" class="btn btn-outline-secondary">
                        <i class="bi bi-x-lg me-2 me-1"></i>Réinitialiser
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Liste de la documentation organisée -->
    <?php if (empty($documentation_organise)): ?>
        <div class="card">
            <div class="card-body text-center py-5">
                <i class="bi bi-file-text fa-3x text-muted mb-3 me-1"></i>
                <h5 class="text-muted">Aucune documentation trouvée</h5>
                <p class="text-muted mb-3">Aucun document ne correspond aux critères sélectionnés.</p>
                <?php
                // Construire l'URL d'ajout avec les paramètres de filtres
                $addParams = [];
                if (!empty($filters['site_id'])) {
                    $addParams['site_id'] = $filters['site_id'];
                }
                if (!empty($filters['salle_id'])) {
                    $addParams['salle_id'] = $filters['salle_id'];
                }
                
                $addUrl = BASE_URL . 'documentation_client/add';
                if (!empty($addParams)) {
                    $addUrl .= '?' . http_build_query($addParams);
                }
                ?>
                <?php if (hasPermission('client_add_documentation')): ?>
                    <a href="<?= $addUrl ?>" class="btn btn-primary">
                        <i class="bi bi-plus me-2 me-1"></i>Ajouter un Document
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($documentation_organise as $client_nom => $sites): ?>
            <div class="card mb-4">
                <div class="card-header bg-body-secondary">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-building me-2 text-primary me-1"></i>
                        <?= h($client_nom) ?>
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($sites as $site_nom => $salles): ?>
                        <div class="border-bottom">
                            <div class="p-3 bg-body-secondary bg-opacity-10">
                                <h6 class="mb-0">
                                    <i class="bi bi-geo-alt me-2 text-success me-1"></i>
                                    <?= h($site_nom) ?>
                                </h6>
                            </div>
                            <?php foreach ($salles as $salle_nom => $documents): ?>
                                <div class="border-bottom">
                                    <div class="p-3">
                                        <h6 class="mb-3">
                                            <i class="bi bi-door-open me-2 text-info me-1"></i>
                                            <?= h($salle_nom) ?>
                                            <span class="badge bg-secondary ms-2"><?= count($documents) ?> document(s)</span>
                                        </h6>

                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead class="table-light">
                                                    <tr>
                                                        <th style="width: 40%;">Document</th>
                                                        <th style="width: 15%;">Type</th>
                                                        <th style="width: 10%;">Taille</th>
                                                        <th style="width: 15%;">Date</th>
                                                        <th style="width: 10%;">User</th>
                                                        <th style="width: 10%;">Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($documents as $doc): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <i class="<?= getFileIcon($doc['type_fichier'] ?? '') ?> text-primary me-2"></i>
                                                                    <div class="flex-grow-1 min-w-0">
                                                                        <div class="fw-bold text-primary d-flex align-items-center gap-2">
                                                                            <span class="editable-name" 
                                                                                  data-id="<?= $doc['id'] ?>"
                                                                                  data-current-name="<?= h($doc['nom_personnalise'] ?? $doc['nom_fichier'] ?? 'Document sans nom') ?>"
                                                                                  style="cursor: pointer;"
                                                                                  title="Double-clic pour modifier">
                                                                                <?= h($doc['nom_personnalise'] ?? $doc['nom_fichier'] ?? 'Document sans nom') ?>
                                                                            </span>
                                                                            <?php if (hasPermission('client_add_documentation') && isset($doc['created_by']) && $doc['created_by'] == $_SESSION['user']['id']): ?>
                                                                                <button type="button" 
                                                                                        class="btn btn-sm btn-link p-0 edit-name-btn" 
                                                                                        data-id="<?= $doc['id'] ?>"
                                                                                        data-current-name="<?= h($doc['nom_personnalise'] ?? $doc['nom_fichier'] ?? 'Document sans nom') ?>"
                                                                                        title="Modifier le nom"
                                                                                        style="font-size: 0.75rem; line-height: 1;">
                                                                                    <i class="bi bi-pencil"></i>
                                                                                </button>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                        <?php if (!empty($doc['nom_personnalise']) && $doc['nom_personnalise'] !== $doc['nom_fichier']): ?>
                                                                            <small class="text-muted">
                                                                                <i class="bi bi-file-earmark me-1"></i>
                                                                                <?= h($doc['nom_fichier']) ?>
                                                                            </small>
                                                                        <?php endif; ?>
                                                                        <?php if (!empty($doc['description'])): ?>
                                                                            <small class="text-muted d-block">
                                                                                <i class="bi bi-chat-text me-1"></i>
                                                                                <?= h($doc['description']) ?>
                                                                            </small>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($doc['type_fichier'])): ?>
                                                                    <span class="badge bg-info"><?= strtoupper($doc['type_fichier']) ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($doc['taille_fichier']) && $doc['taille_fichier'] > 0): ?>
                                                                    <small class="text-muted"><?= formatFileSize($doc['taille_fichier']) ?></small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <small class="text-muted">
                                                                    <i class="bi bi-calendar me-1"></i>
                                                                    <?= formatDateFrench($doc['date_creation'] ?? '') ?>
                                                                </small>
                                                            </td>
                                                            <td>
                                                                <?php if (!empty($doc['uploader_name'])): ?>
                                                                    <small class="text-muted">
                                                                        <i class="bi bi-person me-1"></i>
                                                                        <?= h($doc['uploader_name']) ?>
                                                                    </small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>
                                                                <div class="d-flex gap-1">
                                                                    <!-- Bouton aperçu (pour images et PDF) -->
                                                                    <?php 
                                                                    $fileType = strtolower($doc['type_fichier'] ?? '');
                                                                    $canPreview = in_array($fileType, ['pdf', 'jpg', 'jpeg', 'png', 'gif']);
                                                                    ?>
                                                                    <?php if ($canPreview && !empty($doc['chemin_fichier'])): ?>
                                                                        <button type="button" 
                                                                                class="btn btn-sm btn-outline-info btn-action" 
                                                                                title="Aperçu"
                                                                                data-bs-toggle="modal" 
                                                                                data-bs-target="#previewModal<?= $doc['id'] ?>">
                                                                            <i class="bi bi-eye"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                    
                                                                    <!-- Bouton télécharger -->
                                                                    <?php if (!empty($doc['chemin_fichier'])): ?>
                                                                        <a href="<?= BASE_URL ?>documentation_client/download?attachment_id=<?= $doc['id'] ?>" 
                                                                           class="btn btn-sm btn-outline-success btn-action" 
                                                                           title="Télécharger">
                                                                            <i class="bi bi-download"></i>
                                                                        </a>
                                                                    <?php endif; ?>
                                                                    
                                                                    <!-- Bouton suppression (pour les documents uploadés par l'utilisateur) -->
                                                                    <?php if (hasPermission('client_add_documentation') && isset($doc['created_by']) && $doc['created_by'] == $_SESSION['user']['id']): ?>
                                                                        <button type="button" 
                                                                                class="btn btn-sm btn-outline-danger btn-action" 
                                                                                title="Supprimer"
                                                                                onclick="confirmDeleteDocument(<?= $doc['id'] ?>, '<?= h($doc['nom_personnalise'] ?? $doc['nom_fichier']) ?>')">
                                                                            <i class="bi bi-trash"></i>
                                                                        </button>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                        
                                                        <!-- Modal d'aperçu pour ce document -->
                                                        <?php if ($canPreview && !empty($doc['chemin_fichier'])): ?>
                                                            <?php 
                                                            $extension = strtolower(pathinfo($doc['nom_fichier'], PATHINFO_EXTENSION));
                                                            ?>
                                                            <div class="modal fade" id="previewModal<?= $doc['id'] ?>" tabindex="-1" aria-hidden="true">
                                                                <div class="modal-dialog modal-xl">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title"><?= h($doc['nom_personnalise'] ?? $doc['nom_fichier']) ?></h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <div class="preview-container">
                                                                                <?php if ($extension === 'pdf'): ?>
                                                                                    <iframe src="<?= BASE_URL ?>documentation_client/preview?attachment_id=<?= $doc['id'] ?>" 
                                                                                            width="100%" 
                                                                                            height="600px" 
                                                                                            frameborder="0">
                                                                                    </iframe>
                                                                                <?php elseif (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                                                                                    <img src="<?= BASE_URL ?>documentation_client/preview?attachment_id=<?= $doc['id'] ?>" 
                                                                                         class="img-fluid" 
                                                                                         alt="<?= h($doc['nom_personnalise'] ?? $doc['nom_fichier']) ?>"
                                                                                         onerror="handleImageError(this, <?= $doc['id'] ?>, '<?= h($doc['nom_personnalise'] ?? $doc['nom_fichier']) ?>')"
                                                                                         onload="handleImageLoad(this)">
                                                                                <?php else: ?>
                                                                                    <div class="alert alert-info">
                                                                                        <i class="bi bi-info-circle me-1"></i> 
                                                                                        Ce type de fichier ne peut pas être prévisualisé. 
                                                                                        <a href="<?= BASE_URL ?>documentation_client/download?attachment_id=<?= $doc['id'] ?>" 
                                                                                           class="alert-link" 
                                                                                           target="_blank">
                                                                                            Télécharger le fichier
                                                                                        </a>
                                                                                    </div>
                                                                                <?php endif; ?>
                                                                            </div>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <a href="<?= BASE_URL ?>documentation_client/download?attachment_id=<?= $doc['id'] ?>" 
                                                                               class="btn btn-primary" 
                                                                               target="_blank">
                                                                                <i class="bi bi-download me-1"></i> Télécharger
                                                                            </a>
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fermer</button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
// Variable globale pour l'URL de base
const baseUrl = '<?= BASE_URL ?>';

// Fonction pour mettre à jour les salles selon le site sélectionné ET soumettre le formulaire
function updateRoomsAndSubmit() {
    const siteId = document.getElementById('site_id').value;
    console.log('updateRoomsAndSubmit appelé avec siteId:', siteId);
    
    if (siteId) {
        const url = '<?= BASE_URL ?>documentation_client/get_rooms?site_id=' + siteId;
        console.log('URL de la requête:', url);
        
        fetch(url)
            .then(response => {
                console.log('Réponse reçue:', response);
                if (!response.ok) {
                    throw new Error('Erreur HTTP: ' + response.status);
                }
                return response.json();
            })
            .then(data => {
                console.log('Données reçues:', data);
                const roomSelect = document.getElementById('salle_id');
                roomSelect.innerHTML = '<option value="">Toutes les salles</option>';
                
                if (Array.isArray(data)) {
                    data.forEach(room => {
                        const option = document.createElement('option');
                        option.value = room.id;
                        option.textContent = room.name;
                        roomSelect.appendChild(option);
                    });
                }
                
                // Soumettre le formulaire après la mise à jour
                document.getElementById('filterForm').submit();
            })
            .catch(error => {
                console.error('Erreur lors de la mise à jour des salles:', error);
                alert('Erreur lors de la mise à jour des salles: ' + error.message);
            });
    } else {
        document.getElementById('salle_id').innerHTML = '<option value="">Toutes les salles</option>';
        
        // Soumettre le formulaire même si aucun site n'est sélectionné
        document.getElementById('filterForm').submit();
    }
}

// Les fonctions getFileIcon et formatFileSize sont maintenant définies en PHP dans functions.php

// Fonctions pour gérer l'aperçu des images
function handleImageError(img, attachmentId, fileName) {
    console.error('Erreur lors du chargement de l\'image:', fileName);
    console.error('URL de l\'image:', img.src);
    
    // Remplacer l'image par un message d'erreur avec option de téléchargement
    const container = img.parentElement;
    container.innerHTML = `
        <div class="alert alert-warning text-center">
            <i class="bi bi-exclamation-triangle me-2"></i>
            <strong>Impossible d'afficher l'aperçu de l'image</strong><br>
            <small class="text-muted">${fileName}</small><br><br>
            <a href="<?= BASE_URL ?>documentation_client/download?attachment_id=${attachmentId}" 
               class="btn btn-sm btn-outline-primary" 
               target="_blank">
                <i class="bi bi-download me-1"></i> Télécharger le fichier
            </a>
        </div>
    `;
}

function handleImageLoad(img) {
    // Image chargée avec succès
    console.log('Image chargée avec succès:', img.src);
    img.style.display = 'block';
    img.classList.add('img-fluid');
}

// Fonction pour confirmer la suppression d'un document
function confirmDeleteDocument(documentId, documentName) {
    if (confirm(`Êtes-vous sûr de vouloir supprimer le document "${documentName}" ?\n\nCette action est irréversible et supprimera définitivement le document et toutes ses données associées.`)) {
        // Rediriger vers la page de suppression
        window.location.href = `<?= BASE_URL ?>documentation_client/delete/${documentId}`;
    }
}

// Fonction pour éditer le nom personnalisé
function editDocumentName(element) {
    const currentName = element.getAttribute('data-current-name');
    const docId = element.getAttribute('data-id');
    const span = element;
    const parent = span.parentElement;
    const editBtn = parent.querySelector('.edit-name-btn');
    
    // Si déjà en édition, ne rien faire
    if (parent.querySelector('input.editing-name-input')) {
        return;
    }
    
    // Créer un input inline
    const input = document.createElement('input');
    input.type = 'text';
    input.className = 'form-control form-control-sm editing-name-input';
    input.value = currentName;
    input.style.minWidth = '200px';
    input.style.display = 'inline-block';
    
    // Cacher le span et le bouton
    span.style.display = 'none';
    if (editBtn) editBtn.style.display = 'none';
    
    // Ajouter l'input après le span
    parent.insertBefore(input, span.nextSibling);
    
    // Focus et sélectionner le texte
    input.focus();
    input.select();
    
    // Sauvegarder au Enter ou Escape
    const saveEdit = () => {
        const newName = input.value.trim();
        if (newName === currentName) {
            // Pas de changement, restaurer
            input.remove();
            span.style.display = '';
            if (editBtn) editBtn.style.display = '';
            return;
        }
        
        // Le nom peut être vide (on utilisera nom_fichier dans ce cas)
        // Désactiver l'input pendant la sauvegarde
        input.disabled = true;
        
        // Sauvegarder via AJAX (envoyer null si vide)
        const nomToSend = newName || '';
        fetch('<?= BASE_URL ?>documentation_client/updateName', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': '<?= csrf_token() ?>',
                'Content-Type': 'application/x-www-form-urlencoded',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: `attachment_id=${docId}&nom_personnalise=${encodeURIComponent(nomToSend)}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Mettre à jour l'affichage avec le nom affiché (nom_personnalise ou nom_fichier)
                const displayName = data.display_name || newName || currentName;
                span.setAttribute('data-current-name', displayName);
                span.textContent = displayName;
                if (editBtn) {
                    editBtn.setAttribute('data-current-name', displayName);
                }
                // Retirer l'input et réafficher
                input.remove();
                span.style.display = '';
                if (editBtn) editBtn.style.display = '';
                // Recharger la page pour mettre à jour l'affichage complet
                window.location.reload();
            } else {
                input.disabled = false;
                alert('Erreur : ' + (data.error || 'Erreur inconnue'));
                input.focus();
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            input.disabled = false;
            alert('Erreur de connexion');
            input.focus();
        });
    };
    
    const cancelEdit = () => {
        input.remove();
        span.style.display = '';
        if (editBtn) editBtn.style.display = '';
    };
    
    input.addEventListener('blur', saveEdit);
    input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            input.blur();
        } else if (e.key === 'Escape') {
            e.preventDefault();
            cancelEdit();
        }
    });
}

// S'assurer que les fonctions sont disponibles après le chargement du DOM
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM chargé, fonctions de filtres disponibles');
    
    // Vérifier que les éléments existent
    const siteSelect = document.getElementById('site_id');
    const roomSelect = document.getElementById('salle_id');
    
    console.log('Éléments trouvés:', {
        site: !!siteSelect,
        room: !!roomSelect
    });
    
    // Gérer l'édition des noms de documents
    document.querySelectorAll('.editable-name').forEach(element => {
        element.addEventListener('dblclick', function() {
            editDocumentName(this);
        });
    });
    
    document.querySelectorAll('.edit-name-btn').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            const span = this.parentElement.querySelector('.editable-name');
            if (span) {
                editDocumentName(span);
            }
        });
    });
    
    // Gérer les erreurs d'iframe pour les PDFs
    const iframes = document.querySelectorAll('iframe[src*="documentation_client/preview"]');
    iframes.forEach(iframe => {
        iframe.addEventListener('error', function() {
            const container = this.parentElement;
            const attachmentId = this.src.match(/attachment_id=(\d+)/)?.[1];
            container.innerHTML = `
                <div class="alert alert-warning text-center">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <strong>Impossible d'afficher l'aperçu du PDF</strong><br><br>
                    <a href="<?= BASE_URL ?>documentation_client/download?attachment_id=${attachmentId}" 
                       class="btn btn-sm btn-outline-primary" 
                       target="_blank">
                        <i class="bi bi-download me-1"></i> Télécharger le fichier
                    </a>
                </div>
            `;
        });
    });
});
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?>

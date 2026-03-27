<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue d'ajout de documentation client
 * Formulaire de création avec zone de glisser-déposer pour les documents
 */

// Vérifier si l'utilisateur est connecté
if (!isset($_SESSION['user'])) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

// Définir le type d'utilisateur pour le menu
$userType = $_SESSION['user']['user_type'] ?? null;

setPageVariables(
    'Ajouter de la Documentation',
    'documentation_client'
);

// Définir la page courante pour le menu
$currentPage = 'documentation_client';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';

// Récupérer les données depuis le contrôleur
$sites = $sites ?? [];
$rooms = $rooms ?? [];

// Récupérer le client_id depuis les sites autorisés (premier client trouvé)
$client_id = null;
if (!empty($sites)) {
    $client_id = $sites[0]['client_id'];
}
?>

<style>
/* Styles pour la zone de drag & drop */
.drop-zone {
    border: 2px dashed #dee2e6;
    border-radius: 0.5rem;
    padding: 2rem;
    text-align: center;
    background-color: #f8f9fa;
    transition: all 0.3s ease;
    cursor: pointer;
    min-height: 200px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.drop-zone:hover {
    border-color: #0d6efd;
    background-color: #e7f1ff;
}

.drop-zone.dragover {
    border-color: #198754;
    background-color: #d1e7dd;
    transform: scale(1.02);
}

.drop-message {
    color: #6c757d;
    font-size: 1.1rem;
    margin-bottom: 1rem;
}

.drop-message i {
    font-size: 2rem;
    color: #0d6efd;
    margin-bottom: 0.5rem;
}

/* Styles pour la liste des fichiers */
.file-list {
    margin-top: 1rem;
}

.file-item {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-bottom: 0.75rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    transition: all 0.2s ease;
}

.file-item:hover {
    box-shadow: 0 4px 8px rgba(0,0,0,0.15);
}

.file-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;
}

.file-info {
    display: flex;
    align-items: center;
    flex-grow: 1;
}

.file-icon {
    font-size: 1.5rem;
    margin-right: 0.75rem;
    color: #0d6efd;
}

.file-details {
    flex-grow: 1;
}

.file-name {
    font-weight: 600;
    margin-bottom: 0.25rem;
}

.file-size {
    font-size: 0.875rem;
    color: #6c757d;
}

.file-actions {
    display: flex;
    gap: 0.5rem;
}

.file-form {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 1rem;
    align-items: end;
}

.file-options {
    display: flex;
    gap: 1rem;
    align-items: center;
}

/* Styles pour les statistiques */
.stats {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
    padding: 1rem;
    margin-top: 1rem;
}

.progress-bar {
    width: 100%;
    height: 0.5rem;
    background-color: #e9ecef;
    border-radius: 0.25rem;
    overflow: hidden;
    margin-top: 0.5rem;
}

.progress-fill {
    height: 100%;
    background-color: #198754;
    transition: width 0.3s ease;
    width: 0%;
}

/* Styles pour les messages d'erreur */
.error-message {
    color: #dc3545;
    font-size: 0.875rem;
    margin-top: 0.25rem;
}

/* Styles pour les boutons */
.btn-remove {
    color: #dc3545;
    border: 1px solid #dc3545;
    background: transparent;
}

.btn-remove:hover {
    background-color: #dc3545;
    color: white;
}

/* Responsive */
@media (max-width: 768px) {
    .file-form {
        grid-template-columns: 1fr;
        gap: 0.75rem;
    }
    
    .file-options {
        flex-direction: column;
        align-items: stretch;
        gap: 0.5rem;
    }
}
</style>

<div class="container-fluid flex-grow-1 container-p-y">
    <!-- En-tête avec titre et bouton de retour -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bi bi-plus-circle me-2 me-1"></i>Ajouter de la Documentation
                    </h4>
                    <p class="text-muted mb-0">Upload de documents avec gestion des noms</p>
                </div>
                <div>
                    <?php
                    // Construire l'URL de retour avec les paramètres de filtres
                    $returnParams = [];
                    if (isset($_GET['site_id']) && !empty($_GET['site_id'])) {
                        $returnParams['site_id'] = $_GET['site_id'];
                    }
                    if (isset($_GET['salle_id']) && !empty($_GET['salle_id'])) {
                        $returnParams['salle_id'] = $_GET['salle_id'];
                    }
                    
                    $returnUrl = BASE_URL . 'documentation_client';
                    if (!empty($returnParams)) {
                        $returnUrl .= '?' . http_build_query($returnParams);
                    }
                    ?>
                    <a href="<?= $returnUrl ?>" class="btn btn-secondary">
                        <i class="bi bi-arrow-left me-2 me-1"></i>Retour à la liste
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Formulaire d'ajout -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-file-text me-2 me-1"></i>Informations de la Documentation
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= BASE_URL ?>documentation_client/store" class="needs-validation" novalidate id="documentationForm">
                <?= csrf_field() ?>
                <!-- Sélection du site/salle -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <label for="site_id" class="form-label fw-bold">Site</label>
                        <select class="form-select" id="site_id" name="site_id" onchange="updateRooms()">
                            <option value="">Sélectionner un site (optionnel)</option>
                            <?php if (isset($sites) && is_array($sites)): ?>
                                <?php foreach ($sites as $site): ?>
                                    <option value="<?= $site['id'] ?>" <?= (isset($_GET['site_id']) && $_GET['site_id'] == $site['id']) ? 'selected' : '' ?>>
                                        <?= h($site['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="col-md-6">
                        <label for="room_id" class="form-label fw-bold">Salle</label>
                        <select class="form-select" id="room_id" name="room_id">
                            <option value="">Sélectionner une salle (optionnel)</option>
                            <?php if (isset($rooms) && is_array($rooms)): ?>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?= $room['id'] ?>" <?= (isset($_GET['salle_id']) && $_GET['salle_id'] == $room['id']) ? 'selected' : '' ?>>
                                        <?= h($room['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                </div>

                <!-- Zone de drag & drop -->
                <div class="mb-4">
                    <label class="form-label fw-bold">Documents <span class="text-danger">*</span></label>
                    <div class="drop-zone" id="dropZone">
                        <div class="drop-message">
                            <i class="bi bi-cloud-upload"></i>
                            <div>Glissez-déposez vos documents ici</div>
                            <small class="text-muted">ou cliquez pour sélectionner</small>
                        </div>
                        
                        <input type="file" id="fileInput" multiple style="display: none;" 
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.txt,.jpg,.jpeg,.png,.gif,.zip,.rar">
                        
                        <div class="file-list" id="fileList"></div>
                        
                        <div class="stats" id="stats" style="display: none;">
                            <div class="row">
                                <div class="col-6">
                                    <strong>Fichiers valides:</strong> <span id="validCount">0</span>
                                </div>
                                <div class="col-6">
                                    <strong>Fichiers rejetés:</strong> <span id="invalidCount">0</span>
                                </div>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill"></div>
                            </div>
                        </div>
                    </div>
                    <div class="invalid-feedback" id="filesError">
                        Veuillez ajouter au moins un document.
                    </div>
                </div>

                <!-- Boutons d'action -->
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <button type="button" class="btn btn-outline-danger" id="clearAllBtn" onclick="clearAllFiles()" style="display: none;">
                            <i class="bi bi-trash me-1"></i>Vider la liste
                        </button>
                    </div>
                    <div>
                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                            <i class="bi bi-check-lg me-1"></i>Enregistrer la Documentation
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// Classe pour gérer le drag & drop et l'upload
class DocumentationUploader {
    constructor() {
        this.dropZone = document.getElementById('dropZone');
        this.fileInput = document.getElementById('fileInput');
        this.fileList = document.getElementById('fileList');
        this.stats = document.getElementById('stats');
        this.validCount = document.getElementById('validCount');
        this.invalidCount = document.getElementById('invalidCount');
        this.progressFill = document.getElementById('progressFill');
        this.clearAllBtn = document.getElementById('clearAllBtn');
        this.submitBtn = document.getElementById('submitBtn');
        this.filesError = document.getElementById('filesError');
        this.form = document.getElementById('documentationForm');
        
        this.files = [];
        // Limite dynamique du serveur PHP
        const phpMaxFileSize = '<?php echo ini_get("upload_max_filesize"); ?>';
        const phpPostMaxSize = '<?php echo ini_get("post_max_size"); ?>';
        this.maxSize = Math.min(parsePhpSize(phpMaxFileSize), parsePhpSize(phpPostMaxSize));
        this.allowedExtensions = [];
        
        this.init();
    }
    
    async init() {
        await this.loadAllowedExtensions();
        this.setupEventListeners();
    }
    
    async loadAllowedExtensions() {
        try {
            const response = await fetch('<?php echo BASE_URL; ?>settings/getAllowedExtensions');
            const data = await response.json();
            this.allowedExtensions = data.extensions || [];
        } catch (error) {
            console.error('Erreur lors du chargement des extensions autorisées:', error);
        }
    }
    
    setupEventListeners() {
        // Drag & Drop events
        this.dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            this.dropZone.classList.add('dragover');
        });
        
        this.dropZone.addEventListener('dragleave', (e) => {
            e.preventDefault();
            this.dropZone.classList.remove('dragover');
        });
        
        this.dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            this.dropZone.classList.remove('dragover');
            const files = Array.from(e.dataTransfer.files);
            this.handleFiles(files);
        });
        
        // Click to select files
        this.dropZone.addEventListener('click', (e) => {
            if (e.target === this.dropZone || e.target.classList.contains('drop-message') || e.target.closest('.drop-message')) {
                this.fileInput.click();
            }
        });
        
        this.fileInput.addEventListener('change', (e) => {
            const files = Array.from(e.target.files);
            this.handleFiles(files);
        });
        
        // Form submission
        this.form.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitForm();
        });
    }
    
    handleFiles(newFiles) {
        const validatedFiles = this.validateFiles(newFiles);
        this.files = [...this.files, ...validatedFiles];
        this.displayFiles();
        this.updateStats();
        this.updateSubmitButton();
    }
    
    validateFiles(files) {
        return files.map(file => {
            const validation = {
                file: file,
                valid: true,
                errors: []
            };
            
            // Vérifier la taille
            if (file.size > this.maxSize) {
                validation.valid = false;
                validation.errors.push('Fichier trop volumineux (max ' + formatFileSize(this.maxSize) + ')');
            }
            
            // Vérifier l'extension
            const extension = file.name.split('.').pop().toLowerCase();
            if (!this.allowedExtensions.includes(extension)) {
                validation.valid = false;
                validation.errors.push('Type de fichier non autorisé');
            }
            
            return validation;
        });
    }
    
    displayFiles() {
        this.fileList.innerHTML = '';
        
        this.files.forEach((fileData, index) => {
            const fileItem = document.createElement('div');
            fileItem.className = 'file-item';
            fileItem.innerHTML = `
                <div class="file-header">
                    <div class="file-info">
                        <i class="${this.getFileIcon(fileData.file.type)} file-icon"></i>
                        <div class="file-details">
                            <div class="file-name">${fileData.file.name}</div>
                            <div class="file-size">${this.formatFileSize(fileData.file.size)}</div>
                        </div>
                    </div>
                    <div class="file-actions">
                        <button type="button" class="btn btn-sm btn-remove" onclick="removeFile(${index})">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div class="file-form">
                    <div>
                        <label class="form-label">Nom personnalisé</label>
                        <input type="text" class="form-control" name="custom_names[]" 
                               value="${fileData.file.name}" placeholder="Nom du document">
                        <input type="hidden" name="file_names[]" value="${fileData.file.name}">
                        <input type="hidden" name="file_sizes[]" value="${fileData.file.size}">
                        <input type="hidden" name="file_types[]" value="${fileData.file.type}">
                    </div>
                </div>
                ${fileData.errors.length > 0 ? `
                    <div class="error-message">
                        <i class="bi bi-exclamation-triangle me-1"></i>
                        ${fileData.errors.join(', ')}
                    </div>
                ` : ''}
            `;
            
            this.fileList.appendChild(fileItem);
        });
    }
    
    updateStats() {
        const validFiles = this.files.filter(f => f.valid);
        const invalidFiles = this.files.filter(f => !f.valid);
        
        this.validCount.textContent = validFiles.length;
        this.invalidCount.textContent = invalidFiles.length;
        
        if (this.files.length > 0) {
            this.stats.style.display = 'block';
            this.clearAllBtn.style.display = 'inline-block';
            
            const progress = (validFiles.length / this.files.length) * 100;
            this.progressFill.style.width = `${progress}%`;
        } else {
            this.stats.style.display = 'none';
            this.clearAllBtn.style.display = 'none';
        }
    }
    
    updateSubmitButton() {
        const validFiles = this.files.filter(f => f.valid);
        
        if (validFiles.length > 0) {
            this.submitBtn.disabled = false;
            this.filesError.style.display = 'none';
        } else {
            this.submitBtn.disabled = true;
            if (validFiles.length === 0) {
                this.filesError.style.display = 'block';
            }
        }
    }
    
    submitForm() {
        const validFiles = this.files.filter(f => f.valid);
        if (validFiles.length === 0) {
            alert('Veuillez ajouter au moins un fichier valide.');
            return;
        }
        
        // Créer un FormData pour l'upload
        const formData = new FormData();
        
        // Ajouter les paramètres du formulaire
        formData.append('site_id', document.getElementById('site_id').value || '');
        formData.append('room_id', document.getElementById('room_id').value || '');
        
        // Ajouter les fichiers
        validFiles.forEach((fileData, index) => {
            formData.append('files[]', fileData.file);
            const customNameInput = document.querySelectorAll(`input[name="custom_names[]"]`)[index];
            formData.append('custom_names[]', customNameInput ? customNameInput.value : fileData.file.name);
        });
        
        // Désactiver le bouton de soumission
        this.submitBtn.disabled = true;
        this.submitBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Enregistrement...';
        
        // Envoyer la requête
        fetch('<?= BASE_URL ?>documentation_client/store', {
            method: 'POST',
            headers: {
                'X-CSRF-Token': '<?= csrf_token() ?>',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: formData
        })
        .then(response => {
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                // Rediriger vers la liste avec les filtres
                const returnParams = new URLSearchParams();
                if (document.getElementById('site_id').value) returnParams.set('site_id', document.getElementById('site_id').value);
                if (document.getElementById('room_id').value) returnParams.set('salle_id', document.getElementById('room_id').value);
                
                window.location.href = `<?= BASE_URL ?>documentation_client${returnParams.toString() ? '?' + returnParams.toString() : ''}`;
            } else {
                // Vérifier si c'est une erreur de session
                if (data.error && data.error.includes('Session expirée')) {
                    alert('Votre session a expiré. Vous allez être redirigé vers la page de connexion.');
                    window.location.href = '<?= BASE_URL ?>auth/login';
                } else {
                    alert('Erreur lors de l\'enregistrement: ' + (data.error || 'Erreur inconnue'));
                    this.submitBtn.disabled = false;
                    this.submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Enregistrer la Documentation';
                }
            }
        })
        .catch(error => {
            console.error('Erreur:', error);
            alert('Erreur de connexion: ' + error.message);
            this.submitBtn.disabled = false;
            this.submitBtn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Enregistrer la Documentation';
        });
    }
    
    getFileIcon(fileType) {
        if (fileType.includes('pdf')) return 'bi bi-file-pdf';
        if (fileType.includes('word')) return 'bi bi-file-word';
        if (fileType.includes('excel') || fileType.includes('sheet')) return 'bi bi-file-excel';
        if (fileType.includes('image')) return 'bi bi-file-image';
        if (fileType.includes('zip') || fileType.includes('rar')) return 'bi bi-file-zip';
        if (fileType.includes('text')) return 'bi bi-file-text';
        return 'bi bi-file';
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
    }
}

// Variables globales
let uploader;

// Fonctions globales
function removeFile(index) {
    uploader.files.splice(index, 1);
    uploader.displayFiles();
    uploader.updateStats();
    uploader.updateSubmitButton();
}

function clearAllFiles() {
    if (confirm('Êtes-vous sûr de vouloir supprimer tous les fichiers ?')) {
        uploader.files = [];
        uploader.displayFiles();
        uploader.updateStats();
        uploader.updateSubmitButton();
    }
}

function updateRooms() {
    const siteId = document.getElementById('site_id').value;
    const roomSelect = document.getElementById('room_id');
    
    if (siteId) {
        fetch(`<?= BASE_URL ?>documentation_client/get_rooms?site_id=${siteId}`)
            .then(response => response.json())
            .then(data => {
                roomSelect.innerHTML = '<option value="">Sélectionner une salle (optionnel)</option>';
                if (Array.isArray(data)) {
                    data.forEach(room => {
                        const option = document.createElement('option');
                        option.value = room.id;
                        option.textContent = room.name;
                        roomSelect.appendChild(option);
                    });
                }
            })
            .catch(error => {
                console.error('Erreur lors de la mise à jour des salles:', error);
            });
    } else {
        roomSelect.innerHTML = '<option value="">Sélectionner une salle (optionnel)</option>';
    }
}

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    uploader = new DocumentationUploader();
});
</script>

<?php
// Inclure le footer
include_once __DIR__ . '/../../includes/footer.php';
?>

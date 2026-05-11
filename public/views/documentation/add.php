<?php
require_once __DIR__ . '/../../includes/functions.php';
/**
 * Vue d'ajout de documentation
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
    'documentation'
);

// Définir la page courante pour le menu
$currentPage = 'documentation';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
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
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        transition: all 0.2s ease;
    }

    .file-item:hover {
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
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

    .error-message {
        color: #dc3545;
        font-size: 0.875rem;
        margin-top: 0.25rem;
    }

    .btn-remove {
        color: #dc3545;
        border: 1px solid #dc3545;
        background: transparent;
    }

    .btn-remove:hover {
        background-color: #dc3545;
        color: white;
    }

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
    <!-- En-tête -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h4 class="fw-bold mb-1">
                        <i class="bi bi-plus-circle me-2 me-1"></i>Ajouter de la Documentation
                    </h4>
                    <p class="text-muted mb-0">Upload de documents avec gestion des noms et visibilité</p>
                </div>
                <div>
                    <?php
                    $returnParams = [];
                    if (isset($_GET['client_id']) && !empty($_GET['client_id'])) {
                        $returnParams['client_id'] = $_GET['client_id'];
                    }
                    if (isset($_GET['site_id']) && !empty($_GET['site_id'])) {
                        $returnParams['site_id'] = $_GET['site_id'];
                    }
                    if (isset($_GET['building_id']) && !empty($_GET['building_id'])) {
                        $returnParams['building_id'] = $_GET['building_id'];
                    }
                    if (isset($_GET['salle_id']) && !empty($_GET['salle_id'])) {
                        $returnParams['salle_id'] = $_GET['salle_id'];
                    }

                    $returnUrl = BASE_URL . 'documentation';
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

    <!-- Formulaire -->
    <div class="card">
        <div class="card-header">
            <h5 class="card-title mb-0">
                <i class="bi bi-file-text me-2 me-1"></i>Informations de la Documentation
            </h5>
        </div>
        <div class="card-body">
            <form method="POST" action="<?= BASE_URL ?>documentation/store" class="needs-validation" novalidate
                id="dragDropForm" enctype="multipart/form-data">
                <?= csrf_field() ?>

                <!-- Sélection client/site/bâtiment/salle -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <label for="client_id" class="form-label fw-bold">Client <span
                                class="text-danger">*</span></label>
                        <select class="form-select" id="client_id" name="client_id" required>
                            <option value="">Sélectionner un client</option>
                            <?php if (isset($clients) && is_array($clients)): ?>
                                <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['id'] ?>" <?= (isset($_GET['client_id']) && $_GET['client_id'] == $client['id']) ? 'selected' : '' ?>>
                                        <?= h($client['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                        <div class="invalid-feedback">Veuillez sélectionner un client.</div>
                    </div>

                    <div class="col-md-3">
                        <label for="site_id" class="form-label fw-bold">Site</label>
                        <select class="form-select" id="site_id" name="site_id">
                            <option value="">Sélectionner un site</option>
                            <?php if (isset($sites) && is_array($sites)): ?>
                                <?php foreach ($sites as $site): ?>
                                    <option value="<?= $site['id'] ?>" <?= (isset($_GET['site_id']) && $_GET['site_id'] == $site['id']) ? 'selected' : '' ?>>
                                        <?= h($site['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="building_id" class="form-label fw-bold">Bâtiment</label>
                        <select class="form-select" id="building_id" name="building_id">
                            <option value="">Sélectionner un bâtiment</option>
                            <?php if (isset($buildings) && is_array($buildings)): ?>
                                <?php foreach ($buildings as $building): ?>
                                    <option value="<?= $building['id'] ?>" <?= (isset($_GET['building_id']) && $_GET['building_id'] == $building['id']) ? 'selected' : '' ?>>
                                        <?= h($building['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <label for="room_id" class="form-label fw-bold">Salle</label>
                        <select class="form-select" id="room_id" name="room_id">
                            <option value="">Sélectionner une salle</option>
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
                            accept="<?php require_once INCLUDES_PATH . '/FileUploadValidator.php';
                            echo FileUploadValidator::getAcceptAttribute($GLOBALS['db']); ?>">

                        <div class="file-list" id="fileList"></div>

                        <div class="stats" id="stats" style="display: none;">
                            <div class="row">
                                <div class="col-6"><strong>Fichiers valides:</strong> <span id="validCount">0</span>
                                </div>
                                <div class="col-6"><strong>Fichiers rejetés:</strong> <span id="invalidCount">0</span>
                                </div>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" id="progressFill"></div>
                            </div>
                        </div>
                    </div>
                    <div class="invalid-feedback" id="filesError" style="display: none;">Veuillez ajouter au moins un
                        document.</div>
                </div>

                <div id="filesOptions" style="display: none;">
                    <h6 class="mb-3">Options des fichiers</h6>
                    <div id="filesOptionsList"></div>
                </div>

                <!-- Boutons d'action -->
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <button type="button" class="btn btn-outline-danger" id="clearAllBtn" style="display: none;">
                            <i class="bi bi-trash me-1"></i>Vider la liste
                        </button>
                    </div>
                    <div>
                        <button type="button" class="btn btn-primary" id="uploadValidBtn" style="display: none;">
                            <i class="bi bi-upload me-1"></i>Uploader les fichiers
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
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

    // Classes
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
            this.uploadBtn = document.getElementById('uploadValidBtn');
            this.filesError = document.getElementById('filesError');
            this.form = document.getElementById('dragDropForm');

            this.files = [];
            const phpMaxFileSize = '<?php echo ini_get("upload_max_filesize"); ?>';
            const phpPostMaxSize = '<?php echo ini_get("post_max_size"); ?>';
            this.maxSize = Math.min(this.parsePhpSize(phpMaxFileSize), this.parsePhpSize(phpPostMaxSize));
            this.allowedExtensions = [];

            this.init();
        }

        parsePhpSize(size) {
            const units = { 'K': 1024, 'M': 1048576, 'G': 1073741824 };
            const match = String(size).match(/^(\d+)([KMG])?$/i);
            if (!match) return 0;
            return parseInt(match[1]) * (units[match[2]?.toUpperCase()] || 1);
        }

        formatFileSize(bytes) {
            if (bytes === 0) return '0 B';
            const k = 1024;
            const sizes = ['B', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
        }

        getFileIcon(fileType) {
            if (fileType?.includes('pdf')) return 'bi bi-file-pdf';
            if (fileType?.includes('word')) return 'bi bi-file-word';
            if (fileType?.includes('excel') || fileType?.includes('sheet')) return 'bi bi-file-excel';
            if (fileType?.includes('image')) return 'bi bi-file-image';
            if (fileType?.includes('zip') || fileType?.includes('rar')) return 'bi bi-file-zip';
            if (fileType?.includes('text')) return 'bi bi-file-text';
            return 'bi bi-file';
        }

        async init() {
            await this.loadAllowedExtensions();
            this.setupEventListeners();
            this.updateSubmitButton();
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

            this.dropZone.addEventListener('click', (e) => {
                if (e.target === this.dropZone || e.target.classList.contains('drop-message') || e.target.closest('.drop-message')) {
                    this.fileInput.click();
                }
            });

            this.fileInput.addEventListener('change', (e) => {
                const files = Array.from(e.target.files);
                this.handleFiles(files);
            });

            this.uploadBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this.submitForm();
            });

            this.clearAllBtn.addEventListener('click', () => {
                clearAllFiles();
            });

            // Écouter les changements de client/site/bâtiment
            document.getElementById('client_id').addEventListener('change', () => this.updateSites());
            document.getElementById('site_id').addEventListener('change', () => this.updateBuildings());
            document.getElementById('building_id').addEventListener('change', () => this.updateRooms());
        }

        updateSites() {
            const clientId = document.getElementById('client_id').value;
            const siteSelect = document.getElementById('site_id');
            const buildingSelect = document.getElementById('building_id');
            const roomSelect = document.getElementById('room_id');

            buildingSelect.innerHTML = '<option value="">Sélectionner un bâtiment</option>';
            roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';

            if (clientId) {
                fetch(`<?= BASE_URL ?>documentation/get_sites?client_id=${clientId}`)
                    .then(response => response.json())
                    .then(data => {
                        siteSelect.innerHTML = '<option value="">Sélectionner un site</option>';
                        if (Array.isArray(data)) {
                            data.forEach(site => {
                                const option = document.createElement('option');
                                option.value = site.id;
                                option.textContent = site.name;
                                siteSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(error => console.error('Erreur lors de la mise à jour des sites:', error));
            } else {
                siteSelect.innerHTML = '<option value="">Sélectionner un site</option>';
            }

            this.updateSubmitButton();
        }

        updateBuildings() {
            const siteId = document.getElementById('site_id').value;
            const buildingSelect = document.getElementById('building_id');
            const roomSelect = document.getElementById('room_id');

            roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';

            if (siteId) {
                fetch(`<?= BASE_URL ?>documentation/get_buildings?site_id=${siteId}`)
                    .then(response => response.json())
                    .then(data => {
                        buildingSelect.innerHTML = '<option value="">Sélectionner un bâtiment</option>';
                        if (Array.isArray(data)) {
                            data.forEach(building => {
                                const option = document.createElement('option');
                                option.value = building.id;
                                option.textContent = building.name;
                                buildingSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(error => console.error('Erreur lors de la mise à jour des bâtiments:', error));
            } else {
                buildingSelect.innerHTML = '<option value="">Sélectionner un bâtiment</option>';
            }
        }

        updateRooms() {
            const buildingId = document.getElementById('building_id').value;
            const roomSelect = document.getElementById('room_id');

            if (buildingId) {
                fetch(`<?= BASE_URL ?>documentation/get_rooms_by_building?building_id=${buildingId}`)
                    .then(response => response.json())
                    .then(data => {
                        roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';
                        if (Array.isArray(data)) {
                            data.forEach(room => {
                                const option = document.createElement('option');
                                option.value = room.id;
                                option.textContent = room.name;
                                roomSelect.appendChild(option);
                            });
                        }
                    })
                    .catch(error => console.error('Erreur lors de la mise à jour des salles:', error));
            } else {
                roomSelect.innerHTML = '<option value="">Sélectionner une salle</option>';
            }
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
                const validation = { file: file, valid: true, errors: [] };

                if (file.size > this.maxSize) {
                    validation.valid = false;
                    validation.errors.push('Trop volumineux (max ' + this.formatFileSize(this.maxSize) + ')');
                }

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
                            <div class="file-name">${this.escapeHtml(fileData.file.name)}</div>
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
                        <input type="text" class="form-control" name="custom_names[]" value="${this.escapeHtml(fileData.file.name)}">
                        <input type="hidden" name="file_names[]" value="${this.escapeHtml(fileData.file.name)}">
                        <input type="hidden" name="file_sizes[]" value="${fileData.file.size}">
                        <input type="hidden" name="file_types[]" value="${fileData.file.type}">
                    </div>
                    <div class="file-options">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="visible_by_client[]" value="1" checked>
                            <label class="form-check-label">Visible par le client</label>
                        </div>
                    </div>
                </div>
                ${fileData.errors.length ? `<div class="error-message"><i class="bi bi-exclamation-triangle me-1"></i>${fileData.errors.join(', ')}</div>` : ''}
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
                this.uploadBtn.style.display = 'inline-block';
                this.progressFill.style.width = `${(validFiles.length / this.files.length) * 100}%`;
            } else {
                this.stats.style.display = 'none';
                this.clearAllBtn.style.display = 'none';
                this.uploadBtn.style.display = 'none';
            }
        }

        updateSubmitButton() {
            const validFiles = this.files.filter(f => f.valid);
            const clientId = document.getElementById('client_id').value;

            if (validFiles.length > 0 && clientId) {
                this.filesError.style.display = 'none';
            } else {
                this.filesError.style.display = validFiles.length === 0 ? 'block' : 'none';
            }
        }

        submitForm() {
            const validFiles = this.files.filter(f => f.valid);
            if (validFiles.length === 0) {
                alert('Veuillez ajouter au moins un fichier valide.');
                return;
            }

            const clientId = document.getElementById('client_id').value;
            if (!clientId) {
                alert('Veuillez sélectionner un client.');
                return;
            }

            const formData = new FormData();
            formData.append('client_id', clientId);
            formData.append('site_id', document.getElementById('site_id').value || '');
            formData.append('building_id', document.getElementById('building_id').value || '');
            formData.append('room_id', document.getElementById('room_id').value || '');

            validFiles.forEach((fileData, index) => {
                formData.append('files[]', fileData.file);
                const customName = document.querySelectorAll('input[name="custom_names[]"]')[index];
                formData.append('custom_names[]', customName ? customName.value : fileData.file.name);
                formData.append('visible_by_client[]', document.querySelectorAll('input[name="visible_by_client[]"]')[index]?.checked ? '1' : '0');
            });

            this.uploadBtn.disabled = true;
            this.uploadBtn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Envoi...';

            fetch('<?= BASE_URL ?>documentation/store', {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': '<?= csrf_token() ?>',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const returnParams = new URLSearchParams();
                        if (clientId) returnParams.set('client_id', clientId);
                        if (document.getElementById('site_id').value) returnParams.set('site_id', document.getElementById('site_id').value);
                        if (document.getElementById('building_id').value) returnParams.set('building_id', document.getElementById('building_id').value);
                        if (document.getElementById('room_id').value) returnParams.set('salle_id', document.getElementById('room_id').value);

                        window.location.href = `<?= BASE_URL ?>documentation?${returnParams.toString()}`;
                    } else {
                        alert('Erreur: ' + (data.error || 'Erreur inconnue'));
                        this.uploadBtn.disabled = false;
                        this.uploadBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Uploader les fichiers';
                    }
                })
                .catch(error => {
                    console.error('Erreur:', error);
                    alert('Erreur de connexion: ' + error.message);
                    this.uploadBtn.disabled = false;
                    this.uploadBtn.innerHTML = '<i class="bi bi-upload me-1"></i>Uploader les fichiers';
                });
        }

        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }
    }

    // Initialisation
    document.addEventListener('DOMContentLoaded', function () {
        uploader = new DocumentationUploader();

        // Initialiser les listes déroulantes si des valeurs sont présentes
        const clientId = document.getElementById('client_id').value;
        const siteId = document.getElementById('site_id').value;
        const buildingId = document.getElementById('building_id').value;

        if (clientId) uploader.updateSites();
        if (siteId) uploader.updateBuildings();
        if (buildingId) uploader.updateRooms();
    });
</script>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?>
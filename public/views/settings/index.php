<?php
require_once __DIR__ . '/../../includes/functions.php';

// Vérifier si l'utilisateur est connecté et est admin
if (!isset($_SESSION['user']) || !isAdmin()) {
    header('Location: ' . BASE_URL . 'auth/login');
    exit;
}

setPageVariables('Paramètres', 'settings');

// Définir la page courante pour le menu
$currentPage = 'settings';

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
                <i class="bi bi-gear me-2 me-1"></i>Paramètres du système
            </h4>
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
        <!-- Section Gestion des contrats -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-file-earmark-text text-primary me-2 me-1"></i>
                        Gestion des contrats
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="<?= BASE_URL ?>settings/contractTypes" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-tags me-2 text-info me-1"></i>
                                <strong>Types de contrats</strong>
                                <br><small class="text-muted">Gérer les types de contrats et leurs paramètres par défaut</small>
                            </div>
                            <i class="bi bi-chevron-right text-muted me-1"></i>
                        </a>
                        <a href="<?= BASE_URL ?>settings/accessLevels" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-layers me-2 text-warning me-1"></i>
                                <strong>Niveaux d'accès matériels</strong>
                                <br><small class="text-muted">Configurer la visibilité des champs matériels par niveau de contrat</small>
                            </div>
                            <i class="bi bi-chevron-right text-muted me-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section Configuration système -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-hdd-network text-success me-2 me-1"></i>
                        Configuration système
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center disabled">
                            <div>
                                <i class="bi bi-database me-2 text-secondary me-1"></i>
                                <strong>Paramètres de base de données</strong>
                                <br><small class="text-muted">Configuration des connexions et optimisations</small>
                            </div>
                            <span class="badge bg-secondary">Bientôt</span>
                        </a>
                        <a href="<?= BASE_URL ?>settings/icons" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-palette me-2 text-primary me-1"></i>
                                <strong>Configuration des icônes</strong>
                                <br><small class="text-muted">Personnaliser les icônes d'action du site</small>
                                <br><small class="text-danger fw-bold">ne pas toucher merci</small>
                            </div>
                            <i class="bi bi-chevron-right text-muted me-1"></i>
                        </a>
                        <a href="<?= BASE_URL ?>settings/fileExtensions" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-file-earmark-arrow-up me-2 text-primary me-1"></i>
                                <strong>Extensions de fichiers autorisées</strong>
                                <br><small class="text-muted">Gérer les types de fichiers autorisés à l'upload</small>
                            </div>
                            <i class="bi bi-chevron-right text-muted me-1"></i>
                        </a>
                        <a href="<?= BASE_URL ?>settings/interventionTypes" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-tools me-2 text-primary me-1"></i>
                                <strong>Types d'intervention</strong>
                                <br><small class="text-muted">Gérer les types d'intervention et leurs paramètres de transport</small>
                            </div>
                            <i class="bi bi-chevron-right text-muted me-1"></i>
                        </a>
                        <a href="<?= BASE_URL ?>settings/userTypes" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-people me-2 text-info me-1"></i>
                                <strong>Types d'utilisateur</strong>
                                <br><small class="text-muted">Gérer les types d'utilisateur et leurs descriptions</small>
                            </div>
                            <i class="bi bi-chevron-right text-muted me-1"></i>
                        </a>
                        <a href="<?= BASE_URL ?>settings/configuration" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-sliders me-2 text-success me-1"></i>
                                <strong>Configuration système</strong>
                                <br><small class="text-muted">Paramètres d'intervention (tarifs, coefficients, etc.)</small>
                            </div>
                            <i class="bi bi-chevron-right text-muted me-1"></i>
                        </a>
                        <a href="<?= BASE_URL ?>settings/email" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-envelope me-2 text-primary me-1"></i>
                                <strong>Configuration email</strong>
                                <br><small class="text-muted">Paramètres SMTP et templates d'emails</small>
                            </div>
                            <i class="bi bi-chevron-right text-muted me-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>



        <!-- Section Maintenance -->
        <div class="col-md-6 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-tools text-danger me-2 me-1"></i>
                        Maintenance
                    </h5>
                </div>
                <div class="card-body">
                    <div class="list-group list-group-flush">
                        <a href="<?= BASE_URL ?>settings/exportRoomsUrls" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <div>
                                <i class="bi bi-file-earmark-excel me-2 text-success me-1"></i>
                                <strong>Export URLs des salles</strong>
                                <br><small class="text-muted">Exporter toutes les URLs des salles en Excel (un onglet par client)</small>
                            </div>
                            <i class="bi bi-chevron-right text-muted me-1"></i>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center disabled">
                            <div>
                                <i class="bi bi-download me-2 text-secondary me-1"></i>
                                <strong>Sauvegardes</strong>
                                <br><small class="text-muted">Gérer les sauvegardes automatiques</small>
                            </div>
                            <span class="badge bg-secondary">Bientôt</span>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center disabled">
                            <div>
                                <i class="bi bi-graph-up me-2 text-secondary me-1"></i>
                                <strong>Logs et monitoring</strong>
                                <br><small class="text-muted">Surveiller les performances et erreurs</small>
                            </div>
                            <span class="badge bg-secondary">Bientôt</span>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Section Informations système -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">
                        <i class="bi bi-info-circle text-info me-2 me-1"></i>
                        Informations système
                    </h5>
                </div>
                <div class="card-body">
                    <?php
                    global $db;
                    // Récupérer la limite effective d'upload (en octets, puis formatée)
                    $max_upload_bytes = getServerMaxUploadSize();
                    $max_upload = formatFileSize($max_upload_bytes);
                    
                    // Calculer la taille du répertoire d'upload
                    $uploadDirSize = 0;
                    $uploadDirFormatted = '0 B';
                    if (defined('UPLOADS_PATH') && is_dir(UPLOADS_PATH)) {
                        $uploadDirSize = getDirectorySize(UPLOADS_PATH);
                        $uploadDirFormatted = formatFileSize($uploadDirSize);
                    }
                    ?>
                    <div class="row">
                        <div class="col-md-2">
                            <div class="text-center">
                                <h6 class="text-muted">Version PHP</h6>
                                <p class="h5"><?= PHP_VERSION ?></p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <h6 class="text-muted">Max Upload</h6>
                                <p class="h5"><?= $max_upload ?></p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <h6 class="text-muted">Version MySQL</h6>
                                <p class="h5"><?= $db->getAttribute(PDO::ATTR_SERVER_VERSION) ?></p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <h6 class="text-muted">Taille répertoire upload</h6>
                                <p class="h5"><?= h($uploadDirFormatted) ?></p>
                                <small class="text-muted">
                                    <i class="bi bi-folder2 me-1"></i>
                                    <?= h(basename(UPLOADS_PATH)) ?>
                                </small>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <h6 class="text-muted">Espace disque</h6>
                                <p class="h5"><?= round(disk_free_space('.') / 1024 / 1024 / 1024, 1) ?> GB</p>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="text-center">
                                <h6 class="text-muted">Mémoire utilisée</h6>
                                <p class="h5"><?= round(memory_get_usage(true) / 1024 / 1024, 1) ?> MB</p>
                            </div>
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
<?php
// Les données sont récupérées par le contrôleur SettingsController
// Variables disponibles : $icons
// Les variables de page sont déjà définies par le contrôleur

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <div class="d-flex bd-highlight mb-3">
        <div class="p-2 bd-highlight">
            <h4 class="py-4 mb-6">Configuration des icônes</h4>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success">
            <?php 
            echo $_SESSION['success'];
            unset($_SESSION['success']);
            ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger">
            <?php 
            echo $_SESSION['error'];
            unset($_SESSION['error']);
            ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h5 class="card-title">Personnalisation des icônes d'action</h5>
            <p class="card-text">Modifiez les icônes utilisées dans tout le site pour les actions principales.</p>
        </div>
        <div class="card-body">
            <form action="<?php echo BASE_URL; ?>settings/updateIcons" method="post">
                <?php 
                // Les icônes sont récupérées par le contrôleur SettingsController
                // Variable disponible : $icons
                ?>
                
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead class="table-dark">
                            <tr>
                                <th style="width: 20%">Clé</th>
                                <th style="width: 15%">Aperçu</th>
                                <th style="width: 35%">Classe CSS</th>
                                <th style="width: 30%">Description</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($icons as $icon): ?>
                            <tr>
                                <td>
                                    <strong><?php echo ucfirst($icon['icon_key']); ?></strong>
                                    <br>
                                    <small class="text-muted">Clé: <?php echo $icon['icon_key']; ?></small>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="<?php echo h($icon['icon_class']); ?>" style="font-size: 1.5em; color: #6c757d;"></i>
                                        <span class="ms-2 text-muted small"><?php echo $icon['icon_class']; ?></span>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" 
                                           name="icons[<?php echo $icon['icon_key']; ?>][class]" 
                                           class="form-control form-control-sm bg-body text-body" 
                                           value="<?php echo h($icon['icon_class']); ?>"
                                           placeholder="bi bi-eye">
                                    <small class="form-text text-muted">Ex: bi bi-eye, bi bi-pencil, bi bi-trash</small>
                                </td>
                                <td>
                                    <small class="text-muted"><?php echo $icon['description']; ?></small>
                                    <br>
                                    <span class="badge bg-<?php echo $icon['is_active'] ? 'success' : 'secondary'; ?> badge-sm">
                                        <?php echo $icon['is_active'] ? 'Actif' : 'Inactif'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-lg me-1 me-1"></i> Enregistrer les modifications
                    </button>
                    <a href="<?php echo BASE_URL; ?>settings" class="btn btn-secondary ms-2">
                        <i class="bi bi-arrow-left me-1 me-1"></i> Retour
                    </a>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
include_once __DIR__ . '/../../includes/footer.php';
?> 
<?php
// Vérification de l'accès direct
if (!defined('BASE_URL')) {
    header('Location: ' . BASE_URL);
    exit;
}

// Inclure les fonctions utilitaires
require_once __DIR__ . '/../../includes/functions.php';

// Définir la page courante pour le menu
$currentPage = 'qrcode';

// Inclure le header qui contient le menu latéral
include_once __DIR__ . '/../../includes/header.php';
include_once __DIR__ . '/../../includes/sidebar.php';
include_once __DIR__ . '/../../includes/navbar.php';
?>

<div class="container-fluid flex-grow-1 container-p-y">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4 class="card-title mb-0">
                        <i class="bi bi-qr-code me-2"></i>
                        QR Codes - <?php echo h($site['name']); ?>
                    </h4>
                    <div>
                        <button type="button" class="btn btn-primary" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i> Imprimer
                        </button>
                        <a href="<?php echo BASE_URL; ?>clients/edit/<?php echo $site['client_id']; ?>#sites" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Retour
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($salles)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Aucune salle active trouvée pour ce site.
                        </div>
                    <?php else: ?>
                        <div class="row">
                            <?php foreach ($salles as $salle): ?>
                                <div class="col-md-6 col-lg-4 mb-4">
                                    <div class="card h-100 border">
                                        <div class="card-body text-center">
                                            <h6 class="card-title mb-3">
                                                <?php echo h($salle['name']); ?>
                                            </h6>
                                            
                                                                                                                                      <!-- QR Codes côte à côte -->
                                             <div class="row">
                                                 <!-- QR Code VideoSonic -->
                                                 <div class="col-6">
                                                     <div class="qr-code-container">
                                                         <?php 
                                                         $staffQR = $this->generateQRCodeBase64($this->generateQRUrl($salle['id'], 'staff'), 100);
                                                         if ($staffQR): ?>
                                                             <img src="<?php echo $staffQR; ?>" alt="QR Code VideoSonic" class="qr-code" />
                                                         <?php else: ?>
                                                             <div class="qr-code-placeholder">
                                                                 <i class="bi bi-qr-code"></i>
                                                                 <small>QR Code VideoSonic</small>
                                                             </div>
                                                         <?php endif; ?>
                                                     </div>
                                                     <small class="text-muted d-block mt-2">VideoSonic</small>
                                                 </div>

                                                 <!-- QR Code Client -->
                                                 <div class="col-6">
                                                     <div class="qr-code-container">
                                                         <?php 
                                                         $clientQR = $this->generateQRCodeBase64($this->generateQRUrl($salle['id'], 'client'), 100);
                                                         if ($clientQR): ?>
                                                             <img src="<?php echo $clientQR; ?>" alt="QR Code Client" class="qr-code" />
                                                         <?php else: ?>
                                                             <div class="qr-code-placeholder">
                                                                 <i class="bi bi-qr-code"></i>
                                                                 <small>QR Code Client</small>
                                                             </div>
                                                         <?php endif; ?>
                                                     </div>
                                                     <small class="text-muted d-block mt-2">Client</small>
                                                 </div>
                                             </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
@media print {
    .card-header .btn,
    .sidebar,
    .navbar {
        display: none !important;
    }
    
    .container-fluid {
        padding: 0 !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    .qr-code {
        page-break-inside: avoid;
    }
}

.qr-code-container {
    display: inline-block;
    padding: 10px;
    background: white;
    border: 1px solid #ddd;
    border-radius: 4px;
}

.qr-code {
    width: 100px;
    height: 100px;
}

.qr-code-placeholder {
    width: 100px;
    height: 100px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 4px;
    color: #6c757d;
}

.qr-code-placeholder i {
    font-size: 2rem;
    margin-bottom: 0.5rem;
}
</style>



<?php include_once __DIR__ . '/../../includes/footer.php'; ?>

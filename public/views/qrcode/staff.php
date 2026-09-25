<?php
if (!defined('BASE_URL')) {
    header('Location: ' . BASE_URL);
    exit;
}
require_once __DIR__ . '/../../includes/functions.php';
$currentPage = 'qrcode';
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
                        QR Codes - Staff
                    </h4>
                    <div>
                        <button type="button" class="btn btn-primary" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i> Imprimer
                        </button>
                        <a href="<?php echo BASE_URL; ?>user" class="btn btn-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Retour
                        </a>
                    </div>
                </div>
                <div class="card-body">
                    <?php if (empty($staffMembers)): ?>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle me-2"></i>
                            Aucun membre du staff trouvé.
                        </div>
                    <?php else: ?>
                        <div class="d-flex flex-wrap gap-3">
                            <?php foreach ($staffMembers as $staff): ?>
                                <div class="vip-item">
                                    <div class="card h-100 border vip-card">
                                        <div class="card-body text-center p-2">
                                            <h6 class="card-title mb-1">
                                                <i class="bi bi-person-badge text-primary me-1"></i>
                                                <?php echo h($staff['first_name'] . ' ' . $staff['last_name']); ?>
                                            </h6>
                                            <div class="qr-code-container">
                                                <?php
                                                $staffQR = $this->generateQRCodeBase64(
                                                    $this->generateStaffQRUrl($staff['id']),
                                                    240
                                                );
                                                if ($staffQR): ?>
                                                    <img src="<?php echo $staffQR; ?>" alt="QR Code Staff" class="qr-code" />
                                                <?php else: ?>
                                                    <div class="qr-code-placeholder">
                                                        <i class="bi bi-qr-code"></i>
                                                        <small>QR Code Staff</small>
                                                    </div>
                                                <?php endif; ?>
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
    @page {
        size: A4;
        margin: 0;
    }

    @media print {

        .card-header .btn,
        .sidebar,
        .navbar {
            display: none !important;
        }

        .container-fluid {
            padding: 10mm !important;
        }

        .card {
            border: none !important;
            box-shadow: none !important;
        }

        .card .card {
            border: 1px solid #ddd !important;
            page-break-inside: avoid;
        }

        .qr-code {
            page-break-inside: avoid;
        }
    }

    .vip-item {
        width: 170px;
    }

    .vip-card .card-body {
        padding: 0.5rem !important;
    }

    .vip-card .card-title {
        font-size: 0.85rem;
        line-height: 1.2;
    }

    .qr-code-container {
        display: inline-block;
        padding: 4px;
        background: white;
        border: 1px solid #ddd;
        border-radius: 4px;
    }

    .qr-code {
        display: block;
        width: 120px;
        height: 120px;
    }

    .qr-code-placeholder {
        width: 120px;
        height: 120px;
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
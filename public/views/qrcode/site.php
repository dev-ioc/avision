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
                        QR Codes -
                        <?php echo h($site['name']); ?>
                    </h4>
                    <div>
                        <button type="button" class="btn btn-primary" id="printQrBtn">
                            <i class="bi bi-printer me-1"></i> Imprimer
                        </button>
                        <a href="<?php echo BASE_URL; ?>clients/edit/<?php echo $site['client_id']; ?>#sites"
                            class="btn btn-secondary">
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
                                                <?php echo !empty($salle['batiment_name'])
                                                    ? h($salle['batiment_name']) . ' — ' . h($salle['name'])
                                                    : h($salle['name']); ?>
                                            </h6>
                                            <!-- QR Codes côte à côte -->
                                            <div class="row">
                                                <!-- QR Code VideoSonic -->
                                                <div class="col-6">
                                                    <div class="qr-code-container">
                                                        <?php
                                                        $staffQR = $this->generateQRCodeBase64($this->generateQRUrl($salle['id'], 'staff'), 100);
                                                        if ($staffQR): ?>
                                                            <img src="<?php echo $staffQR; ?>" alt="QR Code VideoSonic"
                                                                class="qr-code" />
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
                                                            <img src="<?php echo $clientQR; ?>" alt="QR Code Client"
                                                                class="qr-code" />
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
<!-- Modale de confirmation d'impression -->
<div class="modal fade" id="printConfirmModal" tabindex="-1" aria-labelledby="printConfirmTitle" aria-hidden="true"
    data-bs-backdrop="static">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <!-- En-tête : sert aussi de poignée pour déplacer la modale -->
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title" id="printConfirmTitle">
                    Confirmer l'impression
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
            </div>
            <div class="modal-body text-center p-4 pt-2">
                <div class="print-confirm-icon mb-3">
                    <i class="bi bi-printer"></i>
                </div>
                <p class="text-muted mb-4">
                    Les QR codes vont être imprimés. Une fois l'impression terminée, les salles de ce site seront
                    marquées comme <strong>« QR code édité »</strong>.
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">
                        Annuler
                    </button>
                    <button type="button" class="btn btn-primary" id="confirmPrintedBtn">
                        <i class="bi bi-printer me-1"></i>
                        Imprimer et marquer comme édités
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast top right -->
<div class="toast-container position-fixed top-0 end-0 p-3" style="z-index: 1100;">
    <div id="qrToast" class="toast align-items-center border-0" role="alert" aria-live="assertive" aria-atomic="true"
        data-bs-delay="4000">
        <div class="d-flex">
            <div class="toast-body d-flex align-items-center">
                <i class="bi me-2 fs-5" id="qrToastIcon"></i>
                <span id="qrToastMessage"></span>
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
                aria-label="Fermer"></button>
        </div>
    </div>
</div>
<?php if (!empty($salles)): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const csrfToken = '<?= csrf_token() ?>';
            const markUrl = '<?= BASE_URL ?>qrcode/markPrinted/site/<?= (int) $site['id'] ?>';

            const modalEl = document.getElementById('printConfirmModal');
            const modal = new bootstrap.Modal(modalEl);
            const confirmBtn = document.getElementById('confirmPrintedBtn');

            const toastEl = document.getElementById('qrToast');
            const toast = bootstrap.Toast.getOrCreateInstance(toastEl);

            // Devient true uniquement après validation de la modale
            let pendingMark = false;

            function showToast(message, type) {
                const isSuccess = type === 'success';
                toastEl.classList.remove('text-bg-success', 'text-bg-danger');
                toastEl.classList.add(isSuccess ? 'text-bg-success' : 'text-bg-danger');
                document.getElementById('qrToastIcon').className =
                    'bi me-2 fs-5 ' + (isSuccess ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill');
                document.getElementById('qrToastMessage').textContent = message;
                toast.show();
            }

            function markAsPrinted() {
                fetch(markUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-Token': csrfToken,
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify({ csrf_token: csrfToken })
                })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            showToast('Les QR codes ont bien été marqués comme édités.', 'success');
                        } else {
                            showToast(data.message || 'Erreur lors de la mise à jour.', 'danger');
                        }
                    })
                    .catch(() => {
                        showToast('Erreur réseau lors de la mise à jour.', 'danger');
                    });
            }

            // 1. Le clic sur Imprimer ouvre d'abord la modale de confirmation
            document.getElementById('printQrBtn').addEventListener('click', function () {
                modal.show();
            });

            // 2. Validation : on ferme la modale, puis on lance l'impression
            confirmBtn.addEventListener('click', function () {
                pendingMark = true;
                modalEl.addEventListener('hidden.bs.modal', function () {
                    // Petit délai pour que la modale et le fond soient bien retirés
                    setTimeout(function () { window.print(); }, 150);
                }, { once: true });
                modal.hide();
            });

            // 3. Après l'impression, on marque les salles comme éditées
            window.addEventListener('afterprint', function () {
                if (!pendingMark) return;
                pendingMark = false;
                markAsPrinted();
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('.modal').forEach(function (modal) {
                modal.addEventListener('hidden.bs.modal', function () {
                    const dialog = modal.querySelector('.modal-dialog');
                    if (dialog) {
                        dialog.style.position = '';
                        dialog.style.left = '';
                        dialog.style.top = '';
                        dialog.style.margin = '';
                        dialog.style.width = '';
                        dialog.style.maxWidth = '';
                    }
                });

                modal.addEventListener('shown.bs.modal', function () {
                    const dialog = modal.querySelector('.modal-dialog');
                    const header = modal.querySelector('.modal-header');
                    if (!dialog || !header) return;
                    if (header.dataset.draggable) return;
                    header.dataset.draggable = 'true';

                    header.style.cursor = 'grab';

                    let isDragging = false;
                    let startX, startY, startLeft, startTop;

                    header.addEventListener('mousedown', function (e) {
                        if (e.target.closest('button')) return;

                        isDragging = true;
                        header.style.cursor = 'grabbing';

                        const rect = dialog.getBoundingClientRect();
                        startX = e.clientX;
                        startY = e.clientY;
                        startLeft = rect.left;
                        startTop = rect.top;
                        dialog.style.width = rect.width + 'px';
                        dialog.style.maxWidth = 'none';
                        dialog.style.position = 'fixed';
                        dialog.style.left = startLeft + 'px';
                        dialog.style.top = startTop + 'px';
                        dialog.style.margin = '0';
                    });

                    document.addEventListener('mousemove', function (e) {
                        if (!isDragging) return;
                        const dx = e.clientX - startX;
                        const dy = e.clientY - startY;
                        dialog.style.left = (startLeft + dx) + 'px';
                        dialog.style.top = (startTop + dy) + 'px';
                    });

                    document.addEventListener('mouseup', function () {
                        if (isDragging) {
                            isDragging = false;
                            header.style.cursor = 'grab';
                        }
                    });
                });

            });
        });
    </script>
<?php endif; ?>
<style>
    @page {
        margin: 0;
    }

    @media print {

        .card-header .btn,
        .sidebar,
        .navbar,
        .modal,
        .modal-backdrop,
        .toast-container {
            display: none !important;
        }

        /* Compense la marge de page à 0 pour que le contenu ne colle pas aux bords */
        .container-fluid {
            padding: 15mm !important;
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

    .print-confirm-icon {
        width: 64px;
        height: 64px;
        margin: 0 auto;
        display: flex;
        align-items: center;
        justify-content: center;
        border-radius: 50%;
        background: rgba(13, 110, 253, 0.1);
        color: #0d6efd;
        font-size: 1.8rem;
    }
</style>



<?php include_once __DIR__ . '/../../includes/footer.php'; ?>
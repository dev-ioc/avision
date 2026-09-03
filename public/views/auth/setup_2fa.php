<?php
if (!defined('BASE_URL')) {
    header('Location: ' . BASE_URL);
    exit;
}
$cancelUrl = isClient()
    ? BASE_URL . 'profileClient'
    : BASE_URL . 'user/view/' . $_SESSION['user']['id'];
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo h($pageTitle ?? 'Activer la 2FA'); ?> -
        <?php echo SITE_NAME; ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="modal fade" id="passkeyNameModal" tabindex="-1" aria-labelledby="passkeyNameModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="passkeyNameModalLabel">Nommer votre passkey</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fermer"></button>
                </div>
                <div class="modal-body">
                    <p class="text-muted small mb-3">
                        Donnez un nom à cet appareil pour le reconnaître facilement dans la liste.
                    </p>
                    <label for="passkeyNameInput" class="form-label">Nom de l'appareil</label>
                    <input type="text" class="form-control" id="passkeyNameInput" placeholder="ex : iPhone de Jean"
                        maxlength="100" autocomplete="off">
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Annuler</button>
                    <button type="button" class="btn btn-primary" id="passkeyNameConfirmBtn">Continuer</button>
                </div>
            </div>
        </div>
    </div>
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h1 class="h3 mb-4">Activer la double authentification</h1>

                        <!-- Onglets -->
                        <ul class="nav nav-tabs mb-4" id="twoFactorTabs" role="tablist">
                            <!-- <li class="nav-item" role="presentation">
                                <button class="nav-link active" id="totp-tab" data-bs-toggle="tab"
                                    data-bs-target="#totp" type="button" role="tab" aria-controls="totp"
                                    aria-selected="true">
                                    Application d'authentification
                                </button>
                            </li> -->

                            <li class="nav-item" role="presentation">
                                <button class="nav-link" id="passkey-tab" data-bs-toggle="tab" data-bs-target="#passkey"
                                    type="button" role="tab" aria-controls="passkey" aria-selected="true">
                                    Passkeys
                                </button>
                            </li>
                        </ul>

                        <div class="tab-content" id="twoFactorTabsContent">
                            <!-- <div class="tab-pane fade show active" id="totp" role="tabpanel" aria-labelledby="totp-tab">

                                <p class="text-muted">
                                    1. Installez une application d'authentification (Google
                                    Authenticator, Microsoft Authenticator, Authy...) sur votre téléphone.
                                    <br>
                                    2. Scannez le QR code ci-dessous avec cette application.
                                    <br>
                                    3. Saisissez le code à 6 chiffres généré pour confirmer l'activation.
                                </p>

                                <?php if (isset($_SESSION['error'])): ?>
                                    <div class="alert alert-danger">
                                        <?php echo h($_SESSION['error']); ?>
                                        <?php unset($_SESSION['error']); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="text-center my-4">
                                    <img src="<?php echo $qrCodeDataUri; ?>" alt="QR code d'activation 2FA"
                                        class="img-fluid border rounded p-2" style="max-width:250px;">
                                </div>

                                <form method="POST" action="<?php echo BASE_URL; ?>auth/confirm-setup-2fa">
                                    <?= csrf_field() ?>

                                    <div class="mb-3">
                                        <label for="totp_code" class="form-label">
                                            Code de confirmation
                                        </label>

                                        <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                                            class="form-control text-center" id="totp_code" name="totp_code" autofocus
                                            style="font-size:1.5rem;letter-spacing:0.5rem;">
                                    </div>

                                    <button type="submit" class="btn btn-primary">
                                        Activer la double authentification
                                    </button>
                                    <a href="<?php echo $cancelUrl; ?>" class="btn btn-secondary" type="button">
                                        Annuler
                                    </a>
                                </form>
                            </div> -->

                            <!-- ===================================================== -->
                            <!-- TAB PASSKEY -->
                            <!-- ===================================================== -->
                            <div class="tab-pane fade show active" id="passkey" role="tabpanel"
                                aria-labelledby="passkey-tab">

                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h2 class="h5 mb-0">
                                        Mes passkeys
                                    </h2>

                                    <button type="button" class="btn btn-sm btn-primary"
                                        onclick="registerPasskey(this)">
                                        + Ajouter une passkey
                                    </button>
                                </div>

                                <p class="text-muted">
                                    Connectez-vous sans mot de passe grâce à Face ID, Touch ID ou une
                                    clé de sécurité.
                                </p>

                                <ul id="passkey-list" class="list-group mb-3">
                                    <!-- rempli en JS -->
                                </ul>

                                <p id="passkey-empty" class="text-muted small d-none">
                                    Aucune passkey enregistrée.
                                </p>

                                <a href="<?php echo $cancelUrl; ?>" class="btn btn-secondary" type="button">
                                    Retour
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <?= csrf_field() ?>

                <script>
                    const BASE_URL = <?php echo json_encode(BASE_URL); ?>;
                </script>

                <script src="<?php echo BASE_URL; ?>assets/js/webauthn.js"></script>

                <script>
                    async function loadPasskeys() {
                        try {
                            const resp = await fetch(
                                BASE_URL + 'auth/webauthn-credentials',
                                {
                                    method: 'GET',
                                    headers: {
                                        'X-Requested-With': 'XMLHttpRequest',
                                        'Accept': 'application/json'
                                    },
                                    credentials: 'same-origin'
                                }
                            );

                            const contentType = resp.headers.get('content-type') || '';

                            if (!resp.ok) {
                                const text = await resp.text();

                                console.error(
                                    'Erreur HTTP lors du chargement des passkeys :',
                                    resp.status
                                );

                                console.error(
                                    'Réponse serveur :',
                                    text
                                );

                                return;
                            }

                            if (!contentType.includes('application/json')) {
                                const text = await resp.text();

                                console.error(
                                    'La réponse du serveur n\'est pas du JSON.'
                                );

                                console.error(
                                    'Content-Type :',
                                    contentType
                                );

                                console.error(
                                    'Réponse serveur :',
                                    text
                                );

                                return;
                            }

                            const data = await resp.json();

                            const list = document.getElementById('passkey-list');
                            const empty = document.getElementById('passkey-empty');

                            list.innerHTML = '';

                            if (!data.success || data.credentials.length === 0) {
                                empty.classList.remove('d-none');
                                return;
                            }

                            empty.classList.add('d-none');

                            data.credentials.forEach(cred => {
                                const li = document.createElement('li');

                                li.className =
                                    'list-group-item d-flex justify-content-between align-items-center';

                                li.innerHTML = `
                                    <span>
                                        <strong>${cred.name || 'Appareil sans nom'}</strong>
                                        <br>
                                        <small class="text-muted">
                                            Ajoutée le ${new Date(
                                    cred.created_at
                                ).toLocaleDateString('fr-FR')}
                                        </small>
                                    </span>

                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger">
                                        Supprimer
                                    </button>
                                `;

                                li.querySelector('button').onclick =
                                    () => deletePasskey(cred.id, li);

                                list.appendChild(li);
                            });

                        } catch (error) {
                            console.error(
                                'Erreur lors du chargement des passkeys :',
                                error
                            );
                        }
                    }

                    // Charger les passkeys lorsqu'on ouvre l'onglet
                    document.getElementById('passkey-tab')
                        .addEventListener('shown.bs.tab', function () {
                            loadPasskeys();
                        });
                </script>

                <!-- Bootstrap JavaScript nécessaire pour les onglets -->
                <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

            </div>
        </div>
    </div>
</body>
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

</html>
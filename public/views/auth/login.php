<?php
// Vérification de l'accès direct
if (!defined('BASE_URL')) {
    header('Location: ' . BASE_URL);
    exit;
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion - <?php echo SITE_NAME; ?></title>
    <!-- Bootstrap CSS from CDN -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body.login-page {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
        }

        .card {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
            border-radius: 0.5rem;
        }

        .card-body {
            padding: 2rem;
        }

        .btn-primary {
            padding: 0.75rem;
            font-weight: 500;
        }

        .forgot-password-link {
            color: #6c757d;
        }

        .forgot-password-link:hover {
            background-color: #edeef0;
        }
    </style>
</head>

<body class="login-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h1 class="text-center mb-4">Connexion</h1>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?php echo h($_SESSION['error']); ?>
                                <?php unset($_SESSION['error']); ?>
                            </div>
                        <?php endif; ?>

                        <div id="webauthn-error" class="alert alert-danger d-none"></div>

                        <!-- Bouton passkey : affiché uniquement si le navigateur le supporte (JS) -->
                        <button type="button" id="passkey-login-btn" class="btn btn-outline-primary w-100 mb-3 d-none"
                            onclick="loginWithPasskey(this)">
                            <!-- <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" fill="currentColor"
                                class="bi bi-fingerprint me-2" viewBox="0 0 16 16">
                                <path
                                    d="M8.06 6.5a.5.5 0 0 1 .5.5c0 .98-.06 1.926-.364 2.717a.5.5 0 0 1-.933-.359C7.5 9.075 7.56 8.34 7.56 7.5a.5.5 0 0 1 .5-.5Z" />
                            </svg> -->
                            Se connecter avec une passkey
                        </button>

                        <div class="text-center text-muted small mb-3" id="passkey-divider" style="display:none;">
                            <hr class="d-inline-block" style="width:40%; vertical-align:middle;">
                            ou
                            <hr class="d-inline-block" style="width:40%; vertical-align:middle;">
                        </div>

                        <form method="POST" action="<?php echo BASE_URL; ?>auth/login">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label for="email" class="form-label">Adresse e-mail</label>
                                <input type="text" class="form-control" id="email" name="email" required>
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label">Mot de passe</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Se connecter</button>
                        </form>
                        <div class="text-center mt-3">
                            <a href="<?php echo BASE_URL; ?>auth/forgot-password" type="button"
                                class="btn w-100 forgot-password-link">Mot de passe oublié ?</a>
                        </div>
                    </div>

                    <script>const BASE_URL = <?php echo json_encode(BASE_URL); ?>;</script>
                    <script src="<?php echo BASE_URL; ?>assets/js/webauthn.js"></script>
                    <script>
                        // On affiche le bouton uniquement si le navigateur supporte WebAuthn
                        // (évite de proposer une option non fonctionnelle sur vieux navigateurs)
                        if (isWebauthnSupported()) {
                            document.getElementById('passkey-login-btn').classList.remove('d-none');
                            document.getElementById('passkey-divider').style.display = 'block';
                        }
                    </script>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS from CDN -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
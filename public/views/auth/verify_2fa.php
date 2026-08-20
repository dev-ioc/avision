<?php
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
    <title>Vérification en deux étapes -
        <?php echo SITE_NAME; ?>
    </title>
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

        .otp-input {
            font-size: 1.5rem;
            letter-spacing: 0.5rem;
            text-align: center;
        }
    </style>
</head>

<body class="login-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h1 class="text-center mb-3 h3">Vérification en deux étapes</h1>
                        <p class="text-muted text-center mb-4">
                            Ouvrez votre application d'authentification et saisissez le code à 6 chiffres.
                        </p>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?php echo h($_SESSION['error']); ?>
                                <?php unset($_SESSION['error']); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?php echo BASE_URL; ?>auth/verify-2fa">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label for="totp_code" class="form-label">Code de vérification</label>
                                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                                    class="form-control otp-input" id="totp_code" name="totp_code"
                                    autocomplete="one-time-code" autofocus>
                            </div>
                            <button type="submit" class="btn btn-primary w-100 mb-2">Valider</button>
                        </form>

                        <details class="mt-3">
                            <summary class="text-muted" style="cursor:pointer;">Téléphone perdu ? Utiliser un code de
                                secours</summary>
                            <form method="POST" action="<?php echo BASE_URL; ?>auth/verify-2fa" class="mt-3">
                                <?= csrf_field() ?>
                                <div class="mb-3">
                                    <label for="backup_code" class="form-label">Code de secours</label>
                                    <input type="text" class="form-control" id="backup_code" name="backup_code"
                                        placeholder="XXXX-XXXX">
                                </div>
                                <button type="submit" class="btn btn-outline-secondary w-100">Valider avec un code de
                                    secours</button>
                            </form>
                        </details>

                        <div class="text-center mt-3">
                            <a href="<?php echo BASE_URL; ?>auth/cancel-2fa" class="text-muted small">Annuler et revenir
                                à la connexion</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
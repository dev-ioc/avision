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
    <title>
        <?php echo h($pageTitle ?? 'Activer la 2FA'); ?> -
        <?php echo SITE_NAME; ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h1 class="h3 mb-3">Activer la double authentification</h1>
                        <p class="text-muted">
                            1. Installez une application d'authentification (Google Authenticator, Microsoft
                            Authenticator, Authy...) sur votre téléphone.<br>
                            2. Scannez le QR code ci-dessous avec cette application.<br>
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
                                class="img-fluid border rounded p-2" style="max-width: 250px;">
                        </div>

                        <form method="POST" action="<?php echo BASE_URL; ?>auth/confirm-setup-2fa">
                            <?= csrf_field() ?>
                            <div class="mb-3">
                                <label for="totp_code" class="form-label">Code de confirmation</label>
                                <input type="text" inputmode="numeric" pattern="[0-9]*" maxlength="6"
                                    class="form-control text-center" id="totp_code" name="totp_code" autofocus
                                    style="font-size:1.5rem; letter-spacing:0.5rem;">
                            </div>
                            <button type="submit" class="btn btn-primary">Activer la double authentification</button>
                            <a href="<?php echo BASE_URL; ?>profileClient" class="btn btn-secondary"
                                type="button">Annuler</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
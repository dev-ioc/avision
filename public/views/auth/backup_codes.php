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
        <?php echo h($pageTitle ?? 'Codes de secours'); ?> -
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
                        <div class="alert alert-success">La double authentification est activée sur votre compte.</div>

                        <h1 class="h4 mb-3">Vos codes de secours</h1>
                        <p class="text-muted">
                            Conservez ces codes dans un endroit sûr (gestionnaire de mots de passe, coffre-fort...).
                            Ils vous permettront de vous connecter si vous perdez l'accès à votre application
                            d'authentification. <strong>Ils ne seront affichés qu'une seule fois.</strong>
                        </p>

                        <div class="bg-light border rounded p-3 my-3">
                            <div class="row row-cols-2 g-2 font-monospace text-center">
                                <?php foreach ($backupCodes as $code): ?>
                                    <div class="col">
                                        <?php echo h($code); ?>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <a href="<?php echo BASE_URL; ?>dashboard" class="btn btn-primary">J'ai noté mes codes,
                            continuer</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>
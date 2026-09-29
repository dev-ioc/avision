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
    <title>Récupération de compte -
        <?php echo SITE_NAME; ?>
    </title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body.login-page {
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 2rem 0;
        }

        .card {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
            border-radius: 0.5rem;
        }

        .card-body {
            padding: 2rem;
        }
    </style>
</head>

<body class="login-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-8 col-lg-5">
                <div class="card">
                    <div class="card-body">
                        <h1 class="h3 text-center mb-3">Je n'ai plus accès à mon e-mail</h1>
                        <p class="text-muted small mb-4">
                            Si votre adresse e-mail est bloquée ou inaccessible et que vous n'avez pas d'adresse de
                            secours,
                            remplissez ce formulaire. Un administrateur vous recontactera et vérifiera votre identité
                            avant toute modification.
                        </p>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?php echo h($_SESSION['error']);
                                unset($_SESSION['error']); ?>
                            </div>
                        <?php endif; ?>

                        <form method="POST" action="<?php echo BASE_URL; ?>auth/request-recovery">
                            <?= csrf_field() ?>

                            <!-- Honeypot anti-robot : doit rester vide -->
                            <div style="position:absolute; left:-9999px;" aria-hidden="true">
                                <input type="text" name="website" tabindex="-1" autocomplete="off">
                            </div>

                            <div class="mb-3">
                                <label for="name" class="form-label">Nom et prénom *</label>
                                <input type="text" class="form-control" id="name" name="name" maxlength="100" required>
                            </div>

                            <div class="mb-3">
                                <label for="old_email" class="form-label">Ancienne adresse e-mail du compte</label>
                                <input type="email" class="form-control" id="old_email" name="old_email"
                                    maxlength="255">
                                <div class="form-text">Si vous vous en souvenez.</div>
                            </div>

                            <div class="mb-3">
                                <label for="contact" class="form-label">Moyen de vous contacter *</label>
                                <input type="text" class="form-control" id="contact" name="contact" maxlength="255"
                                    placeholder="Téléphone ou autre adresse e-mail" required>
                            </div>

                            <div class="mb-3">
                                <label for="message" class="form-label">Précisions</label>
                                <textarea class="form-control" id="message" name="message" rows="3" maxlength="1000"
                                    placeholder="Société, site concerné, fonction..."></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">Envoyer la demande</button>
                        </form>

                        <div class="text-center mt-3">
                            <a href="<?php echo BASE_URL; ?>auth/login">Retour à la connexion</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>

</html>
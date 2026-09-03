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
    <title>Mot de passe oublié -
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

        #pageOverlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.6);
            z-index: 9999;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: not-allowed;
        }
    </style>
</head>

<body class="login-page">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="card">
                    <div class="card-body">
                        <h1 class="text-center mb-3">Mot de passe oublié</h1>
                        <p class="text-muted text-center mb-4">
                            Saisissez votre email, nous vous enverrons un lien pour choisir un nouveau mot de passe.
                        </p>

                        <?php if (isset($_SESSION['error'])): ?>
                            <div class="alert alert-danger">
                                <?php echo h($_SESSION['error']);
                                unset($_SESSION['error']); ?>
                            </div>
                        <?php endif; ?>
                        <?php if (isset($_SESSION['success'])): ?>
                            <div class="alert alert-success">
                                <?php echo h($_SESSION['success']);
                                unset($_SESSION['success']); ?>
                            </div>
                        <?php endif; ?>
                        <div id="formMessage" class="d-none mb-3"></div>
                        <form method="POST" action="<?php echo BASE_URL; ?>auth/forgot-password"
                            id="forgotPasswordForm">

                            <?= csrf_field() ?>

                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>

                                <input type="email" class="form-control" id="email" name="email" required autofocus>
                            </div>

                            <button type="submit" class="btn btn-primary w-100" id="submitBtn">
                                <span id="submitText">Envoyer le lien</span>

                                <span id="submitSpinner" class="spinner-border spinner-border-sm d-none" role="status"
                                    aria-hidden="true"></span>
                            </button>

                        </form>

                        <div class="text-center mt-3">
                            <a href="<?php echo BASE_URL; ?>auth/login">Retour à la connexion</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Overlay de blocage pendant l'envoi -->
    <div id="pageOverlay" class="d-none">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Chargement...</span>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('forgotPasswordForm').addEventListener('submit', async function (e) {

            e.preventDefault();

            const form = this;

            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            // const submitSpinner = document.getElementById('submitSpinner');
            const formMessage = document.getElementById('formMessage');
            const emailInput = document.getElementById('email');
            const pageOverlay = document.getElementById('pageOverlay');

            // Bloquer toute la page + verrouiller le champ email
            pageOverlay.classList.remove('d-none');
            emailInput.readOnly = true;
            submitBtn.disabled = true;
            submitText.textContent = 'Envoi en cours...';
            // submitSpinner.classList.remove('d-none');
            // 
            // Cacher l'ancien message
            formMessage.className = 'd-none mb-3';
            formMessage.textContent = '';

            try {

                const formData = new FormData(form);

                const response = await fetch(form.action, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                let data;

                try {
                    data = await response.json();
                } catch (error) {
                    throw new Error('La réponse du serveur n\'est pas un JSON valide.');
                }

                formMessage.textContent = data.message;

                if (data.success) {
                    formMessage.className = 'alert alert-success mb-3';
                    form.reset();
                } else {
                    formMessage.className = 'alert alert-danger mb-3';
                }

            } catch (error) {

                console.error('Erreur:', error);

                formMessage.className = 'alert alert-danger mb-3';
                formMessage.textContent =
                    'Une erreur est survenue. Veuillez réessayer plus tard.';

            } finally {
                // Débloquer la page
                pageOverlay.classList.add('d-none');
                emailInput.readOnly = false;
                submitBtn.disabled = false;
                submitText.textContent = 'Envoyer le lien';
                submitSpinner.classList.add('d-none');
            }
        });
    </script>
</body>

</html>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Réinitialisation du mot de passe</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            min-height: 100vh;
        }

        .reset-card {
            border: none;
            border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.10);
        }

        .reset-card .card-body {
            padding: 2.5rem;
        }

        .logo-wrapper {
            background: #f0f4ff;
            border-radius: 50%;
            width: 80px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }

        .form-control:focus {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.2rem rgba(13, 110, 253, .15);
        }

        .input-group-text {
            background: transparent;
            cursor: pointer;
        }

        .btn-primary {
            padding: 0.6rem;
            font-size: 1rem;
            border-radius: 8px;
        }

        .divider {
            display: flex;
            align-items: center;
            gap: 12px;
            color: #adb5bd;
            font-size: 0.8rem;
            margin: 1.5rem 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: #dee2e6;
        }
    </style>
</head>

<body>
    <div class="container d-flex justify-content-center align-items-center" style="min-height:100vh">
        <div class="card reset-card" style="width: 100%; max-width: 460px;">
            <div class="card-body">

                <!-- En-tête -->
                <div class="text-center mb-4">
                    <div class="logo-wrapper">
                        <img src="<?= BASE_URL ?>assets/img/logo_avision.png" alt="AVision"
                            style="height:48px; width:auto;">
                    </div>
                    <h4 class="fw-bold mb-1">Nouveau mot de passe</h4>
                    <p class="text-muted small mb-0">Choisissez un mot de passe sécurisé pour votre compte</p>
                </div>

                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger d-flex align-items-center gap-2 py-2">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span><?= $_SESSION['error'];
                        unset($_SESSION['error']); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['success'])): ?>
                    <div class="alert alert-success d-flex align-items-center gap-2 py-2">
                        <i class="bi bi-check-circle-fill"></i>
                        <span><?= $_SESSION['success'];
                        unset($_SESSION['success']); ?></span>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= BASE_URL ?>auth/reset-password" id="resetForm">
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= htmlspecialchars($_GET['token'] ?? '') ?>">

                    <!-- Nouveau mot de passe -->
                    <div class="mb-3">
                        <label class="form-label fw-bold text-secondary small text-uppercase ls-1">
                            Nouveau mot de passe
                        </label>
                        <div class="input-group">
                            <span class="input-group-text border-end-0">
                                <i class="bi bi-lock text-muted"></i>
                            </span>
                            <input type="password" class="form-control border-start-0 ps-0" id="new_password"
                                name="new_password" minlength="8" required placeholder="Minimum 8 caractères">
                            <button type="button" class="input-group-text border-start-0"
                                onclick="togglePassword('new_password', this)">
                                <i class="bi bi-eye text-muted"></i>
                            </button>
                        </div>

                        <!-- Indicateur de force -->
                        <div class="mt-2" id="strengthBar" style="display:none;">
                            <div class="progress" style="height:4px; border-radius:2px;">
                                <div class="progress-bar" id="strengthFill" style="width:0%; transition:width .3s;">
                                </div>
                            </div>
                            <small id="strengthText" class="text-muted"></small>
                        </div>
                    </div>

                    <!-- Confirmer mot de passe -->
                    <div class="mb-4">
                        <label class="form-label fw-bold text-secondary small text-uppercase">
                            Confirmer le mot de passe
                        </label>
                        <div class="input-group">
                            <span class="input-group-text border-end-0">
                                <i class="bi bi-lock-fill text-muted"></i>
                            </span>
                            <input type="password" class="form-control border-start-0 ps-0" id="confirm_password"
                                name="confirm_password" minlength="8" required placeholder="Répétez le mot de passe">
                            <button type="button" class="input-group-text border-start-0"
                                onclick="togglePassword('confirm_password', this)">
                                <i class="bi bi-eye text-muted"></i>
                            </button>
                        </div>
                        <small id="matchMsg" class="mt-1 d-block" style="display:none!important;"></small>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 fw-bold">
                        <i class="bi bi-shield-check me-2"></i> Enregistrer le nouveau mot de passe
                    </button>

                    <div class="divider">ou</div>

                    <div class="text-center">
                        <a href="<?= BASE_URL ?>auth/login" class="text-muted small text-decoration-none">
                            <i class="bi bi-arrow-left me-1"></i> Retour à la connexion
                        </a>
                    </div>
                </form>

            </div>
        </div>
    </div>

    <script>
        function togglePassword(fieldId, btn) {
            const field = document.getElementById(fieldId);
            const icon = btn.querySelector('i');
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'bi bi-eye-slash text-muted';
            } else {
                field.type = 'password';
                icon.className = 'bi bi-eye text-muted';
            }
        }

        // Indicateur de force
        const pwdField = document.getElementById('new_password');
        const bar = document.getElementById('strengthBar');
        const fill = document.getElementById('strengthFill');
        const txt = document.getElementById('strengthText');

        pwdField.addEventListener('input', function () {
            const val = this.value;
            if (!val) { bar.style.display = 'none'; return; }
            bar.style.display = 'block';

            let score = 0;
            if (val.length >= 8) score++;
            if (/[A-Z]/.test(val)) score++;
            if (/[0-9]/.test(val)) score++;
            if (/[^A-Za-z0-9]/.test(val)) score++;

            const levels = [
                { pct: '25%', cls: 'bg-danger', label: 'Très faible' },
                { pct: '50%', cls: 'bg-warning', label: 'Faible' },
                { pct: '75%', cls: 'bg-info', label: 'Moyen' },
                { pct: '100%', cls: 'bg-success', label: 'Fort' },
            ];
            const lvl = levels[score - 1] || levels[0];
            fill.style.width = lvl.pct;
            fill.className = 'progress-bar ' + lvl.cls;
            txt.textContent = lvl.label;
            txt.className = 'text-muted small';
        });

        // Vérification correspondance
        const cfmField = document.getElementById('confirm_password');
        const matchMsg = document.getElementById('matchMsg');

        cfmField.addEventListener('input', function () {
            if (!this.value) { matchMsg.style.display = 'none'; return; }
            matchMsg.style.display = 'block';
            if (this.value === pwdField.value) {
                matchMsg.textContent = '✓ Les mots de passe correspondent';
                matchMsg.className = 'mt-1 d-block text-success small';
                cfmField.classList.remove('is-invalid');
                cfmField.classList.add('is-valid');
            } else {
                matchMsg.textContent = '✗ Les mots de passe ne correspondent pas';
                matchMsg.className = 'mt-1 d-block text-danger small';
                cfmField.classList.remove('is-valid');
                cfmField.classList.add('is-invalid');
            }
        });

        // Bloquer soumission si non correspondant
        document.getElementById('resetForm').addEventListener('submit', function (e) {
            if (pwdField.value !== cfmField.value) {
                e.preventDefault();
                cfmField.classList.add('is-invalid');
                matchMsg.textContent = '✗ Les mots de passe ne correspondent pas';
                matchMsg.className = 'mt-1 d-block text-danger small';
                matchMsg.style.display = 'block';
            }
        });
    </script>
</body>

</html>
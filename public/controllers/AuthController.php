<?php
// Vérification de l'accès direct
if (!defined('BASE_URL')) {
    header('Location: ' . BASE_URL);
    exit;
}

require_once __DIR__ . '/../includes/TotpCrypto.php';

use OTPHP\TOTP;

/**
 * Contrôleur d'authentification
 */
class AuthController
{
    private $userModel;
    private $db;

    // Nombre max de tentatives 2FA échouées avant blocage temporaire
    private const MAX_TOTP_ATTEMPTS = 5;
    private const TOTP_LOCKOUT_WINDOW = 300; // 5 minutes

    /**
     * Constructeur
     */
    public function __construct()
    {
        global $db;
        $this->db = $db;
        $this->userModel = new UserModel($this->db);
    }

    /**
     * Affiche le formulaire de connexion
     */
    public function showLoginForm()
    {
        // Vérifier s'il y a des paramètres QR dans l'URL
        $qrSalle = $_GET['qr'] ?? null;
        $qrType = $_GET['t'] ?? null;

        // Stocker les paramètres QR dans la session pour utilisation après authentification
        if ($qrSalle && $qrType) {
            $_SESSION['qr_salle'] = $qrSalle;
            $_SESSION['qr_type'] = $qrType;
        }

        // Si l'utilisateur est déjà connecté ET qu'il y a des paramètres QR, rediriger directement
        if (isset($_SESSION['user']) && $qrSalle && $qrType) {
            header('Location: ' . BASE_URL . 'qrcode/redirect');
            exit;
        }

        // Si l'utilisateur est déjà connecté, vérifier s'il y a une redirection en attente
        if (isset($_SESSION['user'])) {
            // Vérifier s'il y a une URL de redirection dans la session
            if (isset($_SESSION['redirect_after_login']) && !empty($_SESSION['redirect_after_login'])) {
                $redirectUrl = $_SESSION['redirect_after_login'];
                unset($_SESSION['redirect_after_login']);
                $baseUrl = rtrim(BASE_URL, '/') . '/';
                $redirectUrl = ltrim($redirectUrl, '/');
                header('Location: ' . $baseUrl . $redirectUrl);
                exit;
            }

            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Affichage du formulaire de connexion
        require_once VIEWS_PATH . '/auth/login.php';
    }

    /**
     * Traite la connexion (étape 1 : email + mot de passe)
     */
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            try {
                $success = $this->userModel->authenticate($email, $password);
                if ($success) {
                    $_SESSION['last_activity'] = time();
                    $user = $_SESSION['user'];

                    custom_log("Login réussi (étape mot de passe) - user_id: " . $user['id'], 'DEBUG');

                    // === 2FA : si activée pour ce compte, on ne connecte pas encore complètement ===
                    if (!empty($user['totp_enabled'])) {
                        // On retire l'accès complet et on stocke uniquement l'ID en attente de vérification
                        $_SESSION['pending_2fa_user_id'] = $user['id'];
                        unset($_SESSION['user']);

                        custom_log("2FA requise pour user_id: " . $user['id'], 'DEBUG');
                        header('Location: ' . BASE_URL . 'auth/verify-2fa');
                        exit;
                    }

                    // Pas de 2FA -> flux normal existant
                    $this->redirectAfterFullLogin();
                    exit;
                } else {
                    $_SESSION['error'] = "Nom d'utilisateur ou mot de passe incorrect";
                    header('Location: ' . BASE_URL . 'auth/login');
                    exit;
                }
            } catch (Exception $e) {
                custom_log("Erreur de connexion : " . $e->getMessage(), 'ERROR');
                $_SESSION['error'] = "Une erreur est survenue lors de la connexion";
                header('Location: ' . BASE_URL . 'auth/login');
                exit;
            }
        }
    }

    /**
     * Affiche / traite l'étape 2 : saisie du code TOTP à 6 chiffres
     */
    public function verify2fa()
    {
        // Il faut être passé par l'étape 1 (email + mot de passe corrects)
        if (!isset($_SESSION['pending_2fa_user_id'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        $userId = (int) $_SESSION['pending_2fa_user_id'];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $token = $_POST['csrf_token'] ?? null;
            if (!csrf_verify($token)) {
                $_SESSION['error'] = "Requête invalide, veuillez réessayer.";
                header('Location: ' . BASE_URL . 'auth/verify-2fa');
                exit;
            }

            // Rate limiting anti brute-force
            $recentFailures = $this->userModel->countRecentFailedTotpAttempts($userId, self::TOTP_LOCKOUT_WINDOW);
            if ($recentFailures >= self::MAX_TOTP_ATTEMPTS) {
                $_SESSION['error'] = "Trop de tentatives échouées. Veuillez réessayer dans quelques minutes.";
                header('Location: ' . BASE_URL . 'auth/verify-2fa');
                exit;
            }

            $code = trim($_POST['totp_code'] ?? '');
            $backupCode = trim($_POST['backup_code'] ?? '');
            $user = $this->userModel->getUserById($userId);

            if (!$user || empty($user['totp_secret'])) {
                unset($_SESSION['pending_2fa_user_id']);
                header('Location: ' . BASE_URL . 'auth/login');
                exit;
            }

            $verified = false;
            // Cas 1 : code TOTP à 6 chiffres
            if (!empty($code)) {
                $secret = TotpCrypto::decrypt($user['totp_secret']);
                $totp = TOTP::createFromSecret($secret);
                // Tolérance de 1 fenêtre (±30s) pour absorber le décalage d'horloge
                $verified = $totp->verify($code, null, 1);
            }
            // Cas 2 : code de secours (si le téléphone est perdu)
            elseif (!empty($backupCode)) {
                $hashedCodes = $this->userModel->getBackupCodes($userId);
                $index = TotpCrypto::verifyBackupCode($backupCode, $hashedCodes);
                if ($index !== null) {
                    $verified = true;
                    // Le code de secours est à usage unique : on le retire
                    unset($hashedCodes[$index]);
                    $this->userModel->saveBackupCodes($userId, array_values($hashedCodes));
                }
            }
            $this->userModel->logTotpAttempt($userId, $verified, $_SERVER['REMOTE_ADDR'] ?? null);

            if ($verified) {
                $_SESSION['user'] = $user;
                $_SESSION['last_activity'] = time();
                unset($_SESSION['pending_2fa_user_id']);

                custom_log("2FA validée pour user_id: " . $userId, 'DEBUG');

                $this->redirectAfterFullLogin();
                exit;
            }

            $_SESSION['error'] = "Code invalide. Veuillez réessayer.";
            header('Location: ' . BASE_URL . 'auth/verify-2fa');
            exit;
        }

        // Affichage du formulaire de saisie du code
        require_once VIEWS_PATH . '/auth/verify_2fa.php';
    }

    /**
     * Permet d'annuler la connexion en cours à l'étape 2FA (retour au formulaire de login)
     */
    public function cancel2fa()
    {
        unset($_SESSION['pending_2fa_user_id']);
        header('Location: ' . BASE_URL . 'auth/login');
        exit;
    }

    /**
     * Redirection factorisée après une authentification COMPLÈTE (mot de passe + 2FA le cas échéant)
     */
    private function redirectAfterFullLogin()
    {
        // Paramètres QR (fonctionnalité existante, différente du QR d'activation 2FA)
        if (isset($_SESSION['qr_salle']) && isset($_SESSION['qr_type'])) {
            header('Location: ' . BASE_URL . 'qrcode/redirect');
            return;
        }

        $redirectAfterLogin = $_SESSION['redirect_after_login'] ?? null;
        if ($redirectAfterLogin && trim($redirectAfterLogin) !== '') {
            $redirectUrl = trim($redirectAfterLogin);
            unset($_SESSION['redirect_after_login']);
            $baseUrl = rtrim(BASE_URL, '/') . '/';
            $redirectUrl = ltrim($redirectUrl, '/');
            header('Location: ' . $baseUrl . $redirectUrl);
            return;
        }

        header('Location: ' . BASE_URL . 'dashboard');
    }

    /**
     * Affiche l'écran d'activation de la 2FA : génère un secret temporaire + QR code
     * (le secret n'est enregistré en base qu'après confirmation du premier code)
     */
    public function setup2fa()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        $user = $_SESSION['user'];

        if (!empty($user['totp_enabled'])) {
            $_SESSION['success'] = "La double authentification est déjà activée sur votre compte.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if (!empty($_SESSION['pending_totp_secret'])) {
            $totp = TOTP::createFromSecret($_SESSION['pending_totp_secret']);
        } else {
            $totp = TOTP::generate();
            $_SESSION['pending_totp_secret'] = $totp->getSecret();
        }
        $totp->setLabel($user['email']);
        $totp->setIssuer(defined('SITE_NAME') ? SITE_NAME : 'AVISION');

        $provisioningUri = $totp->getProvisioningUri();

        // Génération du QR code en SVG (aucun appel à un service externe)
        require_once __DIR__ . '/../vendor/autoload.php';
        $builder = new \Endroid\QrCode\Builder\Builder(
            writer: new \Endroid\QrCode\Writer\PngWriter(),
            data: $provisioningUri,
            size: 250,
            margin: 10
        );
        $qrCode = $builder->build();
        $qrCodeDataUri = $qrCode->getDataUri();

        $pageTitle = 'Activer la double authentification';
        require_once VIEWS_PATH . '/auth/setup_2fa.php';
    }

    /**
     * Confirme l'activation : vérifie le premier code saisi et enregistre le secret définitivement
     */
    public function confirmSetup2fa()
    {
        if (!isset($_SESSION['user']) || !isset($_SESSION['pending_totp_secret'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        $token = $_POST['csrf_token'] ?? null;
        if (!csrf_verify($token)) {
            $_SESSION['error'] = "Requête invalide, veuillez réessayer.";
            header('Location: ' . BASE_URL . 'auth/setup-2fa');
            exit;
        }

        $code = trim($_POST['totp_code'] ?? '');
        $secret = $_SESSION['pending_totp_secret'];

        $totp = TOTP::createFromSecret($secret);

        if (!$totp->verify($code, null, 1)) {
            $_SESSION['error'] = "Le code saisi est incorrect. Réessayez.";
            header('Location: ' . BASE_URL . 'auth/setup-2fa');
            exit;
        }

        $userId = $_SESSION['user']['id'];
        $encrypted = TotpCrypto::encrypt($secret);

        $this->userModel->saveTotpSecret($userId, $encrypted);
        $this->userModel->enableTotp($userId);

        // Génération des codes de secours (affichés une seule fois)
        $backup = TotpCrypto::generateBackupCodes(8);
        $this->userModel->saveBackupCodes($userId, $backup['hashed']);

        unset($_SESSION['pending_totp_secret']);

        // Rafraîchir la session utilisateur
        $_SESSION['user']['totp_enabled'] = 1;

        // Afficher les codes de secours une seule fois (via session flash)
        $_SESSION['backup_codes_to_show'] = $backup['plain'];

        header('Location: ' . BASE_URL . 'auth/backup-codes');
        exit;
    }

    /**
     * Affiche une seule fois les codes de secours générés à l'activation
     */
    public function showBackupCodes()
    {
        if (!isset($_SESSION['user']) || !isset($_SESSION['backup_codes_to_show'])) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        $backupCodes = $_SESSION['backup_codes_to_show'];
        unset($_SESSION['backup_codes_to_show']); // usage unique, on ne les remontre jamais

        $pageTitle = 'Codes de secours';
        require_once VIEWS_PATH . '/auth/backup_codes.php';
    }

    /**
     * Désactive la 2FA pour l'utilisateur connecté (nécessite de retaper son mot de passe)
     */
    public function disable2fa()
    {
        if (!isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        $token = $_POST['csrf_token'] ?? null;
        if (!csrf_verify($token)) {
            $_SESSION['error'] = "Requête invalide, veuillez réessayer.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        $password = $_POST['password'] ?? '';
        $email = $_SESSION['user']['email'];

        // On revérifie le mot de passe avant de désactiver une mesure de sécurité
        if (isClient()) {
            if (!$this->userModel->authenticate($email, $password)) {
                $_SESSION['error'] = "Mot de passe incorrect. La 2FA n'a pas été désactivée.";
                header('Location: ' . BASE_URL . 'profileClient');
                exit;
            }
        }
        if (!$this->userModel->authenticate($email, $password)) {
            $_SESSION['error'] = "Mot de passe incorrect. La 2FA n'a pas été désactivée.";
            header('Location: ' . BASE_URL . 'user/view/' . $_SESSION['user']['id']);
            exit;
        }

        $this->userModel->disableTotp($_SESSION['user']['id']);
        $_SESSION['user']['totp_enabled'] = 0;
        $_SESSION['success'] = "La double authentification a été désactivée.";
        header('Location: ' . BASE_URL . 'user/view/' . $_SESSION['user']['id']);
        exit;
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout()
    {
        session_destroy();
        header('Location: ' . BASE_URL . 'auth/login');
        exit;
    }

    /**
     * Affiche le formulaire de réinitialisation de mot de passe
     */
    public function showResetPasswordForm()
    {
        $token = $_GET['token'] ?? null;

        if (empty($token)) {
            $_SESSION['error'] = "Lien de réinitialisation invalide.";
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        require_once MODELS_PATH . '/UserModel.php';
        $userModel = new UserModel($this->db ?? null);
        $user = $userModel->getUserByResetToken($token);

        if (!$user) {
            $_SESSION['error'] = "Ce lien de réinitialisation est invalide ou a expiré.";
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        $pageTitle = 'Réinitialisation du mot de passe';
        include_once VIEWS_PATH . '/auth/reset_password.php';
    }

    /**
     * Traite la soumission du formulaire de réinitialisation
     */
    public function processResetPassword()
    {
        $token = $_POST['token'] ?? null;
        $newPassword = $_POST['new_password'] ?? null;
        $confirmPassword = $_POST['confirm_password'] ?? null;

        if (empty($token) || empty($newPassword) || empty($confirmPassword)) {
            $_SESSION['error'] = "Tous les champs sont requis.";
            header('Location: ' . BASE_URL . 'auth/reset-password?token=' . urlencode($token ?? ''));
            exit;
        }

        if ($newPassword !== $confirmPassword) {
            $_SESSION['error'] = "Les mots de passe ne correspondent pas.";
            header('Location: ' . BASE_URL . 'auth/reset-password?token=' . urlencode($token ?? ''));
            exit;
        }

        if (strlen($newPassword) < 8) {
            $_SESSION['error'] = "Le mot de passe doit contenir au moins 8 caractères.";
            header('Location: ' . BASE_URL . 'auth/reset-password?token=' . urlencode($token ?? ''));
            exit;
        }

        require_once MODELS_PATH . '/UserModel.php';
        $userModel = new UserModel($this->db ?? null);
        $user = $userModel->getUserByResetToken($token);

        if (!$user) {
            $_SESSION['error'] = "Ce lien de réinitialisation est invalide ou a expiré.";
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $userModel->updatePassword($user['id'], $hashedPassword);
        $userModel->deleteResetToken($token);

        $_SESSION['success'] = "Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.";
        header('Location: ' . BASE_URL . 'auth/login');
        exit;
    }

    /**
     * Affiche le formulaire "mot de passe oublié"
     */
    public function showForgotPasswordForm()
    {
        if (isset($_SESSION['user'])) {
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }
        require_once VIEWS_PATH . '/auth/forgot_password.php';
    }

    /**
     * Traite la demande de réinitialisation faite par l'utilisateur lui-même
     */
    public function processForgotPassword()
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'auth/forgot-password');
            exit;
        }

        $token = $_POST['csrf_token'] ?? null;
        if (!csrf_verify($token)) {
            $_SESSION['error'] = "Requête invalide, veuillez réessayer.";
            header('Location: ' . BASE_URL . 'auth/forgot-password');
            exit;
        }

        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $_SESSION['error'] = "Veuillez saisir une adresse email valide.";
            header('Location: ' . BASE_URL . 'auth/forgot-password');
            exit;
        }

        $_SESSION['success'] = "Si un compte est associé à cette adresse, un email de réinitialisation vient de vous être envoyé.";

        try {
            $user = $this->userModel->getUserByEmail($email);

            if ($user && !empty($user['status'])) {
                $resetToken = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                $this->userModel->savePasswordResetToken($user['id'], $resetToken, $expiresAt, null);

                require_once __DIR__ . '/../classes/MailService.php';
                $mailService = new MailService($this->db);
                $mailService->sendPasswordResetLink($user, $resetToken);
            }
        } catch (Exception $e) {
            custom_log("Erreur dans processForgotPassword: " . $e->getMessage(), 'ERROR');
        }

        header('Location: ' . BASE_URL . 'auth/forgot-password');
        exit;
    }
}
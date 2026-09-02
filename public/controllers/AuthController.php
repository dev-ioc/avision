<?php
// Vérification de l'accès direct
if (!defined('BASE_URL')) {
    header('Location: ' . BASE_URL);
    exit;
}

/**
 * Contrôleur d'authentification
 */
class AuthController
{
    private $userModel;
    private $db;

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
                // S'assurer que BASE_URL se termine par un slash et que redirectUrl ne commence pas par un slash
                $baseUrl = rtrim(BASE_URL, '/') . '/';
                $redirectUrl = ltrim($redirectUrl, '/');
                header('Location: ' . $baseUrl . $redirectUrl);
                exit;
            }

            // Sinon, redirection normale vers le tableau de bord
            if (isClient()) {
                // Les clients vont vers le dashboard client
                header('Location: ' . BASE_URL . 'dashboard');
            } else {
                // Le personnel (admin, technicien) va vers le dashboard staff
                header('Location: ' . BASE_URL . 'dashboard');
            }
            exit;
        }

        // Affichage du formulaire de connexion
        require_once VIEWS_PATH . '/auth/login.php';
    }

    /**
     * Traite la connexion
     */
    public function login()
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $email = $_POST['email'] ?? '';
            $password = $_POST['password'] ?? '';

            try {
                $success = $this->userModel->authenticate($email, $password);
                if ($success) {
                    // L'authentification a réussi, les données sont déjà stockées dans la session
                    $_SESSION['last_activity'] = time();

                    // Debug: logger ce qui est dans la session
                    custom_log("Login réussi - Session ID: " . session_id(), 'DEBUG');
                    custom_log("Login réussi - redirect_after_login: " . ($_SESSION['redirect_after_login'] ?? 'NON DÉFINI'), 'DEBUG');
                    custom_log("Login réussi - qr_salle: " . ($_SESSION['qr_salle'] ?? 'NON DÉFINI'), 'DEBUG');
                    custom_log("Login réussi - Toutes les clés de session: " . implode(', ', array_keys($_SESSION)), 'DEBUG');

                    // Vérifier s'il y a des paramètres QR dans la session
                    if (isset($_SESSION['qr_salle']) && isset($_SESSION['qr_type'])) {
                        // Rediriger vers le contrôleur QRCode pour gérer la redirection
                        header('Location: ' . BASE_URL . 'qrcode/redirect');
                        exit;
                    }

                    // Vérifier s'il y a une URL de redirection dans la session
                    // Vérifier d'abord si la clé existe, puis si elle n'est pas vide
                    $redirectAfterLogin = $_SESSION['redirect_after_login'] ?? null;
                    if ($redirectAfterLogin && trim($redirectAfterLogin) !== '') {
                        $redirectUrl = trim($redirectAfterLogin);
                        unset($_SESSION['redirect_after_login']);
                        // S'assurer que BASE_URL se termine par un slash et que redirectUrl ne commence pas par un slash
                        $baseUrl = rtrim(BASE_URL, '/') . '/';
                        $redirectUrl = ltrim($redirectUrl, '/');
                        $finalUrl = $baseUrl . $redirectUrl;
                        custom_log("Redirection après login vers: " . $finalUrl, 'DEBUG');
                        header('Location: ' . $finalUrl);
                        exit;
                    }

                    custom_log("Aucune redirection trouvée (redirect_after_login: " . var_export($redirectAfterLogin, true) . "), redirection vers dashboard", 'DEBUG');

                    // Redirection normale vers le tableau de bord approprié selon le type d'utilisateur
                    if (isClient()) {
                        // Les clients vont vers le dashboard client
                        header('Location: ' . BASE_URL . 'dashboard');
                    } else {
                        // Le personnel (admin, technicien) va vers le dashboard staff
                        header('Location: ' . BASE_URL . 'dashboard');
                    }
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
     * Déconnecte l'utilisateur
     */
    public function logout()
    {
        // Destruction de la session
        session_destroy();

        // Redirection vers la page de connexion
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
        header('Content-Type: application/json; charset=utf-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);

            echo json_encode([
                'success' => false,
                'message' => 'Méthode non autorisée.'
            ]);
            exit;
        }

        $token = $_POST['csrf_token'] ?? null;

        if (!csrf_verify($token)) {
            http_response_code(403);

            echo json_encode([
                'success' => false,
                'message' => 'Requête invalide, veuillez réessayer.'
            ]);
            exit;
        }

        $email = trim($_POST['email'] ?? '');

        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(422);

            echo json_encode([
                'success' => false,
                'message' => 'Veuillez saisir une adresse email valide.'
            ]);
            exit;
        }

        try {
            $user = $this->userModel->getUserByEmail($email);

            /*
             * Pour des raisons de sécurité, on retourne le même message
             * même si l'adresse n'existe pas.
             */
            if ($user && !empty($user['status'])) {

                $resetToken = bin2hex(random_bytes(32));
                $expiresAt = date('Y-m-d H:i:s', time() + 3600);

                $saved = $this->userModel->savePasswordResetToken(
                    $user['id'],
                    $resetToken,
                    $expiresAt,
                    null
                );

                if (!$saved) {
                    throw new Exception(
                        "Impossible de sauvegarder le token de réinitialisation."
                    );
                }

                require_once __DIR__ . '/../classes/MailService.php';

                $mailService = new MailService($this->db);

                $mailService->sendPasswordResetLink(
                    $user,
                    $resetToken
                );
            }

            echo json_encode([
                'success' => true,
                'message' => 'Si un compte est associé à cette adresse, un email de réinitialisation vient de vous être envoyé.'
            ]);

        } catch (Exception $e) {

            custom_log(
                "Erreur dans processForgotPassword: " . $e->getMessage(),
                'ERROR'
            );

            http_response_code(500);

            echo json_encode([
                'success' => false,
                'message' => 'Impossible d\'envoyer l\'email de réinitialisation. Veuillez réessayer plus tard.'
            ]);
        }

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

        // Vérifier que le token est valide et non expiré
        require_once MODELS_PATH . '/UserModel.php';
        $userModel = new UserModel($this->db ?? null);
        $user = $userModel->getUserByResetToken($token);

        if (!$user) {
            $_SESSION['error'] = "Ce lien de réinitialisation est invalide ou a expiré.";
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Afficher la vue
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

        // Mettre à jour le mot de passe
        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
        $userModel->updatePassword($user['id'], $hashedPassword);

        // Supprimer le token utilisé
        $userModel->deleteResetToken($token);

        $_SESSION['success'] = "Votre mot de passe a été réinitialisé avec succès. Vous pouvez maintenant vous connecter.";
        header('Location: ' . BASE_URL . 'auth/login');
        exit;
    }
}
<?php
// Vérification de l'accès direct
if (!defined('BASE_URL')) {
    header('Location: ' . BASE_URL);
    exit;
}

/**
 * Contrôleur d'authentification
 */
class AuthController {
    private $userModel;
    private $db;

    /**
     * Constructeur
     */
    public function __construct() {
        global $db;
        $this->db = $db;
        $this->userModel = new UserModel($this->db);
    }

    /**
     * Affiche le formulaire de connexion
     */
    public function showLoginForm() {
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
    public function login() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $username = $_POST['username'] ?? '';
            $password = $_POST['password'] ?? '';
            
            try {
                $success = $this->userModel->authenticate($username, $password);
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
    public function logout() {
        // Destruction de la session
        session_destroy();
        
        // Redirection vers la page de connexion
        header('Location: ' . BASE_URL . 'auth/login');
        exit;
    }
} 
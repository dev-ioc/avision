<?php
require_once __DIR__ . '/../models/UserModel.php';
require_once __DIR__ . '/../models/ClientModel.php';

class ProfileClientController {
    private $userModel;
    private $clientModel;

    public function __construct($db) {
        $this->userModel = new UserModel($db);
        $this->clientModel = new ClientModel($db);
    }

    /**
     * Affiche le profil de l'utilisateur client
     */
    public function index() {
        // Vérifier l'accès client
        checkClientAccess();

        $userId = $_SESSION['user']['id'];
        $user = $this->userModel->getUserById($userId);
        
        if (!$user) {
            $_SESSION['error'] = 'Utilisateur non trouvé.';
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Récupérer les informations du client associé
        $client = null;
        if (isset($user['client_id']) && $user['client_id']) {
            $client = $this->clientModel->getClientById($user['client_id']);
        }

        // Inclure la vue
        include_once __DIR__ . '/../views/profile_client/index.php';
    }

    /**
     * Affiche le formulaire de modification du profil
     */
    public function edit() {
        // Vérifier l'accès client
        checkClientAccess();

        // Vérifier la permission
        if (!canModifyOwnInfo()) {
            $_SESSION['error'] = 'Vous n\'avez pas les droits pour modifier vos informations.';
            header('Location: ' . BASE_URL . 'profileClient');
            exit;
        }

        $userId = $_SESSION['user']['id'];
        $user = $this->userModel->getUserById($userId);
        
        if (!$user) {
            $_SESSION['error'] = 'Utilisateur non trouvé.';
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // var_dump($_POST);
            // die();
            $firstName = trim($_POST['first_name'] ?? '');
            $lastName = trim($_POST['last_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            // Validation des champs obligatoires
            if (empty($firstName) || empty($lastName) || empty($email)) {
                $_SESSION['error'] = 'Les champs nom, prénom et email sont obligatoires.';
                header('Location: ' . BASE_URL . 'profileClient/edit');
                exit;
            }

            // Validation de l'email
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['error'] = 'L\'adresse email n\'est pas valide.';
                header('Location: ' . BASE_URL . 'profileClient/edit');
                exit;
            }

            // Vérifier si l'email existe déjà (sauf pour cet utilisateur)
            if ($this->userModel->emailExists($email, $userId)) {
                $_SESSION['error'] = 'Cette adresse email est déjà utilisée.';
                header('Location: ' . BASE_URL . 'profileClient/edit');
                exit;
            }

            // Préparer les données à mettre à jour
            $updateData = [
                'first_name' => $firstName,
                'last_name' => $lastName,
                'email' => $email,
                'phone' => $phone
            ];

            // Gestion du changement de mot de passe
            if (!empty($currentPassword) || !empty($newPassword) || !empty($confirmPassword)) {
                // Vérifier que tous les champs de mot de passe sont remplis
                if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
                    $_SESSION['error'] = 'Tous les champs de mot de passe sont requis pour changer le mot de passe.';
                    header('Location: ' . BASE_URL . 'profileClient/edit');
                    exit;
                }

                // Vérifier que le nouveau mot de passe et sa confirmation correspondent
                if ($newPassword !== $confirmPassword) {
                    $_SESSION['error'] = 'Le nouveau mot de passe et sa confirmation ne correspondent pas.';
                    header('Location: ' . BASE_URL . 'profileClient/edit');
                    exit;
                }

                // Vérifier que le mot de passe actuel est correct
                if (!password_verify($currentPassword, $user['password'])) {
                    $_SESSION['error'] = 'Le mot de passe actuel est incorrect.';
                    header('Location: ' . BASE_URL . 'profileClient/edit');
                    exit;
                }

                // Vérifier la complexité du nouveau mot de passe
                if (strlen($newPassword) < 8) {
                    $_SESSION['error'] = 'Le nouveau mot de passe doit contenir au moins 8 caractères.';
                    header('Location: ' . BASE_URL . 'profileClient/edit');
                    exit;
                }

                $updateData['password'] = $newPassword;
            }

            // Mettre à jour l'utilisateur
            if ($this->userModel->updateUser($userId, $updateData)) {
                // Mettre à jour les données de session
                $_SESSION['user']['first_name'] = $firstName;
                $_SESSION['user']['last_name'] = $lastName;
                $_SESSION['user']['email'] = $email;
                $_SESSION['user']['phone'] = $phone;

                $_SESSION['success'] = 'Profil mis à jour avec succès.';
                header('Location: ' . BASE_URL . 'profileClient');
                exit;
            } else {
                $_SESSION['error'] = 'Erreur lors de la mise à jour du profil.';
                header('Location: ' . BASE_URL . 'profileClient/edit');
                exit;
            }
        }

        // Inclure la vue
        include_once __DIR__ . '/../views/profile_client/edit.php';
    }
}
?>

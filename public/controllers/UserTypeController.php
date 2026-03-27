<?php
require_once __DIR__ . '/../models/UserTypeModel.php';

class UserTypeController {
    private $userTypeModel;

    public function __construct($db) {
        $this->userTypeModel = new UserTypeModel($db);
    }

    /**
     * Affiche la liste des types d'utilisateur
     */
    public function index() {
        // Vérifier l'accès admin
        checkStaffAccess();
        if (!isAdmin()) {
            $_SESSION['error'] = "Seuls les administrateurs peuvent gérer les types d'utilisateur.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        // Récupérer tous les types d'utilisateur avec le nombre d'utilisateurs utilisant chaque type
        $userTypes = $this->userTypeModel->getAll();
        
        // Ajouter le nombre d'utilisateurs pour chaque type
        foreach ($userTypes as &$type) {
            $type['user_count'] = $this->userTypeModel->getUserCount($type['id']);
        }

        // Inclure la vue
        include_once __DIR__ . '/../views/settings/user_types.php';
    }

    /**
     * Affiche le formulaire d'ajout d'un type d'utilisateur
     */
    public function add() {
        // Vérifier l'accès admin
        checkStaffAccess();
        if (!isAdmin()) {
            $_SESSION['error'] = "Seuls les administrateurs peuvent créer des types d'utilisateur.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            // Validation
            if (empty($name)) {
                $_SESSION['error'] = 'Le nom du type d\'utilisateur est requis.';
                header('Location: ' . BASE_URL . 'settings/userTypes/add');
                exit;
            }

            if (strlen($name) > 50) {
                $_SESSION['error'] = 'Le nom du type d\'utilisateur ne peut pas dépasser 50 caractères.';
                header('Location: ' . BASE_URL . 'settings/userTypes/add');
                exit;
            }

            if (strlen($description) > 255) {
                $_SESSION['error'] = 'La description ne peut pas dépasser 255 caractères.';
                header('Location: ' . BASE_URL . 'settings/userTypes/add');
                exit;
            }

            // Vérifier si le nom existe déjà
            if ($this->userTypeModel->nameExists($name)) {
                $_SESSION['error'] = 'Un type d\'utilisateur avec ce nom existe déjà.';
                header('Location: ' . BASE_URL . 'settings/userTypes/add');
                exit;
            }

            // Créer le type d'utilisateur
            $data = [
                'name' => $name,
                'description' => $description
            ];

            if ($this->userTypeModel->create($data)) {
                $_SESSION['success'] = 'Type d\'utilisateur créé avec succès.';
                header('Location: ' . BASE_URL . 'settings/userTypes');
                exit;
            } else {
                $_SESSION['error'] = 'Erreur lors de la création du type d\'utilisateur.';
                header('Location: ' . BASE_URL . 'settings/userTypes/add');
                exit;
            }
        }

        // Afficher le formulaire
        include_once __DIR__ . '/../views/settings/user_types_add.php';
    }

    /**
     * Affiche le formulaire de modification d'un type d'utilisateur
     */
    public function edit($id) {
        // Vérifier l'accès admin
        checkStaffAccess();
        if (!isAdmin()) {
            $_SESSION['error'] = "Seuls les administrateurs peuvent modifier les types d'utilisateur.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        $userType = $this->userTypeModel->getById($id);
        if (!$userType) {
            $_SESSION['error'] = 'Type d\'utilisateur non trouvé.';
            header('Location: ' . BASE_URL . 'settings/userTypes');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            // Validation
            if (empty($name)) {
                $_SESSION['error'] = 'Le nom du type d\'utilisateur est requis.';
                header('Location: ' . BASE_URL . 'settings/userTypes/edit/' . $id);
                exit;
            }

            if (strlen($name) > 50) {
                $_SESSION['error'] = 'Le nom du type d\'utilisateur ne peut pas dépasser 50 caractères.';
                header('Location: ' . BASE_URL . 'settings/userTypes/edit/' . $id);
                exit;
            }

            if (strlen($description) > 255) {
                $_SESSION['error'] = 'La description ne peut pas dépasser 255 caractères.';
                header('Location: ' . BASE_URL . 'settings/userTypes/edit/' . $id);
                exit;
            }

            // Vérifier si le nom existe déjà (sauf pour ce type)
            if ($this->userTypeModel->nameExists($name, $id)) {
                $_SESSION['error'] = 'Un type d\'utilisateur avec ce nom existe déjà.';
                header('Location: ' . BASE_URL . 'settings/userTypes/edit/' . $id);
                exit;
            }

            // Mettre à jour le type d'utilisateur
            $data = [
                'name' => $name,
                'description' => $description
            ];

            if ($this->userTypeModel->update($id, $data)) {
                $_SESSION['success'] = 'Type d\'utilisateur mis à jour avec succès.';
                header('Location: ' . BASE_URL . 'settings/userTypes');
                exit;
            } else {
                $_SESSION['error'] = 'Erreur lors de la mise à jour du type d\'utilisateur.';
                header('Location: ' . BASE_URL . 'settings/userTypes/edit/' . $id);
                exit;
            }
        }

        // Afficher le formulaire
        include_once __DIR__ . '/../views/settings/user_types_edit.php';
    }

    /**
     * Supprime un type d'utilisateur
     */
    public function delete($id) {
        // Vérifier l'accès admin
        checkStaffAccess();
        if (!isAdmin()) {
            $_SESSION['error'] = "Seuls les administrateurs peuvent supprimer des types d'utilisateur.";
            header('Location: ' . BASE_URL . 'dashboard');
            exit;
        }

        $userType = $this->userTypeModel->getById($id);
        if (!$userType) {
            $_SESSION['error'] = 'Type d\'utilisateur non trouvé.';
            header('Location: ' . BASE_URL . 'settings/userTypes');
            exit;
        }

        $userCount = $this->userTypeModel->getUserCount($id);
        if ($userCount > 0) {
            $_SESSION['error'] = 'Impossible de supprimer ce type d\'utilisateur car ' . $userCount . ' utilisateur(s) l\'utilise(nt).';
            header('Location: ' . BASE_URL . 'settings/userTypes');
            exit;
        }

        if ($this->userTypeModel->delete($id)) {
            $_SESSION['success'] = 'Type d\'utilisateur supprimé avec succès.';
        } else {
            $_SESSION['error'] = 'Erreur lors de la suppression du type d\'utilisateur.';
        }

        header('Location: ' . BASE_URL . 'settings/userTypes');
        exit;
    }
}
?> 
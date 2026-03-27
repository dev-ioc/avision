<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/InterventionTypeModel.php';

/**
 * Contrôleur pour la gestion des types d'intervention
 */
class InterventionTypeController {
    private $db;
    private $interventionTypeModel;

    public function __construct($db) {
        $this->db = $db;
        $this->interventionTypeModel = new InterventionTypeModel($db);
    }

    /**
     * Affiche la liste des types d'intervention
     */
    public function index() {
        // Vérifier si l'utilisateur est connecté et est admin
        if (!isset($_SESSION['user']) || !isAdmin()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        // Récupérer tous les types d'intervention avec le nombre d'interventions utilisant chaque type
        $interventionTypes = $this->interventionTypeModel->getAll();
        
        // Ajouter le nombre d'interventions pour chaque type
        foreach ($interventionTypes as &$type) {
            $type['intervention_count'] = $this->interventionTypeModel->getInterventionCount($type['id']);
        }

        // Inclure la vue
        include_once __DIR__ . '/../views/settings/intervention_types.php';
    }

    /**
     * Affiche le formulaire d'ajout d'un type d'intervention
     */
    public function add() {
        // Vérifier si l'utilisateur est connecté et est admin
        if (!isset($_SESSION['user']) || !isAdmin()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $requiresTravel = isset($_POST['requires_travel']) ? 1 : 0;

            // Validation
            if (empty($name)) {
                $_SESSION['error'] = 'Le nom du type d\'intervention est requis.';
                header('Location: ' . BASE_URL . 'settings/interventionTypes/add');
                exit;
            }

            if (strlen($name) > 50) {
                $_SESSION['error'] = 'Le nom du type d\'intervention ne peut pas dépasser 50 caractères.';
                header('Location: ' . BASE_URL . 'settings/interventionTypes/add');
                exit;
            }

            // Vérifier si le nom existe déjà
            if ($this->interventionTypeModel->nameExists($name)) {
                $_SESSION['error'] = 'Un type d\'intervention avec ce nom existe déjà.';
                header('Location: ' . BASE_URL . 'settings/interventionTypes/add');
                exit;
            }

            // Créer le type d'intervention
            $data = [
                'name' => $name,
                'requires_travel' => $requiresTravel
            ];

            if ($this->interventionTypeModel->create($data)) {
                $_SESSION['success'] = 'Type d\'intervention créé avec succès.';
                header('Location: ' . BASE_URL . 'settings/interventionTypes');
                exit;
            } else {
                $_SESSION['error'] = 'Erreur lors de la création du type d\'intervention.';
                header('Location: ' . BASE_URL . 'settings/interventionTypes/add');
                exit;
            }
        }

        // Afficher le formulaire
        include_once __DIR__ . '/../views/settings/intervention_types/add.php';
    }

    /**
     * Affiche le formulaire de modification d'un type d'intervention
     */
    public function edit($id) {
        // Vérifier si l'utilisateur est connecté et est admin
        if (!isset($_SESSION['user']) || !isAdmin()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        $interventionType = $this->interventionTypeModel->getById($id);
        if (!$interventionType) {
            $_SESSION['error'] = 'Type d\'intervention non trouvé.';
            header('Location: ' . BASE_URL . 'settings/interventionTypes');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $name = trim($_POST['name'] ?? '');
            $requiresTravel = isset($_POST['requires_travel']) ? 1 : 0;

            // Validation
            if (empty($name)) {
                $_SESSION['error'] = 'Le nom du type d\'intervention est requis.';
                header('Location: ' . BASE_URL . 'settings/interventionTypes/edit/' . $id);
                exit;
            }

            if (strlen($name) > 50) {
                $_SESSION['error'] = 'Le nom du type d\'intervention ne peut pas dépasser 50 caractères.';
                header('Location: ' . BASE_URL . 'settings/interventionTypes/edit/' . $id);
                exit;
            }

            // Vérifier si le nom existe déjà (sauf pour ce type)
            if ($this->interventionTypeModel->nameExists($name, $id)) {
                $_SESSION['error'] = 'Un type d\'intervention avec ce nom existe déjà.';
                header('Location: ' . BASE_URL . 'settings/interventionTypes/edit/' . $id);
                exit;
            }

            // Mettre à jour le type d'intervention
            $data = [
                'name' => $name,
                'requires_travel' => $requiresTravel
            ];

            if ($this->interventionTypeModel->update($id, $data)) {
                $_SESSION['success'] = 'Type d\'intervention mis à jour avec succès.';
                header('Location: ' . BASE_URL . 'settings/interventionTypes');
                exit;
            } else {
                $_SESSION['error'] = 'Erreur lors de la mise à jour du type d\'intervention.';
                header('Location: ' . BASE_URL . 'settings/interventionTypes/edit/' . $id);
                exit;
            }
        }

        // Afficher le formulaire
        include_once __DIR__ . '/../views/settings/intervention_types/edit.php';
    }

    /**
     * Supprime un type d'intervention
     */
    public function delete($id) {
        // Vérifier si l'utilisateur est connecté et est admin
        if (!isset($_SESSION['user']) || !isAdmin()) {
            header('Location: ' . BASE_URL . 'auth/login');
            exit;
        }

        $interventionType = $this->interventionTypeModel->getById($id);
        if (!$interventionType) {
            $_SESSION['error'] = 'Type d\'intervention non trouvé.';
            header('Location: ' . BASE_URL . 'settings/interventionTypes');
            exit;
        }

        // Vérifier si le type est utilisé par des interventions
        $interventionCount = $this->interventionTypeModel->getInterventionCount($id);
        if ($interventionCount > 0) {
            $_SESSION['error'] = 'Impossible de supprimer ce type d\'intervention car ' . $interventionCount . ' intervention(s) l\'utilise(nt).';
            header('Location: ' . BASE_URL . 'settings/interventionTypes');
            exit;
        }

        // Supprimer le type d'intervention
        if ($this->interventionTypeModel->delete($id)) {
            $_SESSION['success'] = 'Type d\'intervention supprimé avec succès.';
        } else {
            $_SESSION['error'] = 'Erreur lors de la suppression du type d\'intervention.';
        }

        header('Location: ' . BASE_URL . 'settings/interventionTypes');
        exit;
    }
} 
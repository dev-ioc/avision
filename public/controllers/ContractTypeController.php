<?php
require_once __DIR__ . '/../models/ContractTypeModel.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class ContractTypeController {
    use AccessControlTrait;
    private $db;
    private $contractTypeModel;

    public function __construct($db) {
        $this->db = $db;
        $this->contractTypeModel = new ContractTypeModel($db);
    }

    /**
     * Affiche la liste des types de contrats
     */
    public function index() {
        $this->checkAdminAccess("Seuls les administrateurs peuvent gérer les types de contrats.");

        try {
            $contractTypes = $this->contractTypeModel->getAllContractTypes();
            
            // Définir les variables de page
            setPageVariables('Types de contrats', 'settings');
            $currentPage = 'settings';

            // Inclure les vues
            include_once __DIR__ . '/../includes/header.php';
            include_once __DIR__ . '/../includes/sidebar.php';
            include_once __DIR__ . '/../includes/navbar.php';
            include_once VIEWS_PATH . '/settings/contract_types/index.php';
            include_once __DIR__ . '/../includes/footer.php';
        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération des types de contrats : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de la récupération des types de contrats.";
            header('Location: ' . BASE_URL . 'settings');
            exit;
        }
    }

    /**
     * Affiche le formulaire d'ajout d'un type de contrat
     */
    public function add() {
        $this->checkAdminAccess("Seuls les administrateurs peuvent gérer les types de contrats.");

        $formData = [];
        
        // Définir les variables de page
        setPageVariables('Ajouter un type de contrat', 'settings');
        $currentPage = 'settings';

                    // Inclure les vues
            include_once __DIR__ . '/../includes/header.php';
            include_once __DIR__ . '/../includes/sidebar.php';
            include_once __DIR__ . '/../includes/navbar.php';
            include_once VIEWS_PATH . '/settings/contract_types/add.php';
            include_once __DIR__ . '/../includes/footer.php';
    }

    /**
     * Traite l'ajout d'un type de contrat
     */
    public function create() {
        $this->checkAdminAccess("Seuls les administrateurs peuvent gérer les types de contrats.");

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'settings/contractTypes');
            exit;
        }

        try {
            // Validation des données
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $defaultTickets = (int)($_POST['default_tickets'] ?? 0);
            $nbInterPrev = (int)($_POST['nb_inter_prev'] ?? 0);

            if (empty($name)) {
                throw new Exception("Le nom du type de contrat est obligatoire.");
            }

            if ($defaultTickets < 0) {
                throw new Exception("Le nombre de tickets par défaut ne peut pas être négatif.");
            }

            if ($nbInterPrev < 0) {
                throw new Exception("Le nombre d'interventions préventives ne peut pas être négatif.");
            }

            // Créer le type de contrat
            $data = [
                'name' => $name,
                'description' => $description,
                'default_tickets' => $defaultTickets,
                'nb_inter_prev' => $nbInterPrev
            ];

            $this->contractTypeModel->createContractType($data);

            $_SESSION['success'] = "Le type de contrat a été créé avec succès.";
            header('Location: ' . BASE_URL . 'settings/contractTypes');
            exit;

        } catch (Exception $e) {
            custom_log("Erreur lors de la création du type de contrat : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $_POST;
            header('Location: ' . BASE_URL . 'settings/contractTypes/add');
            exit;
        }
    }

    /**
     * Affiche le formulaire d'édition d'un type de contrat
     */
    public function edit($id = null) {
        $this->checkAdminAccess("Seuls les administrateurs peuvent gérer les types de contrats.");

        if (!$id || !is_numeric($id)) {
            $_SESSION['error'] = "ID de type de contrat invalide.";
            header('Location: ' . BASE_URL . 'settings/contractTypes');
            exit;
        }

        try {
            $contractType = $this->contractTypeModel->getContractTypeById($id);
            
            if (!$contractType) {
                $_SESSION['error'] = "Type de contrat non trouvé.";
                header('Location: ' . BASE_URL . 'settings/contractTypes');
                exit;
            }

            $formData = $_SESSION['form_data'] ?? [];
            unset($_SESSION['form_data']);

            // Définir les variables de page
            setPageVariables('Modifier le type de contrat', 'settings');
            $currentPage = 'settings';

            // Inclure les vues
            include_once __DIR__ . '/../includes/header.php';
            include_once __DIR__ . '/../includes/sidebar.php';
            include_once __DIR__ . '/../includes/navbar.php';
            include_once VIEWS_PATH . '/settings/contract_types/edit.php';
            include_once __DIR__ . '/../includes/footer.php';

        } catch (Exception $e) {
            custom_log("Erreur lors de la récupération du type de contrat : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Une erreur est survenue lors de la récupération du type de contrat.";
            header('Location: ' . BASE_URL . 'settings/contractTypes');
            exit;
        }
    }

    /**
     * Traite la modification d'un type de contrat
     */
    public function update($id = null) {
        $this->checkAdminAccess("Seuls les administrateurs peuvent gérer les types de contrats.");

        if (!$id || !is_numeric($id)) {
            $_SESSION['error'] = "ID de type de contrat invalide.";
            header('Location: ' . BASE_URL . 'settings/contractTypes');
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'settings/contractTypes');
            exit;
        }

        try {
            // Validation des données
            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $defaultTickets = (int)($_POST['default_tickets'] ?? 0);
            $nbInterPrev = (int)($_POST['nb_inter_prev'] ?? 0);

            if (empty($name)) {
                throw new Exception("Le nom du type de contrat est obligatoire.");
            }

            if ($defaultTickets < 0) {
                throw new Exception("Le nombre de tickets par défaut ne peut pas être négatif.");
            }

            if ($nbInterPrev < 0) {
                throw new Exception("Le nombre d'interventions préventives ne peut pas être négatif.");
            }

            // Mettre à jour le type de contrat
            $data = [
                'name' => $name,
                'description' => $description,
                'default_tickets' => $defaultTickets,
                'nb_inter_prev' => $nbInterPrev
            ];

            $this->contractTypeModel->updateContractType($id, $data);

            $_SESSION['success'] = "Le type de contrat a été modifié avec succès.";
            header('Location: ' . BASE_URL . 'settings/contractTypes');
            exit;

        } catch (Exception $e) {
            custom_log("Erreur lors de la modification du type de contrat : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = $e->getMessage();
            $_SESSION['form_data'] = $_POST;
            header('Location: ' . BASE_URL . 'settings/contractTypes/edit/' . $id);
            exit;
        }
    }

    /**
     * Supprime un type de contrat
     */
    public function delete($id = null) {
        $this->checkAdminAccess("Seuls les administrateurs peuvent gérer les types de contrats.");

        if (!$id || !is_numeric($id)) {
            $_SESSION['error'] = "ID de type de contrat invalide.";
            header('Location: ' . BASE_URL . 'settings/contractTypes');
            exit;
        }

        try {
            // Vérifier si le type de contrat est utilisé
            if ($this->contractTypeModel->isContractTypeUsed($id)) {
                throw new Exception("Ce type de contrat ne peut pas être supprimé car il est utilisé par des contrats existants.");
            }

            $this->contractTypeModel->deleteContractType($id);

            $_SESSION['success'] = "Le type de contrat a été supprimé avec succès.";
            header('Location: ' . BASE_URL . 'settings/contractTypes');
            exit;

        } catch (Exception $e) {
            custom_log("Erreur lors de la suppression du type de contrat : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = $e->getMessage();
            header('Location: ' . BASE_URL . 'settings/contractTypes');
            exit;
        }
    }

    /**
     * Met à jour l'ordre d'affichage des types de contrats
     */
    public function updateOrder() {
        $this->checkAdminAccess("Seuls les administrateurs peuvent gérer les types de contrats.");

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'settings/contractTypes');
            exit;
        }

        try {
            $ordersJson = $_POST['orders'] ?? '';
            
            if (empty($ordersJson)) {
                throw new Exception("Aucun ordre à mettre à jour.");
            }

            // Décoder le JSON reçu
            $orders = json_decode($ordersJson, true);
            
            if ($orders === null) {
                throw new Exception("Format de données invalide.");
            }

            $success = true;
            foreach ($orders as $id => $order) {
                if (!$this->contractTypeModel->updateDisplayOrder($id, $order)) {
                    $success = false;
                    break;
                }
            }

            if ($success) {
                $_SESSION['success'] = "L'ordre d'affichage a été mis à jour avec succès.";
            } else {
                throw new Exception("Erreur lors de la mise à jour de l'ordre d'affichage.");
            }

        } catch (Exception $e) {
            custom_log("Erreur lors de la mise à jour de l'ordre : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = $e->getMessage();
        }

        header('Location: ' . BASE_URL . 'settings/contractTypes');
        exit;
    }
} 
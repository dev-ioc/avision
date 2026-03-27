<?php
require_once __DIR__ . '/../models/AccessLevelModel.php';
require_once __DIR__ . '/../models/ContractModel.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Traits/AccessControlTrait.php';

class AccessLevelController {
    use AccessControlTrait;
    private $db;
    private $accessLevelModel;
    private $contractModel;

    public function __construct() {
        global $db;
        $this->db = $db;
        $this->accessLevelModel = new AccessLevelModel($this->db);
        $this->contractModel = new ContractModel($this->db);
    }

    /**
     * Affiche la liste des niveaux d'accès
     */
    public function index() {
        $this->checkAdminAccess("Accès réservé aux administrateurs");

        try {
            $accessLevels = $this->accessLevelModel->getAllAccessLevels();
        } catch (Exception $e) {
            custom_log("Erreur lors du chargement des niveaux d'accès : " . $e->getMessage(), 'ERROR');
            $accessLevels = [];
        }

        $currentPage = 'access_levels';
        $pageTitle = 'Gestion des Niveaux d\'Accès';

        require_once VIEWS_PATH . '/settings/access_levels.php';
    }

    /**
     * Met à jour le niveau d'accès d'un contrat
     */
    public function updateContractAccessLevel() {
        $this->checkAdminAccess("Accès réservé aux administrateurs");

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'contract');
            exit;
        }

        try {
            $contractId = $_POST['contract_id'] ?? null;
            $newAccessLevelId = $_POST['access_level_id'] ?? null;
            $updateMaterials = isset($_POST['update_materials']) ? true : false;

            if (!$contractId || !$newAccessLevelId) {
                throw new Exception("Paramètres manquants");
            }

            // Vérifier que le contrat existe
            $contract = $this->contractModel->getContractById($contractId);
            if (!$contract) {
                throw new Exception("Contrat non trouvé");
            }

            // Vérifier que le niveau d'accès existe
            $accessLevel = $this->accessLevelModel->getAccessLevelById($newAccessLevelId);
            if (!$accessLevel) {
                throw new Exception("Niveau d'accès non trouvé");
            }

            // Mettre à jour le niveau d'accès du contrat
            $success = $this->accessLevelModel->updateContractAccessLevel($contractId, $newAccessLevelId);
            
            if (!$success) {
                throw new Exception("Erreur lors de la mise à jour du niveau d'accès");
            }

            // Si demandé, mettre à jour la visibilité des matériels
            if ($updateMaterials) {
                $materialsUpdateSuccess = $this->accessLevelModel->updateContractMaterialsVisibility($contractId, $newAccessLevelId);
                
                if ($materialsUpdateSuccess) {
                    $_SESSION['success'] = "Niveau d'accès mis à jour et visibilité des matériels mise à jour avec succès";
                } else {
                    $_SESSION['warning'] = "Niveau d'accès mis à jour mais erreur lors de la mise à jour de la visibilité des matériels";
                }
            } else {
                $_SESSION['success'] = "Niveau d'accès mis à jour avec succès";
            }

            header('Location: ' . BASE_URL . 'contract/view/' . $contractId);
            exit;

        } catch (Exception $e) {
            custom_log("Erreur lors de la mise à jour du niveau d'accès : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de la mise à jour : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'contract');
            exit;
        }
    }

    /**
     * Affiche le formulaire de changement de niveau d'accès pour un contrat
     */
    public function changeContractAccessLevel($contractId) {
        $this->checkAdminAccess("Accès réservé aux administrateurs");

        try {
            // Récupérer le contrat
            $contract = $this->contractModel->getContractById($contractId);
            if (!$contract) {
                $_SESSION['error'] = "Contrat non trouvé";
                header('Location: ' . BASE_URL . 'contract');
                exit;
            }

            // Récupérer le niveau d'accès actuel
            $currentAccessLevel = $this->accessLevelModel->getContractAccessLevel($contractId);
            
            // Récupérer tous les niveaux d'accès disponibles
            $accessLevels = $this->accessLevelModel->getAllAccessLevels();
            
            // Récupérer les matériels du contrat
            $materials = $this->accessLevelModel->getMaterialsByContract($contractId);

        } catch (Exception $e) {
            custom_log("Erreur lors du chargement des données : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du chargement des données";
            header('Location: ' . BASE_URL . 'contract');
            exit;
        }

        $currentPage = 'contract';
        $pageTitle = 'Changer le Niveau d\'Accès';

        require_once VIEWS_PATH . '/access_levels/change_contract_level.php';
    }

    /**
     * Affiche les détails d'un niveau d'accès
     */
    public function view($id) {
        $this->checkAdminAccess("Accès réservé aux administrateurs");

        try {
            $accessLevel = $this->accessLevelModel->getAccessLevelById($id);
            if (!$accessLevel) {
                $_SESSION['error'] = "Niveau d'accès non trouvé";
                header('Location: ' . BASE_URL . 'access_levels');
                exit;
            }

            // Récupérer les règles de visibilité
            $visibilityRules = $this->accessLevelModel->getVisibilityRulesForLevel($id);

        } catch (Exception $e) {
            custom_log("Erreur lors du chargement du niveau d'accès : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du chargement des données";
            header('Location: ' . BASE_URL . 'access_levels');
            exit;
        }

        $currentPage = 'access_levels';
        $pageTitle = 'Détails du Niveau d\'Accès';

        require_once VIEWS_PATH . '/access_levels/view.php';
    }

    /**
     * Affiche le formulaire d'édition d'un niveau d'accès
     */
    public function edit($id) {
        $this->checkAdminAccess("Accès réservé aux administrateurs");

        try {
            $accessLevel = $this->accessLevelModel->getAccessLevelById($id);
            if (!$accessLevel) {
                $_SESSION['error'] = "Niveau d'accès non trouvé";
                header('Location: ' . BASE_URL . 'access_levels');
                exit;
            }

            // Récupérer les règles de visibilité
            $visibilityRules = $this->accessLevelModel->getVisibilityRulesForLevel($id);

        } catch (Exception $e) {
            custom_log("Erreur lors du chargement du niveau d'accès : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors du chargement des données";
            header('Location: ' . BASE_URL . 'access_levels');
            exit;
        }

        $currentPage = 'access_levels';
        $pageTitle = 'Modifier le Niveau d\'Accès';

        require_once VIEWS_PATH . '/access_levels/edit.php';
    }

    /**
     * Traite la mise à jour d'un niveau d'accès
     */
    public function update($id) {
        $this->checkAdminAccess("Accès réservé aux administrateurs");

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            header('Location: ' . BASE_URL . 'access_levels');
            exit;
        }

        try {
            // Récupérer les données du formulaire
            $visibilityRules = [];
            $champs = [
                'modele', 'marque', 'numero_serie', 'version_firmware', 
                'adresse_mac', 'adresse_ip', 'masque', 'passerelle', 
                'login', 'password', 'date_fin_maintenance', 
                'date_fin_garantie', 'date_derniere_inter', 'commentaire'
            ];

            foreach ($champs as $champ) {
                $visibilityRules[$champ] = isset($_POST['visible_' . $champ]) ? true : false;
            }

            // Mettre à jour les règles de visibilité
            $success = $this->updateVisibilityRules($id, $visibilityRules);
            
            if ($success) {
                $_SESSION['success'] = "Niveau d'accès mis à jour avec succès";
                header('Location: ' . BASE_URL . 'access_levels/view/' . $id);
            } else {
                throw new Exception("Erreur lors de la mise à jour");
            }
            exit;

        } catch (Exception $e) {
            custom_log("Erreur lors de la mise à jour du niveau d'accès : " . $e->getMessage(), 'ERROR');
            $_SESSION['error'] = "Erreur lors de la mise à jour : " . $e->getMessage();
            header('Location: ' . BASE_URL . 'access_levels/edit/' . $id);
            exit;
        }
    }

    /**
     * Récupère les règles de visibilité d'un niveau d'accès (AJAX)
     */
    public function getVisibilityRules($accessLevelId) {
        $this->checkAdminAccess("Accès réservé aux administrateurs");

        try {
            $rules = $this->accessLevelModel->getVisibilityRulesForLevel($accessLevelId);
            
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'rules' => $rules
            ]);
        } catch (Exception $e) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        exit;
    }

    /**
     * Met à jour les règles de visibilité d'un niveau d'accès
     * @param int $accessLevelId ID du niveau d'accès
     * @param array $visibilityRules Règles de visibilité
     * @return bool Succès de la mise à jour
     */
    private function updateVisibilityRules($accessLevelId, $visibilityRules) {
        try {
            $this->db->beginTransaction();

            // Supprimer les anciennes règles
            $sql = "DELETE FROM access_level_material_visibility WHERE access_level_id = :access_level_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':access_level_id' => $accessLevelId]);

            // Insérer les nouvelles règles
            $sql = "INSERT INTO access_level_material_visibility (access_level_id, field_name, visible_by_default) VALUES (:access_level_id, :field_name, :visible_by_default)";
            $stmt = $this->db->prepare($sql);

            foreach ($visibilityRules as $fieldName => $visible) {
                $stmt->execute([
                    ':access_level_id' => $accessLevelId,
                    ':field_name' => $fieldName,
                    ':visible_by_default' => $visible
                ]);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de la mise à jour des règles de visibilité : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }
} 
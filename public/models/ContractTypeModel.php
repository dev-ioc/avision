<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

class ContractTypeModel extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
        $this->table = 'contract_types';
    }

    /**
     * Récupère tous les types de contrats
     * @return array Liste des types de contrats
     */
    public function getAllContractTypes() {
        $query = "SELECT id, name, description, default_tickets, nb_inter_prev, ordre_affichage, created_at, updated_at FROM contract_types ORDER BY ordre_affichage, name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un type de contrat par son ID
     * @param int $id ID du type de contrat
     * @return array|null Données du type de contrat ou null si non trouvé
     */
    public function getContractTypeById($id) {
        return $this->find($id);
    }

    /**
     * Crée un nouveau type de contrat
     * @param array $data Données du type de contrat
     * @return int ID du type de contrat créé
     */
    public function createContractType($data) {
        // Récupérer le prochain ordre d'affichage disponible
        $nextOrder = $this->getNextDisplayOrder();
        
        // Préparer les données avec les valeurs par défaut
        $insertData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'default_tickets' => $data['default_tickets'] ?? null,
            'nb_inter_prev' => $data['nb_inter_prev'] ?? null,
            'ordre_affichage' => $nextOrder
        ];
        
        return parent::create($insertData);
    }

    /**
     * Met à jour un type de contrat
     * @param int $id ID du type de contrat
     * @param array $data Nouvelles données
     * @return bool True si la mise à jour a réussi
     */
    public function updateContractType($id, $data) {
        // Préparer les données à mettre à jour
        $updateData = [
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'default_tickets' => $data['default_tickets'] ?? null,
            'nb_inter_prev' => $data['nb_inter_prev'] ?? null
        ];
        
        return parent::update($id, $updateData);
    }

    /**
     * Supprime un type de contrat
     * @param int $id ID du type de contrat
     * @return bool True si la suppression a réussi
     */
    public function deleteContractType($id) {
        return parent::delete($id);
    }

    /**
     * Vérifie si un type de contrat est utilisé par des contrats existants
     * @param int $id ID du type de contrat
     * @return bool True si le type de contrat est utilisé
     */
    public function isContractTypeUsed($id) {
        $query = "SELECT COUNT(*) as count FROM contracts WHERE contract_type_id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    /**
     * Récupère le nombre de contrats utilisant ce type
     * @param int $id ID du type de contrat
     * @return int Nombre de contrats utilisant ce type
     */
    public function getContractCountByType($id) {
        $query = "SELECT COUNT(*) as count FROM contracts WHERE contract_type_id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    /**
     * Vérifie si un nom de type de contrat existe déjà
     * @param string $name Nom du type de contrat
     * @param int $excludeId ID à exclure (pour les mises à jour)
     * @return bool True si le nom existe déjà
     */
    public function isNameExists($name, $excludeId = null) {
        $query = "SELECT COUNT(*) as count FROM contract_types WHERE name = :name";
        $params = [':name' => $name];
        
        if ($excludeId) {
            $query .= " AND id != :exclude_id";
            $params[':exclude_id'] = $excludeId;
        }
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }

    /**
     * Met à jour l'ordre d'affichage d'un type de contrat
     * @param int $id ID du type de contrat
     * @param int $ordre Nouvel ordre d'affichage
     * @return bool True si la mise à jour a réussi
     */
    public function updateDisplayOrder($id, $ordre) {
        $query = "UPDATE contract_types SET ordre_affichage = :ordre WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':ordre', $ordre, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Récupère le prochain ordre d'affichage disponible
     * @return int Prochain ordre disponible
     */
    public function getNextDisplayOrder() {
        $query = "SELECT COALESCE(MAX(ordre_affichage), 0) + 1 as next_order FROM contract_types";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['next_order'];
    }
} 
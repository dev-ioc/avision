<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

class UserTypeModel extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
        $this->table = 'user_types';
    }

    /**
     * Récupère tous les types d'utilisateur
     */
    public function getAll() {
        try {
            $stmt = $this->db->prepare("
                SELECT DISTINCT id, name, description, group_id, created_at, updated_at 
                FROM user_types 
                ORDER BY name ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération des types d'utilisateur : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }

    /**
     * Récupère un type d'utilisateur par son ID
     */
    public function getById($id) {
        try {
            // D'abord essayer de récupérer depuis user_types via BaseModel
            $result = $this->find($id);
            
            if ($result) {
                return $result;
            }
            
            // Si pas trouvé dans user_types, vérifier si ce type est utilisé dans users
            $stmt = $this->db->prepare("
                SELECT DISTINCT
                    ? as id,
                    CONCAT('Type ', ?) as name,
                    'Type d\'utilisateur non défini' as description,
                    NOW() as created_at,
                    NOW() as updated_at
                FROM users u
                WHERE u.user_type_id = ?
                LIMIT 1
            ");
            $stmt->execute([$id, $id, $id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
            
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération du type d'utilisateur : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Vérifie si un nom de type d'utilisateur existe déjà
     */
    public function nameExists($name, $excludeId = null) {
        try {
            $sql = "SELECT COUNT(*) FROM user_types WHERE name = ?";
            $params = [$name];
            
            if ($excludeId) {
                $sql .= " AND id != ?";
                $params[] = $excludeId;
            }
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            custom_log("Erreur lors de la vérification du nom du type d'utilisateur : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Crée un nouveau type d'utilisateur
     */
    public function create($data) {
        try {
            $insertData = [
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'group_id' => $data['group_id']
            ];
            return parent::create($insertData);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la création du type d'utilisateur : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Met à jour un type d'utilisateur
     */
    public function update($id, $data) {
        try {
            $updateData = [
                'name' => $data['name'],
                'description' => $data['description'] ?? '',
                'group_id' => $data['group_id']
            ];
            return parent::update($id, $updateData);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la mise à jour du type d'utilisateur : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Supprime un type d'utilisateur
     */
    public function delete($id) {
        try {
            return parent::delete($id);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la suppression du type d'utilisateur : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère le nombre d'utilisateurs utilisant ce type
     */
    public function getUserCount($typeId) {
        try {
            // Compter les utilisateurs avec ce user_type_id
            $stmt = $this->db->prepare("
                SELECT COUNT(*) FROM users 
                WHERE user_type_id = ?
            ");
            $stmt->execute([$typeId]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            custom_log("Erreur lors du comptage des utilisateurs par type : " . $e->getMessage(), 'ERROR');
            return 0;
        }
    }

    /**
     * Récupère tous les groupes d'utilisateur
     */
    public function getAllGroups() {
        try {
            $stmt = $this->db->prepare("
                SELECT id, name, description, created_at, updated_at FROM user_groups 
                ORDER BY name ASC
            ");
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            custom_log("Erreur lors de la récupération des groupes d'utilisateur : " . $e->getMessage(), 'ERROR');
            return [];
        }
    }
}
?> 
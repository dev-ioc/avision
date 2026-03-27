<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

class DocumentationModel extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
        $this->table = 'documentation';
    }

    /**
     * Récupère tous les documents avec filtres
     */
    public function getAllDocuments($clientId = null, $siteId = null, $roomId = null) {
        $query = "SELECT d.*, c.name as client_name, s.name as site_name, r.name as room_name 
                 FROM {$this->table} d
                 LEFT JOIN clients c ON d.client_id = c.id
                 LEFT JOIN sites s ON d.site_id = s.id
                 LEFT JOIN rooms r ON d.room_id = r.id
                 WHERE 1=1";
        
        $params = [];
        
        if ($clientId) {
            $query .= " AND d.client_id = ?";
            $params[] = $clientId;
        }
        
        if ($siteId) {
            $query .= " AND d.site_id = ?";
            $params[] = $siteId;
        }
        
        if ($roomId) {
            $query .= " AND d.room_id = ?";
            $params[] = $roomId;
        }
        
        $query .= " ORDER BY d.created_at DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les documents d'un utilisateur spécifique
     */
    public function getUserDocuments($userId) {
        $query = "SELECT id, client_id, site_id, room_id, category_id, title, description, content, attachment_path, visible_by_client, created_by, created_at, updated_at FROM {$this->table} WHERE user_id = :user_id ORDER BY created_at DESC";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':user_id', $userId);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute un nouveau document
     */
    public function addDocument($data) {
        $query = "INSERT INTO {$this->table} (title, description, content, attachment_path, client_id, site_id, room_id, category_id, created_by, created_at) 
                 VALUES (:title, :description, :content, :attachment_path, :client_id, :site_id, :room_id, :category_id, :created_by, NOW())";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':content' => $data['content'] ?? null,
            ':attachment_path' => $data['attachment_path'],
            ':client_id' => $data['client_id'],
            ':site_id' => $data['site_id'],
            ':room_id' => $data['room_id'],
            ':category_id' => $data['category_id'],
            ':created_by' => $_SESSION['user']['id']
        ]);
    }

    /**
     * Supprime un document
     */
    public function deleteDocument($documentId) {
        $query = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->db->prepare($query);
        return $stmt->execute([':id' => $documentId]);
    }

    /**
     * Récupère les statistiques des documents par niveau
     */
    public function getDocumentStats() {
        $query = "
            SELECT 
                c.id as client_id,
                c.name as client_name,
                s.id as site_id,
                s.name as site_name,
                r.id as room_id,
                r.name as room_name,
                COUNT(d.id) as doc_count
            FROM clients c
            LEFT JOIN sites s ON s.client_id = c.id AND s.status = 1
            LEFT JOIN rooms r ON r.site_id = s.id AND r.status = 1
            LEFT JOIN documentation d ON (
                d.client_id = c.id 
                AND (d.site_id IS NULL OR d.site_id = s.id)
                AND (d.room_id IS NULL OR d.room_id = r.id)
            )
            WHERE c.status = 1
            GROUP BY c.id, s.id, r.id
            ORDER BY c.name, s.name, r.name
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un document spécifique par son ID
     */
    public function getDocumentById($documentId) {
        $query = "SELECT d.*, u.first_name as author_first_name, u.last_name as author_last_name
                  FROM {$this->table} d
                  LEFT JOIN users u ON d.created_by = u.id
                  WHERE d.id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $documentId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Log pour debug
        error_log("[DEBUG] DocumentationModel::getDocumentById - Document ID: " . $documentId);
        error_log("[DEBUG] DocumentationModel::getDocumentById - Result: " . ($result ? "Found" : "Not found"));
        
        return $result;
    }

    /**
     * Met à jour un document existant
     */
    public function updateDocument($id, $data) {
        // Log pour debug
        error_log("[DEBUG] DocumentationModel::updateDocument - Document ID: " . $id);
        error_log("[DEBUG] DocumentationModel::updateDocument - Data: " . json_encode($data));
        
        // Champs à mettre à jour
        $query = "UPDATE {$this->table} SET 
                    title = :title, 
                    description = :description, 
                    content = :content, 
                    attachment_path = :attachment_path, 
                    client_id = :client_id, 
                    site_id = :site_id, 
                    room_id = :room_id, 
                    category_id = :category_id, 
                    visible_by_client = :visible_by_client, 
                    updated_at = NOW()
                  WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        
        $params = [
            ':id' => $id,
            ':title' => $data['title'],
            ':description' => $data['description'],
            ':content' => $data['content'] ?? null,
            ':attachment_path' => $data['attachment_path'], // This will be null if removed, old path if kept, new path if updated
            ':client_id' => $data['client_id'],
            ':site_id' => $data['site_id'],
            ':room_id' => $data['room_id'],
            ':category_id' => $data['category_id'],
            ':visible_by_client' => $data['visible_by_client']
        ];
        
        // Log pour debug
        error_log("[DEBUG] DocumentationModel::updateDocument - Query: " . $query);
        error_log("[DEBUG] DocumentationModel::updateDocument - Params: " . json_encode($params));
        
        try {
            $result = $stmt->execute($params);
            error_log("[DEBUG] DocumentationModel::updateDocument - Execute result: " . ($result ? "SUCCESS" : "FAILED"));
            return $result;
        } catch (PDOException $e) {
            error_log("[ERROR] DocumentationModel::updateDocument - PDO Exception: " . $e->getMessage());
            error_log("[ERROR] DocumentationModel::updateDocument - Error code: " . $e->getCode());
            throw $e;
        }
    }
} 
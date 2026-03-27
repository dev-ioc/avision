<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

class SiteModel extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
        $this->table = 'sites';
    }

    /**
     * Récupère un site par son ID
     */
    public function getSiteById($id) {
        $query = "SELECT s.*, 
                 c.id as contact_id, c.first_name, c.last_name, c.phone1, c.phone2, c.email,
                 cl.name AS client_name
                 FROM sites s 
                 LEFT JOIN contacts c ON s.main_contact_id = c.id 
                 LEFT JOIN clients cl ON s.client_id = cl.id
                 WHERE s.id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Structurer les données du contact principal
        if ($result && $result['contact_id']) {
            $result['primary_contact'] = [
                'id' => $result['contact_id'],
                'first_name' => $result['first_name'],
                'last_name' => $result['last_name'],
                'phone1' => $result['phone1'],
                'phone2' => $result['phone2'],
                'email' => $result['email']
            ];
        }
        
        return $result;
    }

    /**
     * Récupère tous les sites d'un client
     */
    public function getSitesByClientId($clientId) {
        $query = "SELECT s.*, 
                 c.id as contact_id, c.first_name, c.last_name, c.phone1, c.phone2, c.email,
                 cl.name AS client_name
                 FROM sites s 
                 LEFT JOIN contacts c ON s.main_contact_id = c.id 
                 LEFT JOIN clients cl ON s.client_id = cl.id
                 WHERE s.client_id = :client_id 
                 ORDER BY s.name";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':client_id', $clientId, PDO::PARAM_INT);
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Structurer les données du contact principal pour chaque site
        foreach ($results as &$site) {
            if ($site['contact_id']) {
                $site['primary_contact'] = [
                    'id' => $site['contact_id'],
                    'first_name' => $site['first_name'],
                    'last_name' => $site['last_name'],
                    'phone1' => $site['phone1'],
                    'phone2' => $site['phone2'],
                    'email' => $site['email']
                ];
            }
        }
        
        return $results;
    }

    /**
     * Crée un nouveau site
     */
    public function createSite($data) {
        $query = "INSERT INTO sites (client_id, name, address, postal_code, city, phone, email, comment, main_contact_id, status, created_at, updated_at) 
                 VALUES (:client_id, :name, :address, :postal_code, :city, :phone, :email, :comment, :main_contact_id, :status, NOW(), NOW())";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':client_id', $data['client_id'], PDO::PARAM_INT);
        $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindParam(':address', $data['address'], PDO::PARAM_STR);
        $stmt->bindParam(':postal_code', $data['postal_code'], PDO::PARAM_STR);
        $stmt->bindParam(':city', $data['city'], PDO::PARAM_STR);
        $stmt->bindParam(':phone', $data['phone'], PDO::PARAM_STR);
        $stmt->bindParam(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindParam(':comment', $data['comment'], PDO::PARAM_STR);
        $stmt->bindParam(':main_contact_id', $data['main_contact_id'], PDO::PARAM_INT);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Met à jour un site existant
     */
    public function updateSite($id, $data) {
        $query = "UPDATE sites 
                 SET name = :name, 
                     address = :address, 
                     postal_code = :postal_code, 
                     city = :city, 
                     phone = :phone, 
                     email = :email, 
                     comment = :comment, 
                     main_contact_id = :main_contact_id, 
                     status = :status, 
                     updated_at = NOW() 
                 WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindParam(':address', $data['address'], PDO::PARAM_STR);
        $stmt->bindParam(':postal_code', $data['postal_code'], PDO::PARAM_STR);
        $stmt->bindParam(':city', $data['city'], PDO::PARAM_STR);
        $stmt->bindParam(':phone', $data['phone'], PDO::PARAM_STR);
        $stmt->bindParam(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindParam(':comment', $data['comment'], PDO::PARAM_STR);
        $stmt->bindParam(':main_contact_id', $data['main_contact_id'], PDO::PARAM_INT);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Supprime un site
     */
    public function deleteSite($id) {
        try {
            $this->db->beginTransaction();

            // 1. Supprimer les salles associées au site
            $queryRooms = "DELETE FROM rooms WHERE site_id = :site_id";
            $stmtRooms = $this->db->prepare($queryRooms);
            $stmtRooms->bindParam(':site_id', $id, PDO::PARAM_INT);
            $stmtRooms->execute();

            // 2. Supprimer le site lui-même (utilise BaseModel::delete())
            if (!parent::delete($id)) {
                // If site deletion fails, roll back
                $this->db->rollBack();
                error_log("Erreur lors de la suppression du site ID: " . $id . " - Échec de la suppression du site principal.");
                return false;
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de la suppression du site ID: " . $id . " et de ses salles: " . $e->getMessage());
            return false;
        }
    }

    public function getSiteCountByClientId($clientId) {
        $query = "SELECT COUNT(*) as count 
                 FROM sites 
                 WHERE client_id = :client_id 
                 AND status = 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':client_id', $clientId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    public function getRoomCountByClientId($clientId) {
        $query = "SELECT COUNT(DISTINCT r.id) as count 
                 FROM rooms r
                 JOIN sites s ON r.site_id = s.id
                 WHERE s.client_id = :client_id 
                 AND r.status = 1
                 AND s.status = 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':client_id', $clientId, PDO::PARAM_INT);
        $stmt->execute();
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    public function getAllSites() {
        $query = "SELECT id, client_id, name, address, postal_code, city, phone, email, comment, status, main_contact_id, created_at, updated_at FROM sites ORDER BY name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setSitePrimaryContact($siteId, $contactId) {
        $query = "UPDATE sites SET main_contact_id = :contact_id, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($query);
        if ($contactId === null) {
            $stmt->bindValue(':contact_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':contact_id', (int)$contactId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':id', (int)$siteId, PDO::PARAM_INT);
        return $stmt->execute();
    }
} 
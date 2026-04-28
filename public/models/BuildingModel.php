<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

class BuildingModel extends BaseModel
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->table = 'buildings';
    }

    /**
     * Récupère une salle par son ID
     */
    public function getBuildingById($id)
    {
        $query = "SELECT b.*, s.client_id 
                 FROM buildings b
                 JOIN sites s ON b.site_id = s.id 
                 WHERE b.id = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère toutes les salles d'un site
     */
    public function getBuildingsBySiteId($siteId, $activeOnly = false)
    {
        $query = "SELECT DISTINCT b.id, b.site_id, b.name, b.comment, b.status, b.created_at, b.updated_at,
                        c.first_name, c.last_name, s.client_id 
                 FROM buildings b 
                 LEFT JOIN contacts c ON b.main_contact_id = c.id 
                 JOIN sites s ON b.site_id = s.id 
                 WHERE b.site_id = :site_id";

        if ($activeOnly) {
            $query .= " AND b.status = 1";
        }

        $query .= " ORDER BY b.name";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':site_id', $siteId, PDO::PARAM_INT);
        $stmt->execute();
        custom_log('' . $siteId . '');
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère toutes les salles d'un client
     */
    public function getBuildingsByClientId($clientId, $activeOnly = false)
    {
        $query = "SELECT DISTINCT b.id, b.site_id, b.name, b.comment, b.status, b.created_at, b.updated_at,
                        c.first_name, c.last_name, s.client_id, s.name as site_name
                 FROM buildings b 
                 LEFT JOIN contacts c ON b.main_contact_id = c.id 
                 JOIN sites s ON b.site_id = s.id 
                 WHERE s.client_id = :client_id";

        if ($activeOnly) {
            $query .= " AND b.status = 1";
        }

        $query .= " ORDER BY s.name, b.name";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':client_id', $clientId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crée une nouvelle salle
     */
    public function createBuilding($data)
    {
        // Récupérer l'ID du client à partir du site
        $site = $this->getSiteById($data['site_id']);
        if (!$site) {
            return false;
        }

        $query = "INSERT INTO buildings (site_id, client_id, name, comment, main_contact_id, status, created_at, updated_at) 
                 VALUES (:site_id, :client_id, :name, :comment, :main_contact_id, :status, NOW(), NOW())";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':site_id', $data['site_id'], PDO::PARAM_INT);
        $stmt->bindParam(':client_id', $site['client_id'], PDO::PARAM_INT);
        $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindParam(':comment', $data['comment'], PDO::PARAM_STR);
        $stmt->bindParam(':main_contact_id', $data['main_contact_id'], PDO::PARAM_INT);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Met à jour une salle existante
     */
    public function updateBuilding($id, $data)
    {
        // Récupérer la salle existante pour obtenir le site_id
        $existingBuilding = $this->getBuildingById($id);
        if (!$existingBuilding) {
            return false;
        }

        // Récupérer l'ID du client à partir du site
        $site = $this->getSiteById($existingBuilding['site_id']);
        if (!$site) {
            return false;
        }

        $query = "UPDATE buildings 
                 SET name = :name, 
                     comment = :comment, 
                     main_contact_id = :main_contact_id, 
                     status = :status, 
                     client_id = :client_id,
                     updated_at = NOW() 
                 WHERE id = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindParam(':comment', $data['comment'], PDO::PARAM_STR);
        $stmt->bindParam(':main_contact_id', $data['main_contact_id'], PDO::PARAM_INT);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);
        $stmt->bindParam(':client_id', $site['client_id'], PDO::PARAM_INT);

        return $stmt->execute();
    }

    /**
     * Supprime une salle
     */
    public function deleteBuilding($id)
    {
        return parent::delete($id);
    }

    /**
     * Récupère un site par son ID
     */
    public function getSiteById($id)
    {
        $query = "SELECT id, client_id, name, address, postal_code, city, phone, email, comment, status, main_contact_id, created_at, updated_at FROM sites WHERE id = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllBuildings()
    {
        $query = "SELECT id, client_id, site_id, name, comment, status, main_contact_id, created_at, updated_at FROM rooms ORDER BY name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setBuildingPrimaryContact($buildingId, $contactId)
    {
        $query = "UPDATE buildings SET main_contact_id = :contact_id, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($query);
        if ($contactId === null) {
            $stmt->bindValue(':contact_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':contact_id', (int) $contactId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':id', (int) $buildingId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Vérifie s'il y a des doublons dans la table rooms
     */
    public function checkForDuplicates($siteId = null)
    {
        $whereClause = $siteId ? "WHERE site_id = :site_id" : "";
        $params = $siteId ? [':site_id' => $siteId] : [];

        $query = "SELECT name, site_id, COUNT(*) as count 
                 FROM buildings 
                 $whereClause 
                 GROUP BY name, site_id 
                 HAVING COUNT(*) > 1";

        $stmt = $this->db->prepare($query);
        if ($siteId) {
            $stmt->bindParam(':site_id', $siteId, PDO::PARAM_INT);
        }
        $stmt->execute();

        $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($duplicates)) {
            custom_log("DOUBLONS DÉTECTÉS dans la table buildings:", 'WARNING');
            foreach ($duplicates as $dup) {
                custom_log("Nom: '{$dup['name']}', Site_ID: {$dup['site_id']}, Compte: {$dup['count']}", 'WARNING');
            }
        } else {
            custom_log("Aucun doublon détecté dans la table buildings", 'DEBUG');
        }

        return $duplicates;
    }
}
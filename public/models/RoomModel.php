<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

class RoomModel extends BaseModel
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->table = 'rooms';
    }

    /**
     * Récupère une salle par son ID
     */
    public function getRoomById($id)
    {
        $query = "SELECT r.*, s.client_id 
                 FROM rooms r 
                 JOIN buildings b ON r.building_id = b.id 
                 WHERE r.id = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère toutes les salles d'un site
     */
    public function getRoomsByBuildingId($buildingId, $activeOnly = false)
    {
        $query = "SELECT DISTINCT r.id, r.building_id, r.name, r.comment, r.status, r.created_at, r.updated_at,
                        c.first_name, c.last_name, b.client_id 
                 FROM rooms r 
                 LEFT JOIN contacts c ON r.main_contact_id = c.id 
                 JOIN buildings b ON r.building_id = b.id 
                 WHERE r.building_id = :building_id";

        if ($activeOnly) {
            $query .= " AND r.status = 1";
        }

        $query .= " ORDER BY r.name";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':building_id', $buildingId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    /**
     * Compte le nombre de salles pour un client
     * @param int $clientId ID du client
     * @return int Nombre de salles
     */
    public function getRoomCountByClientId($clientId)
    {
        $query = "SELECT COUNT(r.id) as count
              FROM rooms r
              JOIN buildings b ON r.building_id = b.id
              JOIN sites s ON b.site_id = s.id
              WHERE s.client_id = :client_id";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':client_id' => $clientId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) ($result['count'] ?? 0);
    }

    /**
     * Récupère toutes les salles d'un client
     */
    public function getRoomsByClientId($clientId, $activeOnly = false)
    {
        $query = "SELECT DISTINCT r.id, r.building_id, r.name, r.comment, r.status, r.created_at, r.updated_at,
                c.first_name, c.last_name, s.client_id, b.name as building_name
         FROM rooms r
         LEFT JOIN contacts c ON r.main_contact_id = c.id
         JOIN buildings b ON r.building_id = b.id
         JOIN sites s ON b.site_id = s.id
         WHERE s.client_id = :client_id";

        if ($activeOnly) {
            $query .= " AND r.status = 1";
        }

        $query .= " ORDER BY s.name, r.name";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':client_id', $clientId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Crée une nouvelle salle
     */
    public function createRoom($data)
    {
        // Récupérer l'ID du client à partir du site
        $site = $this->getBuildingById($data['building_id']);
        if (!$site) {
            return false;
        }

        $query = "INSERT INTO rooms (building_id, client_id, name, comment, main_contact_id, status, created_at, updated_at) 
                 VALUES (:building_id, :client_id, :name, :comment, :main_contact_id, :status, NOW(), NOW())";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':building_id', $data['building_id'], PDO::PARAM_INT);
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
    public function updateRoom($id, $data)
    {
        // Récupérer la salle existante pour obtenir le building_id
        $existingRoom = $this->getRoomById($id);
        if (!$existingRoom) {
            return false;
        }

        // Récupérer l'ID du client à partir du site
        $site = $this->getBuildingById($existingRoom['building_id']);
        if (!$site) {
            return false;
        }

        $query = "UPDATE rooms 
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
    public function deleteRoom($id)
    {
        return parent::delete($id);
    }

    /**
     * Récupère un site par son ID
     */
    public function getBuildingById($id)
    {
        $query = "SELECT id, client_id, name, comment, status, main_contact_id, created_at, updated_at FROM buildings WHERE id = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getAllRooms()
    {
        $query = "SELECT id, client_id, building_id, name, comment, status, main_contact_id, created_at, updated_at FROM rooms ORDER BY name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function setRoomPrimaryContact($roomId, $contactId)
    {
        $query = "UPDATE rooms SET main_contact_id = :contact_id, updated_at = NOW() WHERE id = :id";
        $stmt = $this->db->prepare($query);
        if ($contactId === null) {
            $stmt->bindValue(':contact_id', null, PDO::PARAM_NULL);
        } else {
            $stmt->bindValue(':contact_id', (int) $contactId, PDO::PARAM_INT);
        }
        $stmt->bindValue(':id', (int) $roomId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Vérifie s'il y a des doublons dans la table rooms
     */
    public function checkForDuplicates($buildingId = null)
    {
        $whereClause = $buildingId ? "WHERE building_id = :building_id" : "";
        $params = $buildingId ? [':building_id' => $buildingId] : [];

        $query = "SELECT name, building_id, COUNT(*) as count 
                 FROM rooms 
                 $whereClause 
                 GROUP BY name, building_id 
                 HAVING COUNT(*) > 1";

        $stmt = $this->db->prepare($query);
        if ($buildingId) {
            $stmt->bindParam(':building_id', $buildingId, PDO::PARAM_INT);
        }
        $stmt->execute();

        $duplicates = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (!empty($duplicates)) {
            custom_log("DOUBLONS DÉTECTÉS dans la table rooms:", 'WARNING');
            foreach ($duplicates as $dup) {
                custom_log("Nom: '{$dup['name']}', building_id: {$dup['building_id']}, Compte: {$dup['count']}", 'WARNING');
            }
        } else {
            custom_log("Aucun doublon détecté dans la table rooms", 'DEBUG');
        }

        return $duplicates;
    }
}
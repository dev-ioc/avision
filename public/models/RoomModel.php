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
             JOIN sites s ON b.site_id = s.id
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
        $query = "SELECT DISTINCT r.id, r.building_id,r.delivery_date,r.installation_closed, r.name, r.comment, r.status, r.qr_code_edited, r.created_at, r.updated_at,
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
     * Récupère toutes les salles d'un client (via les sites et bâtiments)
     * @param int $clientId ID du client
     * @return array Liste des salles
     */
    public function getRoomsByClientId($clientId)
    {
        $query = "SELECT r.id, r.name, r.building_id, b.site_id, s.name as site_name, b.name as building_name
              FROM rooms r
              JOIN buildings b ON r.building_id = b.id
              JOIN sites s ON b.site_id = s.id
              WHERE s.client_id = :client_id
              ORDER BY s.name, b.name, r.name";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':client_id' => $clientId]);
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
                 delivery_date = :delivery_date,
                 installation_closed = :installation_closed,
                 installation_closed_at = :installation_closed_at,
                 installation_alert_email = :installation_alert_email,
                 updated_at = NOW() 
             WHERE id = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
        $stmt->bindParam(':comment', $data['comment'], PDO::PARAM_STR);
        $stmt->bindParam(':main_contact_id', $data['main_contact_id'], PDO::PARAM_INT);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);
        $stmt->bindParam(':client_id', $site['client_id'], PDO::PARAM_INT);
        $stmt->bindParam(':delivery_date', $data['delivery_date'], PDO::PARAM_STR);
        $stmt->bindParam(':installation_closed', $data['installation_closed'], PDO::PARAM_INT);
        $stmt->bindParam(':installation_closed_at', $data['installation_closed_at'], PDO::PARAM_STR);
        $stmt->bindParam(':installation_alert_email', $data['installation_alert_email'], PDO::PARAM_STR);

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
    public function updateQrCodeStatus($id, $edited)
    {
        try {
            $query = "UPDATE rooms SET 
                    qr_code_edited = :qr_code_edited,
                    updated_at = NOW()
                WHERE id = :id";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':qr_code_edited', $edited ? 1 : 0, PDO::PARAM_INT);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour du statut QR Code: " . $e->getMessage());
            return false;
        }
    }
    /**
     * Récupère toutes les salles d'un site (via les bâtiments)
     * @param int $siteId ID du site
     * @return array Liste des salles
     */
    public function getRoomsBySiteId($siteId)
    {
        $query = "SELECT r.id, r.name, r.building_id, b.site_id, s.name as site_name, b.name as building_name
          FROM rooms r
          JOIN buildings b ON r.building_id = b.id
          JOIN sites s ON b.site_id = s.id
          WHERE b.site_id = :site_id
          ORDER BY b.name, r.name";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':site_id' => $siteId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marque l'alerte comme envoyée pour éviter les doublons
     */
    public function markInstallationAlertSent($roomId)
    {
        $query = "UPDATE rooms SET installation_alert_sent = 1 WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $roomId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Constantes de calendrier des alertes
     */
    private const ALERT_FIRST_REMINDER_DAYS = 21;   // 3 semaines
    private const ALERT_MONTH_REMINDER_DAYS = 30;   // 1 mois
    private const ALERT_WEEKLY_INTERVAL_DAYS = 7;
    private const ALERT_MAX_WEEKLY_REMINDERS = 5;   // à confirmer avec le client

    /**
     * Récupère les salles nécessitant un envoi d'alerte à cet instant,
     * quel que soit le palier (3 semaines / 1 mois / rappel hebdo)
     */
    public function getRoomsNeedingInstallationAlert()
    {
        $maxStage = 2 + self::ALERT_MAX_WEEKLY_REMINDERS;

        $query = "SELECT r.id, r.name, r.delivery_date, r.main_contact_id,
                     r.installation_alert_email, r.installation_alert_stage,
                     r.installation_last_alert_at,
                     b.client_id, b.name AS building_name,
                     c.name AS client_name
              FROM rooms r
              INNER JOIN buildings b ON r.building_id = b.id
              INNER JOIN clients c ON b.client_id = c.id
              WHERE r.delivery_date IS NOT NULL
                AND r.installation_closed = 0
                AND (
                      (r.installation_alert_stage = 0 
                          AND DATE_ADD(r.delivery_date, INTERVAL :firstDays DAY) <= NOW())
                   OR (r.installation_alert_stage = 1 
                          AND DATE_ADD(r.delivery_date, INTERVAL :monthDays DAY) <= NOW())
                   OR (r.installation_alert_stage >= 2 
                          AND r.installation_alert_stage < :maxStage
                          AND r.installation_last_alert_at IS NOT NULL
                          AND DATE_ADD(r.installation_last_alert_at, INTERVAL :weeklyDays DAY) <= NOW())
                    )";

        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':firstDays', self::ALERT_FIRST_REMINDER_DAYS, PDO::PARAM_INT);
        $stmt->bindValue(':monthDays', self::ALERT_MONTH_REMINDER_DAYS, PDO::PARAM_INT);
        $stmt->bindValue(':weeklyDays', self::ALERT_WEEKLY_INTERVAL_DAYS, PDO::PARAM_INT);
        $stmt->bindValue(':maxStage', $maxStage, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Détermine le libellé du palier d'alerte en fonction du stage actuel
     * (utile pour construire le sujet/corps du mail)
     */
    public function getAlertStageLabel($currentStage)
    {
        if ($currentStage === 0) {
            return 'first'; // sera envoyé -> deviendra stage 1
        }
        if ($currentStage === 1) {
            return 'month'; // sera envoyé -> deviendra stage 2
        }
        return 'weekly'; // stage >= 2 -> rappel hebdo
    }

    /**
     * Fait avancer la salle au palier d'alerte suivant
     */
    public function advanceInstallationAlertStage($roomId, $currentStage)
    {
        $newStage = $currentStage + 1;
        $query = "UPDATE rooms 
                  SET installation_alert_stage = :stage, installation_last_alert_at = NOW() 
                  WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindValue(':stage', $newStage, PDO::PARAM_INT);
        $stmt->bindValue(':id', $roomId, PDO::PARAM_INT);
        return $stmt->execute();
    }
}
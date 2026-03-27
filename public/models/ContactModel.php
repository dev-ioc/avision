<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

class ContactModel extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
        $this->table = 'contacts';
    }

    public function getContactsByClientId($clientId) {
        $query = "SELECT 
                    c.*,
                    u.username as user_username,
                    u.email as user_email
                FROM contacts c
                LEFT JOIN users u ON c.user_id = u.id
                WHERE c.client_id = :client_id AND c.status = 1
                ORDER BY c.last_name, c.first_name";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':client_id', $clientId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getContactById($id) {
        $query = "SELECT 
                    c.*,
                    u.username as user_username,
                    u.email as user_email,
                    cl.name as client_name
                FROM contacts c
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN clients cl ON c.client_id = cl.id
                WHERE c.id = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getContactsBySiteId($siteId) {
        $query = "SELECT 
                    c.*,
                    u.username as user_username,
                    u.email as user_email
                FROM contacts c
                LEFT JOIN users u ON c.user_id = u.id
                INNER JOIN sites s ON s.client_id = c.client_id
                WHERE s.id = :site_id AND c.status = 1
                ORDER BY c.last_name, c.first_name";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':site_id', $siteId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getContactsByRoomId($roomId) {
        $query = "SELECT 
                    c.*,
                    u.username as user_username,
                    u.email as user_email
                FROM contacts c
                LEFT JOIN users u ON c.user_id = u.id
                INNER JOIN rooms r ON r.client_id = c.client_id
                WHERE r.id = :room_id AND c.status = 1
                ORDER BY c.last_name, c.first_name";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':room_id', $roomId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAllContacts() {
        $query = "SELECT 
                    c.*,
                    u.username as user_username,
                    u.email as user_email,
                    cl.name as client_name
                FROM contacts c
                LEFT JOIN users u ON c.user_id = u.id
                LEFT JOIN clients cl ON c.client_id = cl.id
                WHERE c.status = 1
                ORDER BY c.last_name, c.first_name";

        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createContact($data) {
        $query = "INSERT INTO contacts (
                    client_id,
                    first_name,
                    last_name,
                    fonction,
                    phone1,
                    phone2,
                    email,
                    comment,
                    has_user_account,
                    status,
                    created_at,
                    updated_at
                ) VALUES (
                    :client_id,
                    :first_name,
                    :last_name,
                    :fonction,
                    :phone1,
                    :phone2,
                    :email,
                    :comment,
                    :has_user_account,
                    :status,
                    NOW(),
                    NOW()
                )";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':client_id', $data['client_id'], PDO::PARAM_INT);
        $stmt->bindParam(':first_name', $data['first_name'], PDO::PARAM_STR);
        $stmt->bindParam(':last_name', $data['last_name'], PDO::PARAM_STR);
        $stmt->bindParam(':fonction', $data['fonction'], PDO::PARAM_STR);
        $stmt->bindParam(':phone1', $data['phone1'], PDO::PARAM_STR);
        $stmt->bindParam(':phone2', $data['phone2'], PDO::PARAM_STR);
        $stmt->bindParam(':email', $data['email'], PDO::PARAM_STR);
        $stmt->bindParam(':comment', $data['comment'], PDO::PARAM_STR);
        $stmt->bindParam(':has_user_account', $data['has_user_account'], PDO::PARAM_INT);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);

        return $stmt->execute();
    }

    public function updateContact($id, $data) {
        try {
            $query = "UPDATE contacts SET 
                        first_name = :first_name,
                        last_name = :last_name,
                        fonction = :fonction,
                        phone1 = :phone1,
                        phone2 = :phone2,
                        email = :email,
                        comment = :comment,
                        updated_at = NOW()
                    WHERE id = :id";

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':first_name', $data['first_name'], PDO::PARAM_STR);
            $stmt->bindParam(':last_name', $data['last_name'], PDO::PARAM_STR);
            $stmt->bindParam(':fonction', $data['fonction'], PDO::PARAM_STR);
            $stmt->bindParam(':phone1', $data['phone1'], PDO::PARAM_STR);
            $stmt->bindParam(':phone2', $data['phone2'], PDO::PARAM_STR);
            $stmt->bindParam(':email', $data['email'], PDO::PARAM_STR);
            $stmt->bindParam(':comment', $data['comment'], PDO::PARAM_STR);

            return $stmt->execute();
        } catch (PDOException $e) {
            error_log("Erreur lors de la mise à jour du contact: " . $e->getMessage());
            return false;
        }
    }

    public function deleteContact($id) {
        try {
            $this->db->beginTransaction();

            // Supprimer les localisations de l'utilisateur associé au contact
            $query = "DELETE ul FROM user_locations ul 
                     INNER JOIN contacts c ON c.user_id = ul.user_id 
                     WHERE c.id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();

            // Supprimer le contact (utilise BaseModel::delete())
            if (!parent::delete($id)) {
                throw new Exception("Erreur lors de la suppression du contact");
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            error_log("Erreur lors de la suppression du contact: " . $e->getMessage());
            return false;
        }
    }
} 
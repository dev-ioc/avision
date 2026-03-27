<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../classes/Models/BaseModel.php';

/**
 * Modèle pour la gestion de l'historique des envois d'emails
 */
class MailHistoryModel extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
        $this->table = 'mail_history';
    }

    /**
     * Récupère l'historique des emails pour une intervention
     * @param int $interventionId ID de l'intervention
     * @return array Liste des emails envoyés
     */
    public function getByIntervention($interventionId) {
        $sql = "SELECT mh.*, mt.name as template_name, mt.template_type
                FROM " . $this->table . " mh
                LEFT JOIN mail_templates mt ON mh.template_id = mt.id
                WHERE mh.intervention_id = ?
                ORDER BY mh.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$interventionId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère l'historique complet avec pagination
     * @param int $limit Limite de résultats
     * @param int $offset Offset
     * @return array Liste des emails
     */
    public function getAll($limit = 50, $offset = 0) {
        $sql = "SELECT mh.*, mt.name as template_name, mt.template_type,
                       i.reference as intervention_reference, i.title as intervention_title
                FROM " . $this->table . " mh
                LEFT JOIN mail_templates mt ON mh.template_id = mt.id
                LEFT JOIN interventions i ON mh.intervention_id = i.id
                ORDER BY mh.created_at DESC
                LIMIT ? OFFSET ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques des envois
     * @return array Statistiques
     */
    public function getStats() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_count,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_count
                FROM " . $this->table;
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Sauvegarde un email dans l'historique
     * @param int $interventionId ID de l'intervention
     * @param int $templateId ID du template
     * @param array $recipient Destinataire
     * @param string $subject Sujet
     * @param string $body Corps
     * @param string $attachmentPath Chemin vers la pièce jointe
     * @param string|null $sendUuid Identifiant d'envoi (regroupement)
     * @param string|null $ccSnapshot Snapshot du CC forcé utilisé
     * @return int ID de l'historique créé
     */
    public function saveToHistory($interventionId, $templateId, $recipient, $subject, $body, $attachmentPath = null, $sendUuid = null, $ccSnapshot = null) {
        // Si template_id est null (message personnalisé), utiliser NULL explicitement
        // La colonne template_id doit accepter NULL dans la base de données
        $sql = "INSERT INTO " . $this->table . " 
                (intervention_id, template_id, send_uuid, recipient_email, recipient_name, cc_snapshot, subject, body, attachment_path, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            $interventionId,
            $templateId, // Peut être null pour les messages personnalisés
            $sendUuid,
            $recipient['email'],
            $recipient['name'],
            $ccSnapshot,
            $subject,
            $body,
            $attachmentPath
        ]);
        
        return $this->db->lastInsertId();
    }

    /**
     * Met à jour le statut d'un email dans l'historique
     * @param int $historyId ID de l'historique
     * @param string $status Nouveau statut
     * @param string $errorMessage Message d'erreur (optionnel)
     * @return bool Succès de la mise à jour
     */
    public function updateHistoryStatus($historyId, $status, $errorMessage = null) {
        $sql = "UPDATE " . $this->table . " SET 
                status = ?, 
                error_message = ?, 
                sent_at = ? 
                WHERE id = ?";
        
        $sentAt = ($status === 'sent') ? date('Y-m-d H:i:s') : null;
        
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([$status, $errorMessage, $sentAt, $historyId]);
    }

    /**
     * Récupère un email par son ID
     * @param int $id ID de l'historique
     * @return array|null L'email ou null
     */
    public function getById($id) {
        $sql = "SELECT mh.*, mt.name as template_name, mt.template_type,
                       i.reference as intervention_reference, i.title as intervention_title
                FROM " . $this->table . " mh
                LEFT JOIN mail_templates mt ON mh.template_id = mt.id
                LEFT JOIN interventions i ON mh.intervention_id = i.id
                WHERE mh.id = ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les emails en échec
     * @param int $limit Limite de résultats
     * @return array Liste des emails en échec
     */
    public function getFailedEmails($limit = 20) {
        $sql = "SELECT mh.*, mt.name as template_name,
                       i.reference as intervention_reference, i.title as intervention_title
                FROM " . $this->table . " mh
                LEFT JOIN mail_templates mt ON mh.template_id = mt.id
                LEFT JOIN interventions i ON mh.intervention_id = i.id
                WHERE mh.status = 'failed'
                ORDER BY mh.created_at DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Supprime les anciens emails de l'historique
     * @param int $days Nombre de jours à conserver
     * @return int Nombre d'emails supprimés
     */
    public function cleanupOldEmails($days = 90) {
        $sql = "DELETE FROM " . $this->table . " 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY)";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$days]);
        return $stmt->rowCount();
    }

    /**
     * Récupère les emails par statut
     * @param string $status Statut (pending, sent, failed)
     * @param int $limit Limite de résultats
     * @return array Liste des emails
     */
    public function getByStatus($status, $limit = 50) {
        $sql = "SELECT mh.*, mt.name as template_name,
                       i.reference as intervention_reference, i.title as intervention_title
                FROM " . $this->table . " mh
                LEFT JOIN mail_templates mt ON mh.template_id = mt.id
                LEFT JOIN interventions i ON mh.intervention_id = i.id
                WHERE mh.status = ?
                ORDER BY mh.created_at DESC
                LIMIT ?";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$status, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}


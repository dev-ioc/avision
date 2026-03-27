<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

class ContractModel extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
        $this->table = 'contracts';
    }

    public function getContractsByClientId($clientId, $siteId = null, $roomId = null, $includeInactive = false) {
        $sql = "SELECT c.*, ct.name as contract_type_name
                FROM contracts c 
                LEFT JOIN contract_types ct ON c.contract_type_id = ct.id 
                WHERE c.client_id = :client_id";
        
        if (!$includeInactive) {
            $sql .= " AND c.status = 'actif'";
        }
        
        $params = [':client_id' => $clientId];

        // Si on a des filtres de site/salle, on doit inclure :
        // 1. Les contrats "hors contrat" (contract_type_id IS NULL)
        // 2. Les contrats associés à la salle/site spécifique
        // 3. Les contrats généraux du client (sans restriction de salle)
        if ($roomId || $siteId) {
            $sql .= " AND (";
            
            // Contrats "hors contrat"
            $sql .= "c.contract_type_id IS NULL OR c.name LIKE '%hors contrat%' OR ct.name LIKE '%hors contrat%'";
            
            // Contrats associés à la salle spécifique
            if ($roomId) {
                $sql .= " OR EXISTS (
                    SELECT 1 FROM contract_rooms cr1 
                    WHERE cr1.contract_id = c.id AND cr1.room_id = :room_id
                )";
                $params[':room_id'] = $roomId;
            }
            
            // Contrats associés au site spécifique
            if ($siteId) {
                $sql .= " OR EXISTS (
                    SELECT 1 FROM contract_rooms cr2 
                    JOIN rooms r ON cr2.room_id = r.id 
                    WHERE cr2.contract_id = c.id AND r.site_id = :site_id
                )";
                $params[':site_id'] = $siteId;
            }
            
            // Contrats généraux du client (sans restriction de salle)
            $sql .= " OR NOT EXISTS (
                SELECT 1 FROM contract_rooms cr3 
                WHERE cr3.contract_id = c.id
            )";
            
            $sql .= ")";
        }

        $sql .= " ORDER BY c.end_date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Ne garder que les contrats dont l'ID est numérique (vrais contrats)
        $contracts = array_filter($contracts, function($contract) {
            return is_numeric($contract['id']);
        });

        // Pour chaque contrat, récupérer les salles associées
        foreach ($contracts as &$contract) {
            $contract['rooms'] = $this->getContractRooms($contract['id']);
        }

        return array_values($contracts);
    }

    public function getContractCountByClientId($clientId) {
        $query = "SELECT COUNT(*) as count 
                FROM contracts 
                WHERE client_id = :client_id 
                AND status = 'actif'
                AND contract_type_id IS NOT NULL";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':client_id', $clientId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['count'];
    }

    public function getContractById($id) {
        $query = "SELECT 
                    c.*,
                    ct.name as contract_type_name,
                    cl.name as client_name,
                    al.name as access_level_name,
                    al.description as access_level_description
                FROM contracts c
                LEFT JOIN contract_types ct ON c.contract_type_id = ct.id
                LEFT JOIN clients cl ON c.client_id = cl.id
                LEFT JOIN contract_access_levels al ON c.access_level_id = al.id
                WHERE c.id = :id";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        $contract = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($contract) {
            // Récupérer les salles associées
            $contract['rooms'] = $this->getContractRooms($id);
        }

        return $contract;
    }

    public function getContractTypes() {
        $query = "SELECT id, name, description, default_tickets, nb_inter_prev, ordre_affichage, created_at, updated_at FROM contract_types ORDER BY ordre_affichage, name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un type de contrat par son ID
     * @param int $id ID du type de contrat
     * @return array|null Les données du type de contrat ou null si non trouvé
     */
    public function getContractTypeById($id) {
        $query = "SELECT id, name, description, default_tickets, nb_inter_prev, ordre_affichage, created_at, updated_at FROM contract_types WHERE id = :id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function createContract($data) {
        $this->db->beginTransaction();
        
        try {
            $query = "INSERT INTO contracts (
                        client_id,
                        contract_type_id,
                        access_level_id,
                        name,
                        start_date,
                        end_date,
                        tickets_number,
                        tickets_remaining,
                        comment,
                        status,
                        reminder_enabled,
                        reminder_days,
                        renouvellement_tacite,
                        num_facture,
                        tarif,
                        indice,
                        isticketcontract,
                        created_at,
                        updated_at
                    ) VALUES (
                        :client_id,
                        :contract_type_id,
                        :access_level_id,
                        :name,
                        :start_date,
                        :end_date,
                        :tickets_number,
                        :tickets_remaining,
                        :comment,
                        :status,
                        :reminder_enabled,
                        :reminder_days,
                        :renouvellement_tacite,
                        :num_facture,
                        :tarif,
                        :indice,
                        :isticketcontract,
                        NOW(),
                        NOW()
                    )";

            // Préparer les valeurs avec des valeurs par défaut
            $comment = $data['comment'] ?? null;
            $reminderEnabled = $data['reminder_enabled'] ?? 1;
            $reminderDays = $data['reminder_days'] ?? 30;
            $renouvellementTacite = $data['renouvellement_tacite'] ?? 0;
            $numFacture = $data['num_facture'] ?? null;
            $tarif = !empty($data['tarif']) ? $data['tarif'] : null;
            $indice = $data['indice'] ?? null;
            $isticketcontract = $data['isticketcontract'] ?? 0;

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':client_id', $data['client_id'], PDO::PARAM_INT);
            $stmt->bindParam(':contract_type_id', $data['contract_type_id'], PDO::PARAM_INT);
            $stmt->bindParam(':access_level_id', $data['access_level_id'], PDO::PARAM_INT);
            $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindParam(':start_date', $data['start_date'], PDO::PARAM_STR);
            $stmt->bindParam(':end_date', $data['end_date'], PDO::PARAM_STR);
            $stmt->bindParam(':tickets_number', $data['tickets_number'], PDO::PARAM_INT);
            $stmt->bindParam(':tickets_remaining', $data['tickets_remaining'], PDO::PARAM_INT);
            $stmt->bindParam(':comment', $comment, PDO::PARAM_STR);
            $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
            $stmt->bindParam(':reminder_enabled', $reminderEnabled, PDO::PARAM_INT);
            $stmt->bindParam(':reminder_days', $reminderDays, PDO::PARAM_INT);
            $stmt->bindParam(':renouvellement_tacite', $renouvellementTacite, PDO::PARAM_INT);
            $stmt->bindParam(':num_facture', $numFacture, PDO::PARAM_STR);
            $stmt->bindParam(':tarif', $tarif, PDO::PARAM_STR);
            $stmt->bindParam(':indice', $indice, PDO::PARAM_STR);
            $stmt->bindParam(':isticketcontract', $isticketcontract, PDO::PARAM_INT);

            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de la création du contrat");
            }

            $contractId = $this->db->lastInsertId();

            // Enregistrer la création du contrat dans l'historique
            $this->recordContractCreation($contractId, $data);

            // Ajouter les salles associées si fournies
            if (!empty($data['rooms']) && is_array($data['rooms'])) {
                $this->addContractRooms($contractId, $data['rooms']);
                
                // Enregistrer les salles dans l'historique lors de la création
                $newRooms = $this->getContractRooms($contractId);
                $newRoomNames = array_map(function($room) {
                    return $room['site_name'] . ' - ' . $room['room_name'];
                }, $newRooms);
                $newRoomsText = !empty($newRoomNames) ? implode(', ', $newRoomNames) : 'Aucune salle';
                
                if ($newRoomsText !== 'Aucune salle') {
                    $this->recordRoomChanges($contractId, 'Aucune salle', $newRoomsText);
                }
            }

            $this->db->commit();
            return $contractId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Met à jour un contrat
     * 
     * @param int $id ID du contrat à mettre à jour
     * @param array $data Données du contrat à mettre à jour
     * @return bool True si la mise à jour a réussi, false sinon
     */
    public function updateContract($id, $data) {
        $this->db->beginTransaction();
        
        try {
            $query = "UPDATE contracts SET
                        client_id = :client_id,
                        contract_type_id = :contract_type_id,
                        access_level_id = :access_level_id,
                        name = :name,
                        start_date = :start_date,
                        end_date = :end_date,
                        tickets_number = :tickets_number,
                        tickets_remaining = :tickets_remaining,
                        comment = :comment,
                        status = :status,
                        reminder_enabled = :reminder_enabled,
                        reminder_days = :reminder_days,
                        renouvellement_tacite = :renouvellement_tacite,
                        num_facture = :num_facture,
                        tarif = :tarif,
                        indice = :indice,
                        isticketcontract = :isticketcontract,
                        updated_at = NOW()
                    WHERE id = :id";

            // Préparer les valeurs avec des valeurs par défaut
            $comment = $data['comment'] ?? null;
            $reminderEnabled = $data['reminder_enabled'] ?? 1;
            $reminderDays = $data['reminder_days'] ?? 30;
            $renouvellementTacite = $data['renouvellement_tacite'] ?? 0;
            $numFacture = $data['num_facture'] ?? null;
            $tarif = !empty($data['tarif']) ? $data['tarif'] : null;
            $indice = $data['indice'] ?? null;
            $isticketcontract = $data['isticketcontract'] ?? 0;

            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->bindParam(':client_id', $data['client_id'], PDO::PARAM_INT);
            $stmt->bindParam(':contract_type_id', $data['contract_type_id'], PDO::PARAM_INT);
            $stmt->bindParam(':access_level_id', $data['access_level_id'], PDO::PARAM_INT);
            $stmt->bindParam(':name', $data['name'], PDO::PARAM_STR);
            $stmt->bindParam(':start_date', $data['start_date'], PDO::PARAM_STR);
            $stmt->bindParam(':end_date', $data['end_date'], PDO::PARAM_STR);
            $stmt->bindParam(':tickets_number', $data['tickets_number'], PDO::PARAM_INT);
            $stmt->bindParam(':tickets_remaining', $data['tickets_remaining'], PDO::PARAM_INT);
            $stmt->bindParam(':comment', $comment, PDO::PARAM_STR);
            $stmt->bindParam(':status', $data['status'], PDO::PARAM_STR);
            $stmt->bindParam(':reminder_enabled', $reminderEnabled, PDO::PARAM_INT);
            $stmt->bindParam(':reminder_days', $reminderDays, PDO::PARAM_INT);
            $stmt->bindParam(':renouvellement_tacite', $renouvellementTacite, PDO::PARAM_INT);
            $stmt->bindParam(':num_facture', $numFacture, PDO::PARAM_STR);
            $stmt->bindParam(':tarif', $tarif, PDO::PARAM_STR);
            $stmt->bindParam(':indice', $indice, PDO::PARAM_STR);
            $stmt->bindParam(':isticketcontract', $isticketcontract, PDO::PARAM_INT);

            if (!$stmt->execute()) {
                throw new Exception("Erreur lors de la mise à jour du contrat");
            }

            // Mettre à jour les salles associées si fournies
            if (isset($data['rooms'])) {
                $this->updateContractRooms($id, $data['rooms']);
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Ajoute des salles à un contrat
     */
    private function addContractRooms($contractId, $roomIds) {
        $query = "INSERT INTO contract_rooms (contract_id, room_id, created_at) VALUES (:contract_id, :room_id, NOW())";
        $stmt = $this->db->prepare($query);
        
        foreach ($roomIds as $roomId) {
            $stmt->execute([':contract_id' => $contractId, ':room_id' => $roomId]);
        }
    }

    /**
     * Met à jour les salles associées à un contrat
     */
    private function updateContractRooms($contractId, $roomIds) {
        // Récupérer les salles actuelles pour l'historique
        $oldRooms = $this->getContractRooms($contractId);
        $oldRoomNames = array_map(function($room) {
            return $room['site_name'] . ' - ' . $room['room_name'];
        }, $oldRooms);
        $oldRoomsText = !empty($oldRoomNames) ? implode(', ', $oldRoomNames) : 'Aucune salle';

        // Supprimer toutes les associations existantes
        $deleteQuery = "DELETE FROM contract_rooms WHERE contract_id = :contract_id";
        $deleteStmt = $this->db->prepare($deleteQuery);
        $deleteStmt->execute([':contract_id' => $contractId]);

        // Ajouter les nouvelles associations
        if (!empty($roomIds)) {
            $this->addContractRooms($contractId, $roomIds);
        }

        // Récupérer les nouvelles salles pour l'historique
        $newRooms = $this->getContractRooms($contractId);
        $newRoomNames = array_map(function($room) {
            return $room['site_name'] . ' - ' . $room['room_name'];
        }, $newRooms);
        $newRoomsText = !empty($newRoomNames) ? implode(', ', $newRoomNames) : 'Aucune salle';

        // Enregistrer le changement dans l'historique si les salles ont changé
        if ($oldRoomsText !== $newRoomsText) {
            $this->recordRoomChanges($contractId, $oldRoomsText, $newRoomsText);
        }
    }

    public function getAllContracts($filters = []) {
        $sql = "SELECT DISTINCT
                    c.*,
                    ct.name as contract_type_name,
                    cl.name as client_name
                FROM contracts c
                LEFT JOIN contract_types ct ON c.contract_type_id = ct.id
                LEFT JOIN clients cl ON c.client_id = cl.id
                WHERE 1=1
                AND c.contract_type_id IS NOT NULL";
        
        $params = [];

        // Appliquer les filtres
        if (!empty($filters['client_id'])) {
            $sql .= " AND c.client_id = :client_id";
            $params[':client_id'] = $filters['client_id'];
        }

        if (!empty($filters['contract_type_id'])) {
            $sql .= " AND c.contract_type_id = :contract_type_id";
            $params[':contract_type_id'] = $filters['contract_type_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND c.status = :status";
            $params[':status'] = $filters['status'];
        }

        // Filtre par type de tickets
        if (!empty($filters['ticket_type'])) {
            if ($filters['ticket_type'] === 'with_tickets') {
                $sql .= " AND c.isticketcontract = 1";
            } elseif ($filters['ticket_type'] === 'without_tickets') {
                $sql .= " AND c.isticketcontract = 0";
            }
        }

        $sql .= " ORDER BY c.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pour chaque contrat, récupérer les salles associées
        foreach ($contracts as &$contract) {
            $contract['rooms'] = $this->getContractRooms($contract['id']);
        }

        return $contracts;
    }

    /**
     * Récupère les salles associées à un contrat
     */
    public function getContractRooms($contractId) {
        $sql = "SELECT r.id as room_id, r.name as room_name, s.name as site_name
                FROM contract_rooms cr
                JOIN rooms r ON cr.room_id = r.id
                JOIN sites s ON r.site_id = s.id
                WHERE cr.contract_id = :contract_id
                ORDER BY s.name, r.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':contract_id' => $contractId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Supprime un contrat
     * 
     * @param int $id ID du contrat à supprimer
     * @return bool True si la suppression a réussi, false sinon
     */
    public function deleteContract($id) {
        $this->db->beginTransaction();
        
        try {
            // Supprimer d'abord l'historique du contrat
            $deleteHistoryQuery = "DELETE FROM contract_history WHERE contract_id = :contract_id";
            $deleteHistoryStmt = $this->db->prepare($deleteHistoryQuery);
            $deleteHistoryStmt->execute([':contract_id' => $id]);

            // Supprimer ensuite les associations dans contract_rooms
            $deleteRoomsQuery = "DELETE FROM contract_rooms WHERE contract_id = :contract_id";
            $deleteRoomsStmt = $this->db->prepare($deleteRoomsQuery);
            $deleteRoomsStmt->execute([':contract_id' => $id]);

            // Supprimer enfin le contrat
            $deleteContractQuery = "DELETE FROM contracts WHERE id = :id";
            $deleteContractStmt = $this->db->prepare($deleteContractQuery);
            $deleteContractStmt->execute([':id' => $id]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Récupère toutes les salles d'un client avec leurs sites associés
     */
    public function getRoomsForClient($clientId) {
        $sql = "SELECT r.id, r.name, s.name as site_name
                FROM rooms r
                JOIN sites s ON r.site_id = s.id
                WHERE s.client_id = :client_id
                ORDER BY s.name, r.name";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':client_id' => $clientId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère le contrat associé à une salle spécifique
     */
    public function getContractByRoomId($roomId) {
        $sql = "SELECT c.*, ct.name as contract_type_name
                FROM contracts c 
                LEFT JOIN contract_types ct ON c.contract_type_id = ct.id 
                JOIN contract_rooms cr ON c.id = cr.contract_id
                WHERE cr.room_id = :room_id AND c.status = 'actif'
                ORDER BY c.name
                LIMIT 1";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':room_id' => $roomId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les pièces jointes d'un contrat
     */
    public function getPiecesJointes($contractId) {
        $query = "
            SELECT 
                pj.*,
                st.setting_value as type_nom,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name
            FROM pieces_jointes pj
            LEFT JOIN settings st ON pj.type_id = st.id
            LEFT JOIN users u ON pj.created_by = u.id
            INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
            WHERE lpj.type_liaison = 'contract' 
            AND lpj.entite_id = :contract_id
            ORDER BY pj.date_creation DESC
        ";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':contract_id', $contractId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute une pièce jointe à un contrat
     * 
     * @param int $contractId ID du contrat
     * @param array $data Données de la pièce jointe
     * @return int ID de la pièce jointe créée
     */
    public function addPieceJointe($contractId, $data) {
        try {
            $this->db->beginTransaction();

            // Insérer la pièce jointe
            $query = "INSERT INTO pieces_jointes (
                        nom_fichier, chemin_fichier, type_fichier, taille_fichier, 
                        commentaire, masque_client, type_id, created_by
                    ) VALUES (
                        :nom_fichier, :chemin_fichier, :type_fichier, :taille_fichier,
                        :commentaire, :masque_client, :type_id, :created_by
                    )";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':nom_fichier' => $data['nom_fichier'],
                ':chemin_fichier' => $data['chemin_fichier'],
                ':type_fichier' => $data['type_fichier'],
                ':taille_fichier' => $data['taille_fichier'],
                ':commentaire' => $data['commentaire'] ?? null,
                ':masque_client' => $data['masque_client'] ?? 0,
                ':type_id' => $data['type_id'] ?? null,
                ':created_by' => $data['created_by'] ?? null
            ]);

            $pieceJointeId = $this->db->lastInsertId();

            // Créer la liaison
            $query = "INSERT INTO liaisons_pieces_jointes (
                        piece_jointe_id, type_liaison, entite_id
                    ) VALUES (
                        :piece_jointe_id, 'contract', :contract_id
                    )";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':piece_jointe_id' => $pieceJointeId,
                ':contract_id' => $contractId
            ]);

            $this->db->commit();
            return $pieceJointeId;
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de l'ajout de la pièce jointe : " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Supprime une pièce jointe d'un contrat
     * 
     * @param int $pieceJointeId ID de la pièce jointe
     * @param int $contractId ID du contrat (pour vérification)
     * @return bool Succès de la suppression
     */
    public function deletePieceJointe($pieceJointeId, $contractId) {
        try {
            $this->db->beginTransaction();

            // Vérifier que la pièce jointe appartient bien au contrat
            $query = "SELECT pj.* FROM pieces_jointes pj
                     INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                     WHERE lpj.type_liaison = 'contract' 
                     AND lpj.entite_id = :contract_id 
                     AND pj.id = :piece_jointe_id";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':contract_id' => $contractId,
                ':piece_jointe_id' => $pieceJointeId
            ]);

            $pieceJointe = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pieceJointe) {
                throw new Exception("Pièce jointe non trouvée ou non autorisée");
            }

            // Supprimer la liaison
            $query = "DELETE FROM liaisons_pieces_jointes 
                     WHERE piece_jointe_id = :piece_jointe_id 
                     AND type_liaison = 'contract' 
                     AND entite_id = :contract_id";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':piece_jointe_id' => $pieceJointeId,
                ':contract_id' => $contractId
            ]);

            // Supprimer la pièce jointe
            $query = "DELETE FROM pieces_jointes WHERE id = :piece_jointe_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':piece_jointe_id' => $pieceJointeId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de la suppression de la pièce jointe : " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Récupère une pièce jointe par son ID
     */
    public function getPieceJointeById($pieceJointeId) {
        $query = "SELECT pj.* FROM pieces_jointes pj
                 INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                 WHERE lpj.type_liaison = 'contract' 
                 AND pj.id = :piece_jointe_id";

        $stmt = $this->db->prepare($query);
        $stmt->execute([':piece_jointe_id' => $pieceJointeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Met à jour la visibilité d'une pièce jointe
     */
    public function updatePieceJointeVisibility($pieceJointeId, $masqueClient) {
        $query = "UPDATE pieces_jointes 
                 SET masque_client = :masque_client 
                 WHERE id = :piece_jointe_id";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':masque_client' => $masqueClient,
            ':piece_jointe_id' => $pieceJointeId
        ]);

        return true;
    }

    /**
     * Enregistre les modifications des salles d'un contrat dans l'historique
     */
    private function recordRoomChanges($contractId, $oldRooms, $newRooms) {
        // Convertir les listes de salles en tableaux pour comparaison
        $oldRoomsArray = $oldRooms === 'Aucune salle' ? [] : explode(', ', $oldRooms);
        $newRoomsArray = $newRooms === 'Aucune salle' ? [] : explode(', ', $newRooms);
        
        // Trouver les salles ajoutées et retirées
        $addedRooms = array_diff($newRoomsArray, $oldRoomsArray);
        $removedRooms = array_diff($oldRoomsArray, $newRoomsArray);
        
        // Créer la description des changements
        $changes = [];
        
        if (!empty($addedRooms)) {
            $changes[] = "Ajout : " . implode(', ', $addedRooms);
        }
        
        if (!empty($removedRooms)) {
            $changes[] = "Retrait : " . implode(', ', $removedRooms);
        }
        
        if (empty($changes)) {
            return; // Aucun changement réel
        }
        
        $description = implode(' | ', $changes);
        
        $sql = "INSERT INTO contract_history (
                    contract_id, field_name, old_value, new_value, changed_by, description
                ) VALUES (
                    :contract_id, :field_name, :old_value, :new_value, :changed_by, :description
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':contract_id' => $contractId,
            ':field_name' => 'Salles associées',
            ':old_value' => $oldRooms,
            ':new_value' => $newRooms,
            ':changed_by' => $_SESSION['user']['id'],
            ':description' => $description
        ]);
    }

    /**
     * Enregistre les modifications d'un contrat dans l'historique
     */
    public function recordChanges($contractId, $oldData, $newData) {
        $fieldsToTrack = [
            'name' => 'Nom',
            'client_id' => 'Client',
            'contract_type_id' => 'Type de contrat',
            'access_level_id' => 'Niveau d\'accès',
            'start_date' => 'Date de début',
            'end_date' => 'Date de fin',
            'tickets_number' => 'Tickets initiaux',
            'tickets_remaining' => 'Tickets restants',
            'comment' => 'Commentaire',
            'status' => 'Statut',
            'reminder_enabled' => 'Rappel activé',
            'reminder_days' => 'Jours de rappel',
            'renouvellement_tacite' => 'Renouvellement tacite',
            'num_facture' => 'Numéro de facture',
            'tarif' => 'Tarif',
            'indice' => 'Indice de révision'
        ];

        $changesCount = 0;
        
        foreach ($fieldsToTrack as $field => $label) {
            if (isset($newData[$field])) {
                $oldFieldValue = array_key_exists($field, $oldData) ? $oldData[$field] : null;
                $newFieldValue = $newData[$field];
                
                // Comparaison directe des valeurs brutes d'abord
                if ($oldFieldValue != $newFieldValue) {
                    $oldValue = $this->getDisplayValue($field, $oldFieldValue);
                    $newValue = $this->getDisplayValue($field, $newFieldValue);
                    
                    // Log pour débogage
                    error_log("DEBUG - Contract History - Field: $field, Old: $oldFieldValue, New: $newFieldValue, OldDisplay: $oldValue, NewDisplay: $newValue");
                    
                    // Construire la description avec mention spéciale pour les modifications manuelles de tickets
                    $description = "$label : $oldValue → $newValue";
                    if ($field === 'tickets_remaining' || $field === 'tickets_number') {
                        $description = "Modif manuelle - $label : $oldValue → $newValue";
                    }
                    
                    $sql = "INSERT INTO contract_history (
                                contract_id, field_name, old_value, new_value, changed_by, description
                            ) VALUES (
                                :contract_id, :field_name, :old_value, :new_value, :changed_by, :description
                            )";
                    
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([
                        ':contract_id' => $contractId,
                        ':field_name' => $label,
                        ':old_value' => $oldValue,
                        ':new_value' => $newValue,
                        ':changed_by' => $_SESSION['user']['id'],
                        ':description' => $description
                    ]);
                    
                    $changesCount++;
                }
            }
        }
        
        // Log du nombre total de changements
        error_log("DEBUG - Contract History - Total changes recorded: $changesCount for contract ID: $contractId");
    }

    /**
     * Récupère la valeur d'affichage d'un champ
     */
    private function getDisplayValue($field, $value) {
        if ($value === null || $value === '') {
            return 'Non défini';
        }

        switch ($field) {
            case 'client_id':
                if (is_numeric($value)) {
                    $sql = "SELECT name FROM clients WHERE id = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$value]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $result ? $result['name'] : 'Client inconnu';
                }
                return $value;
                
            case 'contract_type_id':
                if (is_numeric($value)) {
                    $sql = "SELECT name FROM contract_types WHERE id = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$value]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $result ? $result['name'] : 'Type inconnu';
                }
                return $value;
                
            case 'access_level_id':
                if (is_numeric($value)) {
                    $sql = "SELECT name FROM contract_access_levels WHERE id = ?";
                    $stmt = $this->db->prepare($sql);
                    $stmt->execute([$value]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    return $result ? $result['name'] : 'Niveau inconnu';
                }
                return $value;
                
            case 'start_date':
            case 'end_date':
                if (!empty($value) && $value !== '0000-00-00') {
                    return date('d/m/Y', strtotime($value));
                }
                return 'Non défini';
                
            case 'reminder_enabled':
            case 'renouvellement_tacite':
                return $value ? 'Oui' : 'Non';
                
            case 'status':
                return $value === 'actif' ? 'Actif' : 'Inactif';
                
            case 'tickets_number':
            case 'tickets_remaining':
            case 'reminder_days':
                return (string)$value;
                
            case 'num_facture':
                return !empty($value) ? $value : 'Non défini';
                
            case 'tarif':
                if (!empty($value) && is_numeric($value)) {
                    return number_format($value, 2, ',', ' ') . ' €';
                }
                return !empty($value) ? $value : 'Non défini';
                
            case 'indice':
                return !empty($value) ? $value : 'Non défini';
                
            default:
                return (string)$value;
        }
    }

    /**
     * Récupère l'historique d'un contrat
     */
    public function getContractHistory($contractId) {
        $sql = "SELECT h.*, 
                CONCAT(u.first_name, ' ', u.last_name) as changed_by_name
                FROM contract_history h
                LEFT JOIN users u ON h.changed_by = u.id
                WHERE h.contract_id = ?
                ORDER BY h.created_at DESC";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$contractId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Enregistre une déduction de tickets dans l'historique
     */
    public function recordTicketDeduction($contractId, $ticketsDeduced, $reason = 'Déduction automatique') {
        // Log de débogage
        error_log("DEBUG - recordTicketDeduction appelée avec: contractId=$contractId, ticketsDeduced=$ticketsDeduced, reason=$reason");
        
        // Vérifier que l'utilisateur est connecté
        if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
            error_log("ERROR - recordTicketDeduction: Utilisateur non connecté ou ID manquant");
            return false;
        }
        
        try {
            // Récupérer le nombre de tickets restants avant la déduction
            $sql = "SELECT tickets_remaining FROM contracts WHERE id = :contract_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':contract_id' => $contractId]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$contract) {
                error_log("ERROR - recordTicketDeduction: Contrat non trouvé ID: $contractId");
                return false;
            }
            
            $ticketsBefore = $contract['tickets_remaining'];
            $ticketsAfter = $ticketsBefore - $ticketsDeduced; // On soustrait car $ticketsDeduced est le nombre de tickets à déduire
            
            error_log("DEBUG - recordTicketDeduction: Tickets avant: $ticketsBefore, après: $ticketsAfter, déduits: $ticketsDeduced");
            
            $sql = "INSERT INTO contract_history (
                        contract_id, field_name, old_value, new_value, changed_by, description
                    ) VALUES (
                        :contract_id, :field_name, :old_value, :new_value, :changed_by, :description
                    )";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':contract_id' => $contractId,
                ':field_name' => 'Tickets restants',
                ':old_value' => $ticketsBefore,
                ':new_value' => $ticketsAfter,
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => $reason . ' : -' . $ticketsDeduced . ' tickets'
            ]);
            
            if ($result) {
                error_log("DEBUG - recordTicketDeduction: Enregistrement réussi dans contract_history");
                return true;
            } else {
                error_log("ERROR - recordTicketDeduction: Échec de l'exécution de la requête");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("ERROR - recordTicketDeduction: Exception: " . $e->getMessage());
            error_log("ERROR - recordTicketDeduction: Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Enregistre une modification de tickets dans l'historique (addition ou soustraction)
     */
    public function recordTicketModification($contractId, $ticketsDifference, $reason = 'Modification des tickets') {
        // Log de débogage
        error_log("DEBUG - recordTicketModification appelée avec: contractId=$contractId, ticketsDifference=$ticketsDifference, reason=$reason");
        
        // Vérifier que l'utilisateur est connecté
        if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
            error_log("ERROR - recordTicketModification: Utilisateur non connecté ou ID manquant");
            return false;
        }
        
        try {
            // Récupérer le nombre de tickets restants avant la modification
            $sql = "SELECT tickets_remaining FROM contracts WHERE id = :contract_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([':contract_id' => $contractId]);
            $contract = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$contract) {
                error_log("ERROR - recordTicketModification: Contrat non trouvé ID: $contractId");
                return false;
            }
            
            $ticketsBefore = $contract['tickets_remaining'];
            $ticketsAfter = $ticketsBefore - $ticketsDifference; // On soustrait car $ticketsDifference est positif pour une déduction, négatif pour une addition
            
            error_log("DEBUG - recordTicketModification: Tickets avant: $ticketsBefore, après: $ticketsAfter, différence: $ticketsDifference");
            
            // Déterminer le type de modification et le message
            if ($ticketsDifference > 0) {
                $operation = "déduction";
                $operationText = "-" . $ticketsDifference . " tickets";
            } else {
                $operation = "ajout";
                $operationText = "+" . abs($ticketsDifference) . " tickets";
            }
            
            $sql = "INSERT INTO contract_history (
                        contract_id, field_name, old_value, new_value, changed_by, description
                    ) VALUES (
                        :contract_id, :field_name, :old_value, :new_value, :changed_by, :description
                    )";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':contract_id' => $contractId,
                ':field_name' => 'Tickets restants',
                ':old_value' => $ticketsBefore,
                ':new_value' => $ticketsAfter,
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => $reason . ' : ' . $operationText
            ]);
            
            if ($result) {
                // Maintenant mettre à jour les tickets restants dans la table contracts
                $updateSql = "UPDATE contracts SET tickets_remaining = :tickets_remaining WHERE id = :contract_id";
                $updateStmt = $this->db->prepare($updateSql);
                $updateResult = $updateStmt->execute([
                    ':tickets_remaining' => $ticketsAfter,
                    ':contract_id' => $contractId
                ]);
                
                if ($updateResult) {
                    error_log("DEBUG - recordTicketModification: Mise à jour des tickets restants réussie: $ticketsBefore → $ticketsAfter");
                    return true;
                } else {
                    error_log("ERROR - recordTicketModification: Échec de la mise à jour des tickets restants");
                    return false;
                }
            } else {
                error_log("ERROR - recordTicketModification: Échec de l'exécution de la requête d'historique");
                return false;
            }
            
        } catch (Exception $e) {
            error_log("ERROR - recordTicketModification: Exception: " . $e->getMessage());
            error_log("ERROR - recordTicketModification: Stack trace: " . $e->getTraceAsString());
            return false;
        }
    }

    /**
     * Enregistre un ajout de tickets dans l'historique
     */
    public function recordTicketAddition($contractId, $ticketsAdded, $date, $comment = '', $oldNumFacture = null, $newNumFacture = null, $oldEndDate = null, $newEndDate = null) {
        // Récupérer les valeurs actuelles du contrat
        $sql = "SELECT tickets_number, tickets_remaining FROM contracts WHERE id = :contract_id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute([':contract_id' => $contractId]);
        $contract = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$contract) {
            error_log("ERROR - recordTicketAddition: Contrat non trouvé ID: $contractId");
            return false;
        }
        
        $oldTicketsNumber = $contract['tickets_number'];
        $oldTicketsRemaining = $contract['tickets_remaining'];
        $newTicketsNumber = $oldTicketsNumber + $ticketsAdded;
        $newTicketsRemaining = $oldTicketsRemaining + $ticketsAdded;
        
        // Construire les descriptions spécifiques
        $baseDescription = "Ajout de $ticketsAdded tickets";
        if (!empty($comment)) {
            $baseDescription .= " - $comment";
        }
        if (!empty($date)) {
            $baseDescription .= " (Date: " . date('d/m/Y', strtotime($date)) . ")";
        }

        $descriptionInitiaux = "Ajout de $ticketsAdded tickets initiaux";
        if (!empty($newNumFacture)) {
            $descriptionInitiaux .= " (fact: $newNumFacture)";
        }

        $descriptionRestants = "Ajout de $ticketsAdded tickets restants";
        if (!empty($newNumFacture)) {
            $descriptionRestants .= " (fact: $newNumFacture)";
        }

        // Enregistrer la modification des tickets initiaux
        $sql = "INSERT INTO contract_history (
                    contract_id, field_name, old_value, new_value, changed_by, description
                ) VALUES (
                    :contract_id, :field_name, :old_value, :new_value, :changed_by, :description
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':contract_id' => $contractId,
            ':field_name' => 'Tickets initiaux',
            ':old_value' => $oldTicketsNumber,
            ':new_value' => $newTicketsNumber,
            ':changed_by' => $_SESSION['user']['id'],
            ':description' => $descriptionInitiaux
        ]);
        
        // Enregistrer la modification des tickets restants
        $stmt->execute([
            ':contract_id' => $contractId,
            ':field_name' => 'Tickets restants',
            ':old_value' => $oldTicketsRemaining,
            ':new_value' => $newTicketsRemaining,
            ':changed_by' => $_SESSION['user']['id'],
            ':description' => $descriptionRestants
        ]);

        // Enregistrer la modification du numéro de facture si fourni
        if (!empty($newNumFacture) && $oldNumFacture !== $newNumFacture) {
            $oldValue = $this->getDisplayValue('num_facture', $oldNumFacture);
            $newValue = $this->getDisplayValue('num_facture', $newNumFacture);
            
            $factureDescription = "Numéro de facture : $oldValue → $newValue (ajout de $ticketsAdded tickets)";
            if (!empty($comment)) {
                $factureDescription .= " - $comment";
            }
            
            $stmt->execute([
                ':contract_id' => $contractId,
                ':field_name' => 'Numéro de facture',
                ':old_value' => $oldValue,
                ':new_value' => $newValue,
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => $factureDescription
            ]);
            
            error_log("DEBUG - recordTicketAddition: Modification numéro de facture enregistrée: '$oldValue' → '$newValue'");
        }

        // Enregistrer la prolongation du contrat si fournie
        if (!empty($oldEndDate) && !empty($newEndDate)) {
            $oldValue = $this->getDisplayValue('end_date', $oldEndDate);
            $newValue = $this->getDisplayValue('end_date', $newEndDate);
            
            $extensionDescription = "Date de fin : $oldValue → $newValue (prolongation lors de l'ajout de $ticketsAdded tickets)";
            if (!empty($comment)) {
                $extensionDescription .= " - $comment";
            }
            
            $stmt->execute([
                ':contract_id' => $contractId,
                ':field_name' => 'Date de fin',
                ':old_value' => $oldValue,
                ':new_value' => $newValue,
                ':changed_by' => $_SESSION['user']['id'],
                ':description' => $extensionDescription
            ]);
            
            error_log("DEBUG - recordTicketAddition: Prolongation contrat enregistrée: '$oldValue' → '$newValue'");
        }
    }

    /**
     * Enregistre un renouvellement de contrat dans l'historique
     */
    public function recordRenewal($contractId, $newContractId, $newContractName, $comment = '', $resetTickets = false) {
        $description = "Renouvellement du contrat - Nouveau contrat créé : #$newContractId ($newContractName)";
        if ($resetTickets) {
            $description .= " - Tickets réinitialisés";
        }
        if (!empty($comment)) {
            $description .= " - $comment";
        }

        $sql = "INSERT INTO contract_history (
                    contract_id, field_name, old_value, new_value, changed_by, description
                ) VALUES (
                    :contract_id, :field_name, :old_value, :new_value, :changed_by, :description
                )";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':contract_id' => $contractId,
            ':field_name' => 'Renouvellement',
            ':old_value' => 'Contrat actif',
            ':new_value' => 'Contrat renouvelé',
            ':changed_by' => $_SESSION['user']['id'],
            ':description' => $description
        ]);
    }

    /**
     * Récupère les statistiques des contrats par statut
     */
    public function getContractStatsByStatus() {
        $sql = "SELECT 
                    status,
                    COUNT(*) as count
                FROM contracts 
                WHERE contract_type_id IS NOT NULL
                GROUP BY status
                ORDER BY 
                    CASE status 
                        WHEN 'actif' THEN 1
                        WHEN 'en_attente' THEN 2
                        WHEN 'inactif' THEN 3
                        ELSE 4
                    END";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Formater les résultats avec les noms d'affichage et les couleurs
        $formattedResults = [];
        foreach ($results as $result) {
            $status = $result['status'];
            $count = $result['count'];
            
            // Définir les couleurs et noms d'affichage
            switch ($status) {
                case 'actif':
                    $color = 'bg-success'; // Vert Bootstrap
                    $displayName = 'Actifs';
                    break;
                case 'en_attente':
                    $color = 'bg-warning'; // Orange Bootstrap
                    $displayName = 'En attente';
                    break;
                case 'inactif':
                    $color = 'bg-danger'; // Rouge Bootstrap
                    $displayName = 'Inactifs';
                    break;
                default:
                    $color = 'bg-secondary'; // Gris Bootstrap
                    $displayName = ucfirst(str_replace('_', ' ', $status));
                    break;
            }
            
            $formattedResults[] = [
                'status' => $status,
                'display_name' => $displayName,
                'count' => $count,
                'color' => $color
            ];
        }
        
        return $formattedResults;
    }

    /**
     * Enregistre la création d'un contrat dans l'historique
     * 
     * @param int $contractId ID du contrat créé
     * @param array $data Données du contrat
     */
    private function recordContractCreation($contractId, $data) {
        try {
            // Vérifier que l'utilisateur est connecté
            if (!isset($_SESSION['user']) || !isset($_SESSION['user']['id'])) {
                return false;
            }

            $userId = $_SESSION['user']['id'];
            $currentDate = date('d/m/Y à H:i');
            
            // Récupérer le nom de l'utilisateur
            $userName = $this->getUserNameById($userId);

            // 1. Enregistrer la création du contrat
            $sql = "INSERT INTO contract_history (
                        contract_id, field_name, old_value, new_value, changed_by, description
                    ) VALUES (
                        :contract_id, :field_name, :old_value, :new_value, :changed_by, :description
                    )";
            
            $stmt = $this->db->prepare($sql);
            $result = $stmt->execute([
                ':contract_id' => $contractId,
                ':field_name' => 'Création du contrat',
                ':old_value' => 'Non existant',
                ':new_value' => 'Contrat créé',
                ':changed_by' => $userId,
                ':description' => "Contrat créé le $currentDate par $userName"
            ]);
            

            // 2. Si c'est un contrat à ticket, enregistrer les tickets initiaux
            if (isset($data['tickets_number']) && isContractTicketById($data['id'])) {
                $ticketsNumber = $data['tickets_number'];
                $ticketsRemaining = $data['tickets_remaining'] ?? $data['tickets_number'];
                
                $sql = "INSERT INTO contract_history (
                            contract_id, field_name, old_value, new_value, changed_by, description
                        ) VALUES (
                            :contract_id, :field_name, :old_value, :new_value, :changed_by, :description
                        )";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':contract_id' => $contractId,
                    ':field_name' => 'Nombre de tickets',
                    ':old_value' => '0',
                    ':new_value' => $ticketsNumber,
                    ':changed_by' => $userId,
                    ':description' => "Tickets initiaux définis : $ticketsNumber tickets"
                ]);

                // 3. Enregistrer aussi les tickets restants (qui sont égaux aux tickets initiaux à la création)
                $sql = "INSERT INTO contract_history (
                            contract_id, field_name, old_value, new_value, changed_by, description
                        ) VALUES (
                            :contract_id, :field_name, :old_value, :new_value, :changed_by, :description
                        )";
                
                $stmt = $this->db->prepare($sql);
                $stmt->execute([
                    ':contract_id' => $contractId,
                    ':field_name' => 'Tickets restants',
                    ':old_value' => '0',
                    ':new_value' => $ticketsRemaining,
                    ':changed_by' => $userId,
                    ':description' => "Tickets restants initialisés : $ticketsRemaining tickets"
                ]);
            }

            return true;
            
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Récupère le nom complet d'un utilisateur par son ID
     * 
     * @param int $userId ID de l'utilisateur
     * @return string Nom complet de l'utilisateur ou "Utilisateur inconnu"
     */
    private function getUserNameById($userId) {
        try {
            $sql = "SELECT CONCAT(first_name, ' ', last_name) as full_name FROM users WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['full_name'])) {
                return trim($result['full_name']);
            }
            
            // Si pas de nom complet, essayer avec le username
            $sql = "SELECT username FROM users WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && !empty($result['username'])) {
                return $result['username'];
            }
            
            return "Utilisateur inconnu";
            
        } catch (Exception $e) {
            return "Utilisateur inconnu";
        }
    }

    /**
     * Récupère tous les contrats hors contrat facturable
     * 
     * @param array $filters Filtres à appliquer
     * @return array Liste des contrats hors contrat facturable
     */
    public function getHorsContratFacturableContracts($filters = []) {
        $sql = "SELECT DISTINCT
                    c.*,
                    cl.name as client_name,
                    COUNT(DISTINCT i.id) as interventions_count,
                    SUM(CASE WHEN i.status_id = (SELECT id FROM intervention_statuses WHERE name = 'Fermé') THEN 1 ELSE 0 END) as closed_interventions_count,
                    COUNT(DISTINCT CASE WHEN pj.masque_client = 0 THEN pj.id END) as attachments_count
                FROM contracts c
                LEFT JOIN clients cl ON c.client_id = cl.id
                LEFT JOIN contract_rooms cr ON c.id = cr.contract_id
                LEFT JOIN rooms r ON cr.room_id = r.id
                LEFT JOIN sites s ON r.site_id = s.id
                LEFT JOIN interventions i ON c.id = i.contract_id
                LEFT JOIN liaisons_pieces_jointes lpj ON c.id = lpj.entite_id AND lpj.type_liaison = 'contract'
                LEFT JOIN pieces_jointes pj ON lpj.piece_jointe_id = pj.id
                WHERE c.contract_type_id IS NULL 
                AND c.name LIKE '%hors contrat facturable%'
                AND c.id IS NOT NULL";
        
        $params = [];

        // Appliquer les filtres
        if (!empty($filters['client_id'])) {
            $sql .= " AND c.client_id = :client_id";
            $params[':client_id'] = $filters['client_id'];
        }

        if (!empty($filters['site_id'])) {
            $sql .= " AND s.id = :site_id";
            $params[':site_id'] = $filters['site_id'];
        }

        if (!empty($filters['room_id'])) {
            $sql .= " AND r.id = :room_id";
            $params[':room_id'] = $filters['room_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND c.status = :status";
            $params[':status'] = $filters['status'];
        }

        // Filtre par type de tickets
        if (!empty($filters['ticket_type'])) {
            if ($filters['ticket_type'] === 'with_tickets') {
                $sql .= " AND c.isticketcontract = 1";
            } elseif ($filters['ticket_type'] === 'without_tickets') {
                $sql .= " AND c.isticketcontract = 0";
            }
        }

        $sql .= " GROUP BY c.id ORDER BY c.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pour chaque contrat, récupérer les salles associées
        foreach ($contracts as &$contract) {
            $contract['rooms'] = $this->getContractRooms($contract['id']);
        }

        return $contracts;
    }

    /**
     * Récupère tous les contrats hors contrat non facturable
     * 
     * @param array $filters Filtres à appliquer
     * @return array Liste des contrats hors contrat non facturable
     */
    public function getHorsContratNonFacturableContracts($filters = []) {
        $sql = "SELECT DISTINCT
                    c.*,
                    cl.name as client_name,
                    COUNT(DISTINCT i.id) as interventions_count,
                    SUM(CASE WHEN i.status_id = (SELECT id FROM intervention_statuses WHERE name = 'Fermé') THEN 1 ELSE 0 END) as closed_interventions_count,
                    COUNT(DISTINCT CASE WHEN pj.masque_client = 0 THEN pj.id END) as attachments_count
                FROM contracts c
                LEFT JOIN clients cl ON c.client_id = cl.id
                LEFT JOIN contract_rooms cr ON c.id = cr.contract_id
                LEFT JOIN rooms r ON cr.room_id = r.id
                LEFT JOIN sites s ON r.site_id = s.id
                LEFT JOIN interventions i ON c.id = i.contract_id
                LEFT JOIN liaisons_pieces_jointes lpj ON c.id = lpj.entite_id AND lpj.type_liaison = 'contract'
                LEFT JOIN pieces_jointes pj ON lpj.piece_jointe_id = pj.id
                WHERE c.contract_type_id IS NULL 
                AND c.name LIKE '%hors contrat non facturable%'
                AND c.id IS NOT NULL";
        
        $params = [];

        // Appliquer les filtres
        if (!empty($filters['client_id'])) {
            $sql .= " AND c.client_id = :client_id";
            $params[':client_id'] = $filters['client_id'];
        }

        if (!empty($filters['site_id'])) {
            $sql .= " AND s.id = :site_id";
            $params[':site_id'] = $filters['site_id'];
        }

        if (!empty($filters['room_id'])) {
            $sql .= " AND r.id = :room_id";
            $params[':room_id'] = $filters['room_id'];
        }

        if (!empty($filters['status'])) {
            $sql .= " AND c.status = :status";
            $params[':status'] = $filters['status'];
        }

        // Filtre par type de tickets
        if (!empty($filters['ticket_type'])) {
            if ($filters['ticket_type'] === 'with_tickets') {
                $sql .= " AND c.isticketcontract = 1";
            } elseif ($filters['ticket_type'] === 'without_tickets') {
                $sql .= " AND c.isticketcontract = 0";
            }
        }

        $sql .= " GROUP BY c.id ORDER BY c.name";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $contracts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pour chaque contrat, récupérer les salles associées
        foreach ($contracts as &$contract) {
            $contract['rooms'] = $this->getContractRooms($contract['id']);
        }

        return $contracts;
    }

    /**
     * Récupère les statistiques des contrats hors contrat facturable
     * 
     * @return array Statistiques
     */
    public function getHorsContratFacturableStats() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'actif' THEN 1 ELSE 0 END) as actifs,
                    SUM(CASE WHEN status = 'inactif' THEN 1 ELSE 0 END) as inactifs,
                    SUM(CASE WHEN status = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
                    SUM(CASE WHEN isticketcontract = 1 THEN 1 ELSE 0 END) as avec_tickets,
                    SUM(CASE WHEN isticketcontract = 0 THEN 1 ELSE 0 END) as sans_tickets
                FROM contracts 
                WHERE contract_type_id IS NULL 
                AND name LIKE '%hors contrat facturable%'
                AND id IS NOT NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère les statistiques des contrats hors contrat non facturable
     * 
     * @return array Statistiques
     */
    public function getHorsContratNonFacturableStats() {
        $sql = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'actif' THEN 1 ELSE 0 END) as actifs,
                    SUM(CASE WHEN status = 'inactif' THEN 1 ELSE 0 END) as inactifs,
                    SUM(CASE WHEN status = 'en_attente' THEN 1 ELSE 0 END) as en_attente,
                    SUM(CASE WHEN isticketcontract = 1 THEN 1 ELSE 0 END) as avec_tickets,
                    SUM(CASE WHEN isticketcontract = 0 THEN 1 ELSE 0 END) as sans_tickets
                FROM contracts 
                WHERE contract_type_id IS NULL 
                AND name LIKE '%hors contrat non facturable%'
                AND id IS NOT NULL";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
} 
<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

class ClientModel extends BaseModel {
    public function __construct($db) {
        parent::__construct($db);
        $this->table = 'clients';
    }

    public function getAllClientsWithStats($filters = []) {
        $where = [];
        $params = [];

        // Debug des filtres reçus
        error_log("Filtres reçus dans le modèle : " . print_r($filters, true));

        // Ajout des filtres
        if (isset($filters['search']) && $filters['search'] !== '') {
            $where[] = "(c.name LIKE :search_name OR c.city LIKE :search_city OR c.email LIKE :search_email OR c.phone LIKE :search_phone)";
            $params[':search_name'] = '%' . $filters['search'] . '%';
            $params[':search_city'] = '%' . $filters['search'] . '%';
            $params[':search_email'] = '%' . $filters['search'] . '%';
            $params[':search_phone'] = '%' . $filters['search'] . '%';
        }

        if (!empty($filters['city'])) {
            $where[] = "c.city = :city";
            $params[':city'] = $filters['city'];
        }

        // Filtre sur le statut (actif/inactif)
        if (isset($filters['status']) && $filters['status'] !== '') {
            $where[] = "c.status = :status";
            $params[':status'] = (int)$filters['status'];
        }

        $query = "SELECT 
                    c.id,
                    c.name,
                    c.city,
                    c.email,
                    c.phone,
                    c.status,
                    COUNT(DISTINCT s.id) as site_count,
                    COUNT(DISTINCT r.id) as room_count,
                    COUNT(DISTINCT co.id) as contract_count,
                    COALESCE((
                        SELECT SUM(tickets_remaining) 
                        FROM contracts 
                        WHERE client_id = c.id 
                        AND status = 'actif' 
                        AND contract_type_id IS NOT NULL
                    ), 0) as total_tickets_remaining
                FROM clients c
                LEFT JOIN sites s ON c.id = s.client_id AND s.status = 1
                LEFT JOIN rooms r ON s.id = r.site_id AND r.status = 1
                LEFT JOIN contracts co ON c.id = co.client_id AND co.status = 'actif' AND co.contract_type_id IS NOT NULL";

        // Ajouter la clause WHERE seulement s'il y a des conditions
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }

        $query .= " GROUP BY c.id, c.name, c.city, c.email, c.phone, c.status
                ORDER BY c.name";

        // Debug de la requête et des paramètres
        error_log("Requête SQL : " . $query);
        error_log("Paramètres : " . print_r($params, true));

        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        error_log("Nombre de résultats : " . count($results));
        
        return $results;
    }

    public function getClientById($id) {
        return $this->find($id);
    }

    public function updateClient($id, $data) {
        $query = "UPDATE clients SET 
                    name = :name,
                    address = :address,
                    postal_code = :postal_code,
                    city = :city,
                    phone = :phone,
                    email = :email,
                    website = :website,
                    comment = :comment,
                    status = :status,
                    updated_at = NOW()
                WHERE id = :id";
        
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->bindParam(':name', $data['name']);
        $stmt->bindParam(':address', $data['address']);
        $stmt->bindParam(':postal_code', $data['postal_code']);
        $stmt->bindParam(':city', $data['city']);
        $stmt->bindParam(':phone', $data['phone']);
        $stmt->bindParam(':email', $data['email']);
        $stmt->bindParam(':website', $data['website']);
        $stmt->bindParam(':comment', $data['comment']);
        $stmt->bindParam(':status', $data['status'], PDO::PARAM_INT);
        
        return $stmt->execute();
    }

    /**
     * Crée un nouveau client
     * 
     * @param array $data Les données du client
     * @return int L'ID du client créé
     */
    public function createClient($data) {
        $query = "INSERT INTO clients (
                    name, address, postal_code, city, 
                    phone, email, website, comment, status
                ) VALUES (
                    :name, :address, :postal_code, :city,
                    :phone, :email, :website, :comment, :status
                )";

        $params = [
            ':name' => $data['name'],
            ':address' => $data['address'],
            ':postal_code' => $data['postal_code'],
            ':city' => $data['city'],
            ':phone' => $data['phone'],
            ':email' => $data['email'],
            ':website' => $data['website'],
            ':comment' => $data['comment'],
            ':status' => $data['status']
        ];

        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            
            $clientId = $this->db->lastInsertId();
            
            $this->db->commit();
            return $clientId;
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de la création du client : " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    public function getAllClients() {
        $query = "SELECT id, name, address, postal_code, city, phone, email, website, comment, status, created_at, updated_at FROM clients ORDER BY name";
        $stmt = $this->db->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Supprime un client et toutes ses données associées
     * 
     * @param int $id ID du client à supprimer
     * @return bool True si la suppression a réussi, false sinon
     */
    public function deleteClient($id) {
        try {
            $this->db->beginTransaction();
            
            // Vérifier si le client existe
            $client = $this->getClientById($id);
            if (!$client) {
                throw new Exception("Client non trouvé");
            }
            
            // Supprimer les interventions liées au client
            $query = "DELETE FROM interventions WHERE client_id = :client_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':client_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer les commentaires d'intervention liés au client
            $query = "DELETE ic FROM intervention_comments ic 
                     INNER JOIN interventions i ON ic.intervention_id = i.id 
                     WHERE i.client_id = :client_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':client_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer les pièces jointes d'intervention liées au client
            $query = "DELETE pj FROM pieces_jointes pj 
                     INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id 
                     INNER JOIN interventions i ON lpj.entite_id = i.id 
                     WHERE lpj.type_liaison = 'intervention' AND i.client_id = :client_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':client_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer les liaisons de pièces jointes d'intervention
            $query = "DELETE lpj FROM liaisons_pieces_jointes lpj 
                     INNER JOIN interventions i ON lpj.entite_id = i.id 
                     WHERE lpj.type_liaison = 'intervention' AND i.client_id = :client_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':client_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer les salles associées aux sites du client
            $query = "DELETE r FROM rooms r 
                     INNER JOIN sites s ON r.site_id = s.id 
                     WHERE s.client_id = :client_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':client_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer les sites du client
            $query = "DELETE FROM sites WHERE client_id = :client_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':client_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer les contacts du client
            $query = "DELETE FROM contacts WHERE client_id = :client_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':client_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer les contrats du client
            $query = "DELETE FROM contracts WHERE client_id = :client_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':client_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer les associations contract_rooms pour ce client
            $query = "DELETE cr FROM contract_rooms cr 
                     INNER JOIN contracts c ON cr.contract_id = c.id 
                     WHERE c.client_id = :client_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':client_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer le matériel associé aux salles du client
            $query = "DELETE m FROM materiel m 
                     INNER JOIN rooms r ON m.salle_id = r.id 
                     INNER JOIN sites s ON r.site_id = s.id 
                     WHERE s.client_id = :client_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':client_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer la documentation liée au client
            $query = "DELETE FROM documentation WHERE client_id = :client_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':client_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer les pièces jointes de documentation liées au client
            $query = "DELETE pj FROM pieces_jointes pj 
                     INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id 
                     WHERE lpj.type_liaison = 'documentation' AND lpj.entite_id IN (
                         SELECT id FROM documentation WHERE client_id = :client_id
                     )";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':client_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Supprimer les liaisons de pièces jointes de documentation
            $query = "DELETE lpj FROM liaisons_pieces_jointes lpj 
                     WHERE lpj.type_liaison = 'documentation' AND lpj.entite_id IN (
                         SELECT id FROM documentation WHERE client_id = :client_id
                     )";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':client_id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            // Enfin, supprimer le client lui-même
            $query = "DELETE FROM clients WHERE id = :id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':id', $id, PDO::PARAM_INT);
            $stmt->execute();
            
            $this->db->commit();
            custom_log("Client ID $id supprimé avec succès", 'INFO');
            return true;
            
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de la suppression du client ID $id : " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }
} 
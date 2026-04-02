<?php
require_once __DIR__ . '/../classes/Models/BaseModel.php';

class MaterielModel extends BaseModel
{
    public function __construct($db)
    {
        parent::__construct($db);
        $this->table = 'materiel';
    }

    /**
     * Récupère tous les matériels avec filtres
     * 
     * @param array $filters Les filtres à appliquer
     * @return array Liste des matériels
     */
    public function getAllMateriel($filters = [])
    {
        $where = [];
        $params = [];

        // Filtre par client
        if (!empty($filters['client_id'])) {
            $where[] = "s.client_id = :client_id";
            $params[':client_id'] = $filters['client_id'];
        }

        // Filtre par site
        if (!empty($filters['site_id'])) {
            $where[] = "r.site_id = :site_id";
            $params[':site_id'] = $filters['site_id'];
        }

        // Filtre par salle
        if (!empty($filters['salle_id'])) {
            $where[] = "m.salle_id = :salle_id";
            $params[':salle_id'] = $filters['salle_id'];
        }

        $query = "
            SELECT 
                m.*,
                r.name as salle_nom,
                s.name as site_nom,
                c.name as client_nom,
                m.type_materiel as type_nom
            FROM materiel m
            LEFT JOIN rooms r ON m.salle_id = r.id
            LEFT JOIN sites s ON r.site_id = s.id
            LEFT JOIN clients c ON s.client_id = c.id
        ";

        // Ajouter la clause WHERE seulement s'il y a des conditions
        if (!empty($where)) {
            $query .= " WHERE " . implode(" AND ", $where);
        }

        $query .= " ORDER BY c.name, s.name, r.name, m.marque, m.modele";

        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Récupère un matériel par son ID
     * 
     * @param int $id ID du matériel
     * @return array|null Données du matériel
     */
    public function getMaterielById($id)
    {
        $query = "
            SELECT 
                m.*,
                r.name as salle_nom,
                s.name as site_nom,
                s.id as site_id,
                c.name as client_nom,
                c.id as client_id,
                m.type_materiel as type_nom
            FROM materiel m
            LEFT JOIN rooms r ON m.salle_id = r.id
            LEFT JOIN sites s ON r.site_id = s.id
            LEFT JOIN clients c ON s.client_id = c.id
            WHERE m.id = :id
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Crée un nouveau matériel
     * 
     * @param array $data Les données du matériel
     * @return int L'ID du matériel créé
     */
    public function createMateriel($data)
    {
        $query = "INSERT INTO materiel (
                    salle_id, type_materiel, modele, marque, reference, usage_materiel, numero_serie, 
                    version_firmware, ancien_firmware, adresse_mac, adresse_ip, masque, 
                    passerelle, id_materiel, login, password, ip_primaire, mac_primaire,
                    ip_secondaire, mac_secondaire, stream_aes67_recu, stream_aes67_transmis,
                    ssid, type_cryptage, password_wifi, libelle_pa_salle, numero_port_switch, vlan,
                    date_fin_maintenance, date_fin_garantie, date_derniere_inter, commentaire, url_github
                ) VALUES (
                    :salle_id, :type_materiel, :modele, :marque, :reference, :usage_materiel, :numero_serie,
                    :version_firmware, :ancien_firmware, :adresse_mac, :adresse_ip, :masque,
                    :passerelle, :id_materiel, :login, :password, :ip_primaire, :mac_primaire,
                    :ip_secondaire, :mac_secondaire, :stream_aes67_recu, :stream_aes67_transmis,
                    :ssid, :type_cryptage, :password_wifi, :libelle_pa_salle, :numero_port_switch, :vlan,
                    :date_fin_maintenance, :date_fin_garantie, :date_derniere_inter, :commentaire, :url_github
                )";

        $params = [
            ':salle_id' => $data['salle_id'] ?? null,
            ':type_materiel' => $data['type_materiel'] ?? null,
            ':modele' => $data['modele'] ?? null,
            ':marque' => $data['marque'] ?? null,
            ':reference' => $data['reference'] ?? null,
            ':usage_materiel' => $data['usage_materiel'] ?? null,
            ':numero_serie' => $data['numero_serie'] ?? null,
            ':version_firmware' => $data['version_firmware'] ?? null,
            ':ancien_firmware' => $data['ancien_firmware'] ?? null,
            ':adresse_mac' => $data['adresse_mac'] ?? null,
            ':adresse_ip' => $data['adresse_ip'] ?? null,
            ':masque' => $data['masque'] ?? null,
            ':passerelle' => $data['passerelle'] ?? null,
            ':id_materiel' => $data['id_materiel'] ?? null,
            ':login' => $data['login'] ?? null,
            ':password' => $data['password'] ?? null,
            ':ip_primaire' => $data['ip_primaire'] ?? null,
            ':mac_primaire' => $data['mac_primaire'] ?? null,
            ':ip_secondaire' => $data['ip_secondaire'] ?? null,
            ':mac_secondaire' => $data['mac_secondaire'] ?? null,
            ':stream_aes67_recu' => $data['stream_aes67_recu'] ?? null,
            ':stream_aes67_transmis' => $data['stream_aes67_transmis'] ?? null,
            ':ssid' => $data['ssid'] ?? null,
            ':type_cryptage' => $data['type_cryptage'] ?? null,
            ':password_wifi' => $data['password_wifi'] ?? null,
            ':libelle_pa_salle' => $data['libelle_pa_salle'] ?? null,
            ':numero_port_switch' => $data['numero_port_switch'] ?? null,
            ':vlan' => $data['vlan'] ?? null,
            ':date_fin_maintenance' => $data['date_fin_maintenance'] ?? null,
            ':date_fin_garantie' => $data['date_fin_garantie'] ?? null,
            ':date_derniere_inter' => $data['date_derniere_inter'] ?? null,
            ':commentaire' => $data['commentaire'] ?? null,
            ':url_github' => $data['url_github'] ?? null
        ];

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);

            $materielId = $this->db->lastInsertId();

            $this->db->commit();
            return $materielId;
        } catch (Exception $e) {
            $this->db->rollBack();

            throw $e;
        }
    }

    /**
     * Met à jour un matériel
     * 
     * @param int $id ID du matériel
     * @param array $data Les nouvelles données
     * @return bool Succès de la mise à jour
     */
    public function updateMateriel($id, $data)
    {
        $query = "UPDATE materiel SET 
                    salle_id = :salle_id,
                    type_materiel = :type_materiel,
                    modele = :modele,
                    marque = :marque,
                    reference = :reference,
                    usage_materiel = :usage_materiel,
                    numero_serie = :numero_serie,
                    version_firmware = :version_firmware,
                    ancien_firmware = :ancien_firmware,
                    adresse_mac = :adresse_mac,
                    adresse_ip = :adresse_ip,
                    masque = :masque,
                    passerelle = :passerelle,
                    id_materiel = :id_materiel,
                    login = :login,
                    password = :password,
                    ip_primaire = :ip_primaire,
                    mac_primaire = :mac_primaire,
                    ip_secondaire = :ip_secondaire,
                    mac_secondaire = :mac_secondaire,
                    stream_aes67_recu = :stream_aes67_recu,
                    stream_aes67_transmis = :stream_aes67_transmis,
                    ssid = :ssid,
                    type_cryptage = :type_cryptage,
                    password_wifi = :password_wifi,
                    libelle_pa_salle = :libelle_pa_salle,
                    numero_port_switch = :numero_port_switch,
                    vlan = :vlan,
                    date_fin_maintenance = :date_fin_maintenance,
                    date_fin_garantie = :date_fin_garantie,
                    date_derniere_inter = :date_derniere_inter,
                    commentaire = :commentaire,
                    url_github = :url_github,
                    updated_at = NOW()
                WHERE id = :id";

        $params = [
            ':id' => $id,
            ':salle_id' => $data['salle_id'] ?? null,
            ':type_materiel' => $data['type_materiel'] ?? null,
            ':modele' => $data['modele'] ?? null,
            ':marque' => $data['marque'] ?? null,
            ':reference' => $data['reference'] ?? null,
            ':usage_materiel' => $data['usage_materiel'] ?? null,
            ':numero_serie' => $data['numero_serie'] ?? null,
            ':version_firmware' => $data['version_firmware'] ?? null,
            ':ancien_firmware' => $data['ancien_firmware'] ?? null,
            ':adresse_mac' => $data['adresse_mac'] ?? null,
            ':adresse_ip' => $data['adresse_ip'] ?? null,
            ':masque' => $data['masque'] ?? null,
            ':passerelle' => $data['passerelle'] ?? null,
            ':id_materiel' => $data['id_materiel'] ?? null,
            ':login' => $data['login'] ?? null,
            ':password' => $data['password'] ?? null,
            ':ip_primaire' => $data['ip_primaire'] ?? null,
            ':mac_primaire' => $data['mac_primaire'] ?? null,
            ':ip_secondaire' => $data['ip_secondaire'] ?? null,
            ':mac_secondaire' => $data['mac_secondaire'] ?? null,
            ':stream_aes67_recu' => $data['stream_aes67_recu'] ?? null,
            ':stream_aes67_transmis' => $data['stream_aes67_transmis'] ?? null,
            ':ssid' => $data['ssid'] ?? null,
            ':type_cryptage' => $data['type_cryptage'] ?? null,
            ':password_wifi' => $data['password_wifi'] ?? null,
            ':libelle_pa_salle' => $data['libelle_pa_salle'] ?? null,
            ':numero_port_switch' => $data['numero_port_switch'] ?? null,
            ':vlan' => $data['vlan'] ?? null,
            ':date_fin_maintenance' => $data['date_fin_maintenance'] ?? null,
            ':date_fin_garantie' => $data['date_fin_garantie'] ?? null,
            ':date_derniere_inter' => $data['date_derniere_inter'] ?? null,
            ':commentaire' => $data['commentaire'] ?? null,
            ':url_github' => $data['url_github'] ?? null
        ];

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Met à jour partiellement un matériel (seulement les champs fournis)
     * 
     * @param int $id ID du matériel
     * @param array $data Données à mettre à jour (seulement les champs fournis)
     * @return bool Succès de la mise à jour
     */
    public function updateMaterielPartial($id, $data)
    {
        if (empty($data)) {
            return true; // Rien à mettre à jour
        }

        // Construire dynamiquement la requête avec seulement les champs fournis
        $setParts = [];
        $params = [':id' => $id];

        $allowedFields = [
            'salle_id',
            'type_materiel',
            'modele',
            'marque',
            'reference',
            'usage_materiel',
            'numero_serie',
            'version_firmware',
            'ancien_firmware',
            'adresse_mac',
            'adresse_ip',
            'masque',
            'passerelle',
            'id_materiel',
            'login',
            'password',
            'ip_primaire',
            'mac_primaire',
            'ip_secondaire',
            'mac_secondaire',
            'stream_aes67_recu',
            'stream_aes67_transmis',
            'ssid',
            'type_cryptage',
            'password_wifi',
            'libelle_pa_salle',
            'numero_port_switch',
            'vlan',
            'date_fin_maintenance',
            'date_fin_garantie',
            'date_derniere_inter',
            'commentaire',
            'url_github'
        ];

        foreach ($data as $field => $value) {
            if (in_array($field, $allowedFields)) {
                $setParts[] = "$field = :$field";
                $params[":$field"] = $value;
            }
        }

        if (empty($setParts)) {
            return true; // Aucun champ valide à mettre à jour
        }

        $setParts[] = "updated_at = NOW()";
        $query = "UPDATE materiel SET " . implode(', ', $setParts) . " WHERE id = :id";

        $stmt = $this->db->prepare($query);
        return $stmt->execute($params);
    }

    /**
     * Supprime un matériel
     * 
     * @param int $id ID du matériel
     * @return bool Succès de la suppression
     */
    public function deleteMateriel($id)
    {
        return parent::delete($id);
    }

    /**
     * Supprime plusieurs matériels en masse
     * 
     * @param array $ids Liste des IDs des matériels à supprimer
     * @return array Résultat de la suppression avec détails
     */
    public function deleteMaterielBulk($ids)
    {
        custom_log("Début de la suppression en masse avec IDs: " . implode(', ', $ids), 'INFO');

        if (empty($ids) || !is_array($ids)) {
            custom_log("Aucun ID fourni pour la suppression en masse", 'ERROR');
            return [
                'success' => false,
                'message' => 'Aucun ID de matériel fourni',
                'deleted_count' => 0,
                'errors' => []
            ];
        }

        // Nettoyer et valider les IDs
        $validIds = array_filter(array_map('intval', $ids), function ($id) {
            return $id > 0;
        });

        custom_log("IDs validés: " . implode(', ', $validIds), 'INFO');

        if (empty($validIds)) {
            custom_log("Aucun ID valide après nettoyage", 'ERROR');
            return [
                'success' => false,
                'message' => 'Aucun ID de matériel valide fourni',
                'deleted_count' => 0,
                'errors' => []
            ];
        }

        $this->db->beginTransaction();

        try {
            $deletedCount = 0;
            $errors = [];

            foreach ($validIds as $id) {
                try {
                    custom_log("Traitement de la suppression du matériel ID: $id", 'INFO');

                    // Vérifier que le matériel existe
                    $checkQuery = "SELECT id FROM materiel WHERE id = :id";
                    $checkStmt = $this->db->prepare($checkQuery);
                    $checkStmt->bindParam(':id', $id, PDO::PARAM_INT);
                    $checkStmt->execute();

                    if (!$checkStmt->fetch()) {
                        $errorMsg = "Matériel ID $id non trouvé";
                        $errors[] = $errorMsg;
                        custom_log($errorMsg, 'WARNING');
                        continue;
                    }

                    custom_log("Matériel ID $id trouvé, suppression des pièces jointes...", 'INFO');

                    // Supprimer les pièces jointes associées
                    $this->deleteMaterielAttachments($id);

                    custom_log("Suppression du matériel ID $id de la base de données...", 'INFO');

                    // Supprimer le matériel
                    $deleteQuery = "DELETE FROM materiel WHERE id = :id";
                    $deleteStmt = $this->db->prepare($deleteQuery);
                    $deleteStmt->bindParam(':id', $id, PDO::PARAM_INT);

                    if ($deleteStmt->execute()) {
                        $deletedCount++;
                        custom_log("Matériel ID $id supprimé avec succès", 'INFO');
                    } else {
                        $errorMsg = "Erreur lors de la suppression du matériel ID $id";
                        $errors[] = $errorMsg;
                        custom_log($errorMsg, 'ERROR');
                    }

                } catch (Exception $e) {
                    $errorMsg = "Erreur lors de la suppression du matériel ID $id: " . $e->getMessage();
                    $errors[] = $errorMsg;
                    custom_log($errorMsg, 'ERROR');
                }
            }

            if ($deletedCount > 0) {
                $this->db->commit();
                $message = "$deletedCount matériel(s) supprimé(s) avec succès";
                custom_log($message, 'INFO');
                return [
                    'success' => true,
                    'message' => $message,
                    'deleted_count' => $deletedCount,
                    'errors' => $errors
                ];
            } else {
                $this->db->rollBack();
                $message = 'Aucun matériel n\'a pu être supprimé';
                custom_log($message, 'ERROR');
                return [
                    'success' => false,
                    'message' => $message,
                    'deleted_count' => 0,
                    'errors' => $errors
                ];
            }

        } catch (Exception $e) {
            $this->db->rollBack();
            $errorMsg = "Erreur lors de la suppression en masse: " . $e->getMessage();
            custom_log($errorMsg, 'ERROR');
            return [
                'success' => false,
                'message' => $errorMsg,
                'deleted_count' => 0,
                'errors' => [$e->getMessage()]
            ];
        }
    }

    /**
     * Supprime toutes les pièces jointes d'un matériel
     * 
     * @param int $materielId ID du matériel
     * @return bool Succès de la suppression
     */
    private function deleteMaterielAttachments($materielId)
    {
        try {
            // Récupérer les pièces jointes du matériel
            $query = "SELECT pj.* FROM pieces_jointes pj
                     INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                     WHERE lpj.type_liaison = 'materiel' 
                     AND lpj.entite_id = :materiel_id";

            $stmt = $this->db->prepare($query);
            $stmt->execute([':materiel_id' => $materielId]);
            $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);

            custom_log("Suppression des pièces jointes pour le matériel $materielId: " . count($attachments) . " fichier(s) trouvé(s)", 'INFO');

            // Supprimer les fichiers physiques et les enregistrements
            foreach ($attachments as $attachment) {
                // Supprimer le fichier physique
                $filePath = __DIR__ . '/../../' . $attachment['chemin_fichier'];
                custom_log("Tentative de suppression du fichier: $filePath", 'INFO');

                if (file_exists($filePath)) {
                    if (unlink($filePath)) {
                        custom_log("Fichier supprimé avec succès: $filePath", 'INFO');
                    } else {
                        custom_log("Erreur lors de la suppression du fichier: $filePath", 'ERROR');
                    }
                } else {
                    custom_log("Fichier non trouvé (déjà supprimé?): $filePath", 'WARNING');
                }

                // Supprimer la liaison
                $deleteLiaisonQuery = "DELETE FROM liaisons_pieces_jointes 
                                     WHERE piece_jointe_id = :piece_jointe_id 
                                     AND type_liaison = 'materiel' 
                                     AND entite_id = :materiel_id";
                $deleteLiaisonStmt = $this->db->prepare($deleteLiaisonQuery);
                $deleteLiaisonStmt->execute([
                    ':piece_jointe_id' => $attachment['id'],
                    ':materiel_id' => $materielId
                ]);

                // Supprimer la pièce jointe
                $deletePjQuery = "DELETE FROM pieces_jointes WHERE id = :piece_jointe_id";
                $deletePjStmt = $this->db->prepare($deletePjQuery);
                $deletePjStmt->execute([':piece_jointe_id' => $attachment['id']]);

                custom_log("Pièce jointe ID {$attachment['id']} supprimée de la base de données", 'INFO');
            }

            return true;
        } catch (Exception $e) {
            custom_log("Erreur lors de la suppression des pièces jointes du matériel $materielId: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Récupère les statistiques du matériel
     * 
     * @return array Statistiques
     */
    public function getStats()
    {
        $stats = [];

        // Total matériel
        $query = "SELECT COUNT(*) as total FROM materiel";
        $stmt = $this->db->query($query);
        $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Matériel avec maintenance expirée
        $query = "SELECT COUNT(*) as maintenance_expired FROM materiel 
                 WHERE date_fin_maintenance IS NOT NULL AND date_fin_maintenance < CURDATE()";
        $stmt = $this->db->query($query);
        $stats['maintenance_expired'] = $stmt->fetch(PDO::FETCH_ASSOC)['maintenance_expired'];

        // Matériel avec certificat expiré
        $query = "SELECT COUNT(*) as certificat_expired FROM materiel 
                 WHERE date_fin_garantie IS NOT NULL AND date_fin_garantie < CURDATE()";
        $stmt = $this->db->query($query);
        $stats['certificat_expired'] = $stmt->fetch(PDO::FETCH_ASSOC)['certificat_expired'];

        // Matériel en ligne (avec IP)
        $query = "SELECT COUNT(*) as online FROM materiel WHERE adresse_ip IS NOT NULL AND adresse_ip != ''";
        $stmt = $this->db->query($query);
        $stats['online'] = $stmt->fetch(PDO::FETCH_ASSOC)['online'];

        return $stats;
    }

    /**
     * Récupère la liste des champs matériel avec leurs visibilités
     * 
     * @param int $materielId ID du matériel (optionnel, pour édition)
     * @param int $contractId ID du contrat (optionnel, pour nouveau matériel)
     * @return array Liste des champs avec visibilité
     */
    public function getChampsVisibilite($materielId = null, $contractId = null)
    {
        $champs = [
            'type_materiel' => 'Type de matériel',
            'marque' => 'Marque',
            'modele' => 'Modèle',
            'reference' => 'Référence',
            'usage_materiel' => 'Usage',
            'numero_serie' => 'Numéro de série',
            'version_firmware' => 'Version firmware',
            'ancien_firmware' => 'Ancien firmware',
            'adresse_mac' => 'Adresse MAC',
            'adresse_ip' => 'Adresse IP',
            'masque' => 'Masque réseau',
            'passerelle' => 'Passerelle',
            'id_materiel_tech' => 'ID Matériel Tech',
            'login' => 'Login',
            'password' => 'Mot de passe',
            'ip_primaire' => 'IP Primaire',
            'mac_primaire' => 'MAC Primaire',
            'ip_secondaire' => 'IP Secondaire',
            'mac_secondaire' => 'MAC Secondaire',
            'stream_aes67_recu' => 'Stream AES67 Reçu',
            'stream_aes67_transmis' => 'Stream AES67 Transmis',
            'ssid' => 'SSID',
            'type_cryptage' => 'Type de cryptage',
            'password_wifi' => 'Password WiFi',
            'libelle_pa_salle' => 'Libellé PA Salle',
            'numero_port_switch' => 'N° Port Switch',
            'vlan' => 'VLAN',
            'date_fin_maintenance' => 'Date fin maintenance',
            'date_fin_garantie' => 'Date fin garantie',
            'date_derniere_inter' => 'Date dernière intervention',
            'commentaire' => 'Commentaire',
            'url_github' => 'URL GitHub'
        ];

        // Si on a un ID de matériel, récupérer les visibilités existantes
        if ($materielId) {
            $query = "SELECT nom_champ FROM visibilite_champs_materiel WHERE materiel_id = :materiel_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':materiel_id', $materielId, PDO::PARAM_INT);
            $stmt->execute();
            $visibilites = $stmt->fetchAll(PDO::FETCH_COLUMN);

            // Associer les visibilités aux champs (présent = visible, absent = masqué)
            foreach ($champs as $nom_champ => $label) {
                $champs[$nom_champ] = [
                    'label' => $label,
                    'visible_client' => in_array($nom_champ, $visibilites)
                ];
            }
        } else {
            // Pour un nouveau matériel, utiliser les règles du niveau d'accès du contrat
            if ($contractId) {
                $accessLevelModel = new AccessLevelModel($this->db);
                $contractAccessLevel = $accessLevelModel->getContractAccessLevel($contractId);



                if ($contractAccessLevel) {
                    $visibilityRules = $accessLevelModel->getVisibilityRulesForLevel($contractAccessLevel['id']);



                    foreach ($champs as $nom_champ => $label) {
                        $champs[$nom_champ] = [
                            'label' => $label,
                            'visible_client' => isset($visibilityRules[$nom_champ]) ? $visibilityRules[$nom_champ] : false
                        ];
                    }
                } else {

                    // Fallback sur les valeurs par défaut si pas de niveau d'accès
                    $this->applyDefaultVisibility($champs);
                }
            } else {
                // Valeurs par défaut si pas de contrat spécifié
                $this->applyDefaultVisibility($champs);
            }
        }

        return $champs;
    }

    /**
     * Applique les valeurs par défaut de visibilité aux champs
     * @param array &$champs Référence vers le tableau des champs
     */
    private function applyDefaultVisibility(&$champs)
    {
        $visibilites_defaut = [
            'type_materiel' => true,
            'marque' => true,
            'modele' => true,
            'reference' => true,
            'usage_materiel' => true,
            'numero_serie' => false,
            'version_firmware' => false,
            'ancien_firmware' => false,
            'adresse_mac' => false,
            'adresse_ip' => false,
            'masque' => false,
            'passerelle' => false,
            'id_materiel_tech' => false,
            'login' => false,
            'password' => false,
            'ip_primaire' => false,
            'mac_primaire' => false,
            'ip_secondaire' => false,
            'mac_secondaire' => false,
            'stream_aes67_recu' => false,
            'stream_aes67_transmis' => false,
            'ssid' => false,
            'type_cryptage' => false,
            'password_wifi' => false,
            'libelle_pa_salle' => true,
            'numero_port_switch' => false,
            'vlan' => false,
            'date_fin_maintenance' => false,
            'date_fin_garantie' => false,
            'date_derniere_inter' => false,
            'commentaire' => false,
            'url_github' => false
        ];

        foreach ($champs as $nom_champ => $label) {
            $champs[$nom_champ] = [
                'label' => $label,
                'visible_client' => $visibilites_defaut[$nom_champ]
            ];
        }
    }

    /**
     * Sauvegarde la visibilité des champs pour un matériel
     * 
     * @param int $materielId ID du matériel
     * @param array $visibilites Tableau des visibilités par champ
     * @return bool Succès de la sauvegarde
     */
    public function saveVisibiliteChamps($materielId, $visibilites)
    {
        try {
            $this->db->beginTransaction();

            // Supprimer les anciennes visibilités
            $query = "DELETE FROM visibilite_champs_materiel WHERE materiel_id = :materiel_id";
            $stmt = $this->db->prepare($query);
            $stmt->bindParam(':materiel_id', $materielId, PDO::PARAM_INT);
            $stmt->execute();

            // Insérer seulement les champs visibles (visible = true)
            $query = "INSERT INTO visibilite_champs_materiel (materiel_id, nom_champ) VALUES (:materiel_id, :nom_champ)";
            $stmt = $this->db->prepare($query);

            foreach ($visibilites as $nom_champ => $visible) {
                if ($visible) {
                    $stmt->bindParam(':materiel_id', $materielId, PDO::PARAM_INT);
                    $stmt->bindParam(':nom_champ', $nom_champ, PDO::PARAM_STR);
                    $stmt->execute();
                }
            }

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            return false;
        }
    }

    /**
     * Récupère les pièces jointes d'un matériel
     * 
     * @param int $materielId ID du matériel
     * @return array Liste des pièces jointes
     */
    public function getPiecesJointes($materielId)
    {
        $query = "
            SELECT 
                pj.*,
                st.setting_value as type_nom,
                CONCAT(u.first_name, ' ', u.last_name) as created_by_name
            FROM pieces_jointes pj
            LEFT JOIN settings st ON pj.type_id = st.id
            LEFT JOIN users u ON pj.created_by = u.id
            INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
            WHERE lpj.type_liaison = 'materiel' 
            AND lpj.entite_id = :materiel_id
            ORDER BY pj.date_creation DESC
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':materiel_id', $materielId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Ajoute une pièce jointe à un matériel
     * 
     * @param int $materielId ID du matériel
     * @param array $data Données de la pièce jointe
     * @return int ID de la pièce jointe créée
     */
    public function addPieceJointe($materielId, $data)
    {
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
                        :piece_jointe_id, 'materiel', :materiel_id
                    )";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':piece_jointe_id' => $pieceJointeId,
                ':materiel_id' => $materielId
            ]);

            $this->db->commit();
            return $pieceJointeId;
        } catch (Exception $e) {
            $this->db->rollBack();

            throw $e;
        }
    }

    /**
     * Supprime une pièce jointe d'un matériel
     * 
     * @param int $pieceJointeId ID de la pièce jointe
     * @param int $materielId ID du matériel (pour vérification)
     * @return bool Succès de la suppression
     */
    public function deletePieceJointe($pieceJointeId, $materielId)
    {
        try {
            $this->db->beginTransaction();

            // Vérifier que la pièce jointe appartient bien au matériel
            $query = "SELECT pj.* FROM pieces_jointes pj
                     INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                     WHERE lpj.type_liaison = 'materiel' 
                     AND lpj.entite_id = :materiel_id 
                     AND pj.id = :piece_jointe_id";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':materiel_id' => $materielId,
                ':piece_jointe_id' => $pieceJointeId
            ]);

            $pieceJointe = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$pieceJointe) {
                throw new Exception("Pièce jointe non trouvée ou non autorisée");
            }

            // Supprimer la liaison
            $query = "DELETE FROM liaisons_pieces_jointes 
                     WHERE piece_jointe_id = :piece_jointe_id 
                     AND type_liaison = 'materiel' 
                     AND entite_id = :materiel_id";

            $stmt = $this->db->prepare($query);
            $stmt->execute([
                ':piece_jointe_id' => $pieceJointeId,
                ':materiel_id' => $materielId
            ]);

            // Supprimer la pièce jointe
            $query = "DELETE FROM pieces_jointes WHERE id = :piece_jointe_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':piece_jointe_id' => $pieceJointeId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();

            throw $e;
        }
    }

    /**
     * Récupère les informations de visibilité des champs pour une liste de matériels
     * 
     * @param array $materielIds Liste des IDs de matériel
     * @return array Informations de visibilité par matériel
     */
    public function getVisibiliteChampsForMateriels($materielIds)
    {
        if (empty($materielIds)) {
            return [];
        }

        // Récupérer tous les champs possibles
        $allFields = [
            'type_materiel',
            'marque',
            'modele',
            'reference',
            'usage_materiel',
            'numero_serie',
            'version_firmware',
            'ancien_firmware',
            'adresse_mac',
            'adresse_ip',
            'masque',
            'passerelle',
            'id_materiel_tech',
            'login',
            'password',
            'ip_primaire',
            'mac_primaire',
            'ip_secondaire',
            'mac_secondaire',
            'stream_aes67_recu',
            'stream_aes67_transmis',
            'ssid',
            'type_cryptage',
            'password_wifi',
            'libelle_pa_salle',
            'numero_port_switch',
            'vlan',
            'date_fin_maintenance',
            'date_fin_garantie',
            'date_derniere_inter',
            'commentaire',
            'url_github'
        ];

        $placeholders = str_repeat('?,', count($materielIds) - 1) . '?';
        $query = "SELECT materiel_id, nom_champ 
                 FROM visibilite_champs_materiel 
                 WHERE materiel_id IN ($placeholders)";

        $stmt = $this->db->prepare($query);
        $stmt->execute($materielIds);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Organiser les résultats par matériel
        $visibilites = [];
        foreach ($materielIds as $materielId) {
            $visibilites[$materielId] = [];
            // Par défaut, tous les champs sont masqués (false)
            foreach ($allFields as $field) {
                $visibilites[$materielId][$field] = false;
            }
        }

        // Marquer les champs présents comme visibles (true)
        foreach ($results as $row) {
            $materielId = $row['materiel_id'];
            $nom_champ = $row['nom_champ'];
            if (isset($visibilites[$materielId][$nom_champ])) {
                $visibilites[$materielId][$nom_champ] = true;
            }
        }

        return $visibilites;
    }

    /**
     * Compte le nombre de pièces jointes d'un matériel
     * 
     * @param int $materielId ID du matériel
     * @return int Nombre de pièces jointes
     */
    public function getPiecesJointesCount($materielId)
    {
        $query = "
            SELECT COUNT(*) as count
            FROM pieces_jointes pj
            INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
            WHERE lpj.type_liaison = 'materiel' 
            AND lpj.entite_id = :materiel_id
        ";

        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':materiel_id', $materielId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) $result['count'];
    }



    /**
     * Récupère les matériels pour l'export en masse
     * 
     * @param int $clientId ID du client
     * @param int|null $siteId ID du site (optionnel)
     * @return array Liste des matériels avec informations complètes
     */
    public function getMaterielsForBulkExport($clientId, $siteId = null)
    {
        $params = [':client_id' => $clientId];

        $query = "
            SELECT 
                m.*,
                r.id as salle_id,
                r.name as salle_name,
                s.name as site_name,
                c.name as client_name
            FROM materiel m
            LEFT JOIN rooms r ON m.salle_id = r.id
            LEFT JOIN sites s ON r.site_id = s.id
            LEFT JOIN clients c ON s.client_id = c.id
            WHERE c.id = :client_id
        ";

        if ($siteId) {
            $query .= " AND s.id = :site_id";
            $params[':site_id'] = $siteId;
        }

        $query .= " ORDER BY s.name, r.name, m.marque, m.modele";

        $stmt = $this->db->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compte le nombre de matériel dans une salle
     * 
     * @param int $salleId ID de la salle
     * @return int Nombre de matériel
     */
    public function getMaterielCountBySalle($salleId)
    {
        $query = "SELECT COUNT(*) as count FROM materiel WHERE salle_id = :salle_id";
        $stmt = $this->db->prepare($query);
        $stmt->bindParam(':salle_id', $salleId, PDO::PARAM_INT);
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return (int) $result['count'];
    }
    public function saveAll($rows)
    {
        $this->db->exec("TRUNCATE TABLE materiel");

        $stmt = $this->db->prepare(
            "INSERT INTO excel_tables (nom, quantite, prix) VALUES (?, ?, ?)"
        );

        foreach ($rows as $i => $row) {
            if ($i === 0)
                continue;

            $stmt->execute([
                $row[0] ?? null,
                $row[1] ?? 0,
                $row[2] ?? 0
            ]);
        }
    }
    public function getAll()
    {

        $stmt = $this->db->query(
            "SELECT nom, quantite, prix FROM excel_tables"
        );

        $data = [["Nom", "Quantité", "Prix"]];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $data[] = [
                $row['nom'],
                $row['quantite'],
                $row['prix']
            ];
        }

        return $data;
    }
}
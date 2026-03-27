<?php
/**
 * Service centralisé pour la gestion des pièces jointes
 * 
 * Centralise toutes les opérations sur les pièces jointes :
 * - Upload de fichiers
 * - Téléchargement
 * - Aperçu
 * - Suppression
 * - Toggle visibilité (masque_client)
 * - Mise à jour du nom personnalisé
 */
class AttachmentService {
    private $db;
    private $baseUploadPath;

    // Types de liaisons supportés
    const TYPE_INTERVENTION = 'intervention';
    const TYPE_CONTRACT = 'contract';
    const TYPE_MATERIEL = 'materiel';
    const TYPE_DOCUMENTATION = 'documentation';
    const TYPE_BI = 'bi'; // Bon d'intervention

    // Mappage des types vers les répertoires
    private static $UPLOAD_DIRS = [
        self::TYPE_INTERVENTION => 'interventions',
        self::TYPE_CONTRACT => 'contracts',
        self::TYPE_MATERIEL => 'materiel',
        self::TYPE_DOCUMENTATION => 'documentation',
        self::TYPE_BI => 'interventions' // Les bons d'intervention sont dans le même dossier
    ];

    public function __construct($db, $baseUploadPath = null) {
        $this->db = $db;
        // Par défaut, le chemin de base est à la racine du projet
        // __DIR__ est dans public/classes/Services/, donc ../../../ remonte à la racine (Avision/)
        // uploads/ est à la racine, donc ../../../uploads
        $this->baseUploadPath = $baseUploadPath ?: __DIR__ . '/../../../uploads';
    }

    /**
     * Upload un ou plusieurs fichiers pour une entité
     * 
     * @param string $typeLiaison Type de liaison (intervention, contract, materiel, etc.)
     * @param int $entityId ID de l'entité
     * @param array $files Tableau de fichiers $_FILES
     * @param array $options Options supplémentaires (custom_names, descriptions, masque_client, etc.)
     * @param int $userId ID de l'utilisateur qui upload
     * @return array ['success' => bool, 'uploaded_files' => array, 'errors' => array, 'attachment_ids' => array]
     */
    public function upload($typeLiaison, $entityId, $files, $options = [], $userId = null) {
        require_once __DIR__ . '/../../includes/FileUploadValidator.php';
        require_once __DIR__ . '/../../includes/functions.php';

        $uploadedFiles = [];
        $errors = [];
        $attachmentIds = [];

        // Déterminer le répertoire de destination
        $uploadDir = $this->getUploadDirectory($typeLiaison, $entityId);
        
        // Créer le répertoire s'il n'existe pas
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Vérifier la limite de taille du serveur
        $maxFileSize = getServerMaxUploadSize();

        // Traiter chaque fichier
        $fileArray = $this->normalizeFilesArray($files);
        
        // Log pour debug
        custom_log("AttachmentService::upload - Type: $typeLiaison, EntityId: $entityId, Files count: " . count($fileArray) . ", UploadDir: $uploadDir", 'DEBUG');
        
        foreach ($fileArray as $index => $file) {
            // Vérifier les erreurs d'upload
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = "Erreur lors de l'upload du fichier " . ($index + 1) . " : " . $this->getUploadErrorMessage($file['error']);
                continue;
            }

            $originalFileName = $file['name'];
            $fileSize = $file['size'];
            $fileTmpPath = $file['tmp_name'];

            // Vérifier la taille du fichier
            if ($fileSize > $maxFileSize) {
                $errors[] = "Le fichier '$originalFileName' est trop volumineux (max " . formatFileSize($maxFileSize) . ")";
                continue;
            }

            // Vérifier l'extension
            $fileExtension = strtolower(pathinfo($originalFileName, PATHINFO_EXTENSION));
            if (!FileUploadValidator::isExtensionAllowed($fileExtension, $this->db)) {
                $errors[] = "Le format du fichier '$originalFileName' n'est pas accepté";
                continue;
            }

            // Générer un nom de fichier unique
            $finalFileName = $this->generateUniqueFileName($uploadDir, $originalFileName, $fileExtension);
            $filePath = $uploadDir . '/' . $finalFileName;

            custom_log("AttachmentService::upload - Tentative de déplacement: $fileTmpPath -> $filePath", 'DEBUG');

            // Déplacer le fichier
            if (!move_uploaded_file($fileTmpPath, $filePath)) {
                $errorMsg = "Erreur lors du déplacement du fichier '$originalFileName' vers '$filePath' (tmp: $fileTmpPath)";
                $errors[] = $errorMsg;
                custom_log("AttachmentService::upload - $errorMsg", 'ERROR');
                continue;
            }
            
            custom_log("AttachmentService::upload - Fichier déplacé avec succès: $filePath", 'DEBUG');

            // Préparer les données pour la base de données
            $customName = $options['custom_names'][$index] ?? null;
            $displayName = $customName ?: $originalFileName;
            $description = $options['descriptions'][$index] ?? null;
            $masqueClient = isset($options['masque_client'][$index]) ? (int)$options['masque_client'][$index] : 0;
            $typeId = $options['type_id'][$index] ?? null;

            $data = [
                'nom_fichier' => $originalFileName,
                'nom_personnalise' => $displayName,
                'chemin_fichier' => $this->getRelativePath($typeLiaison, $entityId, $finalFileName),
                'type_fichier' => $fileExtension,
                'taille_fichier' => $fileSize,
                'commentaire' => $description,
                'masque_client' => $masqueClient,
                'type_id' => $typeId,
                'created_by' => $userId
            ];

            // Insérer dans la base de données
            try {
                $attachmentId = $this->insertAttachment($typeLiaison, $entityId, $data);
                $uploadedFiles[] = $displayName;
                $attachmentIds[] = $attachmentId;
                custom_log("AttachmentService::upload - Fichier uploadé avec succès: $originalFileName (ID: $attachmentId)", 'DEBUG');
            } catch (Exception $e) {
                // Supprimer le fichier si l'insertion échoue
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                $errorMsg = "Erreur lors de l'enregistrement du fichier '$originalFileName' : " . $e->getMessage();
                $errors[] = $errorMsg;
                custom_log("AttachmentService::upload - $errorMsg", 'ERROR');
            }
        }

        return [
            'success' => empty($errors) && !empty($uploadedFiles),
            'uploaded_files' => $uploadedFiles,
            'errors' => $errors,
            'attachment_ids' => $attachmentIds
        ];
    }

    /**
     * Télécharge une pièce jointe
     * 
     * @param int $attachmentId ID de la pièce jointe
     * @param bool $forceDownload Forcer le téléchargement (sinon affichage inline)
     * @return void Envoie les headers et le fichier, puis exit
     */
    public function download($attachmentId, $forceDownload = true) {
        $attachment = $this->getAttachmentById($attachmentId);
        
        if (!$attachment) {
            throw new Exception("Pièce jointe non trouvée");
        }

        $filePath = $this->getAbsoluteFilePath($attachment['chemin_fichier']);
        
        if (!file_exists($filePath)) {
            throw new Exception("Fichier non trouvé");
        }

        // Déterminer le type MIME
        $mimeType = mime_content_type($filePath);
        if (!$mimeType) {
            $mimeType = 'application/octet-stream';
        }

        // Nom du fichier pour le téléchargement
        $downloadName = $attachment['nom_personnalise'] ?? $attachment['nom_fichier'];

        // Définir les headers
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: ' . ($forceDownload ? 'attachment' : 'inline') . '; filename="' . addslashes($downloadName) . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: no-cache, must-revalidate');
        header('Pragma: no-cache');
        header('Expires: 0');

        // Lire et envoyer le fichier
        readfile($filePath);
        exit;
    }

    /**
     * Affiche l'aperçu d'une pièce jointe (pour les images/PDF)
     * 
     * @param int $attachmentId ID de la pièce jointe
     * @return void Envoie les headers et le fichier, puis exit
     */
    public function preview($attachmentId) {
        $this->download($attachmentId, false);
    }

    /**
     * Supprime une pièce jointe
     * 
     * @param int $attachmentId ID de la pièce jointe
     * @param string $typeLiaison Type de liaison (pour vérification)
     * @param int $entityId ID de l'entité (pour vérification)
     * @return bool Succès de la suppression
     */
    public function delete($attachmentId, $typeLiaison = null, $entityId = null) {
        try {
            $this->db->beginTransaction();

            // Récupérer la pièce jointe
            $attachment = $this->getAttachmentById($attachmentId);
            
            if (!$attachment) {
                throw new Exception("Pièce jointe non trouvée");
            }

            // Vérifier la liaison si spécifiée
            if ($typeLiaison && $entityId) {
                $query = "SELECT 1 FROM liaisons_pieces_jointes 
                         WHERE piece_jointe_id = :attachment_id 
                         AND type_liaison = :type_liaison 
                         AND entite_id = :entity_id";
                $stmt = $this->db->prepare($query);
                $stmt->execute([
                    ':attachment_id' => $attachmentId,
                    ':type_liaison' => $typeLiaison,
                    ':entity_id' => $entityId
                ]);
                
                if (!$stmt->fetch()) {
                    throw new Exception("La pièce jointe n'appartient pas à cette entité");
                }
            }

            // Supprimer le fichier physique
            $filePath = $this->getAbsoluteFilePath($attachment['chemin_fichier']);
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Supprimer les liaisons
            $query = "DELETE FROM liaisons_pieces_jointes WHERE piece_jointe_id = :attachment_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':attachment_id' => $attachmentId]);

            // Supprimer la pièce jointe
            $query = "DELETE FROM pieces_jointes WHERE id = :attachment_id";
            $stmt = $this->db->prepare($query);
            $stmt->execute([':attachment_id' => $attachmentId]);

            $this->db->commit();
            return true;
        } catch (Exception $e) {
            $this->db->rollBack();
            custom_log("Erreur lors de la suppression de la pièce jointe : " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Toggle la visibilité client (masque_client)
     * 
     * @param int $attachmentId ID de la pièce jointe
     * @param bool $masqueClient Nouvelle valeur (true = masqué, false = visible)
     * @return bool Succès de la mise à jour
     */
    public function toggleVisibility($attachmentId, $masqueClient) {
        $query = "UPDATE pieces_jointes 
                 SET masque_client = :masque_client 
                 WHERE id = :attachment_id";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':masque_client' => $masqueClient ? 1 : 0,
            ':attachment_id' => $attachmentId
        ]);
    }

    /**
     * Met à jour le nom personnalisé d'une pièce jointe
     * 
     * @param int $attachmentId ID de la pièce jointe
     * @param string $newName Nouveau nom personnalisé
     * @return bool Succès de la mise à jour
     */
    public function updateName($attachmentId, $newName) {
        $query = "UPDATE pieces_jointes 
                 SET nom_personnalise = :nom_personnalise 
                 WHERE id = :attachment_id";
        
        $stmt = $this->db->prepare($query);
        return $stmt->execute([
            ':nom_personnalise' => $newName,
            ':attachment_id' => $attachmentId
        ]);
    }

    /**
     * Récupère une pièce jointe par son ID
     * 
     * @param int $attachmentId ID de la pièce jointe
     * @return array|null Données de la pièce jointe ou null
     */
    public function getAttachmentById($attachmentId) {
        $query = "SELECT pj.*, lpj.type_liaison, lpj.entite_id
                 FROM pieces_jointes pj
                 LEFT JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                 WHERE pj.id = :attachment_id
                 LIMIT 1";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([':attachment_id' => $attachmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Récupère toutes les pièces jointes d'une entité
     * 
     * @param string $typeLiaison Type de liaison
     * @param int $entityId ID de l'entité
     * @return array Liste des pièces jointes
     */
    public function getAttachments($typeLiaison, $entityId) {
        $query = "SELECT 
                    pj.*,
                    st.setting_value as type_nom,
                    CONCAT(u.first_name, ' ', u.last_name) as created_by_name,
                    lpj.type_liaison
                  FROM pieces_jointes pj
                  LEFT JOIN settings st ON pj.type_id = st.id
                  LEFT JOIN users u ON pj.created_by = u.id
                  INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                  WHERE lpj.type_liaison = :type_liaison
                  AND lpj.entite_id = :entity_id
                  ORDER BY pj.date_creation DESC";
        
        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':type_liaison' => $typeLiaison,
            ':entity_id' => $entityId
        ]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========== Méthodes privées ==========

    /**
     * Normalise le tableau $_FILES en un format uniforme
     */
    private function normalizeFilesArray($files) {
        $normalized = [];
        
        // Si c'est un fichier unique
        if (isset($files['name']) && !is_array($files['name'])) {
            custom_log("AttachmentService::normalizeFilesArray - Fichier unique détecté: " . $files['name'], 'DEBUG');
            return [$files];
        }
        
        // Si c'est un tableau de fichiers
        if (isset($files['name']) && is_array($files['name'])) {
            custom_log("AttachmentService::normalizeFilesArray - Tableau de fichiers détecté, count: " . count($files['name']), 'DEBUG');
            foreach ($files['name'] as $index => $name) {
                $normalized[] = [
                    'name' => $files['name'][$index],
                    'type' => $files['type'][$index] ?? '',
                    'tmp_name' => $files['tmp_name'][$index] ?? '',
                    'error' => $files['error'][$index] ?? UPLOAD_ERR_NO_FILE,
                    'size' => $files['size'][$index] ?? 0
                ];
            }
        } else {
            custom_log("AttachmentService::normalizeFilesArray - Format de fichiers non reconnu", 'ERROR');
        }
        
        return $normalized;
    }

    /**
     * Génère un nom de fichier unique
     */
    private function generateUniqueFileName($uploadDir, $originalFileName, $extension) {
        // Nettoyer le nom du fichier
        $fileName = pathinfo($originalFileName, PATHINFO_FILENAME);
        $fileName = str_replace(' ', '_', $fileName);
        $fileName = preg_replace('/[^a-zA-Z0-9_-]/', '', $fileName);
        $baseFileName = $fileName;

        // Vérifier si le fichier existe déjà et incrémenter si nécessaire
        $counter = 1;
        $finalFileName = $fileName . '.' . $extension;
        
        while (file_exists($uploadDir . '/' . $finalFileName)) {
            $finalFileName = $baseFileName . '_' . $counter . '.' . $extension;
            $counter++;
        }

        // Pour certains types, on peut ajouter un timestamp pour plus d'unicité
        if (in_array($extension, ['pdf', 'doc', 'docx', 'xls', 'xlsx'])) {
            $finalFileName = time() . '_' . $finalFileName;
        }

        return $finalFileName;
    }

    /**
     * Obtient le répertoire d'upload pour un type et une entité
     */
    private function getUploadDirectory($typeLiaison, $entityId) {
        // Pour les types documentation_*, utiliser 'documentation' comme répertoire
        if (strpos($typeLiaison, 'documentation_') === 0) {
            $dirName = 'documentation';
        } else {
            $dirName = self::$UPLOAD_DIRS[$typeLiaison] ?? 'misc';
        }
        return $this->baseUploadPath . '/' . $dirName . '/' . $entityId;
    }

    /**
     * Obtient le chemin relatif pour la base de données
     */
    private function getRelativePath($typeLiaison, $entityId, $fileName) {
        // Pour les types documentation_*, utiliser 'documentation' comme répertoire
        if (strpos($typeLiaison, 'documentation_') === 0) {
            $dirName = 'documentation';
        } else {
            $dirName = self::$UPLOAD_DIRS[$typeLiaison] ?? 'misc';
        }
        return 'uploads/' . $dirName . '/' . $entityId . '/' . $fileName;
    }

    /**
     * Obtient le chemin absolu d'un fichier depuis son chemin relatif
     */
    private function getAbsoluteFilePath($relativePath) {
        // Si le chemin commence déjà par uploads/, on le prend tel quel
        // __DIR__ est dans public/classes/Services/, donc ../../../ remonte à la racine
        if (strpos($relativePath, 'uploads/') === 0) {
            return __DIR__ . '/../../../' . $relativePath;
        }
        // Sinon, on suppose que c'est déjà un chemin relatif depuis la racine
        return __DIR__ . '/../../../' . $relativePath;
    }

    /**
     * Insère une pièce jointe dans la base de données
     */
    private function insertAttachment($typeLiaison, $entityId, $data) {
        // Insérer la pièce jointe
        $query = "INSERT INTO pieces_jointes (
                    nom_fichier, nom_personnalise, chemin_fichier, type_fichier, taille_fichier, 
                    commentaire, masque_client, type_id, created_by
                ) VALUES (
                    :nom_fichier, :nom_personnalise, :chemin_fichier, :type_fichier, :taille_fichier,
                    :commentaire, :masque_client, :type_id, :created_by
                )";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':nom_fichier' => $data['nom_fichier'],
            ':nom_personnalise' => $data['nom_personnalise'] ?? $data['nom_fichier'],
            ':chemin_fichier' => $data['chemin_fichier'],
            ':type_fichier' => $data['type_fichier'],
            ':taille_fichier' => $data['taille_fichier'],
            ':commentaire' => $data['commentaire'] ?? null,
            ':masque_client' => $data['masque_client'] ?? 0,
            ':type_id' => $data['type_id'] ?? null,
            ':created_by' => $data['created_by'] ?? null
        ]);

        $attachmentId = $this->db->lastInsertId();

        // Créer la liaison
        $query = "INSERT INTO liaisons_pieces_jointes (
                    piece_jointe_id, type_liaison, entite_id
                ) VALUES (
                    :piece_jointe_id, :type_liaison, :entity_id
                )";

        $stmt = $this->db->prepare($query);
        $stmt->execute([
            ':piece_jointe_id' => $attachmentId,
            ':type_liaison' => $typeLiaison,
            ':entity_id' => $entityId
        ]);

        return $attachmentId;
    }

    /**
     * Obtient le message d'erreur d'upload
     */
    private function getUploadErrorMessage($errorCode) {
        $messages = [
            UPLOAD_ERR_INI_SIZE => 'Le fichier dépasse la taille maximale autorisée par le serveur',
            UPLOAD_ERR_FORM_SIZE => 'Le fichier dépasse la taille maximale autorisée par le formulaire',
            UPLOAD_ERR_PARTIAL => 'Le fichier n\'a été que partiellement uploadé',
            UPLOAD_ERR_NO_FILE => 'Aucun fichier n\'a été uploadé',
            UPLOAD_ERR_NO_TMP_DIR => 'Dossier temporaire manquant',
            UPLOAD_ERR_CANT_WRITE => 'Échec de l\'écriture du fichier sur le disque',
            UPLOAD_ERR_EXTENSION => 'Une extension PHP a arrêté l\'upload du fichier'
        ];
        
        return $messages[$errorCode] ?? 'Erreur inconnue lors de l\'upload';
    }
}

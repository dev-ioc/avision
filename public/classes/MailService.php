<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../models/InterventionModel.php';
require_once __DIR__ . '/../models/MailTemplateModel.php';
require_once __DIR__ . '/../models/MailHistoryModel.php';

/**
 * Service de gestion des envois d'emails pour les interventions
 */
class MailService {
    private $db;
    private $interventionModel;
    private $mailTemplateModel;
    private $mailHistoryModel;
    private $config;

    public function __construct($db) {
        $this->db = $db;
        $this->interventionModel = new InterventionModel($db);
        $this->mailTemplateModel = new MailTemplateModel($db);
        $this->mailHistoryModel = new MailHistoryModel($db);
        $this->config = Config::getInstance();
    }

    /**
     * Envoie un email de test (méthode publique pour les tests)
     * @param string $to Adresse email destinataire
     * @param string $subject Sujet de l'email
     * @param string $body Corps de l'email
     * @return bool Succès de l'envoi
     */
    public function sendTestEmail($to, $subject, $body) {
        try {
            $oauth2Enabled = $this->config->get('oauth2_enabled', '0');
            
            if ($oauth2Enabled === '1') {
                // Vérifier le token OAuth2 avant l'envoi
                $accessToken = $this->getValidOAuth2Token();
                if (!$accessToken) {
                    throw new Exception("Token OAuth2 invalide ou expiré. Vérifiez la configuration OAuth2.");
                }
                
                custom_log_mail("Token OAuth2 valide trouvé, tentative d'envoi", 'INFO');
                return $this->sendEmailOAuth2($to, '', $subject, $body);
            } else {
                return $this->sendEmailBasic($to, '', $subject, $body);
            }
        } catch (Exception $e) {
            custom_log_mail("Erreur lors de l'envoi de l'email de test: " . $e->getMessage(), 'ERROR');
            throw $e; // Re-throw pour avoir l'erreur détaillée
        }
    }

    /**
     * Envoie un email de création d'intervention
     * @param int $interventionId ID de l'intervention
     * @return bool Succès de l'envoi
     */
    public function sendInterventionCreated($interventionId) {
        try {
            // Vérifier si l'envoi automatique est activé
            if ($this->config->get('email_auto_send_creation', '0') != '1') {
                custom_log_mail("Envoi automatique de création désactivé pour l'intervention $interventionId", 'INFO');
                return true;
            }

            // Récupérer l'intervention
            $intervention = $this->interventionModel->getById($interventionId);
            if (!$intervention) {
                throw new Exception("Intervention $interventionId introuvable");
            }

            // Récupérer le template
            $template = $this->mailTemplateModel->getByType('intervention_created');
            if (!$template) {
                throw new Exception("Template de création d'intervention introuvable");
            }

            // Préparer les destinataires
            $recipients = $this->prepareRecipients($intervention);

            // Remplacer les variables dans le template
            $subject = $this->replaceTemplateVariables($template['subject'], $intervention);
            $body = $this->replaceTemplateVariables($template['body'], $intervention);

            // Envoyer l'email
            return $this->sendEmail($recipients, $subject, $body, 'intervention_created', $interventionId);

        } catch (Exception $e) {
            custom_log_mail("Erreur envoi email création intervention $interventionId : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Envoie un email de notification au technicien lorsqu'il est affecté
     * @param int $interventionId ID de l'intervention
     * @param int $technicianId ID du technicien à notifier
     * @return bool Succès de l'envoi
     */
    public function sendTechnicianAssigned($interventionId, $technicianId) {
        try {
            // Récupérer l'intervention
            $intervention = $this->interventionModel->getById($interventionId);
            if (!$intervention) {
                throw new Exception("Intervention $interventionId introuvable");
            }

            // Vérifier que le technicien existe et a un email
            require_once __DIR__ . '/../models/UserModel.php';
            $userModel = new UserModel($this->db);
            $technician = $userModel->getUserById($technicianId);
            
            if (!$technician || empty($technician['email'])) {
                throw new Exception("Technicien $technicianId introuvable ou sans email");
            }

            // Récupérer le template
            $template = $this->mailTemplateModel->getByType('technician_assigned');
            if (!$template) {
                throw new Exception("Template d'affectation de technicien introuvable");
            }

            // Préparer le destinataire (seulement le technicien)
            $recipients = [[
                'email' => $technician['email'],
                'name' => ($technician['first_name'] ?? '') . ' ' . ($technician['last_name'] ?? '')
            ]];

            // Remplacer les variables dans le template
            $subject = $this->replaceTemplateVariables($template['subject'], $intervention);
            $body = $this->replaceTemplateVariables($template['body'], $intervention);

            // Envoyer l'email
            return $this->sendEmail($recipients, $subject, $body, 'technician_assigned', $interventionId);

        } catch (Exception $e) {
            custom_log_mail("Erreur envoi email affectation technicien intervention $interventionId : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Envoie un email de fermeture d'intervention
     * @param int $interventionId ID de l'intervention
     * @param bool $force Forcer l'envoi même si l'auto-envoi est désactivé (pour envoi manuel)
     * @return bool Succès de l'envoi
     */
    public function sendInterventionClosed($interventionId, $force = false) {
        try {
            // Vérifier si l'envoi automatique est activé (sauf si forcé)
            if (!$force && $this->config->get('email_auto_send_closing', '0') != '1') {
                custom_log_mail("Envoi automatique de fermeture désactivé pour l'intervention $interventionId", 'INFO');
                return true;
            }

            // Récupérer l'intervention
            $intervention = $this->interventionModel->getById($interventionId);
            if (!$intervention) {
                throw new Exception("Intervention $interventionId introuvable");
            }

            // Récupérer le template
            $template = $this->mailTemplateModel->getByType('intervention_closed');
            if (!$template) {
                throw new Exception("Template de fermeture d'intervention introuvable");
            }

            // Préparer les destinataires
            $recipients = $this->prepareRecipients($intervention);

            // Remplacer les variables dans le template
            $subject = $this->replaceTemplateVariables($template['subject'], $intervention);
            $body = $this->replaceTemplateVariables($template['body'], $intervention);

            // Envoyer l'email
            return $this->sendEmail($recipients, $subject, $body, 'intervention_closed', $interventionId);

        } catch (Exception $e) {
            custom_log_mail("Erreur envoi email fermeture intervention $interventionId : " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Prépare la liste des destinataires pour une intervention
     * @param array $intervention Données de l'intervention
     * @param bool $includeTechnician Si true, inclure le technicien affecté (envoi manuel)
     * @return array Liste des destinataires
     */
    private function prepareRecipients($intervention, $includeTechnician = false) {
        $recipients = [];

        // Destinataire principal : site_email en priorité, puis contact_client
        $recipientEmail = !empty($intervention['site_email']) ? $intervention['site_email'] : 
                         (!empty($intervention['contact_client']) ? $intervention['contact_client'] : '');
        
        if (!empty($recipientEmail)) {
            $recipients[] = [
                'email' => $recipientEmail,
                'name' => 'Contact client'
            ];
        }

        // Option: inclure le technicien affecté (surtout pour l'envoi manuel depuis la modale)
        if ($includeTechnician) {
            $techEmail = $intervention['technician_email'] ?? '';
            $techEmail = is_string($techEmail) ? trim($techEmail) : '';

            // Fallback si l'email n'est pas présent dans $intervention
            if ($techEmail === '' && !empty($intervention['technician_id'])) {
                try {
                    require_once __DIR__ . '/../models/UserModel.php';
                    $userModel = new UserModel($this->db);
                    $technician = $userModel->getUserById((int)$intervention['technician_id']);
                    if (!empty($technician['email'])) {
                        $techEmail = trim($technician['email']);
                    }
                    if (empty($intervention['technician_name']) && (!empty($technician['first_name']) || !empty($technician['last_name']))) {
                        $intervention['technician_name'] = trim(($technician['first_name'] ?? '') . ' ' . ($technician['last_name'] ?? ''));
                    }
                } catch (Exception $e) {
                    // Ne pas bloquer l'envoi si on ne peut pas récupérer l'email du technicien
                }
            }

            if ($techEmail !== '') {
                $recipients[] = [
                    'email' => $techEmail,
                    'name' => !empty($intervention['technician_name']) ? $intervention['technician_name'] : 'Technicien'
                ];
            }
        }

        // Dédoublonner par email
        if (!empty($recipients)) {
            $unique = [];
            foreach ($recipients as $r) {
                $email = isset($r['email']) && is_string($r['email']) ? trim($r['email']) : '';
                if ($email === '') continue;
                $key = strtolower($email);
                if (!isset($unique[$key])) {
                    $unique[$key] = $r;
                }
            }
            $recipients = array_values($unique);
        }

        // Si aucun destinataire sur l'intervention, utiliser l'email de test si configuré (mode test prend le dessus)
        if (empty($recipients)) {
            $testEmail = $this->config->get('test_email', '');
            $testEmail = is_string($testEmail) ? trim($testEmail) : '';
            if ($testEmail !== '') {
                $recipients[] = [
                    'email' => $testEmail,
                    'name' => 'Mode test (aucun destinataire sur l\'intervention)'
                ];
            } else {
                throw new Exception("Aucun destinataire trouvé pour l'intervention " . $intervention['id']);
            }
        }

        return $recipients;
    }

    /**
     * Redirige les emails vers l'adresse de test si configurée
     * @param array $recipients Liste des destinataires
     * @return array Liste des destinataires (modifiée si test_email configuré)
     */
    private function redirectToTestEmail($recipients) {
        $testEmail = $this->config->get('test_email', '');
        
        if (!empty($testEmail)) {
            // Remplacer tous les destinataires par l'email de test
            $redirectedRecipients = [];
            foreach ($recipients as $recipient) {
                $redirectedRecipients[] = [
                    'email' => $testEmail,
                    'name' => $recipient['name'] . ' [REDIRIGÉ: ' . $recipient['email'] . ']',
                    'original_email' => $recipient['email'] // Garder trace de l'email original
                ];
            }
            return $redirectedRecipients;
        }
        
        return $recipients;
    }

    /**
     * Envoie un email avec gestion de la redirection de test
     * @param array $recipients Liste des destinataires
     * @param string $subject Sujet de l'email
     * @param string $body Corps de l'email
     * @param string $templateType Type de template
     * @param int $interventionId ID de l'intervention
     * @param array $attachmentPaths Liste des chemins vers les pièces jointes (optionnel)
     * @return bool Succès de l'envoi
     */
    private function sendEmail($recipients, $subject, $body, $templateType, $interventionId, $attachmentPaths = []) {
        try {
            // Rediriger vers l'email de test si configuré
            $finalRecipients = $this->redirectToTestEmail($recipients);
            
            // Ajouter une note dans le sujet si redirection active
            $testEmail = $this->config->get('test_email', '');
            if (!empty($testEmail)) {
                $subject = '[TEST] ' . $subject;
            }

            // Identifiant unique pour regrouper l'envoi dans mail_history
            $sendUuid = 'mail_' . bin2hex(random_bytes(8));

            // Snapshot du CC forcé utilisé pour cet envoi (désactivé en mode test)
            $ccEmails = $this->getCcAddressesForCurrentMode();
            $ccSnapshot = !empty($ccEmails) ? implode(', ', $ccEmails) : '';
            
            // Logger les pièces jointes
            if (!empty($attachmentPaths)) {
                custom_log_mail("Envoi email avec " . count($attachmentPaths) . " pièce(s) jointe(s) pour intervention $interventionId", 'INFO');
                foreach ($attachmentPaths as $path) {
                    custom_log_mail("  - Pièce jointe : $path (existe: " . (file_exists($path) ? 'OUI' : 'NON') . ")", 'INFO');
                }
            }
            
            // Envoyer à chaque destinataire
            foreach ($finalRecipients as $recipient) {
                $this->sendSingleEmail($recipient, $subject, $body, $templateType, $interventionId, $attachmentPaths, $sendUuid, $ccSnapshot);
            }
            
            return true;
            
        } catch (Exception $e) {
            custom_log_mail("Erreur lors de l'envoi de l'email : " . $e->getMessage(), 'ERROR', [
                'intervention_id' => $interventionId,
                'mail_host' => $this->config->get('mail_host'),
                'mail_port' => $this->config->get('mail_port'),
                'mail_encryption' => $this->config->get('mail_encryption'),
                'mail_from_address' => $this->config->get('mail_from_address'),
                'oauth2_enabled' => $this->config->get('oauth2_enabled', '0'),
            ]);
            throw $e;
        }
    }

    /**
     * Envoie un email à un destinataire unique
     * @param array $recipient Destinataire
     * @param string $subject Sujet
     * @param string $body Corps
     * @param string $templateType Type de template
     * @param int $interventionId ID de l'intervention
     * @param array $attachmentPaths Liste des chemins vers les pièces jointes
     */
    private function sendSingleEmail($recipient, $subject, $body, $templateType, $interventionId, $attachmentPaths = [], $sendUuid = null, $ccSnapshot = null) {
        // Enregistrer dans l'historique avant envoi
        $templateId = $this->mailTemplateModel->getTemplateIdByType($templateType);
        // Si aucun template trouvé (message personnalisé), passer null explicitement
        if ($templateId === false || $templateId === null) {
            $templateId = null;
        }
        $attachmentPathStr = !empty($attachmentPaths) ? implode(', ', $attachmentPaths) : null;
        $historyId = $this->mailHistoryModel->saveToHistory($interventionId, $templateId, $recipient, $subject, $body, $attachmentPathStr, $sendUuid, $ccSnapshot);
        
            custom_log_mail("Envoi email à " . $recipient['email'] . " avec " . count($attachmentPaths) . " pièce(s) jointe(s)", 'INFO');
            if (!empty($attachmentPaths)) {
                foreach ($attachmentPaths as $idx => $path) {
                    custom_log_mail("  PJ " . ($idx + 1) . ": $path (existe: " . (file_exists($path) ? 'OUI' : 'NON') . ", taille: " . (file_exists($path) ? filesize($path) : 0) . " bytes)", 'INFO');
                }
            }
        
        try {
            // Vérifier si OAuth2 est activé
            if ($this->config->get('oauth2_enabled', '0') == '1') {
                $success = $this->sendEmailOAuth2($recipient['email'], $recipient['name'], $subject, $body, $attachmentPaths);
            } else {
                $success = $this->sendEmailBasic($recipient['email'], $recipient['name'], $subject, $body, $attachmentPaths);
            }
            
            if ($success) {
                // Mettre à jour l'historique
                $this->mailHistoryModel->updateHistoryStatus($historyId, 'sent');
                custom_log_mail("Email envoyé avec succès à " . $recipient['email'], 'INFO');
            } else {
                throw new Exception("Échec de l'envoi de l'email");
            }
            
        } catch (Exception $e) {
            // Mettre à jour l'historique avec l'erreur
            $this->mailHistoryModel->updateHistoryStatus($historyId, 'failed', $e->getMessage());
            custom_log_mail("Erreur envoi email à " . $recipient['email'] . " : " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Envoie un email via OAuth2 (Exchange 365)
     */
    private function sendEmailOAuth2($to, $toName, $subject, $body, $attachmentPaths = []) {
        try {
            // Vérifier si le token OAuth2 est valide
            $accessToken = $this->getValidOAuth2Token();
            if (!$accessToken) {
                throw new Exception("Token OAuth2 invalide ou expiré");
            }

            $ccEmails = $this->getCcAddressesForCurrentMode();

            // Configuration SMTP pour OAuth2
            $host = $this->config->get('mail_host', 'smtp.office365.com');
            $port = $this->config->get('mail_port', '587');
            $fromAddress = $this->config->get('mail_from_address', '');
            $fromName = $this->config->get('mail_from_name', 'Support');

            // Créer la socket (timeout 60s)
            $socket = stream_socket_client("tcp://$host:$port", $errno, $errstr, 60);
            if (!$socket) {
                throw new Exception("Impossible de se connecter au serveur SMTP: $errstr");
            }

            // Lire la réponse initiale
            $response = fgets($socket, 1024);
            if (!preg_match('/^220/', $response)) {
                fclose($socket);
                throw new Exception("Réponse invalide du serveur SMTP: " . trim($response));
            }

            // EHLO
            $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
            fwrite($socket, "EHLO $hostname\r\n");
            $response = fgets($socket, 1024);
            
            // Lire toutes les lignes de la réponse EHLO
            $ehloResponse = $response;
            while (preg_match('/^250-/', $response)) {
                $response = fgets($socket, 1024);
                $ehloResponse .= $response;
            }

            // STARTTLS
            fwrite($socket, "STARTTLS\r\n");
            $response = fgets($socket, 1024);
            if (!preg_match('/^220/', $response)) {
                fclose($socket);
                throw new Exception("STARTTLS non supporté");
            }

            // Activer TLS
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                fclose($socket);
                throw new Exception("Impossible d'activer TLS");
            }

            // EHLO après TLS
            fwrite($socket, "EHLO $hostname\r\n");
            $response = fgets($socket, 1024);
            
            // Lire toutes les lignes de la nouvelle réponse EHLO
            $ehloResponse = $response;
            while (preg_match('/^250-/', $response)) {
                $response = fgets($socket, 1024);
                $ehloResponse .= $response;
            }

            // AUTH XOAUTH2
            $authString = base64_encode("user=$fromAddress\1auth=Bearer $accessToken\1\1");
            fwrite($socket, "AUTH XOAUTH2 $authString\r\n");
            $response = fgets($socket, 1024);

            if (!preg_match('/^235/', $response)) {
                fclose($socket);
                throw new Exception("Échec de l'authentification OAuth2: " . trim($response));
            }

            // MAIL FROM
            fwrite($socket, "MAIL FROM:<$fromAddress>\r\n");
            $response = fgets($socket, 1024);
            if (!preg_match('/^250/', $response)) {
                fclose($socket);
                throw new Exception("Erreur MAIL FROM: " . trim($response));
            }

            // RCPT TO
            fwrite($socket, "RCPT TO:<$to>\r\n");
            $response = fgets($socket, 1024);
            if (!preg_match('/^250/', $response)) {
                fclose($socket);
                throw new Exception("Erreur RCPT TO: " . trim($response));
            }

            // RCPT TO (CC)
            foreach ($ccEmails as $ccEmail) {
                fwrite($socket, "RCPT TO:<$ccEmail>\r\n");
                $response = fgets($socket, 1024);
                if (!preg_match('/^250/', $response)) {
                    fclose($socket);
                    throw new Exception("Erreur RCPT TO (CC): " . trim($response));
                }
            }

            // DATA
            fwrite($socket, "DATA\r\n");
            $response = fgets($socket, 1024);
            if (!preg_match('/^354/', $response)) {
                fclose($socket);
                throw new Exception("Erreur DATA: " . trim($response));
            }

            // Gérer les pièces jointes si présentes
            $hasAttachments = !empty($attachmentPaths);
            $boundary = null;
            
            if ($hasAttachments) {
                $boundary = "----=_Part_" . md5(time() . rand());
            }
            
            // En-têtes de l'email
            $encodedFromName = $this->encodeHeader($fromName);
            $encodedToName = $this->encodeHeader($toName);

            $emailData = "From: $encodedFromName <$fromAddress>\r\n";
            $emailData .= "To: $encodedToName <$to>\r\n";
            if (!empty($ccEmails)) {
                $emailData .= "Cc: " . implode(', ', $ccEmails) . "\r\n";
            }
            $emailData .= "Reply-To: $fromAddress\r\n";
            $emailData .= "Subject: " . $this->encodeHeader($subject) . "\r\n";
            $emailData .= "MIME-Version: 1.0\r\n";
            
            if ($hasAttachments) {
                $emailData .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
            } else {
                $emailData .= "Content-Type: text/html; charset=UTF-8\r\n";
            }
            $emailData .= "X-Mailer: Avision Mail Service\r\n";
            $emailData .= "\r\n";
            
            if ($hasAttachments) {
                // Corps du message
                $emailData .= "--$boundary\r\n";
                $emailData .= "Content-Type: text/html; charset=UTF-8\r\n";
                $emailData .= "Content-Transfer-Encoding: 8bit\r\n";
                $emailData .= "\r\n";
            }
            
            // Ajouter le corps du message (convertir les \n en \r\n si nécessaire)
            $body = str_replace(["\r\n", "\n"], "\r\n", $body);
            $emailData .= $body;
            
            // Ajouter les pièces jointes
            if ($hasAttachments) {
                custom_log_mail("Ajout de " . count($attachmentPaths) . " pièce(s) jointe(s) à l'email OAuth2", 'INFO');
                foreach ($attachmentPaths as $index => $attachmentPath) {
                    if (file_exists($attachmentPath)) {
                        $emailData .= "\r\n--$boundary\r\n";
                        $fileName = basename($attachmentPath);
                        $fileContent = file_get_contents($attachmentPath);
                        if ($fileContent === false) {
                            custom_log_mail("Erreur lors de la lecture du fichier OAuth2 : $attachmentPath", 'ERROR');
                            continue;
                        }
                        $fileContentBase64 = chunk_split(base64_encode($fileContent));
                        $mimeType = mime_content_type($attachmentPath) ?: 'application/octet-stream';
                        
                        // Encoder le nom de fichier pour les caractères spéciaux
                        $encodedFileName = $this->encodeHeader($fileName);
                        
                        $emailData .= "Content-Type: $mimeType; name=\"$encodedFileName\"\r\n";
                        $emailData .= "Content-Disposition: attachment; filename=\"$encodedFileName\"\r\n";
                        $emailData .= "Content-Transfer-Encoding: base64\r\n";
                        $emailData .= "\r\n";
                        $emailData .= $fileContentBase64;
                        custom_log_mail("Pièce jointe " . ($index + 1) . " ajoutée à l'email OAuth2 : $fileName (" . strlen($fileContent) . " bytes)", 'INFO');
                    } else {
                        custom_log_mail("Fichier introuvable pour pièce jointe OAuth2 : $attachmentPath", 'ERROR');
                    }
                }
                $emailData .= "\r\n--$boundary--\r\n";
            }
            
            // Appliquer le dot-stuffing SMTP au contenu DATA
            // IMPORTANT: Ne pas appliquer le dot-stuffing au contenu base64 des pièces jointes
            $emailDataLines = explode("\r\n", $emailData);
            $finalEmailData = "";
            $inBase64Content = false;
            
            foreach ($emailDataLines as $line) {
                // Détecter si on est dans le contenu base64 d'une pièce jointe
                if (strpos($line, 'Content-Transfer-Encoding: base64') !== false) {
                    $inBase64Content = true;
                } elseif ($hasAttachments && $boundary && strpos($line, '--' . $boundary) !== false) {
                    // Nouvelle section = fin du contenu base64 précédent
                    $inBase64Content = false;
                }
                
                // Si la ligne commence par un point ET qu'on n'est pas dans le contenu base64
                // (et que ce n'est pas un boundary), appliquer le dot-stuffing
                if (substr($line, 0, 1) === '.' && 
                    substr($line, 0, 2) !== '--' && 
                    !$inBase64Content) {
                    $finalEmailData .= '.' . $line . "\r\n";
                } else {
                    $finalEmailData .= $line . "\r\n";
                }
            }
            // Terminer avec .\r\n pour indiquer la fin du DATA
            $finalEmailData .= ".\r\n";

            fwrite($socket, $finalEmailData);
            
            // Lire la réponse (peut être multi-lignes)
            $response = fgets($socket, 1024);
            $fullResponse = $response;
            
            // Lire toutes les lignes de la réponse si nécessaire
            while (preg_match('/^250-/', $response)) {
                $response = fgets($socket, 1024);
                $fullResponse .= $response;
            }

            if (!preg_match('/^250/', $fullResponse)) {
                fclose($socket);
                throw new Exception("Erreur lors de l'envoi: " . trim($fullResponse));
            }

            // QUIT
            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            custom_log_mail("Email OAuth2 envoyé avec succès à $to" . ($hasAttachments ? " avec " . count($attachmentPaths) . " pièce(s) jointe(s)" : ""), 'INFO');
            return true;

        } catch (Exception $e) {
            custom_log_mail("Erreur OAuth2: " . $e->getMessage(), 'ERROR');
            // Re-throw l'exception pour que les messages d'erreur détaillés soient disponibles
            throw $e;
        }
    }

    /**
     * Envoie un email via authentification basique SMTP
     */
    private function sendEmailBasic($to, $toName, $subject, $body, $attachmentPaths = []) {
        try {
            // Configuration SMTP
            $host = $this->config->get('mail_host');
            $port = $this->config->get('mail_port', '587');
            $username = $this->config->get('mail_username');
            $password = $this->config->get('mail_password');
            $encryption = $this->config->get('mail_encryption', 'tls');
            $fromAddress = $this->config->get('mail_from_address');
            $fromName = $this->config->get('mail_from_name', 'Support');
            $ccEmails = $this->getCcAddressesForCurrentMode();

            // Vérifier la configuration SMTP
            if (empty($host) || empty($fromAddress)) {
                throw new Exception("Configuration SMTP incomplète : serveur ou adresse d'expédition manquante");
            }

            // Déterminer le protocole de connexion selon le chiffrement
            $protocol = 'tcp';
            $sslContext = null;
            
            if ($encryption === 'ssl') {
                $protocol = 'ssl';
                // Configurer le contexte SSL pour SSL direct
                $sslContext = stream_context_create([
                    'ssl' => [
                        'verify_peer' => false,
                        'verify_peer_name' => false,
                        'allow_self_signed' => true,
                    ]
                ]);
            }

            // Créer la socket (timeout 60s)
            $socket = @stream_socket_client(
                "$protocol://$host:$port", 
                $errno, 
                $errstr, 
                60,
                STREAM_CLIENT_CONNECT,
                $sslContext
            );
            
            if (!$socket) {
                $errorDetails = "Impossible de se connecter au serveur SMTP ($host:$port): $errstr (code: $errno)";
                if ($encryption === 'ssl') {
                    $errorDetails .= ". Vérifiez que le port $port supporte SSL direct ou essayez TLS avec le port 587.";
                }
                throw new Exception($errorDetails);
            }

            // Lire la réponse initiale (seulement pour TCP, pas pour SSL direct)
            if ($protocol === 'tcp') {
                $response = fgets($socket, 1024);
                if (!preg_match('/^220/', $response)) {
                    fclose($socket);
                    throw new Exception("Réponse invalide du serveur SMTP: " . trim($response));
                }
            }

            // EHLO
            $hostname = $_SERVER['HTTP_HOST'] ?? 'localhost';
            fwrite($socket, "EHLO $hostname\r\n");
            $response = fgets($socket, 1024);
            
            // Lire toutes les lignes de la réponse EHLO
            $ehloResponse = $response;
            while (preg_match('/^250-/', $response)) {
                $response = fgets($socket, 1024);
                $ehloResponse .= $response;
            }

            // Si TLS est demandé et non déjà activé (pas SSL direct)
            if ($encryption === 'tls' && $protocol === 'tcp') {
                // Vérifier si STARTTLS est supporté
                if (preg_match('/STARTTLS/i', $ehloResponse)) {
                    fwrite($socket, "STARTTLS\r\n");
                    $response = fgets($socket, 1024);
                    
                    if (!preg_match('/^220/', $response)) {
                        fclose($socket);
                        throw new Exception("STARTTLS non supporté ou échec: " . trim($response));
                    }

                    // Configurer le contexte SSL pour permettre les certificats auto-signés (dev)
                    $cryptoMethod = STREAM_CRYPTO_METHOD_TLS_CLIENT;
                    
                    // Essayer différentes méthodes de chiffrement TLS
                    $tlsMethods = [
                        STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT,
                        STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                        STREAM_CRYPTO_METHOD_TLSv1_1_CLIENT,
                        STREAM_CRYPTO_METHOD_TLSv1_0_CLIENT,
                        STREAM_CRYPTO_METHOD_TLS_CLIENT,
                    ];
                    
                    $tlsActivated = false;
                    $lastError = '';
                    
                    foreach ($tlsMethods as $method) {
                        // Configurer les options de contexte pour désactiver la vérification des certificats (dev)
                        $context = stream_context_create([
                            'ssl' => [
                                'verify_peer' => false,
                                'verify_peer_name' => false,
                                'allow_self_signed' => true,
                                'crypto_method' => $method
                            ]
                        ]);
                        
                        // Réessayer avec cette méthode
                        @stream_context_set_option($socket, 'ssl', 'verify_peer', false);
                        @stream_context_set_option($socket, 'ssl', 'verify_peer_name', false);
                        @stream_context_set_option($socket, 'ssl', 'allow_self_signed', true);
                        
                        if (@stream_socket_enable_crypto($socket, true, $method)) {
                            $tlsActivated = true;
                            custom_log_mail("TLS activé avec la méthode: " . $method, 'INFO');
                            break;
                        } else {
                            $lastError = error_get_last()['message'] ?? 'Erreur inconnue';
                        }
                    }
                    
                    if (!$tlsActivated) {
                        fclose($socket);
                        throw new Exception("Impossible d'activer TLS. Dernière erreur: $lastError. Essayez SSL direct (port 465) ou désactivez le chiffrement.");
                    }

                    // Renvoyer EHLO après TLS
                    fwrite($socket, "EHLO $hostname\r\n");
                    $response = fgets($socket, 1024);
                    
                    // Lire toutes les lignes de la nouvelle réponse EHLO
                    $ehloResponse = $response;
                    while (preg_match('/^250-/', $response)) {
                        $response = fgets($socket, 1024);
                        $ehloResponse .= $response;
                    }
                } else {
                    custom_log_mail("Avertissement: STARTTLS demandé mais non supporté par le serveur", 'WARNING');
                }
            }

            // Authentification optionnelle : seulement si identifiants fournis ET serveur supporte AUTH (sinon envoi sans auth, ex. Mailpit localhost)
            if (!empty($username) && !empty($password) && preg_match('/AUTH/i', $ehloResponse)) {
                // Essayer l'authentification LOGIN
                fwrite($socket, "AUTH LOGIN\r\n");
                $response = fgets($socket, 1024);
                
                if (!preg_match('/^334/', $response)) {
                    fclose($socket);
                    throw new Exception("Authentification non supportée: " . trim($response));
                }

                // Envoyer le nom d'utilisateur (base64)
                fwrite($socket, base64_encode($username) . "\r\n");
                $response = fgets($socket, 1024);
                
                if (!preg_match('/^334/', $response)) {
                    fclose($socket);
                    throw new Exception("Erreur lors de l'envoi du nom d'utilisateur: " . trim($response));
                }

                // Envoyer le mot de passe (base64)
                fwrite($socket, base64_encode($password) . "\r\n");
                $response = fgets($socket, 1024);
                
                if (!preg_match('/^235/', $response)) {
                    fclose($socket);
                    throw new Exception("Échec de l'authentification: " . trim($response));
                }
            } elseif (!empty($username) && !empty($password) && !preg_match('/AUTH/i', $ehloResponse)) {
                custom_log_mail("SMTP: serveur sans AUTH (ex. Mailpit localhost) — envoi sans authentification", 'INFO');
            }

            // MAIL FROM
            fwrite($socket, "MAIL FROM:<$fromAddress>\r\n");
            $response = fgets($socket, 1024);
            if (!preg_match('/^250/', $response)) {
                fclose($socket);
                throw new Exception("Erreur MAIL FROM: " . trim($response));
            }

            // RCPT TO
            fwrite($socket, "RCPT TO:<$to>\r\n");
            $response = fgets($socket, 1024);
            if (!preg_match('/^250/', $response)) {
                fclose($socket);
                throw new Exception("Erreur RCPT TO: " . trim($response));
            }

            // RCPT TO (CC)
            foreach ($ccEmails as $ccEmail) {
                fwrite($socket, "RCPT TO:<$ccEmail>\r\n");
                $response = fgets($socket, 1024);
                if (!preg_match('/^250/', $response)) {
                    fclose($socket);
                    throw new Exception("Erreur RCPT TO (CC): " . trim($response));
                }
            }

            // DATA
            fwrite($socket, "DATA\r\n");
            $response = fgets($socket, 1024);
            if (!preg_match('/^354/', $response)) {
                fclose($socket);
                throw new Exception("Erreur DATA: " . trim($response));
            }

            // Gérer les pièces jointes si présentes
            $hasAttachments = !empty($attachmentPaths);
            $boundary = null;
            
            if ($hasAttachments) {
                $boundary = "----=_Part_" . md5(time() . rand());
            }
            
            // Préparer les en-têtes de l'email
            $encodedFromName = $this->encodeHeader($fromName);
            $encodedToName = $this->encodeHeader($toName);

            $emailData = "From: $encodedFromName <$fromAddress>\r\n";
            $emailData .= "To: $encodedToName <$to>\r\n";
            if (!empty($ccEmails)) {
                $emailData .= "Cc: " . implode(', ', $ccEmails) . "\r\n";
            }
            $emailData .= "Reply-To: $fromAddress\r\n";
            $emailData .= "Subject: " . $this->encodeHeader($subject) . "\r\n";
            $emailData .= "MIME-Version: 1.0\r\n";
            
            if ($hasAttachments) {
                $emailData .= "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n";
            } else {
                $emailData .= "Content-Type: text/html; charset=UTF-8\r\n";
            }
            $emailData .= "X-Mailer: Avision Mail Service\r\n";
            $emailData .= "\r\n";
            
            if ($hasAttachments) {
                // Corps du message
                $emailData .= "--$boundary\r\n";
                $emailData .= "Content-Type: text/html; charset=UTF-8\r\n";
                $emailData .= "Content-Transfer-Encoding: 8bit\r\n";
                $emailData .= "\r\n";
            }
            
            // Ajouter le corps du message (convertir les \n en \r\n si nécessaire)
            $body = str_replace(["\r\n", "\n"], "\r\n", $body);
            $emailData .= $body;
            
            // Ajouter les pièces jointes
            if ($hasAttachments) {
                custom_log_mail("Ajout de " . count($attachmentPaths) . " pièce(s) jointe(s) à l'email SMTP Basic", 'INFO');
                foreach ($attachmentPaths as $index => $attachmentPath) {
                    if (file_exists($attachmentPath)) {
                        $emailData .= "\r\n--$boundary\r\n";
                        $fileName = basename($attachmentPath);
                        $fileContent = file_get_contents($attachmentPath);
                        if ($fileContent === false) {
                            custom_log_mail("Erreur lors de la lecture du fichier SMTP Basic : $attachmentPath", 'ERROR');
                            continue;
                        }
                        $fileContentBase64 = chunk_split(base64_encode($fileContent));
                        $mimeType = mime_content_type($attachmentPath) ?: 'application/octet-stream';
                        
                        // Encoder le nom de fichier pour les caractères spéciaux
                        $encodedFileName = $this->encodeHeader($fileName);
                        
                        $emailData .= "Content-Type: $mimeType; name=\"$encodedFileName\"\r\n";
                        $emailData .= "Content-Disposition: attachment; filename=\"$encodedFileName\"\r\n";
                        $emailData .= "Content-Transfer-Encoding: base64\r\n";
                        $emailData .= "\r\n";
                        $emailData .= $fileContentBase64;
                        custom_log_mail("Pièce jointe " . ($index + 1) . " ajoutée à l'email SMTP Basic : $fileName (" . strlen($fileContent) . " bytes)", 'INFO');
                    } else {
                        custom_log_mail("Fichier introuvable pour pièce jointe SMTP Basic : $attachmentPath", 'ERROR');
                    }
                }
                $emailData .= "\r\n--$boundary--\r\n";
            }
            
            // Appliquer le dot-stuffing SMTP au contenu DATA
            // IMPORTANT: Ne pas appliquer le dot-stuffing au contenu base64 des pièces jointes
            $emailDataLines = explode("\r\n", $emailData);
            $finalEmailData = "";
            $inBase64Content = false;
            
            foreach ($emailDataLines as $line) {
                // Détecter si on est dans le contenu base64 d'une pièce jointe
                if (strpos($line, 'Content-Transfer-Encoding: base64') !== false) {
                    $inBase64Content = true;
                } elseif ($hasAttachments && $boundary && strpos($line, '--' . $boundary) !== false) {
                    // Nouvelle section = fin du contenu base64 précédent
                    $inBase64Content = false;
                }
                
                // Si la ligne commence par un point ET qu'on n'est pas dans le contenu base64
                // (et que ce n'est pas un boundary), appliquer le dot-stuffing
                if (substr($line, 0, 1) === '.' && 
                    substr($line, 0, 2) !== '--' && 
                    !$inBase64Content) {
                    $finalEmailData .= '.' . $line . "\r\n";
                } else {
                    $finalEmailData .= $line . "\r\n";
                }
            }
            // Terminer avec .\r\n pour indiquer la fin du DATA
            $finalEmailData .= ".\r\n";

            fwrite($socket, $finalEmailData);
            
            // Lire la réponse (peut être multi-lignes)
            $response = fgets($socket, 1024);
            $fullResponse = $response;
            
            // Lire toutes les lignes de la réponse si nécessaire
            while (preg_match('/^250-/', $response)) {
                $response = fgets($socket, 1024);
                $fullResponse .= $response;
            }

            if (!preg_match('/^250/', $fullResponse)) {
                fclose($socket);
                throw new Exception("Erreur lors de l'envoi: " . trim($fullResponse));
            }

            // QUIT
            fwrite($socket, "QUIT\r\n");
            fclose($socket);

            custom_log_mail("Email SMTP envoyé avec succès à $to" . ($hasAttachments ? " avec " . count($attachmentPaths) . " pièce(s) jointe(s)" : ""), 'INFO');
            return true;

        } catch (Exception $e) {
            custom_log_mail("Erreur lors de l'envoi de l'email SMTP: " . $e->getMessage(), 'ERROR');
            // Re-throw l'exception pour que les messages d'erreur détaillés soient disponibles
            throw $e;
        }
    }

    /**
     * Encode un en-tête email selon RFC 2047 si nécessaire
     * @param string $header L'en-tête à encoder
     * @return string L'en-tête encodé
     */
    private function encodeHeader($header) {
        // Si l'en-tête contient des caractères non-ASCII, l'encoder
        if (preg_match('/[^\x00-\x7F]/', $header)) {
            return '=?UTF-8?B?' . base64_encode($header) . '?=';
        }
        return $header;
    }

    /**
     * Récupère l'adresse CC configurée (désactivée en mode test)
     * @return array Liste d'emails CC valides
     */
    private function getCcAddressesForCurrentMode() {
        $testEmail = $this->config->get('test_email', '');
        $testEmail = is_string($testEmail) ? trim($testEmail) : '';
        if ($testEmail !== '') {
            return [];
        }

        $raw = $this->config->get('mail_cc_address', '');
        $raw = is_string($raw) ? trim($raw) : '';
        if ($raw === '') {
            return [];
        }

        // Support simple: une seule adresse, mais on tolère aussi "a@b.com, c@d.com"
        $parts = preg_split('/[;,]+/', $raw);
        if (!$parts) {
            return [];
        }

        $emails = [];
        foreach ($parts as $part) {
            $email = trim($part);
            if ($email === '') {
                continue;
            }
            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $emails[strtolower($email)] = $email;
            }
        }
        return array_values($emails);
    }

    /**
     * Obtient un token OAuth2 valide (vérifie l'expiration et refresh si nécessaire)
     */
    private function getValidOAuth2Token() {
        $accessToken = $this->config->get('oauth2_access_token', '');
        $refreshToken = $this->config->get('oauth2_refresh_token', '');
        $tokenExpires = $this->config->get('oauth2_token_expires', '');

        // Si pas de token, retourner false
        if (empty($accessToken) || empty($refreshToken)) {
            return false;
        }

        // Vérifier si le token est expiré
        if (!empty($tokenExpires) && strtotime($tokenExpires) <= time()) {
            // Token expiré, essayer de le rafraîchir
            $newToken = $this->refreshOAuth2Token($refreshToken);
            if ($newToken) {
                return $newToken['access_token'];
            } else {
                return false;
            }
        }

        return $accessToken;
    }

    /**
     * Rafraîchit le token OAuth2
     */
    private function refreshOAuth2Token($refreshToken) {
        try {
            $clientId = $this->config->get('oauth2_client_id', '');
            $clientSecret = $this->config->get('oauth2_client_secret', '');
            $tenantId = $this->config->get('oauth2_tenant_id', '');

            if (empty($clientId) || empty($clientSecret) || empty($tenantId)) {
                throw new Exception("Configuration OAuth2 incomplète");
            }

            $tokenUrl = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/token";
            
            $postData = [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'refresh_token' => $refreshToken,
                'grant_type' => 'refresh_token',
                'scope' => 'https://outlook.office.com/SMTP.Send offline_access'
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $tokenUrl);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded'
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($httpCode !== 200) {
                throw new Exception("Erreur HTTP $httpCode lors du refresh du token");
            }

            $tokenData = json_decode($response, true);
            if (!$tokenData || isset($tokenData['error'])) {
                throw new Exception("Erreur lors du refresh du token: " . ($tokenData['error_description'] ?? 'Erreur inconnue'));
            }

            // Sauvegarder les nouveaux tokens
            $this->config->set('oauth2_access_token', $tokenData['access_token']);
            if (isset($tokenData['refresh_token'])) {
                $this->config->set('oauth2_refresh_token', $tokenData['refresh_token']);
            }
            
            $expiresIn = $tokenData['expires_in'] ?? 3600;
            $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn);
            $this->config->set('oauth2_token_expires', $expiresAt);

            custom_log_mail("Token OAuth2 rafraîchi avec succès", 'INFO');
            return $tokenData;

        } catch (Exception $e) {
            custom_log_mail("Erreur lors du refresh du token OAuth2: " . $e->getMessage(), 'ERROR');
            return false;
        }
    }

    /**
     * Formate les commentaires solution pour l'email
     * @param array $comments Liste des commentaires solution uniquement
     * @return string HTML formaté des commentaires
     */
    private function formatSolutionComments($comments) {
        if (empty($comments)) {
            return '<p><em>Aucune solution documentée pour cette intervention.</em></p>';
        }
        
        $html = '<h3>Solution(s) appliquée(s) :</h3><ul>';
        foreach ($comments as $comment) {
            $html .= '<li>';
            $html .= '<strong>' . h($comment['created_by_name']) . '</strong> ';
            $html .= '<small>(' . date('d/m/Y H:i', strtotime($comment['created_at'])) . ')</small><br>';
            $html .= nl2br(h($comment['comment']));
            $html .= '</li>';
        }
        $html .= '</ul>';
        
        return $html;
    }

    /**
     * Remplace les variables dans le template
     * @param string $template Template HTML
     * @param array $intervention Données de l'intervention
     * @return string Template avec variables remplacées
     */
    private function replaceTemplateVariables($template, $intervention) {
        // Variables de base
        $replacements = [
            // Format standard {variable}
            '{intervention_id}' => $intervention['id'] ?? '',
            '{intervention_reference}' => $intervention['reference'] ?? '',
            '{intervention_title}' => $intervention['title'] ?? '',
            '{client_name}' => $intervention['client_name'] ?? '',
            '{demande_par}' => $intervention['demande_par'] ?? '',
            '{ref_client}' => $intervention['ref_client'] ?? '',
            '{contract_name}' => $intervention['contract_name'] ?? '',
            '{site_name}' => $intervention['site_name'] ?? '',
            '{room_name}' => $intervention['room_name'] ?? '',
            '{technician_name}' => $intervention['technician_name'] ?? '',
            '{intervention_description}' => $intervention['description'] ?? '',
            '{intervention_duration}' => $intervention['duration'] ?? '',
            '{intervention_priority}' => $intervention['priority_name'] ?? '',
            '{intervention_type}' => $intervention['type_name'] ?? '',
            '{intervention_status}' => $intervention['status_name'] ?? '',
            '{tickets_used}' => $intervention['tickets_used'] ?? '0',
            '{intervention_url}' => BASE_URL . 'interventions/view/' . $intervention['id'],
            '{intervention_client_url}' => BASE_URL . 'interventions_client/view/' . $intervention['id'],
            '{created_at}' => isset($intervention['created_at']) ? date('d/m/Y H:i', strtotime($intervention['created_at'])) : '',
            '{closed_at}' => isset($intervention['closed_at']) ? date('d/m/Y H:i', strtotime($intervention['closed_at'])) : '',
            '{intervention_date}' => isset($intervention['created_at']) ? date('d/m/Y', strtotime($intervention['created_at'])) : '',
            '{intervention_planned_date}' => !empty($intervention['date_planif']) ? date('d/m/Y', strtotime($intervention['date_planif'])) : 'Non planifiée',
            '{intervention_planned_time}' => !empty($intervention['heure_planif']) ? $intervention['heure_planif'] : '',
            
            // Format avec dièse #{variable} (pour compatibilité)
            '#{intervention_id}' => $intervention['id'] ?? '',
            '#{intervention_reference}' => $intervention['reference'] ?? '',
            '#{intervention_title}' => $intervention['title'] ?? '',
            '#{client_name}' => $intervention['client_name'] ?? '',
            '#{demande_par}' => $intervention['demande_par'] ?? '',
            '#{ref_client}' => $intervention['ref_client'] ?? '',
            '#{contract_name}' => $intervention['contract_name'] ?? '',
            '#{site_name}' => $intervention['site_name'] ?? '',
            '#{room_name}' => $intervention['room_name'] ?? '',
            '#{technician_name}' => $intervention['technician_name'] ?? '',
            '#{intervention_description}' => $intervention['description'] ?? '',
            '#{intervention_duration}' => $intervention['duration'] ?? '',
            '#{intervention_priority}' => $intervention['priority_name'] ?? '',
            '#{intervention_type}' => $intervention['type_name'] ?? '',
            '#{intervention_status}' => $intervention['status_name'] ?? '',
            '#{tickets_used}' => $intervention['tickets_used'] ?? '0',
            '#{intervention_url}' => BASE_URL . 'interventions/view/' . $intervention['id'],
            '#{intervention_client_url}' => BASE_URL . 'interventions_client/view/' . $intervention['id'],
            '#{created_at}' => isset($intervention['created_at']) ? date('d/m/Y H:i', strtotime($intervention['created_at'])) : '',
            '#{closed_at}' => isset($intervention['closed_at']) ? date('d/m/Y H:i', strtotime($intervention['closed_at'])) : '',
            '#{intervention_date}' => isset($intervention['created_at']) ? date('d/m/Y', strtotime($intervention['created_at'])) : '',
            '#{intervention_planned_date}' => !empty($intervention['date_planif']) ? date('d/m/Y', strtotime($intervention['date_planif'])) : 'Non planifiée',
            '#{intervention_planned_time}' => !empty($intervention['heure_planif']) ? $intervention['heure_planif'] : '',
        ];
        
        // Pour les templates de fermeture, ajouter les commentaires solution
        if (strpos($template, '{solution_comments}') !== false) {
            $solutionComments = $this->interventionModel->getSolutionComments($intervention['id']);
            $replacements['{solution_comments}'] = $this->formatSolutionComments($solutionComments);
            $replacements['#{solution_comments}'] = $this->formatSolutionComments($solutionComments);
        }
        
        return str_replace(array_keys($replacements), array_values($replacements), $template);
    }

    /**
     * Remplace les variables dans le template avec observations
     * @param string $template Template HTML
     * @param array $intervention Données de l'intervention
     * @param array $observations Liste des observations
     * @return string Template avec variables remplacées
     */
    private function replaceTemplateVariablesWithObservations($template, $intervention, $observations = []) {
        // Utiliser la méthode de base pour remplacer les variables standard
        $template = $this->replaceTemplateVariables($template, $intervention);
        
        // Ajouter les observations
        $observationsHtml = $this->formatObservations($observations);
        $template = str_replace(['{observations}', '#{observations}'], $observationsHtml, $template);
        
        return $template;
    }

    /**
     * Envoie un email avec message personnalisé et pièces jointes
     * @param int $interventionId ID de l'intervention
     * @param string $subject Sujet de l'email
     * @param string $body Corps de l'email (HTML)
     * @param array $attachmentIds Liste des IDs des pièces jointes à joindre
     * @param bool $includeTechnician Si true, inclure le technicien affecté
     * @return bool Succès de l'envoi
     */
    public function sendCustomMessage($interventionId, $subject, $body, $attachmentIds = [], $includeTechnician = false) {
        try {
            // Récupérer l'intervention
            $interventionModel = new InterventionModel($this->db);
            $intervention = $interventionModel->getById($interventionId);
            
            if (!$intervention) {
                throw new Exception("Intervention introuvable");
            }
            
            // Préparer les destinataires
            $recipients = $this->prepareRecipients($intervention, $includeTechnician);
            
            // Préparer les pièces jointes
            $attachmentPaths = [];
            
            // Ajouter les pièces jointes sélectionnées
            if (!empty($attachmentIds)) {
                custom_log_mail("Traitement de " . count($attachmentIds) . " pièce(s) jointe(s) sélectionnée(s) pour message personnalisé", 'INFO');
                foreach ($attachmentIds as $attachmentId) {
                    $attachment = $this->getAttachmentById($interventionId, $attachmentId);
                    if ($attachment) {
                        // Construire le chemin complet
                        $tentativePath = __DIR__ . '/../../' . $attachment['chemin_fichier'];
                        $attachmentPath = realpath($tentativePath);
                        if ($attachmentPath && file_exists($attachmentPath)) {
                            $attachmentPaths[] = $attachmentPath;
                            custom_log_mail("Pièce jointe ajoutée : $attachmentPath (chemin DB: " . $attachment['chemin_fichier'] . ")", 'INFO');
                        } else {
                            custom_log_mail("Pièce jointe introuvable. ID: $attachmentId, Tentative: $tentativePath, Réel: " . ($attachmentPath ?: 'null'), 'WARNING');
                            // Essayer sans realpath
                            if (file_exists($tentativePath)) {
                                $attachmentPaths[] = $tentativePath;
                                custom_log_mail("Pièce jointe trouvée sans realpath : $tentativePath", 'INFO');
                            }
                        }
                    } else {
                        custom_log_mail("Pièce jointe ID $attachmentId introuvable en base pour intervention $interventionId", 'WARNING');
                    }
                }
            }
            
            custom_log_mail("Nombre total de pièces jointes à envoyer pour message personnalisé : " . count($attachmentPaths), 'INFO');
            if (empty($attachmentPaths)) {
                custom_log_mail("Aucune pièce jointe ne sera envoyée avec le message personnalisé", 'INFO');
            }

            // Envoyer l'email (type 'custom' pour message personnalisé)
            return $this->sendEmail($recipients, $subject, $body, 'custom', $interventionId, $attachmentPaths);

        } catch (Exception $e) {
            custom_log_mail("Erreur lors de l'envoi du message personnalisé pour intervention $interventionId : " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Formate les observations pour l'email
     * @param array $observations Liste des observations
     * @return string HTML formaté des observations
     */
    private function formatObservations($observations) {
        if (empty($observations)) {
            return '<p><em>Aucune observation pour cette intervention.</em></p>';
        }
        
        $html = '<h3>Observations :</h3><ul>';
        foreach ($observations as $index => $obs) {
            $html .= '<li>';
            $html .= '<strong>Observation ' . ($index + 1) . '</strong><br>';
            if (!empty($obs['created_by_name'])) {
                $html .= '<small>Par ' . h($obs['created_by_name']);
                if (!empty($obs['created_at'])) {
                    $html .= ' le ' . h($obs['created_at']);
                }
                $html .= '</small><br>';
            }
            $html .= nl2br(h($obs['comment'] ?? ''));
            $html .= '</li>';
        }
        $html .= '</ul>';
        
        return $html;
    }

    /**
     * Envoie un email avec un template personnalisé (pour envoi manuel)
     * @param int $interventionId ID de l'intervention
     * @param int $templateId ID du template à utiliser
     * @param array $observations Liste des observations (optionnel)
     * @param array $attachmentIds Liste des IDs des pièces jointes à joindre (optionnel)
     * @param bool $autoAttachBon Si true et template bon_intervention, joindre automatiquement le dernier BI
     * @param bool $includeTechnician Si true, inclure le technicien affecté
     * @return bool Succès de l'envoi
     */
    public function sendCustomEmail($interventionId, $templateId, $observations = [], $attachmentIds = [], $autoAttachBon = true, $includeTechnician = false) {
        try {
            // Récupérer l'intervention
            $intervention = $this->interventionModel->getById($interventionId);
            if (!$intervention) {
                throw new Exception("Intervention $interventionId introuvable");
            }

            // Récupérer le template
            $template = $this->mailTemplateModel->getById($templateId);
            if (!$template) {
                throw new Exception("Template introuvable");
            }

            // Préparer les destinataires
            $recipients = $this->prepareRecipients($intervention, $includeTechnician);

            // Remplacer les variables dans le template (avec observations si fournies)
            if (!empty($observations)) {
                $subject = $this->replaceTemplateVariablesWithObservations($template['subject'], $intervention, $observations);
                $body = $this->replaceTemplateVariablesWithObservations($template['body'], $intervention, $observations);
            } else {
                $subject = $this->replaceTemplateVariables($template['subject'], $intervention);
                $body = $this->replaceTemplateVariables($template['body'], $intervention);
            }

            // Utiliser le type de template ou 'custom' par défaut
            $templateType = $template['template_type'] ?? 'custom';

            // Préparer les pièces jointes
            $attachmentPaths = [];
            
            // Si le template est de type "bon_intervention" et autoAttachBon est true, joindre le dernier BI
            if ($templateType === 'bon_intervention' && $autoAttachBon) {
                $lastBon = $this->getLastBonIntervention($interventionId);
                if ($lastBon) {
                    // Construire le chemin complet : MailService est dans public/classes/, donc __DIR__/../../ = racine
                    $tentativePath = __DIR__ . '/../../' . $lastBon['chemin_fichier'];
                    $bonPath = realpath($tentativePath);
                    if ($bonPath && file_exists($bonPath)) {
                        $attachmentPaths[] = $bonPath;
                        custom_log_mail("Bon d'intervention ajouté automatiquement : $bonPath (chemin DB: " . $lastBon['chemin_fichier'] . ")", 'INFO');
                    } else {
                        custom_log_mail("Bon d'intervention introuvable. Tentative: $tentativePath, Réel: " . ($bonPath ?: 'null'), 'WARNING');
                        // Essayer sans realpath
                        if (file_exists($tentativePath)) {
                            $attachmentPaths[] = $tentativePath;
                            custom_log_mail("Bon d'intervention trouvé sans realpath : $tentativePath", 'INFO');
                        }
                    }
                } else {
                    custom_log_mail("Aucun bon d'intervention trouvé pour l'intervention $interventionId", 'INFO');
                }
            }
            
            // Ajouter les pièces jointes sélectionnées
            if (!empty($attachmentIds)) {
                custom_log_mail("Traitement de " . count($attachmentIds) . " pièce(s) jointe(s) sélectionnée(s)", 'INFO');
                foreach ($attachmentIds as $attachmentId) {
                    $attachment = $this->getAttachmentById($interventionId, $attachmentId);
                    if ($attachment) {
                        // Construire le chemin complet
                        $tentativePath = __DIR__ . '/../../' . $attachment['chemin_fichier'];
                        $attachmentPath = realpath($tentativePath);
                        if ($attachmentPath && file_exists($attachmentPath)) {
                            $attachmentPaths[] = $attachmentPath;
                            custom_log_mail("Pièce jointe ajoutée : $attachmentPath (chemin DB: " . $attachment['chemin_fichier'] . ")", 'INFO');
                        } else {
                            custom_log_mail("Pièce jointe introuvable. ID: $attachmentId, Tentative: $tentativePath, Réel: " . ($attachmentPath ?: 'null'), 'WARNING');
                            // Essayer sans realpath
                            if (file_exists($tentativePath)) {
                                $attachmentPaths[] = $tentativePath;
                                custom_log_mail("Pièce jointe trouvée sans realpath : $tentativePath", 'INFO');
                            }
                        }
                    } else {
                        custom_log_mail("Pièce jointe ID $attachmentId introuvable en base pour intervention $interventionId", 'WARNING');
                    }
                }
            }
            
            custom_log_mail("Nombre total de pièces jointes à envoyer : " . count($attachmentPaths), 'INFO');
            if (empty($attachmentPaths)) {
                custom_log_mail("ATTENTION: Aucune pièce jointe ne sera envoyée", 'WARNING');
            }

            // Envoyer l'email
            return $this->sendEmail($recipients, $subject, $body, $templateType, $interventionId, $attachmentPaths);

        } catch (Exception $e) {
            custom_log_mail("Erreur envoi email personnalisé intervention $interventionId : " . $e->getMessage(), 'ERROR');
            throw $e;
        }
    }

    /**
     * Récupère le dernier bon d'intervention pour une intervention
     * @param int $interventionId ID de l'intervention
     * @return array|null Le dernier bon d'intervention ou null
     */
    private function getLastBonIntervention($interventionId) {
        try {
            $sql = "SELECT pj.*, lpj.type_liaison
                    FROM pieces_jointes pj
                    INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                    WHERE lpj.type_liaison = 'bi'
                    AND lpj.entite_id = ?
                    ORDER BY pj.date_creation DESC
                    LIMIT 1";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$interventionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            custom_log_mail("Erreur lors de la récupération du dernier BI pour intervention $interventionId : " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Récupère une pièce jointe par son ID pour une intervention
     * @param int $interventionId ID de l'intervention
     * @param int $attachmentId ID de la pièce jointe
     * @return array|null La pièce jointe ou null
     */
    private function getAttachmentById($interventionId, $attachmentId) {
        try {
            $sql = "SELECT pj.*, lpj.type_liaison
                    FROM pieces_jointes pj
                    INNER JOIN liaisons_pieces_jointes lpj ON pj.id = lpj.piece_jointe_id
                    WHERE pj.id = ?
                    AND lpj.entite_id = ?
                    AND (lpj.type_liaison = 'intervention' OR lpj.type_liaison = 'bi')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$attachmentId, $interventionId]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            custom_log_mail("Erreur lors de la récupération de la pièce jointe $attachmentId pour intervention $interventionId : " . $e->getMessage(), 'ERROR');
            return null;
        }
    }

    /**
     * Prévise un template avec les variables remplacées (méthode publique pour prévisualisation)
     * @param string $template Template HTML avec variables
     * @param array $intervention Données de l'intervention
     * @param array $observations Liste des observations (optionnel)
     * @return string Template avec variables remplacées
     */
    public function previewTemplate($template, $intervention, $observations = []) {
        if (!empty($observations)) {
            return $this->replaceTemplateVariablesWithObservations($template, $intervention, $observations);
        } else {
            return $this->replaceTemplateVariables($template, $intervention);
        }
    }
}

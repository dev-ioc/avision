<?php
class SignatureService
{
    private $apiUrl;
    private $token;

    public function __construct()
    {
        $config = require __DIR__ . '/../../../config/api.php';

        $this->apiUrl = $config['signature_api_url'];

        $this->token = $this->authenticate($config);
    }

    private function authenticate($config)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, 'https://api.signnow.com/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'password',
            'username' => $config['username'],
            'password' => $config['password']
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . $config['basic_token']
        ]);
        $response = curl_exec($ch);
        curl_close($ch);
        $data = json_decode($response, true);
        return $data['access_token'] ?? null;
    }

    public function uploadDocument($pdfPath)
    {
        $ch = curl_init();

        curl_setopt(
            $ch,
            CURLOPT_URL,
            $this->apiUrl . '/document'
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // Ajouté pour éviter l'erreur SSL
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // Ajouté pour éviter l'erreur SSL

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token
        ]);

        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            'file' => new CURLFile($pdfPath)
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true);
    }

    public function createSignatureRequest(
        $pdfPath,
        $email,
        $firstname,
        $lastname,
        $phone
    ) {
        // Upload document
        $document = $this->uploadDocument($pdfPath);

        if (!isset($document['id'])) {
            custom_log('SIGNNOW: Erreur upload document - ' . json_encode($document), 'ERROR');
            return [
                'success' => false,
                'error' => 'Erreur lors de l\'upload du document'
            ];
        }

        $documentId = $document['id'];

        // AJOUT CHAMP SIGNATURE
        $this->addSignatureField($documentId);

        // INVITATION - Utiliser SMS uniquement si téléphone fourni
        if (!empty($phone)) {
            $invite = $this->inviteSignerBySmsOnly(
                $documentId,
                $phone,
                $firstname,
                $lastname
            );
        } else {
            // Sinon invitation par email uniquement
            $invite = $this->inviteSignerByEmailOnly(
                $documentId,
                $email,
                $firstname,
                $lastname
            );
        }

        custom_log('SIGNNOW Document: ' . json_encode($document), 'DEBUG');
        custom_log('SIGNNOW Invite: ' . json_encode($invite), 'DEBUG');

        // Vérifier que l'invitation a réellement réussi (status HTTP 2xx).
        // Sans ce contrôle, un document uploadé avec succès mais dont
        // l'invitation échoue (abonnement expiré, email non vérifié,
        // quota atteint, etc.) remontait quand même success => true.
        $inviteStatus = $invite['status'] ?? 0;

        if ($inviteStatus < 200 || $inviteStatus >= 300) {
            $errorMsg = $invite['data']['message']
                ?? $invite['data']['errors'][0]['message']
                ?? 'Échec de l\'envoi de l\'invitation (code ' . $inviteStatus . ')';

            custom_log('SIGNNOW: Échec invitation - ' . $errorMsg, 'ERROR');

            return [
                'success' => false,
                'document_id' => $documentId,
                'error' => $errorMsg
            ];
        }

        return [
            'success' => true,
            'document_id' => $documentId,
            'invite' => $invite
        ];
    }

    public function downloadSignedDocument($documentId)
    {
        $ch = curl_init();

        curl_setopt(
            $ch,
            CURLOPT_URL,
            $this->apiUrl . "/document/$documentId/download"
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }

    public function addSignatureField($documentId)
    {
        $ch = curl_init();

        curl_setopt(
            $ch,
            CURLOPT_URL,
            $this->apiUrl . "/document/$documentId"
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);

        $fields = [
            'fields' => [
                [
                    'x' => 350,
                    'y' => 465,
                    'width' => 200,
                    'height' => 50,
                    'page_number' => 2,
                    'type' => 'signature',
                    'role' => 'Signer 1',
                    'required' => true
                ]
            ]
        ];

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($fields));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }

    /**
     * Envoie UNIQUEMENT par email (pas de SMS)
     */
    public function inviteSignerByEmailOnly($documentId, $email, $firstname, $lastname)
    {
        if (empty($email)) {
            custom_log('SIGNNOW EMAIL INVITE ERROR - Email vide ou null', 'ERROR');
            return ['status' => 400, 'data' => ['error' => 'Email du signataire manquant']];
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . "/document/$documentId/invite");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);

        $signerData = [
            'email' => $email,
            'role' => 'Signer 1',
            'order' => 1,
        ];

        if (!empty($firstname)) {
            $signerData['first_name'] = $firstname;
        }
        if (!empty($lastname)) {
            $signerData['last_name'] = $lastname;
        }

        $body = [
            'to' => [$signerData],
            'from' => 'dev_mdg@caspeo.fr',
            'subject' => 'Demande de signature - Bon d\'intervention',
            'message' => 'Veuillez signer le bon d\'intervention en cliquant sur le lien ci-dessous.',
        ];

        custom_log('SIGNNOW EMAIL INVITE BODY = ' . json_encode($body), 'DEBUG');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            custom_log('SIGNNOW EMAIL CURL ERROR = ' . $error, 'ERROR');
        }

        custom_log('SIGNNOW EMAIL INVITE RESPONSE = ' . $response, 'DEBUG');

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }

    /**
     * Envoie UNIQUEMENT par SMS (pas d'email)
     */
    public function inviteSignerBySmsOnly($documentId, $phone, $firstname, $lastname)
    {
        if (empty($phone)) {
            custom_log('SIGNNOW SMS INVITE ERROR - Phone vide ou null', 'ERROR');
            return ['status' => 400, 'data' => ['error' => 'Numéro de téléphone manquant']];
        }

        $ch = curl_init();
        $url = $this->apiUrl . "/document/{$documentId}/invite";
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);

        $formattedPhone = $this->formatPhoneNumber($phone);

        // Structure qui a fonctionné dans les tests
        $signerData = [
            'phone_invite' => $formattedPhone,
            'phone' => $formattedPhone,
            'role' => 'Signer 1',
            'order' => 1,
            'authentication_type' => 'phone',
            'method' => 'sms',
            'language' => 'fr'
        ];

        // Ajouter le prénom et nom s'ils sont fournis
        if (!empty($firstname)) {
            $signerData['first_name'] = $firstname;
        }
        if (!empty($lastname)) {
            $signerData['last_name'] = $lastname;
        }

        // Body SANS subject, message, document_id
        $body = [
            'to' => [$signerData],
            'from' => 'dev_mdg@caspeo.fr',
        ];

        custom_log('SIGNNOW SMS INVITE BODY = ' . json_encode($body), 'DEBUG');
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            custom_log('SIGNNOW SMS CURL ERROR = ' . $error, 'ERROR');
        }

        $responseData = json_decode($response, true);
        custom_log('SIGNNOW SMS INVITE RESPONSE = ' . $response, 'DEBUG');
        custom_log('SIGNNOW SMS HTTP CODE = ' . $httpCode, 'DEBUG');

        // Vérifier si l'invitation a réussi
        if ($httpCode === 200 || $httpCode === 201) {
            custom_log('SIGNNOW: Invitation SMS envoyée avec succès au ' . $formattedPhone, 'INFO');
        }

        return [
            'status' => $httpCode,
            'data' => $responseData
        ];
    }

    /**
     * Formate un numéro de téléphone au format international E.164
     * @param string $phone Le numéro à formater
     * @return string|null Le numéro formaté ou null si invalide
     */
    private function formatPhoneNumber($phone)
    {
        // Supprimer tous les caractères non numériques sauf +
        $cleanPhone = preg_replace('/[^0-9+]/', '', $phone);

        // Si le numéro commence par +, vérifier qu'il est valide
        if (strpos($cleanPhone, '+') === 0) {
            if (strlen($cleanPhone) >= 11 && strlen($cleanPhone) <= 15) {
                return $cleanPhone;
            }
        }

        // Supprimer tous les caractères non numériques
        $digitsOnly = preg_replace('/[^0-9]/', '', $phone);

        // Pour Madagascar (indicatif +261)
        if (preg_match('/^0([0-9]{9})$/', $digitsOnly, $matches)) {
            return '+261' . $matches[1];
        }

        if (preg_match('/^261([0-9]{9})$/', $digitsOnly, $matches)) {
            return '+261' . $matches[1];
        }

        if (preg_match('/^([0-9]{9})$/', $digitsOnly, $matches)) {
            return '+261' . $matches[1];
        }

        // Pour la France (indicatif +33)
        if (preg_match('/^0([0-9]{9})$/', $digitsOnly, $matches) && strlen($digitsOnly) == 10) {
            return '+33' . $matches[1];
        }

        if (preg_match('/^33([0-9]{9})$/', $digitsOnly, $matches)) {
            return '+33' . $matches[1];
        }

        // Format invalide
        custom_log("SIGNNOW: Format de téléphone non reconnu: {$phone}", 'WARNING');
        return null;
    }

    public function createWebhook($webhookUrl)
    {
        $ch = curl_init();

        curl_setopt(
            $ch,
            CURLOPT_URL,
            $this->apiUrl . '/event_subscription'
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'callback_url' => $webhookUrl,
            'event' => 'document.complete'
        ]));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }
}
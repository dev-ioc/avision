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

        $documentId = $document['id'];

        // AJOUT CHAMP SIGNATURE
        $this->addSignatureField($documentId);

        // INVITATION
        $invite = $this->inviteSigner(
            $documentId,
            $email,
            $firstname,
            $lastname,
            $phone
        );
        custom_log(json_encode($document), 'DEBUG');
        custom_log(json_encode($invite), 'DEBUG');
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
            $this->apiUrl .
            "/document/$documentId/download"
        );

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

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

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);

        $fields = [
            'fields' => [
                [
                    'x' => 350,
                    'y' => 465, // juste sous le texte légal

                    'width' => 100,
                    'height' => 20,

                    'page_number' => 2,
                    'type' => 'signature',
                    'role' => 'Signer 1',
                    'required' => true
                ]
            ]
        ];
        ;

        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode($fields)
        );

        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }
    public function inviteSigner($documentId, $email, $firstname, $lastname, $phone)
    {
        // Vérifier que l'email n'est pas vide avant d'envoyer
        if (empty($email)) {
            custom_log('SIGNNOW INVITE ERROR - Email vide ou null', 'ERROR');
            return ['status' => 400, 'data' => ['error' => 'Email du signataire manquant']];
        }

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . "/document/$documentId/invite");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);

        $body = [
            'document_id' => $documentId,
            'to' => [
                [
                    'email' => $email,      // ← email obligatoire
                    'role' => 'Signer 1',
                    'first_name' => $firstname,  // ← recommandé
                    'last_name' => $lastname,   // ← recommandé
                    'phone' => $phone
                ]
            ],
            'from' => 'dev_mdg@caspeo.fr'
        ];

        custom_log('SIGNNOW INVITE BODY = ' . json_encode($body), 'DEBUG');

        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));

        $response = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        custom_log('SIGNNOW INVITE RESPONSE = ' . $response, 'DEBUG');
        custom_log('SIGNNOW INVITE ERROR = ' . $error, 'ERROR');

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true)
        ];
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

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

        curl_setopt($ch, CURLOPT_URL, $config['signature_api_url'] . '/oauth2/token');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // ← désactiver vérif SSL en dev
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
            'grant_type' => 'password',
            'username' => $config['username'],
            'password' => $config['password']
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Basic ' . base64_encode(
                $config['client_id'] . ':' . $config['client_secret']
            )
        ]);

        $response = curl_exec($ch);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Afficher le résultat brut pour debug
        echo '<pre>AUTH RESPONSE: ' . $response . '</pre>';
        echo '<pre>CURL ERROR: ' . $curlError . '</pre>';

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
        $lastname
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
            $lastname
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
                    'x' => 320,
                    'y' => 560, // juste sous le texte légal

                    'width' => 170,
                    'height' => 55,

                    'page_number' => 1,
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
    public function inviteSigner(
        $documentId,
        $email,
        $firstname,
        $lastname
    ) {

        $ch = curl_init();

        curl_setopt(
            $ch,
            CURLOPT_URL,
            $this->apiUrl .
            "/document/$documentId/invite"
        );

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
                    'email' => $email,
                    'role' => 'Signer 1'
                ]
            ],

            'from' => 'karijatsilefilaza@gmail.com'
        ];

        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            json_encode($body)
        );

        $response = curl_exec($ch);

        $error = curl_error($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        custom_log(
            'SIGNNOW RESPONSE = ' . $response,
            'DEBUG'
        );

        custom_log(
            'SIGNNOW ERROR = ' . $error,
            'ERROR'
        );

        curl_close($ch);

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
// class SignatureService
// {
//     private $apiUrl;
//     private $apiKey;

//     public function __construct()
//     {
//         $config = require __DIR__ . '/../../../config/api.php';

//         $this->apiUrl = rtrim(
//             $config['signature_api_url'],
//             '/'
//         );

//         $this->apiKey = $config['api_key'];
//     }

//     /**
//      * Requête CURL générique
//      */
//     private function request(
//         $method,
//         $endpoint,
//         $data = null
//     ) {

//         $ch = curl_init();

//         curl_setopt(
//             $ch,
//             CURLOPT_URL,
//             $this->apiUrl . $endpoint
//         );

//         curl_setopt(
//             $ch,
//             CURLOPT_RETURNTRANSFER,
//             true
//         );

//         curl_setopt(
//             $ch,
//             CURLOPT_CUSTOMREQUEST,
//             $method
//         );

//         curl_setopt(
//             $ch,
//             CURLOPT_HTTPHEADER,
//             [
//                 'Authorization: Bearer ' . $this->apiKey,
//                 'Content-Type: application/json'
//             ]
//         );

//         if ($data !== null) {

//             curl_setopt(
//                 $ch,
//                 CURLOPT_POSTFIELDS,
//                 json_encode($data)
//             );
//         }

//         $response = curl_exec($ch);

//         $httpCode = curl_getinfo(
//             $ch,
//             CURLINFO_HTTP_CODE
//         );

//         curl_close($ch);

//         return [
//             'status' => $httpCode,
//             'data' => json_decode($response, true)
//         ];
//     }

//     /**
//      * Créer demande de signature complète
//      */
//     public function createSignatureRequest(
//         $pdfPath,
//         $clientEmail,
//         $clientFirstname,
//         $clientLastname
//     ) {

//         // =====================================
//         // 1. CREATE SIGNATURE REQUEST
//         // =====================================

//         $signatureRequest = $this->request(
//             'POST',
//             '/signature_requests',
//             [
//                 'name' => 'Signature intervention',
//                 'delivery_mode' => 'email',
//                 'timezone' => 'Europe/Paris'
//             ]
//         );

//         if (
//             empty($signatureRequest['data']['id'])
//         ) {

//             return [
//                 'success' => false,
//                 'step' => 'create_signature_request',
//                 'response' => $signatureRequest
//             ];
//         }

//         $requestId =
//             $signatureRequest['data']['id'];

//         // =====================================
//         // 2. UPLOAD DOCUMENT
//         // =====================================

//         $fileContent = base64_encode(
//             file_get_contents($pdfPath)
//         );

//         $document = $this->uploadDocument(
//             $requestId,
//             $pdfPath
//         );

//         if (
//             empty($document['data']['id'])
//         ) {

//             return [
//                 'success' => false,
//                 'step' => 'upload_document',
//                 'response' => $document
//             ];
//         }

//         $documentId =
//             $document['data']['id'];

//         // =====================================
//         // 3. ADD SIGNER
//         // =====================================

//         $signer = $this->request(
//             'POST',
//             "/signature_requests/$requestId/signers",
//             [
//                 'info' => [
//                     'first_name' =>
//                         $clientFirstname,

//                     'last_name' =>
//                         $clientLastname,

//                     'email' =>
//                         $clientEmail,

//                     'locale' =>
//                         'fr'
//                 ],

//                 'signature_level' =>
//                     'electronic_signature',

//                 'signature_authentication_mode' =>
//                     'no_otp',

//                 'fields' => [
//                     [
//                         'document_id' =>
//                             $documentId,

//                         'type' =>
//                             'signature',

//                         'page' => 1,
//                         'x' => 390,
//                         'y' => 730
//                     ]
//                 ]
//             ]
//         );

//         if (
//             empty($signer['data']['id'])
//         ) {

//             return [
//                 'success' => false,
//                 'step' => 'add_signer',
//                 'response' => $signer
//             ];
//         }

//         // =====================================
//         // 4. ACTIVATE SIGNATURE REQUEST
//         // =====================================

//         $activate = $this->request(
//             'POST',
//             "/signature_requests/$requestId/activate"
//         );

//         if (
//             $activate['status'] !== 201
//             &&
//             $activate['status'] !== 200
//         ) {

//             return [
//                 'success' => false,
//                 'step' => 'activate_signature_request',
//                 'response' => $activate
//             ];
//         }

//         // =====================================
//         // 5. SIGNATURE LINK
//         // =====================================

//         $signatureLink =
//             $signer['data']['signature_link']
//             ?? null;

//         return [

//             'success' => true,

//             'signature_request_id' =>
//                 $requestId,

//             'document_id' =>
//                 $documentId,

//             'signer_id' =>
//                 $signer['data']['id'] ?? null,

//             'signature_link' =>
//                 $signatureLink,

//             'signature_request' =>
//                 $signatureRequest,

//             'document' =>
//                 $document,

//             'signer' =>
//                 $signer,

//             'activate' =>
//                 $activate
//         ];
//     }

//     /**
//      * Vérifier statut
//      */
//     public function getStatus($requestId)
//     {
//         return $this->request(
//             'GET',
//             "/signature_requests/$requestId"
//         );
//     }

//     /**
//      * Créer webhook global
//      */
//     public function createWebhook($webhookUrl)
//     {
//         return $this->request(
//             'POST',
//             '/webhooks',
//             [
//                 'endpoint' => $webhookUrl,

//                 'subscriptions' => [
//                     'signature_request.done',
//                     'signature_request.expired',
//                     'signature_request.declined',
//                     'signature_request.canceled'
//                 ]
//             ]
//         );
//     }
//     public function uploadDocument(
//         $requestId,
//         $pdfPath
//     ) {

//         $ch = curl_init();

//         curl_setopt(
//             $ch,
//             CURLOPT_URL,
//             $this->apiUrl .
//             "/signature_requests/$requestId/documents"
//         );

//         curl_setopt(
//             $ch,
//             CURLOPT_RETURNTRANSFER,
//             true
//         );

//         curl_setopt(
//             $ch,
//             CURLOPT_POST,
//             true
//         );

//         curl_setopt(
//             $ch,
//             CURLOPT_HTTPHEADER,
//             [
//                 'Authorization: Bearer ' . $this->apiKey
//             ]
//         );

//         curl_setopt(
//             $ch,
//             CURLOPT_POSTFIELDS,
//             [
//                 'nature' =>
//                     'signable_document',

//                 'file' =>
//                     new CURLFile($pdfPath)
//             ]
//         );

//         $response = curl_exec($ch);

//         $httpCode = curl_getinfo(
//             $ch,
//             CURLINFO_HTTP_CODE
//         );

//         curl_close($ch);

//         return [
//             'status' => $httpCode,
//             'data' => json_decode($response, true)
//         ];
//     }
//     /**
//      * Télécharger le document signé
//      */
//     public function downloadSignedDocument($requestId, $documentId)
//     {
//         $ch = curl_init();

//         curl_setopt(
//             $ch,
//             CURLOPT_URL,
//             $this->apiUrl . "/signature_requests/$requestId/documents/$documentId/download"
//         );
//         curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
//         curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
//         curl_setopt($ch, CURLOPT_HTTPHEADER, [
//             'Authorization: Bearer ' . $this->apiKey
//         ]);

//         $response = curl_exec($ch);
//         $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
//         curl_close($ch);

//         if ($httpCode === 200) {
//             return $response; // Contenu binaire du PDF
//         }
//         return null;
//     }

//     /**
//      * Récupérer les détails d'une signature request (documents inclus)
//      */
//     public function getSignatureRequestDetails($requestId)
//     {
//         return $this->request('GET', "/signature_requests/$requestId");
//     }
// }

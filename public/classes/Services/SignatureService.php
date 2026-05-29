<?php

class SignatureService
{
    private $apiUrl;
    private $apiKey;

    public function __construct()
    {
        $config = require __DIR__ . '/../../../config/api.php';

        $this->apiUrl = rtrim($config['signature_api_url'], '/');

        $this->apiKey = $config['api_key'];
    }

    private function request($method, $endpoint, $data = null)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $this->apiUrl . $endpoint);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiKey,
            'Content-Type: application/json'
        ]);

        if ($data) {
            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                json_encode($data)
            );
        }

        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }

    public function createSignatureRequest(
        $pdfPath,
        $clientEmail,
        $clientFirstname,
        $clientLastname,
        $webhookUrl
    ) {

        // 1. Créer demande de signature

        $signatureRequest = $this->request(
            'POST',
            '/signature_requests',
            [
                'name' => 'Signature intervention',
                'delivery_mode' => 'email',
                'timezone' => 'Europe/Paris',
                'webhook_url' => $webhookUrl
            ]
        );

        if (
            empty($signatureRequest['data']['id'])
        ) {
            return $signatureRequest;
        }

        $requestId = $signatureRequest['data']['id'];

        // 2. Upload document

        $fileBase64 = base64_encode(
            file_get_contents($pdfPath)
        );

        $document = $this->request(
            'POST',
            "/signature_requests/$requestId/documents",
            [
                'nature' => 'signable_document',
                'file' => $fileBase64,
                'file_name' => basename($pdfPath)
            ]
        );

        if (
            empty($document['data']['id'])
        ) {
            return $document;
        }

        $documentId = $document['data']['id'];

        // 3. Ajouter signataire

        $signer = $this->request(
            'POST',
            "/signature_requests/$requestId/signers",
            [
                'info' => [
                    'first_name' => $clientFirstname,
                    'last_name' => $clientLastname,
                    'email' => $clientEmail
                ],

                'signature_level' => 'electronic_signature',

                'signature_authentication_mode' => 'no_otp',

                'fields' => [
                    [
                        'document_id' => $documentId,
                        'type' => 'signature',
                        'page' => 1,
                        'x' => 100,
                        'y' => 500
                    ]
                ]
            ]
        );

        // 4. Activer demande

        $activate = $this->request(
            'POST',
            "/signature_requests/$requestId/activate"
        );

        return [
            'signature_request_id' => $requestId,
            'document' => $document,
            'signer' => $signer,
            'activate' => $activate
        ];
    }

    public function getStatus($requestId)
    {
        return $this->request(
            'GET',
            "/signature_requests/$requestId"
        );
    }
}
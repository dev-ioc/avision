<?php

class SignatureService
{
    private $apiUrl;
    private $apiKey;

    public function __construct()
    {
        $config = require __DIR__ . '/../../../config/api.php';

        $this->apiUrl = rtrim(
            $config['signature_api_url'],
            '/'
        );

        $this->apiKey = $config['api_key'];
    }

    /**
     * Requête CURL générique
     */
    private function request(
        $method,
        $endpoint,
        $data = null
    ) {

        $ch = curl_init();

        curl_setopt(
            $ch,
            CURLOPT_URL,
            $this->apiUrl . $endpoint
        );

        curl_setopt(
            $ch,
            CURLOPT_RETURNTRANSFER,
            true
        );

        curl_setopt(
            $ch,
            CURLOPT_CUSTOMREQUEST,
            $method
        );

        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: application/json'
            ]
        );

        if ($data !== null) {

            curl_setopt(
                $ch,
                CURLOPT_POSTFIELDS,
                json_encode($data)
            );
        }

        $response = curl_exec($ch);

        $httpCode = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }

    /**
     * Créer demande de signature complète
     */
    public function createSignatureRequest(
        $pdfPath,
        $clientEmail,
        $clientFirstname,
        $clientLastname
    ) {

        // =====================================
        // 1. CREATE SIGNATURE REQUEST
        // =====================================

        $signatureRequest = $this->request(
            'POST',
            '/signature_requests',
            [
                'name' => 'Signature intervention',
                'delivery_mode' => 'email',
                'timezone' => 'Europe/Paris'
            ]
        );

        if (
            empty($signatureRequest['data']['id'])
        ) {

            return [
                'success' => false,
                'step' => 'create_signature_request',
                'response' => $signatureRequest
            ];
        }

        $requestId =
            $signatureRequest['data']['id'];

        // =====================================
        // 2. UPLOAD DOCUMENT
        // =====================================

        $fileContent = base64_encode(
            file_get_contents($pdfPath)
        );

        $document = $this->uploadDocument(
            $requestId,
            $pdfPath
        );

        if (
            empty($document['data']['id'])
        ) {

            return [
                'success' => false,
                'step' => 'upload_document',
                'response' => $document
            ];
        }

        $documentId =
            $document['data']['id'];

        // =====================================
        // 3. ADD SIGNER
        // =====================================

        $signer = $this->request(
            'POST',
            "/signature_requests/$requestId/signers",
            [
                'info' => [
                    'first_name' =>
                        $clientFirstname,

                    'last_name' =>
                        $clientLastname,

                    'email' =>
                        $clientEmail
                ],

                'signature_level' =>
                    'electronic_signature',

                'signature_authentication_mode' =>
                    'no_otp',

                'fields' => [
                    [
                        'document_id' =>
                            $documentId,

                        'type' =>
                            'signature',

                        'page' => 1,

                        'x' => 100,

                        'y' => 500
                    ]
                ]
            ]
        );

        if (
            empty($signer['data']['id'])
        ) {

            return [
                'success' => false,
                'step' => 'add_signer',
                'response' => $signer
            ];
        }

        // =====================================
        // 4. ACTIVATE SIGNATURE REQUEST
        // =====================================

        $activate = $this->request(
            'POST',
            "/signature_requests/$requestId/activate"
        );

        if (
            $activate['status'] !== 201
            &&
            $activate['status'] !== 200
        ) {

            return [
                'success' => false,
                'step' => 'activate_signature_request',
                'response' => $activate
            ];
        }

        // =====================================
        // 5. SIGNATURE LINK
        // =====================================

        $signatureLink =
            $signer['data']['signature_link']
            ?? null;

        return [

            'success' => true,

            'signature_request_id' =>
                $requestId,

            'document_id' =>
                $documentId,

            'signer_id' =>
                $signer['data']['id'] ?? null,

            'signature_link' =>
                $signatureLink,

            'signature_request' =>
                $signatureRequest,

            'document' =>
                $document,

            'signer' =>
                $signer,

            'activate' =>
                $activate
        ];
    }

    /**
     * Vérifier statut
     */
    public function getStatus($requestId)
    {
        return $this->request(
            'GET',
            "/signature_requests/$requestId"
        );
    }

    /**
     * Créer webhook global
     */
    public function createWebhook($webhookUrl)
    {
        return $this->request(
            'POST',
            '/webhooks',
            [
                'endpoint' => $webhookUrl,

                'subscriptions' => [
                    'signature_request.done',
                    'signature_request.expired',
                    'signature_request.declined',
                    'signature_request.canceled'
                ]
            ]
        );
    }
    public function uploadDocument(
        $requestId,
        $pdfPath
    ) {

        $ch = curl_init();

        curl_setopt(
            $ch,
            CURLOPT_URL,
            $this->apiUrl .
            "/signature_requests/$requestId/documents"
        );

        curl_setopt(
            $ch,
            CURLOPT_RETURNTRANSFER,
            true
        );

        curl_setopt(
            $ch,
            CURLOPT_POST,
            true
        );

        curl_setopt(
            $ch,
            CURLOPT_HTTPHEADER,
            [
                'Authorization: Bearer ' . $this->apiKey
            ]
        );

        curl_setopt(
            $ch,
            CURLOPT_POSTFIELDS,
            [
                'nature' =>
                    'signable_document',

                'file' =>
                    new CURLFile($pdfPath)
            ]
        );

        $response = curl_exec($ch);

        $httpCode = curl_getinfo(
            $ch,
            CURLINFO_HTTP_CODE
        );

        curl_close($ch);

        return [
            'status' => $httpCode,
            'data' => json_decode($response, true)
        ];
    }
}
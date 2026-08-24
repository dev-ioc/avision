<?php

use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\Denormalizer\WebauthnSerializerFactory;

/**
 * Fournit une instance unique du Serializer Symfony configuré pour WebAuthn.
 * Nécessaire depuis webauthn-lib 5.x : les objets Options/Credential
 * n'implémentent plus JsonSerializable, json_encode() ne fonctionne plus dessus.
 */
class WebauthnSerializer
{
    private static ?\Symfony\Component\Serializer\SerializerInterface $instance = null;

    public static function get(): \Symfony\Component\Serializer\SerializerInterface
    {
        if (self::$instance === null) {
            $attestationManager = AttestationStatementSupportManager::create();
            self::$instance = (new WebauthnSerializerFactory($attestationManager))->create();
        }
        return self::$instance;
    }
}
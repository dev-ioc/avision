<?php
/**
 * TotpCrypto
 *
 * Petite classe utilitaire pour chiffrer/déchiffrer le secret TOTP stocké en base
 * (on ne peut pas se contenter d'un hash comme pour un mot de passe : il faut
 * pouvoir relire le secret en clair pour calculer le code attendu à chaque login).
 *
 * Clé de chiffrement : à définir dans une variable d'environnement, JAMAIS dans le repo.
 * Génération d'une clé (à faire une fois, en CLI) :
 *   php -r "echo base64_encode(sodium_crypto_secretbox_keygen()) . PHP_EOL;"
 * Puis dans .env / config :
 *   TOTP_ENCRYPTION_KEY=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=
 */
class TotpCrypto
{
    /**
     * Récupère la clé de chiffrement depuis l'environnement.
     * Adaptez cette méthode à votre façon de gérer la config (config.php, .env, etc.)
     */
    private static function getKey(): string
    {
        $b64 = getenv('TOTP_ENCRYPTION_KEY') ?: (defined('TOTP_ENCRYPTION_KEY') ? TOTP_ENCRYPTION_KEY : null);

        if (empty($b64)) {
            throw new RuntimeException(
                "TOTP_ENCRYPTION_KEY n'est pas configurée. " .
                "Générez-en une avec : php -r \"echo base64_encode(sodium_crypto_secretbox_keygen());\""
            );
        }

        $key = base64_decode($b64, true);

        if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETBOX_KEYBYTES) {
            throw new RuntimeException("TOTP_ENCRYPTION_KEY invalide (longueur incorrecte après décodage base64).");
        }

        return $key;
    }

    /**
     * Chiffre un secret TOTP en clair -> chaîne à stocker en base (base64)
     */
    public static function encrypt(string $plainSecret): string
    {
        $key = self::getKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($plainSecret, $nonce, $key);

        // On stocke nonce + cipher ensemble, encodés en base64
        return base64_encode($nonce . $cipher);
    }

    /**
     * Déchiffre une valeur stockée en base -> secret TOTP en clair
     */
    public static function decrypt(string $stored): string
    {
        $key = self::getKey();
        $decoded = base64_decode($stored, true);

        if ($decoded === false || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new RuntimeException("Donnée TOTP chiffrée invalide.");
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);

        if ($plain === false) {
            throw new RuntimeException("Impossible de déchiffrer le secret TOTP (clé incorrecte ou donnée corrompue).");
        }

        return $plain;
    }

    /**
     * Génère un lot de codes de secours (à afficher une seule fois à l'utilisateur).
     * Retourne un tableau ['plain' => [...codes en clair...], 'hashed' => [...codes hashés...]]
     */
    public static function generateBackupCodes(int $count = 8): array
    {
        $plain = [];
        $hashed = [];

        for ($i = 0; $i < $count; $i++) {
            // Format lisible type "AB12-CD34"
            $code = strtoupper(bin2hex(random_bytes(4)));
            $code = substr($code, 0, 4) . '-' . substr($code, 4, 4);

            $plain[] = $code;
            $hashed[] = password_hash($code, PASSWORD_DEFAULT);
        }

        return ['plain' => $plain, 'hashed' => $hashed];
    }

    /**
     * Vérifie un code de secours saisi contre la liste hashée stockée en base,
     * et retourne l'index du code consommé (à retirer de la liste) ou null si invalide.
     */
    public static function verifyBackupCode(string $inputCode, array $hashedCodes): ?int
    {
        $inputCode = strtoupper(trim($inputCode));

        foreach ($hashedCodes as $index => $hash) {
            if (password_verify($inputCode, $hash)) {
                return $index;
            }
        }

        return null;
    }
}
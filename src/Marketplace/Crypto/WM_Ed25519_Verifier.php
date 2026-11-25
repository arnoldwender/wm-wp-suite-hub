<?php
/**
 * Class WM_Ed25519_Verifier
 *
 * Ed25519 detached-signature check over the SHA-256 of a plugin ZIP, using libsodium. Nothing in
 * this version calls it on a download: no signing key exists yet (D9), so get_master_public_key()
 * returns '' unless the wm_suite_signing_public_key filter supplies one, and verify_package()
 * refuses to run without a key.
 *
 * @package ArnoldWender\SuiteHub\Marketplace\Crypto
 * @since   1.0.0
 */

declare(strict_types=1);

namespace ArnoldWender\SuiteHub\Marketplace\Crypto;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final readonly class WM_Ed25519_Verifier {

    /**
     * No signing key exists (D9). Until 1.2.2 this held a 64-character placeholder labelled the
     * official root key; it decoded to 48 bytes, so no signature could ever have verified against it.
     */
    public const DEFAULT_MASTER_PUBLIC_KEY = '';

    /**
     * Retrieves the active public key for package verification, allowing filter overrides.
     *
     * @return string Base64 encoded public key.
     */
    public static function get_master_public_key(): string {
        $key = self::DEFAULT_MASTER_PUBLIC_KEY;
        if (function_exists('apply_filters')) {
            $key = (string) apply_filters('wm_suite_signing_public_key', $key);
        }
        return $key;
    }

    /**
     * Validates the authenticity and binary integrity of a packaged ZIP using Libsodium.
     *
     * @param string $zip_path       Absolute path to the downloaded archive.
     * @param string $b64_signature  Base64 encoded detached Ed25519 signature.
     * @param string|null $b64_public_key Optional Base64 public key (defaults to master key).
     * @return bool True if signature is cryptographically valid and matching.
     * @throws \RuntimeException If cryptographic parameters or Libsodium extension are invalid.
     */
    public function verify_package(string $zip_path, string $b64_signature, ?string $b64_public_key = null): bool {
        if (!extension_loaded('sodium')) {
            throw new \RuntimeException('Libsodium extension is required for Ed25519 verification.');
        }

        if (!file_exists($zip_path) || !is_readable($zip_path)) {
            throw new \RuntimeException('Target package archive does not exist or is unreadable: ' . esc_html($zip_path));
        }

        $b64_key = $b64_public_key ?: self::get_master_public_key();
        if ($b64_key === '') {
            throw new \RuntimeException('No Ed25519 public key is configured; nothing can be verified.');
        }
        $signature  = base64_decode($b64_signature, true);
        $public_key = base64_decode($b64_key, true);

        if ($signature === false || $public_key === false) {
            return false;
        }

        // Structural parameter length verification
        if (strlen($public_key) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new \RuntimeException('Invalid Ed25519 public key length detected. Transaction aborted.');
        }

        if (strlen($signature) !== SODIUM_CRYPTO_SIGN_BYTES) {
            return false;
        }

        $file_hash = hash_file('sha256', $zip_path);
        if ($file_hash === false) {
            return false;
        }

        // Native detached signature verification (resistant to timing attacks)
        return sodium_crypto_sign_verify_detached($signature, $file_hash, $public_key);
    }

    /**
     * Utility method to generate an Ed25519 keypair (for testing or build pipeline signing).
     *
     * @return array{public_key: string, secret_key: string} Base64 encoded keypair.
     */
    public static function generate_keypair(): array {
        if (!extension_loaded('sodium')) {
            throw new \RuntimeException('Libsodium extension is required.');
        }

        $keypair    = sodium_crypto_sign_keypair();
        $public_key = sodium_crypto_sign_publickey($keypair);
        $secret_key = sodium_crypto_sign_secretkey($keypair);

        return [
            'public_key' => base64_encode($public_key),
            'secret_key' => base64_encode($secret_key),
        ];
    }

    /**
     * Utility method to sign a file using a secret key.
     *
     * @param string $file_path
     * @param string $b64_secret_key
     * @return string Base64 detached signature.
     */
    public static function sign_file(string $file_path, string $b64_secret_key): string {
        if (!extension_loaded('sodium')) {
            throw new \RuntimeException('Libsodium extension is required.');
        }

        $secret_key = base64_decode($b64_secret_key, true);
        if ($secret_key === false || strlen($secret_key) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new \RuntimeException('Invalid Ed25519 secret key length.');
        }

        $file_hash = hash_file('sha256', $file_path);
        if ($file_hash === false) {
            throw new \RuntimeException('Failed to compute SHA-256 for file: ' . esc_html($file_path));
        }

        $signature = sodium_crypto_sign_detached($file_hash, $secret_key);
        return base64_encode($signature);
    }
}

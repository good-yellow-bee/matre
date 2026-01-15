<?php

declare(strict_types=1);

namespace App\Service\Security;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Service for encrypting and decrypting sensitive credentials.
 *
 * Uses AES-256-GCM for authenticated encryption to protect credentials at rest.
 * The encryption key is derived from the application secret.
 */
class CredentialEncryptionService
{
    private const CIPHER = 'aes-256-gcm';
    private const TAG_LENGTH = 16;

    private readonly string $encryptionKey;

    public function __construct(
        #[Autowire('%kernel.secret%')]
        string $appSecret,
    ) {
        // Derive a 256-bit key from the app secret
        $this->encryptionKey = hash('sha256', $appSecret, true);
    }

    /**
     * Encrypt a credential value.
     *
     * @param string $plaintext The value to encrypt
     *
     * @return string Base64-encoded encrypted value (includes IV and auth tag)
     */
    public function encrypt(string $plaintext): string
    {
        if ('' === $plaintext) {
            return '';
        }

        // Generate random IV
        $iv = random_bytes(openssl_cipher_iv_length(self::CIPHER));

        // Encrypt with authentication tag
        $tag = '';
        $ciphertext = openssl_encrypt(
            $plaintext,
            self::CIPHER,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
            '',
            self::TAG_LENGTH,
        );

        if (false === $ciphertext) {
            throw new \RuntimeException('Encryption failed');
        }

        // Combine IV + tag + ciphertext and base64 encode
        return base64_encode($iv.$tag.$ciphertext);
    }

    /**
     * Decrypt a credential value.
     *
     * @param string $encrypted Base64-encoded encrypted value
     *
     * @return string The decrypted plaintext
     *
     * @throws \RuntimeException if decryption fails (invalid or tampered data)
     */
    public function decrypt(string $encrypted): string
    {
        if ('' === $encrypted) {
            return '';
        }

        $data = base64_decode($encrypted, true);
        if (false === $data) {
            throw new \RuntimeException('Invalid encrypted data format');
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $minLength = $ivLength + self::TAG_LENGTH + 1;

        if (strlen($data) < $minLength) {
            throw new \RuntimeException('Encrypted data too short');
        }

        // Extract components
        $iv = substr($data, 0, $ivLength);
        $tag = substr($data, $ivLength, self::TAG_LENGTH);
        $ciphertext = substr($data, $ivLength + self::TAG_LENGTH);

        // Decrypt and verify authentication tag
        $plaintext = openssl_decrypt(
            $ciphertext,
            self::CIPHER,
            $this->encryptionKey,
            OPENSSL_RAW_DATA,
            $iv,
            $tag,
        );

        if (false === $plaintext) {
            throw new \RuntimeException('Decryption failed - data may be corrupted or tampered');
        }

        return $plaintext;
    }

    /**
     * Check if a value appears to be encrypted (base64 encoded with correct structure).
     */
    public function isEncrypted(string $value): bool
    {
        if ('' === $value) {
            return false;
        }

        $data = base64_decode($value, true);
        if (false === $data) {
            return false;
        }

        $ivLength = openssl_cipher_iv_length(self::CIPHER);
        $minLength = $ivLength + self::TAG_LENGTH + 1;

        return strlen($data) >= $minLength;
    }

    /**
     * Encrypt a value only if it's not already encrypted.
     */
    public function encryptIfNeeded(string $value): string
    {
        if ('' === $value || $this->isEncrypted($value)) {
            return $value;
        }

        return $this->encrypt($value);
    }

    /**
     * Decrypt a value, returning original if decryption fails (for migration).
     */
    public function decryptSafe(string $value): string
    {
        if ('' === $value) {
            return '';
        }

        try {
            return $this->decrypt($value);
        } catch (\RuntimeException) {
            // Value is not encrypted (legacy data)
            return $value;
        }
    }
}

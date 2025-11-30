<?php
/**
 * Encryption class for secure storage of AWS credentials
 *
 * @package Metodo\MediaToolkit
 */

declare(strict_types=1);

namespace Metodo\MediaToolkit\Core;

/**
 * Handles encryption/decryption of sensitive data using WordPress salts
 */
final class Encryption
{
    private const CIPHER = 'aes-256-cbc';
    private string $key;

    public function __construct()
    {
        // Derive encryption key from WordPress salts
        $this->key = $this->derive_key();
    }

    /**
     * Derive encryption key from WordPress salts
     */
    private function derive_key(): string
    {
        $salt = '';
        
        if (defined('SECURE_AUTH_KEY')) {
            $salt .= SECURE_AUTH_KEY;
        }
        if (defined('SECURE_AUTH_SALT')) {
            $salt .= SECURE_AUTH_SALT;
        }
        
        // Fallback if salts are not defined
        if (empty($salt)) {
            $salt = 'media-toolkit-default-key-' . get_site_url();
        }
        
        // Use PBKDF2 to derive a proper key
        return hash_pbkdf2('sha256', $salt, 'media-toolkit', 10000, 32, true);
    }

    /**
     * Encrypt a value
     */
    public function encrypt(string $value): string
    {
        if (empty($value)) {
            return '';
        }

        $iv_length = openssl_cipher_iv_length(self::CIPHER);
        $iv = openssl_random_pseudo_bytes($iv_length);
        
        $encrypted = openssl_encrypt(
            $value,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        if ($encrypted === false) {
            return '';
        }
        
        // Prepend IV to encrypted data and encode
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt a value
     */
    public function decrypt(string $encrypted_value): string
    {
        if (empty($encrypted_value)) {
            return '';
        }

        $data = base64_decode($encrypted_value, true);
        if ($data === false) {
            return '';
        }
        
        $iv_length = openssl_cipher_iv_length(self::CIPHER);
        
        if (strlen($data) < $iv_length) {
            return '';
        }
        
        $iv = substr($data, 0, $iv_length);
        $encrypted = substr($data, $iv_length);
        
        $decrypted = openssl_decrypt(
            $encrypted,
            self::CIPHER,
            $this->key,
            OPENSSL_RAW_DATA,
            $iv
        );
        
        return $decrypted !== false ? $decrypted : '';
    }

    /**
     * Check if encryption is available
     */
    public function is_available(): bool
    {
        return extension_loaded('openssl') && in_array(self::CIPHER, openssl_get_cipher_methods(), true);
    }

    /**
     * Mask a sensitive value for display (show only last 4 characters)
     */
    public function mask(string $value, int $visible_chars = 4): string
    {
        if (strlen($value) <= $visible_chars) {
            return str_repeat('•', strlen($value));
        }
        
        $masked_length = strlen($value) - $visible_chars;
        return str_repeat('•', $masked_length) . substr($value, -$visible_chars);
    }
}

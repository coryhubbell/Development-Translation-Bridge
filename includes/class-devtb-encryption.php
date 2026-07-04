<?php
/**
 * DEVTB Encryption Utility
 *
 * Provides encryption/decryption for sensitive data using WordPress salts
 *
 * @package    DevelopmentTranslation_Bridge
 * @subpackage Security
 * @version    5.1.0
 */

class DEVTB_Encryption {

    /**
     * Encryption cipher method
     */
    private const CIPHER = 'AES-256-CBC';

    /**
     * Whether OpenSSL availability warning has been logged
     *
     * @var bool
     */
    private static bool $openssl_warning_logged = false;

    /**
     * Get encryption key
     *
     * Uses WordPress AUTH_KEY and SECURE_AUTH_KEY as encryption key.
     * Falls back to a machine-specific key if WordPress salts unavailable.
     *
     * @return string Encryption key (32 bytes)
     */
    private function get_key(): string {
        // Use WordPress security constants if available
        if ( defined( 'AUTH_KEY' ) && defined( 'SECURE_AUTH_KEY' ) ) {
            $key = AUTH_KEY . SECURE_AUTH_KEY;
        } else {
            // Fallback to machine-specific key derived from environment
            // This is less secure but works in CLI/standalone mode
            $key = $this->generate_fallback_key();
        }

        // Hash the key to get consistent length (32 bytes for AES-256)
        return hash( 'sha256', $key, true );
    }

    /**
     * Generate a fallback encryption key based on machine-specific values
     *
     * This is used when WordPress salts are not available (e.g., CLI mode).
     * Note: Less secure than WordPress salts - used only as a fallback.
     *
     * @return string Fallback key material
     */
    private function generate_fallback_key(): string {
        $components = array(
            php_uname( 'n' ),           // Machine hostname
            __DIR__,                     // Installation path
            'devtb_encryption_v3',        // Version identifier
        );

        return implode( '|', $components );
    }

    /**
     * Encrypt data
     *
     * @param string $data Data to encrypt.
     * @return string|false Encrypted data (base64 encoded) or false on failure.
     */
    public function encrypt( string $data ) {
        if ( empty( $data ) ) {
            return $data;
        }

        // Check if OpenSSL is available
        if ( ! function_exists( 'openssl_encrypt' ) ) {
            $this->log_openssl_warning( 'encrypt' );
            return $data; // Return unencrypted - caller should be aware via warning
        }

        try {
            $key = $this->get_key();

            // Generate random IV
            $iv_length = openssl_cipher_iv_length( self::CIPHER );
            $iv        = openssl_random_pseudo_bytes( $iv_length );

            // Encrypt the data
            $encrypted = openssl_encrypt(
                $data,
                self::CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ( false === $encrypted ) {
                return false;
            }

            // Combine IV and encrypted data, then base64 encode
            return base64_encode( $iv . $encrypted );

        } catch ( Exception $e ) {
            // Log error without exposing sensitive data
            if ( function_exists( 'error_log' ) ) {
                error_log( 'DEVTB Encryption: Encrypt operation failed' );
            }
            return false;
        }
    }

    /**
     * Log warning when OpenSSL is unavailable (once per request)
     *
     * @param string $operation The operation that required OpenSSL.
     * @return void
     */
    private function log_openssl_warning( string $operation ): void {
        if ( self::$openssl_warning_logged ) {
            return;
        }

        self::$openssl_warning_logged = true;

        $message = sprintf(
            'DEVTB Security Warning: OpenSSL extension not available for %s operation. Data will not be encrypted.',
            $operation
        );

        if ( function_exists( 'error_log' ) ) {
            error_log( $message );
        }
    }

    /**
     * Decrypt data
     *
     * @param string $encrypted_data Encrypted data (base64 encoded).
     * @return string|false Decrypted data or false on failure.
     */
    public function decrypt( string $encrypted_data ) {
        if ( empty( $encrypted_data ) ) {
            return $encrypted_data;
        }

        // Check if OpenSSL is available
        if ( ! function_exists( 'openssl_decrypt' ) ) {
            $this->log_openssl_warning( 'decrypt' );
            return $encrypted_data; // Return as-is - caller should be aware via warning
        }

        try {
            $key = $this->get_key();

            // Base64 decode
            $data = base64_decode( $encrypted_data, true );
            if ( false === $data ) {
                // Not base64 encoded - might be unencrypted legacy data
                return $encrypted_data;
            }

            // Extract IV and encrypted data
            $iv_length = openssl_cipher_iv_length( self::CIPHER );
            $iv        = substr( $data, 0, $iv_length );
            $encrypted = substr( $data, $iv_length );

            // Decrypt
            $decrypted = openssl_decrypt(
                $encrypted,
                self::CIPHER,
                $key,
                OPENSSL_RAW_DATA,
                $iv
            );

            if ( false === $decrypted ) {
                // Decryption failed - might be unencrypted legacy data
                return $encrypted_data;
            }

            return $decrypted;

        } catch ( Exception $e ) {
            // Log error without exposing sensitive data
            if ( function_exists( 'error_log' ) ) {
                error_log( 'DEVTB Encryption: Decrypt operation failed' );
            }
            return false;
        }
    }

    /**
     * Check if data is encrypted
     *
     * @param string $data Data to check.
     * @return bool True if data appears to be encrypted.
     */
    public function is_encrypted( string $data ): bool {
        if ( empty( $data ) ) {
            return false;
        }

        // Encrypted data should be base64 encoded and reasonably long
        // Minimum: 16-byte IV + at least 16 bytes of encrypted data
        $min_encrypted_length = 32;
        if ( strlen( $data ) < $min_encrypted_length ) {
            return false;
        }

        // Check if it's valid base64
        $decoded = base64_decode( $data, true );
        if ( false === $decoded ) {
            return false;
        }

        // Check if decoded length matches expected IV + data length
        $iv_length = openssl_cipher_iv_length( self::CIPHER );
        return strlen( $decoded ) > $iv_length;
    }

    /**
     * Encrypt an array of data
     *
     * @param array $data Array to encrypt.
     * @return array Encrypted array.
     */
    public function encrypt_array( array $data ): array {
        $encrypted = array();

        foreach ( $data as $key => $value ) {
            if ( is_string( $value ) ) {
                $encrypted[ $key ] = $this->encrypt( $value );
            } elseif ( is_array( $value ) ) {
                $encrypted[ $key ] = $this->encrypt_array( $value );
            } else {
                $encrypted[ $key ] = $value;
            }
        }

        return $encrypted;
    }

    /**
     * Decrypt an array of data
     *
     * @param array $data Array to decrypt.
     * @return array Decrypted array.
     */
    public function decrypt_array( array $data ): array {
        $decrypted = array();

        foreach ( $data as $key => $value ) {
            if ( is_string( $value ) ) {
                $decrypted[ $key ] = $this->decrypt( $value );
            } elseif ( is_array( $value ) ) {
                $decrypted[ $key ] = $this->decrypt_array( $value );
            } else {
                $decrypted[ $key ] = $value;
            }
        }

        return $decrypted;
    }
}

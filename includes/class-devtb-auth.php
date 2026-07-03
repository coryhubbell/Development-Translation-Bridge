<?php
/**
 * DEVTB Authentication Handler
 *
 * Manages API key generation, validation, and token-based authentication
 *
 * @package    DevelopmentTranslation_Bridge
 * @subpackage API
 * @version    4.6.0
 */

class DEVTB_Auth {
	/**
	 * Logger instance
	 *
	 * @var DEVTB_Logger
	 */
	private $logger;

	/**
	 * API key prefix
	 *
	 * @var string
	 */
	private $key_prefix = 'devtb_';

	/**
	 * Encryption utility
	 *
	 * @var DEVTB_Encryption
	 */
	private $encryption;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->logger = new DEVTB_Logger();

		// Load encryption class
		require_once __DIR__ . '/class-devtb-encryption.php';
		$this->encryption = new DEVTB_Encryption();
	}

	/**
	 * Generate new API key
	 *
	 * @param int    $user_id    User ID.
	 * @param string $name       Key name/description.
	 * @param array  $permissions Permissions array.
	 * @return array API key data.
	 */
	public function generate_api_key( int $user_id, string $name = '', array $permissions = [] ): array {
		// Generate random API key
		$key = $this->key_prefix . bin2hex( random_bytes( 24 ) );

		// Create key data
		$key_data = [
			'key'         => $key,
			'user_id'     => $user_id,
			'name'        => $name ?: 'API Key',
			'permissions' => $permissions ?: [ 'read', 'write' ],
			'created_at'  => current_time( 'mysql' ),
			'last_used'   => null,
			'status'      => 'active',
		];

		// Store in database
		$this->store_api_key( $key, $key_data );

		$this->logger->info( 'API key generated', [
			'user_id' => $user_id,
			'name'    => $name,
		]);

		return $key_data;
	}

	/**
	 * Validate API key
	 *
	 * @param string $key API key to validate.
	 * @return array|false Key data or false if invalid.
	 */
	public function validate_api_key( string $key ) {
		// Check key format
		if ( strpos( $key, $this->key_prefix ) !== 0 ) {
			$this->logger->warning( 'Invalid API key format', [
				'key_prefix' => substr( $key, 0, 10 ),
			]);
			return false;
		}

		// Get key data
		$key_data = $this->get_api_key( $key );

		if ( ! $key_data ) {
			$this->logger->warning( 'API key not found', [
				'key_prefix' => substr( $key, 0, 10 ),
			]);
			return false;
		}

		// Check if key is active
		if ( $key_data['status'] !== 'active' ) {
			$this->logger->warning( 'API key is not active', [
				'key_prefix' => substr( $key, 0, 10 ),
				'status'     => $key_data['status'],
			]);
			return false;
		}

		// Update last used timestamp
		$this->update_last_used( $key );

		$this->logger->debug( 'API key validated', [
			'user_id' => $key_data['user_id'],
			'name'    => $key_data['name'],
		]);

		return $key_data;
	}

	/**
	 * Check if API key has permission
	 *
	 * @param string $key        API key.
	 * @param string $permission Permission to check.
	 * @return bool Has permission.
	 */
	public function has_permission( string $key, string $permission ): bool {
		$key_data = $this->validate_api_key( $key );

		if ( ! $key_data ) {
			return false;
		}

		// Admin permission grants all
		if ( in_array( 'admin', $key_data['permissions'], true ) ) {
			return true;
		}

		return in_array( $permission, $key_data['permissions'], true );
	}

	/**
	 * Revoke API key
	 *
	 * @param string $key API key to revoke.
	 * @return bool Success.
	 */
	public function revoke_api_key( string $key ): bool {
		$key_data = $this->get_api_key( $key );

		if ( ! $key_data ) {
			return false;
		}

		// Update status
		$key_data['status'] = 'revoked';
		$key_data['revoked_at'] = current_time( 'mysql' );

		$this->store_api_key( $key, $key_data );

		$this->logger->info( 'API key revoked', [
			'user_id' => $key_data['user_id'],
			'name'    => $key_data['name'],
		]);

		return true;
	}

	/**
	 * Get API key from request
	 *
	 * Checks Authorization header, query parameter, and body
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return string|null API key or null.
	 */
	public function get_api_key_from_request( WP_REST_Request $request ): ?string {
		// Check Authorization header (Bearer token)
		$auth_header = $request->get_header( 'Authorization' );
		if ( $auth_header && preg_match( '/Bearer\s+(.+)/i', $auth_header, $matches ) ) {
			return $matches[1];
		}

		// Check X-API-Key header (only secure method allowed)
		$api_key_header = $request->get_header( 'X-API-Key' );
		if ( $api_key_header ) {
			return $api_key_header;
		}

		// API keys in query parameters are disabled for security
		// Query parameters are logged in server logs and browser history
		// Always use the X-API-Key header instead

		return null;
	}

	/**
	 * Authenticate request
	 *
	 * @param WP_REST_Request $request Request to authenticate.
	 * @return array|WP_Error Key data or error.
	 */
	public function authenticate_request( WP_REST_Request $request ) {
		$api_key = $this->get_api_key_from_request( $request );

		if ( ! $api_key ) {
			return new WP_Error(
				'devtb_auth_missing_key',
				'API key is required',
				[ 'status' => 401 ]
			);
		}

		$key_data = $this->validate_api_key( $api_key );

		if ( ! $key_data ) {
			return new WP_Error(
				'devtb_auth_invalid_key',
				'Invalid or expired API key',
				[ 'status' => 401 ]
			);
		}

		return $key_data;
	}

	/**
	 * Store API key in database (with encryption)
	 *
	 * @param string $key      API key.
	 * @param array  $key_data Key data.
	 */
	private function store_api_key( string $key, array $key_data ) {
		$option_name = 'devtb_api_keys';
		$keys = get_option( $option_name, [] );

		// Encrypt sensitive key data before storing
		$encrypted_data = $key_data;
		if (isset($encrypted_data['key'])) {
			$encrypted_data['key'] = $this->encryption->encrypt($encrypted_data['key']);
		}

		$keys[ $key ] = $encrypted_data;

		update_option( $option_name, $keys );
	}

	/**
	 * Get API key data (with decryption)
	 *
	 * @param string $key API key.
	 * @return array|null Key data or null.
	 */
	private function get_api_key( string $key ): ?array {
		$option_name = 'devtb_api_keys';
		$keys = get_option( $option_name, [] );

		$key_data = $keys[ $key ] ?? null;

		if ($key_data && isset($key_data['key'])) {
			// Decrypt the key if it's encrypted
			$key_data['key'] = $this->encryption->decrypt($key_data['key']);
		}

		return $key_data;
	}

	/**
	 * Update last used timestamp
	 *
	 * @param string $key API key.
	 */
	private function update_last_used( string $key ) {
		$key_data = $this->get_api_key( $key );

		if ( $key_data ) {
			$key_data['last_used'] = current_time( 'mysql' );
			$this->store_api_key( $key, $key_data );
		}
	}

	/**
	 * Get all API keys for user
	 *
	 * @param int $user_id User ID.
	 * @return array API keys.
	 */
	public function get_user_api_keys( int $user_id ): array {
		$option_name = 'devtb_api_keys';
		$all_keys = get_option( $option_name, [] );

		$user_keys = [];

		foreach ( $all_keys as $key => $data ) {
			if ( $data['user_id'] === $user_id ) {
				// Don't expose full key
				$data['key_preview'] = substr( $key, 0, 12 ) . '...' . substr( $key, -4 );
				unset( $data['key'] );

				$user_keys[] = $data;
			}
		}

		return $user_keys;
	}

	/**
	 * Clean up expired or revoked keys
	 *
	 * @param int $days Days to keep revoked keys.
	 * @return int Number of keys removed.
	 */
	public function cleanup_old_keys( int $days = 30 ): int {
		$option_name = 'devtb_api_keys';
		$keys = get_option( $option_name, [] );

		$cutoff = strtotime( "-{$days} days" );
		$removed = 0;

		foreach ( $keys as $key => $data ) {
			if ( $data['status'] === 'revoked' && isset( $data['revoked_at'] ) ) {
				$revoked_time = strtotime( $data['revoked_at'] );

				if ( $revoked_time < $cutoff ) {
					unset( $keys[ $key ] );
					$removed++;
				}
			}
		}

		if ( $removed > 0 ) {
			update_option( $option_name, $keys );

			$this->logger->info( 'Cleaned up old API keys', [
				'removed' => $removed,
			]);
		}

		return $removed;
	}

	/**
	 * Generate temporary token
	 *
	 * For short-lived access (e.g., file uploads)
	 *
	 * @param int $user_id User ID.
	 * @param int $ttl     Time to live in seconds.
	 * @return string Token.
	 */
	public function generate_temp_token( int $user_id, int $ttl = DEVTB_Config::TOKEN_TTL_DEFAULT ): string {
		$token = bin2hex( random_bytes( 32 ) );

		$token_data = [
			'user_id'    => $user_id,
			'created_at' => time(),
			'expires_at' => time() + $ttl,
		];

		set_transient( 'devtb_temp_token_' . $token, $token_data, $ttl );

		return $token;
	}

	/**
	 * Validate temporary token
	 *
	 * @param string $token Token to validate.
	 * @return int|false User ID or false if invalid.
	 */
	public function validate_temp_token( string $token ) {
		$token_data = get_transient( 'devtb_temp_token_' . $token );

		if ( ! $token_data ) {
			return false;
		}

		// Check expiration
		if ( time() > $token_data['expires_at'] ) {
			delete_transient( 'devtb_temp_token_' . $token );
			return false;
		}

		return $token_data['user_id'];
	}

	/**
	 * Get authentication statistics
	 *
	 * @return array Statistics.
	 */
	public function get_stats(): array {
		$option_name = 'devtb_api_keys';
		$keys = get_option( $option_name, [] );

		$stats = [
			'total_keys'   => 0,
			'active_keys'  => 0,
			'revoked_keys' => 0,
			'by_user'      => [],
		];

		foreach ( $keys as $key => $data ) {
			$stats['total_keys']++;

			if ( $data['status'] === 'active' ) {
				$stats['active_keys']++;
			} elseif ( $data['status'] === 'revoked' ) {
				$stats['revoked_keys']++;
			}

			$user_id = $data['user_id'];
			if ( ! isset( $stats['by_user'][ $user_id ] ) ) {
				$stats['by_user'][ $user_id ] = 0;
			}
			$stats['by_user'][ $user_id ]++;
		}

		return $stats;
	}

	/**
	 * Migrate existing API keys to encrypted format
	 *
	 * This function encrypts any unencrypted API keys in the database.
	 * Safe to run multiple times - already encrypted keys are skipped.
	 *
	 * @return array Migration results
	 */
	public function migrate_keys_to_encrypted(): array {
		$option_name = 'devtb_api_keys';
		$keys = get_option( $option_name, [] );

		$results = [
			'total' => count($keys),
			'migrated' => 0,
			'already_encrypted' => 0,
			'errors' => 0,
		];

		if (empty($keys)) {
			return $results;
		}

		$updated_keys = [];

		foreach ($keys as $key_id => $key_data) {
			// Skip if key data is missing
			if (!isset($key_data['key'])) {
				$updated_keys[$key_id] = $key_data;
				continue;
			}

			// Check if already encrypted
			if ($this->encryption->is_encrypted($key_data['key'])) {
				$results['already_encrypted']++;
				$updated_keys[$key_id] = $key_data;
				continue;
			}

			// Encrypt the key
			$encrypted_key = $this->encryption->encrypt($key_data['key']);

			if ($encrypted_key === false) {
				// Encryption failed - keep original
				$results['errors']++;
				$updated_keys[$key_id] = $key_data;
				continue;
			}

			// Update with encrypted version
			$key_data['key'] = $encrypted_key;
			$key_data['encrypted'] = true;
			$key_data['encrypted_at'] = current_time('mysql');
			$updated_keys[$key_id] = $key_data;
			$results['migrated']++;
		}

		// Save updated keys
		update_option($option_name, $updated_keys);

		$this->logger->info(sprintf(
			'API key encryption migration complete. Total: %d, Migrated: %d, Already encrypted: %d, Errors: %d',
			$results['total'],
			$results['migrated'],
			$results['already_encrypted'],
			$results['errors']
		));

		return $results;
	}
}

<?php
/**
 * WPBC Persistence Handler
 *
 * Manages database storage for translations and provides CRUD operations
 * for saving, loading, and versioning translation data.
 *
 * @package    WordPress_Bootstrap_Claude
 * @subpackage Core
 * @version    3.3.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WPBC Persistence class
 *
 * Handles all database operations for translation persistence.
 */
class WPBC_Persistence {

	/**
	 * Table name for translations (without prefix)
	 */
	private const TABLE_TRANSLATIONS = 'wpbc_translations';

	/**
	 * Table name for corrections (without prefix)
	 */
	private const TABLE_CORRECTIONS = 'wpbc_corrections';

	/**
	 * Database version for migrations
	 */
	private const DB_VERSION = '1.0.0';

	/**
	 * Option name for tracking DB version
	 */
	private const DB_VERSION_OPTION = 'wpbc_db_version';

	/**
	 * Logger instance
	 *
	 * @var WPBC_Logger
	 */
	private WPBC_Logger $logger;

	/**
	 * Translations table name with prefix
	 *
	 * @var string
	 */
	private string $table_translations;

	/**
	 * Corrections table name with prefix
	 *
	 * @var string
	 */
	private string $table_corrections;

	/**
	 * Constructor
	 */
	public function __construct() {
		global $wpdb;

		$this->logger             = new WPBC_Logger();
		$this->table_translations = $wpdb->prefix . self::TABLE_TRANSLATIONS;
		$this->table_corrections  = $wpdb->prefix . self::TABLE_CORRECTIONS;
	}

	/**
	 * Install database tables
	 *
	 * Should be called on plugin/theme activation.
	 *
	 * @return bool True on success.
	 */
	public static function install(): bool {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_translations = $wpdb->prefix . self::TABLE_TRANSLATIONS;
		$table_corrections  = $wpdb->prefix . self::TABLE_CORRECTIONS;

		// Translations table
		$sql_translations = "CREATE TABLE {$table_translations} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			project_id VARCHAR(64) DEFAULT NULL,
			name VARCHAR(255) DEFAULT NULL,
			source_framework VARCHAR(32) NOT NULL,
			target_framework VARCHAR(32) NOT NULL,
			source_code LONGTEXT NOT NULL,
			translated_code LONGTEXT NOT NULL,
			metadata JSON DEFAULT NULL,
			version INT(11) UNSIGNED DEFAULT 1,
			parent_id BIGINT(20) UNSIGNED DEFAULT NULL,
			status ENUM('draft', 'saved', 'archived') DEFAULT 'draft',
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY user_id (user_id),
			KEY project_id (project_id),
			KEY parent_id (parent_id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		// Corrections table
		$sql_corrections = "CREATE TABLE {$table_corrections} (
			id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			translation_id BIGINT(20) UNSIGNED NOT NULL,
			user_id BIGINT(20) UNSIGNED NOT NULL,
			correction_type ENUM('error', 'warning', 'info', 'enhancement') NOT NULL,
			severity ENUM('critical', 'high', 'medium', 'low') DEFAULT 'medium',
			line_number INT(11) UNSIGNED NOT NULL,
			column_start INT(11) UNSIGNED NOT NULL,
			column_end INT(11) UNSIGNED NOT NULL,
			original_text TEXT DEFAULT NULL,
			suggested_text TEXT DEFAULT NULL,
			message TEXT NOT NULL,
			ai_generated TINYINT(1) DEFAULT 0,
			confidence DECIMAL(5,2) DEFAULT NULL,
			status ENUM('pending', 'applied', 'dismissed') DEFAULT 'pending',
			applied_at DATETIME DEFAULT NULL,
			created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY translation_id (translation_id),
			KEY user_id (user_id),
			KEY status (status)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		dbDelta( $sql_translations );
		dbDelta( $sql_corrections );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );

		return true;
	}

	/**
	 * Check if tables exist
	 *
	 * @return bool True if tables exist.
	 */
	public function tables_exist(): bool {
		global $wpdb;

		$translations_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_translations )
		) === $this->table_translations;

		$corrections_exists = $wpdb->get_var(
			$wpdb->prepare( 'SHOW TABLES LIKE %s', $this->table_corrections )
		) === $this->table_corrections;

		return $translations_exists && $corrections_exists;
	}

	/**
	 * Save a new translation
	 *
	 * @param array $data Translation data.
	 * @return int|WP_Error Translation ID or error.
	 */
	public function save_translation( array $data ): int|WP_Error {
		global $wpdb;

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error( 'not_authenticated', 'User must be logged in to save translations.' );
		}

		// Validate required fields
		$required = array( 'source_framework', 'target_framework', 'source_code', 'translated_code' );
		foreach ( $required as $field ) {
			if ( empty( $data[ $field ] ) ) {
				return new WP_Error( 'missing_field', "Missing required field: {$field}" );
			}
		}

		// Validate frameworks
		if ( ! WPBC_Config::is_valid_framework( $data['source_framework'] ) ) {
			return new WP_Error( 'invalid_framework', 'Invalid source framework.' );
		}
		if ( ! WPBC_Config::is_valid_framework( $data['target_framework'] ) ) {
			return new WP_Error( 'invalid_framework', 'Invalid target framework.' );
		}

		$insert_data = array(
			'user_id'          => $user_id,
			'project_id'       => sanitize_text_field( $data['project_id'] ?? '' ) ?: null,
			'name'             => sanitize_text_field( $data['name'] ?? '' ) ?: null,
			'source_framework' => sanitize_key( $data['source_framework'] ),
			'target_framework' => sanitize_key( $data['target_framework'] ),
			'source_code'      => $data['source_code'], // Raw code, not sanitized
			'translated_code'  => $data['translated_code'], // Raw code, not sanitized
			'metadata'         => isset( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : null,
			'version'          => 1,
			'status'           => 'saved',
		);

		$format = array( '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s' );

		$result = $wpdb->insert( $this->table_translations, $insert_data, $format );

		if ( false === $result ) {
			$this->logger->error( 'Failed to save translation', array( 'error' => $wpdb->last_error ) );
			return new WP_Error( 'db_error', 'Failed to save translation.' );
		}

		$translation_id = (int) $wpdb->insert_id;

		$this->logger->info( 'Translation saved', array(
			'translation_id' => $translation_id,
			'user_id'        => $user_id,
		) );

		return $translation_id;
	}

	/**
	 * Get a translation by ID
	 *
	 * @param int $id Translation ID.
	 * @return array|WP_Error Translation data or error.
	 */
	public function get_translation( int $id ): array|WP_Error {
		global $wpdb;

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error( 'not_authenticated', 'User must be logged in.' );
		}

		$translation = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->table_translations} WHERE id = %d AND user_id = %d",
				$id,
				$user_id
			),
			ARRAY_A
		);

		if ( ! $translation ) {
			return new WP_Error( 'not_found', 'Translation not found.' );
		}

		// Decode metadata JSON
		if ( $translation['metadata'] ) {
			$translation['metadata'] = json_decode( $translation['metadata'], true );
		}

		return $translation;
	}

	/**
	 * Update a translation
	 *
	 * @param int   $id   Translation ID.
	 * @param array $data Updated data.
	 * @param bool  $create_version Whether to create a new version.
	 * @return int|WP_Error New version ID or error.
	 */
	public function update_translation( int $id, array $data, bool $create_version = false ): int|WP_Error {
		global $wpdb;

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error( 'not_authenticated', 'User must be logged in.' );
		}

		// Verify ownership
		$existing = $this->get_translation( $id );
		if ( is_wp_error( $existing ) ) {
			return $existing;
		}

		if ( $create_version ) {
			// Create a new version by inserting a new record with parent_id
			$new_version = (int) $existing['version'] + 1;

			$insert_data = array(
				'user_id'          => $user_id,
				'project_id'       => $existing['project_id'],
				'name'             => $data['name'] ?? $existing['name'],
				'source_framework' => $existing['source_framework'],
				'target_framework' => $existing['target_framework'],
				'source_code'      => $data['source_code'] ?? $existing['source_code'],
				'translated_code'  => $data['translated_code'] ?? $existing['translated_code'],
				'metadata'         => isset( $data['metadata'] ) ? wp_json_encode( $data['metadata'] ) : $existing['metadata'],
				'version'          => $new_version,
				'parent_id'        => $id,
				'status'           => 'saved',
			);

			$result = $wpdb->insert( $this->table_translations, $insert_data );

			if ( false === $result ) {
				return new WP_Error( 'db_error', 'Failed to create version.' );
			}

			return (int) $wpdb->insert_id;
		}

		// Simple update without versioning
		$update_data = array();
		$format      = array();

		if ( isset( $data['name'] ) ) {
			$update_data['name'] = sanitize_text_field( $data['name'] );
			$format[]            = '%s';
		}
		if ( isset( $data['source_code'] ) ) {
			$update_data['source_code'] = $data['source_code'];
			$format[]                   = '%s';
		}
		if ( isset( $data['translated_code'] ) ) {
			$update_data['translated_code'] = $data['translated_code'];
			$format[]                       = '%s';
		}
		if ( isset( $data['metadata'] ) ) {
			$update_data['metadata'] = wp_json_encode( $data['metadata'] );
			$format[]                = '%s';
		}
		if ( isset( $data['status'] ) ) {
			$update_data['status'] = sanitize_key( $data['status'] );
			$format[]              = '%s';
		}

		if ( empty( $update_data ) ) {
			return $id;
		}

		$result = $wpdb->update(
			$this->table_translations,
			$update_data,
			array( 'id' => $id, 'user_id' => $user_id ),
			$format,
			array( '%d', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to update translation.' );
		}

		return $id;
	}

	/**
	 * Delete a translation
	 *
	 * @param int $id Translation ID.
	 * @return bool|WP_Error True on success or error.
	 */
	public function delete_translation( int $id ): bool|WP_Error {
		global $wpdb;

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return new WP_Error( 'not_authenticated', 'User must be logged in.' );
		}

		// Delete associated corrections first
		$wpdb->delete(
			$this->table_corrections,
			array( 'translation_id' => $id ),
			array( '%d' )
		);

		// Delete the translation
		$result = $wpdb->delete(
			$this->table_translations,
			array( 'id' => $id, 'user_id' => $user_id ),
			array( '%d', '%d' )
		);

		if ( false === $result ) {
			return new WP_Error( 'db_error', 'Failed to delete translation.' );
		}

		if ( 0 === $result ) {
			return new WP_Error( 'not_found', 'Translation not found.' );
		}

		$this->logger->info( 'Translation deleted', array( 'translation_id' => $id ) );

		return true;
	}

	/**
	 * Get translation history for a user
	 *
	 * @param array $args Query arguments.
	 * @return array Array of translations.
	 */
	public function get_user_translations( array $args = array() ): array {
		global $wpdb;

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}

		$defaults = array(
			'page'             => 1,
			'per_page'         => 20,
			'status'           => null,
			'source_framework' => null,
			'target_framework' => null,
			'orderby'          => 'updated_at',
			'order'            => 'DESC',
		);

		$args = wp_parse_args( $args, $defaults );

		$where = array( "user_id = {$user_id}" );
		$where[] = 'parent_id IS NULL'; // Only get root translations, not versions

		if ( $args['status'] ) {
			$where[] = $wpdb->prepare( 'status = %s', $args['status'] );
		}
		if ( $args['source_framework'] ) {
			$where[] = $wpdb->prepare( 'source_framework = %s', $args['source_framework'] );
		}
		if ( $args['target_framework'] ) {
			$where[] = $wpdb->prepare( 'target_framework = %s', $args['target_framework'] );
		}

		$where_clause = implode( ' AND ', $where );
		$orderby      = sanitize_sql_orderby( "{$args['orderby']} {$args['order']}" ) ?: 'updated_at DESC';
		$offset       = ( (int) $args['page'] - 1 ) * (int) $args['per_page'];
		$limit        = (int) $args['per_page'];

		// Get total count
		$total = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$this->table_translations} WHERE {$where_clause}"
		);

		// Get translations (summary only, not full code)
		$translations = $wpdb->get_results(
			"SELECT id, name, source_framework, target_framework, version, status, created_at, updated_at
			 FROM {$this->table_translations}
			 WHERE {$where_clause}
			 ORDER BY {$orderby}
			 LIMIT {$limit} OFFSET {$offset}",
			ARRAY_A
		);

		return array(
			'translations' => $translations ?: array(),
			'total'        => $total,
			'page'         => (int) $args['page'],
			'per_page'     => $limit,
		);
	}

	/**
	 * Get version history for a translation
	 *
	 * @param int $translation_id Root translation ID.
	 * @return array Version history.
	 */
	public function get_versions( int $translation_id ): array {
		global $wpdb;

		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, version, created_at, updated_at
				 FROM {$this->table_translations}
				 WHERE (id = %d OR parent_id = %d) AND user_id = %d
				 ORDER BY version DESC",
				$translation_id,
				$translation_id,
				$user_id
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Restore a previous version
	 *
	 * @param int $translation_id Current translation ID.
	 * @param int $version_id     Version to restore.
	 * @return int|WP_Error New translation ID or error.
	 */
	public function restore_version( int $translation_id, int $version_id ): int|WP_Error {
		$version = $this->get_translation( $version_id );
		if ( is_wp_error( $version ) ) {
			return $version;
		}

		// Create a new version from the old one
		return $this->update_translation(
			$translation_id,
			array(
				'source_code'     => $version['source_code'],
				'translated_code' => $version['translated_code'],
				'metadata'        => $version['metadata'],
			),
			true
		);
	}

	/**
	 * Save user preferences
	 *
	 * @param array $preferences User preferences.
	 * @return bool True on success.
	 */
	public function save_preferences( array $preferences ): bool {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		return (bool) update_user_meta( $user_id, 'wpbc_preferences', $preferences );
	}

	/**
	 * Get user preferences
	 *
	 * @return array User preferences.
	 */
	public function get_preferences(): array {
		$user_id = get_current_user_id();
		if ( ! $user_id ) {
			return array();
		}

		$preferences = get_user_meta( $user_id, 'wpbc_preferences', true );
		return is_array( $preferences ) ? $preferences : array();
	}
}

<?php
/**
 * Plugin Scanner Class
 *
 * Handles scanning WordPress plugins for action and filter hooks,
 * storing a cached snapshot for the admin UI.
 *
 * @package NotificatorCompanion
 * @since 1.1.0
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Notificator_Companion_Plugin_Scanner
 *
 * Scans installed plugins for WordPress hooks and metadata.
 */
class Notificator_Companion_Plugin_Scanner {
	/**
	 * Resolved writable data directory for current request.
	 *
	 * @var string
	 */
	private $resolved_data_dir = '';

	/**
	 * Get initialized WP_Filesystem instance.
	 *
	 * @return WP_Filesystem_Base|null
	 */
	private function get_filesystem() {
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		if ( ! $wp_filesystem ) {
			WP_Filesystem();
		}
		return $wp_filesystem instanceof WP_Filesystem_Base ? $wp_filesystem : null;
	}

	/**
	 * Get the data directory path for storing scanned hooks
	 *
	 * @return string Absolute path to data directory.
	 */
	private function get_data_dir() {
		$uploads = wp_upload_dir();
		if ( is_array( $uploads ) && empty( $uploads['error'] ) && ! empty( $uploads['basedir'] ) ) {
			return trailingslashit( $uploads['basedir'] ) . 'notificator-companion';
		}

		if ( defined( 'NOTIFICATOR_COMPANION_PLUGIN_DIR' ) ) {
			return NOTIFICATOR_COMPANION_PLUGIN_DIR . '/data';
		}
		return plugin_dir_path( NOTIFICATOR_COMPANION_PLUGIN_FILE ) . 'data';
	}

	/**
	 * Get legacy data directory path (plugin folder).
	 *
	 * @return string Absolute path to legacy data directory.
	 */
	private function get_legacy_data_dir() {
		if ( defined( 'NOTIFICATOR_COMPANION_PLUGIN_DIR' ) ) {
			return NOTIFICATOR_COMPANION_PLUGIN_DIR . '/data';
		}
		return plugin_dir_path( NOTIFICATOR_COMPANION_PLUGIN_FILE ) . 'data';
	}

	/**
	 * Get the scanned hooks file path
	 *
	 * @return string Absolute path to scanned hooks JSON file.
	 */
	private function get_hooks_file_path() {
		$dir = '' !== $this->resolved_data_dir ? $this->resolved_data_dir : $this->get_data_dir();
		return $dir . '/scanned-hooks.json';
	}

	/**
	 * Get candidate data directories in priority order.
	 *
	 * @return array<int, string>
	 */
	private function get_data_dir_candidates() {
		$primary  = $this->get_data_dir();
		$fallback = $this->get_legacy_data_dir();

		$candidates = array( $primary );
		if ( $fallback !== $primary ) {
			$candidates[] = $fallback;
		}

		return array_values( array_filter( $candidates, 'is_string' ) );
	}

	/**
	 * Ensure data directory exists with proper permissions
	 *
	 * @return bool True if directory exists/created, false on failure.
	 */
	private function ensure_data_dir() {
		$fs  = $this->get_filesystem();
		if ( ! $fs ) {
			return false;
		}

		$legacy_file = trailingslashit( $this->get_legacy_data_dir() ) . 'scanned-hooks.json';

		foreach ( $this->get_data_dir_candidates() as $dir ) {
			if ( ! is_string( $dir ) || '' === $dir ) {
				continue;
			}

			if ( $fs->exists( $dir ) ) {
				if ( ! $fs->is_dir( $dir ) || ! $fs->is_writable( $dir ) ) {
					continue;
				}
			} elseif ( ! $fs->mkdir( $dir, FS_CHMOD_DIR ) ) {
				continue;
			}

			// Add .htaccess to prevent direct access.
			$htaccess = trailingslashit( $dir ) . '.htaccess';
			if ( ! $fs->exists( $htaccess ) ) {
				$fs->put_contents( $htaccess, "Deny from all\n", FS_CHMOD_FILE );
			}

			// Add index.php to prevent directory listing on some hosts.
			$index = trailingslashit( $dir ) . 'index.php';
			if ( ! $fs->exists( $index ) ) {
				$fs->put_contents( $index, "<?php\n// Silence is golden.\n", FS_CHMOD_FILE );
			}

			// Migrate legacy scanned hooks file if present.
			$new_file = trailingslashit( $dir ) . 'scanned-hooks.json';
			if ( $legacy_file !== $new_file && $fs->exists( $legacy_file ) && ! $fs->exists( $new_file ) ) {
				$fs->copy( $legacy_file, $new_file, true );
			}

			$this->resolved_data_dir = $dir;
			return true;
		}

		return false;
	}

	/**
	 * Scan a PHP file's contents for hooks and extract best-effort metadata.
	 *
	 * Notes:
	 * - Only detects hooks with a literal string hook name.
	 * - For *_ref_array variants, argument details cannot be reliably extracted.
	 *
	 * @param string $content PHP file contents.
	 * @param string $file_path Absolute file path.
	 * @return array Map of hook_name => hook_meta.
	 */
	private function scan_php_content_for_hooks( $content, $file_path ) {
		$hooks = array();

		if ( ! is_string( $content ) || '' === $content ) {
			return $hooks;
		}

		$tokens = token_get_all( $content );

		$token_count = count( $tokens );
		for ( $i = 0; $i < $token_count; $i++ ) {
			$token = $tokens[ $i ];
			if ( ! is_array( $token ) || T_STRING !== $token[0] ) {
				continue;
			}

			$fn = strtolower( $token[1] );
			$target = $this->resolve_hook_emitter_target( $fn );
			if ( null === $target ) {
				continue;
			}

			$line = isset( $token[2] ) ? (int) $token[2] : 0;

			// Find the opening parenthesis.
			$j = $i + 1;
			while ( $j < $token_count ) {
				$t = $tokens[ $j ];
				if ( is_array( $t ) && ( T_WHITESPACE === $t[0] || T_COMMENT === $t[0] || T_DOC_COMMENT === $t[0] ) ) {
					$j++;
					continue;
				}
				break;
			}
			if ( $j >= $token_count || '(' !== $tokens[ $j ] ) {
				continue;
			}

			// Parse arguments until the matching ')'.
			$args = array();
			$current = array();
			$depth = 1;
			$square_depth = 0;
			$curly_depth = 0;
			$j++;
			for ( ; $j < $token_count; $j++ ) {
				$t = $tokens[ $j ];
				$text = is_array( $t ) ? $t[1] : $t;

				if ( '(' === $text ) {
					$depth++;
					$current[] = $t;
					continue;
				}
				if ( '[' === $text ) {
					$square_depth++;
					$current[] = $t;
					continue;
				}
				if ( '{' === $text ) {
					$curly_depth++;
					$current[] = $t;
					continue;
				}
				if ( ')' === $text ) {
					$depth--;
					if ( 0 === $depth ) {
						// End of call.
						if ( ! empty( $current ) ) {
							$args[] = $current;
						}
						break;
					}
					$current[] = $t;
					continue;
				}
				if ( ']' === $text ) {
					if ( $square_depth > 0 ) {
						$square_depth--;
					}
					$current[] = $t;
					continue;
				}
				if ( '}' === $text ) {
					if ( $curly_depth > 0 ) {
						$curly_depth--;
					}
					$current[] = $t;
					continue;
				}

				// Split on commas only at top level of function args.
				if ( ',' === $text && 1 === $depth && 0 === $square_depth && 0 === $curly_depth ) {
					$args[] = $current;
					$current = array();
					continue;
				}

				$current[] = $t;
			}

			if ( empty( $args ) ) {
				continue;
			}

			// Support both literal strings and wrapper array syntax (e.g. gf_do_action(array('hook', $id), ...)).
			$hook_name = $this->extract_literal_hook_name_from_tokens( $args[0] );
			if ( '' === $hook_name ) {
				continue;
			}

			$payload_arity = null;
			$arg_names = array();
			$arg_mode = $target['mode'];
			if ( 'direct' === $arg_mode ) {
				$total_args = count( $args );
				$payload_arity = max( $total_args - 1, 0 );
				// Extract best-effort variable names for payload args (args after the hook name).
				for ( $ai = 1; $ai < $total_args; $ai++ ) {
					$arg_tokens = $args[ $ai ];
					$meaningful = array();
					foreach ( $arg_tokens as $at ) {
						if ( is_array( $at ) && ( T_WHITESPACE === $at[0] || T_COMMENT === $at[0] || T_DOC_COMMENT === $at[0] ) ) {
							continue;
						}
						$meaningful[] = $at;
					}
					
					// Try to extract a meaningful name.
					if ( empty( $meaningful ) ) {
						continue;
					}
					
					// Case 1: Single variable like $user_id.
					if ( 1 === count( $meaningful ) && is_array( $meaningful[0] ) && T_VARIABLE === $meaningful[0][0] ) {
						$var = ltrim( $meaningful[0][1], '$' );
						if ( '' !== $var ) {
							$arg_names[] = $var;
						}
					}
					// Case 2: Property access like $this->ID or $object->total.
					elseif ( count( $meaningful ) >= 3 ) {
						$first = $meaningful[0];
						$second = $meaningful[1];
						$third = isset( $meaningful[2] ) ? $meaningful[2] : null;
						
						// Check if it's object property access: $var->prop
						// The -> is stored as the string '->' in tokens (not T_OBJECT_OPERATOR constant).
						$is_object_access = ( is_array( $first ) && T_VARIABLE === $first[0] && 
											  '->' === $second && 
											  is_array( $third ) && T_STRING === $third[0] );
						
						// Also check for array access with string key: $var['key'].
						$is_array_access = ( is_array( $first ) && T_VARIABLE === $first[0] &&
											 '[' === $second &&
											 is_array( $third ) && T_CONSTANT_ENCAPSED_STRING === $third[0] );
						
						if ( $is_object_access ) {
							$var = ltrim( $first[1], '$' );
							$prop = $third[1];
							if ( '' !== $var && '' !== $prop ) {
								$arg_names[] = $var . '_' . $prop;
							}
						} elseif ( $is_array_access ) {
							$var = ltrim( $first[1], '$' );
							$key = trim( $third[1], "\"'" );
							if ( '' !== $var && '' !== $key ) {
								$arg_names[] = $var . '_' . $key;
							}
						} else {
							// Complex expression - use generic name.
							$arg_names[] = 'arg_' . $ai;
						}
					}
					// Case 3: Any other expression - use generic arg_N name.
					else {
						$arg_names[] = 'arg_' . $ai;
					}
				}
			} elseif ( 'registration' === $arg_mode ) {
				// add_action/add_filter register callbacks; runtime payload shape is unknown here.
				$payload_arity = null;
				$arg_names = array();
			} else {
				// For *_ref_array variants, argument info is not reliably extractable.
				$payload_arity = null;
				$arg_names = array();
			}

			// Store relative file path when possible.
			$relative_file = $file_path;
			if ( defined( 'WP_PLUGIN_DIR' ) && is_string( WP_PLUGIN_DIR ) ) {
				$relative_file = str_replace( trailingslashit( WP_PLUGIN_DIR ), '', $file_path );
			}

			$meta = array(
				'label'        => $target['label'],
				'type'         => $target['type'],
				'arg_mode'     => $arg_mode,
				'payload_arity'=> $payload_arity,
				'arg_names'    => array_values( array_unique( $arg_names ) ),
				'source'       => array(
					'file' => $relative_file,
					'line' => $line,
				),
			);

			if ( ! isset( $hooks[ $hook_name ] ) ) {
				$hooks[ $hook_name ] = $meta;
			} else {
				// Merge: keep the richest arg_names and the max payload_arity.
				$existing = $hooks[ $hook_name ];
				if ( isset( $existing['payload_arity'] ) && null !== $existing['payload_arity'] && null !== $payload_arity ) {
					$existing['payload_arity'] = max( (int) $existing['payload_arity'], (int) $payload_arity );
				} elseif ( null === $existing['payload_arity'] ) {
					$existing['payload_arity'] = $payload_arity;
				}
				$existing_names = isset( $existing['arg_names'] ) && is_array( $existing['arg_names'] ) ? $existing['arg_names'] : array();
				$existing['arg_names'] = array_values( array_unique( array_merge( $existing_names, $meta['arg_names'] ) ) );
				$hooks[ $hook_name ] = $existing;
			}
		}

		return $hooks;
	}

	/**
	 * Resolve function name to hook emitter metadata.
	 *
	 * This avoids relying on a rigid hardcoded list and allows discovery of
	 * plugin-prefixed wrapper functions such as gf_do_action()/gf_apply_filters().
	 *
	 * Filter: notificator_companion_scanner_hook_emitters
	 * - Key: function name (lowercase)
	 * - Value: array{type:string,label:string,mode:string}
	 *
	 * @param string $function_name Lowercase function name token.
	 * @return array|null
	 */
	private function resolve_hook_emitter_target( $function_name ) {
		$function_name = strtolower( (string) $function_name );
		if ( '' === $function_name ) {
			return null;
		}

		$emitters = array(
			'do_action'               => array( 'type' => 'action', 'label' => 'Action hook', 'mode' => 'direct' ),
			'do_action_ref_array'     => array( 'type' => 'action', 'label' => 'Action hook', 'mode' => 'ref_array' ),
			'add_action'              => array( 'type' => 'action', 'label' => 'Action hook', 'mode' => 'registration' ),
			'apply_filters'           => array( 'type' => 'filter', 'label' => 'Filter hook', 'mode' => 'direct' ),
			'apply_filters_ref_array' => array( 'type' => 'filter', 'label' => 'Filter hook', 'mode' => 'ref_array' ),
			'add_filter'              => array( 'type' => 'filter', 'label' => 'Filter hook', 'mode' => 'registration' ),
		);

		$emitters = apply_filters( 'notificator_companion_scanner_hook_emitters', $emitters );
		if ( is_array( $emitters ) && isset( $emitters[ $function_name ] ) && is_array( $emitters[ $function_name ] ) ) {
			$candidate = $emitters[ $function_name ];
			$type = isset( $candidate['type'] ) ? (string) $candidate['type'] : '';
			$mode = isset( $candidate['mode'] ) ? (string) $candidate['mode'] : '';
			$label = isset( $candidate['label'] ) ? (string) $candidate['label'] : '';

			if ( in_array( $type, array( 'action', 'filter' ), true ) && in_array( $mode, array( 'direct', 'ref_array', 'registration' ), true ) ) {
				return array(
					'type'  => $type,
					'mode'  => $mode,
					'label' => '' !== $label ? $label : ( 'filter' === $type ? 'Filter hook' : 'Action hook' ),
				);
			}
		}

		if ( preg_match( '/(?:^|_)add_action$/', $function_name ) ) {
			return array( 'type' => 'action', 'label' => 'Action hook', 'mode' => 'registration' );
		}

		if ( preg_match( '/(?:^|_)add_filter$/', $function_name ) ) {
			return array( 'type' => 'filter', 'label' => 'Filter hook', 'mode' => 'registration' );
		}

		// Generic support for prefixed wrappers: foo_do_action(), foo_apply_filters(), etc.
		if ( preg_match( '/(?:^|_)do_action_ref_array$/', $function_name ) ) {
			return array( 'type' => 'action', 'label' => 'Action hook', 'mode' => 'ref_array' );
		}

		if ( preg_match( '/(?:^|_)do_action$/', $function_name ) ) {
			return array( 'type' => 'action', 'label' => 'Action hook', 'mode' => 'direct' );
		}

		if ( preg_match( '/(?:^|_)apply_filters_ref_array$/', $function_name ) ) {
			return array( 'type' => 'filter', 'label' => 'Filter hook', 'mode' => 'ref_array' );
		}

		if ( preg_match( '/(?:^|_)apply_filters$/', $function_name ) ) {
			return array( 'type' => 'filter', 'label' => 'Filter hook', 'mode' => 'direct' );
		}

		return null;
	}

	/**
	 * Extract hook name from call first-argument tokens.
	 *
	 * Supports:
	 * - 'hook_name'
	 * - array( 'hook_name', $context )
	 * - [ 'hook_name', $context ]
	 *
	 * @param array $tokens Argument token list.
	 * @return string Hook name or empty string.
	 */
	private function extract_literal_hook_name_from_tokens( $tokens ) {
		if ( ! is_array( $tokens ) || empty( $tokens ) ) {
			return '';
		}

		$meaningful = array();
		foreach ( $tokens as $token ) {
			if ( is_array( $token ) && ( T_WHITESPACE === $token[0] || T_COMMENT === $token[0] || T_DOC_COMMENT === $token[0] ) ) {
				continue;
			}
			$meaningful[] = $token;
		}

		if ( empty( $meaningful ) ) {
			return '';
		}

		// Case 1: Plain literal string.
		if ( is_array( $meaningful[0] ) && T_CONSTANT_ENCAPSED_STRING === $meaningful[0][0] ) {
			$name = trim( $meaningful[0][1], "\"'" );
			return is_string( $name ) ? $name : '';
		}

		// Case 2: array( 'hook_name', ... ).
		if ( is_array( $meaningful[0] ) && T_ARRAY === $meaningful[0][0] ) {
			$open_index = -1;
			for ( $i = 1, $max = count( $meaningful ); $i < $max; $i++ ) {
				if ( '(' === $meaningful[ $i ] ) {
					$open_index = $i;
					break;
				}
			}
			if ( -1 !== $open_index ) {
				$first_item = $this->extract_first_array_item_tokens( $meaningful, $open_index, '(', ')' );
				return $this->extract_literal_hook_name_from_tokens( $first_item );
			}
		}

		// Case 3: [ 'hook_name', ... ].
		if ( '[' === $meaningful[0] ) {
			$first_item = $this->extract_first_array_item_tokens( $meaningful, 0, '[', ']' );
			return $this->extract_literal_hook_name_from_tokens( $first_item );
		}

		return '';
	}

	/**
	 * Extract first item token list from a PHP array expression.
	 *
	 * @param array  $tokens Token list containing an array expression.
	 * @param int    $open_index Index of opening bracket token.
	 * @param string $open_char Opening bracket character.
	 * @param string $close_char Closing bracket character.
	 * @return array Tokens for first array item (best effort).
	 */
	private function extract_first_array_item_tokens( $tokens, $open_index, $open_char, $close_char ) {
		$first = array();
		$depth = 0;
		$count = count( $tokens );

		for ( $i = $open_index + 1; $i < $count; $i++ ) {
			$t = $tokens[ $i ];
			$text = is_array( $t ) ? $t[1] : $t;

			if ( $open_char === $text ) {
				$depth++;
				$first[] = $t;
				continue;
			}

			if ( $close_char === $text ) {
				if ( 0 === $depth ) {
					break;
				}
				$depth--;
				$first[] = $t;
				continue;
			}

			if ( ',' === $text && 0 === $depth ) {
				break;
			}

			$first[] = $t;
		}

		return $first;
	}

	/**
	 * Scan a single plugin for hooks
	 *
	 * @param string $plugin_path Path to plugin directory.
	 * @param int    $limit Maximum number of hooks to find.
	 * @return array Array of hooks found.
	 */
	private function scan_plugin_for_hooks( $plugin_path, $limit = 100 ) {
		$hooks = array();

		if ( ! file_exists( $plugin_path ) ) {
			return $hooks;
		}

		// Build list of PHP files to scan.
		if ( is_file( $plugin_path ) ) {
			$files = array( new SplFileInfo( $plugin_path ) );
		} else {
			// Get all PHP files in the plugin directory.
			$files = new RecursiveIteratorIterator(
				new RecursiveDirectoryIterator( $plugin_path, RecursiveDirectoryIterator::SKIP_DOTS ),
				RecursiveIteratorIterator::SELF_FIRST
			);
		}

		foreach ( $files as $file ) {
			if ( $file->isFile() && 'php' === $file->getExtension() ) {
				$file_path = $file->getRealPath();
				$content   = file_get_contents( $file_path );
				$found     = $this->scan_php_content_for_hooks( $content, $file_path );
				foreach ( $found as $hook_name => $meta ) {
					if ( ! isset( $hooks[ $hook_name ] ) ) {
						$hooks[ $hook_name ] = $meta;
					} else {
						// Merge duplicates.
						$existing = $hooks[ $hook_name ];
						if ( is_array( $existing ) && is_array( $meta ) ) {
							$existing_names = isset( $existing['arg_names'] ) && is_array( $existing['arg_names'] ) ? $existing['arg_names'] : array();
							$new_names      = isset( $meta['arg_names'] ) && is_array( $meta['arg_names'] ) ? $meta['arg_names'] : array();
							$existing['arg_names'] = array_values( array_unique( array_merge( $existing_names, $new_names ) ) );
							if ( isset( $existing['payload_arity'] ) && null !== $existing['payload_arity'] && isset( $meta['payload_arity'] ) && null !== $meta['payload_arity'] ) {
								$existing['payload_arity'] = max( (int) $existing['payload_arity'], (int) $meta['payload_arity'] );
							} elseif ( ! isset( $existing['payload_arity'] ) || null === $existing['payload_arity'] ) {
								$existing['payload_arity'] = isset( $meta['payload_arity'] ) ? $meta['payload_arity'] : null;
							}
							$hooks[ $hook_name ] = $existing;
						} else {
							$hooks[ $hook_name ] = $meta;
						}
					}
				}

				// Stop if we've found enough hooks.
				if ( count( $hooks ) >= $limit ) {
					break;
				}
			}
		}

		// Sort hooks alphabetically.
		ksort( $hooks );

		return $hooks;
	}

	/**
	 * Get all installed plugins with their discovered hooks
	 *
	 * @return array Array of plugins with their hooks.
	 */
	public function get_available_plugins_with_hooks() {
		$stored_hooks = null;

		// Try to read from cache files, uploads first then plugin data fallback.
		foreach ( $this->get_data_dir_candidates() as $dir ) {
			$file_path = trailingslashit( $dir ) . 'scanned-hooks.json';
			if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
				continue;
			}

			$json = @file_get_contents( $file_path );
			if ( false === $json ) {
				continue;
			}

			$decoded = json_decode( $json, true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}

			$stored_hooks = $decoded;
			$compact_hooks = $this->compact_hooks_cache_payload( $decoded );
			if ( $compact_hooks !== $decoded ) {
				$this->save_hooks_to_file( $compact_hooks );
				$stored_hooks = $compact_hooks;
			}
			break;
		}

		// Fallback: migrate from old DB storage (one-time migration).
		if ( null === $stored_hooks ) {
			$db_hooks = get_option( 'notificator_companion_scanned_hooks', null );
			if ( null === $db_hooks ) {
				$db_hooks = get_option( 'authenticator_companion_scanned_hooks', null );
			}
			if ( null === $db_hooks ) {
				$db_hooks = get_option( 'uptime_monitor_scanned_hooks', null );
			}

			if ( ! empty( $db_hooks ) && is_array( $db_hooks ) ) {
				// Migrate to file.
				$this->save_hooks_to_file( $db_hooks );
				// Clean up old DB entries.
				delete_option( 'notificator_companion_scanned_hooks' );
				delete_option( 'authenticator_companion_scanned_hooks' );
				delete_option( 'uptime_monitor_scanned_hooks' );
				$stored_hooks = $db_hooks;
			}
		}

		// Always refresh the WordPress Core hook list from code.
		// The stored file can contain an older snapshot (e.g. the original 10 hooks).
		$core_hooks = $this->get_wordpress_core_hooks();
		if ( isset( $core_hooks['wordpress-core'] ) && is_array( $core_hooks['wordpress-core'] ) ) {
			if ( ! is_array( $stored_hooks ) ) {
				$stored_hooks = array();
			}
			$stored_hooks['wordpress-core'] = $core_hooks['wordpress-core'];
		}

		// If still no data (shouldn't happen), return WordPress Core only.
		if ( empty( $stored_hooks ) ) {
			return $this->get_wordpress_core_hooks();
		}

		// Return all discovered hooks (actions and filters).
		return $stored_hooks;
	}

	/**
	 * Save hooks data to file
	 *
	 * @param array $hooks_data The hooks data to save.
	 * @return bool True on success, false on failure.
	 */
	private function save_hooks_to_file( $hooks_data ) {
		if ( ! $this->ensure_data_dir() ) {
			return false;
		}

		$fs = $this->get_filesystem();
		if ( ! $fs ) {
			return false;
		}

		$file_path = $this->get_hooks_file_path();
		$hooks_data = $this->compact_hooks_cache_payload( $hooks_data );
		$json = wp_json_encode( $hooks_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return false;
		}

		$temp_file = $file_path . '.tmp';
		$result = $fs->put_contents( $temp_file, $json, FS_CHMOD_FILE );
		if ( false === $result ) {
			return false;
		}

		// Atomic rename.
		return $fs->move( $temp_file, $file_path, true );
	}

	/**
	 * Compact scan cache payload to keep scanned-hooks.json small.
	 *
	 * Removes metadata not currently used by the admin UI (e.g. source file/line)
	 * and keeps only the keys required by rendering and conditions.
	 *
	 * @param array $hooks_data Raw scan payload.
	 * @return array
	 */
	private function compact_hooks_cache_payload( $hooks_data ) {
		if ( ! is_array( $hooks_data ) ) {
			return array();
		}

		$compact = array();

		foreach ( $hooks_data as $plugin_key => $plugin_data ) {
			if ( ! is_string( $plugin_key ) || ! is_array( $plugin_data ) ) {
				continue;
			}

			$entry = array(
				'name'   => isset( $plugin_data['name'] ) ? (string) $plugin_data['name'] : '',
				'file'   => isset( $plugin_data['file'] ) ? (string) $plugin_data['file'] : '',
				'icon'   => isset( $plugin_data['icon'] ) ? (string) $plugin_data['icon'] : '',
				'color'  => isset( $plugin_data['color'] ) ? (string) $plugin_data['color'] : 'blue',
				'active' => isset( $plugin_data['active'] ) ? (bool) $plugin_data['active'] : false,
				'hooks'  => array(),
			);

			if ( isset( $plugin_data['hooks'] ) && is_array( $plugin_data['hooks'] ) ) {
				foreach ( $plugin_data['hooks'] as $hook_name => $hook_meta ) {
					if ( ! is_string( $hook_name ) || '' === $hook_name ) {
						continue;
					}

					if ( is_string( $hook_meta ) ) {
						$entry['hooks'][ $hook_name ] = $hook_meta;
						continue;
					}

					if ( ! is_array( $hook_meta ) ) {
						continue;
					}

					$meta = array();
					if ( isset( $hook_meta['label'] ) && is_string( $hook_meta['label'] ) ) {
						$meta['label'] = $hook_meta['label'];
					}
					if ( isset( $hook_meta['type'] ) && is_string( $hook_meta['type'] ) ) {
						$meta['type'] = $hook_meta['type'];
					}
					if ( isset( $hook_meta['arg_mode'] ) && is_string( $hook_meta['arg_mode'] ) ) {
						$meta['arg_mode'] = $hook_meta['arg_mode'];
					}
					if ( array_key_exists( 'payload_arity', $hook_meta ) ) {
						$meta['payload_arity'] = is_numeric( $hook_meta['payload_arity'] ) ? (int) $hook_meta['payload_arity'] : null;
					}
					if ( isset( $hook_meta['arg_names'] ) && is_array( $hook_meta['arg_names'] ) ) {
						$meta['arg_names'] = array_values(
							array_filter(
								array_map(
									'strval',
									$hook_meta['arg_names']
								),
								'strlen'
							)
						);
					}
					if ( isset( $hook_meta['properties'] ) && is_array( $hook_meta['properties'] ) ) {
						$meta['properties'] = $hook_meta['properties'];
					}

					$entry['hooks'][ $hook_name ] = $meta;
				}
			}

			$compact[ $plugin_key ] = $entry;
		}

		return $compact;
	}

	/**
	 * Get WordPress Core hooks
	 *
	 * @return array Array with WordPress Core hooks.
	 */
	private function get_wordpress_core_hooks() {
		return array(
			'wordpress-core' => array(
				'name'   => 'WordPress Core',
				'file'   => '',
				'icon'   => '⚙️',
				'color'  => 'gray',
				'active' => true,
				'hooks'  => array(
					// Users & auth.
					'user_register'      => __( 'New user registration', 'notificator-companion' ),
					'wp_login'           => __( 'User login', 'notificator-companion' ),
					'wp_login_failed'    => __( 'Failed login attempt', 'notificator-companion' ),
					'retrieve_password'  => __( 'Password reset requested', 'notificator-companion' ),
					'password_reset'     => __( 'Password reset completed', 'notificator-companion' ),
					'delete_user'        => __( 'User deleted', 'notificator-companion' ),
					'profile_update'     => __( 'User profile updated', 'notificator-companion' ),

					// Content.
					'publish_post'       => __( 'Post published', 'notificator-companion' ),
					'wp_insert_post'     => __( 'Post created/updated', 'notificator-companion' ),
					'wp_trash_post'      => __( 'Post trashed', 'notificator-companion' ),
					'untrash_post'       => __( 'Post restored from trash', 'notificator-companion' ),
					'delete_post'        => __( 'Post deleted', 'notificator-companion' ),
					'comment_post'       => __( 'New comment posted', 'notificator-companion' ),
					'edit_comment'       => __( 'Comment edited', 'notificator-companion' ),
					'wp_set_comment_status' => __( 'Comment status changed', 'notificator-companion' ),
					'add_attachment'     => __( 'Media uploaded', 'notificator-companion' ),
					'delete_attachment'  => __( 'Media deleted', 'notificator-companion' ),

					// Site & settings.
					'switch_theme'       => __( 'Theme changed', 'notificator-companion' ),
					'customize_save_after' => __( 'Customizer settings saved', 'notificator-companion' ),
					'update_option'      => __( 'Option updated', 'notificator-companion' ),
					'added_option'       => __( 'Option added', 'notificator-companion' ),
					'deleted_option'     => __( 'Option deleted', 'notificator-companion' ),

					// Updates.
					'upgrader_process_complete' => __( 'Update completed (plugin/theme/core)', 'notificator-companion' ),
					'automatic_updates_complete' => __( 'Automatic updates completed', 'notificator-companion' ),

					// Plugins.
					'activated_plugin'   => __( 'Plugin activated', 'notificator-companion' ),
					'deactivated_plugin' => __( 'Plugin deactivated', 'notificator-companion' ),
				),
			),
		);
	}

	/**
	 * Scan plugins for hooks and store results
	 *
	 * By default, scans active plugins only (plus WordPress Core).
	 *
	 * @param bool $include_inactive Whether to also scan inactive plugins.
	 * @param int  $hook_limit Max hooks to scan per plugin.
	 * @return array Array with scan results.
	 */
	public function scan_plugins_for_hooks( $include_inactive = false, $hook_limit = 500 ) {
		$hook_limit = (int) $hook_limit;
		if ( $hook_limit < 50 ) {
			$hook_limit = 50;
		} elseif ( $hook_limit > 5000 ) {
			$hook_limit = 5000;
		}

		// Start with WordPress Core.
		$available_plugins = $this->get_wordpress_core_hooks();

		// Get all plugins.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );

		$plugins_found = 0;
		$hooks_found   = count( $available_plugins['wordpress-core']['hooks'] );

		foreach ( $all_plugins as $plugin_file => $plugin_data ) {
			// Skip this plugin itself.
			if ( plugin_basename( NOTIFICATOR_COMPANION_PLUGIN_FILE ) === $plugin_file ) {
				continue;
			}

			$plugin_name = $plugin_data['Name'];
			$plugin_dir  = dirname( $plugin_file );
			$plugin_path = ( '.' === $plugin_dir ) ? WP_PLUGIN_DIR . '/' . $plugin_file : WP_PLUGIN_DIR . '/' . $plugin_dir;
			$is_active   = in_array( $plugin_file, $active_plugins, true );

			if ( ! $include_inactive && ! $is_active ) {
				continue;
			}

			// Scan the plugin for hooks.
			$discovered_hooks = $this->scan_plugin_for_hooks( $plugin_path, $hook_limit );

			if ( ! empty( $discovered_hooks ) ) {
				$plugin_slug = dirname( $plugin_file );
				if ( '.' === $plugin_slug || empty( $plugin_slug ) ) {
					$plugin_slug = sanitize_title( basename( $plugin_file, '.php' ) );
				}

				$plugin_key                        = $plugin_slug;
				$available_plugins[ $plugin_key ]  = array(
					'name'   => $plugin_name,
					'file'   => $plugin_file,
					'slug'   => $plugin_slug,
					'icon'   => $this->get_plugin_icon( $plugin_name ),
					'color'  => 'blue',
					'active' => $is_active,
					'hooks'  => $discovered_hooks,
				);

				$plugins_found++;
				$hooks_found += count( $discovered_hooks );
			}
		}

		// Store the scanned hooks to file.
		$saved = $this->save_hooks_to_file( $available_plugins );
		if ( ! $saved ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to save scanned hooks. Check file permissions.', 'notificator-companion' ),
			);
		}

		// Store last scan timestamp in DB (lightweight).
		update_option( 'notificator_companion_last_scan', time(), false );

		return array(
			'success'       => true,
			'plugins_found' => $plugins_found,
			'hooks_found'   => $hooks_found,
		);
	}

	/**
	 * Get plugin icon emoji based on plugin name
	 *
	 * @param string $plugin_name Plugin name.
	 * @return string Icon emoji.
	 */
	private function get_plugin_icon( $plugin_name ) {
		$plugin_lower = strtolower( $plugin_name );

		$icon_map = array(
			'woocommerce'   => '🛒',
			'jetpack'       => '✈️',
			'yoast'         => '📊',
			'elementor'     => '🎨',
			'contact'       => '📧',
			'form'          => '📝',
			'security'      => '🔒',
			'backup'        => '💾',
			'cache'         => '⚡',
			'seo'           => '🔍',
			'analytics'     => '📈',
			'social'        => '👥',
			'gallery'       => '🖼️',
			'slider'        => '🎞️',
			'wordpress'     => '⚙️',
			'wpml'          => '🌍',
			'polylang'      => '🌐',
			'buddypress'    => '👫',
			'bbpress'       => '💬',
			'mailchimp'     => '📬',
			'stripe'        => '💳',
			'paypal'        => '💰',
			'membership'    => '👤',
			'learndash'     => '🎓',
			'event'         => '📅',
			'booking'       => '📆',
			'calendar'      => '🗓️',
			'shop'          => '🏪',
			'payment'       => '💵',
			'shipping'      => '📦',
		);

		foreach ( $icon_map as $keyword => $icon ) {
			if ( false !== strpos( $plugin_lower, $keyword ) ) {
				return $icon;
			}
		}

		return '🔌'; // Default plugin icon.
	}
}

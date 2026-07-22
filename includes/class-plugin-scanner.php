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
	const CACHE_SCHEMA_VERSION = 4;
	/**
	 * Resolved writable data directory for current request.
	 *
	 * @var string
	 */
	private $resolved_data_dir = '';

	/**
	 * Remove cached discovery results so the next scan starts from scratch.
	 *
	 * @return bool True when no cache files remain.
	 */
	public function clear_cache() {
		$fs      = $this->get_filesystem();
		$cleared = true;
		foreach ( $this->get_data_dir_candidates() as $dir ) {
			foreach ( array( 'scanned-hooks.json', 'scanned-hooks.json.tmp' ) as $filename ) {
				$path = trailingslashit( $dir ) . $filename;
				if ( $fs && $fs->exists( $path ) && ! $fs->delete( $path ) ) {
					$cleared = false;
				} elseif ( ! $fs && file_exists( $path ) ) {
					wp_delete_file( $path );
					if ( file_exists( $path ) ) {
						$cleared = false;
					}
				}
			}
			foreach ( (array) glob( trailingslashit( $dir ) . 'scanned-hooks-working-*' ) as $working_file ) {
				if ( $fs && $fs->exists( $working_file ) && ! $fs->delete( $working_file ) ) {
					$cleared = false;
				} elseif ( ! $fs && file_exists( $working_file ) ) {
					wp_delete_file( $working_file );
					if ( file_exists( $working_file ) ) {
						$cleared = false;
					}
				}
			}
		}
		delete_option( 'notificator_companion_scanned_hooks' );
		delete_option( 'authenticator_companion_scanned_hooks' );
		delete_option( 'uptime_monitor_scanned_hooks' );
		$this->resolved_data_dir = '';
		return $cleared;
	}

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
			return trailingslashit( $uploads['basedir'] ) . 'notificator';
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
	 * Get the temporary file used by a resumable background scan.
	 *
	 * @param string $scan_id Unique scan identifier.
	 * @return string Absolute path to the working scan file.
	 */
	private function get_scan_working_file_path( $scan_id ) {
		$scan_id = preg_replace( '/[^a-zA-Z0-9_-]/', '', (string) $scan_id );
		$dir     = '' !== $this->resolved_data_dir ? $this->resolved_data_dir : $this->get_data_dir();
		return $dir . '/scanned-hooks-working-' . $scan_id . '.json';
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
		$fs = $this->get_filesystem();
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

			$fn     = strtolower( $token[1] );
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
					++$j;
					continue;
				}
				break;
			}
			if ( $j >= $token_count || '(' !== $tokens[ $j ] ) {
				continue;
			}

			// Parse arguments until the matching ')'.
			$args         = array();
			$current      = array();
			$depth        = 1;
			$square_depth = 0;
			$curly_depth  = 0;
			++$j;
			for ( ; $j < $token_count; $j++ ) {
				$t    = $tokens[ $j ];
				$text = is_array( $t ) ? $t[1] : $t;

				if ( '(' === $text ) {
					++$depth;
					$current[] = $t;
					continue;
				}
				if ( '[' === $text ) {
					++$square_depth;
					$current[] = $t;
					continue;
				}
				if ( '{' === $text ) {
					++$curly_depth;
					$current[] = $t;
					continue;
				}
				if ( ')' === $text ) {
					--$depth;
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
						--$square_depth;
					}
					$current[] = $t;
					continue;
				}
				if ( '}' === $text ) {
					if ( $curly_depth > 0 ) {
						--$curly_depth;
					}
					$current[] = $t;
					continue;
				}

				// Split on commas only at top level of function args.
				if ( ',' === $text && 1 === $depth && 0 === $square_depth && 0 === $curly_depth ) {
					$args[]  = $current;
					$current = array();
					continue;
				}

				$current[] = $t;
			}

			if ( empty( $args ) ) {
				continue;
			}

			// Support both literal strings and wrapper array syntax (e.g. gf_do_action(array('hook', $id), ...)).
			$hook_name  = $this->extract_literal_hook_name_from_tokens( $args[0] );
			$is_dynamic = false;
			if ( '' === $hook_name ) {
				$hook_name  = $this->extract_dynamic_hook_pattern_from_tokens( $args[0] );
				$is_dynamic = '' !== $hook_name;
			}
			if ( '' === $hook_name ) {
				continue;
			}

			$payload_arity = null;
			$arg_names     = array();
			$arg_mode      = $target['mode'];
			if ( 'direct' === $arg_mode ) {
				$total_args    = count( $args );
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
					} elseif ( count( $meaningful ) >= 3 ) {
						// Case 2: Property access like $this->ID or $object->total.
						$first  = $meaningful[0];
						$second = $meaningful[1];
						$third  = isset( $meaningful[2] ) ? $meaningful[2] : null;

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
							$var  = ltrim( $first[1], '$' );
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
					} else {
						// Case 3: Any other expression - use generic arg_N name.
						$arg_names[] = 'arg_' . $ai;
					}
				}
			} elseif ( 'registration' === $arg_mode ) {
				// add_action/add_filter register callbacks; runtime payload shape is unknown here.
				$payload_arity = null;
				$arg_names     = array();
			} else {
				// For *_ref_array variants, argument info is not reliably extractable.
				$payload_arity = null;
				$arg_names     = array();
			}

			// Store relative file path when possible.
			$relative_file = $file_path;
			if ( defined( 'WP_PLUGIN_DIR' ) && is_string( WP_PLUGIN_DIR ) ) {
				$relative_file = str_replace( trailingslashit( WP_PLUGIN_DIR ), '', $file_path );
			}

			$meta = array(
				'label'         => $target['label'],
				'type'          => $target['type'],
				'arg_mode'      => $arg_mode,
				'payload_arity' => $payload_arity,
				'arg_names'     => array_values( array_unique( $arg_names ) ),
				'source'        => array(
					'file' => $relative_file,
					'line' => $line,
				),
				'dynamic'       => $is_dynamic,
				'selectable'    => ! $is_dynamic,
				'discovery'     => 'registration' === $arg_mode ? 'registration' : 'emission',
			);
			$meta = $this->score_hook_candidate( $hook_name, $meta );

			if ( ! isset( $hooks[ $hook_name ] ) ) {
				$hooks[ $hook_name ] = $meta;
			} else {
				$hooks[ $hook_name ] = $this->merge_hook_candidate( $hook_name, $hooks[ $hook_name ], $meta );
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
			'do_action'               => array(
				'type'  => 'action',
				'label' => 'Action hook',
				'mode'  => 'direct',
			),
			'do_action_ref_array'     => array(
				'type'  => 'action',
				'label' => 'Action hook',
				'mode'  => 'ref_array',
			),
			'add_action'              => array(
				'type'  => 'action',
				'label' => 'Action hook',
				'mode'  => 'registration',
			),
			'apply_filters'           => array(
				'type'  => 'filter',
				'label' => 'Filter hook',
				'mode'  => 'direct',
			),
			'apply_filters_ref_array' => array(
				'type'  => 'filter',
				'label' => 'Filter hook',
				'mode'  => 'ref_array',
			),
			'add_filter'              => array(
				'type'  => 'filter',
				'label' => 'Filter hook',
				'mode'  => 'registration',
			),
		);

		$emitters = apply_filters( 'notificator_companion_scanner_hook_emitters', $emitters );
		if ( is_array( $emitters ) && isset( $emitters[ $function_name ] ) && is_array( $emitters[ $function_name ] ) ) {
			$candidate = $emitters[ $function_name ];
			$type      = isset( $candidate['type'] ) ? (string) $candidate['type'] : '';
			$mode      = isset( $candidate['mode'] ) ? (string) $candidate['mode'] : '';
			$label     = isset( $candidate['label'] ) ? (string) $candidate['label'] : '';

			if ( in_array( $type, array( 'action', 'filter' ), true ) && in_array( $mode, array( 'direct', 'ref_array', 'registration' ), true ) ) {
				return array(
					'type'  => $type,
					'mode'  => $mode,
					'label' => '' !== $label ? $label : ( 'filter' === $type ? 'Filter hook' : 'Action hook' ),
				);
			}
		}

		if ( preg_match( '/(?:^|_)add_action$/', $function_name ) ) {
			return array(
				'type'  => 'action',
				'label' => 'Action hook',
				'mode'  => 'registration',
			);
		}

		if ( preg_match( '/(?:^|_)add_filter$/', $function_name ) ) {
			return array(
				'type'  => 'filter',
				'label' => 'Filter hook',
				'mode'  => 'registration',
			);
		}

		// Generic support for prefixed wrappers: foo_do_action(), foo_apply_filters(), etc.
		if ( preg_match( '/(?:^|_)do_action_ref_array$/', $function_name ) ) {
			return array(
				'type'  => 'action',
				'label' => 'Action hook',
				'mode'  => 'ref_array',
			);
		}

		if ( preg_match( '/(?:^|_)do_action$/', $function_name ) ) {
			return array(
				'type'  => 'action',
				'label' => 'Action hook',
				'mode'  => 'direct',
			);
		}

		if ( preg_match( '/(?:^|_)apply_filters_ref_array$/', $function_name ) ) {
			return array(
				'type'  => 'filter',
				'label' => 'Filter hook',
				'mode'  => 'ref_array',
			);
		}

		if ( preg_match( '/(?:^|_)apply_filters$/', $function_name ) ) {
			return array(
				'type'  => 'filter',
				'label' => 'Filter hook',
				'mode'  => 'direct',
			);
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
		if ( 1 === count( $meaningful ) && is_array( $meaningful[0] ) && T_CONSTANT_ENCAPSED_STRING === $meaningful[0][0] ) {
			$name = trim( $meaningful[0][1], "\"'" );
			return is_string( $name ) ? $name : '';
		}

		// Case 2: A classic array whose first item is a literal hook name.
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

		// Case 3: A short array whose first item is a literal hook name.
		if ( '[' === $meaningful[0] ) {
			$first_item = $this->extract_first_array_item_tokens( $meaningful, 0, '[', ']' );
			return $this->extract_literal_hook_name_from_tokens( $first_item );
		}

		return '';
	}

	/**
	 * Convert a concatenated hook expression into a safe display-only pattern.
	 * Example: 'order_' . $status becomes order_{status}.
	 *
	 * @param array $tokens First argument tokens.
	 * @return string
	 */
	private function extract_dynamic_hook_pattern_from_tokens( $tokens ) {
		$parts       = array();
		$has_literal = false;
		$has_dynamic = false;
		foreach ( (array) $tokens as $token ) {
			if ( is_array( $token ) && in_array( $token[0], array( T_WHITESPACE, T_COMMENT, T_DOC_COMMENT ), true ) ) {
				continue;
			}
			if ( is_array( $token ) && T_CONSTANT_ENCAPSED_STRING === $token[0] ) {
				$parts[]     = trim( $token[1], "\"'" );
				$has_literal = true;
			} elseif ( is_array( $token ) && T_VARIABLE === $token[0] ) {
				$parts[]     = '{' . sanitize_key( ltrim( $token[1], '$' ) ) . '}';
				$has_dynamic = true;
			} elseif ( '.' === $token ) {
				continue;
			} elseif ( is_array( $token ) && in_array( $token[0], array( T_STRING, T_ENCAPSED_AND_WHITESPACE ), true ) ) {
				$parts[]     = '{value}';
				$has_dynamic = true;
			}
		}
		$pattern = implode( '', $parts );
		return $has_literal && $has_dynamic && strlen( $pattern ) <= 190 ? $pattern : '';
	}

	/**
	 * Add confidence, usefulness, risk, and an explanation to a candidate.
	 *
	 * @param string $hook_name Hook name or dynamic pattern.
	 * @param array  $meta Candidate metadata.
	 * @return array
	 */
	private function score_hook_candidate( $hook_name, $meta ) {
		if ( empty( $meta['label'] ) || in_array( $meta['label'], array( 'Action hook', 'Filter hook' ), true ) ) {
			$human_label   = $this->humanize_identifier( str_replace( array( '{', '}' ), '', $hook_name ) );
			$meta['label'] = '' !== $human_label ? $human_label : $hook_name;
		}
		$score   = 'registration' === $meta['arg_mode'] ? 20 : 70;
		$reasons = array( 'registration' === $meta['arg_mode'] ? 'Found as a listener registration' : 'Emitted by plugin code' );
		$risk    = 'normal';
		if ( 'action' === $meta['type'] ) {
			$score    += 8;
			$reasons[] = 'Action hooks are notification-safe';
		}
		if ( ! empty( $meta['arg_names'] ) ) {
			$score    += min( 10, count( $meta['arg_names'] ) * 2 );
			$reasons[] = 'Payload fields were identified';
		}
		if ( preg_match( '/(?:created|completed|failed|deleted|published|paid|refunded|cancelled|submitted|activated|deactivated)/i', $hook_name ) ) {
			$score    += 14;
			$reasons[] = 'Name describes a meaningful state change';
		}
		if ( preg_match( '/(?:^|_)(init|render|display|enqueue|query|loop|before|after|request|ajax)(?:_|$)/i', $hook_name ) ) {
			$score    -= 24;
			$risk      = 'potentially_noisy';
			$reasons[] = 'May execute frequently';
		}
		if ( ! empty( $meta['dynamic'] ) ) {
			$score     = min( $score, 40 );
			$risk      = 'dynamic';
			$reasons[] = 'Dynamic name requires a concrete runtime value';
		}
		$score               = max( 0, min( 100, $score ) );
		$meta['score']       = $score;
		$meta['confidence']  = $score >= 75 ? 'high' : ( $score >= 45 ? 'medium' : 'low' );
		$meta['risk']        = $risk;
		$meta['recommended'] = $score >= 65 && 'registration' !== $meta['arg_mode'] && empty( $meta['dynamic'] );
		$meta['reason']      = implode( '. ', array_unique( $reasons ) ) . '.';
		$meta['description'] = $this->build_plain_language_description( $hook_name, $meta );
		return $meta;
	}

	/**
	 * Convert code-style identifiers into readable labels with common acronyms.
	 *
	 * @param string $value Identifier to humanize.
	 * @return string Human-readable label.
	 */
	private function humanize_identifier( $value ) {
		$value = ucwords( trim( preg_replace( '/\s+/', ' ', str_replace( array( '_', '-' ), ' ', (string) $value ) ) ) );
		$value = str_ireplace( array( 'Woocommerce', 'Wordpress' ), array( 'WooCommerce', 'WordPress' ), $value );
		return preg_replace_callback(
			'/\b(?:id|url|api|ip|html|css|json|xml|mqtt)\b/i',
			static function ( $matches ) {
				return strtoupper( $matches[0] ); },
			$value
		);
	}

	/**
	 * Explain a discovered hook without requiring WordPress development knowledge.
	 *
	 * @param string $hook_name Hook name.
	 * @param array  $meta Hook metadata.
	 * @return string
	 */
	private function build_plain_language_description( $hook_name, $meta ) {
		$label     = isset( $meta['label'] ) && is_string( $meta['label'] ) && '' !== trim( $meta['label'] )
			? trim( $meta['label'] )
			: $this->humanize_identifier( $hook_name );
		$discovery = isset( $meta['discovery'] ) ? (string) $meta['discovery'] : (string) ( $meta['arg_mode'] ?? '' );

		if ( ! empty( $meta['dynamic'] ) ) {
			/* translators: %s: Human-readable event name. */
			$description = sprintf( __( 'Represents a family of “%s” events. The exact event name is decided while the plugin is running.', 'notificator-project' ), $label );
		} elseif ( 'registration' === $discovery ) {
			/* translators: %s: Human-readable event name. */
			$description = sprintf( __( 'The plugin listens for “%s”, but the scanner did not find where that event starts. Test it before relying on it.', 'notificator-project' ), $label );
		} elseif ( 'filter' === ( $meta['type'] ?? 'action' ) ) {
			/* translators: %s: Human-readable event name. */
			$description = sprintf( __( 'Runs while “%s” is being processed. It can inspect or change information before the plugin continues.', 'notificator-project' ), $label );
		} else {
			/* translators: %s: Human-readable event name. */
			$description = sprintf( __( 'Triggered when “%s” happens. Use it to receive a notification when this event occurs.', 'notificator-project' ), $label );
		}

		$arg_names = isset( $meta['arg_names'] ) && is_array( $meta['arg_names'] ) ? array_slice( $meta['arg_names'], 0, 3 ) : array();
		if ( ! empty( $arg_names ) ) {
			$details = array_map( array( $this, 'humanize_identifier' ), $arg_names );
			/* translators: %s: Comma-separated list of details supplied by the event. */
			$description .= ' ' . sprintf( __( 'Available details include %s.', 'notificator-project' ), implode( ', ', $details ) );
		}

		return $description;
	}

	/**
	 * Merge duplicate discoveries, preferring real emissions over registrations.
	 *
	 * @param string $hook_name Hook name.
	 * @param array  $existing Existing metadata.
	 * @param array  $incoming Incoming metadata.
	 * @return array
	 */
	private function merge_hook_candidate( $hook_name, $existing, $incoming ) {
		if ( 'registration' === ( $existing['arg_mode'] ?? '' ) && 'registration' !== ( $incoming['arg_mode'] ?? '' ) ) {
			$preferred = $incoming;
			$secondary = $existing;
		} else {
			$preferred = $existing;
			$secondary = $incoming;
		}
		$preferred_names        = isset( $preferred['arg_names'] ) && is_array( $preferred['arg_names'] ) ? $preferred['arg_names'] : array();
		$secondary_names        = isset( $secondary['arg_names'] ) && is_array( $secondary['arg_names'] ) ? $secondary['arg_names'] : array();
		$preferred['arg_names'] = array_values( array_unique( array_merge( $preferred_names, $secondary_names ) ) );
		$preferred_arity        = $preferred['payload_arity'] ?? null;
		$secondary_arity        = $secondary['payload_arity'] ?? null;
		if ( null !== $secondary_arity ) {
			$preferred['payload_arity'] = null === $preferred_arity ? (int) $secondary_arity : max( (int) $preferred_arity, (int) $secondary_arity );
		}
		return $this->score_hook_candidate( $hook_name, $preferred );
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
			$t    = $tokens[ $i ];
			$text = is_array( $t ) ? $t[1] : $t;

			if ( $open_char === $text ) {
				++$depth;
				$first[] = $t;
				continue;
			}

			if ( $close_char === $text ) {
				if ( 0 === $depth ) {
					break;
				}
				--$depth;
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
		$hooks       = array();
		$file_limit  = $this->get_scan_file_limit( $plugin_path );
		$time_budget = (float) apply_filters( 'notificator_companion_scan_plugin_time_budget', 12.0, $plugin_path );
		$time_budget = max( 3.0, min( 30.0, $time_budget ) );
		$started_at  = microtime( true );

		if ( ! file_exists( $plugin_path ) ) {
			return $hooks;
		}

		// Build list of PHP files to scan.
		if ( is_file( $plugin_path ) ) {
			$files = array( new SplFileInfo( $plugin_path ) );
		} else {
			$excluded  = array( 'vendor', 'node_modules', 'tests', 'test', 'cache', 'build', 'dist', 'coverage', '.git', 'languages' );
			$directory = new RecursiveDirectoryIterator( $plugin_path, RecursiveDirectoryIterator::SKIP_DOTS );
			$filtered  = new RecursiveCallbackFilterIterator(
				$directory,
				static function ( $current ) use ( $excluded ) {
					return ! $current->isDir() || ! in_array( strtolower( $current->getFilename() ), $excluded, true );
				}
			);
			$files     = new RecursiveIteratorIterator( $filtered, RecursiveIteratorIterator::LEAVES_ONLY );
		}

		$file_list = array();
		foreach ( $files as $file ) {
			if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) && $file->getSize() <= 2 * MB_IN_BYTES ) {
				$file_list[] = $file->getRealPath();
			}
			if ( count( $file_list ) >= $file_limit ) {
				break;
			}
		}
		sort( $file_list, SORT_NATURAL | SORT_FLAG_CASE );

		$fs = $this->get_filesystem();
		foreach ( $file_list as $file_path ) {
			if ( microtime( true ) - $started_at >= $time_budget ) {
				break;
			}
			if ( $fs && is_string( $file_path ) && is_readable( $file_path ) ) {
				$content = $fs->get_contents( $file_path );
				if ( false === $content ) {
					continue;
				}
				$found = $this->scan_php_content_for_hooks( $content, $file_path );
				foreach ( $found as $hook_name => $meta ) {
					if ( ! isset( $hooks[ $hook_name ] ) ) {
						$hooks[ $hook_name ] = $meta;
					} else {
						$hooks[ $hook_name ] = is_array( $hooks[ $hook_name ] ) && is_array( $meta )
							? $this->merge_hook_candidate( $hook_name, $hooks[ $hook_name ], $meta )
							: $meta;
					}
				}
			}
		}

		// Rank before truncation so filesystem order cannot decide which hooks survive.
		uasort(
			$hooks,
			static function ( $left, $right ) {
				$score_compare = (int) ( $right['score'] ?? 0 ) <=> (int) ( $left['score'] ?? 0 );
				return 0 !== $score_compare ? $score_compare : strcmp( (string) ( $left['label'] ?? '' ), (string) ( $right['label'] ?? '' ) );
			}
		);
		// Reserve a small part of the limit for diagnostic categories so the
		// Discovery Inbox can explain dynamic, noisy, and registration-only hits.
		$ranked_hooks   = $hooks;
		$hooks          = array();
		$category_quota = max( 5, min( 25, (int) floor( $limit * 0.05 ) ) );
		$categories     = array(
			'dynamic'      => static function ( $meta ) {
				return ! empty( $meta['dynamic'] ); },
			'noisy'        => static function ( $meta ) {
				return 'potentially_noisy' === ( $meta['risk'] ?? '' ); },
			'registration' => static function ( $meta ) {
				return 'registration' === ( $meta['discovery'] ?? $meta['arg_mode'] ?? '' ); },
		);
		foreach ( $categories as $matches_category ) {
			$added = 0;
			foreach ( $ranked_hooks as $hook_name => $meta ) {
				if ( $added >= $category_quota ) {
					break;
				}
				if ( ! isset( $hooks[ $hook_name ] ) && $matches_category( $meta ) ) {
					$hooks[ $hook_name ] = $meta;
					++$added;
				}
			}
		}
		foreach ( $ranked_hooks as $hook_name => $meta ) {
			if ( count( $hooks ) >= $limit ) {
				break;
			}
			if ( ! isset( $hooks[ $hook_name ] ) ) {
				$hooks[ $hook_name ] = $meta;
			}
		}
		uasort(
			$hooks,
			static function ( $left, $right ) {
				return (int) ( $right['score'] ?? 0 ) <=> (int) ( $left['score'] ?? 0 );
			}
		);

		return $hooks;
	}

	/**
	 * Bound directory traversal for a single plugin scan.
	 *
	 * @param string $plugin_path Plugin file or directory.
	 * @return int Maximum files to inspect.
	 */
	private function get_scan_file_limit( $plugin_path ) {
		$limit = (int) apply_filters( 'notificator_companion_scan_file_limit', 2500, $plugin_path );
		return max( 250, min( 5000, $limit ) );
	}

	/**
	 * Fingerprint relevant PHP files so secondary-file changes invalidate cache.
	 *
	 * @param string $plugin_path Plugin file or directory.
	 * @param string $seed Plugin metadata seed.
	 * @return string
	 */
	private function get_plugin_fingerprint( $plugin_path, $seed ) {
		$parts = array( (string) $seed, 'schema:' . self::CACHE_SCHEMA_VERSION );
		if ( is_file( $plugin_path ) ) {
			$parts[] = basename( $plugin_path ) . ':' . (string) filemtime( $plugin_path ) . ':' . (string) filesize( $plugin_path );
		} elseif ( is_dir( $plugin_path ) ) {
			$excluded   = array( 'vendor', 'node_modules', 'tests', 'test', 'cache', 'build', 'dist', 'coverage', '.git', 'languages' );
			$directory  = new RecursiveDirectoryIterator( $plugin_path, RecursiveDirectoryIterator::SKIP_DOTS );
			$filtered   = new RecursiveCallbackFilterIterator(
				$directory,
				static function ( $current ) use ( $excluded ) {
					return ! $current->isDir() || ! in_array( strtolower( $current->getFilename() ), $excluded, true );
				}
			);
			$iterator   = new RecursiveIteratorIterator( $filtered, RecursiveIteratorIterator::LEAVES_ONLY );
			$files      = array();
			$file_limit = $this->get_scan_file_limit( $plugin_path );
			foreach ( $iterator as $file ) {
				if ( $file->isFile() && 'php' === strtolower( $file->getExtension() ) ) {
					$relative = ltrim( str_replace( $plugin_path, '', $file->getPathname() ), '/\\' );
					$files[]  = $relative . ':' . $file->getMTime() . ':' . $file->getSize();
				}
				if ( count( $files ) >= $file_limit ) {
					break;
				}
			}
			sort( $files, SORT_STRING );
			$parts = array_merge( $parts, $files );
		}
		return sha1( implode( '|', $parts ) );
	}

	/**
	 * Get all installed plugins with their discovered hooks
	 *
	 * @return array Array of plugins with their hooks.
	 */
	public function get_available_plugins_with_hooks() {
		$stored_hooks = null;
		$fs           = $this->get_filesystem();

		// Try to read from cache files, uploads first then plugin data fallback.
		foreach ( $this->get_data_dir_candidates() as $dir ) {
			$file_path = trailingslashit( $dir ) . 'scanned-hooks.json';
			if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
				continue;
			}

			$json = $fs ? $fs->get_contents( $file_path ) : false;
			if ( false === $json ) {
				continue;
			}

			$decoded = json_decode( $json, true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}

			$stored_hooks  = $decoded;
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

		$file_path  = $this->get_hooks_file_path();
		$hooks_data = $this->compact_hooks_cache_payload( $hooks_data );
		$json       = wp_json_encode( $hooks_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return false;
		}

		$temp_file = $file_path . '.tmp';
		$result    = $fs->put_contents( $temp_file, $json, FS_CHMOD_FILE );
		if ( false === $result ) {
			return false;
		}

		// Atomic rename.
		return $fs->move( $temp_file, $file_path, true );
	}

	/**
	 * Write a compact scan payload atomically to an explicit path.
	 *
	 * @param string $file_path  Destination path.
	 * @param array  $hooks_data Scan payload.
	 * @return bool True when the payload was saved.
	 */
	private function save_scan_payload_to_path( $file_path, $hooks_data ) {
		if ( ! $this->ensure_data_dir() ) {
			return false;
		}
		$fs = $this->get_filesystem();
		if ( ! $fs ) {
			return false;
		}
		$hooks_data = $this->compact_hooks_cache_payload( $hooks_data );
		$json       = wp_json_encode( $hooks_data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
		if ( false === $json ) {
			return false;
		}
		$temp_file = $file_path . '.tmp';
		if ( false === $fs->put_contents( $temp_file, $json, FS_CHMOD_FILE ) ) {
			return false;
		}
		return $fs->move( $temp_file, $file_path, true );
	}

	/**
	 * Read a resumable scan payload.
	 *
	 * @param string $file_path Working scan file path.
	 * @return array Decoded scan payload.
	 */
	private function read_scan_payload_from_path( $file_path ) {
		if ( ! is_readable( $file_path ) ) {
			return array();
		}
		$fs   = $this->get_filesystem();
		$json = $fs ? $fs->get_contents( $file_path ) : false;
		if ( false === $json ) {
			return array();
		}
		$decoded = json_decode( $json, true );
		return is_array( $decoded ) ? $decoded : array();
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
				'name'        => isset( $plugin_data['name'] ) ? (string) $plugin_data['name'] : '',
				'file'        => isset( $plugin_data['file'] ) ? (string) $plugin_data['file'] : '',
				'icon'        => isset( $plugin_data['icon'] ) ? (string) $plugin_data['icon'] : '',
				'color'       => isset( $plugin_data['color'] ) ? (string) $plugin_data['color'] : 'blue',
				'active'      => isset( $plugin_data['active'] ) ? (bool) $plugin_data['active'] : false,
				'scan_schema' => isset( $plugin_data['scan_schema'] ) ? (int) $plugin_data['scan_schema'] : self::CACHE_SCHEMA_VERSION,
				'fingerprint' => isset( $plugin_data['fingerprint'] ) ? (string) $plugin_data['fingerprint'] : '',
				'hooks'       => array(),
			);

			if ( isset( $plugin_data['hooks'] ) && is_array( $plugin_data['hooks'] ) ) {
				foreach ( $plugin_data['hooks'] as $hook_name => $hook_meta ) {
					if ( ! is_string( $hook_name ) || '' === $hook_name ) {
						continue;
					}

					if ( is_string( $hook_meta ) ) {
						$hook_meta = array(
							'label'         => $hook_meta,
							'type'          => 'action',
							'arg_mode'      => 'direct',
							'discovery'     => 'emission',
							'payload_arity' => null,
							'arg_names'     => array(),
							'dynamic'       => false,
							'selectable'    => true,
						);
					}

					if ( ! is_array( $hook_meta ) ) {
						continue;
					}
					if ( ! isset( $hook_meta['score'] ) ) {
						$hook_meta = $this->score_hook_candidate(
							$hook_name,
							array_merge(
								array(
									'label'         => $hook_name,
									'type'          => 'action',
									'arg_mode'      => 'registration',
									'payload_arity' => null,
									'arg_names'     => array(),
									'dynamic'       => false,
								),
								$hook_meta
							)
						);
					}
					if ( empty( $hook_meta['description'] ) ) {
						$hook_meta['description'] = $this->build_plain_language_description( $hook_name, $hook_meta );
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
					foreach ( array( 'dynamic', 'selectable', 'recommended' ) as $boolean_key ) {
						if ( array_key_exists( $boolean_key, $hook_meta ) ) {
							$meta[ $boolean_key ] = (bool) $hook_meta[ $boolean_key ];
						}
					}
					foreach ( array( 'discovery', 'confidence', 'risk', 'reason', 'description' ) as $string_key ) {
						if ( isset( $hook_meta[ $string_key ] ) && is_string( $hook_meta[ $string_key ] ) ) {
							$meta[ $string_key ] = $hook_meta[ $string_key ];
						}
					}
					if ( isset( $hook_meta['score'] ) ) {
						$meta['score'] = (int) $hook_meta['score'];
					}
					if ( isset( $hook_meta['source'] ) && is_array( $hook_meta['source'] ) ) {
						$meta['source'] = array(
							'file' => isset( $hook_meta['source']['file'] ) ? (string) $hook_meta['source']['file'] : '',
							'line' => isset( $hook_meta['source']['line'] ) ? (int) $hook_meta['source']['line'] : 0,
						);
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
		$hooks = array(
			// Users & auth.
			'user_register'              => __( 'New user registration', 'notificator-project' ),
			'wp_login'                   => __( 'User login', 'notificator-project' ),
			'wp_login_failed'            => __( 'Failed login attempt', 'notificator-project' ),
			'retrieve_password'          => __( 'Password reset requested', 'notificator-project' ),
			'password_reset'             => __( 'Password reset completed', 'notificator-project' ),
			'delete_user'                => __( 'User deleted', 'notificator-project' ),
			'profile_update'             => __( 'User profile updated', 'notificator-project' ),

			// Content.
			'publish_post'               => __( 'Post published', 'notificator-project' ),
			'wp_insert_post'             => __( 'Post created/updated', 'notificator-project' ),
			'wp_trash_post'              => __( 'Post trashed', 'notificator-project' ),
			'untrash_post'               => __( 'Post restored from trash', 'notificator-project' ),
			'delete_post'                => __( 'Post deleted', 'notificator-project' ),
			'comment_post'               => __( 'New comment posted', 'notificator-project' ),
			'edit_comment'               => __( 'Comment edited', 'notificator-project' ),
			'wp_set_comment_status'      => __( 'Comment status changed', 'notificator-project' ),
			'add_attachment'             => __( 'Media uploaded', 'notificator-project' ),
			'delete_attachment'          => __( 'Media deleted', 'notificator-project' ),

			// Site & settings.
			'switch_theme'               => __( 'Theme changed', 'notificator-project' ),
			'customize_save_after'       => __( 'Customizer settings saved', 'notificator-project' ),
			'update_option'              => __( 'Option updated', 'notificator-project' ),
			'added_option'               => __( 'Option added', 'notificator-project' ),
			'deleted_option'             => __( 'Option deleted', 'notificator-project' ),

			// Updates.
			'upgrader_process_complete'  => __( 'Update completed (plugin/theme/core)', 'notificator-project' ),
			'automatic_updates_complete' => __( 'Automatic updates completed', 'notificator-project' ),

			// Plugins.
			'activated_plugin'           => __( 'Plugin activated', 'notificator-project' ),
			'deactivated_plugin'         => __( 'Plugin deactivated', 'notificator-project' ),
		);
		foreach ( $hooks as $hook_name => $label ) {
			$meta                = array(
				'label'         => $label,
				'type'          => 'action',
				'arg_mode'      => 'direct',
				'discovery'     => 'emission',
				'payload_arity' => null,
				'arg_names'     => array(),
				'dynamic'       => false,
				'selectable'    => true,
			);
			$meta                = $this->score_hook_candidate( $hook_name, $meta );
			$meta['score']       = 90;
			$meta['confidence']  = 'high';
			$meta['recommended'] = true;
			$meta['reason']      = __( 'Curated WordPress event.', 'notificator-project' );
			$hooks[ $hook_name ] = $meta;
		}

		return array(
			'wordpress-core' => array(
				'name'   => 'WordPress Core',
				'file'   => '',
				'icon'   => '⚙️',
				'color'  => 'gray',
				'active' => true,
				'hooks'  => $hooks,
			),
		);
	}

	/**
	 * Return the plugin files that should be processed by a background scan.
	 *
	 * @param bool $include_inactive Whether inactive plugins are included.
	 * @return array<int, string>
	 */
	public function get_incremental_scan_targets( $include_inactive = false ) {
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );
		$targets        = array();
		foreach ( $all_plugins as $plugin_file => $unused_plugin_data ) {
			if ( plugin_basename( NOTIFICATOR_COMPANION_PLUGIN_FILE ) === $plugin_file ) {
				continue;
			}
			if ( ! $include_inactive && ! in_array( $plugin_file, $active_plugins, true ) ) {
				continue;
			}
			$targets[] = (string) $plugin_file;
		}
		sort( $targets, SORT_NATURAL | SORT_FLAG_CASE );
		return $targets;
	}

	/**
	 * Return active plugins that have not been covered by a successful scan.
	 *
	 * Comparing plugin slugs with the saved fingerprint map is intentionally
	 * inexpensive: opening an admin page must not trigger filesystem scanning.
	 *
	 * @return array<int, array{file: string, name: string, slug: string}> Unscanned plugins.
	 */
	public function get_unscanned_active_plugins() {
		if ( ! get_option( 'notificator_companion_last_scan', 0 ) ) {
			return array();
		}

		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );
		$fingerprints   = get_option( 'notificator_companion_scan_fingerprints', array() );
		$self_plugin    = plugin_basename( NOTIFICATOR_COMPANION_PLUGIN_FILE );
		$unscanned      = array();

		$active_plugins = is_array( $active_plugins ) ? $active_plugins : array();
		$fingerprints   = is_array( $fingerprints ) ? $fingerprints : array();

		foreach ( $active_plugins as $plugin_file ) {
			$plugin_file = plugin_basename( (string) $plugin_file );
			if ( $self_plugin === $plugin_file || ! isset( $all_plugins[ $plugin_file ] ) ) {
				continue;
			}

			$plugin_slug = dirname( $plugin_file );
			if ( '.' === $plugin_slug || '' === $plugin_slug ) {
				$plugin_slug = sanitize_title( basename( $plugin_file, '.php' ) );
			}

			if ( array_key_exists( $plugin_slug, $fingerprints ) ) {
				continue;
			}

			$plugin_data = $all_plugins[ $plugin_file ];
			$unscanned[] = array(
				'file' => $plugin_file,
				'name' => isset( $plugin_data['Name'] ) ? (string) $plugin_data['Name'] : $plugin_file,
				'slug' => $plugin_slug,
			);
		}

		return $unscanned;
	}

	/**
	 * Start a resumable scan without replacing the currently published cache.
	 *
	 * @param string $scan_id Unique scan identifier.
	 * @return bool True when the working snapshot was created.
	 */
	public function begin_incremental_scan( $scan_id ) {
		if ( ! $this->ensure_data_dir() ) {
			return false;
		}
		return $this->save_scan_payload_to_path(
			$this->get_scan_working_file_path( $scan_id ),
			$this->get_wordpress_core_hooks()
		);
	}

	/**
	 * Scan or reuse one plugin from an incremental queue.
	 *
	 * @param string $plugin_file Plugin basename from get_plugins().
	 * @param int    $hook_limit Result limit for this plugin.
	 * @return array<string, mixed>
	 */
	public function scan_incremental_target( $plugin_file, $hook_limit = 500 ) {
		$hook_limit = max( 50, min( 10000, (int) $hook_limit ) );
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}
		$all_plugins = get_plugins();
		if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
			return array(
				'success' => false,
				'message' => __( 'Plugin is no longer installed.', 'notificator-project' ),
			);
		}

		$plugin_data = $all_plugins[ $plugin_file ];
		$plugin_name = isset( $plugin_data['Name'] ) ? (string) $plugin_data['Name'] : (string) $plugin_file;
		$plugin_dir  = dirname( $plugin_file );
		$plugin_path = ( '.' === $plugin_dir ) ? WP_PLUGIN_DIR . '/' . $plugin_file : WP_PLUGIN_DIR . '/' . $plugin_dir;
		$plugin_slug = $plugin_dir;
		if ( '.' === $plugin_slug || '' === $plugin_slug ) {
			$plugin_slug = sanitize_title( basename( $plugin_file, '.php' ) );
		}
		$is_active      = in_array( $plugin_file, (array) get_option( 'active_plugins', array() ), true );
		$fingerprint    = $this->get_plugin_fingerprint(
			$plugin_path,
			$plugin_file . '|' . ( isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '' ) . '|' . (string) $hook_limit
		);
		$cached_plugins = $this->get_available_plugins_with_hooks();
		$fingerprints   = get_option( 'notificator_companion_scan_fingerprints', array() );
		$fingerprints   = is_array( $fingerprints ) ? $fingerprints : array();
		$reused         = isset( $fingerprints[ $plugin_slug ], $cached_plugins[ $plugin_slug ]['hooks'] )
			&& is_array( $cached_plugins[ $plugin_slug ]['hooks'] )
			&& hash_equals( (string) $fingerprints[ $plugin_slug ], $fingerprint );
		$hooks          = $reused ? $cached_plugins[ $plugin_slug ]['hooks'] : $this->scan_plugin_for_hooks( $plugin_path, $hook_limit );
		$plugin         = null;
		if ( ! empty( $hooks ) ) {
			$plugin = array(
				'name'        => $plugin_name,
				'file'        => $plugin_file,
				'slug'        => $plugin_slug,
				'icon'        => $this->get_plugin_icon( $plugin_name ),
				'color'       => 'blue',
				'active'      => $is_active,
				'scan_schema' => self::CACHE_SCHEMA_VERSION,
				'fingerprint' => $fingerprint,
				'hooks'       => $hooks,
			);
		}

		return array(
			'success'     => true,
			'plugin_name' => $plugin_name,
			'plugin_slug' => $plugin_slug,
			'fingerprint' => $fingerprint,
			'reused'      => $reused,
			'hooks_found' => count( $hooks ),
			'plugin'      => $plugin,
		);
	}

	/**
	 * Merge one completed plugin into the unpublished working snapshot.
	 *
	 * @param string $scan_id Unique scan identifier.
	 * @param array  $result  Completed plugin scan result.
	 * @return bool True when the working snapshot was updated.
	 */
	public function append_incremental_scan_result( $scan_id, $result ) {
		if ( ! is_array( $result ) || empty( $result['success'] ) ) {
			return false;
		}
		$file_path = $this->get_scan_working_file_path( $scan_id );
		$payload   = $this->read_scan_payload_from_path( $file_path );
		if ( empty( $payload ) ) {
			return false;
		}
		if ( ! empty( $result['plugin_slug'] ) && is_array( $result['plugin'] ) ) {
			$payload[ (string) $result['plugin_slug'] ] = $result['plugin'];
		}
		return $this->save_scan_payload_to_path( $file_path, $payload );
	}

	/**
	 * Publish a completed working snapshot and remove its temporary file.
	 *
	 * @param string $scan_id      Unique scan identifier.
	 * @param array  $fingerprints Plugin fingerprints for cache reuse.
	 * @return array Final scan result.
	 */
	public function finalize_incremental_scan( $scan_id, $fingerprints ) {
		$file_path         = $this->get_scan_working_file_path( $scan_id );
		$available_plugins = $this->read_scan_payload_from_path( $file_path );
		if ( empty( $available_plugins ) || ! $this->save_hooks_to_file( $available_plugins ) ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to publish scan results.', 'notificator-project' ),
			);
		}
		update_option( 'notificator_companion_last_scan', time(), false );
		update_option( 'notificator_companion_scan_fingerprints', is_array( $fingerprints ) ? $fingerprints : array(), false );
		$this->delete_incremental_scan( $scan_id );

		$quality     = array(
			'recommended'  => 0,
			'dynamic'      => 0,
			'registration' => 0,
			'noisy'        => 0,
		);
		$hooks_found = 0;
		foreach ( $available_plugins as $plugin ) {
			foreach ( (array) ( $plugin['hooks'] ?? array() ) as $meta ) {
				++$hooks_found;
				if ( ! is_array( $meta ) ) {
					continue;
				}
				if ( ! empty( $meta['recommended'] ) ) {
					++$quality['recommended'];
				}
				if ( ! empty( $meta['dynamic'] ) ) {
					++$quality['dynamic'];
				}
				if ( 'registration' === ( $meta['discovery'] ?? $meta['arg_mode'] ?? '' ) ) {
					++$quality['registration'];
				}
				if ( 'potentially_noisy' === ( $meta['risk'] ?? '' ) ) {
					++$quality['noisy'];
				}
			}
		}
		return array(
			'success'       => true,
			'plugins_found' => max( 0, count( $available_plugins ) - 1 ),
			'hooks_found'   => $hooks_found,
			'quality'       => $quality,
		);
	}

	/**
	 * Remove a temporary working snapshot.
	 *
	 * @param string $scan_id Unique scan identifier.
	 * @return void
	 */
	public function delete_incremental_scan( $scan_id ) {
		$fs        = $this->get_filesystem();
		$file_path = $this->get_scan_working_file_path( $scan_id );
		foreach ( array( $file_path, $file_path . '.tmp' ) as $candidate ) {
			if ( $fs && $fs->exists( $candidate ) ) {
				$fs->delete( $candidate );
			} elseif ( file_exists( $candidate ) ) {
				wp_delete_file( $candidate );
			}
		}
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
		} elseif ( $hook_limit > 10000 ) {
			$hook_limit = 10000;
		}

		// Start with WordPress Core and reuse unchanged plugin results when possible.
		$available_plugins = $this->get_wordpress_core_hooks();
		$cached_plugins    = $this->get_available_plugins_with_hooks();
		$fingerprints      = get_option( 'notificator_companion_scan_fingerprints', array() );
		if ( ! is_array( $fingerprints ) ) {
			$fingerprints = array();
		}
		$next_fingerprints = array();

		// Get all plugins.
		if ( ! function_exists( 'get_plugins' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		$all_plugins    = get_plugins();
		$active_plugins = get_option( 'active_plugins', array() );

		$plugins_found  = 0;
		$plugins_reused = 0;
		$hooks_found    = count( $available_plugins['wordpress-core']['hooks'] );
		$plugins_total  = 0;
		foreach ( $all_plugins as $candidate_file => $unused_plugin_data ) {
			if ( plugin_basename( NOTIFICATOR_COMPANION_PLUGIN_FILE ) !== $candidate_file && ( $include_inactive || in_array( $candidate_file, $active_plugins, true ) ) ) {
				++$plugins_total;
			}
		}
		$plugins_processed = 0;

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

			$plugin_slug = dirname( $plugin_file );
			if ( '.' === $plugin_slug || empty( $plugin_slug ) ) {
				$plugin_slug = sanitize_title( basename( $plugin_file, '.php' ) );
			}
			$fingerprint                       = $this->get_plugin_fingerprint(
				$plugin_path,
				$plugin_file . '|' . ( isset( $plugin_data['Version'] ) ? (string) $plugin_data['Version'] : '' ) . '|' . (string) $hook_limit
			);
			$next_fingerprints[ $plugin_slug ] = $fingerprint;

			if (
				isset( $fingerprints[ $plugin_slug ] ) &&
				hash_equals( (string) $fingerprints[ $plugin_slug ], $fingerprint ) &&
				isset( $cached_plugins[ $plugin_slug ]['hooks'] ) &&
				is_array( $cached_plugins[ $plugin_slug ]['hooks'] )
			) {
				$discovered_hooks = $cached_plugins[ $plugin_slug ]['hooks'];
				++$plugins_reused;
			} else {
				// Scan only new or changed plugin code.
				$discovered_hooks = $this->scan_plugin_for_hooks( $plugin_path, $hook_limit );
			}

			if ( ! empty( $discovered_hooks ) ) {
				$plugin_key                       = $plugin_slug;
				$available_plugins[ $plugin_key ] = array(
					'name'        => $plugin_name,
					'file'        => $plugin_file,
					'slug'        => $plugin_slug,
					'icon'        => $this->get_plugin_icon( $plugin_name ),
					'color'       => 'blue',
					'active'      => $is_active,
					'scan_schema' => self::CACHE_SCHEMA_VERSION,
					'fingerprint' => $fingerprint,
					'hooks'       => $discovered_hooks,
				);

				++$plugins_found;
				$hooks_found += count( $discovered_hooks );
			}
			++$plugins_processed;
			do_action(
				'notificator_companion_scan_progress',
				array(
					'current_plugin' => $plugin_name,
					'processed'      => $plugins_processed,
					'total'          => $plugins_total,
					'hooks_found'    => $hooks_found,
				)
			);
		}

		// Store the scanned hooks to file.
		$saved = $this->save_hooks_to_file( $available_plugins );
		if ( ! $saved ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to save scanned hooks. Check file permissions.', 'notificator-project' ),
			);
		}

		// Store last scan timestamp in DB (lightweight).
		update_option( 'notificator_companion_last_scan', time(), false );
		update_option( 'notificator_companion_scan_fingerprints', $next_fingerprints, false );

		$quality = array(
			'recommended'  => count( $available_plugins['wordpress-core']['hooks'] ),
			'dynamic'      => 0,
			'registration' => 0,
			'noisy'        => 0,
		);
		foreach ( $available_plugins as $plugin ) {
			foreach ( (array) ( $plugin['hooks'] ?? array() ) as $meta ) {
				if ( ! is_array( $meta ) ) {
					continue;
				}
				if ( ! empty( $meta['recommended'] ) ) {
					++$quality['recommended'];
				}
				if ( ! empty( $meta['dynamic'] ) ) {
					++$quality['dynamic'];
				}
				if ( 'registration' === ( $meta['arg_mode'] ?? '' ) ) {
					++$quality['registration'];
				}
				if ( 'potentially_noisy' === ( $meta['risk'] ?? '' ) ) {
					++$quality['noisy'];
				}
			}
		}

		return array(
			'success'        => true,
			'plugins_found'  => $plugins_found,
			'plugins_reused' => $plugins_reused,
			'hooks_found'    => $hooks_found,
			'quality'        => $quality,
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
			'woocommerce' => '🛒',
			'jetpack'     => '✈️',
			'yoast'       => '📊',
			'elementor'   => '🎨',
			'contact'     => '📧',
			'form'        => '📝',
			'security'    => '🔒',
			'backup'      => '💾',
			'cache'       => '⚡',
			'seo'         => '🔍',
			'analytics'   => '📈',
			'social'      => '👥',
			'gallery'     => '🖼️',
			'slider'      => '🎞️',
			'wordpress'   => '⚙️',
			'wpml'        => '🌍',
			'polylang'    => '🌐',
			'buddypress'  => '👫',
			'bbpress'     => '💬',
			'mailchimp'   => '📬',
			'stripe'      => '💳',
			'paypal'      => '💰',
			'membership'  => '👤',
			'learndash'   => '🎓',
			'event'       => '📅',
			'booking'     => '📆',
			'calendar'    => '🗓️',
			'shop'        => '🏪',
			'payment'     => '💵',
			'shipping'    => '📦',
		);

		foreach ( $icon_map as $keyword => $icon ) {
			if ( false !== strpos( $plugin_lower, $keyword ) ) {
				return $icon;
			}
		}

		return '🔌'; // Default plugin icon.
	}
}

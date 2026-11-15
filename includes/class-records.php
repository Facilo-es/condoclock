<?php
/**
 * Records: storage, posting, versions, "not applicable" marks and the board's actions.
 *
 * @package CondoClock
 */

namespace CondoClock;

defined( 'ABSPATH' ) || exit;

/**
 * Records kept as a private post type.
 */
class Records {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_action( 'admin_post_condoclock_add_records', array( __CLASS__, 'handle_add' ) );
		add_action( 'admin_post_condoclock_record', array( __CLASS__, 'handle_action' ) );
		add_action( 'admin_post_condoclock_na', array( __CLASS__, 'handle_na' ) );
	}

	/**
	 * Post types for records and meetings. Neither is public: files and pages are served by CondoClock.
	 */
	public static function register() {
		$common = array(
			'public'              => false,
			'show_ui'             => false,
			'show_in_rest'        => false,
			'exclude_from_search' => true,
			'publicly_queryable'  => false,
			'has_archive'         => false,
			'rewrite'             => false,
			'query_var'           => false,
			'supports'            => array( 'title' ),
			'capability_type'     => 'post',
			'map_meta_cap'        => true,
		);
		register_post_type( 'condoclock_record', $common + array( 'label' => __( 'Records', 'condoclock' ) ) );
		register_post_type( 'condoclock_meeting', $common + array( 'label' => __( 'Notices', 'condoclock' ) ) );
	}

	/**
	 * Today's date in site time (Y-m-d).
	 *
	 * @return string
	 */
	public static function today() {
		return wp_date( 'Y-m-d' );
	}

	/**
	 * Creates a record.
	 *
	 * @param array $args category, title, received, file (info array), video, meeting, note, post (bool).
	 * @return int|\WP_Error Record ID.
	 */
	public static function create( array $args ) {
		$args = wp_parse_args(
			$args,
			array(
				'category' => '',
				'title'    => '',
				'received' => self::today(),
				'file'     => null,
				'video'    => '',
				'meeting'  => 0,
				'note'     => '',
				'post'     => false,
			)
		);
		if ( ! Rules::category( $args['category'] ) ) {
			return new \WP_Error( 'condoclock_category', __( 'Pick a category for this record.', 'condoclock' ) );
		}
		$received = self::clean_date( $args['received'] );
		if ( ! $received ) {
			return new \WP_Error( 'condoclock_date', __( 'Enter the date received or created, such as 2027-03-10.', 'condoclock' ) );
		}
		if ( $received > self::today() ) {
			return new \WP_Error( 'condoclock_future', __( 'The date received or created can\'t be in the future.', 'condoclock' ) );
		}
		$video = $args['video'] ? esc_url_raw( $args['video'], array( 'http', 'https' ) ) : '';
		$title = trim( wp_strip_all_tags( $args['title'] ) );
		if ( '' === $title && ! empty( $args['file']['name'] ) ) {
			$title = self::tidy_title( $args['file']['name'] );
		}
		if ( '' === $title ) {
			$title = $video ? __( 'Meeting recording', 'condoclock' ) : __( 'Untitled record', 'condoclock' );
		}
		$id = wp_insert_post(
			array(
				'post_type'   => 'condoclock_record',
				'post_status' => 'publish',
				'post_title'  => mb_substr( $title, 0, 200 ),
				'post_author' => get_current_user_id(),
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_post_meta( $id, '_cc_category', $args['category'] );
		update_post_meta( $id, '_cc_received', $received );
		update_post_meta( $id, '_cc_status', 'draft' );
		if ( $args['file'] ) {
			update_post_meta( $id, '_cc_file', $args['file'] );
		}
		if ( $video ) {
			update_post_meta( $id, '_cc_video_url', $video );
		}
		if ( $args['meeting'] ) {
			update_post_meta( $id, '_cc_meeting_id', absint( $args['meeting'] ) );
		}
		if ( '' !== trim( $args['note'] ) ) {
			update_post_meta( $id, '_cc_note', mb_substr( sanitize_textarea_field( $args['note'] ), 0, 500 ) );
		}
		self::changed( $id, 'created' );
		if ( $args['post'] ) {
			self::post( $id );
		}
		return $id;
	}

	/**
	 * Turns a file name into a readable title.
	 *
	 * @param string $name File name.
	 * @return string
	 */
	public static function tidy_title( $name ) {
		$base = pathinfo( $name, PATHINFO_FILENAME );
		$base = preg_replace( '/[_\-.]+/', ' ', $base );
		$base = trim( preg_replace( '/\s+/', ' ', $base ) );
		return ucfirst( $base );
	}

	/**
	 * Valid Y-m-d date or ''.
	 *
	 * @param string $date Date.
	 * @return string
	 */
	public static function clean_date( $date ) {
		$date = trim( (string) $date );
		$d    = \DateTime::createFromFormat( '!Y-m-d', $date );
		return ( $d && $d->format( 'Y-m-d' ) === $date ) ? $date : '';
	}

	/**
	 * Does the record have something to show owners?
	 *
	 * @param int $id Record.
	 * @return bool
	 */
	public static function has_content( $id ) {
		return (bool) get_post_meta( $id, '_cc_file', true ) || (bool) get_post_meta( $id, '_cc_video_url', true );
	}

	/**
	 * Posts a record to owners, stamped with the current time.
	 *
	 * @param int $id Record.
	 * @return true|\WP_Error
	 */
	public static function post( $id ) {
		if ( ! self::has_content( $id ) ) {
			return new \WP_Error( 'condoclock_empty', __( 'Add the file or the recording link before posting.', 'condoclock' ) );
		}
		update_post_meta( $id, '_cc_status', 'posted' );
		update_post_meta( $id, '_cc_posted', gmdate( 'Y-m-d H:i:s' ) );
		self::changed( $id, 'posted' );
		return true;
	}

	/**
	 * Replaces a record's file, keeping the old one as an earlier version.
	 *
	 * @param int   $id   Record.
	 * @param array $info New file info.
	 */
	public static function replace( $id, array $info ) {
		$old = get_post_meta( $id, '_cc_file', true );
		if ( is_array( $old ) && $old ) {
			$versions   = (array) get_post_meta( $id, '_cc_versions', true );
			$versions   = array_values( array_filter( $versions ) );
			$versions[] = array(
				'file'   => $old,
				'posted' => (string) get_post_meta( $id, '_cc_posted', true ),
			);
			update_post_meta( $id, '_cc_versions', $versions );
		}
		update_post_meta( $id, '_cc_file', $info );
		delete_post_meta( $id, '_cc_scan' );
		if ( 'posted' === get_post_meta( $id, '_cc_status', true ) ) {
			update_post_meta( $id, '_cc_posted', gmdate( 'Y-m-d H:i:s' ) );
		}
		self::changed( $id, 'replaced' );
	}

	/**
	 * Sets a record's status (withdrawn or archived).
	 *
	 * @param int    $id     Record.
	 * @param string $status withdrawn|archived.
	 */
	public static function set_status( $id, $status ) {
		if ( ! in_array( $status, array( 'withdrawn', 'archived' ), true ) ) {
			return;
		}
		update_post_meta( $id, '_cc_status', $status );
		self::changed( $id, $status );
	}

	/**
	 * Deletes a record and its files for good.
	 *
	 * @param int $id Record.
	 */
	public static function delete( $id ) {
		self::changed( $id, 'deleted' );
		Files::delete( (array) get_post_meta( $id, '_cc_file', true ) );
		foreach ( (array) get_post_meta( $id, '_cc_versions', true ) as $v ) {
			if ( ! empty( $v['file'] ) ) {
				Files::delete( $v['file'] );
			}
		}
		wp_delete_post( $id, true );
	}

	/**
	 * Tells the rest of the site (and Pro's history) that a record changed.
	 *
	 * @param int    $id     Record.
	 * @param string $action What happened.
	 */
	public static function changed( $id, $action ) {
		update_option( 'condoclock_cache_v', (int) get_option( 'condoclock_cache_v', 0 ) + 1, false );
		/**
		 * Fires after a record is created, posted, replaced, withdrawn, archived or (just before) deleted.
		 *
		 * @param int    $id     Record ID.
		 * @param string $action created|posted|replaced|withdrawn|archived|deleted|checked.
		 */
		do_action( 'condoclock_record_changed', (int) $id, $action );
	}

	/**
	 * One record as an array.
	 *
	 * @param int|\WP_Post $post Record.
	 * @return array|null
	 */
	public static function get( $post ) {
		$post = get_post( $post );
		if ( ! $post || 'condoclock_record' !== $post->post_type ) {
			return null;
		}
		$m    = get_post_meta( $post->ID );
		$meta = static function ( $key ) use ( $m ) {
			return isset( $m[ $key ][0] ) ? maybe_unserialize( $m[ $key ][0] ) : '';
		};
		return array(
			'id'       => $post->ID,
			'title'    => $post->post_title,
			'category' => (string) $meta( '_cc_category' ),
			'received' => (string) $meta( '_cc_received' ),
			'posted'   => (string) $meta( '_cc_posted' ),
			'status'   => $meta( '_cc_status' ) ? (string) $meta( '_cc_status' ) : 'draft',
			'file'     => is_array( $meta( '_cc_file' ) ) ? $meta( '_cc_file' ) : array(),
			'versions' => is_array( $meta( '_cc_versions' ) ) ? array_values( $meta( '_cc_versions' ) ) : array(),
			'video'    => (string) $meta( '_cc_video_url' ),
			'meeting'  => (int) $meta( '_cc_meeting_id' ),
			'note'     => (string) $meta( '_cc_note' ),
			'scan'     => is_array( $meta( '_cc_scan' ) ) ? $meta( '_cc_scan' ) : array(),
			'due'      => (string) $meta( '_cc_due' ),
			'author'   => (int) $post->post_author,
		);
	}

	/**
	 * Every record, grouped by category, in one query.
	 *
	 * @param array $statuses Statuses to include, or empty for all.
	 * @return array category key => list of records, newest posting first.
	 */
	public static function grouped( array $statuses = array() ) {
		$ids = get_posts(
			array(
				'post_type'        => 'condoclock_record',
				'post_status'      => 'publish',
				'numberposts'      => -1,
				'fields'           => 'ids',
				'orderby'          => 'ID',
				'order'            => 'DESC',
				'suppress_filters' => false,
			)
		);
		if ( $ids ) {
			update_meta_cache( 'post', $ids );
		}
		$out = array();
		foreach ( $ids as $id ) {
			$r = self::get( $id );
			if ( ! $r || ( $statuses && ! in_array( $r['status'], $statuses, true ) ) ) {
				continue;
			}
			$out[ $r['category'] ][] = $r;
		}
		foreach ( $out as &$list ) {
			usort(
				$list,
				static function ( $a, $b ) {
					return strcmp( $b['posted'] . $b['received'], $a['posted'] . $a['received'] );
				}
			);
		}
		return $out;
	}

	/**
	 * Posted records for owners, cached until a record changes.
	 *
	 * @return array
	 */
	public static function for_owners() {
		$key    = 'condoclock_owner_list_' . (int) get_option( 'condoclock_cache_v', 0 );
		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}
		$list = self::grouped( array( 'posted' ) );
		set_transient( $key, $list, DAY_IN_SECONDS );
		return $list;
	}

	/**
	 * Categories marked "not applicable".
	 *
	 * @return array key => array( reason, show ).
	 */
	public static function na() {
		$na = get_option( 'condoclock_na', array() );
		return is_array( $na ) ? $na : array();
	}

	/**
	 * Marks a category "not applicable", or clears the mark.
	 *
	 * @param string $key    Category.
	 * @param string $reason Reason (required to mark).
	 * @param bool   $show   Show the reason to owners.
	 * @return true|\WP_Error
	 */
	public static function set_na( $key, $reason, $show = true ) {
		$na = self::na();
		if ( null === $reason ) {
			unset( $na[ $key ] );
			update_option( 'condoclock_na', $na, false );
			self::changed( 0, 'na_cleared' );
			return true;
		}
		$reason = trim( sanitize_text_field( $reason ) );
		if ( '' === $reason ) {
			return new \WP_Error( 'condoclock_reason', __( 'Say why this category doesn\'t apply to your association.', 'condoclock' ) );
		}
		$na[ $key ] = array(
			'reason' => mb_substr( $reason, 0, 200 ),
			'show'   => $show ? 1 : 0,
			'by'     => get_current_user_id(),
			'time'   => gmdate( 'Y-m-d H:i:s' ),
		);
		update_option( 'condoclock_na', $na, false );
		/**
		 * Fires when a category is marked "not applicable".
		 *
		 * @param string $key    Category.
		 * @param string $reason Reason.
		 */
		do_action( 'condoclock_marked_na', $key, $na[ $key ]['reason'] );
		self::changed( 0, 'na' );
		return true;
	}

	/**
	 * Status of every category for the board.
	 *
	 * @param string|null $mode condo or hoa.
	 * @return array rows (key => posted|na|missing), latest (key => UTC), files (key => count), and the counts.
	 */
	public static function summary( $mode = null ) {
		$cats    = Rules::categories( $mode );
		$grouped = self::grouped();
		$na      = self::na();
		$out     = array(
			'rows'    => array(),
			'latest'  => array(),
			'files'   => array(),
			'posted'  => 0,
			'na'      => 0,
			'missing' => 0,
			'total'   => count( $cats ),
		);
		foreach ( $cats as $key => $cat ) {
			$posted = array_filter(
				isset( $grouped[ $key ] ) ? $grouped[ $key ] : array(),
				static function ( $r ) {
					return 'posted' === $r['status'];
				}
			);
			$latest = '';
			foreach ( $posted as $r ) {
				$latest = max( $latest, $r['posted'] );
			}
			$out['files'][ $key ]  = count( $posted );
			$out['latest'][ $key ] = $latest;
			if ( $posted ) {
				$state = 'posted';
			} elseif ( isset( $na[ $key ] ) ) {
				$state = 'na';
			} else {
				$state = 'missing';
			}
			$out['rows'][ $key ] = $state;
			++$out[ $state ];
		}
		return $out;
	}

	/**
	 * Back to a board screen with a message.
	 *
	 * @param array $args Query args.
	 */
	private static function back( array $args ) {
		$url = add_query_arg( $args, admin_url( 'admin.php' ) );
		wp_safe_redirect( $url );
		exit;
	}

	/**
	 * Stops unless the user may manage records and the nonce is right.
	 *
	 * @param string $action Nonce action.
	 */
	private static function guard( $action ) {
		if ( ! condoclock_can_manage() ) {
			wp_die( esc_html__( 'You don\'t have permission to manage records.', 'condoclock' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( $action );
	}

	/**
	 * Uploaded files from a multi-file input, one array each.
	 *
	 * @param string $field Input name.
	 * @return array
	 */
	private static function uploaded( $field ) {
		// phpcs:ignore WordPress.Security.NonceVerification.Missing,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- nonce checked by the caller; wp_handle_upload checks each file.
		$f = isset( $_FILES[ $field ] ) ? $_FILES[ $field ] : null;
		if ( ! $f || ! isset( $f['name'] ) ) {
			return array();
		}
		if ( ! is_array( $f['name'] ) ) {
			return UPLOAD_ERR_NO_FILE === (int) $f['error'] ? array() : array( $f );
		}
		$out = array();
		foreach ( array_keys( $f['name'] ) as $i ) {
			if ( UPLOAD_ERR_NO_FILE === (int) $f['error'][ $i ] ) {
				continue;
			}
			$out[] = array(
				'name'     => $f['name'][ $i ],
				'type'     => $f['type'][ $i ],
				'tmp_name' => $f['tmp_name'][ $i ],
				'error'    => $f['error'][ $i ],
				'size'     => $f['size'][ $i ],
			);
		}
		return $out;
	}

	/**
	 * Runs the upload check (Pro's privacy scan) on a stored file.
	 *
	 * @param int   $id   Record.
	 * @param array $info File info.
	 * @return array Findings summary, empty when nothing was found.
	 */
	public static function check_file( $id, array $info ) {
		/**
		 * Looks inside a file before it goes up. CondoClock Pro's privacy scan returns its findings here.
		 *
		 * @param array  $scan Empty array, or array( 'found' => int, 'message' => string, 'items' => array ).
		 * @param string $path Absolute path of the stored file.
		 * @param array  $info File info.
		 * @param int    $id   Record ID.
		 */
		$scan = apply_filters( 'condoclock_check_file', array(), Files::path( $info ), $info, $id );
		if ( is_array( $scan ) && ! empty( $scan['message'] ) ) {
			$scan['checked'] = 0;
			update_post_meta( $id, '_cc_scan', $scan );
			return $scan;
		}
		delete_post_meta( $id, '_cc_scan' );
		return array();
	}

	/**
	 * Does a scan block posting until the board ticks "I checked this file"?
	 *
	 * @param int $id Record.
	 * @return bool
	 */
	public static function needs_check( $id ) {
		$scan = get_post_meta( $id, '_cc_scan', true );
		return is_array( $scan ) && ! empty( $scan['message'] ) && empty( $scan['checked'] );
	}

	/**
	 * Adds records from the board's form (one or many files).
	 */
	public static function handle_add() {
		self::guard( 'condoclock_add_records' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in guard().
		$cat      = isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : '';
		$title    = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$received = isset( $_POST['received'] ) ? sanitize_text_field( wp_unslash( $_POST['received'] ) ) : self::today();
		$video    = isset( $_POST['video'] ) ? esc_url_raw( wp_unslash( $_POST['video'] ) ) : '';
		$meeting  = isset( $_POST['meeting'] ) ? absint( $_POST['meeting'] ) : 0;
		$note     = isset( $_POST['note'] ) ? sanitize_textarea_field( wp_unslash( $_POST['note'] ) ) : '';
		$post     = isset( $_POST['do'] ) && 'post' === $_POST['do'];
		// phpcs:enable
		$back  = array(
			'page' => 'condoclock',
			'cat'  => $cat,
		);
		$files = self::uploaded( 'files' );
		if ( ! $files && ! $video ) {
			$pending = condoclock_is_pro() && ! $post && '' !== $title;
			if ( ! $pending ) {
				self::back( $back + array( 'cc_msg' => 'nofile' ) );
			}
			$files = array( null );
		}
		$done    = 0;
		$checks  = 0;
		$errors  = array();
		$created = 0;
		foreach ( $files as $file ) {
			$info = null;
			if ( $file ) {
				$info = Files::store_upload( $file );
				if ( is_wp_error( $info ) ) {
					$errors[] = sanitize_file_name( $file['name'] ) . ': ' . $info->get_error_message();
					continue;
				}
			}
			$id = self::create(
				array(
					'category' => $cat,
					'title'    => 1 === count( $files ) ? $title : '',
					'received' => $received,
					'file'     => $info,
					'video'    => $video,
					'meeting'  => $meeting,
					'note'     => $note,
				)
			);
			if ( is_wp_error( $id ) ) {
				if ( $info ) {
					Files::delete( $info );
				}
				$errors[] = $id->get_error_message();
				continue;
			}
			$created = $id;
			if ( $info && self::check_file( $id, $info ) ) {
				++$checks;
				continue;
			}
			if ( $post ) {
				self::post( $id );
			}
			++$done;
		}
		if ( $errors ) {
			set_transient( 'condoclock_errors_' . get_current_user_id(), $errors, 300 );
		}
		$args = $back + array(
			'cc_msg' => $post ? 'posted' : 'saved',
			'cc_n'   => $done,
		);
		if ( $checks ) {
			$args['cc_msg']   = 'check';
			$args['cc_check'] = 1 === $checks ? $created : 0;
		}
		if ( $errors ) {
			$args['cc_err'] = 1;
		}
		self::back( $args );
	}

	/**
	 * Post, replace, withdraw, archive or delete one record.
	 */
	public static function handle_action() {
		self::guard( 'condoclock_record' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in guard().
		$id   = isset( $_POST['record'] ) ? absint( $_POST['record'] ) : 0;
		$do   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$conf = isset( $_POST['confirm'] ) ? sanitize_text_field( wp_unslash( $_POST['confirm'] ) ) : '';
		$tick = ! empty( $_POST['checked'] );
		// phpcs:enable
		$r = self::get( $id );
		if ( ! $r ) {
			self::back( array( 'page' => 'condoclock' ) );
		}
		$back = array(
			'page' => 'condoclock',
			'cat'  => $r['category'],
		);
		switch ( $do ) {
			case 'post':
				if ( self::needs_check( $id ) ) {
					if ( ! $tick ) {
						self::back(
							$back + array(
								'cc_msg'   => 'check',
								'cc_check' => $id,
							)
						);
					}
					$scan            = get_post_meta( $id, '_cc_scan', true );
					$scan['checked'] = get_current_user_id();
					$scan['time']    = gmdate( 'Y-m-d H:i:s' );
					update_post_meta( $id, '_cc_scan', $scan );
					self::changed( $id, 'checked' );
				}
				$res = self::post( $id );
				self::back(
					$back + array(
						'cc_msg' => is_wp_error( $res ) ? 'nofile' : 'posted',
						'cc_n'   => 1,
					)
				);
				break;
			case 'replace':
				$files = self::uploaded( 'file' );
				if ( ! $files ) {
					self::back( $back + array( 'cc_msg' => 'nofile' ) );
				}
				$info = Files::store_upload( $files[0] );
				if ( is_wp_error( $info ) ) {
					set_transient( 'condoclock_errors_' . get_current_user_id(), array( $info->get_error_message() ), 300 );
					self::back( $back + array( 'cc_err' => 1 ) );
				}
				self::replace( $id, $info );
				if ( self::check_file( $id, $info ) && 'posted' === $r['status'] ) {
					update_post_meta( $id, '_cc_status', 'draft' );
					self::changed( $id, 'held' );
					self::back(
						$back + array(
							'cc_msg'   => 'check',
							'cc_check' => $id,
						)
					);
				}
				self::back( $back + array( 'cc_msg' => 'replaced' ) );
				break;
			case 'withdraw':
			case 'archive':
				self::set_status( $id, 'withdraw' === $do ? 'withdrawn' : 'archived' );
				self::back( $back + array( 'cc_msg' => $do ) );
				break;
			case 'delete':
				if ( 'delete' !== strtolower( trim( $conf ) ) ) {
					self::back( $back + array( 'cc_msg' => 'confirm' ) );
				}
				self::delete( $id );
				self::back( $back + array( 'cc_msg' => 'deleted' ) );
				break;
		}
		self::back( $back );
	}

	/**
	 * Marks a category "not applicable", or clears it.
	 */
	public static function handle_na() {
		self::guard( 'condoclock_na' );
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- checked in guard().
		$cat    = isset( $_POST['category'] ) ? sanitize_key( wp_unslash( $_POST['category'] ) ) : '';
		$clear  = ! empty( $_POST['clear'] );
		$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';
		$show   = ! empty( $_POST['show'] );
		$from   = isset( $_POST['from'] ) ? sanitize_key( wp_unslash( $_POST['from'] ) ) : '';
		// phpcs:enable
		if ( ! Rules::category( $cat ) ) {
			self::back( array( 'page' => 'condoclock' ) );
		}
		$res  = self::set_na( $cat, $clear ? null : $reason, $show );
		$back = array( 'page' => 'condoclock' );
		if ( 'cat' === $from || is_wp_error( $res ) ) {
			$back['cat'] = $cat;
		}
		$back['cc_msg'] = is_wp_error( $res ) ? 'reason' : ( $clear ? 'na_cleared' : 'na' );
		self::back( $back );
	}
}

<?php
/**
 * Notices: meetings with their notice, agenda and documents to be voted on.
 *
 * @package CondoClock
 */

namespace CondoClock;

defined( 'ABSPATH' ) || exit;

/**
 * Meeting notices, kept as the condoclock_meeting post type.
 */
class Notices {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_condoclock_notice', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Local "Y-m-d" + "H:i" to a UTC "Y-m-d H:i:s", or ''.
	 *
	 * @param string $date Date.
	 * @param string $time Time.
	 * @return string
	 */
	public static function to_utc( $date, $time ) {
		$d = \DateTime::createFromFormat( '!Y-m-d H:i', trim( $date ) . ' ' . trim( $time ), wp_timezone() );
		if ( ! $d || $d->format( 'Y-m-d H:i' ) !== trim( $date ) . ' ' . trim( $time ) ) {
			return '';
		}
		$d->setTimezone( new \DateTimeZone( 'UTC' ) );
		return $d->format( 'Y-m-d H:i:s' );
	}

	/**
	 * UTC "Y-m-d H:i:s" to a timestamp.
	 *
	 * @param string $utc UTC datetime.
	 * @return int
	 */
	public static function ts( $utc ) {
		return $utc ? (int) strtotime( $utc . ' UTC' ) : 0;
	}

	/**
	 * One meeting as an array.
	 *
	 * @param int|\WP_Post $post Meeting.
	 * @return array|null
	 */
	public static function get( $post ) {
		$post = get_post( $post );
		if ( ! $post || 'condoclock_meeting' !== $post->post_type ) {
			return null;
		}
		$docs = get_post_meta( $post->ID, '_cc_docs', true );
		$chk  = get_post_meta( $post->ID, '_cc_checklist', true );
		return array(
			'id'        => $post->ID,
			'title'     => $post->post_title,
			'published' => 'publish' === $post->post_status,
			'type'      => (string) get_post_meta( $post->ID, '_cc_type', true ),
			'start'     => (string) get_post_meta( $post->ID, '_cc_start', true ),
			'ts'        => self::ts( (string) get_post_meta( $post->ID, '_cc_start', true ) ),
			'place'     => (string) get_post_meta( $post->ID, '_cc_place', true ),
			'video'     => (string) get_post_meta( $post->ID, '_cc_video', true ),
			'docs'      => is_array( $docs ) ? $docs : array(),
			'checklist' => is_array( $chk ) ? $chk : array(),
		);
	}

	/**
	 * Meetings, soonest first.
	 *
	 * @param string $when   upcoming|past|all.
	 * @param bool   $is_public Only those shown on the Notices page.
	 * @return array
	 */
	public static function query( $when = 'upcoming', $is_public = true ) {
		$posts = get_posts(
			array(
				'post_type'        => 'condoclock_meeting',
				'post_status'      => $is_public ? 'publish' : array( 'publish', 'draft' ),
				'numberposts'      => 200, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_numberposts -- one association rarely has 200 meetings; the list is capped on purpose.
				'suppress_filters' => false,
			)
		);
		$now   = time();
		$out   = array();
		foreach ( $posts as $p ) {
			$m = self::get( $p );
			if ( ! $m['ts'] ) {
				continue;
			}
			if ( ( 'upcoming' === $when && $m['ts'] < $now ) || ( 'past' === $when && $m['ts'] >= $now ) ) {
				continue;
			}
			$out[] = $m;
		}
		usort(
			$out,
			static function ( $a, $b ) use ( $when ) {
				return 'past' === $when ? $b['ts'] - $a['ts'] : $a['ts'] - $b['ts'];
			}
		);
		return $out;
	}

	/**
	 * Creates or updates a meeting notice.
	 *
	 * @param array $args title, type, date, time, place, video, publish (bool).
	 * @param int   $id   Meeting to update, or 0.
	 * @return int|\WP_Error
	 */
	public static function save( array $args, $id = 0 ) {
		$args  = wp_parse_args(
			$args,
			array(
				'title'   => '',
				'type'    => '',
				'date'    => '',
				'time'    => '',
				'place'   => '',
				'video'   => '',
				'publish' => true,
			)
		);
		$start = self::to_utc( $args['date'], $args['time'] );
		if ( ! $start ) {
			return new \WP_Error( 'condoclock_when', __( 'Enter the meeting date and time.', 'condoclock' ) );
		}
		$types = Rules::meeting_types();
		$type  = isset( $types[ $args['type'] ] ) ? $args['type'] : '';
		$title = trim( sanitize_text_field( $args['title'] ) );
		if ( '' === $title ) {
			$title = $type ? $types[ $type ]['name'] : __( 'Meeting', 'condoclock' );
		}
		$data = array(
			'post_type'   => 'condoclock_meeting',
			'post_title'  => mb_substr( $title, 0, 200 ),
			'post_status' => $args['publish'] ? 'publish' : 'draft',
		);
		if ( $id ) {
			$data['ID'] = $id;
			$res        = wp_update_post( $data, true );
		} else {
			$data['post_author'] = get_current_user_id();
			$res                 = wp_insert_post( $data, true );
		}
		if ( is_wp_error( $res ) ) {
			return $res;
		}
		update_post_meta( $res, '_cc_type', $type );
		update_post_meta( $res, '_cc_start', $start );
		update_post_meta( $res, '_cc_place', mb_substr( sanitize_text_field( $args['place'] ), 0, 300 ) );
		update_post_meta( $res, '_cc_video', $args['video'] ? esc_url_raw( $args['video'], array( 'http', 'https' ) ) : '' );
		/**
		 * Fires after a meeting notice is saved.
		 *
		 * @param int  $id     Meeting ID.
		 * @param bool $is_new Whether it was just created.
		 */
		do_action( 'condoclock_notice_saved', (int) $res, ! $id );
		return (int) $res;
	}

	/**
	 * Stores a document (notice, agenda or vote-N) for a meeting.
	 *
	 * @param int    $id   Meeting.
	 * @param string $slot notice|agenda|voteN.
	 * @param array  $info File info.
	 */
	public static function set_doc( $id, $slot, array $info ) {
		$docs = get_post_meta( $id, '_cc_docs', true );
		$docs = is_array( $docs ) ? $docs : array();
		if ( isset( $docs[ $slot ] ) ) {
			Files::delete( $docs[ $slot ] );
		}
		$docs[ $slot ] = $info;
		update_post_meta( $id, '_cc_docs', $docs );
		/**
		 * Fires after a meeting document is added.
		 *
		 * @param int    $id   Meeting ID.
		 * @param string $slot notice|agenda|voteN.
		 */
		do_action( 'condoclock_notice_doc', $id, $slot );
	}

	/**
	 * Deletes a meeting and its documents.
	 *
	 * @param int $id Meeting.
	 */
	public static function delete( $id ) {
		$docs = get_post_meta( $id, '_cc_docs', true );
		foreach ( is_array( $docs ) ? $docs : array() as $info ) {
			Files::delete( $info );
		}
		wp_delete_post( $id, true );
	}

	/**
	 * Saves or deletes a notice from the board's form.
	 */
	public static function handle() {
		if ( ! condoclock_can_manage() ) {
			wp_die( esc_html__( 'You don\'t have permission to manage notices.', 'condoclock' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'condoclock_notice' );
		$id   = isset( $_POST['meeting'] ) ? absint( $_POST['meeting'] ) : 0;
		$do   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : 'save';
		$page = isset( $_POST['from'] ) && 'condoclock-meetings' === $_POST['from'] ? 'condoclock-meetings' : 'condoclock-notices';
		$args = array( 'page' => $page );
		if ( $id && 'condoclock_meeting' !== get_post_type( $id ) ) {
			$id = 0;
		}
		if ( 'delete' === $do && $id ) {
			self::delete( $id );
			$args['cc_msg'] = 'notice_deleted';
		} else {
			$res = self::save(
				array(
					'title'   => isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '',
					'type'    => isset( $_POST['type'] ) ? sanitize_key( wp_unslash( $_POST['type'] ) ) : '',
					'date'    => isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '',
					'time'    => isset( $_POST['time'] ) ? sanitize_text_field( wp_unslash( $_POST['time'] ) ) : '',
					'place'   => isset( $_POST['place'] ) ? sanitize_text_field( wp_unslash( $_POST['place'] ) ) : '',
					'video'   => isset( $_POST['video'] ) ? esc_url_raw( wp_unslash( $_POST['video'] ) ) : '',
					/**
					 * Whether a saved notice shows on the Notices page straight away. Pro holds meetings until the notice is ready.
					 *
					 * @param bool $publish Publish now.
					 * @param int  $id      Meeting ID, or 0 for a new one.
					 */
					'publish' => apply_filters( 'condoclock_publish_notice', true, $id ),
				),
				$id
			);
			if ( is_wp_error( $res ) ) {
				set_transient( 'condoclock_errors_' . get_current_user_id(), array( $res->get_error_message() ), 300 );
				$args['cc_err'] = 1;
			} else {
				$errors = array();
				foreach ( array( 'notice', 'agenda', 'vote' ) as $slot ) {
					// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized,WordPress.Security.ValidatedSanitizedInput.MissingUnslash -- wp_handle_upload checks each file.
					$f = isset( $_FILES[ 'doc_' . $slot ] ) ? $_FILES[ 'doc_' . $slot ] : null;
					if ( ! $f || UPLOAD_ERR_NO_FILE === (int) $f['error'] ) {
						continue;
					}
					$info = Files::store_upload( $f );
					if ( is_wp_error( $info ) ) {
						$errors[] = $info->get_error_message();
						continue;
					}
					self::set_doc( $res, $slot, $info );
				}
				if ( $errors ) {
					set_transient( 'condoclock_errors_' . get_current_user_id(), $errors, 300 );
					$args['cc_err'] = 1;
				}
				$args['cc_msg'] = 'notice_saved';
			}
		}
		Records::changed( 0, 'notice' );
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}

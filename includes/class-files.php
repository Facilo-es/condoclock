<?php
/**
 * Private files: storage outside the public media library, protected streaming, and the self-test.
 *
 * @package CondoClock
 */

namespace CondoClock;

defined( 'ABSPATH' ) || exit;

/**
 * Stores record files in a private folder and serves them only to those allowed.
 */
class Files {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'maybe_serve' ), 20 );
	}

	/**
	 * File types boards can upload.
	 *
	 * @return array ext regex => mime.
	 */
	public static function mimes() {
		return array(
			'pdf'      => 'application/pdf',
			'docx'     => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
			'xlsx'     => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
			'jpg|jpeg' => 'image/jpeg',
			'png'      => 'image/png',
			'txt'      => 'text/plain',
		);
	}

	/**
	 * Base of the private folder (parent of the random folder).
	 *
	 * @return array array( dir, url ).
	 */
	private static function base() {
		$up = wp_upload_dir( null, false );
		return array( $up['basedir'] . '/condoclock-private', $up['baseurl'] . '/condoclock-private' );
	}

	/**
	 * The private folder, created with its deny files on first use.
	 *
	 * @return array array( path, url ).
	 */
	public static function dir() {
		$rand = get_option( 'condoclock_private_dir' );
		if ( ! $rand || ! preg_match( '/^[a-z0-9]{20}$/', $rand ) ) {
			$rand = strtolower( wp_generate_password( 20, false, false ) );
			update_option( 'condoclock_private_dir', $rand, false );
		}
		list( $base, $base_url ) = self::base();
		$path                    = $base . '/' . $rand;
		if ( ! is_dir( $path ) ) {
			wp_mkdir_p( $path );
		}
		self::protect( $base );
		self::protect( $path );
		return array( $path, $base_url . '/' . $rand );
	}

	/**
	 * Writes the deny file and an empty index into a folder, if missing.
	 *
	 * @param string $path Folder.
	 * @param bool   $force Rewrite even if present.
	 */
	public static function protect( $path, $force = false ) {
		$deny = "# CondoClock: files here are served only to logged-in owners.\n<IfModule mod_authz_core.c>\n\tRequire all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n\tOrder allow,deny\n\tDeny from all\n</IfModule>\n";
		if ( $force || ! file_exists( $path . '/.htaccess' ) ) {
			file_put_contents( $path . '/.htaccess', $deny ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- small fixed file in our own folder.
		}
		if ( ! file_exists( $path . '/index.php' ) ) {
			file_put_contents( $path . '/index.php', "<?php\n// Silence is golden.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- small fixed file in our own folder.
		}
	}

	/**
	 * Largest upload the server takes.
	 *
	 * @return int Bytes.
	 */
	public static function max_size() {
		return (int) wp_max_upload_size();
	}

	/**
	 * Stores one uploaded file from $_FILES.
	 *
	 * @param array $file One entry of $_FILES (already unslashed and normalised).
	 * @return array|\WP_Error File info.
	 */
	public static function store_upload( array $file ) {
		list( $path ) = self::dir();
		$name         = sanitize_file_name( isset( $file['name'] ) ? $file['name'] : 'file' );
		$dir_filter   = static function ( $dirs ) use ( $path ) {
			$dirs['path']   = $path;
			$dirs['subdir'] = '';
			$dirs['url']    = '';
			return $dirs;
		};
		add_filter( 'upload_dir', $dir_filter );
		$result = wp_handle_upload(
			$file,
			array(
				'test_form'                => false,
				'mimes'                    => self::mimes(),
				'unique_filename_callback' => array( __CLASS__, 'random_name' ),
			)
		);
		remove_filter( 'upload_dir', $dir_filter );
		if ( isset( $result['error'] ) ) {
			return new \WP_Error( 'condoclock_upload', $result['error'] );
		}
		return self::info( $result['file'], $name, $result['type'] );
	}

	/**
	 * Copies a local file into the private folder (for imports and tests).
	 *
	 * @param string $source Local path.
	 * @param string $name   Original name.
	 * @return array|\WP_Error File info.
	 */
	public static function store_copy( $source, $name ) {
		$name  = sanitize_file_name( $name );
		$check = wp_check_filetype_and_ext( $source, $name, self::mimes() );
		if ( empty( $check['ext'] ) || empty( $check['type'] ) ) {
			return new \WP_Error( 'condoclock_type', __( 'This type of file can\'t be posted. Use PDF, Word (DOCX), Excel (XLSX), JPG, PNG or text.', 'condoclock' ) );
		}
		list( $path ) = self::dir();
		$target       = $path . '/' . self::random_name( $path, $name, '.' . $check['ext'] );
		if ( ! copy( $source, $target ) ) {
			return new \WP_Error( 'condoclock_copy', __( 'The file could not be saved. Check the uploads folder can be written to.', 'condoclock' ) );
		}
		return self::info( $target, $name, $check['type'] );
	}

	/**
	 * Random name on disk, keeping only the extension.
	 *
	 * @param string $dir  Folder.
	 * @param string $name Original name.
	 * @param string $ext  Extension with dot.
	 * @return string
	 */
	public static function random_name( $dir, $name, $ext ) {
		unset( $dir, $name );
		return strtolower( wp_generate_password( 24, false, false ) ) . strtolower( $ext );
	}

	/**
	 * File info array kept in post meta.
	 *
	 * @param string $abs  Absolute path.
	 * @param string $name Original name.
	 * @param string $mime Type.
	 * @return array
	 */
	private static function info( $abs, $name, $mime ) {
		list( $path ) = self::dir();
		return array(
			'rel'    => ltrim( str_replace( $path, '', $abs ), '/' ),
			'name'   => $name,
			'mime'   => $mime,
			'size'   => (int) filesize( $abs ),
			'sha256' => hash_file( 'sha256', $abs ),
			'added'  => gmdate( 'Y-m-d H:i:s' ),
		);
	}

	/**
	 * Absolute path of a stored file, or '' when unsafe or missing.
	 *
	 * @param array $info File info.
	 * @return string
	 */
	public static function path( $info ) {
		if ( empty( $info['rel'] ) || ! preg_match( '/^[a-z0-9]+\.[a-z0-9]+$/', $info['rel'] ) ) {
			return '';
		}
		list( $path ) = self::dir();
		$abs          = $path . '/' . $info['rel'];
		return is_file( $abs ) ? $abs : '';
	}

	/**
	 * Deletes a stored file.
	 *
	 * @param array $info File info.
	 */
	public static function delete( $info ) {
		$abs = self::path( $info );
		if ( $abs ) {
			wp_delete_file( $abs );
		}
	}

	/**
	 * Short type label, such as PDF.
	 *
	 * @param array $info File info.
	 * @return string
	 */
	public static function type_label( $info ) {
		$ext = strtoupper( pathinfo( isset( $info['name'] ) ? $info['name'] : '', PATHINFO_EXTENSION ) );
		return 'JPEG' === $ext ? 'JPG' : $ext;
	}

	/**
	 * The protected link to a record's file or a meeting document.
	 *
	 * @param int    $post_id Record or meeting.
	 * @param string $part    Earlier version index for records, or notice/agenda/vote-N for meetings.
	 * @return string
	 */
	public static function url( $post_id, $part = '' ) {
		$args = array( 'condoclock_file' => (int) $post_id );
		if ( '' !== $part ) {
			$args['part'] = $part;
		}
		$public = 'condoclock_meeting' === get_post_type( $post_id ) && self::notices_public();
		if ( ! $public ) {
			$args['_wpnonce'] = wp_create_nonce( 'condoclock_file_' . (int) $post_id );
		}
		return add_query_arg( $args, home_url( '/' ) );
	}

	/**
	 * Are notices public?
	 *
	 * @return bool
	 */
	public static function notices_public() {
		$s = condoclock_settings();
		return ! empty( $s['notices_public'] );
	}

	/**
	 * Serves a file when the request asks for one.
	 */
	public static function maybe_serve() {
		if ( ! isset( $_GET['condoclock_file'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the nonce is checked below for private files.
			return;
		}
		$id   = absint( $_GET['condoclock_file'] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$part = isset( $_GET['part'] ) ? sanitize_key( wp_unslash( $_GET['part'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$post = get_post( $id );
		$info = $post ? self::resolve( $post, $part ) : null;
		if ( ! $info ) {
			self::deny( 404 );
		}
		if ( ! self::allowed( $post ) ) {
			self::deny( 403 );
		}
		$abs = self::path( $info );
		if ( ! $abs ) {
			self::deny( 404 );
		}
		/**
		 * Fires before a file is sent. CondoClock Pro counts views here, without storing who.
		 *
		 * @param int    $id   Record or meeting ID.
		 * @param string $part Version or document.
		 */
		do_action( 'condoclock_file_served', $id, $part );
		self::send( $abs, $info );
	}

	/**
	 * Finds the file info a request asks for.
	 *
	 * @param \WP_Post $post Record or meeting.
	 * @param string   $part Part.
	 * @return array|null
	 */
	private static function resolve( $post, $part ) {
		if ( 'condoclock_record' === $post->post_type ) {
			if ( '' === $part ) {
				$info = get_post_meta( $post->ID, '_cc_file', true );
			} else {
				$versions = (array) get_post_meta( $post->ID, '_cc_versions', true );
				$info     = isset( $versions[ (int) $part ]['file'] ) ? $versions[ (int) $part ]['file'] : null;
			}
			return is_array( $info ) && $info ? $info : null;
		}
		if ( 'condoclock_meeting' === $post->post_type ) {
			$docs = (array) get_post_meta( $post->ID, '_cc_docs', true );
			return isset( $docs[ $part ] ) && is_array( $docs[ $part ] ) ? $docs[ $part ] : null;
		}
		return null;
	}

	/**
	 * May the current visitor open this file?
	 *
	 * @param \WP_Post $post Record or meeting.
	 * @return bool
	 */
	private static function allowed( $post ) {
		if ( condoclock_can_manage() ) {
			return true;
		}
		if ( 'condoclock_meeting' === $post->post_type ) {
			if ( 'publish' !== $post->post_status ) {
				return false;
			}
			if ( self::notices_public() ) {
				return true;
			}
		} elseif ( 'posted' !== get_post_meta( $post->ID, '_cc_status', true ) ) {
			return false;
		}
		$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- this is the nonce check.
		return current_user_can( 'condoclock_view_records' ) && wp_verify_nonce( $nonce, 'condoclock_file_' . $post->ID );
	}

	/**
	 * Stops with a plain error.
	 *
	 * @param int $code HTTP status.
	 */
	private static function deny( $code ) {
		nocache_headers();
		$msg = 403 === $code ? __( 'Log in as an owner to open this file. If you were logged in, go back to the records page and open it again.', 'condoclock' ) : __( 'This file is no longer here.', 'condoclock' );
		wp_die( esc_html( $msg ), esc_html__( 'Owners\' records', 'condoclock' ), array( 'response' => (int) $code ) );
	}

	/**
	 * Streams a file in chunks, with Range support.
	 *
	 * @param string $abs  Path.
	 * @param array  $info File info.
	 */
	private static function send( $abs, $info ) {
		$size   = (int) filesize( $abs );
		$start  = 0;
		$end    = $size - 1;
		$status = 200;
		if ( isset( $_SERVER['HTTP_RANGE'] ) && preg_match( '/^bytes=(\d*)-(\d*)$/', sanitize_text_field( wp_unslash( $_SERVER['HTTP_RANGE'] ) ), $m ) ) {
			if ( '' === $m[1] && '' !== $m[2] ) {
				$start = max( 0, $size - (int) $m[2] );
			} else {
				$start = (int) $m[1];
				$end   = '' !== $m[2] ? min( (int) $m[2], $size - 1 ) : $end;
			}
			if ( $start > $end || $start >= $size ) {
				status_header( 416 );
				header( 'Content-Range: bytes */' . $size );
				exit;
			}
			$status = 206;
		}
		$inline = in_array( $info['mime'], array( 'application/pdf', 'image/jpeg', 'image/png' ), true );
		$name   = str_replace( array( '"', "\r", "\n" ), '', $info['name'] );
		while ( ob_get_level() ) {
			ob_end_clean();
		}
		status_header( $status );
		header( 'Content-Type: ' . $info['mime'] );
		header( 'Content-Disposition: ' . ( $inline ? 'inline' : 'attachment' ) . '; filename="' . $name . '"; filename*=UTF-8\'\'' . rawurlencode( $info['name'] ) );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'Cache-Control: private, no-store' );
		header( 'Accept-Ranges: bytes' );
		header( 'Content-Length: ' . ( $end - $start + 1 ) );
		if ( 206 === $status ) {
			header( 'Content-Range: bytes ' . $start . '-' . $end . '/' . $size );
		}
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === $_SERVER['REQUEST_METHOD'] ) {
			exit;
		}
		$fh = fopen( $abs, 'rb' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fopen -- streaming a large file in chunks.
		fseek( $fh, $start );
		$left = $end - $start + 1;
		while ( $left > 0 && ! feof( $fh ) ) {
			$chunk = fread( $fh, (int) min( 1048576, $left ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fread
			echo $chunk; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- binary file contents.
			$left -= strlen( $chunk );
			flush();
		}
		fclose( $fh ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_fclose
		exit;
	}

	/**
	 * Checks that a file in the private folder can't be opened directly.
	 *
	 * @return array array( result => protected|exposed|unknown, code, time ).
	 */
	public static function self_test() {
		list( $path, $url ) = self::dir();
		$test               = $path . '/condoclock-selftest.txt';
		if ( ! file_exists( $test ) ) {
			file_put_contents( $test, "CondoClock self-test. If you can read this in a browser, private files are open to anyone.\n" ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- tiny test file.
		}
		/**
		 * The direct address the self-test asks for. Useful when the site can't reach its own public address.
		 *
		 * @param string $url Direct link to the test file.
		 */
		$target   = apply_filters( 'condoclock_selftest_url', $url . '/condoclock-selftest.txt' );
		$response = wp_remote_get(
			$target,
			array(
				'timeout'     => 10,
				'redirection' => 0,
				'sslverify'   => false,
			)
		);
		$code     = is_wp_error( $response ) ? 0 : (int) wp_remote_retrieve_response_code( $response );
		$body     = is_wp_error( $response ) ? '' : wp_remote_retrieve_body( $response );
		if ( 200 === $code && false !== strpos( $body, 'CondoClock self-test' ) ) {
			$result = 'exposed';
		} elseif ( in_array( $code, array( 401, 403, 404 ), true ) ) {
			$result = 'protected';
		} else {
			$result = 'unknown';
		}
		$out = array(
			'result' => $result,
			'code'   => $code,
			'time'   => time(),
		);
		update_option( 'condoclock_selftest', $out, false );
		return $out;
	}

	/**
	 * The nginx rule hosts that ignore .htaccess need.
	 *
	 * @return string
	 */
	public static function nginx_rule() {
		$up  = wp_upload_dir( null, false );
		$rel = wp_parse_url( $up['baseurl'], PHP_URL_PATH );
		return 'location ^~ ' . rtrim( (string) $rel, '/' ) . "/condoclock-private/ {\n    deny all;\n    return 403;\n}";
	}
}

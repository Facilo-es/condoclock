<?php
/**
 * Owners: roles, adding owners by hand, removing access, employees.
 *
 * @package CondoClock
 */

namespace CondoClock;

defined( 'ABSPATH' ) || exit;

/**
 * Owner accounts are ordinary WordPress users with the CondoClock owner role.
 */
class Owners {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_condoclock_add_owner', array( __CLASS__, 'handle_add' ) );
		add_action( 'admin_post_condoclock_owner', array( __CLASS__, 'handle_action' ) );
		add_action( 'wp_login', array( __CLASS__, 'stamp_login' ), 10, 2 );
		if ( get_option( 'condoclock_roles_v' ) !== CONDOCLOCK_VERSION ) {
			add_action( 'init', array( __CLASS__, 'roles' ) );
		}
	}

	/**
	 * Activation: roles and the private folder.
	 */
	public static function activate() {
		self::roles();
		Files::dir();
	}

	/**
	 * Adds the roles and gives administrators both capabilities.
	 */
	public static function roles() {
		add_role(
			'condoclock_board',
			__( 'Board member (CondoClock)', 'condoclock' ),
			array(
				'read'                    => true,
				'upload_files'            => true,
				'condoclock_manage'       => true,
				'condoclock_view_records' => true,
			)
		);
		add_role(
			'condoclock_owner',
			__( 'Owner (CondoClock)', 'condoclock' ),
			array(
				'read'                    => true,
				'condoclock_view_records' => true,
			)
		);
		$admin = get_role( 'administrator' );
		if ( $admin ) {
			$admin->add_cap( 'condoclock_manage' );
			$admin->add_cap( 'condoclock_view_records' );
		}
		update_option( 'condoclock_roles_v', CONDOCLOCK_VERSION, false );
	}

	/**
	 * Remembers each owner's last login (shown to the board).
	 *
	 * @param string   $login User login.
	 * @param \WP_User $user  User.
	 */
	public static function stamp_login( $login, $user ) {
		unset( $login );
		if ( $user instanceof \WP_User && $user->has_cap( 'condoclock_view_records' ) ) {
			update_user_meta( $user->ID, 'condoclock_last_login', time() );
		}
	}

	/**
	 * Owners and employees with access.
	 *
	 * @return \WP_User[]
	 */
	public static function all() {
		$owners = get_users(
			array(
				'role__in' => array( 'condoclock_owner' ),
				'orderby'  => 'display_name',
			)
		);
		$staff  = get_users(
			array(
				'meta_key'   => 'condoclock_employee', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- small list on an admin screen.
				'meta_value' => '1', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);
		$out    = array();
		foreach ( array_merge( $owners, $staff ) as $u ) {
			$out[ $u->ID ] = $u;
		}
		return array_values( $out );
	}

	/**
	 * Adds an owner and sends the set-password email.
	 *
	 * @param string $name  Name.
	 * @param string $email Email.
	 * @param string $unit  Unit or lot.
	 * @param array  $extra Extra user meta (Pro: invited_by, approved_by).
	 * @return int|\WP_Error User ID.
	 */
	public static function add( $name, $email, $unit, array $extra = array() ) {
		$email = sanitize_email( $email );
		$name  = trim( sanitize_text_field( $name ) );
		$unit  = trim( sanitize_text_field( $unit ) );
		if ( ! is_email( $email ) ) {
			return new \WP_Error( 'condoclock_email', __( 'Enter a valid email address.', 'condoclock' ) );
		}
		if ( '' === $name ) {
			return new \WP_Error( 'condoclock_name', __( 'Enter the owner\'s name.', 'condoclock' ) );
		}
		$existing = get_user_by( 'email', $email );
		if ( $existing ) {
			if ( $existing->has_cap( 'condoclock_view_records' ) ) {
				return new \WP_Error( 'condoclock_exists', __( 'This email already has access.', 'condoclock' ) );
			}
			$existing->add_role( 'condoclock_owner' );
			update_user_meta( $existing->ID, 'condoclock_unit', $unit );
			self::meta( $existing->ID, $extra );
			self::send_invite( $existing->ID );
			/** This action is documented below. */
			do_action( 'condoclock_owner_added', $existing->ID );
			return $existing->ID;
		}
		$login = sanitize_user( strtolower( strstr( $email, '@', true ) ), true );
		$login = $login ? $login : 'owner';
		$try   = $login;
		$n     = 1;
		while ( username_exists( $try ) ) {
			$try = $login . ( ++$n );
		}
		$parts = explode( ' ', $name, 2 );
		$id    = wp_insert_user(
			array(
				'user_login'   => $try,
				'user_email'   => $email,
				'user_pass'    => wp_generate_password( 24 ),
				'display_name' => $name,
				'first_name'   => $parts[0],
				'last_name'    => isset( $parts[1] ) ? $parts[1] : '',
				'role'         => 'condoclock_owner',
			)
		);
		if ( is_wp_error( $id ) ) {
			return $id;
		}
		update_user_meta( $id, 'condoclock_unit', $unit );
		update_user_meta( $id, 'condoclock_invited', time() );
		self::meta( $id, $extra );
		self::send_invite( $id );
		/**
		 * Fires after an owner gets access.
		 *
		 * @param int $id User ID.
		 */
		do_action( 'condoclock_owner_added', $id );
		return $id;
	}

	/**
	 * Saves extra user meta with the condoclock_ prefix.
	 *
	 * @param int   $id    User.
	 * @param array $extra Meta.
	 */
	private static function meta( $id, array $extra ) {
		foreach ( $extra as $k => $v ) {
			update_user_meta( $id, 'condoclock_' . sanitize_key( $k ), $v );
		}
	}

	/**
	 * Sends WordPress's set-password link with CondoClock's own subject and intro.
	 *
	 * @param int $id User.
	 * @return bool
	 */
	public static function send_invite( $id ) {
		$user = get_userdata( $id );
		if ( ! $user ) {
			return false;
		}
		$key = get_password_reset_key( $user );
		if ( is_wp_error( $key ) ) {
			return false;
		}
		$s       = condoclock_settings();
		$assoc   = $s['name'] ? $s['name'] : get_bloginfo( 'name' );
		$link    = network_site_url( 'wp-login.php?action=rp&key=' . $key . '&login=' . rawurlencode( $user->user_login ), 'login' );
		$records = ! empty( $s['pages']['records'] ) ? get_permalink( $s['pages']['records'] ) : home_url( '/' );
		/* translators: %s: association name. */
		$subject = sprintf( __( 'Your login for %s owners\' records', 'condoclock' ), $assoc );
		/* translators: 1: owner's name, 2: association name, 3: set-password link, 4: records page address. */
		$body = sprintf( __( 'Hello %1$s, your request for access to the owners\' records of %2$s was approved. Set your password here: %3$s. After that, you\'ll find every record at %4$s.', 'condoclock' ), $user->display_name, $assoc, $link, $records );
		update_user_meta( $id, 'condoclock_invited', time() );
		return wp_mail( $user->user_email, wp_specialchars_decode( $subject, ENT_QUOTES ), $body );
	}

	/**
	 * Removes an owner's access but keeps the user.
	 *
	 * @param int $id User.
	 */
	public static function remove( $id ) {
		$user = get_userdata( $id );
		if ( ! $user ) {
			return;
		}
		$user->remove_role( 'condoclock_owner' );
		delete_user_meta( $id, 'condoclock_employee' );
		$user->remove_cap( 'condoclock_view_records' );
		if ( ! $user->roles ) {
			$user->set_role( get_option( 'default_role', 'subscriber' ) );
		}
		/**
		 * Fires after an owner's access is removed.
		 *
		 * @param int $id User ID.
		 */
		do_action( 'condoclock_owner_removed', $id );
	}

	/**
	 * Gives an existing user (an employee) access, or takes it away.
	 *
	 * @param int  $id User.
	 * @param bool $on Give or take.
	 */
	public static function employee( $id, $on ) {
		$user = get_userdata( $id );
		if ( ! $user ) {
			return;
		}
		if ( $on ) {
			$user->add_cap( 'condoclock_view_records' );
			update_user_meta( $id, 'condoclock_employee', '1' );
			/** This action is documented in Owners::add(). */
			do_action( 'condoclock_owner_added', $id );
		} else {
			self::remove( $id );
		}
	}

	/**
	 * Adds an owner from the board's form.
	 */
	public static function handle_add() {
		if ( ! condoclock_can_manage() ) {
			wp_die( esc_html__( 'You don\'t have permission to manage owners.', 'condoclock' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'condoclock_add_owner' );
		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$email = isset( $_POST['email'] ) ? sanitize_email( wp_unslash( $_POST['email'] ) ) : '';
		$unit  = isset( $_POST['unit'] ) ? sanitize_text_field( wp_unslash( $_POST['unit'] ) ) : '';
		$res   = self::add( $name, $email, $unit, array( 'approved_by' => get_current_user_id() ) );
		$args  = array( 'page' => 'condoclock-owners' );
		if ( is_wp_error( $res ) ) {
			set_transient( 'condoclock_errors_' . get_current_user_id(), array( $res->get_error_message() ), 300 );
			$args['cc_err'] = 1;
		} else {
			$args['cc_msg'] = 'owner_added';
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Remove access, resend the invite, or add an employee.
	 */
	public static function handle_action() {
		if ( ! condoclock_can_manage() ) {
			wp_die( esc_html__( 'You don\'t have permission to manage owners.', 'condoclock' ), '', array( 'response' => 403 ) );
		}
		check_admin_referer( 'condoclock_owner' );
		$id   = isset( $_POST['user'] ) ? absint( $_POST['user'] ) : 0;
		$do   = isset( $_POST['do'] ) ? sanitize_key( wp_unslash( $_POST['do'] ) ) : '';
		$args = array( 'page' => 'condoclock-owners' );
		if ( 'employee' === $do && isset( $_POST['login'] ) ) {
			$who = sanitize_text_field( wp_unslash( $_POST['login'] ) );
			$u   = is_email( $who ) ? get_user_by( 'email', $who ) : get_user_by( 'login', $who );
			if ( $u ) {
				self::employee( $u->ID, true );
				$args['cc_msg'] = 'employee';
			} else {
				set_transient( 'condoclock_errors_' . get_current_user_id(), array( __( 'No user has that username or email. Add them in Users first.', 'condoclock' ) ), 300 );
				$args['cc_err'] = 1;
			}
		} elseif ( $id && get_current_user_id() !== $id ) {
			if ( 'remove' === $do ) {
				self::remove( $id );
				$args['cc_msg'] = 'removed';
			} elseif ( 'resend' === $do ) {
				self::send_invite( $id );
				$args['cc_msg'] = 'resent';
			}
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}
}

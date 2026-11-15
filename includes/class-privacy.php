<?php
/**
 * WordPress privacy tools: personal data exporter, eraser and policy text.
 *
 * @package CondoClock
 */

namespace CondoClock;

defined( 'ABSPATH' ) || exit;

/**
 * Owners' personal data.
 */
class Privacy {

	/**
	 * User meta CondoClock keeps, with labels.
	 *
	 * @return array
	 */
	private static function fields() {
		return array(
			'condoclock_unit'        => __( 'Unit or lot number', 'condoclock' ),
			'condoclock_employee'    => __( 'Employed by the association', 'condoclock' ),
			'condoclock_invited'     => __( 'Login sent', 'condoclock' ),
			'condoclock_last_login'  => __( 'Last login to owners\' records', 'condoclock' ),
			'condoclock_approved_by' => __( 'User ID that gave access', 'condoclock' ),
		);
	}

	/**
	 * Hooks.
	 */
	public static function init() {
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'erasers' ) );
		add_action( 'admin_init', array( __CLASS__, 'policy' ) );
	}

	/**
	 * Registers the exporter.
	 *
	 * @param array $registered Exporters.
	 * @return array
	 */
	public static function exporters( $registered ) {
		$registered['condoclock'] = array(
			'exporter_friendly_name' => __( 'CondoClock owner access', 'condoclock' ),
			'callback'               => array( __CLASS__, 'export' ),
		);
		return $registered;
	}

	/**
	 * Registers the eraser.
	 *
	 * @param array $registered Erasers.
	 * @return array
	 */
	public static function erasers( $registered ) {
		$registered['condoclock'] = array(
			'eraser_friendly_name' => __( 'CondoClock owner access', 'condoclock' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);
		return $registered;
	}

	/**
	 * Exports an owner's CondoClock data.
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	public static function export( $email ) {
		$user = get_user_by( 'email', $email );
		$data = array();
		if ( $user ) {
			foreach ( self::fields() as $key => $label ) {
				$value = get_user_meta( $user->ID, $key, true );
				if ( '' === $value || false === $value ) {
					continue;
				}
				if ( in_array( $key, array( 'condoclock_invited', 'condoclock_last_login' ), true ) ) {
					$value = condoclock_datetime( (int) $value );
				} elseif ( 'condoclock_employee' === $key ) {
					$value = $value ? __( 'Yes', 'condoclock' ) : __( 'No', 'condoclock' );
				}
				$data[] = array(
					'name'  => $label,
					'value' => (string) $value,
				);
			}
		}
		$items = array();
		if ( $data ) {
			$items[] = array(
				'group_id'    => 'condoclock',
				'group_label' => __( 'Owners\' records access', 'condoclock' ),
				'item_id'     => 'condoclock-' . $user->ID,
				'data'        => $data,
			);
		}
		/**
		 * Extra export items (CondoClock Pro adds access requests).
		 *
		 * @param array  $items Export items.
		 * @param string $email Email address.
		 */
		return array(
			'data' => apply_filters( 'condoclock_privacy_export', $items, $email ),
			'done' => true,
		);
	}

	/**
	 * Erases an owner's CondoClock data. The WordPress user stays; its owner role goes.
	 *
	 * @param string $email Email address.
	 * @return array
	 */
	public static function erase( $email ) {
		$user    = get_user_by( 'email', $email );
		$removed = false;
		if ( $user ) {
			foreach ( array_keys( self::fields() ) as $key ) {
				$removed = delete_user_meta( $user->ID, $key ) || $removed;
			}
			if ( in_array( 'condoclock_owner', (array) $user->roles, true ) ) {
				$user->remove_role( 'condoclock_owner' );
				$removed = true;
			}
		}
		/**
		 * Fires when an owner's data is erased (CondoClock Pro removes access requests).
		 *
		 * @param string $email Email address.
		 */
		do_action( 'condoclock_privacy_erase', $email );
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * Suggested privacy policy text.
	 */
	public static function policy() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text  = '<p>' . __( 'If you are an owner, the association keeps your name, email address and unit or lot number so you can log in to the owners\' records. We record when your login was sent and when you last opened the records.', 'condoclock' ) . '</p>';
		$text .= '<p>' . __( 'This information stays on this website. It is not shared with the makers of the CondoClock plugin. You can ask the association for a copy of it or to remove it.', 'condoclock' ) . '</p>';
		wp_add_privacy_policy_content( 'CondoClock', wp_kses_post( wpautop( $text, false ) ) );
	}
}

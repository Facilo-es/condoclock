<?php
/**
 * Status screen: file protection self-test, rule date, versions and the daily job.
 *
 * @package CondoClock
 */

namespace CondoClock;

defined( 'ABSPATH' ) || exit;

/**
 * Health checks.
 */
class Status {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'schedule' ) );
		add_action( 'condoclock_daily', array( __CLASS__, 'daily' ) );
		add_action( 'admin_post_condoclock_selftest', array( __CLASS__, 'handle_selftest' ) );
	}

	/**
	 * Next 07:00 in site time, as a timestamp.
	 *
	 * @return int
	 */
	public static function next_seven() {
		$tz   = wp_timezone();
		$now  = new \DateTimeImmutable( 'now', $tz );
		$next = $now->setTime( 7, 0 );
		if ( $next <= $now ) {
			$next = $next->modify( '+1 day' );
		}
		return $next->getTimestamp();
	}

	/**
	 * One daily event at 07:00 site time.
	 */
	public static function schedule() {
		if ( ! wp_next_scheduled( 'condoclock_daily' ) ) {
			wp_schedule_event( self::next_seven(), 'daily', 'condoclock_daily' );
		}
	}

	/**
	 * The daily job. Free only notes that it ran; CondoClock Pro sends reminders from here.
	 */
	public static function daily() {
		update_option( 'condoclock_cron_last', time(), false );
		/**
		 * Fires once a day at about 07:00 site time.
		 */
		do_action( 'condoclock_daily_run' );
	}

	/**
	 * Removes the daily event.
	 */
	public static function deactivate() {
		wp_clear_scheduled_hook( 'condoclock_daily' );
	}

	/**
	 * Is WP-Cron running?
	 *
	 * @return string ok, disabled or late.
	 */
	public static function cron_state() {
		if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
			$last = (int) get_option( 'condoclock_cron_last', 0 );
			return ( $last && time() - $last < 2 * DAY_IN_SECONDS ) ? 'ok' : 'disabled';
		}
		$next = wp_next_scheduled( 'condoclock_daily' );
		if ( $next && $next < time() - 2 * HOUR_IN_SECONDS ) {
			return 'late';
		}
		return 'ok';
	}

	/**
	 * The line a host adds to the server's crontab when WP-Cron is off.
	 *
	 * @return string
	 */
	public static function cron_line() {
		return '*/15 * * * * wget -q -O - ' . site_url( 'wp-cron.php?doing_wp_cron' ) . ' >/dev/null 2>&1';
	}

	/**
	 * Runs the self-test from the Status screen.
	 */
	public static function handle_selftest() {
		if ( ! condoclock_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'condoclock' ), 403 );
		}
		check_admin_referer( 'condoclock_selftest' );
		Files::self_test();
		wp_safe_redirect( admin_url( 'admin.php?page=condoclock-status' ) );
		exit;
	}

	/**
	 * The red banner when private files are open, or ''.
	 *
	 * @return string HTML.
	 */
	public static function exposed_banner() {
		$t = get_option( 'condoclock_selftest' );
		if ( ! is_array( $t ) || 'exposed' !== $t['result'] ) {
			return '';
		}
		$html  = '<div class="ccb-note bad" role="alert">' . condoclock_icon( 'alert' ) . '<div><p><b>' . esc_html__( 'Your server shows private files to anyone with the link', 'condoclock' ) . '</b></p>';
		$html .= '<p>' . esc_html__( 'Your host ignores the protection file CondoClock puts in the folder, which is common on nginx. Ask your host to add this rule to the site\'s nginx settings, then run the test again:', 'condoclock' ) . '</p>';
		$html .= '<pre class="ccb-code">' . esc_html( Files::nginx_rule() ) . '</pre></div></div>';
		return $html;
	}

	/**
	 * Renders the Status screen.
	 */
	public static function render() {
		global $wp_version;
		Admin::head( __( 'Status', 'condoclock' ), esc_html__( 'Checks that keep owners\' records private and on time.', 'condoclock' ) );
		echo '<div class="ccb-wrap">';
		echo wp_kses( self::exposed_banner(), Admin::kses() );

		$t    = get_option( 'condoclock_selftest' );
		$rows = array();

		if ( ! is_array( $t ) ) {
			$rows[] = array( 'dash', __( 'Private files', 'condoclock' ), __( 'Not tested yet.', 'condoclock' ) );
		} elseif ( 'protected' === $t['result'] ) {
			/* translators: %s: date and time of the test. */
			$rows[] = array( 'check', __( 'Private files', 'condoclock' ), sprintf( __( 'Protected. A visitor without a login gets "not allowed". Tested %s.', 'condoclock' ), condoclock_datetime( $t['time'] ) ) );
		} elseif ( 'exposed' === $t['result'] ) {
			$rows[] = array( 'alert', __( 'Private files', 'condoclock' ), __( 'Open to anyone with the link. See the steps above.', 'condoclock' ) );
		} else {
			/* translators: %d: HTTP status code, 0 when the site could not reach itself. */
			$rows[] = array( 'dash', __( 'Private files', 'condoclock' ), sprintf( __( 'The site could not test itself (answer %d). Files are still only sent to logged-in owners through CondoClock.', 'condoclock' ), (int) $t['code'] ) );
		}

		/* translators: %s: date of the rule table. */
		$rows[] = array( 'cal', __( 'Florida rules', 'condoclock' ), sprintf( __( 'Rules as of %s', 'condoclock' ), Rules::as_of() ) );
		/* translators: 1: CondoClock version, 2: WordPress version, 3: PHP version. */
		$rows[] = array( 'file', __( 'Versions', 'condoclock' ), sprintf( __( 'CondoClock %1$s · WordPress %2$s · PHP %3$s', 'condoclock' ), CONDOCLOCK_VERSION, $wp_version, PHP_VERSION ) );

		$cron = self::cron_state();
		if ( 'ok' === $cron ) {
			$next = wp_next_scheduled( 'condoclock_daily' );
			/* translators: %s: date and time of the next run. */
			$rows[] = array( 'check', __( 'Daily check', 'condoclock' ), $next ? sprintf( __( 'Working. Next run %s.', 'condoclock' ), condoclock_datetime( $next ) ) : __( 'Working.', 'condoclock' ) );
		} else {
			$rows[] = array( 'alert', __( 'Daily check', 'condoclock' ), 'disabled' === $cron ? __( 'WordPress\'s scheduled tasks are switched off on this site. Ask your host to add this line to the server\'s cron:', 'condoclock' ) : __( 'The daily check is running late, usually because the site gets few visits. Ask your host to add this line to the server\'s cron:', 'condoclock' ), self::cron_line() );
		}

		echo '<section class="ccb-card ccs-list"><ul>';
		foreach ( $rows as $r ) {
			echo '<li class="ccs-' . esc_attr( $r[0] ) . '">' . condoclock_icon( $r[0] ) . '<b>' . esc_html( $r[1] ) . '</b><span>' . esc_html( $r[2] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
			if ( ! empty( $r[3] ) ) {
				echo '<pre class="ccb-code">' . esc_html( $r[3] ) . '</pre>';
			}
			echo '</span></li>';
		}
		echo '</ul>';
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		wp_nonce_field( 'condoclock_selftest' );
		echo '<input type="hidden" name="action" value="condoclock_selftest"><button class="ccb-btn ghost">' . esc_html__( 'Test file protection again', 'condoclock' ) . '</button></form></section>';
		echo '</div>';
		Admin::foot();
	}
}

<?php
/**
 * Small shared helpers.
 *
 * @package CondoClock
 */

defined( 'ABSPATH' ) || exit;

/**
 * Settings with defaults.
 *
 * @return array
 */
function condoclock_settings() {
	$defaults = array(
		'mode'                => 'condo',
		'count'               => 0,
		'timeshare'           => 0,
		'name'                => get_bloginfo( 'name' ),
		'pages'               => array(),
		'style'               => 'clean',
		'accent'              => '',
		'logo'                => 0,
		'notices_public'      => 1,
		'show_na_reason'      => 1,
		'delete_on_uninstall' => 0,
		'setup_done'          => 0,
		'reminders'           => array(),
		'target_days_hoa'     => 30,
		'due_soon_days'       => 7,
		'buffer_days'         => 2,
		'periods'             => array(),
	);
	$saved    = get_option( 'condoclock_settings', array() );
	return array_merge( $defaults, is_array( $saved ) ? $saved : array() );
}

/**
 * Save some settings, keeping the rest.
 *
 * @param array $changes Keys to change.
 */
function condoclock_update_settings( array $changes ) {
	update_option( 'condoclock_settings', array_merge( condoclock_settings(), $changes ) );
	wp_cache_delete( 'condoclock_records_v', 'condoclock' );
}

/**
 * True when CondoClock Pro is active with a working licence.
 *
 * @return bool
 */
function condoclock_is_pro() {
	return (bool) apply_filters( 'condoclock_is_pro', false );
}

/**
 * Can the current user manage records?
 *
 * @return bool
 */
function condoclock_can_manage() {
	return current_user_can( 'condoclock_manage' ) || current_user_can( 'manage_options' );
}

/**
 * Date in the site's format, "6 Nov 2026" style.
 *
 * @param int|string $time Timestamp or UTC 'Y-m-d H:i:s'.
 * @return string
 */
function condoclock_date( $time ) {
	if ( ! is_numeric( $time ) ) {
		$time = strtotime( $time . ' UTC' );
	}
	/* translators: date format for posted dates, see https://www.php.net/manual/datetime.format.php */
	return wp_date( _x( 'j M Y', 'short date', 'condoclock' ), (int) $time );
}

/**
 * Date and time, "Mon 1 Mar 2027, 7:00 pm" style.
 *
 * @param int $time Timestamp.
 * @return string
 */
function condoclock_datetime( $time ) {
	/* translators: date and time format for deadlines, see https://www.php.net/manual/datetime.format.php */
	return wp_date( _x( 'D j M Y, g:i a', 'date and time', 'condoclock' ), (int) $time );
}

/**
 * Human file size.
 *
 * @param int $bytes Size.
 * @return string
 */
function condoclock_size( $bytes ) {
	return size_format( (int) $bytes, $bytes >= MB_IN_BYTES ? 1 : 0 );
}

/**
 * Inline SVG icon from the design set.
 *
 * @param string $name Icon name.
 * @return string Trusted markup.
 */
function condoclock_icon( $name ) {
	$p     = '<svg viewBox="0 0 16 16" width="14" height="14" aria-hidden="true" focusable="false" fill="none" stroke="currentColor" ';
	$icons = array(
		'check' => $p . 'stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3.5 8.5l3 3 6-7"/></svg>',
		'clock' => $p . 'stroke-width="2" stroke-linecap="round"><circle cx="8" cy="8" r="6"/><path d="M8 4.8V8l2.2 1.5"/></svg>',
		'alert' => $p . 'stroke-width="2.2" stroke-linecap="round"><circle cx="8" cy="8" r="6.2"/><path d="M8 4.6v4.1M8 11.3v.1"/></svg>',
		'dash'  => $p . 'stroke-width="2.2" stroke-linecap="round"><path d="M4 8h8"/></svg>',
		'empty' => $p . 'stroke-width="2" stroke-dasharray="2.4 2.2"><circle cx="8" cy="8" r="6"/></svg>',
		'file'  => $p . 'stroke-width="1.6" stroke-linejoin="round"><path d="M4 1.8h5l3 3v9.4H4z"/><path d="M9 1.8v3h3"/></svg>',
		'up'    => $p . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 11V3M4.5 6.5L8 3l3.5 3.5M3 13.5h10"/></svg>',
		'cal'   => $p . 'stroke-width="1.7"><rect x="2" y="3" width="12" height="11" rx="2"/><path d="M2 6.5h12M5.5 1.5v3M10.5 1.5v3"/></svg>',
		'lock'  => $p . 'stroke-width="1.7"><rect x="3" y="7" width="10" height="7.5" rx="1.5"/><path d="M5.5 7V5a2.5 2.5 0 015 0v2"/></svg>',
		'find'  => $p . 'stroke-width="1.8" stroke-linecap="round"><circle cx="7" cy="7" r="4.5"/><path d="M10.5 10.5L14 14"/></svg>',
		'down'  => $p . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 2.5v8M4.5 7L8 10.5 11.5 7M3 13.5h10"/></svg>',
		'go'    => $p . 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 8h10M9 4l4 4-4 4"/></svg>',
	);
	return isset( $icons[ $name ] ) ? $icons[ $name ] : '';
}

/**
 * Allowed tags for our icons in wp_kses.
 *
 * @return array
 */
function condoclock_kses_svg() {
	$common = array(
		'fill'             => true,
		'stroke'           => true,
		'stroke-width'     => true,
		'stroke-linecap'   => true,
		'stroke-linejoin'  => true,
		'stroke-dasharray' => true,
		'd'                => true,
	);
	return array(
		'svg'    => array_merge(
			$common,
			array(
				'viewbox'     => true,
				'width'       => true,
				'height'      => true,
				'aria-hidden' => true,
				'focusable'   => true,
				'class'       => true,
			)
		),
		'path'   => $common,
		'circle' => array_merge(
			$common,
			array(
				'cx' => true,
				'cy' => true,
				'r'  => true,
			)
		),
		'rect'   => array_merge(
			$common,
			array(
				'x'      => true,
				'y'      => true,
				'width'  => true,
				'height' => true,
				'rx'     => true,
			)
		),
	);
}

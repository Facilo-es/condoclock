<?php
/**
 * The Florida rule table: record categories, coverage and meeting types.
 *
 * @package CondoClock
 */

namespace CondoClock;

defined( 'ABSPATH' ) || exit;

/**
 * Reads rules/florida.json and adds translated names.
 */
class Rules {

	/**
	 * Parsed rule table.
	 *
	 * @var array|null
	 */
	private static $data = null;

	/**
	 * The whole rule table.
	 *
	 * @return array
	 */
	public static function data() {
		if ( null === self::$data ) {
			$json       = file_get_contents( CONDOCLOCK_DIR . 'rules/florida.json' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local file shipped with the plugin.
			self::$data = json_decode( (string) $json, true );
		}
		return self::$data;
	}

	/**
	 * Current mode, condo or hoa.
	 *
	 * @return string
	 */
	public static function mode() {
		$s = condoclock_settings();
		return 'hoa' === $s['mode'] ? 'hoa' : 'condo';
	}

	/**
	 * "5 Nov 2026" rule table date.
	 *
	 * @return string
	 */
	public static function as_of() {
		$d = self::data();
		return condoclock_date( strtotime( $d['as_of'] . ' 12:00:00 UTC' ) );
	}

	/**
	 * Is an association of this kind and size covered by the website rule?
	 *
	 * @param string $mode      condo or hoa.
	 * @param int    $count     Units or parcels.
	 * @param bool   $timeshare Condo contains timeshare units.
	 * @return bool
	 */
	public static function covered( $mode, $count, $timeshare = false ) {
		$count = (int) $count;
		if ( 'hoa' === $mode ) {
			return $count >= 100;
		}
		return $count >= 25 && ! $timeshare;
	}

	/**
	 * Translated names and hints, keyed by mode then category key.
	 *
	 * @return array
	 */
	private static function words() {
		return array(
			'condo' => array(
				'declaration'        => array( __( 'Declaration of condominium and amendments', 'condoclock' ), __( 'The document that created the condominium, with every amendment.', 'condoclock' ) ),
				'bylaws'             => array( __( 'Bylaws and amendments', 'condoclock' ), __( 'How the association is run: officers, meetings and voting.', 'condoclock' ) ),
				'articles'           => array( __( 'Articles of incorporation and amendments', 'condoclock' ), __( 'The papers filed with the state that created the association.', 'condoclock' ) ),
				'rules'              => array( __( 'Rules of the association', 'condoclock' ), __( 'The house rules owners and tenants follow.', 'condoclock' ) ),
				'minutes'            => array( __( 'Approved board minutes, last 12 months', 'condoclock' ), __( 'Minutes the board approved in the last 12 months.', 'condoclock' ) ),
				'video'              => array( __( 'Video recordings of video-conference meetings, last 12 months', 'condoclock' ), __( 'Links to recordings of meetings held by video, last 12 months.', 'condoclock' ) ),
				'contracts_bids'     => array( __( 'Current contracts and bids received in the past year', 'condoclock' ), __( 'Every current contract, and bids received in the last 12 months.', 'condoclock' ) ),
				'budget'             => array( __( 'Annual budget and proposed budget', 'condoclock' ), __( "This year's budget and any proposed budget.", 'condoclock' ) ),
				'financial'          => array( __( 'Annual financial report and monthly statements for meetings', 'condoclock' ), __( 'The yearly financial report, and statements shown at meetings.', 'condoclock' ) ),
				'director_cert'      => array( __( 'Director certifications', 'condoclock' ), __( "Each director's written certification after election.", 'condoclock' ) ),
				'director_contracts' => array( __( 'Contracts with directors, officers or their businesses', 'condoclock' ), __( 'Any contract with a director, an officer or their business.', 'condoclock' ) ),
				'conflict'           => array( __( 'Conflict-of-interest contracts', 'condoclock' ), __( 'Contracts where a director has a conflict of interest.', 'condoclock' ) ),
				'owner_notices'      => array( __( "Owners' meeting notices and agendas", 'condoclock' ), __( "Notices and agendas for owners' meetings.", 'condoclock' ) ),
				'board_notices'      => array( __( 'Board meeting notices, agendas and documents', 'condoclock' ), __( 'Notices, agendas and documents for board meetings.', 'condoclock' ) ),
				'inspections'        => array( __( 'Inspection reports (milestone, structural, life safety)', 'condoclock' ), __( 'Milestone, structural and life safety inspection reports.', 'condoclock' ) ),
				'sirs'               => array( __( 'Structural integrity reserve study (latest)', 'condoclock' ), __( 'The latest structural integrity reserve study.', 'condoclock' ) ),
				'permits'            => array( __( 'Building permits for ongoing or planned work', 'condoclock' ), __( "Permits for work that's going on or planned.", 'condoclock' ) ),
				'affidavits'         => array( __( 'Affidavits required by Chapter 718', 'condoclock' ), __( 'Affidavits that Chapter 718 asks the association to keep.', 'condoclock' ) ),
			),
			'hoa'   => array(
				'articles'           => array( __( 'Articles of incorporation and amendments', 'condoclock' ), __( 'The papers filed with the state that created the association.', 'condoclock' ) ),
				'bylaws'             => array( __( 'Bylaws and amendments', 'condoclock' ), __( 'How the association is run: officers, meetings and voting.', 'condoclock' ) ),
				'declaration'        => array( __( 'Declaration of covenants and amendments', 'condoclock' ), __( 'The covenants that bind every parcel, with every amendment.', 'condoclock' ) ),
				'rules'              => array( __( 'Rules of the association', 'condoclock' ), __( 'The rules members follow. You can also post the record-keeping rules here.', 'condoclock' ) ),
				'contracts_bids'     => array( __( 'Current contracts and bids received in the past year', 'condoclock' ), __( 'Every current contract, and bids received in the last 12 months.', 'condoclock' ) ),
				'budget'             => array( __( 'Annual budget and proposed budget', 'condoclock' ), __( "This year's budget and any proposed budget.", 'condoclock' ) ),
				'financial'          => array( __( 'Annual financial report', 'condoclock' ), __( 'The yearly financial report.', 'condoclock' ) ),
				'insurance'          => array( __( 'Insurance policies', 'condoclock' ), __( 'Every current insurance policy the association holds.', 'condoclock' ) ),
				'director_cert'      => array( __( 'Director certifications', 'condoclock' ), __( "Each director's written certification after election.", 'condoclock' ) ),
				'director_contracts' => array( __( 'Contracts with directors, officers or their businesses', 'condoclock' ), __( 'Any contract with a director, an officer or their business.', 'condoclock' ) ),
				'conflict'           => array( __( 'Conflict-of-interest contracts', 'condoclock' ), __( 'Contracts where a director has a conflict of interest.', 'condoclock' ) ),
				'owner_notices'      => array( __( "Members' meeting notices and agendas", 'condoclock' ), __( "Notices and agendas for members' meetings.", 'condoclock' ) ),
				'board_notices'      => array( __( 'Board meeting notices and agendas', 'condoclock' ), __( 'Notices and agendas for board meetings.', 'condoclock' ) ),
			),
		);
	}

	/**
	 * Categories for a mode, in statute order, keyed by category key.
	 *
	 * @param string|null $mode condo or hoa; current mode when null.
	 * @return array key => array( key, letter, name, hint, ref, rolling, link_only, meeting_rules ).
	 */
	public static function categories( $mode = null ) {
		$mode  = $mode ? $mode : self::mode();
		$data  = self::data();
		$words = self::words();
		$m     = $data['modes'][ $mode ];
		$out   = array();
		foreach ( $m['categories'] as $c ) {
			$w                = isset( $words[ $mode ][ $c['key'] ] ) ? $words[ $mode ][ $c['key'] ] : array( $c['name'], $c['hint'] );
			$out[ $c['key'] ] = array(
				'key'           => $c['key'],
				'letter'        => $c['letter'],
				'name'          => $w[0],
				'hint'          => $w[1],
				'ref'           => $m['list'] . '.' . $c['letter'],
				'short'         => '2.' . $c['letter'],
				'rolling'       => ! empty( $c['rolling'] ),
				'link_only'     => ! empty( $c['link_only'] ),
				'meeting_rules' => ! empty( $c['meeting_rules'] ),
			);
		}
		return $out;
	}

	/**
	 * One category, or null.
	 *
	 * @param string $key Category key.
	 * @return array|null
	 */
	public static function category( $key ) {
		$all = self::categories();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Groups for the Records board: array of array( label, keys ).
	 *
	 * @param string|null $mode Mode.
	 * @return array
	 */
	public static function groups( $mode = null ) {
		$mode   = $mode ? $mode : self::mode();
		$data   = self::data();
		$labels = array(
			'governing' => __( 'Governing documents', 'condoclock' ),
			'meetings'  => __( 'Meetings and minutes', 'condoclock' ),
			'money'     => __( 'Money and contracts', 'condoclock' ),
			'notices'   => __( 'Notices', 'condoclock' ),
			'safety'    => __( 'Building safety', 'condoclock' ),
		);
		$out    = array();
		foreach ( $data['modes'][ $mode ]['groups'] as $g ) {
			$out[] = array( $labels[ $g[0] ], $g[2] );
		}
		return $out;
	}

	/**
	 * Statute list reference for the mode, such as 718.111(12)(g)2.
	 *
	 * @param string|null $mode Mode.
	 * @return string
	 */
	public static function list_ref( $mode = null ) {
		$mode = $mode ? $mode : self::mode();
		$data = self::data();
		return $data['modes'][ $mode ]['list'];
	}

	/**
	 * Meeting types for the mode, keyed by type, with translated names.
	 *
	 * @param string|null $mode Mode.
	 * @return array type => array( type, name, notice, vote, extra, source ).
	 */
	public static function meeting_types( $mode = null ) {
		$mode  = $mode ? $mode : self::mode();
		$data  = self::data();
		$names = array(
			'board'         => __( 'Board meeting', 'condoclock' ),
			'board_special' => 'hoa' === $mode ? __( 'Board meeting on special assessments or use rules', 'condoclock' ) : __( 'Board meeting on special assessments or unit-use rules', 'condoclock' ),
			'emergency'     => __( 'Emergency board meeting', 'condoclock' ),
			'annual'        => __( "Annual owners' meeting", 'condoclock' ),
			'owners'        => __( "Other owners' meeting", 'condoclock' ),
			'budget'        => __( 'Budget meeting', 'condoclock' ),
			'committee'     => __( 'Committee meeting', 'condoclock' ),
			'members'       => __( "Members' meeting", 'condoclock' ),
		);
		$out   = array();
		foreach ( $data['modes'][ $mode ]['meetings'] as $t ) {
			$t['name']         = isset( $names[ $t['type'] ] ) ? $names[ $t['type'] ] : $t['name'];
			$out[ $t['type'] ] = $t;
		}
		return $out;
	}
}

<?php
/**
 * Blocks and shortcodes for the Owners' Records and Notices pages.
 *
 * @package CondoClock
 */

namespace CondoClock;

defined( 'ABSPATH' ) || exit;

/**
 * Server-rendered blocks, so the pages work with any theme.
 */
class Blocks {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_shortcode( 'condoclock_records', array( __CLASS__, 'records' ) );
		add_shortcode( 'condoclock_notices', array( __CLASS__, 'notices' ) );
	}

	/**
	 * Registers the editor script, the stylesheet and the two blocks.
	 */
	public static function register() {
		wp_register_script( 'condoclock-blocks-editor', CONDOCLOCK_URL . 'assets/blocks.js', array( 'wp-blocks', 'wp-element', 'wp-block-editor', 'wp-server-side-render' ), CONDOCLOCK_VERSION, true );
		wp_add_inline_script( 'condoclock-blocks-editor', 'window.condoclockBlocks=(window.condoclockBlocks||[]).concat(["condoclock/records","condoclock/notices"]);', 'before' );
		wp_register_style( 'condoclock-owners', CONDOCLOCK_URL . 'assets/owners.css', array(), CONDOCLOCK_VERSION );
		wp_register_script( 'condoclock-owners', CONDOCLOCK_URL . 'assets/owners.js', array(), CONDOCLOCK_VERSION, true );
		register_block_type( CONDOCLOCK_DIR . 'blocks/records', array( 'render_callback' => array( __CLASS__, 'records' ) ) );
		register_block_type( CONDOCLOCK_DIR . 'blocks/notices', array( 'render_callback' => array( __CLASS__, 'notices' ) ) );
	}

	/**
	 * Style presets for the owners' pages.
	 *
	 * @return array key => array( name, class, vars, pro ).
	 */
	public static function presets() {
		$presets = array(
			'clean'   => array(
				'name'  => __( 'Clean', 'condoclock' ),
				'class' => 'op-clean',
				'vars'  => '',
				'swat'  => array( '#ffffff', '#2F5BEA', '#13213C' ),
			),
			'classic' => array(
				'name'  => __( 'Classic', 'condoclock' ),
				'class' => 'op-classic',
				'vars'  => '',
				'swat'  => array( '#FFFDF8', '#1F3B3C', '#A88F6A' ),
			),
		);
		/**
		 * Style presets for the owners' pages. CondoClock Pro adds six more.
		 *
		 * @param array $presets key => array( name, class, vars (CSS custom properties), swat (3 colours), pro ).
		 */
		return apply_filters( 'condoclock_style_presets', $presets );
	}

	/**
	 * Loads the stylesheet with the chosen preset and returns the wrapper classes.
	 *
	 * @return string
	 */
	private static function assets() {
		$s       = condoclock_settings();
		$presets = self::presets();
		$p       = isset( $presets[ $s['style'] ] ) ? $presets[ $s['style'] ] : $presets['clean'];
		wp_enqueue_style( 'condoclock-owners' );
		$css = $p['vars'] ? '.op.' . sanitize_html_class( $p['class'] ) . '{' . $p['vars'] . '}' : '';
		/**
		 * Extra CSS for the owners' pages (Pro: accent colour).
		 *
		 * @param string $css      CSS.
		 * @param array  $settings Settings.
		 */
		$css = apply_filters( 'condoclock_owner_css', $css, $s );
		if ( $css ) {
			wp_add_inline_style( 'condoclock-owners', wp_strip_all_tags( $css ) );
		}
		return 'op ' . sanitize_html_class( $p['class'] );
	}

	/**
	 * The heading, with the association name above it in the Classic look.
	 *
	 * @param string $title Heading.
	 * @return string
	 */
	private static function heading( $title ) {
		$s    = condoclock_settings();
		$html = '<div class="op-h">';
		/**
		 * Markup before the heading (Pro: association logo).
		 *
		 * @param string $html HTML.
		 */
		$html .= apply_filters( 'condoclock_owner_logo', '' );
		$html .= '<span class="op-sub">' . esc_html( $s['name'] ) . '</span>';
		$html .= '<h2 class="op-t">' . esc_html( $title ) . '</h2></div>';
		return $html;
	}

	/**
	 * Login box for visitors.
	 *
	 * @param string $lede Line under the heading.
	 * @return string
	 */
	private static function gate( $lede ) {
		$html  = '<div class="op-gate"><div class="op-lock">' . wp_kses( condoclock_icon( 'lock' ), condoclock_kses_svg() ) . '</div>';
		$html .= '<p class="op-lede">' . esc_html( $lede ) . '</p>';
		if ( ! is_user_logged_in() ) {
			$html .= wp_login_form(
				array(
					'echo'     => false,
					'redirect' => get_permalink(),
					'form_id'  => 'condoclock-login',
				)
			);
		}
		ob_start();
		/**
		 * Prints after the login box on the Owners' Records page (Pro: "Need access? Request a login").
		 */
		do_action( 'condoclock_records_logged_out' );
		$html .= ob_get_clean() . '</div>';
		return $html;
	}

	/**
	 * The Owners' Records page.
	 *
	 * @return string
	 */
	public static function records() {
		$class = self::assets();
		$html  = '<div class="' . esc_attr( $class ) . '">' . self::heading( __( 'Owners\' records', 'condoclock' ) );
		if ( ! current_user_can( 'condoclock_view_records' ) ) {
			$lede = is_user_logged_in()
				? __( 'Your account can\'t open the owners\' records yet. Ask the board to give you access.', 'condoclock' )
				: __( 'Log in to see the association\'s records.', 'condoclock' );
			return $html . self::gate( $lede ) . '</div>';
		}
		wp_enqueue_script( 'condoclock-owners' );
		$s       = condoclock_settings();
		$grouped = Records::for_owners();
		$na      = Records::na();
		$side    = self::side();
		$html   .= '<div class="op-cols' . ( $side ? '' : ' op-one' ) . '"><div class="op-list">';
		$html   .= '<label class="op-search">' . wp_kses( condoclock_icon( 'find' ), condoclock_kses_svg() ) . '<span class="screen-reader-text">' . esc_html__( 'Search records', 'condoclock' ) . '</span><input type="search" placeholder="' . esc_attr__( 'Search records', 'condoclock' ) . '"></label>';
		$any     = false;
		foreach ( Rules::categories() as $key => $cat ) {
			$items = isset( $grouped[ $key ] ) ? $grouped[ $key ] : array();
			if ( ! $items ) {
				if ( isset( $na[ $key ] ) && ! empty( $na[ $key ]['show'] ) && ! empty( $s['show_na_reason'] ) ) {
					/* translators: %s: the board's reason. */
					$html .= '<section class="op-sec"><h3>' . esc_html( $cat['name'] ) . '</h3><div class="op-na">' . esc_html( sprintf( __( 'Not applicable: %s', 'condoclock' ), $na[ $key ]['reason'] ) ) . '</div></section>';
				}
				continue;
			}
			$any   = true;
			$html .= '<section class="op-sec"><h3>' . esc_html( $cat['name'] ) . '<small>' . esc_html( $cat['ref'] ) . '</small></h3>';
			foreach ( $items as $r ) {
				$html .= self::file_row( $r );
			}
			$html .= '</section>';
		}
		if ( ! $any ) {
			$html .= '<p class="op-empty">' . esc_html__( 'The board hasn\'t posted any records yet.', 'condoclock' ) . '</p>';
		}
		$html .= '<p class="op-empty op-none" hidden>' . esc_html__( 'No records match your search.', 'condoclock' ) . '</p>';
		$html .= '</div>' . $side . '</div></div>';
		return $html;
	}

	/**
	 * One record on the owners' page.
	 *
	 * @param array $r Record.
	 * @return string
	 */
	private static function file_row( array $r ) {
		/* translators: %s: date. */
		$posted = sprintf( __( 'Posted %s', 'condoclock' ), condoclock_date( $r['posted'] ) );
		if ( $r['file'] ) {
			$meta = Files::type_label( $r['file'] ) . ' · ' . condoclock_size( $r['file']['size'] ) . ' · ' . $posted;
			$link = '<a class="op-dl" href="' . esc_url( Files::url( $r['id'] ) ) . '">' . esc_html__( 'Download', 'condoclock' ) . '<span class="screen-reader-text"> ' . esc_html( $r['title'] ) . '</span></a>';
		} else {
			$meta = __( 'Recording', 'condoclock' ) . ' · ' . $posted;
			$link = '<a class="op-dl" href="' . esc_url( $r['video'] ) . '" rel="noopener" target="_blank">' . esc_html__( 'Watch', 'condoclock' ) . '<span class="screen-reader-text"> ' . esc_html( $r['title'] ) . '</span></a>';
		}
		$html = '<div class="op-file" data-t="' . esc_attr( mb_strtolower( $r['title'] ) ) . '">' . wp_kses( condoclock_icon( 'file' ), condoclock_kses_svg() ) . '<div><b>' . esc_html( $r['title'] ) . '</b><small>' . esc_html( $meta ) . '</small>';
		if ( $r['file'] && $r['video'] ) {
			$html .= '<small><a href="' . esc_url( $r['video'] ) . '" rel="noopener" target="_blank">' . esc_html__( 'Watch the recording', 'condoclock' ) . '</a></small>';
		}
		if ( $r['note'] ) {
			$html .= '<p>' . esc_html( $r['note'] ) . '</p>';
		}
		if ( $r['versions'] ) {
			$html .= '<details class="op-ev"><summary>' . esc_html__( 'Earlier versions', 'condoclock' ) . '</summary><ul>';
			foreach ( array_reverse( $r['versions'], true ) as $i => $v ) {
				/* translators: %s: date. */
				$label = $v['posted'] ? sprintf( __( 'Posted %s', 'condoclock' ), condoclock_date( $v['posted'] ) ) : $v['file']['name'];
				$html .= '<li><a href="' . esc_url( Files::url( $r['id'], (string) $i ) ) . '">' . esc_html( $label ) . '</a></li>';
			}
			$html .= '</ul></details>';
		}
		return $html . '</div>' . $link . '</div>';
	}

	/**
	 * Side card on the records page: the next meetings.
	 *
	 * @return string
	 */
	private static function side() {
		$next = array_slice( Notices::query( 'upcoming' ), 0, 3 );
		$html = '';
		if ( $next ) {
			$s     = condoclock_settings();
			$html .= '<div class="op-card"><h4>' . esc_html__( 'Upcoming meetings', 'condoclock' ) . '</h4>';
			foreach ( $next as $m ) {
				$html .= '<div class="op-m"><b>' . esc_html( $m['title'] ) . '</b>' . esc_html( self::when( $m['ts'] ) ) . self::countdown( $m ) . '</div>';
			}
			if ( ! empty( $s['pages']['notices'] ) ) {
				$html .= '<a href="' . esc_url( get_permalink( $s['pages']['notices'] ) ) . '">' . esc_html__( 'All notices', 'condoclock' ) . '</a>';
			}
			$html .= '</div>';
		}
		/**
		 * Extra side cards on the Owners' Records page.
		 *
		 * @param string $html HTML.
		 */
		return (string) apply_filters( 'condoclock_records_side', $html );
	}

	/**
	 * "{date} at {time}" in site time.
	 *
	 * @param int $ts Timestamp.
	 * @return string
	 */
	public static function when( $ts ) {
		/* translators: 1: date, 2: time. */
		return sprintf( __( '%1$s at %2$s', 'condoclock' ), wp_date( _x( 'D j M Y', 'meeting date', 'condoclock' ), $ts ), wp_date( get_option( 'time_format', 'g:i a' ), $ts ) );
	}

	/**
	 * Countdown pill (Pro).
	 *
	 * @param array $m Meeting.
	 * @return string
	 */
	private static function countdown( array $m ) {
		/**
		 * Countdown text for a meeting, such as "Meeting in 9 days". Empty in Free.
		 *
		 * @param string $text    Text.
		 * @param array  $meeting Meeting.
		 */
		$text = (string) apply_filters( 'condoclock_countdown', '', $m );
		return '' === $text ? '' : '<span class="op-cd">' . wp_kses( condoclock_icon( 'clock' ), condoclock_kses_svg() ) . esc_html( $text ) . '</span>';
	}

	/**
	 * The Notices page.
	 *
	 * @return string
	 */
	public static function notices() {
		$class = self::assets();
		$html  = '<div class="' . esc_attr( $class ) . '">' . self::heading( __( 'Notices', 'condoclock' ) );
		if ( ! Files::notices_public() && ! current_user_can( 'condoclock_view_records' ) ) {
			return $html . self::gate( __( 'Log in to see meeting notices.', 'condoclock' ) ) . '</div>';
		}
		$up = Notices::query( 'upcoming' );
		if ( ! $up ) {
			$html .= '<p class="op-empty">' . esc_html__( 'No meetings scheduled right now.', 'condoclock' ) . '</p>';
		} else {
			$html .= '<div class="op-ns">';
			foreach ( $up as $m ) {
				$html .= '<article class="op-n">' . self::countdown( $m ) . '<b>' . esc_html( $m['title'] ) . '</b><p>' . esc_html( self::when( $m['ts'] ) );
				if ( $m['place'] ) {
					$html .= '<br>' . esc_html( $m['place'] );
				}
				$html .= '</p>';
				if ( $m['video'] ) {
					$html .= '<a href="' . esc_url( $m['video'] ) . '" rel="noopener" target="_blank">' . esc_html__( 'Join online', 'condoclock' ) . wp_kses( condoclock_icon( 'go' ), condoclock_kses_svg() ) . '</a>';
				}
				$html .= self::doc_links( $m, 'a' ) . '</article>';
			}
			$html .= '</div>';
		}
		$past = array_slice( Notices::query( 'past' ), 0, 20 );
		if ( $past ) {
			$html .= '<h3 class="op-past">' . esc_html__( 'Past notices', 'condoclock' ) . '</h3>';
			foreach ( $past as $m ) {
				$html .= '<div class="op-pi"><b>' . esc_html( $m['title'] ) . '</b> · ' . esc_html( self::when( $m['ts'] ) ) . self::doc_links( $m, 'small' ) . '</div>';
			}
		}
		return $html . '</div>';
	}

	/**
	 * Links to a meeting's documents.
	 *
	 * @param array  $m     Meeting.
	 * @param string $style a (full rows) or small (inline).
	 * @return string
	 */
	private static function doc_links( array $m, $style ) {
		$labels = array(
			'notice' => __( 'Notice', 'condoclock' ),
			'agenda' => __( 'Agenda', 'condoclock' ),
			'vote'   => __( 'Documents to be voted on', 'condoclock' ),
		);
		$html   = '';
		foreach ( $labels as $slot => $label ) {
			if ( empty( $m['docs'][ $slot ] ) ) {
				continue;
			}
			$url   = Files::url( $m['id'], $slot );
			$html .= 'a' === $style
				? '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . wp_kses( condoclock_icon( 'down' ), condoclock_kses_svg() ) . '</a>'
				: '<a href="' . esc_url( $url ) . '">' . esc_html( $label ) . '</a>';
		}
		return $html;
	}
}

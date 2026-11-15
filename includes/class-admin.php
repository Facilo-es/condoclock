<?php
/**
 * Admin screens: records board, category screen, owners, notices, settings, help and the Pro tab.
 *
 * @package CondoClock
 */

namespace CondoClock;

defined( 'ABSPATH' ) || exit;

/**
 * The board's screens.
 */
class Admin {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_action( 'admin_init', array( __CLASS__, 'first_run' ) );
		add_action( 'admin_notices', array( __CLASS__, 'site_notices' ) );
		add_action( 'admin_post_condoclock_settings', array( __CLASS__, 'handle_settings' ) );
		add_action( 'wp_dashboard_setup', array( __CLASS__, 'dashboard' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( CONDOCLOCK_FILE ), array( __CLASS__, 'action_links' ) );
	}

	/**
	 * Is this one of our screens?
	 *
	 * @return bool
	 */
	public static function is_ours() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the screen name.
		$page = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		return 0 === strpos( $page, 'condoclock' );
	}

	/**
	 * Menu and submenus.
	 */
	public static function menu() {
		$cap  = current_user_can( 'condoclock_manage' ) ? 'condoclock_manage' : 'manage_options';
		$icon = 'data:image/svg+xml;base64,' . base64_encode( '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill="#a7aaad" d="M10 1a9 9 0 100 18 9 9 0 000-18zm4.2 6.6l-5 5.4a1 1 0 01-1.5 0L5.8 11a1 1 0 011.5-1.4l1.2 1.3 4.3-4.6a1 1 0 011.4 1.3z"/></svg>' ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- menu icon data URI.
		$hook = add_menu_page( __( 'Records', 'condoclock' ), 'CondoClock', $cap, 'condoclock', array( __CLASS__, 'render_records' ), $icon, 26 );
		add_action( 'load-' . $hook, array( __CLASS__, 'need_setup' ) );
		add_submenu_page( 'condoclock', __( 'Records', 'condoclock' ), __( 'Records', 'condoclock' ), $cap, 'condoclock', array( __CLASS__, 'render_records' ) );
		add_submenu_page( 'condoclock', __( 'Notices', 'condoclock' ), __( 'Notices', 'condoclock' ), $cap, 'condoclock-notices', array( __CLASS__, 'render_notices' ) );
		add_submenu_page( 'condoclock', __( 'Owners', 'condoclock' ), __( 'Owners', 'condoclock' ), $cap, 'condoclock-owners', array( __CLASS__, 'render_owners' ) );
		/**
		 * Lets CondoClock Pro add its screens (Meetings, History) to the menu.
		 *
		 * @param string $cap Capability for the screens.
		 */
		do_action( 'condoclock_admin_menu', $cap );
		add_submenu_page( 'condoclock', __( 'Settings', 'condoclock' ), __( 'Settings', 'condoclock' ), $cap, 'condoclock-settings', array( __CLASS__, 'render_settings' ) );
		add_submenu_page( 'condoclock', __( 'Status', 'condoclock' ), __( 'Status', 'condoclock' ), $cap, 'condoclock-status', array( 'CondoClock\\Status', 'render' ) );
		add_submenu_page( 'condoclock', __( 'Help', 'condoclock' ), __( 'Help', 'condoclock' ), $cap, 'condoclock-help', array( __CLASS__, 'render_help' ) );
		if ( ! condoclock_is_pro() ) {
			add_submenu_page( 'condoclock', __( 'Get Pro', 'condoclock' ), __( 'Get Pro', 'condoclock' ), $cap, 'condoclock-pro', array( __CLASS__, 'render_pro' ) );
		}
		add_submenu_page( 'condoclock', __( 'Set up CondoClock', 'condoclock' ), __( 'Set up', 'condoclock' ), $cap, 'condoclock-setup', array( 'CondoClock\\Setup', 'render' ) );
		add_action( 'admin_head', array( __CLASS__, 'hide_setup' ) );
	}

	/**
	 * Keeps the wizard out of the menu once its page title is known.
	 */
	public static function hide_setup() {
		remove_submenu_page( 'condoclock', 'condoclock-setup' );
	}

	/**
	 * Sends the board to the wizard until setup is done.
	 */
	public static function need_setup() {
		$s = condoclock_settings();
		if ( empty( $s['setup_done'] ) ) {
			wp_safe_redirect( Setup::url( 1 ) );
			exit;
		}
	}

	/**
	 * Styles and script on our screens only.
	 */
	public static function assets() {
		if ( ! self::is_ours() ) {
			return;
		}
		wp_enqueue_style( 'condoclock-admin', CONDOCLOCK_URL . 'assets/admin.css', array(), CONDOCLOCK_VERSION );
		wp_enqueue_script( 'condoclock-admin', CONDOCLOCK_URL . 'assets/admin.js', array(), CONDOCLOCK_VERSION, true );
	}

	/**
	 * Sends the board to the wizard after the first activation.
	 */
	public static function first_run() {
		if ( ! get_option( 'condoclock_redirect' ) ) {
			return;
		}
		delete_option( 'condoclock_redirect' );
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- WordPress's own bulk activation flag.
		if ( wp_doing_ajax() || isset( $_GET['activate-multi'] ) || ! condoclock_can_manage() ) {
			return;
		}
		$s = condoclock_settings();
		if ( empty( $s['setup_done'] ) ) {
			wp_safe_redirect( Setup::url( 1 ) );
			exit;
		}
	}

	/**
	 * Settings and Set up links on the Plugins screen.
	 *
	 * @param array $links Links.
	 * @return array
	 */
	public static function action_links( $links ) {
		$s = condoclock_settings();
		array_unshift(
			$links,
			empty( $s['setup_done'] )
				? '<a href="' . esc_url( Setup::url( 1 ) ) . '">' . esc_html__( 'Set up', 'condoclock' ) . '</a>'
				: '<a href="' . esc_url( admin_url( 'admin.php?page=condoclock' ) ) . '">' . esc_html__( 'Records', 'condoclock' ) . '</a>'
		);
		return $links;
	}

	/**
	 * Warnings that matter on every admin screen: open private files and a renamed Notices page.
	 */
	public static function site_notices() {
		if ( ! condoclock_can_manage() || self::is_ours() ) {
			return;
		}
		$t = get_option( 'condoclock_selftest' );
		if ( is_array( $t ) && 'exposed' === $t['result'] ) {
			printf(
				'<div class="notice notice-error"><p><b>%1$s</b> <a href="%2$s">%3$s</a></p></div>',
				esc_html__( 'CondoClock: your server shows private files to anyone with the link.', 'condoclock' ),
				esc_url( admin_url( 'admin.php?page=condoclock-status' ) ),
				esc_html__( 'See how to fix it', 'condoclock' )
			);
		}
		$warn = Setup::title_warning();
		if ( $warn ) {
			echo '<div class="notice notice-warning"><p>' . esc_html( $warn ) . '</p></div>';
		}
	}

	/**
	 * Allowed HTML for our prepared snippets.
	 *
	 * @return array
	 */
	public static function kses() {
		$attrs = array(
			'class' => true,
			'role'  => true,
			'id'    => true,
			'href'  => true,
			'title' => true,
		);
		return array_merge(
			condoclock_kses_svg(),
			array(
				'div'   => $attrs,
				'p'     => $attrs,
				'b'     => $attrs,
				'span'  => $attrs,
				'small' => $attrs,
				'a'     => $attrs,
				'pre'   => $attrs,
				'code'  => $attrs,
				'ul'    => $attrs,
				'li'    => $attrs,
				'br'    => array(),
			)
		);
	}

	/**
	 * Echoes SVG markup from our own icon set.
	 *
	 * @param string $name Icon.
	 */
	public static function icon( $name ) {
		echo wp_kses( condoclock_icon( $name ), condoclock_kses_svg() );
	}

	/**
	 * Screen header with the brand line, title and subtitle.
	 *
	 * @param string $title    Title (plain text).
	 * @param string $sub_html Subtitle, already escaped.
	 * @param string $back     Back link URL, or ''.
	 */
	public static function head( $title, $sub_html = '', $back = '' ) {
		$s     = condoclock_settings();
		$assoc = '' !== $s['name'] ? $s['name'] : get_bloginfo( 'name' );
		echo '<div class="wrap ccb"><h1 class="screen-reader-text">' . esc_html( $title ) . '</h1><header class="ccb-head"><div class="ccb-brand">';
		echo '<svg viewBox="0 0 26 26" aria-hidden="true"><rect width="26" height="26" rx="7" fill="#2F5BEA"/><path d="M7 13.5l4 4 8-9" stroke="#fff" stroke-width="2.6" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>';
		echo esc_html( 'CondoClock · ' . $assoc ) . '</div>';
		if ( $back ) {
			echo '<a class="ccb-back" href="' . esc_url( $back ) . '">&larr; ' . esc_html__( 'All records', 'condoclock' ) . '</a>';
		}
		echo '<h2 class="ccb-title" aria-hidden="true">' . esc_html( $title ) . '</h2>';
		if ( $sub_html ) {
			echo '<p>' . wp_kses( $sub_html, self::kses() ) . '</p>';
		}
		self::nav();
		echo '</header>';
		self::messages();
	}

	/**
	 * Closes the screen.
	 */
	public static function foot() {
		echo '</div>';
	}

	/**
	 * Tabs across our screens.
	 */
	private static function nav() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- reading the screen name.
		$page  = isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : '';
		$items = array(
			'condoclock'         => __( 'Records', 'condoclock' ),
			'condoclock-notices' => __( 'Notices', 'condoclock' ),
			'condoclock-owners'  => __( 'Owners', 'condoclock' ),
		);
		/**
		 * Screens in the header tabs. CondoClock Pro adds Meetings and History.
		 *
		 * @param array $items page slug => label.
		 */
		$items = apply_filters( 'condoclock_nav', $items );
		$items = array_merge(
			$items,
			array(
				'condoclock-settings' => __( 'Settings', 'condoclock' ),
				'condoclock-status'   => __( 'Status', 'condoclock' ),
				'condoclock-help'     => __( 'Help', 'condoclock' ),
			)
		);
		if ( 'condoclock-setup' === $page ) {
			return;
		}
		echo '<nav class="ccb-nav" aria-label="' . esc_attr__( 'CondoClock screens', 'condoclock' ) . '">';
		foreach ( $items as $slug => $label ) {
			$on = $slug === $page;
			printf( '<a href="%1$s"%2$s>%3$s</a>', esc_url( admin_url( 'admin.php?page=' . $slug ) ), $on ? ' class="on" aria-current="page"' : '', esc_html( $label ) );
		}
		echo '</nav>';
	}

	/**
	 * Result of the last action, from the redirect.
	 */
	private static function messages() {
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- display only.
		$msg = isset( $_GET['cc_msg'] ) ? sanitize_key( wp_unslash( $_GET['cc_msg'] ) ) : '';
		$n   = isset( $_GET['cc_n'] ) ? absint( $_GET['cc_n'] ) : 1;
		$err = ! empty( $_GET['cc_err'] );
		// phpcs:enable
		$texts = array(
			/* translators: %d: number of records. */
			'posted'         => sprintf( _n( '%d record posted. Owners can see it now.', '%d records posted. Owners can see them now.', $n, 'condoclock' ), $n ),
			'saved'          => __( 'Saved. Owners won\'t see it until you post it.', 'condoclock' ),
			'replaced'       => __( 'File replaced. Owners see the new one, and the old one under "Earlier versions".', 'condoclock' ),
			'withdraw'       => __( 'Withdrawn. Owners no longer see it.', 'condoclock' ),
			'archive'        => __( 'Moved to the archive. Owners no longer see it.', 'condoclock' ),
			'deleted'        => __( 'Deleted.', 'condoclock' ),
			'na'             => __( 'Marked not applicable.', 'condoclock' ),
			'na_cleared'     => __( 'This category counts again.', 'condoclock' ),
			'owner_added'    => __( 'Owner added. They\'ll get an email to set their password.', 'condoclock' ),
			'employee'       => __( 'Done. That user can now see the owners\' records.', 'condoclock' ),
			'removed'        => __( 'Access removed. The user account stays.', 'condoclock' ),
			'resent'         => __( 'Login email sent again.', 'condoclock' ),
			'notice_saved'   => __( 'Notice saved.', 'condoclock' ),
			'notice_deleted' => __( 'Notice deleted.', 'condoclock' ),
			'settings'       => __( 'Settings saved.', 'condoclock' ),
		);
		$bad   = array(
			'nofile'  => __( 'Add a file or a recording link first.', 'condoclock' ),
			'confirm' => __( 'Type delete to confirm.', 'condoclock' ),
			'reason'  => __( 'Give a reason of up to 200 characters.', 'condoclock' ),
		);
		$list  = array();
		if ( $err ) {
			$key  = 'condoclock_errors_' . get_current_user_id();
			$list = (array) get_transient( $key );
			delete_transient( $key );
		}
		if ( isset( $bad[ $msg ] ) ) {
			$list[] = $bad[ $msg ];
		}
		if ( isset( $texts[ $msg ] ) ) {
			echo '<div class="ccb-wrap ccb-msgs"><div class="ccb-note ok" role="status">';
			self::icon( 'check' );
			echo '<p>' . esc_html( $texts[ $msg ] ) . '</p></div></div>';
		}
		$list = array_filter( array_map( 'strval', $list ) );
		if ( $list ) {
			echo '<div class="ccb-wrap ccb-msgs"><div class="ccb-note bad" role="alert">';
			self::icon( 'alert' );
			echo '<div>';
			foreach ( $list as $line ) {
				echo '<p>' . esc_html( $line ) . '</p>';
			}
			echo '</div></div></div>';
		}
	}

	/**
	 * The association's name, or the site name.
	 *
	 * @return string
	 */
	public static function assoc() {
		$s = condoclock_settings();
		return '' !== $s['name'] ? $s['name'] : get_bloginfo( 'name' );
	}

	/**
	 * Subtitle used on the board: mode, size, record types, statute and rule date.
	 *
	 * @return string HTML.
	 */
	public static function sub_line() {
		$s     = condoclock_settings();
		$total = count( Rules::categories() );
		$kind  = 'hoa' === $s['mode'] ? __( 'HOA', 'condoclock' ) : __( 'Condominium', 'condoclock' );
		/* translators: %d: number of units. */
		$size = 'hoa' === $s['mode'] ? sprintf( _n( '%d parcel', '%d parcels', (int) $s['count'], 'condoclock' ), (int) $s['count'] ) : sprintf( _n( '%d unit', '%d units', (int) $s['count'], 'condoclock' ), (int) $s['count'] );
		/* translators: 1: Condominium or HOA, 2: number of units, 3: number of record types, 4: statute reference, 5: rule table date. */
		$fmt = __( '%1$s, <b>%2$s</b> · %3$d record types under %4$s · Rules as of %5$s', 'condoclock' );
		return sprintf( $fmt, esc_html( $kind ), esc_html( $size ), $total, '<span class="ccb-mono">' . esc_html( Rules::list_ref() ) . '</span>', esc_html( Rules::as_of() ) );
	}

	/**
	 * Records board, or one category when ?cat= is set.
	 */
	public static function render_records() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- screen selection.
		$cat = isset( $_GET['cat'] ) ? sanitize_key( wp_unslash( $_GET['cat'] ) ) : '';
		if ( $cat && Rules::category( $cat ) ) {
			self::render_category( $cat );
			return;
		}
		self::render_board();
	}

	/**
	 * The board: progress bar, rows by group and the side panel.
	 */
	private static function render_board() {
		$sum  = Records::summary();
		$cats = Rules::categories();
		$na   = Records::na();
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- filter selection.
		$show = isset( $_GET['show'] ) ? sanitize_key( wp_unslash( $_GET['show'] ) ) : 'all';
		/**
		 * Clock state per category from CondoClock Pro: key => array( state soon|late, text ).
		 *
		 * @param array $clocks Clock states.
		 */
		$clocks = (array) apply_filters( 'condoclock_board_clocks', array() );

		self::head( __( 'Records', 'condoclock' ), self::sub_line() );
		echo '<div class="ccb-wrap">';

		// Progress.
		echo '<section class="ccb-prog"><div class="ccb-count">' . (int) $sum['posted'] . '<span> / ' . (int) $sum['total'] . '</span><small>';
		/* translators: 1: posted, 2: total, 3: not applicable, 4: missing. */
		echo esc_html( sprintf( __( '%1$d of %2$d posted · %3$d not applicable · %4$d missing', 'condoclock' ), $sum['posted'], $sum['total'], $sum['na'], $sum['missing'] ) );
		echo '</small></div><div><div class="ccb-bar" style="--n:' . (int) $sum['total'] . '">';
		foreach ( $cats as $key => $c ) {
			$state = $sum['rows'][ $key ];
			if ( isset( $clocks[ $key ] ) && 'late' === $clocks[ $key ][0] ) {
				$state = 'late';
			} elseif ( isset( $clocks[ $key ] ) && 'soon' === $clocks[ $key ][0] && 'posted' === $state ) {
				$state = 'soon';
			}
			echo '<span class="ccb-seg ' . esc_attr( $state ) . '" title="' . esc_attr( $c['name'] ) . '"></span>';
		}
		echo '</div><div class="ccb-letters" style="--n:' . (int) $sum['total'] . '">';
		foreach ( $cats as $key => $c ) {
			$late = isset( $clocks[ $key ] ) && 'late' === $clocks[ $key ][0];
			echo '<span' . ( $late ? ' class="late"' : '' ) . '>' . esc_html( $c['letter'] ) . '</span>';
		}
		echo '</div><div class="ccb-legend">';
		$legend = array(
			array( 'background:#17734A', __( 'Posted', 'condoclock' ) ),
			array( 'background:#17734A;box-shadow:inset 0 -4px 0 #F4B731', __( 'Posted, more due soon', 'condoclock' ) ),
			array( 'box-shadow:inset 0 0 0 2px #A3ACBB', __( 'Missing', 'condoclock' ) ),
			array( 'box-shadow:inset 0 0 0 2px #B42318', __( 'Late', 'condoclock' ) ),
			array( 'background:repeating-linear-gradient(135deg,#C5CBD6 0 2px,#E8EBF0 2px 5px)', __( 'Not applicable', 'condoclock' ) ),
		);
		foreach ( $legend as $l ) {
			echo '<span><i style="' . esc_attr( $l[0] ) . '"></i>' . esc_html( $l[1] ) . '</span>';
		}
		echo '</div></div></section>';

		if ( 0 === $sum['posted'] && 0 === $sum['na'] ) {
			echo '<p class="ccb-empty">' . esc_html__( 'Start with the documents you already have: the declaration, bylaws, articles and rules. Drag them onto their row.', 'condoclock' ) . '</p>';
		}

		// List.
		echo '<div class="ccb-cols"><section class="ccb-list"><div class="ccb-tools"><label class="ccb-search">';
		self::icon( 'find' );
		echo '<span class="ccb-sr">' . esc_html__( 'Search records', 'condoclock' ) . '</span><input type="search" id="ccb-q" placeholder="' . esc_attr__( 'Search records', 'condoclock' ) . '"></label><span class="ccb-seg2">';
		$filters = array(
			'all'     => array( __( 'All', 'condoclock' ), $sum['total'] ),
			'missing' => array( __( 'Missing', 'condoclock' ), $sum['missing'] ),
			'posted'  => array( __( 'Posted', 'condoclock' ), $sum['posted'] ),
			'na'      => array( __( 'N/A', 'condoclock' ), $sum['na'] ),
		);
		foreach ( $filters as $k => $f ) {
			$url = admin_url( 'admin.php?page=condoclock' . ( 'all' === $k ? '' : '&show=' . $k ) );
			printf( '<a href="%1$s"%2$s>%3$s %4$d</a>', esc_url( $url ), $k === $show ? ' class="on" aria-current="true"' : '', esc_html( $f[0] ), (int) $f[1] );
		}
		echo '</span></div>';

		$records_page = Setup::find_page( 'records' );
		foreach ( Rules::groups() as $g ) {
			$rows = '';
			foreach ( $g[1] as $key ) {
				if ( ! isset( $cats[ $key ] ) ) {
					continue;
				}
				$state = $sum['rows'][ $key ];
				if ( 'all' !== $show && $state !== $show ) {
					continue;
				}
				$rows .= self::board_row( $cats[ $key ], $state, $sum, $na, isset( $clocks[ $key ] ) ? $clocks[ $key ] : null, $records_page );
			}
			if ( '' === $rows ) {
				continue;
			}
			echo '<div class="ccb-groupwrap"><div class="ccb-group">' . esc_html( $g[0] ) . '</div>';
			echo $rows; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built from escaped parts in board_row().
			echo '</div>';
		}
		echo '</section><aside class="ccb-side">';
		/**
		 * Top of the board's side panel. CondoClock Pro adds What's due and Meetings.
		 */
		do_action( 'condoclock_board_side' );
		if ( ! condoclock_is_pro() ) {
			echo '<div class="ccb-card"><h2>' . esc_html__( 'What\'s due', 'condoclock' ) . ' <span class="ccb-pro">PRO</span></h2>';
			echo '<p>' . esc_html__( 'See what\'s due before it\'s late, with a clock on every record.', 'condoclock' ) . '</p>';
			echo '<a class="ccb-btn ghost block" href="' . esc_url( admin_url( 'admin.php?page=condoclock-pro' ) ) . '">' . esc_html__( 'See what Pro adds', 'condoclock' ) . '</a></div>';
		}
		self::side_notices();
		echo '</aside></div></div>';
		self::foot();
	}

	/**
	 * One board row.
	 *
	 * @param array      $c            Category.
	 * @param string     $state        posted|na|missing.
	 * @param array      $sum          Summary.
	 * @param array      $na           Not applicable list.
	 * @param array|null $clock        Pro clock state.
	 * @param int        $records_page Owners' Records page ID.
	 * @return string HTML.
	 */
	private static function board_row( $c, $state, $sum, $na, $clock, $records_page ) {
		$key = $c['key'];
		$url = admin_url( 'admin.php?page=condoclock&cat=' . $key );
		if ( $clock && in_array( $clock[0], array( 'soon', 'late' ), true ) ) {
			$tag = self::tag( $clock[0], $clock[1] );
		} else {
			$tag = self::tag( $state );
		}
		if ( 'posted' === $state ) {
			$n = (int) $sum['files'][ $key ];
			/* translators: 1: date, 2: number of files. */
			$meta = sprintf( _n( 'Last posted %1$s · %2$d file', 'Last posted %1$s · %2$d files', $n, 'condoclock' ), condoclock_date( $sum['latest'][ $key ] ), $n );
		} elseif ( 'na' === $state ) {
			$meta = $na[ $key ]['reason'];
		} else {
			$meta = __( 'Nothing posted yet', 'condoclock' );
		}
		$acts  = '<a href="' . esc_url( $url . '#cc-add' ) . '">' . esc_html__( 'Add files', 'condoclock' ) . '</a>';
		$acts .= '<a href="' . esc_url( $url . '#cc-na' ) . '">' . esc_html__( 'Mark not applicable', 'condoclock' ) . '</a>';
		if ( $records_page && 'posted' === $state ) {
			$acts .= '<a href="' . esc_url( get_permalink( $records_page ) . '#cc-' . $key ) . '" target="_blank" rel="noopener">' . esc_html__( 'View', 'condoclock' ) . '</a>';
		}
		$search = strtolower( $c['name'] . ' ' . $c['hint'] . ' ' . $c['short'] );
		$html   = '<div class="ccb-row" data-t="' . esc_attr( $search ) . '"><span class="ccb-let">' . esc_html( $c['short'] ) . '</span>';
		$html  .= '<span class="nm"><a href="' . esc_url( $url ) . '">' . esc_html( $c['name'] ) . '</a><small>' . esc_html( $c['hint'] ) . '</small><span class="ccb-acts">' . $acts . '</span></span>';
		$html  .= '<span class="ccb-r">' . $tag . '<small>' . esc_html( $meta ) . '</small></span></div>';
		return $html;
	}

	/**
	 * A status tag.
	 *
	 * @param string $state posted|na|missing|soon|late|draft|withdrawn|archived.
	 * @param string $text  Text for clock states.
	 * @return string HTML.
	 */
	public static function tag( $state, $text = '' ) {
		$map = array(
			'posted'    => array( 'check', __( 'Posted', 'condoclock' ) ),
			'na'        => array( 'dash', __( 'Not applicable', 'condoclock' ) ),
			'missing'   => array( 'empty', __( 'Missing', 'condoclock' ) ),
			'soon'      => array( 'clock', $text ),
			'late'      => array( 'alert', $text ),
			'draft'     => array( 'file', __( 'Not posted', 'condoclock' ) ),
			'withdrawn' => array( 'dash', __( 'Withdrawn', 'condoclock' ) ),
			'archived'  => array( 'lock', __( 'Archived', 'condoclock' ) ),
		);
		if ( ! isset( $map[ $state ] ) ) {
			return '';
		}
		return '<span class="ccb-tag t-' . esc_attr( $state ) . '">' . wp_kses( condoclock_icon( $map[ $state ][0] ), condoclock_kses_svg() ) . esc_html( $map[ $state ][1] ) . '</span>';
	}

	/**
	 * Upcoming notices card for the board's side panel.
	 */
	private static function side_notices() {
		$up = array_slice( Notices::query( 'upcoming', false ), 0, 3 );
		echo '<div class="ccb-card"><h2>' . esc_html__( 'Upcoming meetings', 'condoclock' ) . '</h2>';
		if ( ! $up ) {
			echo '<p>' . esc_html__( 'No meetings scheduled right now.', 'condoclock' ) . '</p>';
		}
		foreach ( $up as $m ) {
			echo '<div class="ccb-meet"><b>' . esc_html( $m['title'] ) . '</b><span>' . esc_html( condoclock_datetime( $m['ts'] ) . ( $m['place'] ? ' · ' . $m['place'] : '' ) ) . '</span>';
			/**
			 * Extra lines under a meeting on the board (CondoClock Pro shows its deadlines).
			 *
			 * @param array $m Meeting.
			 */
			do_action( 'condoclock_board_meeting', $m );
			echo '</div>';
		}
		echo '<a class="ccb-link" href="' . esc_url( admin_url( 'admin.php?page=condoclock-notices' ) ) . '">' . esc_html__( 'Add a notice', 'condoclock' ) . '</a></div>';
	}

	/**
	 * Hidden fields every record form needs.
	 *
	 * @param string $action admin_post action and nonce name.
	 */
	private static function form_start( $action ) {
		echo '<input type="hidden" name="action" value="' . esc_attr( $action ) . '">';
		wp_nonce_field( $action );
	}

	/**
	 * One category: add form, files, and not applicable.
	 *
	 * @param string $key Category key.
	 */
	private static function render_category( $key ) {
		$c       = Rules::category( $key );
		$grouped = Records::grouped( array( 'draft', 'posted', 'withdrawn', 'archived' ) );
		$list    = isset( $grouped[ $key ] ) ? $grouped[ $key ] : array();
		$na      = Records::na();
		$post_to = admin_url( 'admin-post.php' );

		/* translators: %s: statute reference. */
		$sub = esc_html( $c['hint'] ) . '<br>' . sprintf( esc_html__( 'Statute: %s', 'condoclock' ), '<span class="ccb-mono">' . esc_html( $c['ref'] ) . '</span>' );
		self::head( $c['name'], $sub, admin_url( 'admin.php?page=condoclock' ) );
		echo '<div class="ccb-wrap"><div class="ccb-cols"><section class="ccb-list">';

		self::scan_dialog();

		if ( ! empty( $c['link_only'] ) ) {
			echo '<div class="ccb-note warn">';
			self::icon( 'alert' );
			echo '<p>' . esc_html__( 'Post a link to each recording, such as a private YouTube or Vimeo link. CondoClock doesn\'t store video files.', 'condoclock' ) . '</p></div>';
		}

		// Add form.
		echo '<form id="cc-add" class="ccb-f" method="post" action="' . esc_url( $post_to ) . '" enctype="multipart/form-data">';
		self::form_start( 'condoclock_add_records' );
		echo '<input type="hidden" name="category" value="' . esc_attr( $key ) . '">';
		echo '<h3>' . esc_html__( 'Add files', 'condoclock' ) . '</h3>';
		echo '<label class="ccb-drop">';
		self::icon( 'up' );
		echo '<b>' . esc_html__( 'Drop files here, or choose them', 'condoclock' ) . '</b>';
		/* translators: %s: file size, such as 64 MB. */
		echo '<small>' . esc_html( sprintf( __( 'PDF, Word, Excel, images or text. Largest file: %s', 'condoclock' ), condoclock_size( Files::max_size() ) ) ) . '</small>';
		echo '<input type="file" name="files[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt,.jpg,.jpeg,.png,.gif,.webp"><span class="ccb-picked" aria-live="polite"></span></label>';
		echo '<div class="ccb-grid2">';
		echo '<p><label for="cc-title">' . esc_html__( 'Title', 'condoclock' ) . '</label><input type="text" id="cc-title" name="title" maxlength="200"><span class="ccb-hint">' . esc_html__( 'Leave empty to use each file\'s name.', 'condoclock' ) . '</span></p>';
		echo '<p><label for="cc-received">' . esc_html__( 'Date received or created', 'condoclock' ) . '</label><input type="date" id="cc-received" name="received" value="' . esc_attr( Records::today() ) . '" max="' . esc_attr( Records::today() ) . '" required></p>';
		echo '</div>';
		$link_label = ! empty( $c['link_only'] ) ? __( 'Recording link', 'condoclock' ) : __( 'Recording link (optional)', 'condoclock' );
		echo '<p><label for="cc-video">' . esc_html( $link_label ) . '</label><input type="url" id="cc-video" name="video" placeholder="https://"></p>';
		$meetings = array_merge( Notices::query( 'upcoming', false ), Notices::query( 'past', false ) );
		if ( $meetings ) {
			echo '<p><label for="cc-meeting">' . esc_html__( 'Meeting (optional)', 'condoclock' ) . '</label><select id="cc-meeting" name="meeting"><option value="0">' . esc_html__( 'None', 'condoclock' ) . '</option>';
			foreach ( array_slice( $meetings, 0, 40 ) as $m ) {
				echo '<option value="' . (int) $m['id'] . '">' . esc_html( $m['title'] . ' · ' . condoclock_date( $m['ts'] ) ) . '</option>';
			}
			echo '</select></p>';
		}
		echo '<p><label for="cc-note">' . esc_html__( 'Note for owners (optional)', 'condoclock' ) . '</label><textarea id="cc-note" name="note" rows="2" maxlength="500"></textarea></p>';
		echo '<div class="ccb-actions"><button class="ccb-btn" name="do" value="post">' . esc_html__( 'Post to owners', 'condoclock' ) . '</button><button class="ccb-btn ghost" name="do" value="save">' . esc_html__( 'Save without posting', 'condoclock' ) . '</button></div>';
		echo '</form>';

		// Files.
		echo '<h3 class="ccb-group">' . esc_html__( 'Files', 'condoclock' ) . '</h3>';
		if ( ! $list ) {
			echo '<p class="ccb-empty">' . esc_html__( 'Nothing posted yet', 'condoclock' ) . '</p>';
		}
		echo '<ul class="ccc-files">';
		foreach ( $list as $r ) {
			self::file_row( $r, $post_to );
		}
		echo '</ul>';

		echo '</section><aside class="ccb-side">';
		self::na_card( $key, $na, $post_to );
		/**
		 * Side panel of a category screen (CondoClock Pro adds the clock).
		 *
		 * @param string $key Category key.
		 */
		do_action( 'condoclock_category_side', $key );
		echo '</aside></div></div>';
		self::foot();
	}

	/**
	 * The privacy scan dialog, opened after an upload that needs a check.
	 */
	private static function scan_dialog() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- display only.
		$id = isset( $_GET['cc_check'] ) ? absint( $_GET['cc_check'] ) : 0;
		$r  = $id ? Records::get( $id ) : null;
		if ( ! $r ) {
			return;
		}
		echo '<dialog class="ccb-dialog" data-autoopen aria-labelledby="cc-check-t"><h2 id="cc-check-t">' . esc_html__( 'Check this file before it goes up', 'condoclock' ) . '</h2>';
		self::scan_box( $r );
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		self::form_start( 'condoclock_record' );
		echo '<input type="hidden" name="record" value="' . (int) $r['id'] . '"><input type="hidden" name="do" value="post">';
		echo '<p><label><input type="checkbox" name="checked" value="1" required> ' . esc_html__( 'I checked this file', 'condoclock' ) . '</label></p>';
		echo '<div class="ccb-actions"><button class="ccb-btn">' . esc_html__( 'Post to owners', 'condoclock' ) . '</button><button type="button" class="ccb-btn ghost" data-close>' . esc_html__( 'Not now', 'condoclock' ) . '</button></div></form></dialog>';
	}

	/**
	 * Scan findings for a record.
	 *
	 * @param array $r Record.
	 */
	private static function scan_box( $r ) {
		$scan = is_array( $r['scan'] ) ? $r['scan'] : array();
		echo '<div class="ccc-scan">';
		if ( ! empty( $scan['message'] ) ) {
			echo '<p>' . esc_html( $scan['message'] ) . '</p>';
		}
		if ( ! empty( $scan['samples'] ) && is_array( $scan['samples'] ) ) {
			echo '<ul>';
			foreach ( array_slice( $scan['samples'], 0, 6 ) as $sample ) {
				echo '<li class="ccb-mono">' . esc_html( $sample ) . '</li>';
			}
			echo '</ul>';
		}
		if ( empty( $scan['message'] ) ) {
			echo '<p>' . esc_html( $r['title'] ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * One record on the category screen.
	 *
	 * @param array  $r       Record.
	 * @param string $post_to Form action.
	 */
	private static function file_row( $r, $post_to ) {
		$id = (int) $r['id'];
		echo '<li><div class="nm">';
		self::icon( 'file' );
		echo '<b>' . esc_html( $r['title'] ) . '</b><small>';
		$bits = array();
		if ( $r['file'] ) {
			$bits[] = Files::type_label( $r['file'] ) . ', ' . condoclock_size( $r['file']['size'] );
		}
		if ( $r['video'] ) {
			$bits[] = __( 'Recording link', 'condoclock' );
		}
		if ( $r['received'] ) {
			/* translators: %s: date. */
			$bits[] = sprintf( __( 'Received %s', 'condoclock' ), condoclock_date( $r['received'] ) );
		}
		if ( 'posted' === $r['status'] && $r['posted'] ) {
			/* translators: %s: date. */
			$bits[] = sprintf( __( 'Posted %s', 'condoclock' ), condoclock_date( $r['posted'] ) );
		}
		echo esc_html( implode( ' · ', $bits ) ) . '</small>';
		if ( $r['note'] ) {
			echo '<small>' . esc_html( $r['note'] ) . '</small>';
		}
		if ( $r['versions'] ) {
			echo '<details class="ccc-more"><summary>' . esc_html__( 'Earlier versions', 'condoclock' ) . '</summary><ul>';
			foreach ( $r['versions'] as $i => $v ) {
				echo '<li><a href="' . esc_url( Files::url( $id, 'v' . $i ) ) . '">' . esc_html( $v['file']['name'] ) . '</a> · ' . esc_html( condoclock_date( $v['posted'] ) ) . '</li>';
			}
			echo '</ul></details>';
		}
		echo '</div><div class="ccb-r">';
		echo self::tag( $r['status'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in tag().
		if ( $r['file'] ) {
			echo '<a class="ccb-link" href="' . esc_url( Files::url( $id ) ) . '">' . esc_html__( 'Open', 'condoclock' ) . '</a>';
		}

		$simple = array();
		if ( 'posted' !== $r['status'] ) {
			$simple['post'] = array( __( 'Post to owners', 'condoclock' ), '' );
		}
		if ( 'posted' === $r['status'] ) {
			$simple['withdraw'] = array( __( 'Withdraw', 'condoclock' ), 'ghost' );
		}
		foreach ( $simple as $do => $b ) {
			echo '<form method="post" action="' . esc_url( $post_to ) . '">';
			self::form_start( 'condoclock_record' );
			echo '<input type="hidden" name="record" value="' . (int) $id . '"><button class="ccb-btn small ' . esc_attr( $b[1] ) . '" name="do" value="' . esc_attr( $do ) . '">' . esc_html( $b[0] ) . '</button></form>';
		}
		if ( $r['file'] ) {
			echo '<form class="ccc-inline" method="post" action="' . esc_url( $post_to ) . '" enctype="multipart/form-data">';
			self::form_start( 'condoclock_record' );
			echo '<input type="hidden" name="record" value="' . (int) $id . '"><label class="ccb-sr" for="cc-rep-' . (int) $id . '">' . esc_html__( 'New file', 'condoclock' ) . '</label><input type="file" id="cc-rep-' . (int) $id . '" name="file" required>';
			echo '<button class="ccb-btn small ghost" name="do" value="replace">' . esc_html__( 'Replace file', 'condoclock' ) . '</button></form>';
		}
		echo '<button type="button" class="ccb-btn small danger" data-open="cc-del-' . (int) $id . '">' . esc_html__( 'Delete', 'condoclock' ) . '</button>';
		echo '<dialog class="ccb-dialog" id="cc-del-' . (int) $id . '" aria-labelledby="cc-del-t-' . (int) $id . '"><h2 id="cc-del-t-' . (int) $id . '">' . esc_html__( 'Delete this record?', 'condoclock' ) . '</h2>';
		echo '<p>' . esc_html__( 'The file and its earlier versions are removed for good. To hide it from owners but keep it, use Withdraw instead.', 'condoclock' ) . '</p>';
		echo '<form method="post" action="' . esc_url( $post_to ) . '">';
		self::form_start( 'condoclock_record' );
		echo '<input type="hidden" name="record" value="' . (int) $id . '"><input type="hidden" name="do" value="delete">';
		echo '<p class="ccb-f"><label for="cc-conf-' . (int) $id . '">' . esc_html__( 'Type delete to confirm', 'condoclock' ) . '</label><input type="text" id="cc-conf-' . (int) $id . '" name="confirm" autocomplete="off" required pattern="delete"></p>';
		echo '<div class="ccb-actions"><button class="ccb-btn danger">' . esc_html__( 'Delete', 'condoclock' ) . '</button><button type="button" class="ccb-btn ghost" data-close>' . esc_html__( 'Cancel', 'condoclock' ) . '</button></div></form></dialog>';
		echo '</div>';
		if ( Records::needs_check( $id ) ) {
			self::scan_box( $r );
		}
		echo '</li>';
	}

	/**
	 * Not applicable card.
	 *
	 * @param string $key     Category.
	 * @param array  $na      Not applicable list.
	 * @param string $post_to Form action.
	 */
	private static function na_card( $key, $na, $post_to ) {
		$cur = isset( $na[ $key ] ) ? $na[ $key ] : null;
		echo '<div class="ccb-card" id="cc-na"><h2>' . esc_html__( 'Not applicable', 'condoclock' ) . '</h2>';
		echo '<p>' . esc_html__( 'If your association has nothing of this kind, say why. It stops counting as missing.', 'condoclock' ) . '</p>';
		echo '<form class="ccb-f" method="post" action="' . esc_url( $post_to ) . '">';
		self::form_start( 'condoclock_na' );
		echo '<input type="hidden" name="category" value="' . esc_attr( $key ) . '"><input type="hidden" name="from" value="cat">';
		echo '<p><label for="cc-reason">' . esc_html__( 'Reason', 'condoclock' ) . '</label><textarea id="cc-reason" name="reason" rows="2" maxlength="200" required>' . esc_textarea( $cur ? $cur['reason'] : '' ) . '</textarea><span class="ccb-hint">' . esc_html__( 'Up to 200 characters, such as "No building permits open this year."', 'condoclock' ) . '</span></p>';
		$show = $cur ? ! empty( $cur['show'] ) : true;
		echo '<p><label><input type="checkbox" name="show" value="1"' . checked( $show, true, false ) . '> ' . esc_html__( 'Show reason to owners', 'condoclock' ) . '</label></p>';
		echo '<div class="ccb-actions"><button class="ccb-btn ghost">' . esc_html__( 'Mark not applicable', 'condoclock' ) . '</button></div></form>';
		if ( $cur ) {
			echo '<form method="post" action="' . esc_url( $post_to ) . '">';
			self::form_start( 'condoclock_na' );
			echo '<input type="hidden" name="category" value="' . esc_attr( $key ) . '"><input type="hidden" name="from" value="cat"><input type="hidden" name="clear" value="1">';
			echo '<button class="ccb-link">' . esc_html__( 'It applies after all', 'condoclock' ) . '</button></form>';
		}
		echo '</div>';
	}

	/**
	 * Owners screen.
	 */
	public static function render_owners() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- tab selection.
		$tab  = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'owners';
		$tabs = array( 'owners' => __( 'Owners', 'condoclock' ) );
		/**
		 * Owners screen tabs. CondoClock Pro adds Access requests and Import.
		 *
		 * @param array $tabs key => label.
		 */
		$tabs = apply_filters( 'condoclock_owner_tabs', $tabs );
		self::head( __( 'Owners', 'condoclock' ), esc_html__( 'Owners and association employees who can log in to see the records.', 'condoclock' ) );
		echo '<div class="ccb-wrap">';
		if ( count( $tabs ) > 1 ) {
			echo '<nav class="ccb-tabs">';
			foreach ( $tabs as $k => $label ) {
				printf( '<a href="%1$s"%2$s>%3$s</a>', esc_url( admin_url( 'admin.php?page=condoclock-owners&tab=' . $k ) ), $k === $tab ? ' class="on" aria-current="page"' : '', esc_html( $label ) );
			}
			echo '</nav>';
		}
		if ( 'owners' !== $tab && isset( $tabs[ $tab ] ) ) {
			/**
			 * Renders a Pro tab on the Owners screen.
			 */
			do_action( 'condoclock_owner_tab_' . $tab );
			echo '</div>';
			self::foot();
			return;
		}
		$post_to = admin_url( 'admin-post.php' );
		echo '<div class="ccb-cols"><section class="ccb-list">';
		$owners = Owners::all();
		if ( ! $owners ) {
			echo '<p class="ccb-empty">' . esc_html__( 'No owners yet. Add them one by one, or import a list (Pro).', 'condoclock' ) . '</p>';
		} else {
			echo '<table class="ccb-table"><thead><tr><th>' . esc_html__( 'Name', 'condoclock' ) . '</th><th>' . esc_html__( 'Unit or lot', 'condoclock' ) . '</th><th>' . esc_html__( 'Last login', 'condoclock' ) . '</th><th><span class="ccb-sr">' . esc_html__( 'Actions', 'condoclock' ) . '</span></th></tr></thead><tbody>';
			foreach ( $owners as $u ) {
				$last = (int) get_user_meta( $u->ID, 'condoclock_last_login', true );
				$emp  = (bool) get_user_meta( $u->ID, 'condoclock_employee', true );
				echo '<tr><td><b>' . esc_html( $u->display_name ) . '</b><small>' . esc_html( $u->user_email ) . ( $emp ? ' · ' . esc_html__( 'Employee', 'condoclock' ) : '' ) . '</small></td>';
				echo '<td>' . esc_html( (string) get_user_meta( $u->ID, 'condoclock_unit', true ) ) . '</td>';
				echo '<td>' . esc_html( $last ? condoclock_date( $last ) : __( 'Never', 'condoclock' ) ) . '</td><td>';
				foreach ( array(
					'resend' => array( __( 'Send login again', 'condoclock' ), 'ghost' ),
					'remove' => array( __( 'Remove access', 'condoclock' ), 'danger' ),
				) as $do => $b ) {
					echo '<form method="post" action="' . esc_url( $post_to ) . '">';
					self::form_start( 'condoclock_owner' );
					echo '<input type="hidden" name="user" value="' . (int) $u->ID . '"><button class="ccb-btn small ' . esc_attr( $b[1] ) . '" name="do" value="' . esc_attr( $do ) . '">' . esc_html( $b[0] ) . '</button></form>';
				}
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</section><aside class="ccb-side">';

		echo '<div class="ccb-card"><h2>' . esc_html__( 'Add an owner', 'condoclock' ) . '</h2><p>' . esc_html__( 'They get an email to set their password, then see every posted record.', 'condoclock' ) . '</p>';
		echo '<form class="ccb-f" method="post" action="' . esc_url( $post_to ) . '">';
		self::form_start( 'condoclock_add_owner' );
		echo '<p><label for="cc-o-name">' . esc_html__( 'Name', 'condoclock' ) . '</label><input type="text" id="cc-o-name" name="name" required maxlength="100"></p>';
		echo '<p><label for="cc-o-email">' . esc_html__( 'Email', 'condoclock' ) . '</label><input type="email" id="cc-o-email" name="email" required></p>';
		echo '<p><label for="cc-o-unit">' . esc_html__( 'Unit or lot number', 'condoclock' ) . '</label><input type="text" id="cc-o-unit" name="unit" maxlength="40"></p>';
		echo '<button class="ccb-btn block">' . esc_html__( 'Add owner', 'condoclock' ) . '</button></form></div>';

		echo '<div class="ccb-card"><h2>' . esc_html__( 'Employees', 'condoclock' ) . '</h2><p>' . esc_html__( 'Let a user who works for the association see the records. Enter their username or email.', 'condoclock' ) . '</p>';
		echo '<form class="ccb-f" method="post" action="' . esc_url( $post_to ) . '">';
		self::form_start( 'condoclock_owner' );
		echo '<input type="hidden" name="do" value="employee"><p><label for="cc-o-login">' . esc_html__( 'Username or email', 'condoclock' ) . '</label><input type="text" id="cc-o-login" name="login" required></p>';
		echo '<button class="ccb-btn ghost block">' . esc_html__( 'Give access', 'condoclock' ) . '</button></form></div>';
		echo '</aside></div></div>';
		self::foot();
	}

	/**
	 * Notices screen.
	 */
	public static function render_notices() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- edit selection.
		$edit    = isset( $_GET['edit'] ) ? Notices::get( absint( $_GET['edit'] ) ) : null;
		$post_to = admin_url( 'admin-post.php' );
		$s       = condoclock_settings();
		$sub     = $s['notices_public'] ? esc_html__( 'Everyone can see the Notices page.', 'condoclock' ) : esc_html__( 'Only owners can see the Notices page.', 'condoclock' );
		self::head( __( 'Notices', 'condoclock' ), $sub );
		echo '<div class="ccb-wrap">';
		$warn = Setup::title_warning();
		if ( $warn ) {
			echo '<div class="ccb-note warn">';
			self::icon( 'alert' );
			echo '<p>' . esc_html( $warn ) . '</p></div>';
		}
		echo '<div class="ccb-cols"><section class="ccb-list">';
		foreach ( array(
			'upcoming' => __( 'Upcoming', 'condoclock' ),
			'past'     => __( 'Past notices', 'condoclock' ),
		) as $when => $label ) {
			$list = Notices::query( $when, false );
			echo '<h3 class="ccb-group">' . esc_html( $label ) . '</h3>';
			if ( ! $list ) {
				echo '<p class="ccb-empty">' . esc_html__( 'No meetings scheduled right now.', 'condoclock' ) . '</p>';
				continue;
			}
			echo '<table class="ccb-table"><tbody>';
			foreach ( array_slice( $list, 0, 'past' === $when ? 20 : 100 ) as $m ) {
				$docs = array();
				foreach ( array(
					'notice' => __( 'Notice', 'condoclock' ),
					'agenda' => __( 'Agenda', 'condoclock' ),
					'vote'   => __( 'Documents to be voted on', 'condoclock' ),
				) as $slot => $dl ) {
					if ( ! empty( $m['docs'][ $slot ] ) ) {
						$docs[] = '<a href="' . esc_url( Files::url( $m['id'], $slot ) ) . '">' . esc_html( $dl ) . '</a>';
					}
				}
				echo '<tr><td><b>' . esc_html( $m['title'] ) . '</b><small>' . esc_html( condoclock_datetime( $m['ts'] ) . ( $m['place'] ? ' · ' . $m['place'] : '' ) ) . '</small>';
				if ( ! $m['published'] ) {
					echo '<small>' . esc_html__( 'Not on the Notices page yet', 'condoclock' ) . '</small>';
				}
				echo '</td><td>' . wp_kses( implode( ' · ', $docs ), self::kses() ) . '</td><td>';
				echo '<a class="ccb-btn small ghost" href="' . esc_url( admin_url( 'admin.php?page=condoclock-notices&edit=' . $m['id'] ) ) . '">' . esc_html__( 'Edit', 'condoclock' ) . '</a>';
				echo '<form method="post" action="' . esc_url( $post_to ) . '">';
				wp_nonce_field( 'condoclock_notice' );
				echo '<input type="hidden" name="action" value="condoclock_notice"><input type="hidden" name="meeting" value="' . (int) $m['id'] . '"><button class="ccb-btn small danger" name="do" value="delete">' . esc_html__( 'Delete', 'condoclock' ) . '</button></form>';
				echo '</td></tr>';
			}
			echo '</tbody></table>';
		}
		echo '</section><aside class="ccb-side">';
		self::notice_form( $edit, $post_to );
		echo '</aside></div></div>';
		self::foot();
	}

	/**
	 * Add or edit a notice.
	 *
	 * @param array|null $m       Meeting being edited.
	 * @param string     $post_to Form action.
	 */
	private static function notice_form( $m, $post_to ) {
		$date = '';
		$time = '';
		if ( $m && $m['ts'] ) {
			$dt   = ( new \DateTimeImmutable( '@' . $m['ts'] ) )->setTimezone( wp_timezone() );
			$date = $dt->format( 'Y-m-d' );
			$time = $dt->format( 'H:i' );
		}
		echo '<div class="ccb-card"><h2>' . esc_html( $m ? __( 'Edit notice', 'condoclock' ) : __( 'Add a notice', 'condoclock' ) ) . '</h2>';
		echo '<form class="ccb-f" method="post" action="' . esc_url( $post_to ) . '" enctype="multipart/form-data">';
		wp_nonce_field( 'condoclock_notice' );
		echo '<input type="hidden" name="action" value="condoclock_notice"><input type="hidden" name="do" value="save"><input type="hidden" name="from" value="condoclock-notices">';
		if ( $m ) {
			echo '<input type="hidden" name="meeting" value="' . (int) $m['id'] . '">';
		}
		echo '<p><label for="cc-n-title">' . esc_html__( 'Meeting title', 'condoclock' ) . '</label><input type="text" id="cc-n-title" name="title" required maxlength="160" value="' . esc_attr( $m ? $m['title'] : '' ) . '" placeholder="' . esc_attr__( 'Board meeting', 'condoclock' ) . '"></p>';
		echo '<p><label for="cc-n-type">' . esc_html__( 'Type', 'condoclock' ) . '</label><select id="cc-n-type" name="type">';
		foreach ( Rules::meeting_types() as $k => $t ) {
			echo '<option value="' . esc_attr( $k ) . '"' . selected( $m ? $m['type'] : 'board', $k, false ) . '>' . esc_html( is_array( $t ) ? $t['name'] : $t ) . '</option>';
		}
		echo '</select></p>';
		echo '<div class="ccb-grid2"><p><label for="cc-n-date">' . esc_html__( 'Date', 'condoclock' ) . '</label><input type="date" id="cc-n-date" name="date" required value="' . esc_attr( $date ) . '"></p>';
		echo '<p><label for="cc-n-time">' . esc_html__( 'Time', 'condoclock' ) . '</label><input type="time" id="cc-n-time" name="time" required value="' . esc_attr( $time ) . '"></p></div>';
		echo '<p><label for="cc-n-place">' . esc_html__( 'Place', 'condoclock' ) . '</label><input type="text" id="cc-n-place" name="place" maxlength="200" value="' . esc_attr( $m ? $m['place'] : '' ) . '" placeholder="' . esc_attr__( 'Clubhouse, 2nd floor', 'condoclock' ) . '"></p>';
		echo '<p><label for="cc-n-video">' . esc_html__( 'Video link (optional)', 'condoclock' ) . '</label><input type="url" id="cc-n-video" name="video" value="' . esc_attr( $m ? $m['video'] : '' ) . '" placeholder="https://"></p>';
		foreach ( array(
			'notice' => __( 'Notice file', 'condoclock' ),
			'agenda' => __( 'Agenda file', 'condoclock' ),
			'vote'   => __( 'Documents to be voted on', 'condoclock' ),
		) as $slot => $label ) {
			$have = $m && ! empty( $m['docs'][ $slot ] );
			echo '<p><label for="cc-n-' . esc_attr( $slot ) . '">' . esc_html( $label ) . '</label><input type="file" id="cc-n-' . esc_attr( $slot ) . '" name="doc_' . esc_attr( $slot ) . '">';
			if ( $have ) {
				/* translators: %s: file name. */
				echo '<span class="ccb-hint">' . esc_html( sprintf( __( 'Now: %s. Choose a file to replace it.', 'condoclock' ), $m['docs'][ $slot ]['name'] ) ) . '</span>';
			}
			echo '</p>';
		}
		echo '<button class="ccb-btn block">' . esc_html__( 'Save notice', 'condoclock' ) . '</button></form></div>';
	}

	/**
	 * Saves the Settings screen.
	 */
	public static function handle_settings() {
		if ( ! condoclock_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'condoclock' ), 403 );
		}
		check_admin_referer( 'condoclock_settings' );
		$presets = Blocks::presets();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.MissingUnslash,WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitize_key below.
		$style = isset( $_POST['style'] ) ? sanitize_key( wp_unslash( $_POST['style'] ) ) : 'clean';
		if ( ! isset( $presets[ $style ] ) || ( ! empty( $presets[ $style ]['pro'] ) && ! condoclock_is_pro() ) ) {
			$style = 'clean';
		}
		$changes = array(
			'notices_public'      => ! empty( $_POST['notices_public'] ),
			'show_na_reason'      => ! empty( $_POST['show_na_reason'] ),
			'delete_on_uninstall' => ! empty( $_POST['delete_on_uninstall'] ),
			'style'               => $style,
		);
		if ( isset( $_POST['name'] ) ) {
			$changes['name'] = mb_substr( sanitize_text_field( wp_unslash( $_POST['name'] ) ), 0, 120 );
		}
		/**
		 * Extra settings saved by CondoClock Pro.
		 *
		 * @param array $changes Settings to save.
		 */
		$changes = apply_filters( 'condoclock_save_settings', $changes );
		condoclock_update_settings( $changes );
		Records::changed( 0, 'settings' );
		wp_safe_redirect( admin_url( 'admin.php?page=condoclock-settings&cc_msg=settings' ) );
		exit;
	}

	/**
	 * Settings screen.
	 */
	public static function render_settings() {
		$s = condoclock_settings();
		self::head( __( 'Settings', 'condoclock' ), self::sub_line() );
		echo '<div class="ccb-wrap"><form class="ccb-card ccb-f" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
		self::form_start( 'condoclock_settings' );

		echo '<h2>' . esc_html__( 'Association', 'condoclock' ) . '</h2>';
		echo '<p><label for="cc-s-name">' . esc_html__( 'Association name', 'condoclock' ) . '</label><input type="text" id="cc-s-name" name="name" maxlength="120" value="' . esc_attr( $s['name'] ) . '"></p>';
		echo '<p><a class="ccb-link" href="' . esc_url( Setup::url( 1 ) ) . '">' . esc_html__( 'Change the association type or size (runs setup again)', 'condoclock' ) . '</a></p>';

		echo '<h2>' . esc_html__( 'Notices page', 'condoclock' ) . '</h2><fieldset><legend class="ccb-sr">' . esc_html__( 'Who can see notices', 'condoclock' ) . '</legend>';
		echo '<p><label><input type="radio" name="notices_public" value="1"' . checked( (bool) $s['notices_public'], true, false ) . '> ' . esc_html__( 'Public', 'condoclock' ) . '</label><br><label><input type="radio" name="notices_public" value=""' . checked( (bool) $s['notices_public'], false, false ) . '> ' . esc_html__( 'Owners only', 'condoclock' ) . '</label></p>';
		echo '<p class="ccb-hint">' . esc_html__( 'The statute asks for notices in plain view on the front page or on a page called Notices linked from it.', 'condoclock' ) . '</p></fieldset>';

		echo '<h2>' . esc_html__( 'Owners\' records page', 'condoclock' ) . '</h2>';
		echo '<p><label><input type="checkbox" name="show_na_reason" value="1"' . checked( (bool) $s['show_na_reason'], true, false ) . '> ' . esc_html__( 'Show owners why a category is not applicable', 'condoclock' ) . '</label></p>';

		echo '<h2>' . esc_html__( 'Look', 'condoclock' ) . '</h2><div class="ccw-presets">';
		foreach ( Blocks::presets() as $k => $p ) {
			$locked = ! empty( $p['pro'] ) && ! condoclock_is_pro();
			echo '<label class="ccw-preset' . ( $locked ? ' locked' : '' ) . '"><input type="radio" name="style" value="' . esc_attr( $k ) . '"' . checked( $s['style'], $k, false ) . disabled( $locked, true, false ) . '><span class="ccw-swat">';
			foreach ( $p['swat'] as $col ) {
				echo '<i style="background:' . esc_attr( $col ) . '"></i>';
			}
			echo '</span><b>' . esc_html( $p['name'] ) . '</b>' . ( $locked ? ' <span class="ccb-pro">PRO</span>' : '' ) . '</label>';
		}
		echo '</div>';
		/**
		 * More settings from CondoClock Pro (reminders, clocks, logo).
		 *
		 * @param array $s Settings.
		 */
		do_action( 'condoclock_settings_fields', $s );

		echo '<h2>' . esc_html__( 'When you remove the plugin', 'condoclock' ) . '</h2>';
		echo '<p><label><input type="checkbox" name="delete_on_uninstall" value="1"' . checked( (bool) $s['delete_on_uninstall'], true, false ) . '> ' . esc_html__( 'Delete all data when uninstalled', 'condoclock' ) . '</label><span class="ccb-hint">' . esc_html__( 'Off by default. When on, deleting the plugin also deletes every record, file and setting.', 'condoclock' ) . '</span></p>';
		echo '<div class="ccb-actions"><button class="ccb-btn">' . esc_html__( 'Save settings', 'condoclock' ) . '</button></div></form></div>';
		self::foot();
	}

	/**
	 * Help screen.
	 */
	public static function render_help() {
		self::head( __( 'Help', 'condoclock' ), self::sub_line() );
		echo '<div class="ccb-wrap"><div class="ccb-note warn">';
		self::icon( 'alert' );
		echo '<p>' . esc_html__( 'CondoClock helps you keep records organised and on time. It isn\'t legal advice: your association\'s attorney decides what your documents and deadlines are.', 'condoclock' ) . '</p></div>';
		echo '<section class="ccb-card"><h2>' . esc_html__( 'What each category means', 'condoclock' ) . '</h2>';
		/* translators: %s: statute reference. */
		echo '<p>' . esc_html( sprintf( __( 'Section %s lists these records. Rules as of the date shown above.', 'condoclock' ), Rules::list_ref() ) ) . '</p>';
		echo '<table class="ccb-table"><tbody>';
		foreach ( Rules::categories() as $c ) {
			echo '<tr><td class="ccb-mono">' . esc_html( $c['short'] ) . '</td><td><b>' . esc_html( $c['name'] ) . '</b><small>' . esc_html( $c['hint'] ) . '</small></td><td class="ccb-mono">' . esc_html( $c['ref'] ) . '</td></tr>';
		}
		echo '</tbody></table></section>';
		echo '<section class="ccb-card"><h2>' . esc_html__( 'Good to know', 'condoclock' ) . '</h2><ul>';
		$tips = array(
			__( 'Owners log in on the Owners\' Records page. Add them on the Owners screen and they get an email to set a password.', 'condoclock' ),
			__( 'Files are kept in a private folder and only sent to logged-in owners. The Status screen tests this.', 'condoclock' ),
			__( 'Replacing a file keeps the old one under "Earlier versions", so owners can see what changed.', 'condoclock' ),
			__( 'Withdraw hides a record from owners without deleting it.', 'condoclock' ),
			__( 'The Notices page lists upcoming meetings, soonest first. Past meetings move to "Past notices" after they start.', 'condoclock' ),
		);
		foreach ( $tips as $t ) {
			echo '<li>' . esc_html( $t ) . '</li>';
		}
		echo '</ul></section></div>';
		self::foot();
	}

	/**
	 * Where the Pro button goes: the hosted checkout.
	 *
	 * @return string
	 */
	public static function pro_url() {
		/**
		 * URL of the Pro checkout.
		 *
		 * @param string $url URL.
		 */
		return apply_filters( 'condoclock_pro_url', 'https://checkout.freemius.com/plugin/45028/plan/79404/' );
	}

	/**
	 * Pro tab (Free only).
	 */
	public static function render_pro() {
		self::head( __( 'Get Pro', 'condoclock' ), '' );
		echo '<div class="ccb-wrap"><section class="ccb-card ccp"><h2>' . esc_html__( 'Stay ahead of every deadline', 'condoclock' ) . '</h2><ul>';
		$lines = array(
			__( 'See what\'s due before it\'s late, with a clock on every record.', 'condoclock' ),
			__( 'Post meeting notices with a countdown, and get reminders until they\'re up.', 'condoclock' ),
			__( 'Let owners request a login, and approve it in one click.', 'condoclock' ),
			__( 'Keep a dated history you can export when someone asks.', 'condoclock' ),
			__( 'Catch emails, phone numbers and other private details before a file goes up.', 'condoclock' ),
		);
		foreach ( $lines as $l ) {
			echo '<li>';
			self::icon( 'check' );
			echo esc_html( $l ) . '</li>';
		}
		echo '</ul><p class="ccp-price">' . esc_html__( 'USD 149 a year for one association. USD 449 a year for up to 10 (for web designers and managers).', 'condoclock' ) . '</p>';
		echo '<a class="ccb-btn" href="' . esc_url( self::pro_url() ) . '" target="_blank" rel="noopener">' . esc_html__( 'Get CondoClock Pro', 'condoclock' ) . '</a>';
		echo '<small>' . esc_html__( '14-day money-back guarantee.', 'condoclock' ) . '</small></section></div>';
		self::foot();
	}

	/**
	 * Dashboard widget.
	 */
	public static function dashboard() {
		if ( ! condoclock_can_manage() ) {
			return;
		}
		wp_add_dashboard_widget( 'condoclock', 'CondoClock', array( __CLASS__, 'render_widget' ) );
	}

	/**
	 * Dashboard widget body: "3 records missing, next meeting in 9 days".
	 */
	public static function render_widget() {
		$sum  = Records::summary();
		$next = Notices::query( 'upcoming', false );
		/* translators: %d: number of record types missing. */
		$line = sprintf( _n( '%d record missing', '%d records missing', $sum['missing'], 'condoclock' ), $sum['missing'] );
		if ( $next ) {
			$days = (int) floor( ( $next[0]['ts'] - time() ) / DAY_IN_SECONDS );
			/* translators: %d: days until the meeting. */
			$line .= ', ' . ( $days < 1 ? __( 'next meeting today', 'condoclock' ) : sprintf( _n( 'next meeting in %d day', 'next meeting in %d days', $days, 'condoclock' ), $days ) );
		}
		echo '<p>' . esc_html( $line ) . '</p><p><a href="' . esc_url( admin_url( 'admin.php?page=condoclock' ) ) . '">' . esc_html__( 'Open records', 'condoclock' ) . '</a></p>';
	}
}

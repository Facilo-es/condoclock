<?php
/**
 * The three-step setup wizard: association, owners' pages, look.
 *
 * @package CondoClock
 */

namespace CondoClock;

defined( 'ABSPATH' ) || exit;

/**
 * Setup wizard, rerun any time from Settings.
 */
class Setup {

	/**
	 * Hooks.
	 */
	public static function init() {
		add_action( 'admin_post_condoclock_setup', array( __CLASS__, 'handle' ) );
	}

	/**
	 * Page URL for a step.
	 *
	 * @param int $step 1 to 3.
	 * @return string
	 */
	public static function url( $step = 1 ) {
		return admin_url( 'admin.php?page=condoclock-setup&step=' . (int) $step );
	}

	/**
	 * The covered or not covered sentence.
	 *
	 * @param bool $covered Covered by the website rule.
	 * @return string
	 */
	public static function coverage_text( $covered ) {
		return $covered
			? __( 'Associations like yours must keep these records on a website or app, with a page only owners can open. CondoClock gives you both.', 'condoclock' )
			: __( 'Your association is under the size where the website rule applies. Many smaller associations use CondoClock anyway, so owners always know where to look.', 'condoclock' );
	}

	/**
	 * Finds the page that holds a block: first the stored ID, then any page with the block.
	 *
	 * @param string $which records or notices.
	 * @return int Page ID or 0.
	 */
	public static function find_page( $which ) {
		$s  = condoclock_settings();
		$id = isset( $s['pages'][ $which ] ) ? (int) $s['pages'][ $which ] : 0;
		if ( $id && in_array( get_post_status( $id ), array( 'publish', 'draft', 'private', 'pending', 'future' ), true ) ) {
			return $id;
		}
		$found = get_posts(
			array(
				'post_type'      => 'page',
				'post_status'    => array( 'publish', 'draft', 'private', 'pending', 'future' ),
				's'              => 'wp:condoclock/' . $which,
				'search_columns' => array( 'post_content' ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
			)
		);
		return $found ? (int) $found[0] : 0;
	}

	/**
	 * Creates the two pages unless they already exist. Running it twice never makes duplicates (T1).
	 *
	 * @return array records => ID, notices => ID.
	 */
	public static function ensure_pages() {
		$pages = array(
			'records' => __( "Owners' Records", 'condoclock' ),
			'notices' => __( 'Notices', 'condoclock' ),
		);
		$ids   = array();
		foreach ( $pages as $which => $title ) {
			$id = self::find_page( $which );
			if ( ! $id ) {
				$id = wp_insert_post(
					array(
						'post_type'    => 'page',
						'post_status'  => 'publish',
						'post_title'   => $title,
						'post_content' => '<!-- wp:condoclock/' . $which . ' /-->',
					)
				);
				$id = is_wp_error( $id ) ? 0 : (int) $id;
			} elseif ( 'publish' !== get_post_status( $id ) ) {
				wp_update_post(
					array(
						'ID'          => $id,
						'post_status' => 'publish',
					)
				);
			}
			$ids[ $which ] = $id;
		}
		condoclock_update_settings( array( 'pages' => $ids ) );
		return $ids;
	}

	/**
	 * Adds the Notices page to the site's main menu.
	 *
	 * @param int $page_id Notices page.
	 * @return string added, already or none.
	 */
	public static function add_to_menu( $page_id ) {
		if ( ! $page_id ) {
			return 'none';
		}
		$label = get_the_title( $page_id );

		// Block themes keep their menu in a wp_navigation post.
		if ( wp_is_block_theme() ) {
			$nav = get_posts(
				array(
					'post_type'      => 'wp_navigation',
					'post_status'    => 'publish',
					'posts_per_page' => 1,
					'orderby'        => 'date',
					'order'          => 'ASC',
				)
			);
			if ( ! $nav ) {
				return 'none';
			}
			$content = $nav[0]->post_content;
			// A Page List block already shows every published page.
			if ( false !== strpos( $content, '"id":' . $page_id . ',' ) || false !== strpos( $content, '<!-- wp:page-list' ) ) {
				return 'already';
			}
			$link = sprintf(
				'<!-- wp:navigation-link {"label":%s,"type":"page","id":%d,"url":%s,"kind":"post-type"} /-->',
				wp_json_encode( $label ),
				$page_id,
				wp_json_encode( get_permalink( $page_id ) )
			);
			wp_update_post(
				array(
					'ID'           => $nav[0]->ID,
					'post_content' => $content . "\n" . $link,
				)
			);
			return 'added';
		}

		// Classic themes: the menu in the first theme location, else the first menu.
		$menu_id   = 0;
		$locations = get_nav_menu_locations();
		foreach ( array( 'primary', 'main', 'header', 'menu-1' ) as $loc ) {
			if ( ! empty( $locations[ $loc ] ) ) {
				$menu_id = (int) $locations[ $loc ];
				break;
			}
		}
		if ( ! $menu_id && $locations ) {
			$menu_id = (int) current( array_filter( $locations ) );
		}
		if ( ! $menu_id ) {
			return 'none';
		}
		foreach ( (array) wp_get_nav_menu_items( $menu_id ) as $item ) {
			if ( isset( $item->object_id ) && (int) $item->object_id === (int) $page_id ) {
				return 'already';
			}
		}
		wp_update_nav_menu_item(
			$menu_id,
			0,
			array(
				'menu-item-title'     => $label,
				'menu-item-object'    => 'page',
				'menu-item-object-id' => $page_id,
				'menu-item-type'      => 'post_type',
				'menu-item-status'    => 'publish',
			)
		);
		return 'added';
	}

	/**
	 * Warns when the board renamed the Notices page.
	 *
	 * @return string Warning or ''.
	 */
	public static function title_warning() {
		$id = self::find_page( 'notices' );
		if ( ! $id ) {
			return '';
		}
		$title = get_the_title( $id );
		if ( in_array( $title, array( 'Notices', __( 'Notices', 'condoclock' ) ), true ) ) {
			return '';
		}
		/* translators: %s: the page's current title. */
		return sprintf( __( 'Your notices page is now called "%s". Owners look for "Notices", so we suggest keeping that title.', 'condoclock' ), $title );
	}

	/**
	 * Saves a wizard step.
	 */
	public static function handle() {
		if ( ! condoclock_can_manage() ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'condoclock' ), 403 );
		}
		check_admin_referer( 'condoclock_setup' );
		$step = isset( $_POST['step'] ) ? (int) $_POST['step'] : 1;

		if ( 1 === $step ) {
			$mode  = isset( $_POST['mode'] ) && 'hoa' === $_POST['mode'] ? 'hoa' : 'condo';
			$count = isset( $_POST['count'] ) ? (int) $_POST['count'] : 0;
			$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
			if ( $count < 1 || $count > 100000 || '' === $name ) {
				set_transient( 'condoclock_errors_' . get_current_user_id(), array( __( 'Please fill in the name and a number of units between 1 and 100,000.', 'condoclock' ) ), 60 );
				wp_safe_redirect( add_query_arg( 'cc_err', 1, self::url( 1 ) ) );
				exit;
			}
			$changes = array(
				'mode'      => $mode,
				'count'     => $count,
				'timeshare' => ( 'condo' === $mode && ! empty( $_POST['timeshare'] ) ) ? 1 : 0,
				'name'      => mb_substr( $name, 0, 120 ),
			);
			$old     = condoclock_settings();
			condoclock_update_settings( $changes );
			if ( $old['mode'] !== $mode ) {
				Records::changed( 0, 'mode' );
			}
			wp_safe_redirect( self::url( 2 ) );
			exit;
		}

		if ( 2 === $step ) {
			$ids  = self::ensure_pages();
			$menu = ! empty( $_POST['menu'] ) ? self::add_to_menu( $ids['notices'] ) : 'skip';
			update_option( 'condoclock_setup_menu', $menu, false );
			wp_safe_redirect( self::url( 3 ) );
			exit;
		}

		$presets = Blocks::presets();
		$style   = isset( $_POST['style'] ) ? sanitize_key( $_POST['style'] ) : 'clean';
		if ( ! isset( $presets[ $style ] ) || ( ! empty( $presets[ $style ]['pro'] ) && ! condoclock_is_pro() ) ) {
			$style = 'clean';
		}
		condoclock_update_settings(
			array(
				'style'      => $style,
				'setup_done' => 1,
			)
		);
		if ( ! get_option( 'condoclock_selftest' ) ) {
			Files::self_test();
		}
		wp_safe_redirect( admin_url( 'admin.php?page=condoclock' ) );
		exit;
	}

	/**
	 * Step dots.
	 *
	 * @param int $step Current step.
	 */
	private static function steps( $step ) {
		$names = array(
			1 => __( 'Association', 'condoclock' ),
			2 => __( 'Pages', 'condoclock' ),
			3 => __( 'Look', 'condoclock' ),
		);
		echo '<ol class="ccw-steps">';
		foreach ( $names as $n => $label ) {
			$class = $n < $step ? 'done' : ( $n === $step ? 'on' : '' );
			printf( '<li class="%s"%s><span>%d</span>%s</li>', esc_attr( $class ), $n === $step ? ' aria-current="step"' : '', (int) $n, esc_html( $label ) );
		}
		echo '</ol>';
	}

	/**
	 * Opens a step form.
	 *
	 * @param int $step Step.
	 */
	private static function form_open( $step ) {
		echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" class="ccb-card ccw-card">';
		wp_nonce_field( 'condoclock_setup' );
		echo '<input type="hidden" name="action" value="condoclock_setup"><input type="hidden" name="step" value="' . (int) $step . '">';
	}

	/**
	 * Renders the wizard.
	 */
	public static function render() {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- read-only step number.
		$step = isset( $_GET['step'] ) ? max( 1, min( 3, (int) $_GET['step'] ) ) : 1;
		$s    = condoclock_settings();

		Admin::head( __( 'Set up CondoClock', 'condoclock' ), '' );
		echo '<div class="ccb-wrap ccw">';
		self::steps( $step );

		if ( 1 === $step ) {
			$covered = Rules::covered( $s['mode'], $s['count'] ? $s['count'] : 25, (bool) $s['timeshare'] );
			self::form_open( 1 );
			echo '<h2>' . esc_html__( "Let's set up your association", 'condoclock' ) . '</h2>';
			echo '<p class="ccb-f"><label for="cc-name">' . esc_html__( 'Association name', 'condoclock' ) . '</label><input id="cc-name" name="name" type="text" required maxlength="120" value="' . esc_attr( $s['name'] ) . '"></p>';
			echo '<fieldset class="ccb-f"><legend>' . esc_html__( 'Type', 'condoclock' ) . '</legend>';
			echo '<label class="ccw-radio"><input type="radio" name="mode" value="condo"' . checked( $s['mode'], 'condo', false ) . '> ' . esc_html__( 'Condominium association (Chapter 718)', 'condoclock' ) . '</label>';
			echo '<label class="ccw-radio"><input type="radio" name="mode" value="hoa"' . checked( $s['mode'], 'hoa', false ) . '> ' . esc_html__( "Homeowners' association (Chapter 720)", 'condoclock' ) . '</label></fieldset>';
			echo '<p class="ccb-f"><label for="cc-count"><span class="ccw-condo">' . esc_html__( 'Number of units', 'condoclock' ) . '</span><span class="ccw-hoa">' . esc_html__( 'Number of parcels', 'condoclock' ) . '</span></label><input id="cc-count" name="count" type="number" min="1" max="100000" required value="' . esc_attr( $s['count'] ? $s['count'] : '' ) . '"></p>';
			echo '<p class="ccb-f ccw-condo"><label><input type="checkbox" name="timeshare" value="1"' . checked( $s['timeshare'], 1, false ) . '> ' . esc_html__( 'Does the condominium contain timeshare units?', 'condoclock' ) . '</label></p>';
			printf(
				'<p class="ccw-cover" aria-live="polite" data-yes="%1$s" data-no="%2$s">%3$s</p>',
				esc_attr( self::coverage_text( true ) ),
				esc_attr( self::coverage_text( false ) ),
				esc_html( self::coverage_text( $covered ) )
			);
			echo '<p class="ccw-actions"><button class="ccb-btn">' . esc_html__( 'Next', 'condoclock' ) . '</button></p></form>';
		} elseif ( 2 === $step ) {
			self::form_open( 2 );
			echo '<h2>' . esc_html__( "Your owners' pages", 'condoclock' ) . '</h2>';
			echo '<p>' . esc_html__( "We'll create two pages: Owners' Records, which only owners can open, and Notices, for upcoming meetings.", 'condoclock' ) . '</p>';
			$existing = array_filter( array( self::find_page( 'records' ), self::find_page( 'notices' ) ) );
			if ( $existing ) {
				echo '<p class="ccb-hint">' . esc_html__( 'Your pages already exist, so we will keep them and make no copies.', 'condoclock' ) . '</p>';
			}
			echo '<p class="ccb-f"><label><input type="checkbox" name="menu" value="1" checked> ' . esc_html__( 'Add Notices to the main menu', 'condoclock' ) . '</label></p>';
			echo '<p class="ccw-actions"><a class="ccb-btn ghost" href="' . esc_url( self::url( 1 ) ) . '">' . esc_html__( 'Back', 'condoclock' ) . '</a> <button class="ccb-btn">' . esc_html__( 'Create my pages', 'condoclock' ) . '</button></p></form>';
		} else {
			$menu = get_option( 'condoclock_setup_menu', '' );
			$ids  = condoclock_settings()['pages'];
			if ( ! empty( $ids['notices'] ) ) {
				echo '<div class="ccb-note ok">' . condoclock_icon( 'check' ) . '<p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
				printf(
					/* translators: 1: link to Owners' Records page, 2: link to Notices page. */
					esc_html__( 'Your pages are ready: %1$s and %2$s.', 'condoclock' ),
					'<a href="' . esc_url( get_permalink( $ids['records'] ) ) . '">' . esc_html( get_the_title( $ids['records'] ) ) . '</a>',
					'<a href="' . esc_url( get_permalink( $ids['notices'] ) ) . '">' . esc_html( get_the_title( $ids['notices'] ) ) . '</a>'
				);
				if ( 'added' === $menu || 'already' === $menu ) {
					echo ' ' . esc_html__( 'Notices is in your main menu.', 'condoclock' );
				}
				echo '</p></div>';
				if ( 'none' === $menu ) {
					echo '<div class="ccb-note">' . condoclock_icon( 'alert' ) . '<p>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- static SVG.
					printf(
						/* translators: %s: Notices page address. */
						esc_html__( 'Your theme has no menu yet. To link Notices from your front page, open Appearance, then Menus or Editor, add a menu and put the Notices page in it, or add a button or link to %s on your front page.', 'condoclock' ),
						'<code>' . esc_html( get_permalink( $ids['notices'] ) ) . '</code>'
					);
					echo '</p></div>';
				}
			}
			self::form_open( 3 );
			echo '<h2>' . esc_html__( 'Pick a look', 'condoclock' ) . '</h2><div class="ccw-presets">';
			foreach ( Blocks::presets() as $key => $p ) {
				$locked = ! empty( $p['pro'] ) && ! condoclock_is_pro();
				echo '<label class="ccw-preset' . ( $locked ? ' locked' : '' ) . '"><input type="radio" name="style" value="' . esc_attr( $key ) . '"' . checked( $s['style'], $key, false ) . disabled( $locked, true, false ) . '>';
				echo '<span class="ccw-swat">';
				foreach ( (array) $p['swat'] as $c ) {
					echo '<i style="background:' . esc_attr( sanitize_hex_color( $c ) ) . '"></i>';
				}
				echo '</span><b>' . esc_html( $p['name'] ) . '</b>';
				if ( ! empty( $p['pro'] ) ) {
					echo '<span class="ccb-pro">' . esc_html__( 'Pro', 'condoclock' ) . '</span>';
				}
				echo '</label>';
			}
			echo '</div>';
			/**
			 * Extra fields on the look step (CondoClock Pro adds the accent colour).
			 */
			do_action( 'condoclock_setup_look' );
			echo '<p class="ccw-actions"><a class="ccb-btn ghost" href="' . esc_url( self::url( 2 ) ) . '">' . esc_html__( 'Back', 'condoclock' ) . '</a> <button class="ccb-btn">' . esc_html__( 'Go to my records', 'condoclock' ) . '</button></p></form>';
		}
		echo '</div>';
		Admin::foot();
	}
}

<?php
/**
 * Plugin Name:       CondoClock – Condo & HOA Records and Meeting Notices
 * Plugin URI:        https://facilo.es/
 * Description:       Owners-only records for Florida condos and HOAs: every record in its place, dated, with meeting notices on the front page.
 * Version:           1.0.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Facilo
 * Author URI:        https://facilo.es/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       condoclock
 * Domain Path:       /languages
 *
 * @package CondoClock
 */

defined( 'ABSPATH' ) || exit;

define( 'CONDOCLOCK_VERSION', '1.0.0' );
define( 'CONDOCLOCK_FILE', __FILE__ );
define( 'CONDOCLOCK_DIR', plugin_dir_path( __FILE__ ) );
define( 'CONDOCLOCK_URL', plugin_dir_url( __FILE__ ) );

require_once CONDOCLOCK_DIR . 'includes/functions.php';
require_once CONDOCLOCK_DIR . 'includes/class-rules.php';
require_once CONDOCLOCK_DIR . 'includes/class-files.php';
require_once CONDOCLOCK_DIR . 'includes/class-records.php';
require_once CONDOCLOCK_DIR . 'includes/class-owners.php';
require_once CONDOCLOCK_DIR . 'includes/class-notices.php';
require_once CONDOCLOCK_DIR . 'includes/class-blocks.php';
require_once CONDOCLOCK_DIR . 'includes/class-setup.php';
require_once CONDOCLOCK_DIR . 'includes/class-status.php';
require_once CONDOCLOCK_DIR . 'includes/class-privacy.php';
require_once CONDOCLOCK_DIR . 'includes/class-admin.php';

register_activation_hook( __FILE__, array( 'CondoClock\\Owners', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'CondoClock\\Status', 'deactivate' ) );

add_action(
	'plugins_loaded',
	static function () {
		condoclock_load_bundled_translations();
		CondoClock\Records::init();
		CondoClock\Files::init();
		CondoClock\Owners::init();
		CondoClock\Notices::init();
		CondoClock\Blocks::init();
		CondoClock\Setup::init();
		CondoClock\Status::init();
		CondoClock\Privacy::init();
		if ( is_admin() ) {
			CondoClock\Admin::init();
		}
		/**
		 * Fires once CondoClock is loaded. CondoClock Pro hooks in here.
		 */
		do_action( 'condoclock_loaded' );
	}
);

/**
 * Loads the translations bundled with the GitHub download. The wordpress.org
 * zip has no languages folder, and a language pack always wins.
 */
function condoclock_load_bundled_translations() {
	$locale = determine_locale();
	$pack   = WP_LANG_DIR . '/plugins/condoclock-' . $locale . '.mo';
	if ( file_exists( $pack ) || file_exists( substr( $pack, 0, -3 ) . '.l10n.php' ) ) {
		return;
	}
	$file = CONDOCLOCK_DIR . 'languages/condoclock-' . $locale . '.mo';
	if ( is_readable( $file ) ) {
		load_textdomain( 'condoclock', $file, $locale );
	}
}

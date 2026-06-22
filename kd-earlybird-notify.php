<?php
/**
 * Plugin Name: KD EarlyBird Notify
 * Description: Allows users to pre-register for upcoming products, services, or applications and enables admins to send batch notifications with a single click.
 * Version: 1.0
 * Author: KhvichaDev
 * Author URI: https://khvichadev.com
 * Plugin URI: https://github.com/KhvichaDev/kd-earlybird-notify
 * Requires at least: 5.6
 * Requires PHP: 8.2
 * Tested up to: 7.0
 * Text Domain: kd-earlybird-notify
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Define plugin paths and constants.
 */
define('KD_EB_PATH', plugin_dir_path(__FILE__));
define('KD_EB_URL', plugin_dir_url(__FILE__));
define('KD_EB_VERSION', '1.0');

/**
 * Load core database file.
 */
require_once KD_EB_PATH . 'core/kd-database.php';

/**
 * Activation hook to build the database table.
 */
register_activation_hook(__FILE__, 'kd_early_bird_activate');
function kd_early_bird_activate() {
    kd_Database::kd_create_table();
}

/**
 * Load features.
 */
// Load frontend widget/shortcode feature
require_once KD_EB_PATH . 'features/widget/controller/kd-signup-handler.php';
require_once KD_EB_PATH . 'features/widget/ui/kd-signup-form.php';

// Load admin dashboard feature
require_once KD_EB_PATH . 'features/dashboard/controller/kd-admin-handler.php';
require_once KD_EB_PATH . 'features/dashboard/ui/kd-admin-page.php';

/**
 * Initialize all features.
 */
add_action('plugins_loaded', 'kd_early_bird_init');
function kd_early_bird_init() {
    // Instantiate signup form and signup AJAX handler
    new kd_Signup_Form();
    new kd_Signup_Handler();

    // Instantiate admin page and admin AJAX handler
    new kd_Admin_Handler();
}


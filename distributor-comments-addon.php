<?php
/**
 * Plugin Name:       Distributor Comments Add-on
 * Description:       Custom functionality for comments integration
 * Version:           1.0.0
 * Author:            Novembit
 * Author URI:        https://novembit.com
 * License:           GPLv3 or later
 * Domain Path:       /lang/
 * GitHub Plugin URI: git@github.com:madmax3365/distributor-comments-addon.git
 * Text Domain:       distributor-comments
 *
 * @package distributor-comments
 */

/**
 * Bootstrap function
 */
function dt_comments_add_on_bootstrap() {
	if ( ! function_exists( '\Distributor\ExternalConnectionCPT\setup' ) ) {
		if ( is_admin() ) {
			add_action(
				'admin_notices',
				function() {
					printf( '<div class="%1$s"><p>%2$s</p></div>', esc_attr( 'notice notice-error' ), esc_html( 'You need to have Distributor plug-in activated to run the {Add-on name}.', 'distributor-comments' ) );
				}
			);
		}
		return;
	}
	require_once plugin_dir_path( __FILE__ ) . 'manager.php';
}

add_action( 'plugins_loaded', 'dt_comments_add_on_bootstrap' );

<?php
/**
 * Plugin Name: Integrate CiviCRM with WooCommerce
 * Plugin URI: http://www.vedaconsulting.co.uk
 * Description: Plugin for intergrating WooCommerce with CiviCRM
 * Author: Veda NFP Consulting Ltd
 * Version: 2.3
 * Author URI: http://www.vedaconsulting.co.uk
 * Text Domain: woocommerce-civicrm
 * Domain path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * WooCommerce CiviCRM class
 * A class that encapsulates this plugin's functionality
 *
 * @since 2.0
 */
class WPCV_Woo_Civi {

	/**
	 * Plugin.
	 *
	 * @since 2.0
	 * @access protected
	 * @var $plugin
	 */
	protected $plugin;

	/**
	 * The class instance.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $instance The class instance
	 */
	private static $instance;

	/**
	 * The Settings Tab management object.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $settings_tab The Settings Tab management object
	 */
	public $settings_tab;

	/**
	 * The Orders Contact Tab management object.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $orders_tab The Orders Tab management object
	 */
	public $orders_tab;

	/**
	 * The Manager management object.
	 *
	 * Encapsulates the WooCommerce CiviCRM functionality
	 *
	 * @since 2.0
	 * @access private
	 * @var object $manager The plugin functionality management object
	 */
	public $manager;

	/**
	 * The Sync management object.
	 *
	 * Encapsulates the WooCommerce and CiviCRM synchrinzation objects.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $sync The Sync management object
	 */
	public $sync;

	/**
	 * The Helper management object.
	 *
	 * Encapsulates the Helper management object.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $helper The Helper management object
	 */
	public $helper;

	/**
	 * CiviCRM States/Provinces management object.
	 *
	 * @since 2.0
	 * @access private
	 * @var object $states_replacement The States replacement management object
	 */
	public $states_replacement;

	/**
	 * WooCommerce Product management object.
	 *
	 * @since 2.2
	 * @access public
	 * @var object products The Product management object
	 */
	public $products;

	/**
	 * Plugin activated in network context
	 *
	 * @since 2.2
	 * @access public
	 * @var bool $is_network_installed
	 */
	public $is_network_installed;

	/**
	 * Constructor.
	 *
	 * @since 2.1
	 */
	public function __construct() {
		// Makes sure the plugin is defined before trying to use it.
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . '/wp-admin/includes/plugin.php';
		}
		$plugin_name                = basename( __DIR__ ) . '/' . basename( __FILE__ );
		$this->is_network_installed = is_plugin_active_for_network( $plugin_name );

		add_action( 'admin_init', [ $this, 'check_dependencies' ], 10 );
		$this->define_constants();
		$this->include_files();
		$this->plugin = plugin_basename( __FILE__ );
		// Init plugin.
		add_action( 'plugins_loaded', [ $this, 'init' ], 10 );
		// Clear cache on activation.
		add_action( 'woocommerce_civicrm_activated', [ $this, 'schedule_clear_civi_cache' ] );
	}


	/**
	 * Returns a single instance of this object when called.
	 *
	 * @since 2.0
	 * @return object $instance WooCommerce_CiviCRM instance
	 */
	public static function instance() {

		if ( ! isset( self::$instance ) ) {
			// Instantiate.
			self::$instance = new WPCV_Woo_Civi();

			/**
			 * Broadcast to other plugins that this plugin is loaded.
			 *
			 * @since 2.0
			 */
			do_action( 'woocommerce_civicrm_loaded' );
		}
		// Always return instance.
		return self::$instance;
	}

	/**
	 * Initialize.
	 *
	 * @since 2.1
	 */
	public function init() {

		// Only setup objects after Woo has been bootstraped.
		add_action( 'woocommerce_init', [ $this, 'setup_objects' ] );
		$this->register_hooks();
		$this->enable_translation();

		if ( $this->is_network_installed ) {
			add_action( 'network_admin_menu', [ $this, 'network_admin_menu' ] );
		}
	}

	/**
	 * Adds the setting page manu.
	 *
	 * @since 2.4
	 *
	 * @return void
	 */
	public function network_admin_menu() {
		add_submenu_page(
			'settings.php',
			__( 'WooCommerce CiviCRM settings', 'woocommerce-civicrm' ),
			__( 'WooCommerce CiviCRM settings', 'woocommerce-civicrm' ),
			'manage_network_options',
			'woocommerce-civicrm-settings',
			[ $this->settings_tab, 'network_settings' ]
		);
	}

	/**
	 * Define constants.
	 *
	 * @since 2.0
	 */
	private function define_constants() {
		define( 'WOOCOMMERCE_CIVICRM_VER', '2.1' );
		define( 'WOOCOMMERCE_CIVICRM_URL', plugin_dir_url( __FILE__ ) );
		define( 'WOOCOMMERCE_CIVICRM_PATH', plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Bootstrap CiviCRM.
	 *
	 * @since 2.1
	 */
	public function boot_civi() {
		if ( ! function_exists( 'civi_wp' ) ) {
			return;
		}
		return civi_wp()->initialize();
	}

	/**
	 * Check plugin dependencies.
	 *
	 * @since 2.0
	 * @return bool True if dependencies exist, false otherwise
	 */
	public function check_dependencies() {

		// Bail if WooCommerce is not available.
		if ( ! is_multisite() && ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
			$this->display_woocommerce_required_notice();
		}
		// Bail if CiviCRM is not available.
		if ( ! function_exists( 'civi_wp' ) ) {
			$this->display_civicrm_required_notice();
		}
		// Bail if CiviCRM is not installed.
		if ( ! defined( 'CIVICRM_INSTALLED' ) ) {
			$this->display_civicrm_initialised_notice();
		}

		return true;

	}

	/**
	 * Include plugin files.
	 *
	 * @since 2.0
	 */
	private function include_files() {
		// Include WooCommerce CiviCRM Helper class.
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/class-woo-civi-helper.php';
		// Include WooCommerce settings tab class.
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/class-woo-civi-settings-tab.php';
		// Include CiviCRM orders tab class.
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/class-woo-civi-orders-contact-tab.php';
		// Include WooCommerce functionality class.
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/class-woo-civi-manager.php';
		// Include Address Sync functionality class.
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/class-woo-civi-sync.php';
		// Include States replacement functionality class.
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/class-woo-civi-states.php';
		// Include Products functionality class.
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/class-woo-civi-products.php';
		// Include Orders functionality class.
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/class-woo-civi-orders.php';

		// Include POS functionality class.
		include WOOCOMMERCE_CIVICRM_PATH . 'includes/class-woo-civi-pos.php';

	}

	/**
	 * Set up plugin objects.
	 *
	 * @since 2.0
	 */
	public function setup_objects() {
		// init orders tab.
		$this->orders_tab = new WPCV_Woo_Civi_Orders_Contact_Tab();
		// init helper instance.
		$this->helper = new WPCV_Woo_Civi_Helper();
		// init settings page.
		$this->settings_tab = new WPCV_Woo_Civi_Settings_Tab();
		// init manager.
		$this->manager = new WPCV_Woo_Civi_Manager();
		// init states replacement.
		$this->states_replacement = new WPCV_Woo_Civi_States();
		// init sync manager.
		$this->sync = new WPCV_Woo_Civi_Sync();
		// init products.
		$this->products = new WPCV_Woo_Civi_Products();
		// init orders.
		$this->products = new WPCV_Woo_Civi_Orders();
		// init POS.
		$this->pos = new WPCV_Woo_Civi_POS();

	}

	/**
	 * Register hooks.
	 *
	 * @since 2.0
	 */
	private function register_hooks() {
		// add settings link to plugin listing page.
		add_filter( 'plugin_action_links', [ $this, 'add_action_links' ], 10, 2 );
	}

	/**
	 * Load translation files.
	 *
	 * Reference on how to implement translation in WordPress:
	 * http://ottopress.com/2012/internationalization-youre-probably-doing-it-wrong/
	 *
	 * @since 2.0
	 */
	public function enable_translation() {
		// load translations if present.
		// phpcs:ignore WordPress.WP.DeprecatedParameters.Load_plugin_textdomainParam2Found
		load_plugin_textdomain(
			'woocommerce-civicrm', // unique name.
			'', // deprecated argument.
			dirname( plugin_basename( __FILE__ ) ) . '/languages/' // relative path to translation files.
		);
	}

	/**
	 * Plugin activation.
	 *
	 * @since 2.1
	 */
	public function activate() {
		do_action( 'woocommerce_civicrm_activated' );
	}

	/**
	 * Ensure every plugin is loaded before clearing CiviCRM cache
	 *
	 * @since 2.1.1
	 */
	public function schedule_clear_civi_cache() {
		add_action( 'plugins_loaded', [ $this, 'clear_civi_cache' ], 10 );
	}

	/**
	 * Clear CiviCRM cache after plugin activation.
	 *
	 * @since 2.1
	 */
	public function clear_civi_cache() {
		CRM_Core_Config::singleton()->cleanup( 1, false );
		CRM_Core_Config::clearDBCache();
		CRM_Utils_System::flushCache();
	}

	/**
	 * Add Settings link to plugin listing page.
	 *
	 * @since 2.0
	 * @param array  $links The list of plugin links.
	 * @param string $file The plugin file.
	 * @return $links
	 */
	public function add_action_links( $links, $file ) {
		if ( plugin_basename( __FILE__ ) === $file ) {
			$links[] = '<a href="' . admin_url( 'admin.php?page=wc-settings&tab=woocommerce_civicrm' ) . '">' . __( 'Settings', 'woocommerce-civicrm' ) . '</a>';
		}
		return $links;
	}

	/**
	 * Display WooCommerce required notice.
	 *
	 * @since 2.0
	 */
	public function display_woocommerce_required_notice() {
		deactivate_plugins( $this->plugin );
		wp_die( '<h1>Ooops</h1><p><strong>WooCommerce CiviCRM integration</strong> requires <strong>WooCommerce</strong> plugin installed and activated.<br/> This plugin has been deactivated! Please activate <strong>WooCommerce</strong> and try again.<br/><br/>Back to the WordPress <a href="' . esc_url( get_admin_url( null, 'plugins.php' ) ) . '">plugins page</a>.</p>' );
	}

	/**
	 * Display CiviCRM required notice.
	 *
	 * @since 2.0
	 */
	public function display_civicrm_required_notice() {
		deactivate_plugins( $this->plugin );
		wp_die( '<h1>Ooops</h1><p><strong>WooCommerce CiviCRM Integration</strong> requires <strong>CiviCRM</strong> plugin installed and activated.<br/> This plugin has been deactivated! Please activate <strong>CiviCRM</strong> and try again.<br/><br/>Back to the WordPress <a href="' . esc_url( get_admin_url( null, 'plugins.php' ) ) . '">plugins page</a>.</p>' );
	}

	/**
	 * Display CiviCRM initilised notice.
	 *
	 * @since 2.0
	 */
	public function display_civicrm_initialised_notice() {
		deactivate_plugins( $this->plugin );
		wp_die( '<h1>Ooops</h1><p><strong>CiviCRM</strong> could not be initialized.<br/> <strong>WooCommerce CiviCRM</strong> integration has been deactivated!<br/><br/>Back to the WordPress <a href="' . esc_url( get_admin_url( null, 'plugins.php' ) ) . '">plugins page</a>.</p>' );
	}

}

/**
 * Instantiate plugin.
 *
 * @since 2.1
 * @return object $instance The plugin instance
 */
// phpcs:ignore
function WCI() {
	return WPCV_Woo_Civi::instance();
}

WCI();

register_activation_hook( __FILE__, [ WCI(), 'activate' ] );

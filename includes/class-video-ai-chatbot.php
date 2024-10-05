<?php

/**
 * The file that defines the core plugin class
 *
 * A class definition that includes attributes and functions used across both the
 * public-facing side of the site and the admin area.
 *
 * @link       https://localhost
 * @since      1.0.0
 *
 * @package    Video_Ai_Chatbot
 * @subpackage Video_Ai_Chatbot/includes
 */

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * Also maintains the unique identifier of this plugin as well as the current
 * version of the plugin.
 *
 * @since      1.0.0
 * @package    Video_Ai_Chatbot
 * @subpackage Video_Ai_Chatbot/includes
 * @author     Daniele Napolitano <p.d.napolitano@gmail.com>
 */
class Video_Ai_Chatbot {

	/**
	 * The loader that's responsible for maintaining and registering all hooks that power
	 * the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      Video_Ai_Chatbot_Loader    $loader    Maintains and registers all hooks for the plugin.
	 */
	protected $loader;

	/**
	 * The unique identifier of this plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $plugin_name    The string used to uniquely identify this plugin.
	 */
	protected $plugin_name;

	/**
	 * The current version of the plugin.
	 *
	 * @since    1.0.0
	 * @access   protected
	 * @var      string    $version    The current version of the plugin.
	 */
	protected $version;


	private $openai;
	private $communityopenai;
	private $wa_webhooks;
	private $api;
	private $messagesDB;

	/**
	 * Define the core functionality of the plugin.
	 *
	 * Set the plugin name and the plugin version that can be used throughout the plugin.
	 * Load the dependencies, define the locale, and set the hooks for the admin area and
	 * the public-facing side of the site.
	 *
	 * @since    1.0.0
	 */
	public function __construct() {
		if ( defined( 'VIDEO_AI_CHATBOT_VERSION' ) ) {
			$this->version = VIDEO_AI_CHATBOT_VERSION;
		} else {
			$this->version = '1.0.0';
		}
		$this->plugin_name = 'video-ai-chatbot';

		$this->load_dependencies();
		$this->set_locale();
		$this->define_admin_hooks();
		$this->define_public_hooks();
	}

	/**
	 * Load the required dependencies for this plugin.
	 *
	 * Include the following files that make up the plugin:
	 *
	 * - Video_Ai_Chatbot_Loader. Orchestrates the hooks of the plugin.
	 * - Video_Ai_Chatbot_i18n. Defines internationalization functionality.
	 * - Video_Ai_Chatbot_Admin. Defines all hooks for the admin area.
	 * - Video_Ai_Chatbot_Public. Defines all hooks for the public side of the site.
	 *
	 * Create an instance of the loader which will be used to register the hooks
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function load_dependencies() {

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'vendor/autoload.php';

		/**
		 * The class responsible for orchestrating the actions and filters of the
		 * core plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-video-ai-chatbot-loader.php';

		/**
		 * The class responsible for defining internationalization functionality
		 * of the plugin.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-video-ai-chatbot-i18n.php';

		/**
		 * The class responsible for defining all actions that occur in the admin area.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'admin/class-video-ai-chatbot-admin.php';

		/**
		 * The class responsible for defining all actions that occur in the public-facing
		 * side of the site.
		 */
		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'public/class-video-ai-chatbot-public.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-video-ai-chatbot-openai.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-video-ai-chatbot-openai-community-client.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-video-ai-chatbot-wa-webhooks.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-video-ai-chatbot-tutor-utils.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-video-ai-chatbot-api.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-video-ai-chatbot-instagram.php';

		require_once plugin_dir_path( dirname( __FILE__ ) ) . 'includes/class-video-ai-chatbot-messages-db.php';

		$this->loader = new Video_Ai_Chatbot_Loader();
		$this->communityopenai = new Video_Ai_Community_OpenAi();
		$this->messagesDB = new Video_AI_Chatbot_Messages_DB();

		$this->openai = new Video_Ai_OpenAi($this->communityopenai, $this->messagesDB);
		$this->api = new Video_Ai_Chatbot_Api($this->openai);
	}

	/**
	 * Define the locale for this plugin for internationalization.
	 *
	 * Uses the Video_Ai_Chatbot_i18n class in order to set the domain and to register the hook
	 * with WordPress.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function set_locale() {

		$plugin_i18n = new Video_Ai_Chatbot_i18n();

		$this->loader->add_action( 'plugins_loaded', $plugin_i18n, 'load_plugin_textdomain' );

	}

	/**
	 * Register all of the hooks related to the admin area functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_admin_hooks() {

		$plugin_admin = new Video_Ai_Chatbot_Admin( $this->get_plugin_name(), $this->get_version(), $this->openai );
		$this->loader->add_action( 'admin_init', $plugin_admin, 'openai_assistant_settings_init');
		$this->loader->add_action( 'admin_menu', $plugin_admin, 'openai_assistant_admin_menu' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_styles' );
		$this->loader->add_action( 'admin_enqueue_scripts', $plugin_admin, 'enqueue_scripts' );
		$this->loader->add_action( 'wp_ajax_openai_cancel_thread_options', $plugin_admin, 'delete_thread' );
		$this->loader->add_action( 'wp_ajax_openai_delete_files_data_options', $plugin_admin, 'delete_file_data' );
		$this->loader->add_action('rest_api_init', $this->api, 'register_api_hooks');
		$this->loader->add_action('rest_api_init', $this->communityopenai, 'register_api_hooks');			
		//$this->loader->add_action( 'rest_api_init', $this, 'my_customize_rest_cors', 15 );
	}

	/**
	 * Register all of the hooks related to the public-facing functionality
	 * of the plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 */
	private function define_public_hooks() {

		$plugin_public = new Video_Ai_Chatbot_Public( $this->get_plugin_name(), $this->get_version(), $this->openai );
		$this->loader->add_action( 'plugins_loaded', $plugin_public, 'init_cookies' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_styles' );
		$this->loader->add_action( 'wp_enqueue_scripts', $plugin_public, 'enqueue_scripts' );
		$this->loader->add_shortcode('openai_assistant',  $plugin_public, 'openai_assistant_shortcode');
		
	}

	

	/**
	 * Run the loader to execute all of the hooks with WordPress.
	 *
	 * @since    1.0.0
	 */
	public function run() {
		$this->loader->run();
	}

	/**
	 * The name of the plugin used to uniquely identify it within the context of
	 * WordPress and to define internationalization functionality.
	 *
	 * @since     1.0.0
	 * @return    string    The name of the plugin.
	 */
	public function get_plugin_name() {
		return $this->plugin_name;
	}

	/**
	 * The reference to the class that orchestrates the hooks with the plugin.
	 *
	 * @since     1.0.0
	 * @return    Video_Ai_Chatbot_Loader    Orchestrates the hooks of the plugin.
	 */
	public function get_loader() {
		return $this->loader;
	}

	/**
	 * Retrieve the version number of the plugin.
	 *
	 * @since     1.0.0
	 * @return    string    The version number of the plugin.
	 */
	public function get_version() {
		return $this->version;
	}


	// public function my_customize_rest_cors() {
	// 	remove_filter( 'rest_pre_serve_request', 'rest_send_cors_headers' );
	// 	add_filter( 'rest_pre_serve_request', function( $value ) {
	// 	header( 'Access-Control-Allow-Origin: *' );
	// 	header( 'Access-Control-Allow-Methods: GET,HEAD,OPTIONS,POST,PUT' );
	// 	header( 'Access-Control-Allow-Credentials: true' );
	// 	header( 'Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization' );
	// 	header( 'Access-Control-Expose-Headers: Link', false );
	// 	return $value;
	// 	} );
	// }
}

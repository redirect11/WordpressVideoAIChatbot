<?php

/**
 * The admin-specific functionality of the plugin.
 *
 * @link       https://localhost
 * @since      1.0.0
 *
 * @package    Video_Ai_Chatbot
 * @subpackage Video_Ai_Chatbot/admin
 */

/**
 * The admin-specific functionality of the plugin.
 *
 * Defines the plugin name, version, and two examples hooks for how to
 * enqueue the admin-specific stylesheet and JavaScript.
 *
 * @package    Video_Ai_Chatbot
 * @subpackage Video_Ai_Chatbot/admin
 * @author     Daniele Napolitano <p.d.napolitano@gmail.com>
 */
class Video_Ai_Chatbot_Admin {

	/**
	 * The ID of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $plugin_name    The ID of this plugin.
	 */
	private $plugin_name;

	/**
	 * The version of this plugin.
	 *
	 * @since    1.0.0
	 * @access   private
	 * @var      string    $version    The current version of this plugin.
	 */
	private $version;

	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;

	}

	//defined('ABSPATH') or die('Accesso non consentito');

	/**
	 * Aggiunge il menu principale del plugin con sottomenu per assistenti e video.
	 */
	public function openai_assistant_admin_menu() {
		add_menu_page('OpenAI Assistant', 'OpenAI Assistant', 'manage_options', 'openai-assistant', array($this,'openai_assistant_main_page'), 'dashicons-admin-generic', 20);
		add_submenu_page('openai-assistant', 'Gestione Assistenti', 'Assistenti', 'manage_options', 'openai-assistants', array($this,'openai_assistants_page'));
		add_submenu_page('openai-assistant', 'Gestione Video', 'Video', 'manage_options', 'openai-videos', array($this,'openai_videos_page'));
	}

	/**
	 * Pagina principale del plugin.
	 */
	public function openai_assistant_main_page() {
		echo '<h1>Benvenuto in OpenAI Assistant</h1>';
		echo '<p>Seleziona una delle opzioni nel menu per gestire assistenti o video.</p>';
	}

	/**
	 * Pagina per la gestione degli assistenti.
	 */
	public function openai_assistants_page() {
		?>
		<div class="wrap">
			<h2>Assistenti</h2>
			<button id="create-new-assistant" class="button button-primary">Crea Nuovo Assistente</button>
			<div id="assistants-list">
				<ul>
					<!-- Qui andranno elencati gli assistenti esistenti -->
				</ul>
			</div>
		</div>
		<script type="text/javascript">
			jQuery('#create-new-assistant').on('click', function() {
				// Implementa la logica per creare un nuovo assistente
				alert("Creazione di un nuovo assistente...");
			});
		</script>
		<?php
	}

	/**
	 * Pagina per la gestione dei video e delle trascrizioni.
	 */
	public function openai_videos_page() {
		?>
		<div class="wrap">
			<h2>Video</h2>
			<form id="upload-video-form" method="post" enctype="multipart/form-data">
				<input type="file" name="video_file" id="video_file">
				<input type="submit" class="button button-primary" value="Carica Video">
			</form>
			<div id="videos-list">
				<!-- Qui andranno elencati i video caricati -->
			</div>
		</div>
		<?php
	}

	/**
	 * Enqueue scripts and styles specifici del plugin.
	 */
	public function openai_assistant_enqueue_scripts() {
		wp_enqueue_script('openai-assistant-js', plugins_url('/js/admin.js', __FILE__), array('jquery'), '1.0', true);
		wp_localize_script('openai-assistant-js', 'openaiAjax', array('ajaxurl' => admin_url('admin-ajax.php')));
	}
	

	/**
	 * Registra le azioni AJAX per la gestione dei dati del plugin.
	 */
	public function openai_assistant_settings_init() {
		register_setting('openai_assistant', 'openai_assistant_options');
		add_settings_section(
			'openai_assistant_section',
			__('Impostazioni del Chatbot', 'openai_assistant'),
			'openai_assistant_section_callback',
			'openai_assistant'
		);
		add_settings_field(
			'openai_enable_chatbot',
			__('Abilita Chatbot', 'openai_assistant'),
			'openai_enable_chatbot_render',
			'openai_assistant',
			'openai_assistant_section'
		);

		add_action('wp_ajax_save_new_assistant', 'save_new_assistant');
		add_action('wp_ajax_upload_video', 'upload_video');
	}
	
	public function openai_enable_chatbot_render() {
		$options = get_option('openai_assistant_options');
		?>
		<input type='checkbox' name='openai_assistant_options[openai_enable_chatbot]' <?php checked($options['openai_enable_chatbot'], 1); ?> value='1'>
		<?php
	}
	
	public function openai_assistant_section_callback() {
		echo __('Attiva o disattiva il Chatbot sul tuo sito.', 'openai_assistant');
	}
	

	/**
	 * Funzioni AJAX per salvare nuovi assistenti e caricare video.
	 */
	public function save_new_assistant() {
		// Logica per salvare un nuovo assistente nel database
	}

	public function upload_video() {
		// Logica per caricare e processare il video
	}

	/**
	 * Register the stylesheets for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_styles() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Video_Ai_Chatbot_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Video_Ai_Chatbot_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_style( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'css/video-ai-chatbot-admin.css', array(), $this->version, 'all' );

	}

	/**
	 * Register the JavaScript for the admin area.
	 *
	 * @since    1.0.0
	 */
	public function enqueue_scripts() {

		/**
		 * This function is provided for demonstration purposes only.
		 *
		 * An instance of this class should be passed to the run() function
		 * defined in Video_Ai_Chatbot_Loader as all of the hooks are defined
		 * in that particular class.
		 *
		 * The Video_Ai_Chatbot_Loader will then create the relationship
		 * between the defined hooks and the functions defined in this
		 * class.
		 */

		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/video-ai-chatbot-admin.js', array( 'jquery' ), $this->version, false );

	}

}

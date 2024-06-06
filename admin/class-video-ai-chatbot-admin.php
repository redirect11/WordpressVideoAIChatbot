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

	private $openai;

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
	public function __construct( $plugin_name, $version, $openai ) {

		$this->plugin_name = $plugin_name;
		$this->version = $version;
		$this->openai = $openai;
	}

	//defined('ABSPATH') or die('Accesso non consentito');

	/**
	 * Aggiunge il menu principale del plugin con sottomenu per assistenti e video.
	 */
	public function openai_assistant_admin_menu() {
		add_menu_page('Chatbot Settings', 'Chatbot Settings', 'manage_options', 'chatbot-settings', array($this,'openai_assistant_main_page'), 'dashicons-admin-generic', 20);
		add_submenu_page('chatbot-settings', 'Gestione Assistenti', 'Assistenti', 'manage_options', 'openai-assistants', array($this,'openai_assistants_page'));
		add_submenu_page('chatbot-settings', 'Gestione Trascrizioni', 'Trascrizioni', 'manage_options', 'openai-transcriptions', array($this,'openai_transcriptions_page'));
	}

	/**
	 * Pagina principale del plugin.
	 */
	public function openai_assistant_main_page() {
		include_once 'partials/video-ai-chatbot-admin-display.php';
	}

	/**
	 * Pagina per la gestione degli assistenti.
	 */
	public function openai_assistants_page() {
		include_once 'partials/video-ai-chatbot-admin-assistants.php';
	}

	/**
	 * Pagina per la gestione dei video e delle trascrizioni.
	 */
	public function openai_transcriptions_page() {
		include_once 'partials/video-ai-chatbot-admin-transcriptions.php';
	}

	/**
	 * Registra le azioni AJAX per la gestione dei dati del plugin.
	 */
	public function openai_assistant_settings_init() {
		register_setting('video_ai_chatbot_group', 'video_ai_chatbot_options');
		register_setting('video_ai_chatbot_group', 'openai_cancel_thread_options');

		$default = array(
			array(
				'id'    => '1',
				'videoTitle' => 'Titolo del Video',
				'videoText' => 'Testo del Video',
				'videoHrefLink' => 'Link del Video',
				'transcription' => 'Trascrizione del Video',
			),
		);

		$schema  = array(
					'type'  => 'array',
					'items' => array(
						'id'    => 'string',
						'videoTitle' => 'string',
						'videoText' => 'string',
						'videoHrefLink' => 'string',
						'transcription' => 'string',
					),
				);

	
		register_setting(
			'video_ai_chatbot_group',
			'video_ai_chatbot_transcriptions',
			array(
				'type'         => 'array',
				'default'      => $default,
				'show_in_rest' => array(
					'schema' => $schema,
				),
			)
		);

		add_settings_section(
			'video_ai_chatbot_section',
			__('Impostazioni del Chatbot', 'video-ai-chatbot'),
			array($this,'video_ai_chatbot_section_callback'),
			'chatbot-settings'
		);
		add_settings_field(
			'video_ai_enable_chatbot_field',
			__('Abilita Chatbot', 'openai-assistant'),
			array($this, 'video_ai_enable_chatbot_render'),
			'chatbot-settings',
			'video_ai_chatbot_section'
		);
		add_settings_field(
			'video_ai_chatbot_welcome_message_field',
			__('Messaggio di Benvenuto del Chatbot', 'openai-assistant'),
			array($this, 'openai_welcome_message_render'),
			'chatbot-settings',
			'video_ai_chatbot_section'
		);

		add_settings_section(
			'openai_api_settings_section',
			'API Settings',
			array($this, 'openai_settings_section_text'),
			'chatbot-settings'
		);
	
		add_settings_field(
			'openai_api_key_field',
			'OpenAI API Key',
			array($this, 'openai_api_key_field_render'),
			'chatbot-settings',
			'openai_api_settings_section'
		);

		add_settings_section(
			'openai_cancel_thread_section',
			__('Cancella Thread Corrente', 'video-ai-chatbot'),
			array($this, 'openai_cancel_thread_section_callback'),
			'chatbot-settings'
		);

	}

	
	public function openai_cancel_thread_section_callback() {
		echo '<p>Clicca "Cancella Thread" per rimuovere il thread corrente associato al tuo account.</p>';
		?>
		<div class="wrap">
			<h2>Assistenti</h2>
			<button id="delete-thread-button" class="button button-primary" type="button">Cancella Thread Corrente</button>
			<div id="delete-thread-button-result"></div>
		</div>
		<?php
	}
	


	public function openai_settings_section_text() {
		echo '<p>Enter your OpenAI API key below.</p>';
	}

		
	public function video_ai_chatbot_section_callback() {
		echo __('Attiva o disattiva il Chatbot sul tuo sito.', 'openai_assistant');
	}
	
	public function openai_api_key_field_render() {
		$options = get_option('video_ai_chatbot_options');
		?>
		<input type='text' name='video_ai_chatbot_options[openai_api_key_field]' value='<?php echo $options['openai_api_key_field']; ?>'>
		<?php
	}

	
	public function video_ai_enable_chatbot_render() {
		$options = get_option('video_ai_chatbot_options');
		?>
		<input type='checkbox' name='video_ai_chatbot_options[video_ai_enable_chatbot_field]' <?php checked($options['video_ai_enable_chatbot_field'], 1); ?> value='1'>
		<?php
	}

	public function openai_welcome_message_render() {
		$options = get_option('video_ai_chatbot_options');
		?>
				<h1>  <?php echo $options; ?> </h1>
		<textarea name='video_ai_chatbot_options[video_ai_chatbot_welcome_message_field]' rows='5' cols='50'><?php echo esc_textarea($options['video_ai_chatbot_welcome_message_field'] ?? ''); ?></textarea>
		<?php
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
		wp_enqueue_style( 'wp-components' );

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
		wp_localize_script(
			$this->plugin_name,
			'my_ajax_obj',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( $this->plugin_name ),
			)
		);
		$api_key = get_option('video_ai_chatbot_options')['video_ai_chatbot_welcome_message_field'];
		if (!$api_key) {
			echo '<p>Please configure your OpenAI API key in the settings.</p>';
			return;
		}

		wp_enqueue_script('openai-assistant-admin-react', plugins_url('../assets/js/assistantsPage.bundle.js', __FILE__), array(), '1.0', true);
		wp_enqueue_script('openai-create-assistant-admin-react', plugins_url('../assets/js/transcriptionsPage.bundle.js', __FILE__), array(), '1.0', true);

		if(!$this->openai->is_active()) {
			echo '<p>OpenAI client not initialized</p>';
			$this->openai->activate($api_key);
		}
		wp_localize_script('openai-assistant-js', 'openaiAjax', array('ajaxurl' => admin_url('admin-ajax.php')));
		$assistants = $this->openai->get_assistants();
		$transcriptions = $this->get_local_transcriptions();
		wp_localize_script('openai-assistant-admin-react', 'adminData', array('assistants' => $assistants, 'transcriptions' => $transcriptions));
		
	}

	private function get_local_transcriptions() {
		$transcriptions = get_option('video_ai_chatbot_transcriptions', []);
		return $transcriptions;
	}

	/**
	 * Handles my AJAX request.
	 */
	function delete_thread() {
		//check_ajax_referer( $this->plugin_name );
		$this->openai->handle_thread_deletion();
	}

}

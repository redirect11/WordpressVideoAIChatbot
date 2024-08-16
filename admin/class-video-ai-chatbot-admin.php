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

	private $wa;
	/**
	 * Initialize the class and set its properties.
	 *
	 * @since    1.0.0
	 * @param      string    $plugin_name       The name of this plugin.
	 * @param      string    $version    The version of this plugin.
	 */
	public function __construct( $plugin_name, $version, $openai) {

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
	}

	/**
	 * Pagina principale del plugin.
	 */
	public function openai_assistant_main_page() {
		include_once 'partials/video-ai-chatbot-admin-display.php';
	}

	public function before_section() {
		return '<div class="chatbot-settings-section">
					<div class="components-panel">
						<div class="components-panel__body is-opened">';
	}

	public function after_section() {
		return '		</div>
					</div>
					<br/>
				</div>';
	}

	public function section_wrapper() {
		$section_class = [ 
			'before_section' => $this->before_section(),
			'after_section' => $this->after_section(), 
			'section_class' => 'chatbot-settings-section' 
		];
		return $section_class;
	}
	/**
	 * Registra le azioni AJAX per la gestione dei dati del plugin.
	 */
	public function openai_assistant_settings_init() {
		register_setting('video_ai_chatbot_group', 'video_ai_chatbot_options');
		register_setting('video_ai_chatbot_group', 'openai_cancel_thread_options');
		register_setting('video_ai_chatbot_group', 'openai_delete_files_data_options');

		
		$default = array(
			array(
				'id'    => '1',
				'videoTitle' => 'Titolo del Video',
				'videoText' => 'Testo del Video',
				'videoHrefLink' => 'Link del Video',
				'transcription' => 'Trascrizione del Video',
			),
		);

		$transcriptionSchema  = array(
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
			'video_ai_chatbot_transcriptions_group',
			'video_ai_chatbot_transcriptions',
			array(
				'type'         => 'array',
				'default'      => $default,
				'show_in_rest' => array(
					'schema' => $transcriptionSchema,
				),
			)
		);

		$fileSchema  = array(
			'type'  => 'array',
			'items' => array(
				'id'           => 'string',	
				'file_name'    => 'string',
				'file_content' => 'string',
			),
		);


		register_setting(
			'video_ai_chatbot_files_group',
			'video_ai_chatbot_files',
			array(
				'type'         => 'array',
				'default'      => [],
				'show_in_rest' => array(
					'schema' => $fileSchema,
				),
			)
		);

		$waAssistantSchema  = array(
			'type'  => 'array',
			'items' => array(
				'assistant' 		=> 'string',	
				'token'     		=> 'string',
				'outgoingNumberId'  => 'string',
			),
		);


		register_setting(
			'video_ai_whatsapp_assistants_group',
			'video_ai_whatsapp_assistants',
			array(
				'type'         => 'array',
				'default'      => [],
				'show_in_rest' => array(
					'schema' => $waAssistantSchema,
				),
			)
		);


		add_settings_section(
			'video_ai_chatbot_section',
			__('Impostazioni del Chatbot', 'video-ai-chatbot'),
			array($this,'video_ai_chatbot_section_callback'),
			'chatbot-settings',
			$this->section_wrapper('Impostazioni del Chatbot')
		);

		// Aggiungi il campo nome del chatbot	
		add_settings_field(
			'video_ai_chatbot_name_field',
			__('Nome del Chatbot', 'video-ai-chatbot'),
			array($this,'video_ai_chatbot_name_render'),
			'chatbot-settings',
			'video_ai_chatbot_section'
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
		add_settings_field(
			'video_ai_chatbot_theme',
			__('Seleziona Tema', 'video-ai-chatbot'),
			array($this, 'video_ai_chatbot_theme_render'),
			'chatbot-settings',
			'video_ai_chatbot_section'
		);

		add_settings_field(
			'openai_api_key_field',
			'',
			array($this, 'openai_api_key_field_render'),
			'chatbot-settings',
			'video_ai_chatbot_section'
		);
		
		add_settings_section(
			'openai_cancel_thread_section',
			__('Cancella dati plugin', 'video-ai-chatbot'),
			array($this, 'openai_delete_data_section_callback'),
			'chatbot-settings',
			$this->section_wrapper()
		);



		

	}

	// public function video_ai_dummy_calback() {
	// 	echo '<p>Click "Cancel Thread" to remove the current thread associated with your account.</p>';
	// }
	public function openai_delete_data_section_callback() {
		echo '<h4>ATTENZIONE, QUESTE OPERAZIONI COMPORTANO LA PERDITA DEI DATI DEI FILE E DELLE TRASCRIZIONI SALVATE.</h4>';
		?>
			<h3>Cancella il thread</h3>
			<p>Cancella il thread corrente associato al tuo account.</p>
			<button id="delete-thread-button" class="button button-primary" type="button">Cancella Thread Corrente</button>
			<div id="delete-thread-button-result"></div>
			<br />
			<h3>Cancella i file e le trascrizioni</h3>
			<p>Cancella i file degli assistenti preventivi e le trascrizioni. Non cancella gli assistenti. </p>
			<p>ATTENZIONE: Questa operazione non può essere annullata.</p>
			<button id="delete-filedata-button" class="button button-primary" type="button">Reset File e Trascrizioni</button>
			<div id="delete-filedata-button-result"></div>
		<?php
	}



	//Implementa la callback per il campo nome del chatbot
	public function video_ai_chatbot_name_render() {
		$options = get_option('video_ai_chatbot_options');
		$name = '';
		if(isset($options['video_ai_chatbot_name_field'])) {
			$name = $options['video_ai_chatbot_name_field'];
		}
		?>
		<input type='text' name='video_ai_chatbot_options[video_ai_chatbot_name_field]' value='<?php echo $name; ?>'>
		<?php
	}

		
	public function video_ai_chatbot_section_callback() {
		echo __('Attiva o disattiva il Chatbot sul tuo sito.', 'openai_assistant');
	}
	
	public function openai_api_key_field_render() {
		$options = get_option('video_ai_chatbot_options');
		$key = '';
		if(isset($options['openai_api_key_field'])) {
			$key = $options['openai_api_key_field'];
		}
		?>
		<input type='hidden' name='video_ai_chatbot_options[openai_api_key_field]' value='<?php echo $key; ?>'>
		<?php
	}

		
	public function video_ai_enable_chatbot_render() {
		$options = get_option('video_ai_chatbot_options');
		$enabled = isset($options['video_ai_enable_chatbot_field']) ? $options['video_ai_enable_chatbot_field'] : 0; 
		?>
		<input type='checkbox' name='video_ai_chatbot_options[video_ai_enable_chatbot_field]' <?php checked($enabled, 1); ?> value='1'>
		<?php
	}

	public function openai_welcome_message_render() {
		$options = get_option('video_ai_chatbot_options');
		?>
		<textarea name='video_ai_chatbot_options[video_ai_chatbot_welcome_message_field]' rows='5' cols='50'><?php echo esc_textarea($options['video_ai_chatbot_welcome_message_field'] ?? ''); ?></textarea>
		<?php
	}

	public function video_ai_chatbot_theme_render() {
		?>
		<select name='video_ai_chatbot_options[video_ai_chatbot_theme]'>
		<?php
		$options = get_option('video_ai_chatbot_options');
		$dirPath = plugin_dir_path(__FILE__) . '../public/css/themes';
		$files = scandir($dirPath);

		if(isset($options['video_ai_chatbot_theme'])) {
			$selected = $options['video_ai_chatbot_theme'];
		} else {
			$selected = '';
		}

		foreach ($files as $file) {
			$filePath = $dirPath . '/' . $file;
			if (is_file($filePath)) {
				$filename = pathinfo($filePath, PATHINFO_FILENAME);
				// render dropdown option
				?>
				<option value='<?php echo $filename; ?>' <?php selected($selected, $filename); ?>><?php echo $filename; ?></option>
				<?php
				//echo $file . "<br>";
			}
		}
		?>
		</select>
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
		$nonce = wp_create_nonce( 'wp_rest' );
		wp_enqueue_script( $this->plugin_name, plugin_dir_url( __FILE__ ) . 'js/video-ai-chatbot-admin.js', array( 'jquery' ), $this->version, false );
		wp_localize_script(
			$this->plugin_name,
			'my_ajax_obj',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => $nonce,
			)
		);
		$options = get_option('video_ai_chatbot_options');
		$api_key = isset($options['openai_api_key_field']) ? $options['openai_api_key_field'] :'';
		$wa_apy_key = isset($options['openai_whatsapp_token_field']) ? $options['openai_whatsapp_token_field'] :'';
		$wa_phone_id = isset($options['openai_whatsapp_outcoming_number_id_field']) ? $options['openai_whatsapp_outcoming_number_id_field'] :'';
		$wa_assistant_id = isset($options['openai_whatsapp_associate_assistant_field']) ? $options['openai_whatsapp_associate_assistant_field'] :'';
		// if (!$api_key) {
		// 	echo '<p>Please configure your OpenAI API key in the settings.</p>';
		// 	return;
		// }

		// if(!$wa_apy_key || !$wa_phone_id || !$wa_assistant_id) {
		// 	echo '<p>Please configure your WhatsApp settings in the settings.</p>';
		// 	return;
		// }

		// wp_enqueue_script('openai-assistant-admin-react', plugins_url('../assets/js/assistantsPage.bundle.js', __FILE__), array(), '1.0', true);
		// wp_enqueue_script('openai-create-assistant-admin-react', plugins_url('../assets/js/transcriptionsPage.bundle.js', __FILE__), array(), '1.0', true);
		wp_enqueue_script('openai-assistant-settings-react', plugins_url('../assets/js/settingsPage.bundle.js', __FILE__), array(), '1.0', true);
		
		if(!$this->openai->is_active()) {
			$this->openai->activate($api_key);
		}

		// if(!$this->wa->is_active()) {
		// 	$this->wa->activate($wa_apy_key, $wa_phone_id, $wa_assistant_id);
		// }
		$assistants = $this->openai->get_assistants();
		$transcriptions = $this->get_local_transcriptions();
		wp_localize_script('openai-assistant-admin-react', 'adminData', array('assistants' => $assistants, 'transcriptions' => $transcriptions, 'nonce' => $nonce));
		wp_localize_script('openai-create-assistant-admin-react', 'adminData', array('assistants' => $assistants, 'transcriptions' => $transcriptions, 'nonce' => $nonce));
		wp_localize_script('openai-assistant-settings-react', 'adminData', array('assistants' => $assistants, 'transcriptions' => $transcriptions, 'nonce' => $nonce));
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
		return $this->openai->handle_thread_deletion();
	}

	function delete_file_data() {
		error_log("delete_file_data");
		return $this->openai->delete_local_files_data();
	}

}

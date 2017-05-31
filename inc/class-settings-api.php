<?php
/*
Добавляем страницу настроек WordPress
*/

class HAWP_Settings {

	function __construct() {
		add_action('admin_menu', function(){
			add_options_page(
				$page_title = 'HybridAuth - авторизация в социальных сетях',
				$menu_title = 'HybridAuth',
				$capability = 'manage_options',
				$menu_slug = 'hybridauth',
				$function = array($this, 'settings_ui')
			);
		});


		add_action( 'admin_init', array($this, 'settings_init') );

	}


	/**
	 * Init settings
	 */
	public function settings_init() {
		add_settings_section(
			$name = 'hawp_settings_section_main',
			$title = 'Основные настройки',
			$callback = array($this, 'display_settings_section_main'),
			$page = 'hybridauth'
		);

		register_setting(
			$option_group = 'hybridauth',
			$option_name = 'domains_white_list_enabled'
		);

		add_settings_field(
			$id = 'domains_white_list_enabled',
			$title = 'Включить белый список доменов',
			$callback = [$this, 'display_domains_white_list_enabled'],
			$page = 'hybridauth',
			$section = 'hawp_settings_section_main'
		);


		register_setting(
			$option_group = 'hybridauth',
			$option_name = 'domains_white_list'
		);

		add_settings_field(
			$id = 'domains_white_list',
			$title = 'Белый список доменов',
			$callback = [$this, 'display_domains_white_list'],
			$page = 'hybridauth',
			$section = 'hawp_settings_section_main'
		);



	}

	/**
	 * Display field: Domains white list
	 */
	public function display_domains_white_list() {
		printf('<input type="text" id="domains_white_list" name="domains_white_list" value="%s" size="111"  />', get_option('domains_white_list') );
		?>
			<p><small>Укажите список разрешенных доменов через запятую</small></p>
		<?php
	}

	/**
	 * Display field: Domains white list enabled
	 */
	public function display_domains_white_list_enabled() {

		printf('<input type="checkbox" id="domains_white_list_enabled" name="domains_white_list_enabled" value="1" %s />', checked('1', get_option('domains_white_list_enabled'), false) );
	}

	/**
	 * Display main settings
	 */
	public function display_settings_section_main() {
		echo '<hr>';
	}

 /**
	* UI for Settings
	*/
	function settings_ui(){
	?>
	    <div class="wrap">
	        <h1>Настройки HybridAuth</h1>
	        <form action="options.php" method="POST">
	            <?php settings_fields( 'hybridauth' ); ?>
	            <?php do_settings_sections( 'hybridauth' ); ?>
	            <?php submit_button(); ?>
	        </form>
	    </div>
	<?php
	}


}
new HAWP_Settings;

<?php


/*
Добавляем страницу настроек WordPress
*/
add_action('admin_menu', 'cp_hybridauth_settings_page_add');
function cp_hybridauth_settings_page_add(){
add_options_page(
	$page_title = 'HybridAuth - авторизация в социальных сетях', 
	$menu_title='HybridAuth by CP',
	$capability='manage_options', 
	$menu_slug='cp_hybridauth_settings_page', 
	$function='cp_hybridauth_settings_page_function');
}

function cp_hybridauth_settings_page_function(){
?>
    <div class="wrap">
        <h1>Настройки</h1>
        <form action="options.php" method="POST">
            <?php settings_fields( 'cp_hybridauth_settings_page' ); ?>
            <?php do_settings_sections( 'cp_hybridauth_settings_page' ); ?>
            <?php submit_button(); ?>
        </form>
    </div>
<?php
}

/*
Регистрируем опции, секции и поля
*/
add_action('admin_init', 'cp_hybridauth_init_options');
function cp_hybridauth_init_options(){
	
    register_setting( 'cp_hybridauth_settings_page', 'cp_hybridauth_config_data' );
    register_setting( 'cp_hybridauth_settings_page', 'cp_hybridauth_template_for_comments' );
    register_setting( 'cp_hybridauth_settings_page', 'cp_hybridauth_template_for_login' );
    register_setting( 'cp_hybridauth_settings_page', 'cp_hybridauth_template_for_user_profile' );

	/*
	Добавляем секцию на страницу настроек
	*/
	add_settings_section( 
		$id = 'cp_hybridauth_settings_sections', 
		$title = '', 
		$callback = 'cp_settings_pages_section_callback', 
		$page = 'cp_hybridauth_settings_page'
	);

	/*
	Добавляем поля к секции настроек
	*/
	add_settings_field(
		$id = 'cp_hybridauth_config_data', 
		$title = 'Данные социальных сетей', 
		$callback = 'cp_hybridauth_options_field_config_data_callback', 
		$page = "cp_hybridauth_settings_page", 
		$section = "cp_hybridauth_settings_sections" 
		);

}

function cp_settings_pages_section_callback(){
?>
<p>Данные ключей и приложений следует брать на соответствующей странице сети</p>
<p>Для добавления кнопки авторизации через социальную сеть, следует использовать шорткод вида [btn-hybridauth provider_id="Twitter" text="Twitter"]</p>
<p>Для добавления кнопки авторизации, с возможностью подключения и отключения социальной сети, следует использовать шорткод вида [btn-hybridauth connect=true provider_id="Twitter" text="Twitter"]</p>
<p>Пример использования шорткодов в комплексе:<br/>
Кнопки без подключения, только для авторизации. Если пользователь уже авторизован, то эти кнопки не видны.<br/>
[btn-hybridauth provider_id="Twitter" text="Twitter"]<br/>
[btn-hybridauth provider_id="Facebook text="Фейсбук"]<br/>
[btn-hybridauth provider_id="Vkontakte" text="ВКонтакте"]<br/>
[btn-hybridauth provider_id="Odnoklassniki" text="Одноклассники"]<br/>
<br/>
Кнопки с подключением и отключения соц сетей<br/>
[btn-hybridauth connect=true provider_id="Twitter" text="Twitter"]<br/>
[btn-hybridauth connect=true provider_id="Facebook text="Фейсбук"]<br/>
[btn-hybridauth connect=true provider_id="Vkontakte" text="ВКонтакте"]<br/>
[btn-hybridauth connect=true provider_id="Odnoklassniki" text="Одноклассники"]<br/>
</p>
<?php
}


function cp_hybridauth_options_field_config_data_callback(){
	$setting_name = 'cp_hybridauth_config_data';
	$setting_value = get_option( $setting_name );

	//Если опция не заполнена, то делаем пустой массив
	if(! is_array($setting_value)) $setting_value = array();
	?>
	<div id="<?php echo $setting_name; ?>">
		<input type="hidden" name="<?php echo $setting_name . '[base_url]'?>" value="<?php echo CP_HYBRIDAUTH_PLUGIN_DIR_URL . "hybridauth/"; ?>">
		<fieldset id="facebook">
			<legend><h1>Facebook</h1></legend>
			<div class="enabled">
				<div><small>Укажите, чтобы использование данного провайдера</small></div>
				<div>
					<?php 
					$checked = $setting_value['providers']['Facebook']['enabled'];
					?>
					<input id="facebook-enabled" type="checkbox" name="<?php echo $setting_name . '[providers][Facebook][enabled]'; ?>" value=true <?php checked( $checked, "true", true ); ?> />
					<label for="facebook-enabled">Включить</label>
				</div>
			</div>
			<div class="key_id">
				<div>
					<label for="facebook_key_id">ID App</label>
				</div>
				<div>
					<input id="facebook_key_id" type="text" size="55" name="<?php echo $setting_name . '[providers][Facebook][keys][id]'; ?>" value="<?php echo $setting_value['providers']['Facebook']['keys']['id']; ?>" />
				</div>
			</div>
			<div class="key_secret">
				<div>
					<label for="facebook_key_secret">Secret App</label>
				</div>
				<div>
					<input id="facebook_key_secret" type="text" size="55" name="<?php echo $setting_name . '[providers][Facebook][keys][secret]'; ?>" value="<?php echo $setting_value['providers']['Facebook']['keys']['secret']; ?>" />
				</div>
			</div>
			<input type="hidden" name="<?php echo $setting_name . '[providers][Facebook][trustForwarded]'; ?>" value=false>
		</fieldset>
		<fieldset id="twitter">
			<legend><h1>Twitter</h1></legend>
			<div class="enabled">
				<div><small>Укажите, чтобы использование данного провайдера</small></div>
				<div>
					<?php 
					$checked = $setting_value['providers']['Twitter']['enabled'];
					?>
					<input id="twitter-enabled" type="checkbox" name="<?php echo $setting_name . '[providers][Twitter][enabled]'; ?>" value=true <?php checked( $checked, "true", true ); ?> />
					<label for="twitter-enabled">Включить</label>
				</div>
			</div>
			<div class="key_id">
				<div>
					<label for="twitter-key_id">Key (ID) App</label>
				</div>
				<div>
					<input id="twitter-key_id" type="text" size="55" name="<?php echo $setting_name . '[providers][Twitter][keys][key]'; ?>" value="<?php echo $setting_value['providers']['Twitter']['keys']['key']; ?>" />
				</div>
			</div>
			<div class="key_secret">
				<div>
					<label for="twitter-key_secret">Secret App</label>
				</div>
				<div>
					<input id="twitter-key_secret" type="text" size="55" name="<?php echo $setting_name . '[providers][Twitter][keys][secret]'; ?>" value="<?php echo $setting_value['providers']['Twitter']['keys']['secret']; ?>" />
				</div>
			</div>	
		</fieldset>
		<fieldset id="vkontakte">
			<legend><h1>Vkontakte</h1></legend>
			<div class="enabled">
				<div><small>Укажите, чтобы использование данного провайдера</small></div>
				<div>
					<?php 
					$checked = $setting_value['providers']['Vkontakte']['enabled'];
					?>
					<input id="vkontakte-enabled" type="checkbox" name="<?php echo $setting_name . '[providers][Vkontakte][enabled]'; ?>" value=true <?php checked( $checked, "true", true ); ?> />
					<label for="vkontakte-enabled">Включить</label>
				</div>
			</div>
			<div class="key_id">
				<div>
					<label for="vkontakte-key_id">ID App</label>
				</div>
				<div>
					<input id="vkontakte-key_id" type="text" size="55" name="<?php echo $setting_name . '[providers][Vkontakte][keys][id]'; ?>" value="<?php echo $setting_value['providers']['Vkontakte']['keys']['id']; ?>" />
				</div>
			</div>
			<div class="key_secret">
				<div>
					<label for="vkontakte-key_secret">Secret App</label>
				</div>
				<div>
					<input id="vkontakte-key_secret" type="text" size="55" name="<?php echo $setting_name . '[providers][Vkontakte][keys][secret]'; ?>" value="<?php echo $setting_value['providers']['Vkontakte']['keys']['secret']; ?>" />
				</div>
			</div>
			<div class="scope">
				<div>
					<label for="vkontakte-scope">Scope</label>
				</div>
				<div>
					<input id="vkontakte-scope" type="text" size="55" name="<?php echo $setting_name . '[providers][Vkontakte][scope]'; ?>" value="<?php echo $setting_value['providers']['Vkontakte']['scope']; ?>" />
				</div>
			</div>		
		</fieldset>
		<fieldset id="odnoklassniki">
			<legend><h1>Odnoklassniki</h1></legend>
			<div class="enabled">
				<div><small>Укажите, чтобы использование данного провайдера</small></div>
				<div>
					<?php 
					$checked = $setting_value['providers']['Odnoklassniki']['enabled'];
					?>
					<input id="odnoklassniki-enabled" type="checkbox" name="<?php echo $setting_name . '[providers][Odnoklassniki][enabled]'; ?>" value=true <?php checked( $checked, "true", true ); ?> />
					<label for="odnoklassniki-enabled">Включить</label>
				</div>
			</div>
			<div class="key_id">
				<div>
					<label for="odnoklassniki-key_id">ID App</label>
				</div>
				<div>
					<input id="odnoklassniki-key_id" type="text" size="55" name="<?php echo $setting_name . '[providers][Odnoklassniki][keys][key]'; ?>" value="<?php echo $setting_value['providers']['Odnoklassniki']['keys']['key']; ?>" />
				</div>
			</div>
			<div class="key_secret">
				<div>
					<label for="odnoklassniki-key_secret">Secret App</label>
				</div>
				<div>
					<input id="odnoklassniki-key_secret" type="text" size="55" name="<?php echo $setting_name . '[providers][Odnoklassniki][keys][secret]'; ?>" value="<?php echo $setting_value['providers']['Odnoklassniki']['keys']['secret']; ?>" />
				</div>
			</div>
			<div class="key_public">
				<div>
					<label for="odnoklassniki-key_public">Public Key App</label>
				</div>
				<div>
					<input id="odnoklassniki-key_public" type="text" size="55" name="<?php echo $setting_name . '[providers][Odnoklassniki][keys][key_public]'; ?>" value="<?php echo $setting_value['providers']['Odnoklassniki']['keys']['key_public']; ?>" />
				</div>
			</div>		
		</fieldset>
	</div>
	<?php
}
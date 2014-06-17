<?php
/*
Plugin Name: HybridAuth by CasePress
Description: Позволяет авторизовываться в соц сетях и через различные сторонние сайты посредством протокола OAuth2, OAuth1 & OpenID
Version: 0.1
Author: CasePress
Author URI: http://casepress.org
License: MIT License
Text Domain: hybridauth-wp-cp
Domain Path: languages
*/

//Определяем константу и помещаем в нее путь до папки с плагином. Чтобы затем использовать ее.
define ("CP_HYBRIDAUTH_PLUGIN_DIR_URL", plugin_dir_url(__FILE__));

//Connect config for hybridauth
//require_once('config-hybridauth.php');
//Connect settings page
require_once('includes/settings-api.php');

//Добавляем шорткод для вывода кнопки
add_shortcode('btn-hybridauth', 'cp_btn_hybridauth');
function cp_btn_hybridauth($atts, $content=""){
	extract(shortcode_atts( array(
		'provider_id' => 'Facebook',
		'img' => 'default baz',
		'connect' => false,
		'text' => 'Facebook'
		), $atts, 'btn-hybridauth' ));

	//если пользователи авторизован, то вернуть пустоту
	if(is_user_logged_in() && $connect == false) return;


	//Если у шорткода есть контент, то текст ссылки заполняется контентом.
	if(! empty($content)) $text = $content;

	//Проверяем наличие профиля у текущего пользователя и меняем слегка URL
	$profile = get_user_meta(get_current_user_id(), $meta_key = 'cp_hybridauth_' . strtolower( $provider_id ) . '_identifier', true);
	if(empty($profile)) {
	$url = add_query_arg(array('cp-aa' => $provider_id));
	$class_html = strtolower( $provider_id );
	} else {
	$class_html = strtolower( $provider_id ) . ' cp_delete';
	$url = add_query_arg(array('cp-aa-delete' => $provider_id));
	}

	//Выводим HTML код кнопки
	ob_start();
	?>
	<div class="cp-btn-hybridauth <?php echo apply_filters('cp_hybridauth_btn_class', $class_html) ?>">
		<a href="<?php echo $url ?>"><?php echo $text ?></a>
	</div>
	<script>
		(function($) {
			  $('div.<?php echo strtolower( $provider_id ); ?> a').click(function(){
				event.preventDefault();
				url = "<?php echo $url ?>"
				ha_popup=window.open(url,'name','height=300,width=400');
				if (window.focus) {ha_popup.focus()}
			  });
		})(jQuery);
	</script>
	<?php
	$data = ob_get_contents();
	ob_end_clean();
	return $data;
}

/*
Функция удаления профиля соц сети. Срабатывает на основе URL с параметром. Например: site.ru/?cp-aa-delete=Facebook
*/
add_action('template_redirect', 'cp_ha_delete_profile');
function cp_ha_delete_profile() {
	if(! isset($_REQUEST['cp-aa-delete'])) return;

	$provider_id = (string) $_REQUEST['cp-aa-delete'];

	//Проверяем есть ли удаляемый профайл. Если нет, то возвращаем URL уведомления отсутствия профиля. Иначе удаляем профиль.
	$profile = get_user_meta(get_current_user_id(), $meta_key = 'cp_hybridauth_' . strtolower( $provider_id ) . '_identifier', true);
	if(empty($profile)) {
		wp_redirect(add_query_arg(array('result' => 'not_found_proifile')));
		exit;
	} else {
		delete_user_meta(
			get_current_user_id(), 
			$meta_key = 'cp_hybridauth_' . strtolower( $provider_id ) . '_identifier'
		);
		global $wp;
		wp_redirect(add_query_arg(array('cp_result_delete_profile' => 'success'), home_url( $wp->request )));
		exit;
	}

}


/*
На хуке редирект шаблона ловим ключ авторизации и если он есть, то выполняем авторизацию в соц сетях
*/
add_action('template_redirect', 'start_session_hybrydauth');
function start_session_hybrydauth(){
	//проверям на наличие параметра в URL.
	if(! isset($_REQUEST['cp-aa'])) return;
	
	$provider_name = $_REQUEST['cp-aa'];

	require_once (plugin_dir_path( __FILE__ ) . '/hybridauth/Hybrid/Auth.php');

	$config = get_option('cp_hybridauth_config_data'); //get_config_hybridauth();

	//error_log('ttt' . print_r($config, true));
	
	//получаем текущий URL для дальнейшей работы
	global $wp;
	$current_url = home_url( $wp->request );
	$redirect_url = home_url($wp->request);
	
	try{
		// hybridauth EP
		$hybridauth = new Hybrid_Auth( $config );

		// automatically try to login with Twitter
		$provider = $hybridauth->authenticate( $provider_name );

		
		
		// return TRUE or False <= generally will be used to check if the user is connected to twitter before getting user profile, posting stuffs, etc..
		$is_user_logged_in = $provider->isUserConnected();

		// get the user profile 
		$user_profile = $provider->getUserProfile();

		//проводим авторизацию и аутентификацию. если пользователь получается то возвращаем ID
		$user_id = cp_login_authenticate_wp_user($user_profile, $provider->id);


		?>
		<div>Поздравляем с успешной авторизацией!</div>
		<script language="javascript">
 
			window.onload = function(){
				window.opener.location.href="<?php echo $current_url; ?>";
				self.close();
			};
		</script>
		<?php
		exit; 

	}
	catch( Exception $e ){  
		// In case we have errors 6 or 7, then we have to use Hybrid_Provider_Adapter::logout() to 
		// let hybridauth forget all about the user so we can try to authenticate again.

		// Display the received error,
		// to know more please refer to Exceptions handling section on the userguide
		switch( $e->getCode() ){ 
			case 0 : echo "Unspecified error."; break;
			case 1 : echo "Hybridauth configuration error."; break;
			case 2 : echo "Provider not properly configured."; break;
			case 3 : echo "Unknown or disabled provider."; break;
			case 4 : echo "Missing provider application credentials."; break;
			case 5 : echo "Authentication failed. " 
					  . "The user has canceled the authentication or the provider refused the connection."; 
				   break;
			case 6 : echo "User profile request failed. Most likely the user is not connected "
					  . "to the provider and he should to authenticate again."; 
				   $twitter->logout();
				   break;
			case 7 : echo "User not connected to the provider."; 
				   $twitter->logout();
				   break;
			case 8 : echo "Provider does not support this feature."; break;
		} 

		// well, basically your should not display this to the end user, just give him a hint and move on..
		echo "<br /><br /><b>Original error message:</b> " . $e->getMessage();

		echo "<hr /><h3>Trace</h3> <pre>" . $e->getTraceAsString() . "</pre>"; 

		/*
			// If you want to get the previous exception - PHP 5.3.0+ 
			// http://www.php.net/manual/en/language.exceptions.extending.php
			if ( $e->getPrevious() ) {
				echo "<h4>Previous exception</h4> " . $e->getPrevious()->getMessage() . "<pre>" . $e->getPrevious()->getTraceAsString() . "</pre>";
			}
		*/
	}
	
}


/*
Эта функция получает данные профиля из соц сети и проверяет есть ли связанные профили пользователя WP по идентификаторам или email.
Если есть то выполняет аутентификацию.
Если нет, то создает нового пользователя на основе данных соц сети.
*/
function cp_login_authenticate_wp_user($profile, $provider_id){

/*
Определяем переменные из профиля
*/
	$provider_id = strtolower( $provider_id );
	$email = $profile->email;
	$displayName = $profile->displayName;
	$identifier = $profile->identifier;

/*
Запрашиваем идентификатор и провайдера, чтобы понять есть ли пользователи с такими параметрами
*/
	$user_query = new WP_User_Query(
		array(
			'meta_key'	  =>	'cp_hybridauth_' . $provider_id . '_identifier',
			'meta_value'	=>	$identifier
		)
	);

	if($user_query->total_users == 1) {
		//Если запрос вернул одного пользователя, то ставим куку и авторизуем
		$users = $user_query->get_results();
		$user_id = $users[0]->ID;
		wp_set_auth_cookie($user_id, 1);
		//error_log('Если запрос вернул одного пользователя, то ставим куку и авторизуем');
		
		return $user_id;
		
	} elseif($user_query->total_users > 1) {
		//Если запрос вернул более одного пользователя, то сбрасываем меты. Это маловероятно, но лучше удалить авторизацию.
		$users = $user_query->get_results();
		foreach( $users as $user ):
			
			$user_id = $user->ID;
			
			delete_user_meta(
			$user_id, 
			$meta_key = 'cp_hybridauth_' . $provider_id . '_identifier', 
			$meta_value = $identifier);
		endforeach;
		return false;
	}

/*
Пробуем найти пользователя по эл.почте.
Если в профиле есть email, и есть такой пользователь в базе сайта, то добавляем мету и делаем авторизацию.
У этого пользователя явно не было ранних подключений к других соц сетям, иначе отработка прошла бы выше.
Даже если текущий пользователь авторизован на сайте, то email имеет приоритет и потому произойдет переавторизация.
*/
	if(! empty($email)) $user = get_user_by('email', $email );
	
	//Если не нашли пользователя по email то вернется false и нужно это учесть
	if(! empty($user_id)) {
		$user_id = $user->ID;
		if($user_id > 0) {
			error_log('Пробуем найти пользователя по эл.почте.');
			update_user_meta(
				$user_id, 
				$meta_key = 'cp_hybridauth_' . $provider_id . '_identifier', 
				$meta_value = $identifier);
			wp_set_auth_cookie($user_id, 1);
			return $user_id;	
		}
	}


/*
Если пользователь авторизован, то подключить к нему профиль.
При этом система не смогла найти аналогичные эл.ящики в базе или аналогичные профили ранее подключенные.
*/
	if ( is_user_logged_in() ) {
		$user_id = get_current_user_id();
		error_log('user id'.$user_id);
		update_user_meta(
			$user_id, 
			$meta_key = 'cp_hybridauth_' . $provider_id . '_identifier', 
			$meta_value = $identifier);
		return $user_id;
	}

/*
Если пользователя нет и нет авторизации, то создать нового и авторизовать
*/
	$random_password = wp_generate_password( $length=12, $include_standard_special_chars=false );
	//берем реальную почту из профиля или генерируем на лету
	$user_email = empty($email) ? ($identifier . '@' . $provider_id . '.tmp') : $email;
	
	//создаем пользователя
	$user_id = wp_create_user( $user_name = $displayName, $random_password, $user_email );
	
	update_user_meta(
		$user_id, 
		$meta_key = 'cp_hybridauth_' . $provider_id . '_identifier', 
		$meta_value = $identifier);
	wp_set_auth_cookie($user_id, 1);
	return $user_id;
}


/*
Добавляем форму для ввода электронной почты. Этот УРЛ вызывается в том случае если у пользователя нет почты.
*/
add_action('template_redirect', 'cp_hybridauth_add_email');
function cp_hybridauth_add_email(){
if(! isset($_REQUEST['add_email'])) return;
?>
<div id="add_email">
<form>
<input type="text" />
</form>
</div>
<?php
}
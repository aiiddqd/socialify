<?php
/*
Plugin Name: HAWP - HybridAuth for WordPress
Description: Позволяет авторизовываться в соц сетях и через различные сторонние сайты посредством протокола OAuth2, OAuth1 & OpenID
Version: 0.3
Author: AY
Author URI: https://github.com/yumashev
License: MIT License
Text Domain: hawp
Domain Path: languages
*/


require_once 'inc/class-ha-ep-callback.php';

class HAWP_Base {

	function __construct(){
		add_shortcode('btn-hybridauth', array($this, 'shortcode_display'));

		add_action('template_redirect', array($this, 'delete_profile'));

		add_action('init', array($this, 'start_session_hybridauth_login_page'));

		add_action('template_redirect', array($this, 'start_session_hybrydauth'));

		add_action( 'wp_enqueue_scripts', array($this, 'load_style') );
		add_action( 'login_enqueue_scripts', array($this, 'load_style') );


	}







	/*
	* @todo - разобраться
	*/
	function start_session_hybrydauth() {
		//проверям на наличие параметра в URL.
		if( empty($_REQUEST['cp-aa']) )
        return;

		$provider_name = $_REQUEST['cp-aa'];

		require_once (plugin_dir_path( __FILE__ ) . 'inc/hybridauth/Hybrid/Auth.php'); 

		// require_once (plugin_dir_path( __FILE__ ) . '/hybridauth/Hybrid/Auth.php');

		$config = $this->get_config_hybridauth();

		//получаем текущий URL для дальнейшей работы
		global $wp;

		$redirect_url = home_url($wp->request);


		try {

			// use Hybridauth\Hybridauth;

			// $hybridauth = new Hybridauth( $config );
			$hybridauth = new Hybrid_Auth( $config );


			$provider = $hybridauth->authenticate( $provider_name );

			// var_dump($provider); exit;

			// return TRUE or False <= generally will be used to check if the user is connected to twitter before getting user profile, posting stuffs, etc..
			$is_user_logged_in = $provider->isUserConnected();

			// get the user profile
			$user_profile = $provider->getUserProfile();

			//проводим авторизацию и аутентификацию. если пользователь получается то возвращаем ID
			$user_id = $this->cp_login_authenticate_wp_user($user_profile, $provider->id);

			//Если функция авторизации вернула ложь, то добавить в URL параметр ошибки
			if($user_id== false) $redirect_url = add_query_arg(array('h-auth' => 'fail'), $redirect_url);

			//проверка на временный email
			/*$user = get_userdata( $user_id );
			error_log('substr - ' . substr($user->user_email, -3));
			if(substr($user->user_email, -3) == 'tmp')
	        {
				wp_redirect(add_query_arg(array('get_email' => '1'), $redirect_url));
				exit;
			}*/

			wp_redirect($redirect_url);
			exit;

		}
		catch( Exception $e ) {
			printr("Ooophs, we got an error: %s", $e->getMessage()) ;
		}

	}


	function get_config_hybridauth(){

		$data =  array(
				'base_url' => "https://h404.wpcraft.ru/hawp/",

		    "providers" => array(
						"Google" => array(
		            "enabled" => true,
		            "keys" => array(
									"id" => "551592182457-k6ki08jpjv9ru9ct5qsksetsq6a6c6jj.apps.googleusercontent.com",
									"secret" => "betQ0acOYYTCZQWNNlIV4OE9"
								),
		        ),
		        "OpenID" => array(
		            "enabled" => false,
		        ),
		        "Yahoo" => array(
		            "enabled" => false,
		            "keys" => array("id" => "", "secret" => ""),
		        ),
		        "Facebook" => array(
		            "enabled" => false,
		            "keys" => array("id" => "", "secret" => ""),
		            "trustForwarded" => false,
		        ),
		        "Twitter" => array(
		            "enabled" => false,
		            "keys" => array("key" => "", "secret" => ""),
		            "includeEmail" => false,
		        ),
		    ),
		    // If you want to enable logging, set 'debug_mode' to true.
		    // You can also set it to
		    // - "error" To log only error messages. Useful in production
		    // - "info" To log info and error messages (ignore debug messages)
		    "debug_mode" => false,
		    // Path to file writable by the web server. Required if 'debug_mode' is not false
		    "debug_file" => "",
		);

		// $data = get_option('cp_hybridauth_config_data');
		return apply_filters('hawp_config', $data);
	}

	/*
  * Load style
	*/
	function load_style() {
    wp_enqueue_style( 'cp_hybridauth_style_frontend', plugins_url( 'inc/style.css', __FILE__ ) );
	}

	/*
	* На хуке редирект шаблона ловим ключ авторизации и если он есть, то выполняем авторизацию в соц сетях
	* @todo - разобраться
	*/
	function start_session_hybridauth_login_page()
	{
	    if(in_array($GLOBALS['pagenow'], array( 'wp-login.php')))
	        $this->start_session_hybrydauth();
	}

	/*
	Функция удаления профиля соц сети. Срабатывает на основе URL с параметром. Например: site.ru/?cp-aa-delete=Facebook
	*/
	function delete_profile()
	{
		if(! isset($_REQUEST['cp-aa-delete']))
	        return;

		$provider_id = (string) $_REQUEST['cp-aa-delete'];

		//Проверяем есть ли удаляемый профайл. Если нет, то возвращаем URL уведомления отсутствия профиля. Иначе удаляем профиль.
		$profile = get_user_meta(get_current_user_id(), $meta_key = 'cp_hybridauth_' . strtolower( $provider_id ) . '_identifier', true);
		if(empty($profile)) {
			wp_redirect(add_query_arg(array('result' => 'not_found_profile')));
			exit;
		} else  {
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
	* Display Shortcode
	*/
	function shortcode_display($atts, $content="") {

		extract(shortcode_atts( array(
			'provider_id' => 'Facebook',
			'img' => 'default baz',
			'connect' => false,
			'text' => 'Facebook'
			), $atts, 'btn-hybridauth' ));

		//если пользователи авторизован, то вернуть пустоту
		if(is_user_logged_in() && $connect == false)
	        return;

		//Если у шорткода есть контент, то текст ссылки заполняется контентом.
		if(! empty($content))
	        $text = $content;

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
		ob_start(); ?>
			<div class="cp-btn-hybridauth <?php echo apply_filters('cp_hybridauth_btn_class', $class_html) ?>">
				<a href="<?php echo $url ?>"><?php echo $text ?></a>
			</div>
	  <?php return ob_get_clean();
	}












	/*
	Эта функция получает данные профиля из соц сети и проверяет есть ли связанные профили пользователя WP по идентификаторам или email.
	Если есть то выполняет аутентификацию.
	Если нет, то создает нового пользователя на основе данных соц сети.
	*/
	function cp_login_authenticate_wp_user($profile, $provider_id)
	{
	    /*
	    Определяем переменные из профиля
	    */
		$provider_id = strtolower( $provider_id );
		$email = $profile->email;
		$username = $profile->displayName;
		$displayName = $profile->displayName;
		if(empty($displayName)) $displayName = $profile->lastName . ' ' . $profile->firstName;
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
			error_log('Если запрос вернул одного пользователя, то ставим куку и авторизуем');
			return $user_id;

		} elseif($user_query->total_users > 1) {
			//Если запрос вернул более одного пользователя, то сбрасываем меты. Это маловероятно, но лучше удалить авторизацию.
			error_log('Если запрос вернул много пользователей, то удаляем профиль на всякий случай у всех');
			$users = $user_query->get_results();
			foreach( $users as $user ):

				$user_id = $user->ID;

				delete_user_meta(
					$user_id,
					$meta_key = 'cp_hybridauth_' . $provider_id . '_identifier',
					$meta_value = $identifier
				);
			endforeach;
			return false;
		}

	    /*
	    Пробуем найти пользователя по эл.почте.
	    Если в профиле есть email, и есть такой пользователь в базе сайта, то добавляем мету и делаем авторизацию.
	    У этого пользователя явно не было ранних подключений к других соц сетям, иначе отработка прошла бы выше.
	    Даже если текущий пользователь авторизован на сайте, то email имеет приоритет и потому произойдет переавторизация.
	    */
		if(! empty($email))
	        $user = get_user_by('email', $email );

		//Если не нашли пользователя по email то вернется false и нужно это учесть
		if(! empty($user))
	    {
			$user_id = $user->ID;
			if($user_id > 0)
	        {
				error_log('Нашли пользователя по эл.почте.');
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
		if ( is_user_logged_in() )
	    {
			$user_id = get_current_user_id();
			error_log('Если пользователь авторизован, то подключить к нему профиль - '.$user_id);
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
		if(! is_email($email))
	        $email = $identifier . '@' . $provider_id . '.tmp';

		if(!validate_username($username) || empty($username))
	        $username = str_replace(array(' ', '@', '.'), '-', $email);

		//проверяем имя пользователя и мыло
		error_log('имя пользователя и мыло - ' . $username . ', ' . $email);

		//создаем пользователя
		$user_id = wp_create_user( $username, $random_password, $email );

		error_log('wp error - ' . print_r($user_id, true));

		if(! is_wp_error($user_id)) {
			if(!is_wp_error($user_id))
	        {
	            wp_update_user(array(
	                'ID' => $user_id,
	                'display_name' => $displayName,
	                'first_name' => $profile->firstName,
	                'last_name' => $profile->lastName
	            ));
	            update_user_meta(
	                $user_id,
	                $meta_key = 'cp_hybridauth_' . $provider_id . '_identifier',
	                $meta_value = $identifier
	            );
	            update_user_meta(
	                $user_id,
	                $meta_key = 'cp_hybridauth_email_confirmed',
	                $meta_value = 0
	            );
	        }

			error_log('Создаем пользователя - ' . $user_id);

			wp_set_auth_cookie($user_id, 1);
			return $user_id;
		}

	    /*
	    Если дошли до сюда, то ни одна из схем не сработала. Возвращаем false
	    */
	    return false;
	}

}
new HAWP_Base;

<?php
/*
Plugin Name: HAWP - HybridAuth for WordPress
Description: Позволяет авторизовываться в соц сетях и через различные сторонние сайты посредством протокола OAuth2, OAuth1 & OpenID
Version: 0.4
Author: AY
Author URI: https://github.com/yumashev
License: MIT License
Text Domain: hawp
Domain Path: languages
*/


require_once 'inc/class-ha-ep-callback.php';
require_once 'inc/class-login-form-act.php';
require_once 'inc/class-settings-api.php';
require_once 'inc/class-white-list.php';




class HAWP_Base {

	public $options;

	function __construct(){
		$this->options = $this->get_options();
		add_shortcode('btn-hybridauth', array($this, 'shortcode_display'));
		add_action('init', array($this, 'add_endpoint'));
		add_action('template_redirect', array($this, 'start_session_hybrydauth'));
		add_action('wp_enqueue_scripts', array($this, 'load_style') );
		add_action('login_enqueue_scripts', array($this, 'load_style') );
		add_action('wp_loaded', array($this, 'flush_rewrite_rules_hack') );
		add_filter('restricted_site_access_is_restricted', array($this, 'support_rsa'), 10 ,2);
	}

	/*
	* Add endpoint HAWP as exception
	* Use apply_filters( 'restricted_site_access_is_restricted', $is_restricted, $wp
	*/
	function support_rsa($is_restricted, $wp){

		$check = stristr($wp->request, 'ha-sign' );
		if($check !== false){
			$is_restricted = false;
		}

		return $is_restricted;
	}

	/*
	* Small hack for check and reset rewrite rules
	*/
	function flush_rewrite_rules_hack(){
		$rules = get_option( 'rewrite_rules' );

		if ( ! isset( $rules['ha-sign(/(.*))?/?$'] ) ) {
				flush_rewrite_rules( $hard = false );
		}
	}

	/*
  * Add endpoint /ha-sign/ for app
  */
  function add_endpoint() {
    add_rewrite_endpoint( 'ha-sign', EP_ROOT );
  }


	function get_options(){
		$data = array(
			'disable_registration' => true,
			'domains_check' => false,
			'domains_white_list' => array()
		);
		return apply_filters('hawp_options', $data);
	}


	/*
  * Start OAuth2
	*/
	function start_session_hybrydauth() {

		$call = get_query_var('ha-sign', false);

		//проверям на наличие запроса в URL Endpoint
		if( $call === false ){
			return;
		}

		try {

			if(empty($call)){
				 throw new Exception('Empty provider\'s name.');
			}

			if ( is_user_logged_in() ){
				throw new Exception('User logged in. Not allow sign in via OAuth2');
			}


			$provider_name = $call;

			require_once (plugin_dir_path( __FILE__ ) . 'inc/hybridauth/Hybrid/Auth.php');

			$config = $this->get_config_hybridauth();

			$hybridauth = new Hybrid_Auth( $config );

			$provider = $hybridauth->authenticate( $provider_name );

			// return TRUE or False <= generally will be used to check if the user is connected to twitter before getting user profile, posting stuffs, etc..
			$is_user_logged_in = $provider->isUserConnected();

			// get the user profile
			$user_profile = $provider->getUserProfile();

			$user_id = $this->hawp_get_user($user_profile, $provider->id);
			$user_id = (int)$user_id;

			if( ! empty($user_id)){
				if( apply_filters('hawp_set_auth_cookie', true) ){
					update_user_meta( $user_id, $meta_key = 'cp_hybridauth_' . $provider_id . '_identifier', $meta_value = $identifier);
					wp_set_auth_cookie($user_id, 1);
				}
			}

			//Если функция авторизации вернула ложь, то добавить в URL параметр ошибки
			if( empty($user_id) ) {
				$redirect_url = wp_login_url();
			} else {
				if( empty($_GET['redirect_url'])){
					$redirect_url = site_url('/');
				} else {
					$redirect_url = sanitize_url($_GET['redirect_url']);
				}
			}

			wp_redirect($redirect_url);
			exit;

		} catch( Exception $e ) {
			wp_send_json_error($e->getMessage(), 400);
		}

	}



	/*
	Эта функция получает данные профиля из соц сети и проверяет есть ли связанные профили пользователя WP по идентификаторам или email.
	Если есть то выполняет аутентификацию.
	Если нет, то создает нового пользователя на основе данных соц сети.
	*/
	function hawp_get_user($profile, $provider_id) {
	    /*
	    Определяем переменные из профиля
	    */
		$provider_id = strtolower( $provider_id );
		$email = $profile->email;
		$username = $profile->displayName;
		$displayName = $profile->displayName;
		if(empty($displayName)) $displayName = $profile->lastName . ' ' . $profile->firstName;
		$identifier = $profile->identifier;



		if(empty($email)){
			throw new Exception('Email is empty');
		}

		//try get user_id by social id
		$user_id = $this->profile_exist($provider_id, $identifier);

		//try get user_id by email
		if(empty($user_id)){
			$user_id = email_exists($email);
			$user_id = (int)$user_id;
		}

		if(empty($user_id)){
			$user_id = apply_filters('hawp_get_user_id', 0, $profile, $provider_id);
		}

    $user_id = (int)$user_id;

		if(empty($user_id)){
			throw new Exception('User ID not found');
		}

		return $user_id;

	}

	/**
	* Check exist profile and return user_id or false
	*
	* @return $user_id int or false
	*/
	function profile_exist($provider_id, $identifier){

    //Запрашиваем идентификатор и провайдера, чтобы понять есть ли пользователи с такими параметрами
		$user_query = new WP_User_Query(
			array(
				'meta_key'	  =>	'cp_hybridauth_' . $provider_id . '_identifier',
				'meta_value'	=>	$identifier
			)
		);

		if($user_query->total_users === 1) {
			//Если запрос вернул одного пользователя, то ставим куку и авторизуем
			$users = $user_query->get_results();
			$user_id = $users[0]->ID;
			// error_log('Если запрос вернул одного пользователя, то ставим куку и авторизуем');
			return (int)$user_id;

		} elseif($user_query->total_users > 1) {
			//Если запрос вернул более одного пользователя, то сбрасываем меты. Это маловероятно, но лучше удалить авторизацию.
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
		} else {
			return false;
		}

	}


	/*
  * Load style
	*/
	function load_style() {
    wp_enqueue_style( 'cp_hybridauth_style_frontend', plugins_url( 'inc/style.css', __FILE__ ) );
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

    $url = add_query_arg(array('cp-aa' => $provider_id));
    $class_html = strtolower( $provider_id );

		//Выводим HTML код кнопки
		ob_start(); ?>
			<div class="cp-btn-hybridauth <?php echo apply_filters('cp_hybridauth_btn_class', $class_html) ?>">
				<a href="<?php echo $url ?>"><?php echo $text ?></a>
			</div>
	  <?php return ob_get_clean();
	}

	/*
	* Get config for HybridAuth
	*/
	function get_config_hybridauth(){

		$data =  array(

			'base_url' => site_url('/hawp/'),

	    "providers" => array(
					"Google" => array(
	            "enabled" => false,
	            "keys" => array(
								"id" => "",
								"secret" => ""
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

		return apply_filters('hawp_config', $data);
	}

}
new HAWP_Base;

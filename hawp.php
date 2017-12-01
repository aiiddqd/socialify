<?php
/*
Plugin Name: HAWP - HybridAuth for WordPress
Description: Позволяет авторизовываться в соц сетях и через различные сторонние сайты посредством протокола OAuth2, OAuth1 & OpenID
Version: 0.7
Author: AY
Author URI: https://github.com/uptimizt
License: MIT License
Text Domain: hawp
Domain Path: languages
*/




// require_once 'inc/class-ha-ep-callback.php';
require_once 'inc/class-login-form-act.php';
require_once 'inc/class-settings-api.php';
require_once 'inc/class-white-list.php';


// @todo - do the right thing
// require_once 'inc/class-profile-ui.php';



class HAWP_Base {

	public $options;

	function __construct(){
		$this->options = $this->get_options();
		// add_shortcode('btn-hybridauth', array($this, 'shortcode_display'));
		add_action('init', array($this, 'add_endpoint'));
		add_action('wp_enqueue_scripts', array($this, 'load_style') );
		add_action('login_enqueue_scripts', array($this, 'load_style') );
		add_action('wp_loaded', array($this, 'flush_rewrite_rules_hack') );
		add_filter('restricted_site_access_is_restricted', array($this, 'support_rsa'), 10 ,2);

		add_action('template_redirect', array($this, 'hybrydauth_start'));
		add_action('template_redirect', array($this, 'hybrydauth_callback'));
	}


	function hybridauth_process($provider_name = '')
	{
			$config =  array(
				'callback' => site_url('/ha-callback/'),
				"providers" => array(
						"Google" => array(
								"enabled" => false,
								"keys" => array(
									"id" => "",
									"secret" => ""
								),
						),
						"OpenID" => array(
								"enabled" => false
						),
						"Facebook" => array(
								"enabled" => false,
								"keys" => array("id" => "", "secret" => ""),
								"trustForwarded" => false,
						)
				),
				// If you want to enable logging, set 'debug_mode' to true.
				// You can also set it to
				// - "error" To log only error messages. Useful in production
				// - "info" To log info and error messages (ignore debug messages)
				"debug_mode" => false,
				// Path to file writable by the web server. Required if 'debug_mode' is not false
				"debug_file" => "",
			);

			$config = apply_filters('hawp_config', $config);

			include plugin_dir_path( __FILE__ ) . 'inc/ha2/autoload.php';
			$hybridauth = new Hybridauth\Hybridauth( $config );

			$provider = $hybridauth->authenticate( $provider_name );

			//Returns a boolean of whether the user is connected with Twitter
	    $isConnected = $provider->isConnected();

			$user_profile = $provider->getUserProfile();

			// get the user profile
			$user_id = $this->hawp_get_user($user_profile, $provider->id);
			$user_id = (int)$user_id;

			if( ! empty($user_id)){
				if( apply_filters('hawp_set_auth_cookie', true) ){
					wp_set_auth_cookie($user_id, 1);
				}
			}

			//Если функция авторизации вернула ложь, то добавить в URL параметр ошибки
			if( empty($user_id) ) {
				$redirect_url = wp_login_url();
			} else {
				if( empty($_GET['redirect_to'])){
					$redirect_url = site_url('/');
				} else {
					$redirect_url = sanitize_url($_GET['redirect_to']);
				}
			}

			wp_redirect($redirect_url);
			exit;

	}

	/*
  * Start OAuth2
	*/
	function hybrydauth_start() {

		$call = get_query_var('ha-sign', false);

		//проверям на наличие запроса в URL Endpoint
		if( $call === false ){
			return;
		}

		try {

			if(empty($call)){
				 throw new Exception('Empty provider\'s name.');
			}

			$provider_name = $call;


			if ( is_user_logged_in() ){
				throw new Exception('User logged in. Not allow sign in via OAuth2');
			}

			$this->hybridauth_process($provider_name);

		} catch (Exception $e) {
			$msg = $e->getMessage();
			do_action('u7logger', ['hybridauth-wordpress/hawp.php - err1', $msg]);
			exit;
		}
	}



	function hybrydauth_callback()
	{
		$call = get_query_var('ha-callback', false);
		if( $call === false ){
			return;
		}

		try {
			$this->hybridauth_process();
		} catch( Exception $e ) {
			$msg = $e->getMessage();
			do_action('u7logger', ['hybridauth-wordpress/hawp.php - err2', $msg]);
			exit;
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
		// $displayName = $profile->displayName;

		$displayName = $profile->lastName . ' ' . $profile->firstName;

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

		$user_id = wp_update_user( array(
			'ID' => $user_id,
			'display_name' => $displayName,
			'last_name' => $profile->lastName,
			'first_name' => $profile->firstName
		));


		// var_dump($user_id); exit;

		update_user_meta( $user_id, $meta_key = 'cp_hybridauth_' . $provider_id . '_identifier', $meta_value = $identifier);

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
	* Add endpoints HAWP as exception
	* Use apply_filters( 'restricted_site_access_is_restricted', $is_restricted, $wp
	*/
	function support_rsa($is_restricted, $wp){

		$check = stristr($wp->request, 'ha-sign' );
		if($check !== false){
			$is_restricted = false;
		}

		$check = stristr($wp->request, 'hawp' );
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
		if ( ! isset( $rules['ha-callback(/(.*))?/?$'] ) ) {
				flush_rewrite_rules( $hard = false );
		}
	}

	/*
	* Add endpoint /ha-sign/ for app
	*/
	function add_endpoint() {
		add_rewrite_endpoint( 'ha-sign', EP_ROOT );
		add_rewrite_endpoint( 'ha-callback', EP_ROOT );
	}


	function get_options(){
		$data = array(
			'disable_registration' => true,
			'domains_check' => false,
			'domains_white_list' => array()
		);
		return apply_filters('hawp_options', $data);
	}
}
new HAWP_Base;

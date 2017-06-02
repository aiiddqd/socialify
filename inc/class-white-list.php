<?php

/**
 * White List for HAWP
 *
 */
class HAWP_White_List{

  function __construct()
  {
    add_filter('hawp_get_user_id', [$this, 'add_user_for_domain'], 10, 3);
  }

  /**
   * Add user for domain
   *
   * Use hook apply_filters('hawp_get_user_id', 0, $profile, $provider_id);
   *
   * @return $user_id or Exception
   */
  public function add_user_for_domain($user_id, $profile, $provider_id){

    //Если уже есть user id, то нет смысла делать новый
    if( ! empty($user_id)){
      return $user_id;
    }

    //Проверка включена или нет регистрация по белому списку
    if( empty( get_option('domains_white_list_enabled') ) ){
      return $user_id;
    }

    if(empty($profile->email)){
      throw new Exception("Empty email (White List)");
    }

    if( ! $this->is_email_in_white_list($profile->email)){
      throw new Exception("Email not allow (White List)");
    }

    $user_id = email_exists($profile->email);
    if( ! empty($user_id)){
      return $user_id;
    }

    $password = wp_generate_password( $length=12, $include_standard_special_chars=false );

    $users_ids = get_users('fields=ID&number=3&orderby=registered&order=DESC');
    $last_id = max($users_ids);
    $new_id = $last_id+1;
    $user_login = 'u'. $new_id;

    $user_id = wp_create_user($username = $this->generate_new_userlogin(), $password, $profile->email);

    return $user_id;
  }

  /**
   * Check email in white list
   */
  public function is_email_in_white_list($email) {

    $domain_name = substr(strrchr($email, "@"), 1);

    $domains_white_list = get_option('domains_white_list');
    $domains = explode(",", $domains_white_list);

    return in_array($domain_name, $domains);
  }

  /**
  * Generate login for new user
  */
  function generate_new_userlogin(){
    $users_ids = get_users('fields=ID&number=3&orderby=registered&order=DESC');
    $last_id = max($users_ids);
    $new_id = $last_id+1;
    $user_login = 'u'. $new_id;
    return $user_login;
  }

}
new HAWP_White_List;

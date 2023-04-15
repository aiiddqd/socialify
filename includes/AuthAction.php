<?php 

namespace Socialify\AuthAction;

//do_action('socialify_auth_handle', $data['user_profile'], PROVIDER_KEY, $data['redirect_to']);
add_action('socialify_auth_handle', function(\Hybridauth\User\Profile $user_profile, string $provider, $redirect_to){

    if($user_id = get_current_user_id()){
        $user = get_connected_user($user_profile->identifier, $provider);
    } else {
        $user = get_user_by('email', $user_profile->email);
    }
    // var_dump($user); exit;

    if(empty($user)){
        $user = add_user($user_profile);
    }

    if(empty($user)){
        return false;
    }

    $auth_id_meta_key = 'socialify_' . $provider . '_id_' . $user_profile->identifier;
    update_user_meta($user->ID, $auth_id_meta_key, $user_profile->identifier);

    auth_user($user);

    if(empty($redirect_to)){
        wp_redirect(site_url());
    } else {
        wp_safe_redirect($redirect_to);
    }
    exit;

}, 10, 3);

function auth_user( \WP_User $user){
    if(empty($user->ID)){
        return false;
    }

    wp_set_current_user( $user->ID );
    wp_set_auth_cookie( $user->ID, true );
    do_action( 'wp_login', $user->user_login, $user );
    return true;
}

function add_user($userProfile){
    if(empty($userProfile->email)){
        return false;
    }

    $user_data = [
        'user_login' => generate_new_userlogin(),
        'user_pass'  => wp_generate_password( 11, false ),
        'user_email' => sanitize_email($userProfile->email),
    ];

    if( ! empty($userProfile->firstName) ){
        $user_data['first_name'] = sanitize_text_field($userProfile->firstName);
    }

    if( ! empty($userProfile->lastName) ){
        $user_data['last_name'] = sanitize_text_field($userProfile->lastName);
    }

    if( ! empty($userProfile->displayName) ){
        $user_data['display_name'] = sanitize_text_field($userProfile->displayName);
    }

    if( ! $user_id = wp_insert_user($user_data) ){
        return false;
    }

    if(!$user = get_user_by('id', $user_id)){
        return false;
    }

    return $user;
}

function get_connected_user($identifier = '', $provider = ''){

    $auth_id_meta_key = 'socialify_' . $provider . '_id_' . $identifier;
    $users = get_users(array(
        'meta_key' => $auth_id_meta_key,
        'meta_value' => $identifier,
        'count_total' => false
    ));

    if(empty($users[0]->ID)){
        return false;
    }

    if(count($users) > 1){
        return false;
    }

    return get_user_by('id', $users[0]->ID);
}

function generate_new_userlogin(){
    $users_ids  = get_users('fields=ID&number=3&orderby=registered&order=DESC');
    $last_id    = max($users_ids);
    $new_id     = $last_id + 1;
    $user_login = 'id' . $new_id;

    return $user_login;
}
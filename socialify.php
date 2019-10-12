<?php
/**
 * Plugin Name:  Socialify (Social Login, OAuth, HybridAuth)
 * Description:  Social Login for WordPress based on OAuth2 and HybridAuth
 * Version:      0.3
 * Author:       uptimizt
 * Author URI:   https://github.com/uptimizt
 * Text Domain:  socialify
 * Domain Path:  /languages/
 * Requires PHP: 5.6
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 */

namespace Socialify;

defined('ABSPATH') || die();

final class General
{

    public static $slug = 'socialify';
    public static $name = 'Socialify';

    /**
     * @var string - for grouping all settings (by Settings API)
     */
    public static $settings_group = 'socialify_login_settings';

    public static function init()
    {
        add_action('init', function(){
            if( ! isset($_GET['dd']) ){
                return;
            }

            echo '<pre>';
            var_dump(1);
            exit;
        });

        require_once __DIR__ . '/vendor/autoload.php';
        require_once __DIR__ . '/includes/FacebookConfig.php';
        require_once __DIR__ . '/includes/GoogleConfig.php';
        require_once __DIR__ . '/includes/YandexConfig.php';
        require_once __DIR__ . '/includes/VKConfig.php';

        add_action('wp', [__CLASS__, 'start_auth']);

        add_action('init', [__CLASS__, 'add_endpoint']);

        add_action('admin_menu', function(){
            add_options_page(
                $page_title = 'Socialify Settings',
                $menu_title = 'Socialify',
                $capability = 'administrator',
                $menu_slug = self::$slug . '-settings',
                $callback = [__CLASS__, 'render_settings']
            );
        });
    }


    public static function start_auth()
    {
        if (false === get_query_var(self::$slug, false)) {
            return;
        }

        if( ! $endpoint = get_query_var(self::$slug)){
            wp_redirect(site_url());
            exit;
        }

        try {

            $userProfile = '';
            $userProfile = apply_filters('socialify_user_profile', $userProfile, $endpoint);

            if(is_wp_error($userProfile)){
                throw new \Exception('$userProfile is WP Error.');
            }

            if(empty($userProfile)){
                throw new \Exception('$userProfile is empty.');
            }

            self::user_handler($userProfile);

            wp_redirect(site_url());
            exit;
        }

        catch(\Exception $e){
            error_log('Socialify: Oops, we ran into an issue! ' . $e->getMessage());
            wp_redirect(site_url());
            exit;
        }
    }

    /**
     * Check data from HA, auth exists user or create and auth
     *
     * @param $userProfile
     *
     * @return bool
     */
    public static function user_handler($userProfile){

        if(empty($userProfile->email)){
            return false;
        }

        if($user = get_user_by('email', $userProfile->email)){
            self::auth_user($user);
        } else {
            if($user = self::add_user($userProfile)){
                self::auth_user($user);
            } else {
                return false;
            }
        }

        return true;
    }

    public static function add_user($userProfile){
        if(empty($userProfile->email)){
            return false;
        }

        $user_data = [
            'user_login' => self::generate_new_userlogin(),
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

        $userProfileArray = (array) $userProfile;

        if(!$user = get_user_by('id', $user_id)){
            return false;
        }

        return $user;
    }

    public static function generate_new_userlogin(){
        $users_ids  = get_users('fields=ID&number=3&orderby=registered&order=DESC');
        $last_id    = max($users_ids);
        $new_id     = $last_id + 1;
        $user_login = 'id' . $new_id;

        return $user_login;
    }

    public static function auth_user($user){
        if(empty($user->ID)){
            return false;
        }

        wp_set_current_user( $user->ID );
        wp_set_auth_cookie( $user->ID );
        do_action( 'wp_login', $user->user_login, $user );
        return true;
    }

    /**
     * Add settings
     */
    public static function render_settings(){
        ?>
        <div class="wrap">
            <h1><?= __('Socialify Settings', 'socialify') ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields( self::$settings_group ); ?>
                <?php do_settings_sections( self::$settings_group ); ?>
                <?php submit_button(); ?>

            </form>
        </div>
        <?php
    }

    /**
     * add endpoint
     */
    public static function add_endpoint()
    {
        add_rewrite_endpoint(self::$slug, EP_ROOT);

        /**
         * hack for reset rewrite rules
         */
        $rules = get_option('rewrite_rules');
        $key = sprintf('%s(/(.*))?/?$', self::$slug);
        if ( ! isset($rules[$key])) {
            flush_rewrite_rules($hard = false);
        }
    }

}

General::init();

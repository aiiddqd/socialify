<?php
/**
 * Plugin Name:  Socialify
 * Description:  Social Login for WordPress based the OAuth2 and HybridAuth
 * Plugin URI:   https://github.com/uptimizt/socialify
 * Author:       uptimizt
 * Author URI:   https://github.com/uptimizt
 * Text Domain:  socialify
 * Domain Path:  /languages/
 * GitHub Plugin URI: https://github.com/uptimizt/socialify
 * Requires PHP: 8.0
 * Version:      0.9.241208
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

require_once __DIR__ . '/vendor/autoload.php';

$files = glob(__DIR__ . '/includes/*.php');
foreach ($files as $file) {
    require_once $file;
}

General::init();

final class General
{
    /**
     * Name of product
     *
     * @var string
     */
    public static $name = 'Socialify';

    /**
     * Slug of the product for make a hungarian notations
     * @var string
     */
    public static $slug = 'socialify';

    /**
     * Save the $plugin_basename value for various frequent tasks
     *
     * @var string
     */
    public static $plugin_basename = '';
    public static $plugin_file_path = '';
    public static $plugin_dir_path = '';
    public static $plugin_dir_url = '';
    public static $redirect_to = '';

    /**
     * @var string - for grouping all settings (by Settings API)
     */
    public static $settings_group = 'socialify_login_settings';

    /**
     * The init
     */
    public static function init()
    {
        self::$plugin_basename = plugin_basename(__FILE__);
        self::$plugin_file_path = __FILE__;
        self::$plugin_dir_path = plugin_dir_path(__FILE__);
        self::$plugin_dir_url = plugin_dir_url(__FILE__);

        add_action('wp', [__CLASS__, 'start_auth']);

        add_action('init', [__CLASS__, 'add_endpoint']);

        add_filter("plugin_action_links_" . self::$plugin_basename, [__CLASS__, 'add_settings_url_to_plugins_list']);
    }


    /**
     * Add Settings link in pligins list
     */
    public static function add_settings_url_to_plugins_list($links)
    {
        $settings_link = sprintf('<a href="%s">%s</a>', admin_url('admin.php?page=socialify-settings'), __('Settings', self::$slug));
        array_unshift($links, $settings_link);
        return $links;
    }



    /**
     * helper get_current_url
     *
     * @link https://wordpress.stackexchange.com/questions/274569/how-to-get-url-of-current-page-displayed
     *
     * @return string|void
     */
    public static function get_current_url()
    {
        global $wp;
        return home_url($wp->request);
    }


    public static function start_auth()
    {
        if (false === get_query_var(self::$slug, false)) {
            return;
        }

        if (! $endpoint = get_query_var(self::$slug)) {
            wp_redirect(site_url());
            exit;
        }

        try {

            $userProfile = '';
            $userProfile = apply_filters('socialify_user_profile', $userProfile, $endpoint);

            $auth_process_data = [
                'user_data' => $userProfile,
                'redirect_to' => '',
            ];

            $auth_process_data = apply_filters('socialify_auth_process', $auth_process_data, $endpoint);

            if (is_wp_error($auth_process_data['user_data'])) {
                throw new \Exception('$userProfile is WP Error.');
            }

            if (empty($auth_process_data['user_data'])) {
                throw new \Exception('$userProfile is empty.');
            }

            self::user_handler($auth_process_data);

            if (empty(self::$redirect_to)) {
                self::$redirect_to = $auth_process_data['redirect_to'];
            }

            if (empty(self::$redirect_to)) {
                wp_redirect(site_url());
            } else {
                wp_redirect(self::$redirect_to);
            }
            exit;
        } catch (\Exception $e) {
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
    public static function user_handler($process_data)
    {
        if (empty($process_data['user_data']) || empty($process_data['provider'])) {
            return false;
        }

        $user_data = $process_data['user_data'];

        $user = get_user_by('id', get_current_user_id());

        if (empty($user)) {
            $user = self::get_connected_user($user_data->identifier, $process_data['provider']);
        }

        if (empty($user) && ! empty($user_data->email)) {
            $user = get_user_by('email', $user_data->email);
        }

        if (empty($user)) {
            $user = self::add_user($user_data);
        }

        if (empty($user)) {
            return false;
        }

        $auth_id_meta_key = 'socialify_' . $process_data['provider'] . '_id_' . $user_data->identifier;
        update_user_meta($user->ID, $auth_id_meta_key, $user_data->identifier);

        self::auth_user($user);
        return true;

    }

    public static function get_connected_user($identifier = '', $provider = '')
    {

        $auth_id_meta_key = 'socialify_' . $provider . '_id_' . $identifier;
        $users = get_users(array(
            'meta_key' => $auth_id_meta_key,
            'meta_value' => $identifier,
            'count_total' => false
        ));

        if (empty($users[0]->ID)) {
            return false;
        }

        if (count($users) > 1) {
            return false;
        }

        return get_user_by('id', $users[0]->ID);
    }

    public static function add_user($userProfile)
    {
        if (empty($userProfile->email)) {
            return false;
        }

        $user_data = [
            'user_login' => self::generate_new_userlogin(),
            'user_pass' => wp_generate_password(11, false),
            'user_email' => sanitize_email($userProfile->email),
        ];

        if (! empty($userProfile->firstName)) {
            $user_data['first_name'] = sanitize_text_field($userProfile->firstName);
        }

        if (! empty($userProfile->lastName)) {
            $user_data['last_name'] = sanitize_text_field($userProfile->lastName);
        }

        if (! empty($userProfile->displayName)) {
            $user_data['display_name'] = sanitize_text_field($userProfile->displayName);
        }

        if (! $user_id = wp_insert_user($user_data)) {
            return false;
        }

        if (! $user = get_user_by('id', $user_id)) {
            return false;
        }

        return $user;
    }

    public static function generate_new_userlogin()
    {
        $users_ids = get_users('fields=ID&number=3&orderby=registered&order=DESC');
        $last_id = max($users_ids);
        $new_id = $last_id + 1;
        $user_login = 'id' . $new_id;

        return $user_login;
    }

    public static function auth_user($user)
    {
        if (empty($user->ID)) {
            return false;
        }

        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        do_action('wp_login', $user->user_login, $user);
        return true;
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
        if (! isset($rules[$key])) {
            flush_rewrite_rules($hard = false);
        }
    }

}

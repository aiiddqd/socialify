<?php

namespace Socialify;

defined('ABSPATH') || die();

Yandex::init();

final class Yandex
{
    public static $key = 'yandex';

    public static $endpoint;

    public static function init()
    {
        // @link https://wpcraft.ru/wp-json/socialify/v1/yandex
        add_action('rest_api_init', function () {
            register_rest_route('socialify/v1', 'yandex', [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'process'],
                'permission_callback' => '__return_true'
            ]);
        });

        add_action('init', function () {
            self::$endpoint = rest_url('socialify/v1/yandex');
        });

        add_action('admin_init', [__CLASS__, 'add_settings']);

    }

    /**
     * // @link https://wpcraft.ru/wp-json/socialify/v1/yandex
     */
    public static function process(\WP_REST_Request $req)
    {
        $appConfig = self::get_config();

        if (empty($appConfig['id']) || empty($appConfig['secret'])) {
            return new \WP_Error('no_config', 'No config');
        }

        $url = 'https://oauth.yandex.ru/authorize';

        $state = $_GET['redirect'] ?? '';

        $url = add_query_arg([
            'response_type' => 'code',
            'state' => urlencode($state),
            'client_id' => $appConfig['id'],
        ], $url);

        // request code
        if (empty($_GET['code'])) {
            wp_redirect($url);
            exit;
        }

        $code = $_GET['code'] ?? null;
        if (empty($code)) {
            return new \WP_Error('no_code', 'No code');
        }

        $url = 'https://oauth.yandex.ru/token';

        $response = wp_remote_request($url, [
            'method' => 'POST',
            'body' => [
                'code' => $code,
                'grant_type' => 'authorization_code',
            ],
            'headers' => [
                'Content-type' => 'application/x-www-form-urlencoded',
                'Authorization' => 'Basic ' . base64_encode($appConfig['id'] . ':' . $appConfig['secret']),
            ],

        ]);

        if (is_wp_error($response)) {
            return $response;
        }

        $token_data = json_decode(wp_remote_retrieve_body($response), true);

        $access_token = $token_data['access_token'] ?? null;
        $refresh_token = $token_data['refresh_token'] ?? null;
        $expires_in = $token_data['expires_in'] ?? null;

        // @link https://wpcraft.ru/wp-json/socialify/v1/yandex

        if(empty($access_token)) {
            return new \WP_Error('no_access_token', 'No access token');
        }

        $userData = self::get_user_data($access_token);

        
        $email = $userData['default_email'] ?? null;
        if (empty($email)) {
            return new \WP_Error('no_email', 'No email');
        }

        $user = get_user_by('email', $email);
        if (empty($user)) {
            $username = wp_generate_uuid4();
            $user_id = wp_create_user($username, wp_generate_password(), $email);

            $user = get_user_by('id', $user_id);

            wp_update_user([
                'ID' => $user_id,
                'display_name' => $userData['display_name'] ?? $user->display_name,
                'first_name' => $userData['first_name'] ?? $user->first_name,
                'last_name' => $userData['last_name'] ?? $user->last_name,
            ]);
        }

        $meta = get_user_meta($user->ID, 'socialify', true);
        if($meta){
            $meta = [];
        }
        // $meta['yandex'] = [
        //     'access_token' => $access_token,
        //     'refresh_token' => $refresh_token,
        //     'expires_in' => $expires_in,
        // ];

        update_user_meta($user->ID, 'socialify', $meta);

        $redirect_url = site_url();
        $state = $_GET['state'] ?? '';
        if(isset($state) && filter_var($state, FILTER_VALIDATE_URL)) {
            $redirect_url = $state;
        }
        
        General::auth_user($user);

        wp_redirect($redirect_url);
        exit;
    }

    public static function get_user_data(string $access_token)
    {
        $url = 'https://login.yandex.ru/info';
        $url = add_query_arg('format', 'json', $url);

        $response = wp_remote_get($url, [
            'headers' => [
                'Authorization' => 'OAuth ' . $access_token,
            ],
        ]);

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    /**
     * Add settings
     */
    public static function add_settings()
    {
        add_settings_section(
            self::get_section_id(),
            __('Yandex', 'socialify'),
            function () { ?>
            <ol>
                <li>
                    <span><?= __('Get values: ', 'socialify') ?></span>
                    <a href="https://oauth.yandex.ru/" target="_blank">https://oauth.yandex.ru/</a>
                </li>
                <li>Callback URI: <code><?= self::$endpoint ?></code></li>
                <li>Website: <code><?= site_url() ?></code></li>
                <li>Domain: <code><?= $_SERVER['SERVER_NAME'] ?></code></li>
            </ol>
            <?php
            },
            Settings::$settings_group
        );

        // register_setting(Settings::$settings_group, Settings::$option_key);

        self::add_setting_id();
        self::add_setting_secret();
    }

    public static function get_section_id()
    {
        return self::$key . '_section';
    }

    public static function add_setting_id()
    {
        add_settings_field(
            self::$key . '_id',
            __('Yandex ID', 'socialify'),
            $callback = function ($args) {
                printf(
                    '<input type="text" name="%s" value="%s" size="77">',
                    $args['name'],
                    $args['value']
                );
            },
            Settings::$settings_group,
            self::get_section_id(),
            [
                'name' => Settings::$option_key . '[yandex][id]',
                'value' => get_option(Settings::$option_key)['yandex']['id'] ?? null,
            ]
        );
    }

    public static function get_config()
    {
        return get_option(Settings::$option_key)['yandex'] ?? [];
    }

    public static function add_setting_secret()
    {

        add_settings_field(
            self::$key . '_secret',
            __('Yandex Secret', 'socialify'),
            $callback = function ($args) {
                printf(
                    '<input type="text" name="%s" value="%s" size="77">',
                    $args['name'],
                    $args['value']
                );
            },
            Settings::$settings_group,
            self::get_section_id(),
            [
                'name' => Settings::$option_key . '[yandex][secret]',
                'value' => get_option(Settings::$option_key)['yandex']['secret'] ?? null,
            ]
        );

    }
}


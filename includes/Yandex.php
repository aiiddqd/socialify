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

        add_action('admin_init', function () {
            // if (isset($_GET['test_yandex'])) {
            //     var_dump(1); exit;
            // }
        });

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
        add_action('socialify_shortcode', [__CLASS__, 'display_button']);

    }


    public static function display_button($args)
    {
        if (is_user_logged_in()) {
            return;
        }
        if (!self::is_enabled()) {
            return;
        }

        if (empty(get_option(Settings::$option_key)['yandex']['show_button'])) {
            return;
        }

        $url = self::$endpoint;
        if (isset($args['redirect'])) {
            $url = add_query_arg('redirect', urlencode($args['redirect']), $url);
        }

        ?>
        <a class="wp-block-button__link wp-element-button" href="<?= $url ?>">Войти через Яндекс</a>
        <!-- <div class="wp-block-group is-layout-constrained wp-block-group-is-layout-constrained">
            <div class="wp-block-buttons is-layout-flex wp-block-buttons-is-layout-flex">
                <div class="wp-block-button">
                    
                </div>
            </div>
        </div> -->
        <?php

        // printf('<a href="%s" class="socialify_yandex">Login with Yandex</a>', $url);
    }


    public static function process(\WP_REST_Request $req)
    {
        $appConfig = self::get_config();

        if (empty($appConfig['id']) || empty($appConfig['secret'])) {
            return new \WP_Error('no_config', 'No config');
        }

        if (empty($_GET['code'])) {
            self::request_code($appConfig['id']);
        }

        $code = sanitize_text_field($_GET['code']);

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
        $redirect_url = site_url();
        $state = $_GET['state'] ?? '';
        if (isset($state) && filter_var($state, FILTER_VALIDATE_URL)) {
            $redirect_url = $state;
        }

        $token_data = json_decode(wp_remote_retrieve_body($response), true);

        $access_token = $token_data['access_token'] ?? null;
        $refresh_token = $token_data['refresh_token'] ?? null;
        $expires_in = $token_data['expires_in'] ?? null;

        if (empty($access_token)) {
            wp_redirect($redirect_url);
            exit;
            // return new \WP_Error('no_access_token', 'No access token');
        }

        $userData = self::get_user_data($access_token);

        $email = $userData['default_email'] ?? null;
        if (empty($email)) {
            return new \WP_Error('no_email', 'No email');
        }

        $user = get_user_by('email', $email);
        if (empty($user)) {
            $username = wp_generate_uuid4();
            if (function_exists('wc_create_new_customer')) {
                $user_id = wc_create_new_customer($email, $username);
            } else {
                $user_id = wp_create_user($username, wp_generate_password(), $email);
            }

            wp_send_new_user_notifications($user_id, 'admin');

            $user = get_user_by('id', $user_id);

            wp_update_user([
                'ID' => $user_id,
                'display_name' => $userData['display_name'] ?? $user->display_name,
                'first_name' => $userData['first_name'] ?? $user->first_name,
                'last_name' => $userData['last_name'] ?? $user->last_name,
            ]);
        }

        $meta = get_user_meta($user->ID, 'socialify', true);
        if ($meta) {
            $meta = [];
        }
        // $meta['yandex'] = [
        //     'access_token' => $access_token,
        //     'refresh_token' => $refresh_token,
        //     'expires_in' => $expires_in,
        // ];

        update_user_meta($user->ID, 'socialify', $meta);

        General::auth_user($user);

        wp_redirect($redirect_url);
        exit;
    }

    /**
     * @link https://yandex.ru/dev/id/doc/ru/codes/code-url
     */
    public static function request_code($client_id)
    {
        $url = 'https://oauth.yandex.ru/authorize';

        $state = $_GET['redirect'] ?? '';

        $url = add_query_arg([
            'response_type' => 'code',
            'state' => urlencode($state),
            'client_id' => $client_id,
        ], $url);

        wp_redirect($url);
        exit;
    }

    /**
     * @doc https://yandex.ru/dev/id/doc/ru/user-information
     */
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

    public static function get_config()
    {
        return get_option(Settings::$option_key)['yandex'] ?? [];
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
            <details>
                <summary>Help</summary>
                <ol>
                    <li>
                        <span><?= __('Get values: ', 'socialify') ?></span>
                        <a href="https://oauth.yandex.ru/" target="_blank">https://oauth.yandex.ru/</a>
                    </li>
                    <li>Callback URI: <code><?= self::$endpoint ?></code></li>
                    <li>Website: <code><?= site_url() ?></code></li>
                    <li>Domain: <code><?= $_SERVER['SERVER_NAME'] ?></code></li>
                </ol>
            </details>

            <?php
            },
            Settings::$settings_group
        );

        // register_setting(Settings::$settings_group, Settings::$option_key);

        self::add_setting_enable();
        self::add_setting_id();
        self::add_setting_secret();
        self::add_setting_show_button();
    }

    public static function get_section_id()
    {
        return self::$key . '_section';
    }

    public static function add_setting_enable()
    {
        add_settings_field(
            self::$key . '_enable',
            __('Enable', 'socialify'),
            function ($args) {
                printf(
                    '<input type="checkbox" name="%s" value="1" %s>',
                    $args['name'],
                    checked(1, $args['value'], false)
                );
            },
            Settings::$settings_group,
            self::get_section_id(),
            [
                'name' => Settings::$option_key . '[yandex][enable]',
                'value' => get_option(Settings::$option_key)['yandex']['enable'] ?? null,
            ]
        );
    }

    public static function add_setting_show_button()
    {
        if (!self::is_enabled()) {
            return;
        }
        add_settings_field(
            self::$key . '_show_button',
            __('Show button', 'socialify'),
            function ($args) {
                printf(
                    '<input type="checkbox" name="%s" value="1" %s>',
                    $args['name'],
                    checked(1, $args['value'], false)
                );
            },
            Settings::$settings_group,
            self::get_section_id(),
            [
                'name' => Settings::$option_key . '[yandex][show_button]',
                'value' => get_option(Settings::$option_key)['yandex']['show_button'] ?? null,
            ]
        );
    }

    public static function is_enabled()
    {
        return get_option(Settings::$option_key)['yandex']['enable'] ? true : false;
    }

    public static function add_setting_id()
    {
        if (!self::is_enabled()) {
            return;
        }
        add_settings_field(
            self::$key . '_id',
            __('Client ID', 'socialify'),
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


    public static function add_setting_secret()
    {
        if (!self::is_enabled()) {
            return;
        }
        add_settings_field(
            self::$key . '_secret',
            __('Client Secret', 'socialify'),
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


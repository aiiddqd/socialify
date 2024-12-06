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

        if(empty($appConfig['id']) || empty($appConfig['secret'])) {
            return new \WP_Error('no_config', 'No config');
        }


        $url = 'https://oauth.yandex.ru/authorize';

        $url = add_query_arg([
            'client_id' => $appConfig['id'],
            'response_type' => 'code',
        ], $url);

        if(empty($_GET['code'])){
            wp_redirect($url);
            exit;
        }



        $code = $_GET['code'] ?? null;
        if(empty($code)){    
            return new \WP_Error('no_code', 'No code');
        }

        $url = 'https://oauth.yandex.ru';

        $url = add_query_arg([
            'code' => $code,
            'grant_type' => 'authorization_code',
        ], $url);


        // var_dump($url); exit;

        $data = wp_remote_request($url, [
            'method' => 'POST',
            'headers' => [
                // 'Content-Type' => 'application/x-www-form-urlencoded',
                // 'Content-Length ' => strlen(http_build_query([
                //     'grant_type' => 'authorization_code',
                //     'code' => $code,
                // ])),
                // 'Authorization' => 'Basic ' . base64_encode($appConfig['id'] . ':' . $appConfig['secret']),
                // 'grant_type' => 'authorization_code',
                // 'code' => $code,
            ],
            
        ]);

        echo '<pre>';
        var_dump($data); exit;

        $url = add_query_arg('', $req->get_param(''), $url);
        // var_dump($req); exit;
        
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


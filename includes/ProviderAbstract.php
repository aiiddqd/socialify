<?php 

namespace Socialify;

abstract class ProviderAbstract implements ProviderInterface {

    public static $provider_name = '';

    public static $option_key = \Socialify\Settings\OPTION_KEY;
    public static $option_page = \Socialify\Settings\OPTION_PAGE;

    public static function init(){
        add_action('admin_init', [__CLASS__, 'add_settings']);

        add_action('rest_api_init', function () {
            register_rest_route('socialify/v1', self::$provider_name, [
                'methods' => 'GET',
                'callback' => [__CLASS__, 'api_handle'],
                'permission_callback' => '__return_true'
            ]);
        });
    }

    public static function get_config()
    {
        return \Socialify\Settings\get();
    }

    public static function get_callback()
    {
        return rest_url('socialify/v1/' . self::$provider_name);
    }

}

interface ProviderInterface {

    public static function init();

    public static function api_handle(\WP_REST_Request $req);
    public static function add_settings();

}
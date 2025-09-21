<?php

namespace Socialify;

use Exception;

defined('ABSPATH') || die();

abstract class AbstractProvider
{

    // Unique key of provider - used for saving settings and other things
    public static $key;

    /**
     * Redirect URI - after auth on provider side user will be redirected to this URI
     *
     * @var string
     */
    // protected static $redirect_uri = '';

    /**
     * The init
     */
    abstract public static function init(): void;

    /**
     * Allow the provider to be invoked as a function.
     */
    public function __invoke()
    {

        add_action('rest_api_init', [static::class, 'add_routes']);

        static::init();

        
    }

    public static function add_routes(){
        // add_action('rest_api_init', function () {
        //     register_rest_route('socialify/v1', '/'.static::getProviderKey(), [
        //         'methods' => 'GET',
        //         'callback' => [static::class, 'getUrlToAuth'],
        //         'permission_callback' => '__return_true',
        //     ]);
        // });
    }


    public static function saveDataToUserMeta($user_id, $data)
    {
        try {
            $data = (array) $data;
            update_user_meta($user_id, 'socialify_'.static::getProviderKey(), $data);
            update_user_meta($user_id, 'socialify_telegram', $data);
            update_user_meta($user_id, 'socialify_telegram_id_'.$data['identifier'], strtotime('now'));
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the logo URL
     */
    abstract public static function getProviderKey(): string;
    abstract public static function getProviderName(): string;

    
    abstract public static function getUrlToLogo(): string;
    abstract public static function getUrlToConnect(): string;

    abstract public static function getUrlToAuth(): string;
    abstract public static function is_enabled(): bool;
}

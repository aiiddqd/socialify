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

        // dd_only_admins(1); exit;

        // add_action('rest_api_init', [self::class, 'add_routes']);

        static::init();


    }

    public static function add_routes()
    {
        add_action('rest_api_init', function () {

            register_rest_route(
                route_namespace: 'socialify/',
                route: sprintf('%s-auth', static::getProviderKey()),
                args: [
                    'methods' => 'GET',
                    'callback' => [static::class, 'actionAuth'],
                    'permission_callback' => '__return_true',
                ]);
        });
    }


    public static function getProviderDataFromUserMeta($user_id)
    {
        return get_user_meta($user_id, 'socialify_'.static::getProviderKey(), true);
    }

    public static function getUserIdByIdFromProvider($provider_user_id)
    {
        $key = 'socialify_'.static::getProviderKey().'_id_'.$provider_user_id;
        $user_query = new \WP_User_Query(array(
            'meta_key' => $key,
            'meta_compare' => 'EXISTS',
        ));
        $users = $user_query->get_results();
        if (empty($users[0]->ID)) {
            return false;
        }

        return $users[0]->ID;
    }

    public static function deleteDataFromUserMeta($user_id)
    {
        try {
            if (empty($user_id) || empty(static::getProviderKey())) {
                return false;
            }

            //get all meta keys starts with socialify_{provider_key} and delete them
            $meta_keys = array_keys(get_user_meta($user_id));
            foreach ($meta_keys as $meta_key) {
                if (strpos($meta_key, 'socialify_'.static::getProviderKey()) === 0) {
                    delete_user_meta($user_id, $meta_key);
                }
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    public static function saveDataToUserMeta($user_id, $data)
    {
        try {
            $data = (array) $data;

            if (empty($user_id) || empty(static::getProviderKey()) || empty($data['identifier'])) {
                return false;
            }

            update_user_meta($user_id, 'socialify_'.static::getProviderKey(), $data);
            $provider_user_id_meta_key = 'socialify_'.static::getProviderKey().'_id_'.$data['identifier'];
            update_user_meta($user_id, $provider_user_id_meta_key, strtotime('now'));
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Get the logo URL
     */
    abstract public static function actionAuth();
    abstract public static function getProviderKey(): string;
    abstract public static function getProviderName(): string;


    abstract public static function getUrlToLogo(): string;
    abstract public static function getUrlToConnect(): string;

    abstract public static function getUrlToAuth(): string;
    abstract public static function is_enabled(): bool;
}

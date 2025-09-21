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
    public static function load()
    {
        add_action('rest_api_init', [static::class, 'add_routes']);

        add_action('admin_init', [static::class, 'add_settings']);

        static::init();
    }

    //get user id by otp nonce
    public static function getUserIdByNonce($nonce): ?int
    {
        $user_id = get_transient("socialify_connect_providers_nonce_$nonce");
        delete_transient("socialify_connect_providers_nonce_$nonce");
        if ($user_id) {
            return (int) $user_id;
        }
        return null;
    }

    public static function add_settings()
    {
        add_settings_section(
            id: self::getSectionId(),
            title: static::getProviderName(),
            callback: function () { ?>
            <details>
                <summary>Help</summary>
                <?php
                    if (method_exists(static::class, 'getInstructionsHtml')) {
                        static::getInstructionsHtml();
                    }
                    ?>
            </details>
            <?php
            },
            page: Settings::$settings_group
        );

        self::add_setting_fields();
    }

    public static function add_setting_fields()
    {
        add_settings_field(
            id: static::getProviderKey().'_enabled',
            title: __('Enable/Disable', 'socialify'),
            callback: function ($args) {
                printf(
                    '<input type="checkbox" name="%s" value="1" %s>',
                    $args['name'],
                    checked(1, $args['value'], false)
                );
            },
            page: Settings::$settings_group,
            section: self::getSectionId(),
            args: [
                'name' => Settings::$option_key.sprintf("[%s][enable]", static::getProviderKey()),
                'value' => get_option(Settings::$option_key)[static::getProviderKey()]['enable'] ?? null,
            ]
        );
    }

    public static function getSectionId()
    {
        return 'socialify_'.static::getProviderKey().'_section';
    }

    //get option
    public static function getOption($key)
    {
        $options = get_option(Settings::$option_key);
        return $options[static::getProviderKey()][$key] ?? null;
    }

    public static function isEnabled(): bool
    {
        $options = get_option(Settings::$option_key);
        return ! empty($options[static::getProviderKey()]['enable']);
    }

    /**
     * Get the logo URL
     */
    abstract public static function actionAuth();
    abstract public static function getInstructionsHtml(): void;
    abstract public static function actionConnect();
    abstract public static function getProviderKey(): string;
    abstract public static function getProviderName(): string;

    abstract public static function getUrlToLogo(): string;

    public static function redirectAfterAuth()
    {
        $redirect_url = site_url();
        $redirect_to = $_GET['_redirect_to'] ?? '';
        if (isset($redirect_to) && filter_var($redirect_to, FILTER_VALIDATE_URL)) {
            $redirect_url = $redirect_to;
        }
        wp_redirect($redirect_url);
        exit;
    }

    //get nonce from url
    public static function getNonceFromUrl(): ?string
    {
        return esc_attr($_GET['nonce']) ?? null;
    }

    public static function getUrlToConnect(): string
    {
        $url = rest_url(sprintf('socialify/%s-connect', static::getProviderKey()));

        return $url;
    }
    public static function getUrlToAuth(): string
    {
        global $wp;
        $redirect_to = esc_url($_GET['_redirect_to'] ?? home_url($wp->request));
        $url = rest_url(sprintf('socialify/%s-auth', static::getProviderKey()));
        $url = add_query_arg([
            '_redirect_to' => $redirect_to,
        ], $url);
        return $url;
    }

    public static function setCurrentUser(\WP_User $user)
    {
        wp_set_current_user($user->ID);
        wp_set_auth_cookie($user->ID, true);
        do_action('wp_login', $user->user_login, $user);
    }

    public static function add_routes()
    {
        register_rest_route(
            'socialify/',
            route: sprintf('%s-auth', static::getProviderKey()),
            args: [
                'methods' => 'GET',
                'callback' => [static::class, 'actionAuth'],
                'permission_callback' => '__return_true',
            ]);
        register_rest_route(
            'socialify/',
            route: sprintf('%s-connect', static::getProviderKey()),
            args: [
                'methods' => 'GET',
                'callback' => [static::class, 'actionConnect'],
                'permission_callback' => '__return_true',
            ]);
    }


    public static function getProviderDataFromUserMeta($user_id)
    {
        return get_user_meta($user_id, 'socialify_'.static::getProviderKey(), true);
    }

    public static function authenticateByProviderProfile($providerProfile)
    {
        $user = self::getUserByIdFromProvider($providerProfile->identifier);
        if (empty($user)) {
            $user = self::tryRegisterUserByProviderProfile($providerProfile);
        }
        if ($user) {
            self::setCurrentUser($user);
            return $user;
        }

        wp_die(__('Authentication failed.', 'socialify'));
    }

    public static function tryRegisterUserByProviderProfile($providerProfile)
    {
        $email = $providerProfile->email ?? null;
        if (empty($email)) {
            wp_die(__('Email not provided by provider. Cannot register user without email.', 'socialify'));
        }
        if (email_exists($email)) {
            return get_user_by('email', $email);
        }

        //check is registration allowed
        if (get_option('users_can_register') != 1) {
            // wp_die(__('User registration is disabled. Please contact the site administrator.', 'socialify'));
        }

        $username = sanitize_user($providerProfile->displayName ?? ($providerProfile->firstName ?? 'user'), true);
        if (username_exists($username)) {
            $username .= rand(1000, 9999);
        }
        if (empty($username)) {
            $username = 'user'.rand(1000, 9999);
        }

        $random_password = wp_generate_password(12, false);
        $user_id = wp_create_user($username, $random_password, $email);
        if (is_wp_error($user_id)) {
            wp_die($user_id->get_error_message());
        }

        //update user meta with provider data
        self::saveDataToUserMeta($user_id, data: $providerProfile);

        //update user by $providerProfile
        wp_update_user([
            'ID' => $user_id,
            'first_name' => $providerProfile->firstName ?? '',
            'last_name' => $providerProfile->lastName ?? '',
            'display_name' => $providerProfile->displayName ?? '',
            'user_nicename' => sanitize_title($providerProfile->displayName ?? $username),
        ]);

        //send notification to user with password
        // wp_new_user_notification($user_id, null, 'both');

        return get_user_by('id', $user_id);

    }

    public static function getUserByIdFromProvider($provider_user_id)
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

        return $users[0];
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
}

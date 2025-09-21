<?php

namespace Socialify;

use Hybridauth\Hybridauth;
use Hybridauth\Exception\Exception;

defined('ABSPATH') || die();

add_filter('socialify_providers', function ($providers) {
    $providers[TelegramProvider::$key] = TelegramProvider::class;
    return $providers;
}, 33);

class TelegramProvider extends AbstractProvider
{
    public static $key = 'telegram';

    public static function init(): void
    {


        // rest route for connect telegram auth
        // example route url http://aappss.ru/wp-json/socialify/v1/telegram-connect
        add_action('rest_api_init', function () {
            register_rest_route('socialify/v1', '/telegram-connect', [
                'methods' => 'GET',
                'callback' => [self::class, 'handleConnect'],
                'permission_callback' => '__return_true',
            ]);
        });


        add_action('admin_init', [self::class, 'addSettings']);


    }


    //handle connect
    public static function handleConnect()
    {
        $callbackUrl = rest_url('socialify/v1/telegram-connect');
        $redirect_to = esc_url($_GET['_redirect_to'] ?? null);
        if ($redirect_to) {
            $callbackUrl = add_query_arg('_redirect_to', $redirect_to, $callbackUrl);
        }
        $nonce = $_GET['nonce'] ?? '';
        $callbackUrl = add_query_arg('nonce', $nonce, $callbackUrl);

        if (empty($nonce)) {
            wp_die(__('Invalid or expired nonce.', 'socialify'));
        }

        $userProfile = self::authAndGetUserProfile($callbackUrl);

        $user_id = get_transient('telegram_otp_'.$nonce);
        delete_transient('telegram_otp_'.$nonce);

        if (empty($user_id)) {
            wp_die(__('Invalid or expired nonce.', 'socialify'));
        }
        // dd($user_id); exit;

        self::saveDataToUserMeta($user_id, data: $userProfile);

        $redirect_to = $_GET['_redirect_to'] ?? home_url();
        $redirect_url = esc_url_raw($redirect_to);

        wp_redirect($redirect_url);
        exit;
    }


    public static function actionAuth()
    {
        $callbackUrl = rest_url('socialify/telegram-auth');
        $redirect_to = esc_url($_GET['_redirect_to'] ?? site_url());
        if ($redirect_to) {
            $callbackUrl = add_query_arg('_redirect_to', $redirect_to, $callbackUrl);
        }
        $userProfile = self::authAndGetUserProfile($callbackUrl);

        $user = self::getUserByIdFromProvider($userProfile->identifier);

        //auth user by id
        if (empty($user)) {
            wp_die(__('Пользователь не найден. Вам нужно сначала подключить Телеграм к одному из существующих пользователей.', 'socialify'));
        }

        Plugin::auth_user($user);
        wp_redirect($redirect_to);
        exit;

        // return self::handleConnect();
    }

    public static function addSettings()
    {
        add_settings_section(
            id: self::get_section_id(),
            title: self::getProviderName(),
            callback: function () { ?>
            <details>
                <summary>Help</summary>
                <ol>
                    <li>
                        <span><?= __('Get values: ', 'socialify') ?></span>
                        <a href="https://core.telegram.org/bots/features#web-login" target="_blank">Instructions</a>
                    </li>
                    <li>Website: <code><?= site_url() ?></code></li>
                    <li>Domain: <code><?= $_SERVER['SERVER_NAME'] ?></code></li>
                </ol>
            </details>
            <?php
            },
            page: Settings::$settings_group
        );

        self::add_setting_fields();
        // self::add_setting_id();
        // self::add_setting_secret();

    }

    public static function add_setting_fields()
    {
        add_settings_field(
            id: self::$key.'_enabled',
            title: __('Enable/Disable', 'socialify'),
            callback: function ($args) {
                printf(
                    '<input type="checkbox" name="%s" value="1" %s>',
                    $args['name'],
                    checked(1, $args['value'], false)
                );
            },
            page: Settings::$settings_group,
            section: self::get_section_id(),
            args: [
                'name' => Settings::$option_key.'[telegram][enable]',
                'value' => get_option(Settings::$option_key)['telegram']['enable'] ?? null,
            ]
        );

        add_settings_field(
            id: self::$key.'_id',
            title: __('Telegram Bot ID/Name', 'socialify'),
            callback: function ($args) {
                printf(
                    '<input type="text" name="%s" value="%s" class="regular-text">',
                    $args['name'],
                    esc_attr($args['value'])
                );
            },
            page: Settings::$settings_group,
            section: self::get_section_id(),
            args: [
                'name' => Settings::$option_key.'[telegram][id]',
                'value' => get_option(Settings::$option_key)['telegram']['id'] ?? null,
            ]
        );

        add_settings_field(
            id: self::$key.'_secret',
            title: __('Telegram Bot Secret', 'socialify'),
            callback: function ($args) {
                printf(
                    '<input type="text" name="%s" value="%s" class="regular-text">',
                    $args['name'],
                    esc_attr($args['value'])
                );
            },
            page: Settings::$settings_group,
            section: self::get_section_id(),
            args: [
                'name' => Settings::$option_key.'[telegram][secret]',
                'value' => get_option(Settings::$option_key)['telegram']['secret'] ?? null,
            ]
        );


    }

    public static function get_section_id()
    {
        return self::$key.'_section';
    }


    public static function authAndGetUserProfile($callbackUrl)
    {
        try {

            $config = [
                'callback' => $callbackUrl,
                'keys' => [
                    'id' => self::get_config()['id'] ?? '',
                    'secret' => self::get_config()['secret'] ?? '',
                ],
            ];


            $adapter = new \Hybridauth\Provider\Telegram($config);

            //starter step with widget JS
            if (empty($_GET['hash'])) {
                header('Content-Type: text/html; charset=utf-8');
                // exit;
            }
            $adapter->authenticate();


            $userProfile = $adapter->getUserProfile();

            return $userProfile;
        } catch (Exception $e) {
            echo 'Authentication failed: '.$e->getMessage();
            return null;
        }

    }

    public static function getUrlToLogo(): string
    {
        return plugins_url('assets/telegram.svg', dirname(__FILE__));
    }

    public static function get_config()
    {
        return get_option(Settings::$option_key)[self::getProviderKey()] ?? [];
    }

    public static function getProviderKey(): string
    {
        return self::$key;
    }

    public static function getProviderName(): string
    {
        return 'Telegram';
    }

    public static function getUrlToConnect(): string
    {
        global $wp;
        $url = rest_url('socialify/v1/telegram-connect');
        $user_id = get_current_user_id();

        $redirect_to = $_GET['_redirect_to'] ?? home_url($wp->request);
        $nonce = wp_create_nonce('telegram_connect');
        set_transient('telegram_otp_'.$nonce, $user_id, 5 * MINUTE_IN_SECONDS);
        $url = add_query_arg([
            '_redirect_to' => $redirect_to,
            'nonce' => $nonce
        ], $url);
        return $url;
    }

    public static function getUrlToAuth(): string
    {
        global $wp;
        $url = rest_url('socialify/telegram-auth');
        $user_id = get_current_user_id();

        $redirect_to = $_GET['_redirect_to'] ?? home_url($wp->request);
        $url = add_query_arg([
            '_redirect_to' => $redirect_to,
        ], $url);
        return $url;
    }

    public static function is_enabled(): bool
    {
        return get_option(Settings::$option_key)[self::getProviderKey()]['enable'] ? true : false;
    }
}

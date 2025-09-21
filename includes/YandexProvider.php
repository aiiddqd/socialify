<?php

namespace Socialify;

defined('ABSPATH') || die();

add_filter('socialify_providers', function ($providers) {
    $providers[] = YandexProvider::class;
    return $providers;
});

final class YandexProvider extends AbstractProvider
{
    public static $key = 'yandex';



    public static function init(): void
    {

        add_action('admin_init', [self::class, 'additionalSettings']);

    }


    public static function actionConnect()
    {

        $config = [
            'id' => self::getOption('id'),
            'secret' => self::getOption('secret'),
        ];

        if (! self::isEnabled()) {
            wp_die('Yandex provider is not enabled');
        }

        if (empty($config['id']) || empty($config['secret'])) {
            wp_die('No config - you need to set Client ID and Secret in plugin settings');
        }

        $nonce = self::getNonceFromUrl();
        if (empty($nonce)) {
            wp_die('Invalid nonce');
        }

        $callbackUrl = self::getUrlToConnect();
        $callbackUrl = add_query_arg('nonce', $nonce, $callbackUrl);

        if (empty($_GET['code'])) {
            self::request_code($config['id'], $callbackUrl);
        }

        $userProfile = self::getUserProfile();
        if (empty($userProfile)) {
            wp_die('Failed to get user profile from Yandex');
        }

        $user_id = self::getUserIdByNonce($nonce);

        $state = $_GET['state'] ?? '';
        if (isset($state) && filter_var($state, FILTER_VALIDATE_URL)) {
            $redirect_to = $state;
        }

        if (empty($user_id)) {
            wp_die('Invalid or expired nonce');
        }

        self::saveDataToUserMeta($user_id, data: $userProfile);
        wp_redirect($redirect_to);
        exit;
    }

    public static function actionAuth()
    {
        try {
            if (! self::isEnabled()) {
                throw new \Exception('Yandex not enabled');
            }

            $appConfig = self::get_config();

            if (empty($_GET['code'])) {
                self::request_code($appConfig['id'], self::getUrlToAuth());
            }

            $getUserData = function ($appConfig) {
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
                        'Authorization' => 'Basic '.base64_encode($appConfig['id'].':'.$appConfig['secret']),
                    ],

                ]);

                $token_data = json_decode(wp_remote_retrieve_body($response), true);

                $access_token = $token_data['access_token'] ?? null;
                if (empty($access_token)) {
                    self::redirectAfterAuth();
                }

                $userData = self::get_user_data($access_token);
                if ($userData) {
                    // return $userData;
                    $userProfile = new \Hybridauth\User\Profile();

                    $userProfile->email = $userData['default_email'];
                    $userProfile->identifier = $userData['id'];
                    $userProfile->firstName = $userData['first_name'] ?? null;
                    $userProfile->lastName = $userData['last_name'] ?? null;
                    $userProfile->displayName = $userData['display_name'] ?? null;
                    $userProfile->gender = $userData['sex'] ?? null;

                    return $userProfile;
                }

                return null;

            };

            $userProfile = $getUserData($appConfig);
            $user = self::authenticateByProviderProfile($userProfile);

            self::redirectAfterAuth();

        } catch (\Exception $e) {
            wp_die($e->getMessage());
        }
    }

    // get user profile
    public static function getUserProfile()
    {
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
                'Authorization' => 'Basic '.base64_encode(self::getOption('id').':'.self::getOption('secret')),
            ],

        ]);

        $token_data = json_decode(wp_remote_retrieve_body($response), true);

        $access_token = $token_data['access_token'] ?? null;
        if (empty($access_token)) {
            self::redirectAfterAuth();
        }

        $userData = self::get_user_data($access_token);
        if ($userData) {
            // return $userData;
            $userProfile = new \Hybridauth\User\Profile();

            $userProfile->email = $userData['default_email'];
            $userProfile->identifier = $userData['id'];
            $userProfile->firstName = $userData['first_name'] ?? null;
            $userProfile->lastName = $userData['last_name'] ?? null;
            $userProfile->displayName = $userData['display_name'] ?? null;
            $userProfile->gender = $userData['sex'] ?? null;

            return $userProfile;
        }

        return null;

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
        } catch (\Exception $e) {
            echo 'Authentication failed: '.$e->getMessage();
            return null;
        }

    }

    public static function getUrlToLogo(): string
    {
        // Replace with the actual logo URL if available
        return plugins_url('assets/yandex.png', dirname(__FILE__));
    }

    public static function getProviderKey(): string
    {
        return self::$key;
    }
    public static function getProviderName(): string
    {
        return 'Yandex';
    }

    /**
     * @link https://yandex.ru/dev/id/doc/ru/codes/code-url
     */
    public static function request_code($client_id, $callbackUrl = null)
    {
        $url = 'https://oauth.yandex.ru/authorize';

        $state = $_GET['_redirect_to'] ?? '';

        $args = [
            'response_type' => 'code',
            'state' => urlencode($state),
            'client_id' => $client_id,
        ];

        if ($callbackUrl) {
            $args['redirect_uri'] = $callbackUrl;
        }

        $url = add_query_arg($args, $url);

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
                'Authorization' => 'OAuth '.$access_token,
            ],
        ]);

        return json_decode(wp_remote_retrieve_body($response), true);
    }

    public static function get_config()
    {
        return get_option(Settings::$option_key)['yandex'] ?? [];
    }

    public static function getInstructionsHtml()
    {
        ?>
        <p>To get Google Client ID and Secret, follow these steps:</p>
        <ol>
            <li>
                <span><?= __('Get values: ', 'socialify') ?></span>
                <a href="https://oauth.yandex.ru/" target="_blank">https://oauth.yandex.ru/</a>
            </li>
            <li>Callback URI for Auth: <code><?= self::getUrlToAuth() ?></code></li>
            <li>Callback URI for Connect: <code><?= self::getUrlToConnect() ?></code></li>
            <li>Website: <code><?= site_url() ?></code></li>
            <li>Domain: <code><?= $_SERVER['SERVER_NAME'] ?></code></li>
        </ol>
        <?php
    }

    public static function additionalSettings()
    {
        add_settings_field(
            self::$key.'_id',
            __('Client ID', 'socialify'),
            $callback = function ($args) {
                printf(
                    '<input type="text" name="%s" value="%s" size="77">',
                    $args['name'],
                    $args['value']
                );
            },
            Settings::$settings_group,
            self::getSectionId(),
            [
                'name' => Settings::$option_key.'[yandex][id]',
                'value' => get_option(Settings::$option_key)['yandex']['id'] ?? null,
            ]
        );

        add_settings_field(
            id: self::$key.'_secret',
            title: __('Client Secret', 'socialify'),
            callback: function ($args) {
                printf(
                    '<input type="text" name="%s" value="%s" size="77">',
                    $args['name'],
                    $args['value']
                );
            },
            page: Settings::$settings_group,
            section: self::getSectionId(),
            args: [
                'name' => Settings::$option_key.'[yandex][secret]',
                'value' => get_option(Settings::$option_key)['yandex']['secret'] ?? null,
            ]
        );
    }


    public static function is_enabled(): bool
    {
        return self::isEnabled();
    }

}

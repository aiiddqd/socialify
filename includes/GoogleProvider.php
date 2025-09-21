<?php
namespace Socialify;

defined('ABSPATH') || die();

add_filter('socialify_providers', function ($providers) {
    $providers[GoogleProvider::getProviderKey()] = GoogleProvider::class;
    return $providers;
});

class GoogleProvider extends AbstractProvider
{
    public static function init(): void
    {
        add_action('admin_init', [self::class, 'additionalSettings']);
    }
    
    public static function getInstructionsHtml(): void
    {
        ?>
        <p>To get Google Client ID and Secret, follow these steps:</p>
        <ol>
            <li>Go to the <a href="https://console.developers.google.com/" target="_blank" rel="noopener">Google Developers Console</a>.</li>
            <li>Create a new project or select an existing one.</li>
            <li>Navigate to "OAuth consent screen" and configure your app details.</li>
            <li>Go to "Credentials" and click on "Create Credentials" > "OAuth 2.0 Client IDs".</li>
            <li>Select "Web application" as the application type.</li>
            <li>Add the following URL to the "Authorized redirect URIs":
                <pre>'.esc_html(self::getUrlToAuth()).'</pre>
            </li>
            <li>Click "Create" to generate your Client ID and Secret.</li>
            <li>Copy the Client ID and Secret and paste them into the fields below.</li>
        </ol>
        <?php
    }

    public static function additionalSettings()
    {
        add_settings_field(
            id: self::getProviderKey().'_id',
            title: __('Google Client ID', 'socialify'),
            callback: function ($args) {
                printf(
                    '<input type="text" name="%s" value="%s" class="regular-text">',
                    $args['name'],
                    esc_attr($args['value'])
                );
            },
            page: Settings::$settings_group,
            section: self::getSectionId(),
            args: [
                'name' => Settings::$option_key.sprintf("[%s][id]", static::getProviderKey()),
                'value' => get_option(Settings::$option_key)[static::getProviderKey()]['id'] ?? null,
            ]
        );

        add_settings_field(
            id: self::getProviderKey().'_secret',
            title: __('Google Secret', 'socialify'),
            callback: function ($args) {
                printf(
                    '<input type="text" name="%s" value="%s" class="regular-text">',
                    $args['name'],
                    esc_attr($args['value'])
                );
            },
            page: Settings::$settings_group,
            section: self::getSectionId(),
            args: [
                'name' => Settings::$option_key.sprintf("[%s][secret]", static::getProviderKey()),
                'value' => get_option(Settings::$option_key)[static::getProviderKey()]['secret'] ?? null,
            ]
        );
    }

    public static function actionAuth()
    {
        $callbackUrl = self::getUrlToAuth();

        $redirect_to = esc_url_raw($_GET['_redirect_to']) ?? home_url();

        $config = [
            'callback' => $callbackUrl,
            'keys' => [
                'id' => self::getOption('id'),
                'secret' => self::getOption('secret'),
            ],
            'scope' => 'https://www.googleapis.com/auth/userinfo.profile',
            'authorize_url_parameters' => [
                // 'approval_prompt' => 'force',
                // 'access_type' => 'offline', // default is 'offline'
                // 'hd' => '', // set if needed
                // 'state' => $redirect_to, // set if needed
                // add other parameters as needed
            ],
        ];

        $adapter = new \Hybridauth\Provider\Google($config);
        $adapter->authenticate();

        $userProfile = $adapter->getUserProfile();

        $user = self::authenticateByProviderProfile($userProfile);

        if (empty($user)) {
            wp_die(__('Пользователь не найден. Вам нужно сначала подключить Телеграм к одному из существующих пользователей.', 'socialify'));
        }

        // Plugin::auth_user($user);
        self::redirectAfterAuth();
        // exit;
    }

    public static function actionConnect()
    {

        $callbackUrl = self::getUrlToConnect();
        $nonce = esc_attr($_GET['nonce']) ?? '';
        if (empty($nonce)) {
            wp_die('Invalid nonce');
        }

        $config = [
            'callback' => $callbackUrl,
            'keys' => [
                'id' => self::getOption('id'),
                'secret' => self::getOption('secret'),
            ],
            'scope' => 'https://www.googleapis.com/auth/userinfo.profile',
            'authorize_url_parameters' => [
                // 'approval_prompt' => 'force',
                // 'access_type' => 'offline', // default is 'offline'
                // 'hd' => '', // set if needed
                'state' => $nonce, // set if needed
                // add other parameters as needed
            ],
        ];


        $adapter = new \Hybridauth\Provider\Google($config);
        $adapter->authenticate();

        $userProfile = $adapter->getUserProfile();

        $nonce = sanitize_text_field($_GET['nonce']) ?? '';
        $user_id = self::getUserIdByNonce($nonce);

        if (empty($user_id)) {
            wp_die('Invalid or expired nonce');
        }

        self::saveDataToUserMeta($user_id, data: $userProfile);
        $redirect_to = $_GET['_redirect_to'] ?? home_url();
        $redirect_url = esc_url_raw($redirect_to);
        wp_redirect($redirect_url);
        exit;
    }

    public static function getProviderKey(): string
    {
        return 'google';
    }

    public static function getProviderName(): string
    {
        return 'Google';
    }

    public static function getUrlToLogo(): string
    {
        return 'https://www.google.com/favicon.ico';
    }
}
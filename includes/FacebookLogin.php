<?php
namespace Socialify;
defined('ABSPATH') || die();

final class FacebookLogin
{
    public static $data = [
        'settings_section_title' => 'Facebook Login',
        'settings_section_key' => 'socialify_config_facebook_section',
        'setting_title_id' => 'Facebook ID',
        'setting_title_secret' => 'Facebook Secret',
    ];

    public static $option_name = 'socialify_config_facebook';

    public static $endpoint = '/socialify/Facebook/';

    public static function init()
    {
        self::$endpoint = site_url(self::$endpoint);

        add_action('admin_init', [__CLASS__, 'add_settings']);
        add_filter('socialify_user_profile', [__CLASS__, 'auth_handler'], 11, 2);

        add_filter('socialify_shortcode_data', [__CLASS__, 'add_btn_for_shortcode']);
    }

    public static function add_btn_for_shortcode($data)
    {
        $data['login_items']['fb'] = [
            'url' => self::$endpoint,
            'ico_url' => General::$plugin_dir_url . 'assets/svg/facebook.svg',
        ];
        return $data;
    }

    public static function auth_handler($userProfile, $endpoint)
    {

        if ('Facebook' != $endpoint) {
            return $userProfile;
        }

        $config_data = get_option(self::$option_name);
        if (empty($config_data['id']) || empty($config_data['secret'])) {
            return $userProfile;
        }

        $config = [
            'callback' => self::$endpoint,
            //Facebook application credentials
            'keys'     => [
                'id'     => $config_data['id'], //Required: your Facebook application id
                'secret' => $config_data['secret']  //Required: your Facebook application secret
            ]
        ];

        //Instantiate Facebook's adapter directly
        $adapter = new \Hybridauth\Provider\Facebook($config);

        //Attempt to authenticate the user with Facebook
        $adapter->authenticate();

        //Retrieve the user's profile
        $userProfile = $adapter->getUserProfile();

        //Disconnect the adapter
        $adapter->disconnect();

        return $userProfile;
    }

    /**
     * Add settings
     */
    public static function add_settings()
    {
        add_settings_section(
            $section_id = self::$data['settings_section_key'],
            $header = self::$data['settings_section_title'],
            $callback = [__CLASS__, 'render_settings_instructions'],
            General::$settings_group
        );

        register_setting(General::$settings_group, self::$option_name);

        self::add_setting_id();
        self::add_setting_secret();
    }

    /**
     * render_settings_instructions
     */
    public static function render_settings_instructions(){
        ?>

        <ol>
            <li>
                <span><?= __('Получить реквизиты для доступа можно по ссылке: ', 'socialify') ?></span>
                '<a href="https://developers.facebook.com/apps/" target="_blank">https://developers.facebook.com/apps/</a>'
            </li>
            <li>В поле Callback URI запишите: <code><?= self::$endpoint ?></code></li>
            <li>Ссылка на сайт: <code><?= site_url() ?></code></li>
            <li>Домен если потребуется: <code><?= $_SERVER['SERVER_NAME'] ?></code></li>
        </ol>
        <?php
    }

    /**
     * add_setting_id
     *
     * input name: socialify_config_facebook[id]
     */
    public static function add_setting_id()
    {
        add_settings_field(
            $setting_id = self::$option_name . '_id',
            $setting_title = self::$data['setting_title_id'],
            $callback = function ($args) {
                printf(
                    '<input type="text" name="%s" value="%s" >',
                    $args['name'], $args['value']
                );
            },
            $page = General::$settings_group,
            $section_id = self::$data['settings_section_key'],
            $args = [
                'name'  => self::$option_name . '[id]',
                'value' => @get_option(self::$option_name)['id'],
            ]
        );
    }

    /**
     * add_setting_secret
     *
     * input name: socialify_config_facebook[secret]
     */
    public static function add_setting_secret()
    {
        $setting_title = 'Facebook Secret';
        $setting_id    = General::$slug . '_facebook_secret';
        add_settings_field(
            $setting_id,
            $setting_title,
            $callback = function ($args) {
                printf(
                    '<input type="text" name="%s" value="%s" >',
                    $args['name'], $args['value']
                );
            },
            $page = General::$settings_group,
            $section_id = self::$data['settings_section_key'],
            $args = [
                'name'  => self::$option_name . '[secret]',
                'value' => @get_option(self::$option_name)['secret'],
            ]
        );
    }
}

FacebookLogin::init();
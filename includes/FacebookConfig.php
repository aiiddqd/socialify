<?php
namespace Socialify;
defined('ABSPATH') || die();

class FacebookConfig
{
    public static $option_name = General::$slug . '_config_facebook';
    public static $section_settings_key = '';

    public static function init()
    {
        add_action('admin_init', [__CLASS__, 'add_settings']);
        add_filter('socialify_user_profile', [__CLASS__, 'auth_handler'], 11, 2);
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
            //Location where to redirect users once they authenticate with Facebook
            //For this example we choose to come back to this same script
            'callback' => site_url('/socialify/Facebook/'),

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
        self::$section_settings_key = self::$option_name . '_section';
        $instrunction = __('Получить реквизиты для доступа на странице Facebook', 'socialify');
        add_settings_section(
            self::$section_settings_key,
            $header = 'Facebook',
            function () { ?>
                <div>
                    <p>
                        <a href="https://developers.facebook.com/apps/" target="_blank"><?= $instrunction ?></a>
                    </p>
                </div>
                <?php
            },
            General::$settings_group
        );
        register_setting(General::$settings_group, self::$option_name);

        self::add_setting_id();
        self::add_setting_secret();
    }


    public static function add_setting_facebook_id()
    {
        $setting_title = 'Facebook ID';
        $setting_id    = General::$slug . '_facebook_id';
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
            self::$section_settings_key,
            $args = [
                'name'  => self::$option_name . '[id]',
                'value' => @get_option(self::$option_name)['id'],
            ]
        );
    }

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
            self::$section_settings_key,
            $args = [
                'name'  => self::$option_name . '[secret]',
                'value' => @get_option(self::$option_name)['secret'],
            ]
        );
    }
}

FacebookConfig::init();
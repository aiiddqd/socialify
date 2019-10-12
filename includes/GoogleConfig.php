<?php
namespace Socialify;
defined('ABSPATH') || die();

/**
 * Google Config
 */
class GoogleConfig
{
    public static $option_name = General::$slug . '_config_google';

    public static $endpoint = site_url('/socialify/Google/');

    public static $data = [
        'settings_section_insstruction' => __('Получить реквизиты для доступа можно по ссылке: ', 'socialify'),
        'settings_section_title' => 'Google',
        'setting_title_id' => 'Google ID',
        'setting_title_secret' => 'Google Secret',
    ];

    public static function init(){
        add_action('admin_init', [__CLASS__, 'add_settings']);
        add_filter('socialify_user_profile', [__CLASS__, 'auth_handler'], 11, 2);
    }

    public static function auth_handler($userProfile, $endpoint)
    {
        if('Google' != $endpoint){
            return $userProfile;
        }

        if(!$config = self::get_config()){
            return $userProfile;
        }

        $adapter = new \Hybridauth\Provider\Google($config);

        //Attempt to authenticate the user with Facebook
        if($accessToken = $adapter->getAccessToken()){
            $adapter->setAccessToken($accessToken);
        }

        $adapter->authenticate();

        //Retrieve the user's profile
        $userProfile = $adapter->getUserProfile();

        //Disconnect the adapter
        $adapter->disconnect();

        return $userProfile;
    }

    public static function get_config()
    {
        $config_data = get_option(self::$option_name);
        if(empty($config_data['id']) || empty($config_data['secret'])){
            return false;
        }

        $config = [
            'callback' => self::$endpoint,
            'keys' => [ 'id' => $config_data['id'], 'secret' => $config_data['secret'] ],
//            'scope'    => 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email',
            'authorize_url_parameters' => [
                'approval_prompt' => 'force', // to pass only when you need to acquire a new refresh token.
                'access_type'     => 'offline',
            ],
//            'debug_mode' => 'debug',
//            'debug_file' => __FILE__ . '.log',
        ];

        return $config;
    }

    /**
     * Add settings
     */
    public static function add_settings()
    {
        add_settings_section(
            $section_id = self::$option_name,
            $section_title = self::$data['settings_section_title'],
            function(){ 
                ?>
                <div>
                    <span><?= self::$data['settings_section_insstruction'] ?></span>
                    <a href="https://console.developers.google.com/apis/credentials/" target="_blank">Google Dev Console https://console.developers.google.com/apis/credentials</a>
                </div>
                <?php
            },
            General::$settings_group
        );
        register_setting(General::$settings_group, self::$option_name);

        self::add_setting_id();
        self::add_setting_secret();
    }


    public static function add_setting_id(){
        $setting_title = self::$data['setting_title_id'];
        $setting_id = General::$slug . '_google_id';
        add_settings_field(
            $setting_id,
            $setting_title,
            $callback = function($args){
                printf(
                    '<input type="text" name="%s" value="%s" size="77">',
                    $args['name'], $args['value']
                );
            },
            $page = General::$settings_group,
            $section = self::$option_name,
            $args = [
                'name' => self::$option_name . '[id]',
                'value' => @get_option(self::$option_name)['id'],
            ]
        );
    }

    public static function add_setting_secret(){
        $setting_title = self::$data['setting_title_secret'];
        $setting_id = General::$slug . '_google_secret';
        add_settings_field(
            $setting_id,
            $setting_title,
            $callback = function($args){
                printf(
                    '<input type="text" name="%s" value="%s" size="77">',
                    $args['name'], $args['value']
                );
            },
            $page = General::$settings_group,
            $section = self::$option_name,
            $args = [
                'name' => self::$option_name . '[secret]',
                'value' => @get_option(self::$option_name)['secret'],
            ]
        );
    }
}

GoogleConfig::init();
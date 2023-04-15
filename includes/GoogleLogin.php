<?php
namespace Socialify;

defined('ABSPATH') || die();

const PROVIDER_KEY = 'google';

add_action('admin_init', __NAMESPACE__ . '\\add_settings');

// wp-json/socialify/v1/google/
add_action('rest_api_init', function () {
    register_rest_route('socialify/v1', PROVIDER_KEY, [
        'methods' => 'GET',
        'callback' => function ( \WP_REST_Request $req ) {
            $config = get_config();
            if (empty($config)) {
                return new \WP_Error( '502', esc_html__( 'Config error', 'socialify' ), array( 'status' => 502 ) );
            }
            try{
                $adapter = new \Hybridauth\Provider\Google($config);
                if (!empty($_REQUEST['redirect_to'])) {
                    $redirect_to = $_REQUEST['redirect_to'];
                    $adapter->getStorage()->set('socialify_redirect_to', $redirect_to);
                }


                if ($accessToken = $adapter->getAccessToken()) {
                    $adapter->setAccessToken($accessToken);
                }
                $adapter->authenticate();

                $data = [
                    'user_profile' => $adapter->getUserProfile(),
                    'redirect_to' => $adapter->getStorage()->get('socialify_redirect_to'),
                ];

                $adapter->disconnect();

                do_action('socialify_auth_handle', $data['user_profile'], PROVIDER_KEY, $data['redirect_to']);

                //Set process data
                // $auth_process_data['user_data'] = $adapter->getUserProfile();
                // $auth_process_data['redirect_to'] = $adapter->getStorage()->get('socialify_redirect_to');
                // $auth_process_data['provider'] = 'google';
    
                //Disconnect the adapter & destroy session
    
                return json_encode(["message" => "Authorized skiped"]);
    
            }
            catch(\Exception $e){
                return new \WP_Error( '502', $e->getMessage(), array( 'status' => 502 ) );
            }
        },
        'permission_callback' => '__return_true'
    ]);
});

// add_filter('socialify_auth_process', __NAMESPACE__ . '\\handle', 11, 2);

function get_config()
{
    $options = \Socialify\Settings\get();

    if (empty($options['google_client_id']) || empty($options['google_client_secret'])) {
        return false;
    }

    $config = [
        'callback' => get_endpoint(),
        'keys' => [
            'id' => $options['google_client_id'] ?? null,
            'secret' => $options['google_client_secret'] ?? null,
        ],
        //'scope'    => 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email',
        'authorize_url_parameters' => [
            'approval_prompt' => 'force',
            'access_type' => 'offline',
            // to pass only when you need to acquire a new refresh token.
        ],
        //'debug_mode' => 'debug',
        //'debug_file' => __FILE__ . '.log',
    ];


    return $config;
}


function get_auth_url_with_redirect_to_current_page(){
    global $wp;
    return add_query_arg('redirect_to', home_url( $wp->request), get_endpoint());
}

function get_endpoint()
{
    return rest_url('socialify/v1/' . PROVIDER_KEY);
}


// function handle($auth_process_data, $endpoint)
// {

//     if ('Google' != $endpoint) {
//         return $auth_process_data;
//     }

//     if (!$config = self::get_config()) {
//         return $auth_process_data;
//     }

//     $adapter = new \Hybridauth\Provider\Google($config);

//     if (!empty($_GET['redirect_to'])) {
//         $redirect_to = $_GET['redirect_to'];
//         $adapter->getStorage()->set('socialify_redirect_to', $redirect_to);
//     }

//     if ($accessToken = $adapter->getAccessToken()) {
//         $adapter->setAccessToken($accessToken);
//     }

//     $adapter->authenticate();

//     //Set process data
//     $auth_process_data['user_data'] = $adapter->getUserProfile();
//     $auth_process_data['redirect_to'] = $adapter->getStorage()->get('socialify_redirect_to');
//     $auth_process_data['provider'] = 'google';

//     //Disconnect the adapter & destroy session
//     $adapter->disconnect();

//     return $auth_process_data;

// }

function add_settings()
{
    $section_id = 'google';
    $option_key = \Socialify\Settings\OPTION_KEY;
    $option_page = \Socialify\Settings\OPTION_PAGE;

    add_settings_section(
        $section_id,
        "Google",
        function () {
            ?>
        <details>
            <summary>Help</summary>
            <ol>
                <li>
                    <span>
                        <?= __('Get settings for Googla App: ', 'socialify') ?>
                    </span>
                    <a href="https://console.developers.google.com/apis/credentials/"
                        target="_blank">https://console.developers.google.com/apis/credentials</a>
                </li>
                <li>Callback URI use this: <code><?= get_endpoint() ?></code></li>
                <li>URL for site: <code><?= site_url() ?></code></li>
                <li>Domain: <code><?= $_SERVER['SERVER_NAME'] ?></code></li>
            </ol>
        </details>
        <?php
        },
        $option_page
    );

    add_settings_field(
        'google_client_id',
        'Client ID',
        function ($args) {
            printf(
                '<input type="text" name="%s" value="%s" size="77">',
                $args['name'], $args['value']
            );
        },
        $option_page,
        $section_id,
        $args = [
            'name' => $option_key . '[google_client_id]',
            'value' => get_option($option_key)['google_client_id'] ?? '',
        ]
    );

    add_settings_field(
        'google_client_secret',
        'Secret Token',
        function ($args) {
            printf(
                '<input type="text" name="%s" value="%s" size="77">',
                $args['name'], $args['value']
            );
        },
        $option_page,
        $section_id,
        $args = [
            'name' => $option_key . '[google_client_secret]',
            'value' => get_option($option_key)['google_client_secret'] ?? '',
        ]
    );


}

/**
 * Login via Google OAuth2
 */
final class GoogleLogin
{
    public static $data = [
        'settings_section_title' => 'Google Login',
        'setting_title_id' => 'Google ID',
        'setting_title_secret' => 'Google Secret',
    ];

    public static $option_name = 'socialify_config_google';

    public static $endpoint = '/socialify/Google/';

    public static function init()
    {
        self::$endpoint = site_url(self::$endpoint);

        add_action('admin_init', [__CLASS__, 'add_settings']);

        add_action('plugins_loaded', function () {
            add_filter('socialify_auth_process', [__CLASS__, 'auth_process'], 11, 2);
            // add_filter('socialify_shortcode_data', [__CLASS__, 'add_btn_for_shortcode']);
        });

        add_action('socialify_btns', [__CLASS__, 'render_btn']);
    }

    public static function render_btn()
    {
        ?>
        <div class="wp-block-buttons is-layout-flex wp-block-buttons-is-layout-flex">
            <div class="wp-block-button">
                <a class="wp-block-button__link wp-element-button" href="<?= self::$endpoint ?>">Google</a>
            </div>
        </div>

        <?php
    }
    /**
     * Check is active
     */
    public static function is_active()
    {
        $config_data = get_option(self::$option_name);
        if (empty($config_data['id']) || empty($config_data['secret'])) {
            return false;
        }

        return true;
    }

    /**
     * apply_filters('socialify_auth_process', $auth_process_data);
     */
    public static function auth_process($auth_process_data, $endpoint)
    {
        if ('Google' != $endpoint) {
            return $auth_process_data;
        }

        if (!$config = self::get_config()) {
            return $auth_process_data;
        }

        $adapter = new \Hybridauth\Provider\Google($config);

        if (!empty($_GET['redirect_to'])) {
            $redirect_to = $_GET['redirect_to'];
            $adapter->getStorage()->set('socialify_redirect_to', $redirect_to);
        }

        //Attempt to authenticate the user with Facebook
        if ($accessToken = $adapter->getAccessToken()) {
            $adapter->setAccessToken($accessToken);
        }

        $adapter->authenticate();

        //Set process data
        $auth_process_data['user_data'] = $adapter->getUserProfile();
        $auth_process_data['redirect_to'] = $adapter->getStorage()->get('socialify_redirect_to');
        $auth_process_data['provider'] = 'google';

        //Disconnect the adapter & destroy session
        $adapter->disconnect();

        return $auth_process_data;

    }

    public static function get_config()
    {
        $config_data = get_option(self::$option_name);
        if (empty($config_data['id']) || empty($config_data['secret'])) {
            return false;
        }

        $config = [
            'callback' => self::$endpoint,
            'keys' => ['id' => $config_data['id'], 'secret' => $config_data['secret']],
            //            'scope'    => 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email',
            'authorize_url_parameters' => [
                'approval_prompt' => 'force',
                // to pass only when you need to acquire a new refresh token.
                'access_type' => 'offline',
            ],
            //            'debug_mode' => 'debug',
//            'debug_file' => __FILE__ . '.log',
        ];

        return $config;
    }

    public static function add_btn_for_shortcode($data)
    {
        if (!self::is_active()) {
            return $data;
        }

        $data['login_items']['google'] = [
            'url' => self::$endpoint,
            'ico_url' => General::$plugin_dir_url . 'assets/svg/google.svg',
        ];

        return $data;
    }

    /**
     * Add settings
     */
    public static function add_settings()
    {

        add_settings_section(
            $section_id = self::$option_name . '_section',
            $section_title = self::$data['settings_section_title'],
            $callback = [__CLASS__, 'render_settings_instructions'],
            General::$settings_group
        );
        register_setting(General::$settings_group, self::$option_name);

        self::add_setting_id();
        self::add_setting_secret();
    }

    public static function render_settings_instructions()
    {
        ?>
        <details>
            <summary>Help</summary>
            <ol>
                <li>
                    <span>
                        <?= __('Get settings for Googla App: ', 'socialify') ?>
                    </span>
                    <a href="https://console.developers.google.com/apis/credentials/"
                        target="_blank">https://console.developers.google.com/apis/credentials</a>
                </li>
                <li>Callback URI use this: <code><?= self::$endpoint ?></code></li>
                <li>URL for site: <code><?= site_url() ?></code></li>
                <li>Domain: <code><?= $_SERVER['SERVER_NAME'] ?></code></li>
            </ol>
        </details>
        <?php
    }

    /**
     * add option id
     *
     * name = socialify_config_google[id]
     */
    public static function add_setting_id()
    {
        $setting_title = self::$data['setting_title_id'];
        $setting_id = General::$slug . '_google_id';
        add_settings_field(
            $setting_id,
            $setting_title,
            $callback = function ($args) {
                printf(
                    '<input type="text" name="%s" value="%s" size="77">',
                    $args['name'], $args['value']
                );
            },
            $page = General::$settings_group,
            $section = self::$option_name . '_section',
            $args = [
                'name' => self::$option_name . '[id]',
                'value' => @get_option(self::$option_name)['id'],
            ]
        );
    }

    /**
     * add option secret
     *
     * name = socialify_config_google[secret]
     */
    public static function add_setting_secret()
    {
        $setting_title = self::$data['setting_title_secret'];
        $setting_id = self::$option_name . '_secret';
        add_settings_field(
            $setting_id,
            $setting_title,
            $callback = function ($args) {
                printf(
                    '<input type="text" name="%s" value="%s" size="77">',
                    $args['name'], $args['value']
                );
            },
            $page = General::$settings_group,
            $section = self::$option_name . '_section',
            $args = [
                'name' => self::$option_name . '[secret]',
                'value' => @get_option(self::$option_name)['secret'],
            ]
        );
    }
}

GoogleLogin::init();
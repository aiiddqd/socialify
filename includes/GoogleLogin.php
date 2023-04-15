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

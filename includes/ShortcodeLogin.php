<?php
namespace Socialify;
defined('ABSPATH') || die();

/**
 * ShortcodeLogin
 */
final class ShortcodeLogin
{
    /**
     * @var bool for check login page
     */
    public static $is_login_page = false;

    public static $option_name = 'socialify_shortcode';

    public static $show_on_login = false;

    /**
     * The init
     */
    public static function init()
    {
        add_action('plugins_loaded', function (){

          self::$show_on_login = @get_option(self::$option_name)['login_page_show'];

          add_shortcode('socialify_login', function($args) {
            $data = [];
            if( ! empty($args['email']) ){
              $data['login_items'] = [
                'email_standard' => [
                  'url'     => wp_login_url(home_url()),
                  'ico_url' => General::$plugin_dir_url . 'assets/svg/email.svg',
                ],
              ];
            }

            $data = apply_filters('socialify_shortcode_data', $data);

            foreach ($data['login_items'] as $key => $item) {
              $data['login_items'][ $key ]['class_array'][] = 'socialify_shortcode_login__item';
              $data['login_items'][ $key ]['class_array'][] = 'socialify_' . $key;
            }

            ob_start();
            require_once __DIR__ . '/../templates/shortocde-btns.php';
            $content = ob_get_clean();
            $content = apply_filters('socialify_shortcode_content', $content, $data);
            return $content;
          });

          add_action( 'wp_enqueue_scripts', [__CLASS__, 'assets'] );

          add_filter('socialify_shortcode_data', [__CLASS__, 'add_redirect_to']);

          add_action('admin_init', [__CLASS__, 'add_settings']);

          if(self::$show_on_login){
              add_filter('socialify_shortcode_data', [__CLASS__, 'filter_login_page']);
              add_action('login_form', [__CLASS__, 'add_to_login_page']);
              add_action('login_enqueue_scripts', [__CLASS__, 'assets_login_page']);
          }
        });
    }

    /**
     * add_settings
     */
    public static function add_settings(){
      add_settings_section(
        $section_id = self::$option_name . '_section',
        $section_title = __('Shortcode'),
        $callback = '',
        General::$settings_group
      );
      register_setting(General::$settings_group, self::$option_name);

      add_settings_field(
        $setting_id = self::$option_name . '_login_page_show',
        $setting_title = __('Показывать шорткод на странице авторизации', 'socialify'),
        $callback = function($args){
          printf(
            '<input type="checkbox" name="%s" value="1" %s>',
            $args['name'], checked( 1, $args['value'], false )
          );
        },
        $page = General::$settings_group,
        $section = self::$option_name . '_section',
        $args = [
          'name' => self::$option_name . '[login_page_show]',
          'value' => @get_option(self::$option_name)['login_page_show'],
        ]
      );
    }

    /**
     * add redirect to param for login url
     */
    public static function add_redirect_to($data)
    {
        if(empty($data['login_items'])){
            return $data;
        }

        $data_new = $data;
        foreach ($data['login_items'] as $key => $data_item){
            $data_new['login_items'][$key]['url'] = remove_query_arg('redirect_to', $data_item['url']);
            $redirect_to = self::get_redirect_to();
            $data_new['login_items'][$key]['url'] = add_query_arg('redirect_to', urlencode($redirect_to), $data_item['url']);
        }

        return $data_new;
    }

    /**
     * get_redirect_to
     */
    public static function get_redirect_to()
    {
        global $wp;
        $redirect_to = empty($_GET['redirect_to']) ? home_url( $wp->request ) : $_GET['redirect_to'];
        return apply_filters('socialify_redirect_to', $redirect_to);
    }


    public static function filter_login_page($data){
        if(self::$is_login_page){
            unset($data['login_items']['email_standard']);
        }
        return $data;
    }

    public static function add_to_login_page(){
        echo do_shortcode('[socialify_login]');
    }

    public static function assets_login_page(){

        self::$is_login_page = true; //hack for check login page
        wp_enqueue_style(
            'socialify-sc-style',
            $url = General::$plugin_dir_url . 'assets/style.css',
            $dep = array(),
            $ver = filemtime(General::$plugin_dir_path . '/assets/style.css')
        );
    }

    /**
     * assets
     */
    public static function assets()
    {
        /**
         * hook for enable / disable ccs
         */
        if(apply_filters('socialify_shortcode_css_enable', true)){
          wp_enqueue_style(
            'socialify-sc-style',
            $url = General::$plugin_dir_url . 'assets/style.css',
            $dep = array(),
            $ver = filemtime(General::$plugin_dir_path . '/assets/style.css')
          );
        }
    }
}

ShortcodeLogin::init();

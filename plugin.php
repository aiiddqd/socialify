<?php
/**
 * Plugin Name:  Socialify
 * Description:  Social Login for WordPress based the OAuth2 and HybridAuth
 * Plugin URI:   https://github.com/aiiddqd/socialify
 * Author:       aiiddqd
 * Author URI:   https://github.com/aiiddqd
 * Text Domain:  socialify
 * Domain Path:  /languages/
 * GitHub Plugin URI: aiiddqd/socialify
 * Requires PHP: 8.0
 * Version:      0.9.250911
 */

namespace Socialify;

defined('ABSPATH') || die();

Plugin::init();

final class Plugin
{
    /**
     * Name of product
     *
     * @var string
     */
    public static $name = 'Socialify';

    /**
     * Slug of the product for make a hungarian notations
     * @var string
     */
    public static $slug = 'socialify';

    /**
     * Save the $plugin_basename value for various frequent tasks
     *
     * @var string
     */
    public static $plugin_basename = '';
    public static $plugin_file_path = '';
    public static $plugin_dir_path = '';
    public static $plugin_dir_url = '';
    public static $redirect_to = '';
    public static $providers = [];

    /**
     * @var string - for grouping all settings (by Settings API)
     */
    public static $settings_group = 'socialify_login_settings';

    /**
     * The init
     */
    public static function init()
    {
        self::$plugin_basename = plugin_basename(__FILE__);
        self::$plugin_file_path = __FILE__;
        self::$plugin_dir_path = plugin_dir_path(__FILE__);
        self::$plugin_dir_url = plugin_dir_url(__FILE__);

        require_once __DIR__.'/vendor/autoload.php';

        $files = glob(__DIR__.'/includes/*.php');
        foreach ($files as $file) {
            require_once $file;
        }

        add_action('plugins_loaded', [self::class, 'load_providers']);

        add_filter("plugin_action_links_".self::$plugin_basename, [self::class, 'add_settings_url_to_plugins_list']);

        add_action('wp_enqueue_scripts', [self::class, 'enqueue_styles']);
    }

    //enque styles
    public static function enqueue_styles()
    {
        $path = 'assets/build.css';
        $file_mtime = filemtime(self::$plugin_dir_path.$path);
        $file_url = self::$plugin_dir_url.$path;
        wp_enqueue_style('socialify-styles', $file_url, [], $file_mtime);
    }

    public static function load_providers()
    {
        self::$providers = apply_filters('socialify_providers', []);

        foreach (self::$providers as $provider) {
            if (is_a($provider, AbstractProvider::class, true)) {
                $provider::load();
            }
        }
    }

    //get providers
    public static function get_providers()
    {
        return self::$providers;
    }


    /**
     * Add Settings link in pligins list
     */
    public static function add_settings_url_to_plugins_list($links)
    {
        $settings_link = sprintf('<a href="%s">%s</a>', admin_url('admin.php?page=socialify-settings'), __('Settings', self::$slug));
        array_unshift($links, $settings_link);
        return $links;
    }

    /**
     * helper get_current_url
     *
     * @link https://wordpress.stackexchange.com/questions/274569/how-to-get-url-of-current-page-displayed
     *
     * @return string|void
     */
    public static function get_current_url()
    {
        global $wp;
        return home_url($wp->request);
    }
}

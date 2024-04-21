<?php 

namespace Socialify;

class Settings {

    public static $settings_group = 'socialify_settings';
    public static $option_key = 'socialify_options';

    public static function init(){
        add_action('admin_init', [__CLASS__, 'register_main_setting']);
        
        add_action('admin_menu', function() {
            add_options_page(
                $page_title = 'Socialify Settings',
                $menu_title = 'Socialify',
                $capability = 'administrator',
                $menu_slug = 'socialify-settings',
                $callback = [__CLASS__, 'render_settings']
            );
        });
    }

    public static function register_main_setting(){
        register_setting( self::$settings_group, self::$option_key ); 
    }

    public static function get_settings_group(){
        return self::$settings_group;
    }

    public static function get_option_key(){
        return self::$option_key;
    }

    public static function get_form_field_name($key){
        return sprintf('%s[%s]', self::$option_key, $key);
    }
    
    public static function get($key = null){
        if(empty($key)){
            return get_option(self::$option_key) ?? [];
        } else {
            return get_option(self::$option_key)[$key] ?? null;
        }
    }

    /**
     * Add settings
     */
    public static function render_settings(){
        ?>
        <div class="wrap">
            <h1><?= __('Socialify Settings', 'socialify') ?></h1>

            <form method="post" action="options.php">
                <?php settings_fields( self::$settings_group ); ?>
                <?php do_settings_sections( self::$settings_group ); ?>
                <?php submit_button(); ?>

            </form>
        </div>
        <?php
    }

}

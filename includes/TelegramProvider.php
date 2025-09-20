<?php 

namespace Socialify;

defined('ABSPATH') || die();


class TelegramProvider extends AbstractProvider {
    
    public static $key = 'telegram';

    public static function init(): void
    {
        // Initialization code for Telegram provider
    }

    public static function getLogoUrl(): string
    {
        return plugins_url('assets/telegram.svg', dirname(__FILE__));
    }

    public static function getProviderKey(): string
    {
        return self::$key;
    }

    public static function getProviderName(): string
    {
        return 'Telegram';
    }

    public static function getAuthStartUrl(): string
    {
        // Return the URL to start the authentication process with Telegram
        return 'https://t.me/your_bot?start=auth';
    }

    public static function is_enabled(): bool
    {
        return true;
        // Logic to determine if the Telegram provider is enabled
        $options = get_option(Plugin::$slug.'_settings', []);
        return !empty($options['telegram_enabled']);
    }
}

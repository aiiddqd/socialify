# Socialify

**Socialify** is a WordPress plugin that enables seamless social login functionality for your website. Powered by [HybridAuth](https://hybridauth.github.io/), Socialify allows users to log in or register using their existing accounts from popular platforms.

## Features

- **Social Login via OAuth2**
    - Google
    - Telegram
    - Yandex
- Powered by [HybridAuth](https://hybridauth.github.io/)
- Simple integration with your WordPress site
- Secure authentication flow

## Installation

1. Upload the `_socialify` plugin folder to your WordPress `/wp-content/plugins/` directory.
2. Activate the plugin through the WordPress admin dashboard.
3. Configure your social providers (Google, Telegram, Yandex) in the plugin settings.

## Add shortcodes 
### To Auth `[socialify_auth]`
- You can add the shortcode `[socialify_auth]` to any page or post where you want to display the social login buttons.
- As example - My Account page for WooCommerce.

### To Connect `[socialify_connect_providers]]`
- This shortcode will display the social connection buttons for all connected providers.
- You can add the shortcode `[socialify_connect_providers]` to any page or post where you want to display the social connection buttons.
- As example - My Account page for WooCommerce - has been planted automatically.


## Configuration

1. Go to **Settings > Socialify** in your WordPress admin panel.
2. Enter your API credentials for each provider:
     - **Google:** Client ID & Secret
     - **Telegram:** Bot Token
     - **Yandex:** Client ID & Secret
3. Save changes.

## Usage

After configuration, Socialify will add social login buttons to your WordPress login and registration forms. Users can authenticate using their preferred social account.

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher

## Credits

- [HybridAuth](https://hybridauth.github.io/) for OAuth2 integration

## License

This plugin is released under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

---

**Developed with ❤️ for the WordPress community.**
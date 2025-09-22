<?php

defined('ABSPATH') || die();

if (empty($items)) {
    return 'No providers are enabled';
}

?>
<div class="socialify-auth-providers">
    <div class="socialify-connect-providers">
        <legend class="socialify-fieldset-legend"><?= __('Connecting providers', 'socialify') ?></legend>
        <?php foreach ($items as $key => $item) : ?>
            <div class="socialify-connect-provider-row socialify-card">
                <div class="wp-block-group is-nowrap is-layout-flex wp-block-group-is-layout-flex">
                    <figure class="wp-block-image size-full is-resized">
                        <img decoding="async" width="32" height="32" alt="<?php echo esc_attr(ucfirst($key)); ?>"
                            src="<?php echo esc_url($item['logo_url']); ?>" />
                    </figure>
                    <?php if ($item['is_connected']) :
                        ?>
                        <p>Connected as
                            <span><?php echo esc_html($item['meta']['displayName'] ?? $item['meta']['displayName'] ?? $item['meta']['firstName']); ?></span>
                        </p>
                    <?php else : ?>
                        <p>Connect with <span><?php echo esc_html($item['name']); ?></span></p>
                    <?php endif; ?>
                </div>

                <div class="socialify-provider-action">
                    <?php if ($item['is_connected']) : ?>
                        <a href="#" class="socialify-btn socialify-provider-<?php echo esc_attr($key); ?> connected"
                            aria-disabled="true">
                            <span><?= __('Disconnect', 'socialify') ?></span>
                        </a>
                    <?php else : ?>
                        <a href="<?php echo esc_url($item['url']); ?>"
                            class="socialify-btn socialify-provider-<?php echo esc_attr($key); ?>">
                            <span><?= __('Connect', 'socialify') ?></span>
                        </a>
                    <?php endif; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>
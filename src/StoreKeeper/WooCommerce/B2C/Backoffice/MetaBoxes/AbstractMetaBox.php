<?php

namespace StoreKeeper\WooCommerce\B2C\Backoffice\MetaBoxes;

use WP_Post;

abstract class AbstractMetaBox
{
    const ACTION_NAME = 'sk_sync';

    abstract public function register(): void;

    abstract public function renderSyncBox(WP_Post $post): void;

    protected function showPossibleError(): void
    {
        if (array_key_exists('sk_sync_error', $_REQUEST)) {
            $message = esc_html($_REQUEST['sk_sync_error']);
            echo <<<HTML
                    <div class="notice notice-error">
                        <h4>$message</h4>
                    </div>
            HTML;
        }
    }

    protected function getNonceSyncActionLink(WP_Post $post): string
    {
        $post_type_object = get_post_type_object($post->post_type);
        $syncLink = add_query_arg(
            'action',
            self::ACTION_NAME,
            admin_url(sprintf($post_type_object->_edit_link, $post->ID))
        );

        return wp_nonce_url($syncLink, self::ACTION_NAME.'_post_'.$post->ID);
    }

    protected function getPostMeta($postId, string $metaKey, $fallback)
    {
        if (metadata_exists('post', $postId, $metaKey)) {
            return get_post_meta($postId, $metaKey, true);
        }

        return $fallback;
    }
}

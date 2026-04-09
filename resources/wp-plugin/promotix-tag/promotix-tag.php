<?php
/**
 * Plugin Name: Promotix Tag
 * Plugin URI: https://promotix.app/
 * Description: Installs the Promotix tracking tag on your WordPress site.
 * Version: 0.1.0
 * Requires at least: 5.6
 * Requires PHP: 7.4
 * Author: Promotix
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: promotix-tag
 */

if (!defined('ABSPATH')) {
    exit;
}

define('PROMOTIX_TAG_OPTION_KEY', 'promotix_tag_settings');

function promotix_tag_default_settings() {
    return array(
        'server_url' => '',
        'domain_key' => '',
        'secret_key' => '',
        'authentication_key' => '',
        'enabled' => '1',
    );
}

function promotix_tag_get_settings() {
    $defaults = promotix_tag_default_settings();
    $saved = get_option(PROMOTIX_TAG_OPTION_KEY, array());
    if (!is_array($saved)) $saved = array();
    return array_merge($defaults, $saved);
}

function promotix_tag_register_settings() {
    register_setting('promotix_tag', PROMOTIX_TAG_OPTION_KEY, 'promotix_tag_sanitize_settings');
}
add_action('admin_init', 'promotix_tag_register_settings');

function promotix_tag_sanitize_settings($input) {
    $out = promotix_tag_default_settings();

    $out['server_url'] = isset($input['server_url']) ? esc_url_raw(trim($input['server_url'])) : '';
    $out['domain_key'] = isset($input['domain_key']) ? sanitize_text_field(trim($input['domain_key'])) : '';
    $out['secret_key'] = isset($input['secret_key']) ? sanitize_text_field(trim($input['secret_key'])) : '';
    $out['authentication_key'] = isset($input['authentication_key']) ? sanitize_text_field(trim($input['authentication_key'])) : '';
    $out['enabled'] = !empty($input['enabled']) ? '1' : '0';

    return $out;
}

function promotix_tag_admin_menu() {
    add_options_page(
        'Promotix Tag',
        'Promotix Tag',
        'manage_options',
        'promotix-tag',
        'promotix_tag_render_settings_page'
    );
}
add_action('admin_menu', 'promotix_tag_admin_menu');

function promotix_tag_render_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    $s = promotix_tag_get_settings();
    ?>
    <div class="wrap">
        <h1>Promotix Tag</h1>
        <p>Paste your keys from the Promotix dashboard. This plugin will inject the tracking tag site-wide.</p>

        <form method="post" action="options.php">
            <?php settings_fields('promotix_tag'); ?>
            <table class="form-table" role="presentation">
                <tr>
                    <th scope="row"><label for="promotix_server_url">Server URL</label></th>
                    <td>
                        <input name="<?php echo esc_attr(PROMOTIX_TAG_OPTION_KEY); ?>[server_url]" id="promotix_server_url" type="url" class="regular-text"
                               value="<?php echo esc_attr($s['server_url']); ?>" placeholder="https://your-promotix-app.com" />
                        <p class="description">Your Promotix app base URL (where /tag/... lives).</p>
                    </td>
                </tr>
                <tr>
                    <th scope="row"><label for="promotix_domain_key">Domain key</label></th>
                    <td><input name="<?php echo esc_attr(PROMOTIX_TAG_OPTION_KEY); ?>[domain_key]" id="promotix_domain_key" type="text" class="regular-text"
                               value="<?php echo esc_attr($s['domain_key']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="promotix_secret_key">Secret key</label></th>
                    <td><input name="<?php echo esc_attr(PROMOTIX_TAG_OPTION_KEY); ?>[secret_key]" id="promotix_secret_key" type="text" class="regular-text"
                               value="<?php echo esc_attr($s['secret_key']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row"><label for="promotix_authentication_key">Authentication key</label></th>
                    <td><input name="<?php echo esc_attr(PROMOTIX_TAG_OPTION_KEY); ?>[authentication_key]" id="promotix_authentication_key" type="text" class="regular-text"
                               value="<?php echo esc_attr($s['authentication_key']); ?>" /></td>
                </tr>
                <tr>
                    <th scope="row">Enable</th>
                    <td>
                        <label>
                            <input name="<?php echo esc_attr(PROMOTIX_TAG_OPTION_KEY); ?>[enabled]" type="checkbox" value="1" <?php checked('1', $s['enabled']); ?> />
                            Inject tag on all pages
                        </label>
                    </td>
                </tr>
            </table>

            <?php submit_button('Save settings'); ?>
        </form>
    </div>
    <?php
}

function promotix_tag_build_tag_url($server_url, $domain_key) {
    $server_url = rtrim($server_url, '/');
    if ($server_url === '' || $domain_key === '') return '';
    return $server_url . '/tag/' . rawurlencode($domain_key) . '.js';
}

function promotix_tag_inject_head() {
    $s = promotix_tag_get_settings();
    if ($s['enabled'] !== '1') return;

    $tagUrl = promotix_tag_build_tag_url($s['server_url'], $s['domain_key']);
    if ($tagUrl === '') return;

    echo "\n<!-- Promotix Tag -->\n";
    echo '<script async src="' . esc_url($tagUrl) . '" class="pm_tag"></script>' . "\n";
}
add_action('wp_head', 'promotix_tag_inject_head', 1);


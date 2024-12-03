<?php

/*
Plugin Name:  Vital Dev Tools
Plugin URI:   https://github.com/vitalseeds/vital-dev-tools
Description:  A collection of tools for Wordpress development.
Version:      2.0
Author:       tombola
Author URI:   https://github.com/tombola
License:      GPL2
License URI:  https://github.com/vitalseeds/vital-dev-tools/blob/main/LICENSE
Text Domain:  vital-dev-tools
Domain Path:  /languages
*/

// define('HOOK_LABELS', []);
define('HOOK_LABELS', [
	'woocommerce_before_main_content',
	'woocommerce_archive_description',
	'woocommerce_before_shop_loop',
	'woocommerce_shop_loop',
	'woocommerce_after_shop_loop',
	'woocommerce_no_products_found',
	'woocommerce_after_main_content',
	'woocommerce_sidebar',
]);


add_action('admin_bar_menu', 'show_template');

function show_template()
{
	if (!get_option('vital_dev_tools_show_template')) {
		return;
	}

	global $template;
?>
	<script>
		console.log(`TEMPLATE:\n<?php print_r($template); ?>`)
	</script>
<?php
}

add_action('init', 'label_template_hooks');

function label_template_hooks()
{
	if (!get_option('vital_dev_tools_label_hooks')) {
		return;
	}
	foreach (HOOK_LABELS as $hook_label) {
		add_action($hook_label, function () use ($hook_label) {
			echo "<h2>$hook_label</h2>";
		});
	}
}

add_action('admin_menu', 'vital_dev_tools_menu');

function vital_dev_tools_menu()
{
	add_options_page(
		'Vital Dev Tools Settings',
		'Vital Dev Tools',
		'manage_options',
		'vital-dev-tools',
		'vital_dev_tools_settings_page'
	);
}

function vital_dev_tools_settings_page()
{
	if (isset($_POST['show_template'])) {
		update_option('vital_dev_tools_show_template', 1);
	} else {
		update_option('vital_dev_tools_show_template', 0);
	}

	if (isset($_POST['label_hooks'])) {
		update_option('vital_dev_tools_label_hooks', 1);
	} else {
		update_option('vital_dev_tools_label_hooks', 0);
	}

	echo '<div class="updated"><p>Settings saved.</p></div>';

	$show_template = get_option('vital_dev_tools_show_template', 1);
	$label_hooks = get_option('vital_dev_tools_label_hooks', 1);
?>
	<div class="wrap">
		<h1>Vital Dev Tools Settings</h1>
		<form method="post" action="">
			<table class="form-table">
				<tr valign="top">
					<th scope="row">Show Template in Admin Bar</th>
					<td><input type="checkbox" name="show_template" <?php checked($show_template, 1); ?> /></td>
				</tr>
				<tr valign="top">
					<th scope="row">Label Template Hooks</th>
					<td><input type="checkbox" name="label_hooks" <?php checked($label_hooks, 1); ?> /></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
<?php
}

if (get_option('vital_dev_tools_show_template', 1)) {
	add_action('admin_bar_menu', 'show_template');
}

if (get_option('vital_dev_tools_label_hooks', 1)) {
	add_action('init', 'label_template_hooks');
}

if (defined('WP_CLI') && WP_CLI) {
	WP_CLI::add_command('vital-snippets', 'Vital_Snippets_Command');
}

class Vital_Snippets_Command
{
	/**
	 * List all code snippets with their status, id, and description.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vital-snippets list
	 *
	 * @when after_wp_load
	 */
	public function list()
	{
		global $wpdb;
		$snippets = $wpdb->get_results("SELECT id, name, description, active FROM {$wpdb->prefix}snippets");

		if (empty($snippets)) {
			WP_CLI::success("No snippets found.");
			return;
		}

		$items = [];
		foreach ($snippets as $snippet) {
			$items[] = [
				'id' => $snippet->id,
				'name' => $snippet->name,
				'description' => $snippet->description,
				'status' => $snippet->active ? 'enabled' : 'disabled',
			];
		}

		WP_CLI\Utils\format_items('table', $items, ['id', 'name', 'description', 'status']);
	}

	/**
	 * Enable a code snippet.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the snippet to enable.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vital-snippets enable 1
	 *
	 * @when after_wp_load
	 */
	public function enable($args)
	{
		global $wpdb;
		$id = (int) $args[0];
		$updated = $wpdb->update("{$wpdb->prefix}snippets", ['active' => 1], ['id' => $id]);

		if ($updated) {
			WP_CLI::success("Snippet {$id} enabled.");
		} else {
			WP_CLI::error("Failed to enable snippet {$id}.");
		}
	}

	/**
	 * Disable a code snippet.
	 *
	 * ## OPTIONS
	 *
	 * <id>
	 * : The ID of the snippet to disable.
	 *
	 * ## EXAMPLES
	 *
	 *     wp vital-snippets disable 1
	 *
	 * @when after_wp_load
	 */
	public function disable($args)
	{
		global $wpdb;
		$id = (int) $args[0];
		$updated = $wpdb->update("{$wpdb->prefix}snippets", ['active' => 0], ['id' => $id]);

		if ($updated) {
			WP_CLI::success("Snippet {$id} disabled.");
		} else {
			WP_CLI::error("Failed to disable snippet {$id}.");
		}
	}
	// Existing methods...

	/**
	 * Enable or disable all snippets containing a specified token.
	 *
	 * ## OPTIONS
	 *
	 * <token>
	 * : The token to search for in the snippet code.
	 *
	 * <action>
	 * : The action to perform (enable or disable).
	 *
	 * ## EXAMPLES
	 *
	 *     wp vital-snippets toggle-by-token my_token enable
	 *     wp vital-snippets toggle-by-token my_token disable
	 *
	 * @when after_wp_load
	 */
	public function toggle_by_token($args)
	{
		global $wpdb;
		$token = $args[0];
		$action = $args[1];
		$active = ($action === 'enable') ? 1 : 0;

		$snippets = $wpdb->get_results($wpdb->prepare(
			"SELECT id, name FROM {$wpdb->prefix}snippets WHERE code LIKE %s",
			'%' . $wpdb->esc_like($token) . '%'
		));

		if (empty($snippets)) {
			WP_CLI::success("No snippets found containing the token '{$token}'.");
			return;
		}

		$items = [];
		foreach ($snippets as $snippet) {
			$wpdb->update("{$wpdb->prefix}snippets", ['active' => $active], ['id' => $snippet->id]);
			$items[] = [
				'id' => $snippet->id,
				'name' => $snippet->name,
			];
		}

		WP_CLI\Utils\format_items('table', $items, ['id', 'name']);
		WP_CLI::success("Snippets containing the token '{$token}' have been " . ($active ? 'enabled' : 'disabled') . ".");
	}
}

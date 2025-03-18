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

	if (isset($_POST['prefix_product_title'])) {
		update_option('vital_dev_tools_prefix_product_title', 1);
	} else {
		update_option('vital_dev_tools_prefix_product_title', 0);
	}

	echo '<div class="updated"><p>Settings saved.</p></div>';

	$show_template = get_option('vital_dev_tools_show_template', 1);
	$label_hooks = get_option('vital_dev_tools_label_hooks', 1);
	$prefix_product_title = get_option('vital_dev_tools_prefix_product_title', 1);
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
				<tr valign="top">
					<th scope="row">Prefix Product Title with ID</th>
					<td><input type="checkbox" name="prefix_product_title" <?php checked($prefix_product_title, 1); ?> /></td>
				</tr>
			</table>
			<?php submit_button(); ?>
		</form>
	</div>
<?php
}

if (get_option('vital_dev_tools_show_template', 0)) {
	add_action('admin_bar_menu', 'show_template');
}

if (get_option('vital_dev_tools_label_hooks', 0)) {
	add_action('init', 'label_template_hooks');
}

if (get_option('vital_dev_tools_prefix_product_title', 0)) {
	add_filter('the_title', 'prefix_product_title_with_id', 10, 2);
}

function prefix_product_title_with_id($title, $id)
{
	if (is_admin()) {
		return $title;
	}

	if (get_post_type($id) === 'product') {
		$product = wc_get_product($id);
		$title = $product->get_id() . ' - ' . $title;
	}

	return $title;
}
/**
 * Adds a custom WP-CLI command 'vs get_custom_css' to retrieve and display the
 * current theme's custom CSS.
 *
 * Usage:
 *   wp vs get_custom_css
 *
 * @package VitalDevTools
 */

if (defined('WP_CLI') && WP_CLI) {
	WP_CLI::add_command('vs get_custom_css', function () {
		$custom_css = wp_get_custom_css();
		if ($custom_css) {
			WP_CLI::success("Custom CSS: \n" . $custom_css);
		} else {
			WP_CLI::warning("No custom CSS found.");
		}
	});
	WP_CLI::add_command('vs get_theme_options', function ($args, $assoc_args) {
		$theme_options = get_option('theme_mods_' . get_option('stylesheet'));
		// $theme_options = wp_load_alloptions();
		$output = [];

		if ($theme_options) {
			foreach ($theme_options as $option_name => $option_value) {
				$option_value = maybe_unserialize($option_value);
				$output[$option_name] = $option_value;
			}
			// output as json so more readable
			$json_output = json_encode($output, JSON_PRETTY_PRINT);
			WP_CLI::line($json_output);
		} else {
			WP_CLI::warning("No theme options found.");
		}
	});

	WP_CLI::add_command('vs update_custom_css_post_id', function ($args, $assoc_args) {
		if (isset($assoc_args['duplicate']) && $assoc_args['duplicate']) {
			$custom_css_post_id = get_theme_mod('custom_css_post_id');
			if ($custom_css_post_id) {
				$custom_css_post = get_post($custom_css_post_id);
				if ($custom_css_post) {
					$new_custom_css_post = array(
						'post_title'   => $custom_css_post->post_title . ' (Duplicate)',
						'post_content' => $custom_css_post->post_content,
						'post_status'  => 'publish',
						'post_type'    => 'custom_css',
					);
					$new_custom_css_post_id = wp_insert_post($new_custom_css_post);
					if ($new_custom_css_post_id) {
						WP_CLI::success("Custom CSS post duplicated successfully with new ID {$new_custom_css_post_id}.");
						$new_custom_css_post_id = $new_custom_css_post_id;
					} else {
						WP_CLI::error("Failed to duplicate the custom CSS post.");
					}
				} else {
					WP_CLI::error("Original custom CSS post not found.");
				}
			} else {
				WP_CLI::error("custom_css_post_id not set in theme options.");
			}
		}

		if (!isset($new_custom_css_post_id)) {
			if (empty($args)) {
				WP_CLI::error("Please provide a new custom_css_post_id.");
				return;
			}
			$new_custom_css_post_id = $args[0];
		}
		$theme_options = get_option('theme_mods_' . get_option('stylesheet'));

		if ($theme_options && isset($theme_options['custom_css_post_id'])) {
			$theme_options['custom_css_post_id'] = $new_custom_css_post_id;
			update_option('theme_mods_' . get_option('stylesheet'), $theme_options);
			WP_CLI::success("custom_css_post_id updated successfully to {$new_custom_css_post_id}.");
		} else {
			WP_CLI::warning("custom_css_post_id not found in theme options.");
		}
	});
}
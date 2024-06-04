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

define('HOOK_LABELS', []);
// define('HOOK_LABELS', [
// 	'woocommerce_before_main_content',
// 	'woocommerce_archive_description',
// 	'woocommerce_before_shop_loop',
// 	'woocommerce_shop_loop',
// 	'woocommerce_after_shop_loop',
// 	'woocommerce_no_products_found',
// 	'woocommerce_after_main_content',
// 	'woocommerce_sidebar',
// ]);


add_action('admin_bar_menu', 'show_template');

function show_template()
{
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
	foreach (HOOK_LABELS as $hook_label) {
		add_action($hook_label, function () use ($hook_label) {
			echo "<h2>$hook_label</h2>";
		});
	}
}

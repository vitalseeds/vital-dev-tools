<?php

if (defined('WP_CLI') && WP_CLI) {

    /**
     * Registers a WP-CLI command 'vs edit_category_description' to edit category descriptions.
     *
     * This command retrieves all product category terms and deletes the calendar heading and shortcode
     * for each term.
     *
     * Usage:
     *   wp vs edit_category_description
     *
     * @return void
     */
	WP_CLI::add_command('vs edit_category_description', function () {
		echo "Editing category descriptions\n";
		$terms = get_product_cat_terms();
		echo $terms;
		foreach ($terms as $term) {
			// echo "Term ID: " . $term->term_id . "\n";
			delete_calendar_heading_and_shortcode($term);
		}
	});
    WP_CLI::add_command('vs deploy_calendar', function() {
        WP_CLI::log("Deploying growing calendar\n");
        WP_CLI::log("Note: first steps are");
        WP_CLI::log("- import calendar CSV");
        // WP_CLI::log("- temporarily disable tablepress.\n");
        WP_CLI::confirm('Delete growing calendar headings and shortcodes from all product category descriptions. Are you sure?');
        WP_CLI::runcommand('vs edit_category_description');
    });
}

// function get_product_cat_terms($taxonomy){
function get_product_cat_terms(){
    // $term = $wpdb->get_row("SELECT term_id, description FROM {$wpdb->prefix}term_taxonomy WHERE term_id = 135");
    global $wpdb;
    echo "Getting terms\n";
    echo "SELECT term_id, description FROM {$wpdb->prefix}term_taxonomy WHERE taxonomy = 'product_cat'";
    return $wpdb->get_results("SELECT term_id, description FROM {$wpdb->prefix}term_taxonomy WHERE taxonomy = 'product_cat'");
}

function comment_out_calendar_heading($term){
    global $wpdb;
    // Comment out the growing calendar headings on product category pages
    if ($term) {
        $updated_description = preg_replace(
            "/(<h[24][^>]*>(?:<strong>)?[^<>]+? growing calendar(?:<\/strong>)?<\/h[24]>)/",
            "<!-- $1 -->",
            $term->description
        );
        // Commenting tablepress shortcode doesn't work
        // $updated_description = preg_replace(
        //     "/(\[table id=[A-Za-z0-9_-]+ \/])/",
        //     "<!-- $1 -->",
        //     $updated_description
        // );
        echo $term->description;
        echo "\n===========================\n";
        echo $updated_description;
        echo "\n\n\n";
        $wpdb->update("wpjp_term_taxonomy", ["description" => $updated_description], ["term_id" => $term->term_id]);
    }
}

function delete_calendar_heading_and_shortcode($term){
    global $wpdb;
    // Comment out the growing calendar headings on product category pages
    if ($term && $term->description) {
        echo "Term ID: ". $term->term_id . "\n";
        $updated_description = preg_replace(
            "/(<h[24][^>]*>(?:<strong>)?[^<>]+? growing calendar(?:<\/strong>)?<\/h[24]>)/",
            "",
            $term->description
        );
        $updated_description = preg_replace(
            "/(\[table id=[A-Za-z0-9_-]+ \/])/",
            "",
            $updated_description
        );
        if ($term->description === $updated_description) {
            return;
        }
        echo $term->description;
        echo "\n===========================\n";
        echo $updated_description;
        echo "\n\n\n";
        $wpdb->update("wpjp_term_taxonomy", ["description" => $updated_description], ["term_id" => $term->term_id]);
    }
}

// foreach ($terms as $term) {
//     delete_calendar_heading_and_shortcode($term);
// }

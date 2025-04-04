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
    WP_CLI::add_command('vs extract_tablepress_id', function ($args) {
        if (empty($args[0])) {
            WP_CLI::error("Please provide a product ID.");
        }

        $product_id = intval($args[0]);
        $post = get_post($product_id);

        if (!$post || $post->post_type !== 'product') {
            WP_CLI::error("Invalid product ID or the product does not exist.");
        }

        $content = $post->post_content;

        // \[table id=([^\s/]+)[ ]?\/\]
        if (preg_match("/\[table id=([A-Za-z0-9_-]+) ?\/\]/", $content, $matches)) {
            $tablepress_id = $matches[1];
            WP_CLI::success("Found TablePress ID: $tablepress_id");
        } else {
            WP_CLI::warning("No TablePress shortcode found in the product description.");
        }
        WP_CLI::line("Product ID: $product_id");
        if (!empty($tablepress_id)) {
            // Force-load TablePress plugin code if not loaded yet
            include_once WP_PLUGIN_DIR . '/tablepress/tablepress.php';
            // if ( ! class_exists( 'TablePress' ) ) {
            //     TablePress::load(); // Force TablePress to initialize
            // }
            // TablePress::load_model( 'table' );
            // // Load the TablePress table
            // $table = null;

            // if (class_exists('TablePress') && isset(TablePress::$controller->model_table)) {
            //     $table = TablePress::$controller->model_table->load($tablepress_id);
            // } else {
            //     WP_CLI::error("TablePress is not properly initialized or 'model_table' is undefined.");
            // }
            // if ($table) {
            //     WP_CLI::success("Loaded TablePress table:");
            //     WP_CLI::line(print_r($table, true));
            // } else {
            //     WP_CLI::error("Failed to load TablePress table with ID: $tablepress_id");
            //     return;
            // }
            // ;

            // Load the TablePress model
            $tablepress_table_model = TablePress::load_model( 'table' );

            // Get the table data
            $table = $tablepress_table_model->load( $tablepress_id, true );

            // This is your 2D array (rows and columns)
            $table_data = $table['data'];

            // Now you can use $table_data as a PHP 2D array

            foreach ( $table_data as $row ) {
                WP_CLI::line( implode( "\t", $row ) );
            }


        }

    });
    // function wp_cli_tablepress_html_command( $args, $assoc_args ) {
    //     $table_id = $assoc_args['id'] ?? null;

    //     if ( empty( $table_id ) ) {
    //         WP_CLI::error( 'You must provide a table ID using --id.' );
    //         return;
    //     }

    //     // Manually initialize TablePress if it's not loaded
    //     if ( ! class_exists( 'TablePress' ) ) {
    //         include_once WP_PLUGIN_DIR . '/tablepress/tablepress.php';
    //         TablePress::load();
    //     }

    //     // Render the table using the shortcode with string ID
    //     $shortcode = '[table id=' . esc_attr( $table_id ) . ' /]';
    //     $html = do_shortcode( $shortcode );

    //     if ( empty( $html ) ) {
    //         WP_CLI::error( "No HTML output for table ID '{$table_id}'. Does it exist?" );
    //     }

    //     WP_CLI::line( $html );
    // }

    // WP_CLI::add_command( 'tablepress-html', 'wp_cli_tablepress_html_command'
    // );
    function wp_cli_tablepress_html_css_command( $args, $assoc_args ) {
        $table_id = $assoc_args['id'] ?? null;

        if ( empty( $table_id ) ) {
            WP_CLI::error( 'You must provide a table ID using --id.' );
            return;
        }

        // Manually initialize TablePress if it's not loaded
        if ( ! class_exists( 'TablePress' ) ) {
            include_once WP_PLUGIN_DIR . '/tablepress/tablepress.php';
            TablePress::load();
        }

        // Render the table using the shortcode with string or integer ID
        $shortcode = '[table id=' . esc_attr( $table_id ) . ' /]';
        $html = do_shortcode( $shortcode );

        if ( empty( $html ) ) {
            WP_CLI::error( "No HTML output for table ID '{$table_id}'. Does it exist?" );
        }

        // Output the HTML first
        WP_CLI::line( "HTML Output:" );
        WP_CLI::line( $html );

        // Output the associated CSS
        WP_CLI::line( "\nCSS Output:" );
        $css = '';

        // Add TablePress's default CSS
        $css .= wp_enqueue_style( 'tablepress' );

        // Collect any custom CSS associated with this table
        // Check for any custom table classes or inline CSS
        $table_style = get_option( 'tablepress_options' );
        if ( isset( $table_style['custom_css'] ) && ! empty( $table_style['custom_css'] ) ) {
            $css .= $table_style['custom_css'];
        }

        // Output the CSS (or default message if none exists)
        if ( ! empty( $css ) ) {
            WP_CLI::line( "<style>" . $css . "</style>" );
        } else {
            WP_CLI::line( "No custom CSS applied to this table." );
        }
    }

    WP_CLI::add_command( 'tablepress-html-css', 'wp_cli_tablepress_html_css_command' );
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

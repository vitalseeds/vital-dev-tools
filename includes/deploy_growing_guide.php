<?php

require_once __DIR__ . '/utils.php';

define('SEED_PARENT_TERM_ID', 276);

// These pages had quirky categories picked out by the migration:
// Growing guide title: How to grow Agretti (86336)
//            Category: Winter lettuce
// Growing guide title: How to grow broccoli Fiolaro di Creazzo (85461)
//            Category: Broccoli Seeds
// Growing guide title: How to grow chillies and peppers (40289)
//            Category: Bells of Ireland Seeds
// Growing guide title: How to grow Claytonia (85710)
//            Category: Batavia
// Growing guide title: How to grow Corn Salad (86117)
//            Category: Coriander Seeds
// Growing guide title: How to grow Fiolaro Di Creazzo (86331)
//            Category: Viola Seeds
// Growing guide title: How to grow peas (40352)
//            Category: Beans
define('OVERRIDE_GROWING_RESOURCE_CATEGORIES', [
    86336 => null,
    85461 => null,
    40289 => 164,
    85710 => 220,
    86117 => 219,
    86331 => null,
    40352 => 158,
]);


if (defined('WP_CLI') && WP_CLI) {

    /**
     * Registers a WP-CLI command `vs set_category_growersguide` to associate a growing guide with a product category.
     *
     * Command Usage:
     *   wp vs set_category_growersguide <category_id> <guide_id>
     *
     * Parameters:
     *   @param int $cat_id   The ID of the product category to associate with the growing guide.
     *   @param int $guide_id The ID of the growing guide (post) to associate with the category.
     */
    WP_CLI::add_command('vs set_category_growersguide', function($cat_id, $guide_id ) {
        $guide = get_post($guide_id);
        if ($guide) {
            WP_CLI::line("Guide Title: " . $guide->post_title . "\n");
        } else {
            WP_CLI::line("No page found for guide ID: $guide_id\n");
        }

        $category = get_term($cat_id, 'product_cat');
        if ($category && !is_wp_error($category)) {
            WP_CLI::line("Category Name: " . $category->name . "\n");
        } else {
            WP_CLI::line("No category found for category ID: $cat_id\n");
        }
        WP_CLI::confirm('Update the growing guide field for this category?', ['y', 'n']);
        update_field('growing_guide', $guide_id, 'term_' . $cat_id);
        // update_field('growing_guide', $page->ID, 'term_' . $term->term_id);
    });
    WP_CLI::add_command('vs list_category_growersguide', function($cat_id, $guide_id ) {
        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);

        echo str_pad("Category", 40) . str_pad("Category ID", 15) . str_pad("Growing Guide", 40) . "Growing Guide ID\n";
        echo str_repeat("-", 80) . "\n";

        foreach ($terms as $term) {
            if (is_under_seeds_category($term->term_id)) {
            $growing_guide = get_field('growing_guide', 'term_' . $term->term_id);
            if (is_array($growing_guide)) {
                $growing_guide = $growing_guide[0];
            }
            if ($growing_guide) {
                $growing_guide_title = $growing_guide->post_title;
                $growing_guide_id = $growing_guide->ID;
            } else {
                $growing_guide_title = '-';
                $growing_guide_id = '-';
            }
            echo str_pad($term->name, 40) . str_pad($term->term_id, 15) . str_pad($growing_guide_title, 40) . $growing_guide_id . "\n";
            }
        }
    });
    WP_CLI::add_command('vs list_growing_guides', function() {
        $guides = get_posts([
            'post_type' => 'growing-guide',
            'numberposts' => -1,
            'post_status' => 'publish',
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        foreach ($guides as $guide) {
            $permalink = get_permalink($guide->ID);
            // echo "{$guide->post_title}: {$permalink}\n";
            echo "{$permalink}\n";
        }
    });
    WP_CLI::add_command('vs backup_product_descriptions', function() {
        $products = get_posts([
            'post_type' => 'product',
            'numberposts' => -1,
            // 'numberposts' => 1,
            'post_status' => 'publish',
            'tax_query' => [
            [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => SEED_PARENT_TERM_ID,
                'include_children' => true,
            ],
            ],
        ]);

        foreach ($products as $product) {
            // $description = get_post_meta($product->ID, '_product_description', true);
            $product_obj = wc_get_product($product->ID);
            $description = $product_obj->get_description();
            // $short_description = $product_obj->get_short_description();
            if (!empty($description)) {
                update_field('product_description_backup', $description, $product->ID);
                $permalink = get_permalink($product->ID);
                echo "Backed up description for product {$product->ID} ({$permalink})\n";
            }
        }
    });
    WP_CLI::add_command('vs backup_category_descriptions', function() {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);

        foreach ($categories as $category) {
            if (!empty($category->description)) {
            update_field('category_description_backup', $category->description, 'term_' . $category->term_id);
            echo "Backed up description for category {$category->term_id} ({$category->name})\n";
            }
        }
    });
    WP_CLI::add_command('vs product_cat_tree', function() {
        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        $tree = build_term_tree($terms);
        display_term_tree($tree);
    });
    WP_CLI::add_command('vs construct_all_seeds_category', function() {
        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        $tree = build_term_tree($terms);
        move_parent_seeds($tree);
    });
    WP_CLI::add_command('vs remove_empty_categories', function() {
        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        $tree = build_term_tree($terms);
        remove_empty_parent_categories($tree);
    });
    WP_CLI::add_command('vs create_growers_guides', function() {
        $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
        // create_empty_growers_guides($terms);
        // create_growers_guides_from_product_cat($terms);
        create_growers_guides_from_resource_pages(39860, false);
    });

    WP_CLI::add_command('vs deploy_growing_guide', function() {
        WP_CLI::runcommand('vs backup_product_descriptions');
        WP_CLI::runcommand('vs backup_category_descriptions');
        WP_CLI::runcommand('vs construct_all_seeds_category');
        // WP_CLI::runcommand('vs remove_empty_categories');
        WP_CLI::runcommand('vs product_cat_tree');
        WP_CLI::runcommand('vs create_growers_guides');
    });

    // Miscellanous commands

    WP_CLI::add_command('vs delete_all_growing_guides', function() {
        WP_CLI::confirm('Delete all growing guides from the site. Are you sure?');
        delete_all_growing_guides();
    });
}

function get_seed_parent_term() {
    static $seeds_term = null;
    if ($seeds_term === null) {
        $seeds_term = get_term(SEED_PARENT_TERM_ID);
    }
    return $seeds_term;
}

function build_term_tree($terms) {
    $tree = [];
    $children = [];

    foreach ($terms as $term) {
        $tree[$term->term_id] = [
            'slug' => $term->slug,
            'parent' => $term->parent,
            'has_description' => ($term->description) ? true : false,
            'children' => []
        ];
        if ($term->parent) {
            $children[$term->parent][] = $term->term_id;
        }
    }

    foreach ($children as $parent_id => $child_ids) {
        foreach ($child_ids as $child_id) {
            $tree[$parent_id]['children'][$child_id] = &$tree[$child_id];
        }
    }

    return array_filter($tree, function($term) {
        return $term['parent'] == 0;
    });
}

function is_under_seeds_category($term_id) {
    return $term_id != SEED_PARENT_TERM_ID && term_is_ancestor_of(SEED_PARENT_TERM_ID, $term_id, 'product_cat');
}

function display_term_tree($tree, $parent_id = 0, $depth = 0) {
    foreach ($tree as $term_id => $term) {
        // $asterisk = is_under_seeds_category($term_id) ? "*" : "";
        $asterisk = $term['has_description'] ? "*" : "";
        echo str_repeat('    ', $depth) . "- {$term['slug']} ($term_id) $asterisk\n";
        if (!empty($term['children'])) {
            display_term_tree($term['children'], $term_id, $depth + 1);
        }
    }
}

function move_parent_seeds($tree, $parent_id = 0, $depth = 0) {
    foreach ($tree as $term_id => $term) {
        if ($term_id != SEED_PARENT_TERM_ID && !is_under_seeds_category($term_id) && $term['slug'] && substr($term['slug'], -5) === 'seeds') {
            echo "{$term['slug']} moved to seeds category\n";
            wp_update_term($term_id, 'product_cat', ['parent' => SEED_PARENT_TERM_ID]);
        }
    }
}

function term_has_posts($term_id) {
    $posts = get_posts([
        'post_type' => 'product',
        'tax_query' => [
            [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $term_id,
            ],
        ],
    ]);
    return !empty($posts);
}

function remove_empty_parent_categories($tree, $parent_id = 0, $depth = 0) {
    foreach ($tree as $term_id => $term) {
        if (!is_under_seeds_category($term_id)) {
            if (empty($term['children']) && !term_has_posts($term_id)) {
                echo "Empty category {$term['slug']} removed\n";
                wp_delete_term($term_id, 'product_cat');
            }
        }
    }
}

function delete_all_growing_guides() {
    $posts = get_posts([
        'post_type' => 'growing-guide',
        'numberposts' => -1,
        'post_status' => 'any'
    ]);

    foreach ($posts as $post) {
        wp_delete_post($post->ID, true);
        echo "Deleted growing guide {$post->post_title}\n";
    }
}

function create_empty_growers_guides($terms) {
    foreach ($terms as $term) {
        if (is_under_seeds_category($term->term_id) && $term->description) {
            $cat_name = trim(str_replace(['Seeds', 'seeds'], '', $term->name));
            $post_id = wp_insert_post([
                'post_title' => 'How to grow ' . $cat_name,
                'post_content' => '',
                'post_status' => 'publish',
                'post_type' => 'growing-guide',
                'published' => false,
                'product_category' => $term->term_id,
            ]);
            wp_set_object_terms($post_id, $term->term_id, 'product_cat');
            echo "Created growers guide for {$term->name}\n";
            // echo json_encode([
            //     'post_title' => 'How to grow ' . $cat_name,
            //     'post_content' => '',
            //     'post_status' => 'publish',
            //     'post_type' => 'growers_guide',
            //     'product_category' => $term->term_id
            // ], JSON_PRETTY_PRINT);
            $post_url = get_permalink($post_id);
            echo "{$post_url}\n";
        }
    }
}

function create_category_growers_guide_from_page($term, $page, $title=null) {
    // $markup = get_elementor_markup($page);
    $rendered_content = get_rendered_html($page);
    // Extract media IDs from the rendered content
    $media_ids = [];
    if (preg_match_all('/wp-image-(\d+)/', $rendered_content, $matches)) {
        $media_ids = array_unique($matches[1]);
    }
    $markup = strip_extra_markup($rendered_content);
    // $markup = urldecode($markup);
    // $markup = mb_convert_encoding($markup, 'UTF-8', 'auto');

    $headings = [
        'seed_sowing' => description_headings_by_phrase($markup, 'Sow'),
        'transplanting' => description_headings_by_phrase($markup, 'Transplant'),
        'plant_care' => description_headings_by_phrase($markup, 'Care'),
        'challenges' => description_headings_by_phrase($markup, ['Challenges', 'Diseases']),
        'harvest' => description_headings_by_phrase($markup, 'Harvest'),
        'seed_saving' => description_headings_by_phrase($markup, 'Saving'),
        'culinary_ideas' => description_headings_by_phrase($markup, ['Culinary', 'Cooking']),
        // 'varieties' => description_headings_by_phrase($markup, 'Varieties'),
    ];

    foreach ($headings as $key => $heading) {
        if (!empty($heading)) {
            echo "\033[32m✔ {$key}: " . $heading[0] . (count($heading) > 1 ? " (" . count($heading) . ")" : "") . "\033[0m\n";
            if (count($heading) > 1) {
                echo "    - " . implode("\n  ", $heading) . "\n";
            }
        } else {
            echo "\033[31m✘ {$key}: None\033[0m\n";
        }
    }
    echo "----------------------------------------\n";

    $sections = get_sections_between_headings($markup, $headings);

    echo "Sections:\n";
    foreach ($sections as $key => $section) {
        echo strtoupper($key) . ":\n";
        echo $section . "\n";;
        echo "----------------------------------------\n";
    }
    $growing_guide_id = wp_insert_post([
        'post_title' => $title ?? 'How to grow ' . $term->name,
        'post_content' => $page->post_content,
        'post_status' => 'publish',
        'post_type' => 'growing-guide',
    ]);

    if (!is_wp_error($growing_guide_id)) {
        update_field('growers_guide', $growing_guide_id, 'term_' . $term->term_id);

        update_field('seed_sowing', $sections['seed_sowing'], $growing_guide_id);
        update_field('transplanting', $sections['transplanting'], $growing_guide_id);
        update_field('plant_care', $sections['plant_care'], $growing_guide_id);
        update_field('challenges', $sections['challenges'], $growing_guide_id);
        update_field('harvest', $sections['harvest'], $growing_guide_id);
        update_field('culinary_ideas', $sections['culinary_ideas'], $growing_guide_id);
        update_field('seed_saving', $sections['seed_saving'], $growing_guide_id);

        update_field('images', $media_ids, $growing_guide_id);
        update_field('original_growing_reference_page', $page->ID, $growing_guide_id);

        // Add the category to the growing guide
        wp_set_object_terms($growing_guide_id, $term->term_id, 'product_cat');
        echo "Created guide: \033[36m{$term->name} \033[0m -> \033[35m{$page->post_title} (guide)\033[0m\n";
        $growers_guide = get_post($growing_guide_id);

        // Link this growing guide to the category for display
        update_field('growing_guide', $growing_guide_id, 'term_' . $term->term_id);

        get_permalink($growers_guide->ID);
        return $growers_guide;
    } else {
        echo "Failed to create growers guide for {$term->name} from page {$page->post_title}\n";
        return null;
    }
}

function create_growers_guides_from_resource_pages($growing_resources_page_id, $check_only=false, $delete=True) {
    if ($delete) {
        delete_all_growing_guides();
    }
    $resource_pages = get_pages(['child_of' => $growing_resources_page_id]);
    $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
    $categories = array_filter($terms, function($term) {
        return is_under_seeds_category($term->term_id);
    });
    WP_CLI::log("Creating growing guides from existing pages in the growing resources section.");
    WP_CLI::log("\033[35mcheck that the proposed growing guide title matches the category.\033[0m\n ");

    foreach ($resource_pages as $page) {
        if ( in_array($page->ID, array_keys(OVERRIDE_GROWING_RESOURCE_CATEGORIES))) {
            continue;
        }
        // if ($page->post_title != 'How to grow basil') {
        //     continue;
        // }
        $title = str_replace('How to grow ', '', $page->post_title);
        // find a matching category
        $override_term_id = OVERRIDE_GROWING_RESOURCE_CATEGORIES[$page->ID] ?? null;
        if ($override_term_id !== null) {
            $term = get_term($override_term_id, 'product_cat');
        } else {
            $term = best_match_category_title($categories, $title, $title . " seeds", 30);
        }

        if ($term) {
            WP_CLI::log("Growing guide title: \033[36m{$page->post_title} ({$page->ID})\033[0m");
            WP_CLI::log("           Category: \033[35m" . $term->name . "\033[0m");
            if ( $check_only ) { continue; }
            // $confirm = WP_CLI::confirm("Do you want to create a growing guide
            // for this category?", false);
            // $confirm = confirmation_prompt( "Do you want to create a growing guide for this category? (y/n)", $options = ['y'] );
            $confirm = true;
            if ($confirm) {
                // Create the growing guide
                WP_CLI::log("\033[36mCreating growing guide for {$term->name} from {$page->post_title}\033[0m");
                create_category_growers_guide_from_page($term, $page, $guide_id);
            } else {
                WP_CLI::log("\033[31mNo growers guide created\033[0m");
            }
            // $guide_id = WP_CLI::ask("Please provide an ID for the growing guide:");
            WP_CLI::log("\n\n");
        } else {
            WP_CLI::log("\033[31m- {$page->post_title} ({$page->ID})\033[0m\n");
        }
    }
}

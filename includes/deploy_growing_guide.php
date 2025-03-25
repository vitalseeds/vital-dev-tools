<?php


define('SEED_PARENT_TERM_ID', 276);

if (defined('WP_CLI') && WP_CLI) {
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
        create_empty_growers_guides($terms);
    });

    WP_CLI::add_command('vs deploy_growing_guide', function() {
        WP_CLI::runcommand('vs backup_product_descriptions');
        WP_CLI::runcommand('vs construct_all_seeds_category');
        // WP_CLI::runcommand('vs remove_empty_categories');
        WP_CLI::runcommand('vs product_cat_tree');
        // WP_CLI::runcommand('vs create_growers_guides');
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
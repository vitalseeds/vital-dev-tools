<?php


define('SEED_PARENT_TERM_ID', 276);

if (defined('WP_CLI') && WP_CLI) {
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
    WP_CLI::add_command('vs deploy_growing_guide', function() {
        // WP_CLI::runcommand('vs construct_all_seeds_category');
        WP_CLI::runcommand('vs remove_empty_categories');
        WP_CLI::runcommand('vs product_cat_tree');
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

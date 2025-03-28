<?php
/**
 * Partially finished or unused code snippets.
 *
 * From deploy_growing_guides.php
 *
 * @package Vital_Dev_Tools
 */


 function create_growers_guides_from_product_cat($terms, $check_only = true) {
    // ✔ step through by category
    // ✔ look for matching growing guide resource
    //    - if we have one
    //        ✔ convert to a growers guide
    //        ✔ add link to the category
    //    - if we don't
    //         - check the category description for growing information
    //         - divide up the description by heading, use regex or simple explode
    //         - create growers guide using headings as fields
    //         - add link to the category
    // - step through products
    //      - check if product description matches category growing guide
    //          - if not create a guide from product
    //              - check the product descriptions
    //              - compare product descriptions within category
    //                  - if the same (or similar?) make category guide
    //                  - else make product guides
    // - log guides created
    // - note categories and products that should be skipped
    //      - eg don't create product guide even if not match category guide)

    $count_seeds_categories = 0;
    $count_seeds_category_growing_instructions = 0;

    // delete_all_growing_guides();
    echo "========================================\n\n";

    foreach ($terms as $term) {

        if (is_under_seeds_category($term->term_id) && $term->description) {
            $cat_name = trim(str_replace(['Seeds', 'seeds'], '', $term->name));
            $guide_title = 'How to grow ' . $cat_name;

            // Don't create guides for categories with no products
            $products_in_category = get_posts([
                'post_type' => 'product',
                'numberposts' => -1,
                'post_status' => 'publish',
                'tax_query' => [
                    [
                        'taxonomy' => 'product_cat',
                        'field' => 'term_id',
                        'terms' => $term->term_id,
                        'include_children' => true,
                    ],
                ],
            ]);
            if (empty($products_in_category)) {
                echo "No products found for category: \033[33m{$cat_name}\033[0m\n";
                continue;
            }

            if ($cat_name !== 'Pea') {
                continue;
            }

            $term_url = get_term_link($term);
            $count_seeds_categories ++;

            $growing_resource_pages = get_posts([
                'post_type' => 'page',
                's' => $guide_title,
                'post_status' => 'publish',
                'posts_per_page' => -1,
            ]);

            // Growing resources page
            if (!empty($growing_resource_pages)) {
                echo "Category: \033[33m{$cat_name}:\033[0m\n";
                echo "----------------------------------------\n";

                $best_match = best_match_page_title($growing_resource_pages, $cat_name, $guide_title);

                echo "Existing guide page(s):\n";
                foreach ($growing_resource_pages as $page) {
                    $asterisk = ($page == $best_match) ? " *" : "  ";
                    // resource page title contains category name
                    if (stripos($page->post_title, $cat_name) !== false) {
                        // $edit_link = get_edit_post_link($page->ID);
                        $page_url = get_permalink($page->ID);
                        echo "  - {$page->post_title} ({$page->ID}) $asterisk  - {$page_url}\n";
                    }
                }
                echo "----------------------------------------\n";
                if ($best_match) {
                    $guide = create_category_growers_guide_from_page($term, $best_match, $guide_title);
                    if ($guide) {
                        update_field('growers_guide', $guide->ID, 'term_' . $term->term_id);
                        $permalink = get_permalink($guide);
                        echo $permalink . "\n";
                        add_redirection(get_permalink($best_match), $permalink, 302);
                    }
                }
                echo "========================================\n";
            }

            // Check the category description for growing information too
            // if ($headings_including_growing = description_has_growing_information($term->description)) {
            //     $count_seeds_category_growing_instructions ++;

            //     if ($items_with_growing_info) {
            //         $all_headings = get_headings_from_description($term->description);

            //         echo $cat_name . "\n";
            //         echo $term_url . "\n";
            //         echo "--------------------------------------\n";
            //         // foreach ($headings_including_growing as $heading) {
            //         //     echo "  " . strip_tags($heading) . "\n";
            //         // }
            //         foreach ($all_headings as $heading) {
            //             echo "  " . strip_tags($heading) . "\n";
            //         }
            //         // $description = wordwrap($term->description, 120, "\n    ");
            //         // echo $description . "\n";
            //         echo "======================================-\n";
            //     }
            // } else if (!$items_with_growing_info) {
            //     echo $cat_name . "\n";
            //     echo $term_url . "\n";
            //     echo "--------------------------------------\n";

            // }
        }
    }
    echo "\n\nSeeds categories: {$count_seeds_categories}\n";
    echo "Seeds category growing instructions: {$count_seeds_category_growing_instructions}\n";
}
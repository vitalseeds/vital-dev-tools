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
        // create_empty_growers_guides($terms);
        create_growers_guides_from_product_cat($terms);
    });

    WP_CLI::add_command('vs deploy_growing_guide', function() {
        WP_CLI::runcommand('vs backup_product_descriptions');
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

function description_headings_by_phrase($description, $phrase) {
    $headings = get_headings_from_description($description);
    $matching_headings = [];
    foreach ($headings as $heading) {
        if (is_array($phrase)) {
            foreach ($phrase as $p) {
                if (stripos($heading, $p) !== false) {
                    $matching_headings[] = $heading;
                    break;
                }
            }
        } else {
            if (stripos($heading, $phrase) !== false) {
                $matching_headings[] = $heading;
            }
        }
    }
    return $matching_headings;
}

function description_has_growing_information($description) {
    preg_match_all('/<h[1-6][^>]*>(.*?Growing.*?)<\/h[1-6]>/', $description, $matches);
    return $matches[1];
    // return description_headings_by_phrase($description, 'Growing');
}

function get_headings_from_description($description) {
    // preg_match_all('/<h[1-6][^>]*>(.*?)<\/h[1-6]>/', $description, $matches);
    // return $matches[1];
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML($description);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $headings = [];
    foreach (range(1, 6) as $level) {
        $nodes = $xpath->query("//h{$level}");
        foreach ($nodes as $node) {
            $headings[] = trim($node->textContent);
        }
    }
    return $headings;
}

function get_sections_between_headings($page_content, $headings) {
    $sections = [];
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML(mb_convert_encoding($page_content, 'HTML-ENTITIES', 'UTF-8'));
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    foreach ($headings as $key => $heading) {
        if (!empty($heading)) {
            foreach ($heading as $h) {
                $section = '';
                // Find nodes that follow the heading
                $nodes = $xpath->query("//*[contains(text(), '$h')]/following-sibling::*");
                foreach ($nodes as $node) {
                    // Stop if another heading is encountered
                    if (in_array(strtolower($node->nodeName), ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'])) {
                        break;
                    }
                    // Append the node's HTML to the section
                    $section .= $dom->saveHTML($node);
                }
                // Add the section to the sections array
                $sections[$key][] = $section;
            }
        }
    }
    return $sections;
}


function best_match_page_title($pages, $string, $extended_string=null) {
    $best_match = null;
    $best_similarity = 0;
    $extended_string = $extended_string ?? $string;
    foreach ($pages as $page) {
        similar_text($string, $page->post_title, $similarity);
        // if the exact string is found in the title give it a boost
        if (stripos($page->post_title, $extended_string) !== false) {
            $similarity += 20;
        }
        if ($similarity > $best_similarity) {
            $best_similarity = $similarity;
            $best_match = $page;
        }
    }
    return $best_match;
}

function get_elementor_markup($page) {
    $elementor_data = get_post_meta($page->ID, '_elementor_data', true);
    if ($elementor_data) {
        $elementor_content = \Elementor\Plugin::$instance->frontend->get_builder_content_for_display($page->ID);
        // Remove layout tags
        // $content = preg_replace('/<section[^>]*>|<\/section>|<div[^>]*>|<\/div>/', '', $elementor_content);
        // $content = preg_replace('/<span[^>]*>|<\/span>/', '', $content);
        // // Remove images
        // $content = preg_replace('/<img[^>]*>/', '', $content);
        // return $content;
        $content = strip_tags($elementor_content, ['p', 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li']);
        // remove class attributes
        $content = preg_replace('/class="[^"]*"/', '', $content);
        // remove target attributes
        $content = preg_replace('/target="[^"]*"/', '', $content);
        // Remove extra whitespace
        $content = preg_replace('/\s+/', ' ', $content);
        return $content;
    }
    return $page->post_content;
}

function create_category_growers_guide_from_page($term, $page, $title=null) {
    $page_content = get_elementor_markup($page);
    // $page_content = urldecode($page_content);
    // $page_content = mb_convert_encoding($page_content, 'UTF-8', 'auto');
    if (strpos($page_content, 'â') !== false) {
        echo "The character â exists in the page content.\n";
        exit;
    }

    $headings = [
        'sowing' => description_headings_by_phrase($page_content, 'Sow'),
        'transplant' => description_headings_by_phrase($page_content, 'Transplant'),
        'care' => description_headings_by_phrase($page_content, 'Care'),
        'challenges' => description_headings_by_phrase($page_content, ['Challenges', 'Diseases']),
        'harvest' => description_headings_by_phrase($page_content, 'Harvest'),
        'seed' => description_headings_by_phrase($page_content, 'Saving'),
        'varieties' => description_headings_by_phrase($page_content, 'Varieties'),
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

    $sections = get_sections_between_headings($page_content, $headings);

    echo "Sections:\n";
    foreach ($sections as $key => $section) {
        echo strtoupper($key) . ":\n";
        foreach ($section as $content) {
            echo $content . "\n";
        }
        echo "----------------------------------------\n";
    }

    $post_id = wp_insert_post([
        'post_title' => $title ?? 'How to grow ' . $term->name,
        'post_content' => $page->post_content,
        'post_status' => 'publish',
        'post_type' => 'growing-guide',
    ]);

    if (!is_wp_error($post_id)) {
        update_field('growers_guide', $post_id, 'term_' . $term->term_id);
        // update_field('seed_sowing', $sowing_section, $post_id);
        wp_set_object_terms($post_id, $term->term_id, 'product_cat');
        echo "Created guide: \033[36m{$term->name} \033[0m -> \033[35m{$page->post_title} (guide)\033[0m\n";
        return get_post($post_id);
    } else {
        echo "Failed to create growers guide for {$term->name} from page {$page->post_title}\n";
        return null;
    }
}


function create_growers_guides_from_product_cat($terms, $check_only = true) {
    // - step through by category
    // - look for matching growing guide resource
    //    - if we have one
    //        convert to a growers guide
    //        add link to the category
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

    delete_all_growing_guides();
    echo "========================================\n\n";

    foreach ($terms as $term) {

        if (is_under_seeds_category($term->term_id) && $term->description) {
            $cat_name = trim(str_replace(['Seeds', 'seeds'], '', $term->name));
            $guide_title = 'How to grow ' . $cat_name;

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
                    $permalink = get_permalink($guide);
                    echo $permalink . "\n";
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
<?php
/**
 * Utility functions for the Vital Dev Tools plugin.
 *
 * @package Vital_Dev_Tools
 */


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
    if (empty($description)) {
        return [];
    }
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
    if (empty($page_content)) {
        return [];
    }
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
                $sections[$key] = $section;
            }
        }
    }
    return $sections;
}

function best_match_page_title($pages, $string, $extended_string=null) {
    $best_match = null;
    $best_similarity = 0;
    $threshold = 40;
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
    if ($best_similarity < $threshold) {
        $best_match = null;
    }
    return $best_match;
}

function best_match_category_title($terms, $string, $extended_string=null, $threshold=50) {
    $best_match = null;
    $best_similarity = 0;
    $extended_string = $extended_string ?? $string;
    foreach ($terms as $term) {
        similar_text($string, $term->name, $similarity);
        // if the exact string is found in the title give it a boost
        if (stripos($term->post_title, $extended_string) !== false) {
            $similarity += 20;
        }
        if ($similarity > $best_similarity) {
            $best_similarity = $similarity;
            $best_match = $term;
        }
    }
    if ($best_similarity < $threshold) {
        $best_match = null;
    }
    return $best_match;
}

function strip_extra_markup($content) {
    $content = strip_tags($content, ['p', 'a', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'ul', 'ol', 'li']);
    // remove class attributes
    $content = preg_replace('/class="[^"]*"/', '', $content);
    // remove target attributes
    $content = preg_replace('/target="[^"]*"/', '', $content);
    // Remove extra whitespace
    $content = preg_replace('/\s+/', ' ', $content);
    return $content;
}

function get_rendered_html($post) {
    $post_id = is_object($post) ? $post->ID : $post;
    $post = is_int($post) ? get_post($post) : $post;

    // remove_filter('the_content', 'do_shortcode', 11);
    $page = get_post($post_id);
    $content = apply_filters('the_content', $post->post_content);
    return $content;
}

function confirmation_prompt( $message, $options = ['y', 'n'] ) {

    $input = readline($message);

    if (!is_array($options) || count($options) === 1) {
        return ($input === $options || $input === $options[0]);
    }
    if ( ! in_array( $input, $options, true ) ) {
        WP_CLI::error( "Invalid option. Please choose one of the following: " . implode( ', ', $options ) );
        return false;
    }
    return $input;
}


/**
 * Adds a redirection using direct SQL for the Redirection plugin.
 *
 * @param string $source_url The URL to redirect from.
 * @param string $target_url The URL to redirect to.
 * @param int $redirect_type The type of redirection (default is 301).
 * @return bool True if the redirection was added successfully, false otherwise.
 */
function add_redirection($source_url, $target_url, $redirect_type = 302, $flush = false) {
    global $wpdb;

    // Validate that both URLs are not absoulte
    if (strpos($source_url, 'http') === 0 || strpos($target_url, 'http') === 0) {
        return false; // Invalid URLs
    }

    // Prepend "/" to relative url if not present
    if (strpos($source_url, '/') !== 0) {
        $source_url = '/' . ltrim($source_url, '/');
    }
    if (strpos($target_url, '/') !== 0) {
        $target_url = '/' . ltrim($target_url, '/');
    }
    // remove trailing slashes
    $source_url = rtrim($source_url, '/');
    $target_url = rtrim($target_url, '/');

    // Table names used by the Redirection plugin
    $redirection_groups_table = $wpdb->prefix . 'redirection_groups';
    $redirection_items_table = $wpdb->prefix . 'redirection_items';

    // Check if the "growing_guides" group exists, create it if not
    $group_id = $wpdb->get_var("SELECT id FROM $redirection_groups_table WHERE name = 'growing_guides' LIMIT 1");
    if (!$group_id) {
        $wpdb->insert(
            $redirection_groups_table,
            [
                'name' => 'growing_guides',
                'status' => 'enabled',
                'module_id' => 1,
                'tracking' => 1,
                'position' => 0,
            ],
            [
                '%s', '%s', '%d','%d','%d'
            ]
        );
        $group_id = $wpdb->insert_id;
    }

    // Insert the redirection into the redirection_items table
    $result = $wpdb->insert(
        $redirection_items_table,
        [
            'url' => $source_url,
            'match_url' => $source_url,
            'match_data' => null,
            'regex' => 0,
            'position' => 0,
            'last_count' => 0,
            'last_access' => '1970-01-01 00:00:00',
            'group_id' => $group_id,
            'status' => 'enabled',
            'action_type' => 'url',
            'action_code' => $redirect_type,
            'action_data' => $target_url,
            'match_type' => 'url',
            'title' => '',
        ],
        ['%s', '%s', '%s', '%s', '%d', '%d', '%s', '%d', '%s', '%s', '%d', '%s', '%s', '%s']
    );
    if ($flush) {
        flush_rewrite_rules();
    }

    return (bool) $result;
}

/**
 * Deletes a redirection based on the source URL.
 *
 * @param string $source_url The URL to delete the redirection for.
 * @return bool True if the redirection was deleted successfully, false otherwise.
 */
function remove_redirection($source_url) {
    global $wpdb;

    // Prepend "/" to relative url if not present
    if (strpos($source_url, '/') !== 0) {
        $source_url = '/' . ltrim($source_url, '/');
    }
    // Remove trailing slash
    $source_url = rtrim($source_url, '/');

    // Table name used by the Redirection plugin
    $redirection_items_table = $wpdb->prefix . 'redirection_items';

    // Delete the redirection from the redirection_items table
    $result = $wpdb->delete(
        $redirection_items_table,
        ['url' => $source_url],
        ['%s']
    );

    return (bool) $result;
}

if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::add_command('flush_rewrites', function () {
        flush_rewrite_rules();
        WP_CLI::success('Rewrite rules flushed successfully.');
    });
}
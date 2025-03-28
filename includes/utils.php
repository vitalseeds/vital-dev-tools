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
 * Adds a redirection using the Redirection plugin's REST API.
 *
 * NOT CURRENTLY WORKING
 *
 * @param string $source_url The URL to redirect from.
 * @param string $target_url The URL to redirect to.
 * @param int $redirect_type The type of redirection (default is 301).
 * @return bool True if the redirection was added successfully, false otherwise.
 */
function add_redirection($source_url, $target_url, $redirect_type = 301, $cli = true) {
    $api_url = rest_url('redirection/v1/redirect');
    $args = array(
        'method' => 'POST',
        'headers' => array(
            'Content-Type' => 'application/json',
            'X-WP-Nonce' => wp_create_nonce('wp_rest')
        ),
        'body' => json_encode(array(
            'url' => $source_url,
            'action_data' => array('url' => $target_url),
            'action_type' => 'url',
            'action_code' => $redirect_type,
            'group_id' => 1, // Default group
            'enabled' => true
        ))
    );
    $response = wp_remote_post($api_url, $args);
    if (is_wp_error($response)) {
        return false;
    }
    $body = wp_remote_retrieve_body($response);
    $data = json_decode($body, true);
    if (isset($data['id'])) {
        "Added redirection from \033[33m$source_url\033[0m\n to \033[33m$target_url\033[0m\n";
    } else {
        echo "\033[31mFailed to add redirection from \033[33m$source_url\033[31m\n to \033[33m$target_url\033[31m\n\033[0m";
    }
    return isset($data['id']);
}

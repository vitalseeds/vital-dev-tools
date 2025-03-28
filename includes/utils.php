<?php
/**
 * Utility functions for the Vital Dev Tools plugin.
 *
 * @package Vital_Dev_Tools
 */

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
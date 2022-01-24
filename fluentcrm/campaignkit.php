<?php

/*
Plugin Name:  CampaignKit - Email Validation Service
Version    :  1.0
Description:  Validates the email address for newly created contacts in fluent crm
Author     :  CampaignKit
Author URI :  https://campaignkit.cc/
License    :  MIT
License URI:  https://mit-license.org/
Text Domain:  campaignkit
*/

//=================================================
// Security: Abort if this file is called directly
//=================================================
if ( !defined('ABSPATH') ) { 
    die;
}

function debug_log( $msg, $name = '' ) {
    if( WP_DEBUG === true ) { 
        $trace=debug_backtrace();
        $name = ( '' == $name ) ? $trace[1]['function'] : $name;

        $error_dir = 'campaignkit.log';
        $msg = print_r( $msg, true );
        $log = $name . "  |  " . $msg . "\n";
        error_log( $log, 3, $error_dir );
    }
}

function validate_email( $contact ) {
    $api_key = get_option( 'campaignkit_apikey' );
    $fluentcrm_tag = (int) get_option('campaignkit_fluentcrm_tag', '-1');

    $curl = curl_init();
    $url = "https://api.campaignkit.cc/v1/email/validate";

    curl_setopt_array( $curl, array(
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json', 
            'Authorization: Bearer ' . $api_key),
        CURLOPT_POSTFIELDS => "{\"emails\": [\"$contact->email\"]}",
        CURLOPT_RETURNTRANSFER => true
    ) );

    $response = curl_exec( $curl );
    $err = curl_error( $curl );
    
    curl_close( $curl );
    
    if ( $err ) {
        debug_log( $err, "CURL error" );
    } else {
        $responseObj = json_decode( $response );
        debug_log( $response, "CURL response" );

        if ( $responseObj->results[0]->result->score < 5 ) {
            $contact->attachTags( [$fluentcrm_tag] );
            $contact->save();
        }
    }
}

add_action( 'fluentcrm_contact_created', 'validate_email', 10, 1 );

// ----------------------------------------------------------------
// Settings
// ----------------------------------------------------------------

/* Settings Init */
function campaignkit_settings_init(){

    /* Register Settings */
    register_setting(
        'general',             // Options group
        'campaignkit_apikey'   // Option name/database
    );

     /* Create settings section */
     add_settings_section(
        'campaignkit-section-id',          // Section ID
        'CampaignKit Email Validation',    // Section title
        'campaignkit_section_description', // Section callback function
        'general'                          // Settings page slug
    );

    /* Create settings field */
    add_settings_field(
        'campaignkit-apikey-field-id',       // Field ID
        'API Key',                           // Field title 
        'campaignkit_field_callback',        // Field callback function
        'general',                           // Settings page slug
        'campaignkit-section-id'             // Section ID
    );

    if ( function_exists( 'FluentCrmApi') ) {
        register_setting(
            'general',                       // Options group
            'campaignkit_fluentcrm_tag'      // Option name/database
        );

        /* Create settings field */
        add_settings_field(
            'campaignkit-fluentcrm-tag-field-id',       // Field ID
            'FluentCRM Tag',                            // Field title 
            'campaignkit_fluentcrm_tag_callback',       // Field callback function
            'general',                                  // Settings page slug
            'campaignkit-section-id'                    // Section ID
        );
    }
}

/* Setting Section Description */
function campaignkit_section_description() {
}

/* Settings Field Callback */
function campaignkit_field_callback(){
    ?>
    <input id="campaignkit_apikey" type="text" value="<?php echo get_option('campaignkit_apikey'); ?>" name="campaignkit_apikey"> 
    <p id="campaignkit_apikey-description" class="description">CampaignKit's API Key. Get it from your <a target="_blank" href="https://app.campaignkit.cc/profile">CampaignKit Profile</a>.</p>
    <?php
}

function campaignkit_fluentcrm_tag_callback() {
    $tagApi = FluentCrmApi('tags');    
    $allTags = $tagApi->all(); 
    $selected_tag = (int) get_option('campaignkit_fluentcrm_tag', '-1');

    ?>
    <select id="campaignkit_fluentcrm_tag" name="campaignkit_fluentcrm_tag"> 
        <option value="-1" <?php selected($selected_tag, $tag->id); ?>></option>
        <?php
            foreach ($allTags as $tag) {
        ?>    <option value="<?php echo $tag->id; ?>" <?php selected($selected_tag, $tag->id); ?>><?php echo $tag->title; ?></option>
        <?php } ?>
    </select>
    <p id="campaignkit_fluentcrm_tag-description" class="description">Tag to mark FluentCRM contact's with invalid email addresses.</p>
    <?php
}

/* Admin init */
add_action( 'admin_init', 'campaignkit_settings_init' );

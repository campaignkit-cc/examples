<?php

/*
Plugin Name:  CampaignKit
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

const API_KEY = "<<CAMPAIGNKIT API KEY>>";
const FLUENT_INVALID_TAG_ID = 1; // FLuent tag id to use to mark invalid email addresses.

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
    $curl = curl_init();
    $url = "https://api.campaignkit.cc/v1/email/validate";

    curl_setopt_array( $curl, array(
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            'Content-Type: application/json', 
            'Authorization: Bearer ' . API_KEY),
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
            $contact->attachTags( [FLUENT_INVALID_TAG_ID] );
            $contact->save();
        }
    }
}

add_action( 'fluentcrm_contact_created', 'validate_email', 10, 1 );

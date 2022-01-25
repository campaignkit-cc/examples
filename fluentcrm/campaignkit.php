<?php

/** 
 * @wordpress-plugin
 * Plugin Name:  CampaignKit - Email Validation for FluentCRM
 * Version    :  1.0
 * Description:  Validates the email address for newly created contacts in FluentCRM
 * Author     :  campaignkit.cc
 * Author URI :  https://campaignkit.cc/
 * License    :  GPLv3
 * License URI:  https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:  campaignkit-plugin
 */

//=================================================
// Security: Abort if this file is called directly
//=================================================
if ( !defined("WPINC") ) { 
    die;
}

function campaignkit_validate_email( $contact ) {
    $api_key = get_option( "campaignkit_apikey" );
    $fluentcrm_tag = (int) get_option("campaignkit_fluentcrm_tag", "-1");

    if ( $fluentcrm_tag == -1 ) {
        return;
    }

    $curl = curl_init();
    $url = "https://api.campaignkit.cc/v1/email/validate";

    curl_setopt_array( $curl, array(
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => array(
            "Content-Type: application/json", 
            "Authorization: Bearer " . $api_key),
        CURLOPT_POSTFIELDS => "{\"emails\": [\"$contact->email\"]}",
        CURLOPT_RETURNTRANSFER => true
    ) );

    $response = curl_exec( $curl );
    $err = curl_error( $curl );

    curl_close( $curl );
    
    if ( ! $err ) {
        $responseObj = json_decode( $response );
        
        if ( $responseObj->results[0]->result->score < 5 ) {
            $contact->attachTags( [$fluentcrm_tag] );
            $contact->save();
        }
    }
}

add_action( "fluentcrm_contact_created", "campaignkit_validate_email", 10, 1 );

// ----------------------------------------------------------------
// Settings
// ----------------------------------------------------------------

/* Settings Init */
function campaignkit_settings_init(){

    /* Register Settings */
    register_setting(
        "general",             // Options group
        "campaignkit_apikey"   // Option name/database
    );

     /* Create settings section */
     add_settings_section(
        "campaignkit-section-id",          // Section ID
        "CampaignKit - Email Validation for FluentCRM",    // Section title
        "campaignkit_section_description", // Section callback function
        "general"                          // Settings page slug
    );

    /* Create settings field */
    add_settings_field(
        "campaignkit-apikey-field-id",       // Field ID
        __("API Key", "campaignkit-plugin"), // Field title 
        "campaignkit_field_callback",        // Field callback function
        "general",                           // Settings page slug
        "campaignkit-section-id"             // Section ID
    );

    if ( function_exists( "FluentCrmApi") ) {
        register_setting(
            "general",                       // Options group
            "campaignkit_fluentcrm_tag"      // Option name/database
        );

        /* Create settings field */
        add_settings_field(
            "campaignkit-fluentcrm-tag-field-id",       // Field ID
            __("FluentCRM Tag", "campaignkit-plugin"),  // Field title 
            "campaignkit_fluentcrm_tag_callback",       // Field callback function
            "general",                                  // Settings page slug
            "campaignkit-section-id"                    // Section ID
        );
    }
}

/* Setting Section Description */
function campaignkit_section_description() {
    if ( ! function_exists( "FluentCrmApi") ) {
        _e("No FluentCRM installation found. CampaignKit's plugin works with FluentCRM only.", "campaignkit-plugin");
    }
}

/* Settings Field Callback */
function campaignkit_field_callback(){
    ?>
    <input id="campaignkit_apikey" type="text" value="<?php echo get_option("campaignkit_apikey"); ?>" name="campaignkit_apikey"> 
    <p id="campaignkit_apikey-description" class="description">
        <?php _e("CampaignKit's API Key.", "campaignkit-plugin"); ?>
    </p>
    <?php
}

function campaignkit_fluentcrm_tag_callback() {
    $tagApi = FluentCrmApi("tags");    
    $allTags = $tagApi->all(); 
    $selected_tag = (int) get_option("campaignkit_fluentcrm_tag", "-1");

    ?>
    <select id="campaignkit_fluentcrm_tag" name="campaignkit_fluentcrm_tag"> 
        <option value="-1" <?php selected($selected_tag, $tag->id); ?>></option>
        <?php
            foreach ($allTags as $tag) {
        ?>    <option value="<?php echo $tag->id; ?>" <?php selected($selected_tag, $tag->id); ?>><?php echo $tag->title; ?></option>
        <?php } ?>
    </select>
    <p id="campaignkit_fluentcrm_tag-description" class="description">
        <?php _e("Tag to mark FluentCRM contact's with invalid email addresses.", "campaignkit-plugin"); ?>
    </p>
    <?php
}

/* Admin init */
add_action( "admin_init", "campaignkit_settings_init" );

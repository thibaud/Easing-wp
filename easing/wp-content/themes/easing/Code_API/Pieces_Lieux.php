<?php

function Router_piece($post, $post_id, $label, $token_access): void {
    error_log("");
    error_log("===================================================");
    error_log("              Router Piece Lieu");
    error_log("===================================================");
    error_log("");

    $Post_Status = $post->post_status;

    $Piece_ID = $post->post_title;
    $node_ID = get_piece_id($Piece_ID, $label, $token_access);

    error_log("===================================");
    error_log("              Info");
    error_log("Post Status: ".$Post_Status);
    error_log("Label: ".$label);
    error_log("Piece_ID: ".$Piece_ID);
    error_log("Node ID: ".$node_ID);
    error_log("===================================");
    error_log("");


    if ($node_ID < 1) {
        create_piece($post, $post_id, $label, $token_access);
    }
    else {
        if ($Post_Status == "publish"){
            update_piece($node_ID, $post_id, $label, $token_access);
        } elseif ($Post_Status == "trash") {
            delete_piece($node_ID, $token_access, $label);
        } else {
            // If you want to do something on draft
            error_log("Draft");
        }
    }
}

function get_piece_id($Piece_ID, $label, $token_access){
    error_log("");
    error_log("=========================================");
    error_log("              Get Piece ID");
    error_log("=========================================");
    error_log("ID: ".$Piece_ID);
    error_log("token: ".$token_access);

    $ID_url = "/graph/read_node_collection";

    error_log("URL: ".$GLOBALS['API_URL'].$ID_url);

    $search_node_property = "ID_".ucfirst($label);

    $response = wp_remote_get(
        $GLOBALS['API_URL'].$ID_url."?search_node_property=".$search_node_property."&node_property_value=".urlencode($Piece_ID), array(
            'headers' => array(
                'Content-Type' => 'application/x-www-form-urlencoded',
                'Accept' => 'application/json',
                'Authorization' => 'bearer '.$token_access
            ),
        )
    );

    if( is_wp_error( $response ) ) {
        error_log("Error");
    }

//    error_log(print_r($response, true));

    $pieces = json_decode($response['body'], true);


    if (count($pieces['nodes']) > 0) {
        $piece = $pieces['nodes'][0];
        $node_ID = $piece['node_id'];

        error_log("========================================= Get Piece ID");
        error_log("");
        error_log("");

        return $node_ID;
    } else {
        error_log("========================================= Get Piece ID");
        error_log("");
        error_log("");

        return -1;
    }

}

function create_piece($post, $post_id, $label, $token_access):void {
    error_log("");
    error_log("=========================================");
    error_log("              Create Piece");
    error_log("=========================================");
    error_log("post_id: ".$post_id);
    error_log("label: ".$label);
    error_log("token: ".$token_access);
    error_log("Piece_ID: ".$post->post_title);
    $Logement_ID = $post->post_title;

    // =================================================================================================================
    //                                                  Create Request
    // =================================================================================================================

    $create_url = "/graph/create_node";

    $complete_url = $GLOBALS['API_URL'].$create_url."?label=".$label;

//    error_log("complete url: ".$complete_url);

    $fields = get_fields($post_id);
    error_log(print_r($fields, true));
    if (gettype($fields) != "array") {
        return;
    }

    $create_body = array(
        'ID_'.ucfirst($label)=>$Logement_ID,
        'ID_Post'=>$post_id,
    );

    $field_to_skip = array(
        "equipements",
        "adaptations",
        "ouvertures"
    );

    $create_body = body_builder($create_body, $fields, $field_to_skip);

    $update_header = array(
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'Authorization' => 'bearer '.$token_access
    );

    $args = array(
        'headers' => $update_header,
        'body' => json_encode($create_body),
        'method' => 'POST'
    );

    $create_response = wp_remote_request($complete_url, $args);

    if( is_wp_error( $create_response ) ) {
        error_log("Error");
    }

    error_log("result: ".print_r($create_response, true));
    error_log("========================================= Create");
    error_log("");
    error_log("");
}

function update_piece($node_ID, $post_id, $label, $token_access):void {
    error_log("");
    error_log("=====================================");
    error_log("            Edit Piece");
    error_log("=====================================");

    // =================================================================================================================
    //                                                  Update Request
    // =================================================================================================================

    $update_url = "/graph/update/".$node_ID;

    $fields = get_fields($post_id);

    $update_body = array(
        'ID_Post'=>$post_id
    );

    $field_to_skip = array(
        "equipements",
        "adaptations",
        "ouvertures"
    );

    $update_body = body_builder($update_body, $fields, $field_to_skip);

    $update_header = array(
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'Authorization' => 'bearer '.$token_access
    );

    $args = array(
        'headers' => $update_header,
        'body' => json_encode($update_body),
        'method' => 'PUT'
    );

    $update_response = wp_remote_request($GLOBALS['API_URL'].$update_url, $args);

    if( is_wp_error( $update_response ) ) {
        error_log("Error");
    }

    # Équipements
    update_relationship(
        $node_ID,
        $label,
        get_field('equipements', $post_id),
        'ID_Equipement',
        'equipements',
        "equipement_p",
        $token_access
    );

    # Adaptations
    update_relationship(
        $node_ID,
        $label,
        get_field('adaptations', $post_id),
        'ID_Adaptation',
        'adaptation',
        'adaptation_p',
        $token_access
    );

    # Ouvertures
    update_relationship(
        $node_ID,
        $label,
        get_field('ouvertures', $post_id),
        'ID_Ouvertures',
        'ouvertures',
        'has_ouvertures',
        $token_access
    );

//    error_log('fields: '.print_r(get_fields($post_id), true));

//    error_log("result: ".print_r($update_response, true));
    error_log("========================================= Edit Piece");
    error_log("");
    error_log("");
}

function delete_piece($node_id, $token_access):void {
    error_log("");
    error_log("=========================================");
    error_log("              Delete Piece");
    error_log("=========================================");

    // Delete all relationship linked to the logement
    $DEL_All_R_URL = "/graph/delete_all_relationship/$node_id";

    $header = array(
        'Content-Type'=>'application/json',
        'Accept' => 'application/json',
        'Authorization' => 'bearer '.$token_access
    );

    $args = array(
        'headers' => $header,
        'method' => 'POST'
    );

    $relationship_response = wp_remote_request( $GLOBALS['API_URL'].$DEL_All_R_URL, $args);
    if( is_wp_error($relationship_response) ) {
        error_log("Error");
    }


    // Delete the room

    $complete_url = $GLOBALS['API_URL']."/graph/delete/".$node_id;

    $delete_header = array(
        'Content-Type' => 'application/json',
        'Accept' => 'application/json',
        'Authorization' => 'bearer '.$token_access
    );

    $args = array(
        'headers' => $delete_header,
        'method' => 'POST'
    );

    $delete_response = wp_remote_request($complete_url, $args);

    if( is_wp_error($delete_response) ) {
        error_log("Error");
    }

    error_log("Delete Result: ".print_r(json_decode($delete_response['body'], true), true));

    error_log("========================================= Delete");
    error_log("");
    error_log("");

}



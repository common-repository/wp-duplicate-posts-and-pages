<?php
/**

Plugin Name: WP Duplicate Posts and Pages
Plugin URI: https://www.eastsidecode.com
Description: Duplicate WordPress Posts easily from the admin panel. 
Author: Louis Fico
Version: 1.1
Author URI: eastsidecode.com

*/


function escode_duplicate_post_as_draft(){
	global $wpdb;
	if (! ( isset( $_GET['post']) || isset( $_POST['post'])  || ( isset($_REQUEST['action']) && 'escode_duplicate_post_as_draft' == $_REQUEST['action'] ) ) ) {
		wp_die('No post to duplicate has been supplied!');
	}
 
	/*
	 * Nonce verification
	 */
	if ( !isset( $_GET['duplicate_nonce'] ) || !wp_verify_nonce( $_GET['duplicate_nonce'], basename( __FILE__ ) ) )
		return;
 

	// get the origin post id
	$post_id = (isset($_GET['post']) ? absint( $_GET['post'] ) : absint( $_POST['post'] ) );
	

	$post = get_post( $post_id );
 

	// copies the current author as well
	$current_user = wp_get_current_user();
	$new_post_author = $post->post_author;

	// add (Copy) to the duolicate post title
	$newPostTitle = $post->post_title . " (Copy)";
 
	/*
	 * if post data exists, create the post duplicate
	 */
	if (isset( $post ) && $post != null) {
 
		/*
		 * new post data array
		 */
		$args = array(
			'comment_status' => $post->comment_status,
			'ping_status'    => $post->ping_status,
			'post_author'    => $new_post_author,
			'post_content'   => $post->post_content,
			'post_excerpt'   => $post->post_excerpt,
			'post_name'      => $post->post_name,
			'post_parent'    => $post->post_parent,
			'post_password'  => $post->post_password,
			'post_status'    => 'draft',
			'post_title'     => $newPostTitle,
			'post_type'      => $post->post_type,
			'to_ping'        => $post->to_ping,
			'menu_order'     => $post->menu_order
		);
 
		// create the new post
		$new_post_id = wp_insert_post( $args );
 
		// add in the taxonomies 
		$taxonomies = get_object_taxonomies($post->post_type); // returns array of taxonomy names for post type, ex array("category", "post_tag");
		foreach ($taxonomies as $taxonomy) {
			$post_terms = wp_get_object_terms($post_id, $taxonomy, array('fields' => 'slugs'));
			wp_set_object_terms($new_post_id, $post_terms, $taxonomy, false);
		}
 


		  // Duplicate all the post meta

		 $meta_keys = get_post_custom_keys($post_id);

		  foreach ( $meta_keys as $meta_key ) {
			$meta_values = get_post_custom_values($meta_key, $post_id);
			foreach ($meta_values as $meta_value) {
				$meta_value = maybe_unserialize($meta_value);
				add_post_meta($new_post_id, $meta_key, escode_duplicate_post_wp_slash($meta_value));
			}
		  } // end meta keys loop

 
 
		// redirect the user from the post screen to the new draft screen
		wp_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
		exit;
	} else { // post didn't exist
		wp_die('Post creation failed, could not find original post: ' . $post_id);
	}
}
add_action( 'admin_action_escode_duplicate_post_as_draft', 'escode_duplicate_post_as_draft' );
 

function escode_duplicate_post_action_row( $post ) {

	$post_type = get_post_type_object( $post->post_type );
	$label = "Duplicate " .   $post_type->labels->singular_name;

	if ($post_type->labels->singular_name !== "Field Group") {

	return '<a href="' . wp_nonce_url('admin.php?action=escode_duplicate_post_as_draft&post=' . $post->ID, basename(__FILE__), 'duplicate_nonce' ) . '" title="Duplicate this item" rel="permalink">' . $label. '</a>';

	}


}


function escode_duplicate_post_addslashes_deep( $value ) {
	if (function_exists('map_deep')){
		return map_deep( $value, 'escode_duplicate_post_addslashes_to_strings_only' );
	} else {
		return wp_slash( $value );
	}
}

function escode_duplicate_post_addslashes_to_strings_only( $value ) {
	return is_string( $value ) ? addslashes( $value ) : $value;
}

function escode_duplicate_post_wp_slash( $value ) { 
	return escode_duplicate_post_addslashes_deep( $value ); 
} 
		

// add to the post row's actions links 


add_filter( 'post_row_actions', function( $actions, $post ) {

	if (current_user_can('edit_posts')) {

			$actions['duplicate'] = escode_duplicate_post_action_row($post);

	}

	return $actions;

}, 10, 2 );


add_filter( 'page_row_actions', function( $actions, $post ) {

	if (current_user_can('edit_posts')) {
		$actions['duplicate'] = escode_duplicate_post_action_row($post);
	}

	return $actions;

}, 10, 2 );
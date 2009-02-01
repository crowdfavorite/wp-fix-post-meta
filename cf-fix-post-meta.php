<?php
/*
Plugin Name: Crowd Favorite Fix Post Meta
Plugin URI: http://crowdfavorite.com
Description: This plugin patches strings in post meta and in post GUIDs in a serialization-friendly way. Useful for changing all hard-coded references to a url or filesystem path when a WP build is migrated from one place to another, and especially useful when those strings live in PHP-serialized data, so a textual search-and-replace in the SQL dump won't work. Configure this plugin by editing the arrays at the top of its php file. Note: this plugin depends on cf-compat.php.
Version: 1.2
Author: Crowd Favorite
Author URI: http://crowdfavorite.com
*/


/**
 * Populate this list using the cffx_postmeta_keys filter
 */
$cffx_postmeta_keys = array();

/**
 * List here all the meta keys you want to patch. These will be updated along with any post revision or attachment's guid.
 */
// $cffx_postmeta_keys = array(
// 	'_wp_attached_file',
// 	'_wp_attachment_metadata',
// 	
// 	'cfpm_landing_front_page_image',
// 	'cfpm_project_category_category_images',
// );

/**
 * Populate this list using the cffx_old_strings filter
 */
$cffx_old_strings = array();

/**
 * List here the strings to search for.
 * 
 * Note that earlier strings cannot not be substrings of later strings.
 * in other words, 'bcd' has to come after 'abcde' in this list.
 */
// $cffx_old_strings = array(
// 	'/home/cfdev23.com/web/public/wordpress_2.6.1/',
// 	'/home/cfdev23.com/web/public/',
// 	'http://cfdev23.com/wordpress_2.6.1/',
// 	'http://cfdev23.com/'
// );

/**
 * Populate this list using the cffx_new_strings filter
 */
$cffx_new_strings = array();

/**
 * List here the replacements for the above strings. Their order must match
 * the order in $cffx_old_strings. Be sure to match trailing slashes!
 */
// $cffx_new_strings = array(
// 	'/www/mba-architecture.com/wordpress/',
// 	'/www/mba-architecture.com/',
// 	'http://test.mba-architecture.com/wordpress/',
// 	'http://test.mba-architecture.com/'
// );

function cffx_set_data() {
	global $cffx_postmeta_keys, $cffx_old_strings, $cffx_new_strings;
	$cffx_postmeta_keys = apply_filters('cffx_postmeta_keys', $cffx_postmeta_keys);
	$cffx_old_strings = apply_filters('cffx_old_strings', $cffx_old_strings);
	$cffx_new_strings = apply_filters('cffx_new_strings', $cffx_new_strings);
}
add_action('init', 'cffx_set_data');

function cffx_admin_menu() {
	if (current_user_can('manage_options')) {
		add_options_page(
			__('CF Fix Postmeta')
			, __('CF Fix Postmeta')
			, 10
			, basename(__FILE__)
			, 'cffx_settings_form'
		);
	}
}
add_action('admin_menu', 'cffx_admin_menu');

function cffx_request_handler() {
	if (!empty($_GET['cf_action'])) {
		switch ($_GET['cf_action']) {
			case 'cffx_admin_js':
				cffx_admin_js();
				break;
		}
	}
	if (!empty($_POST['cf_action'])) {
		switch ($_POST['cf_action']) {
			case 'cffx_fix_meta':
				if(!is_numeric($_POST['cffx_batch_increment']) || !is_numeric($_POST['cffx_batch_offset'])) {
					echo cf_json_encode(array('result'=>false,'message'=>'Invalid quantity or offset'));
					exit();
				}
				$increment = (int) $_POST['cffx_batch_increment'];
				$offset = (int) $_POST['cffx_batch_offset'];
				cffx_fix_meta($increment,$offset);
				die();
				break;

			case 'cffx_fix_attachment_guids':
				if(!is_numeric($_POST['cffx_batch_increment']) || !is_numeric($_POST['cffx_batch_offset'])) {
					echo cf_json_encode(array('result'=>false,'message'=>'Invalid quantity or offset'));
					exit();
				}
				$increment = (int) $_POST['cffx_batch_increment'];
				$offset = (int) $_POST['cffx_batch_offset'];
				cffx_fix_attachment_guids($increment,$offset);
				die();
				break;
		}
	}
}
add_action('init', 'cffx_request_handler');

wp_enqueue_script('jquery');
wp_enqueue_script('cffx_admin_js', trailingslashit(get_bloginfo('url')).'?cf_action=cffx_admin_js', 'jquery');
function cffx_admin_js() {
	header('Content-type: text/javascript');
?>
jQuery(function() {

	jQuery('#cffx_fix_meta input').click(function(){
		cffx_fix_postmeta('cffx_fix_meta');
		return false;
	});
	/*
	jQuery('#cffx_fix_attachment_metadata').submit(function(){
		cffx_fix_postmeta('cffx_fix_attachment_metadata');
		return false;
	});
	*/
	jQuery('#cffx_fix_attachment_guids input').click(function() {
		cffx_fix_postmeta('cffx_fix_attachment_guids');
		return false;
	});
	
	function cffx_fix_postmeta(metafix) {
		var batch_offset = 0;
		var batch_increment = 100;
		var finished = false;
		
		params = {'cffx_rebuild_indexes':'1',
				  'cffx_rebuild_offset':'0'
				 }
		cffx_update_status('Processing post meta');
		
		// process posts
		while(!finished) {
			response = cffx_batch_request(batch_offset,batch_increment,metafix);
			if(!response.result && !response.finished) {
				cffx_update_status('Post Meta processing failed. Server said: ' + response.message);
				return;
			}
			else if(!response.result && response.finished) {
				cffx_update_status('Post Meta processing complete.');
				finished = true;
			}
			else if(response.result) {
				cffx_update_status(response.message);
				batch_offset = (batch_offset + batch_increment);
			}
		}
	}
	
	// make a request
	function cffx_batch_request(offset,increment,metafix) {
		var r = jQuery.ajax({type:'post',
								url:'index.php',
								dataType:'json',
								async:false,
								data:'cf_action='+metafix+'&cffx_batch_offset=' + offset + '&cffx_batch_increment=' + increment
							}).responseText;
		var j = eval( '(' + r + ')' );
		return j;
	}
	
	// handle the building of indexes
	function cffx_index_build_callback(response) {
		if(response.result) {
			cffx_update_status('Post Meta Fix Complete');
		}
		else {
			cffx_update_status('Failed to fix post meta');
		}
	}
	
	// update status message
	function cffx_update_status(message) {
		if(!jQuery('#index-status').hasClass('updated')) {
			jQuery('#index-status').addClass('updated');
		}
		jQuery('#index-status p').html(message);
	}
	
});
<?php
	die();
}
function cffx_patch_string($string, $old_list, $new_list) {
	$result = $string;
	for ($i = 0; $i < count($old_list); $i++) {
		$result = str_replace($old_list[$i], $new_list[$i], $result);
	}
	return $result;
}

function cffx_get_meta_keys() {
	global $cffx_postmeta_keys;
	return implode(',', array_map(create_function('$key', 'return "\'".$key."\'";'), $cffx_postmeta_keys));
}

function cffx_fix_meta($increment=0,$offset=0) {
	global $wpdb;
	global $cffx_old_strings, $cffx_new_strings;
	$rows = $wpdb->get_results("SELECT post_id, meta_value, meta_key FROM $wpdb->postmeta WHERE meta_key IN (".cffx_get_meta_keys().") LIMIT ".$offset.",".$increment);	
	foreach($rows as $row) {
		$new_value = cffx_patch_string($row->meta_value, $cffx_old_strings, $cffx_new_strings);
		update_post_meta($row->post_id, $row->meta_key, $new_value, $row->meta_value);
	}
	$total_so_far = $increment+$offset;
	$total_count = cffx_count_meta();
	if($total_so_far >= $total_count) {
		echo cf_json_encode(array('result'=>false,'finished'=>true,'message'=>true));
	}
	else {
		$message = 'Processing meta values, '.number_format(($total_so_far / $total_count) * 100, 1).'% done';
		echo cf_json_encode(array('result'=>true,'finished'=>false,'message'=>$message));
	}
	exit();
}

function cffx_fix_attachment_guids($increment=0,$offset=0) {
	global $wpdb;
	global $cffx_old_strings, $cffx_new_strings;
	$attacheds = $wpdb->get_results("SELECT ID,guid FROM $wpdb->posts WHERE post_type = 'attachment' OR post_type = 'revision' LIMIT ".$offset.",".$increment);
	foreach($attacheds as $attached) {
		$data = maybe_unserialize($attached->guid);
		$result = cffx_patch_string($data, $cffx_old_strings, $cffx_new_strings);
		$wpdb->query("UPDATE $wpdb->posts SET guid = '".$result."' WHERE ID = '".$attached->ID."'");
	}
	$total_so_far = $increment+$offset;
	$total_count = cffx_count_post_attachments();
	if($total_so_far >= $total_count) {
		echo cf_json_encode(array('result'=>false,'finished'=>true,'message'=>true));
	}
	else {
		$message = 'Processing post attached files, '.number_format(($total_so_far / $total_count) * 100, 1).'% done.';
		echo cf_json_encode(array('result'=>true,'finished'=>false,'message'=>$message));
	}
	exit();
}

function cffx_count_meta() {
	global $wpdb;
	$attached = $wpdb->get_results("SELECT * FROM $wpdb->postmeta WHERE meta_key IN (".cffx_get_meta_keys().")");
	return count($attached);
}

function cffx_count_post_attachments() {
	global $wpdb;
	$attached = $wpdb->get_results("SELECT * FROM $wpdb->posts WHERE post_type = 'attachment' OR post_type = 'revision'");
	return count($attached);
}

function cffx_settings_form() {
	global $wpdb;
	print('
	<div id="wpbody">
		<div class="wrap">
			<h2>'.__('Crowd Favorite Fix MetaData').'</h2>
			<form id="cffx_fix_meta" name="cffx_fix_meta" action="" method="post">
				<p class="submit" style="border-top: none;">
					<input type="submit" name="submit" value="'.__('Fix Meta Values').'" />
				</p>
			</form>
			<!--
			<form id="cffx_fix_attachment_metadata" name="cffx_fix_attachment_metadata" action="" method="post">
				<p class="submit" style="border-top: none;">
					<input type="submit" name="submit" value="'.__('Fix Attachment Meta').'" />
				</p>
			</form>
			-->
			<form id="cffx_fix_attachment_guids" name="cffx_fix_attachment_metadata" action="" method="post">
				<p class="submit" style="border-top: none;">
					<input type="submit" name="submit" value="'.__('Fix Post Attachment GUIDs').'" />
				</p>
			</form>
			<div id="index-status" style="margin-top: 20px;">
				<p></p>
			</div>
		</div>
	</div>
	');
}

?>
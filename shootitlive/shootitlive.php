<?php
/*
Plugin Name: Shootitlive
Plugin URI: http://shootitlive.com/shootitlive_wp
Description: Plugin for embedding live feeds from Shootitlive.com
Author: Martin Levy
Version: 1.0
Author URI: http://shootitlive.com
*/

register_activation_hook(__FILE__, 'silp_add_defaults');
register_uninstall_hook(__FILE__, 'silp_delete_plugin_options');
add_action('admin_init', 'silp_init' );
define('apiBaseUrl', 'http://cdn.api.shootitlive.com');

// Delete options table entries ONLY when plugin deactivated AND deleted
function silp_delete_plugin_options() {
	delete_option('silp_options');
}

// Define default option settings
function silp_add_defaults() {
	$tmp = get_option('silp_options');
    if(($tmp['chk_default_options_db']=='1')||(!is_array($tmp))) {
		delete_option('silp_options'); // so we don't have to reset all the 'off' checkboxes too! (don't think this is needed but leave for now)
		$arr = array(
			"client" => "Enter Organisation Name",
			"token" => "Enter API Key"
		);
		update_option('silp_options', $arr);
	}
}

// Init plugin options to white list our options
function silp_init(){
	register_setting( 'silp_plugin_options', 'silp_options', 'silp_validate_options' );
}

// Add meta_box
add_action( 'add_meta_boxes', 'silp_meta_box_add' );
function silp_meta_box_add() {
	add_meta_box( 'silp-meta-box-id', 'Shootitlive', 'silp_meta_box_cb', 'post', 'side', 'high' );
}


//API call & drop down menu
function silp_meta_box_cb( $post ) {
	$values = get_post_custom( $post->ID );
	$selected = isset( $values['silp_meta_box_select'] ) ? esc_attr( $values['silp_meta_box_select'][0] ) : '';
	$options = get_option('silp_options');
    $silp_client = $options['client'];
    $silp_token = $options['token'];

	if ($options['client'] =='Enter Organisation Name' or $options['client'] =='') {
		//nothing here
		echo "Please, enter API settings";
	}

	else {
		$silp_call = apiBaseUrl."/v1/projects/?client=".$silp_client."&token=".$silp_token."&embed=true";
		$json_data2 = file_get_contents($silp_call);
		$obj2=json_decode($json_data2, true);

	}

	wp_nonce_field( 'silp_meta_box_nonce', 'meta_box_nonce' );


	echo "<div style='float:left;'>Project:</div>";

	echo "<div style='margin-left:60px;'>";
	echo "<select name='silp_meta_box_select' id='silp_meta_box_select'>\n\n";
	echo "\n\n<option>Select a project:</option>\n\n";

	foreach($obj2[$silp_client] as $p) {
		echo "<option value='".$p[project]."'".selected( $selected, $p[project]).">";
		//limit project description to 23chars, so it fits in dropdown
		$description = (strlen($p[description]) > 23) ? substr($p[description],0,19).'...' : $p[description];
		echo $description;
		echo "</option>\n";
		echo $p[embed]."\n\n";
	}

	echo "</select>";
	echo "</div>\n\n";

	echo "<p>\n";
	echo "<div style='float:left;'>Options:</div>\n";
	foreach ($obj2[silp_options] as $key => $value) {
		$hiddenArr = explode(',', $obj2[silp_options][hidden]); //convert "hidden" to an array
		if(!in_array($key, (array)$hiddenArr)) { //only list not hidden silp_options

			if(is_bool($value) || $key == "ratio") { //only include key with true/false value and the ratio key

				if($key !="ratio") {
					echo "<div style='margin-left:60px;'>\n";
					$checked = ($value == true) ? "checked" : "";
					echo "<input type='checkbox' name='".$key."' value='".$value."' ".$checked."> ".$key."\n";
					echo "</div>\n";
				}

				if($key =="ratio") {
					/*
					Instead of printing the ratio dropdown immediately
					we print the output below to make sure it displays
					as the last option - this is just for looks
					*/
					if($value == 1.5) $valueDescription = "Standard";
					if($value == 1.7777777778) $valueDescription = "Wide";
					if($value == 1) $valueDescription = "Square";

					$ratioHtml = "<div style='margin-left:60px;'>\n";

					$ratioHtml .= "<select name='silp_ratio_box_select' id='silp_ratio_box_select'>\n\n";
					$ratioHtml .= "<option value='".$value."' selected='selected'>".$valueDescription."</option>\n";
					if($value != 1.5) $ratioHtml .= "<option value='1.5'>Standard</option>\n";
					if($value != 1.7777777778) $ratioHtml .= "<option value='1.7777777778'>Wide</option>\n";
					if($value != 1) $ratioHtml .= "<option value='1'>Square</option>\n";
					$ratioHtml .= "</select>";
					$ratioHtml .= " ".$key."\n";

					$ratioHtml .= "</div>\n";
				}

			}
 		}
	}
	if($ratioHtml) echo $ratioHtml;
	echo "</p>\n\n";

}


// SAVE the meta_box value
add_action( 'save_post', 'silp_meta_box_save' );
function silp_meta_box_save( $post_id )
{
	// Bail if we're doing an auto save
	if( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;

	// if our nonce isn't there, or we can't verify it, bail
	if( !isset( $_POST['meta_box_nonce'] ) || !wp_verify_nonce( $_POST['meta_box_nonce'], 'silp_meta_box_nonce' ) ) return;

	// if our current user can't edit this post, bail
	if( !current_user_can( 'edit_post' ) ) return;

	// now we can actually save the data
	$allowed = array(
		'a' => array( // on allow a tags
			'href' => array() // and those anchords can only have href attribute
		)
	);

	// Probably a good idea to make sure your data is set
	if( isset( $_POST['silp_meta_box_text'] ) )
		update_post_meta( $post_id, 'silp_meta_box_text', wp_kses( $_POST['silp_meta_box_text'], $allowed ) );

	if( isset( $_POST['silp_meta_box_select'] ) )
		update_post_meta( $post_id, 'silp_meta_box_select', esc_attr( $_POST['silp_meta_box_select'] ) );

	// This is purely my personal preference for saving checkboxes
	$chk = ( isset( $_POST['silp_meta_box_check'] ) && $_POST['silp_meta_box_check'] ) ? 'on' : 'off';
	update_post_meta( $post_id, 'silp_meta_box_check', $chk );
}



//[silp]
function silp_embed()  {

	global $post;
	$project = get_post_meta($post->ID, 'silp_meta_box_select', true);
	$testArr = get_post_meta($post->ID, 'silp_meta_box_select', true);
	$options = get_option('silp_options');
	$silp_client = $options['client'];
	$silp_token = $options['token'];
	// echo $silp_token;
	$silp_call = apiBaseUrl."/v1/projects/?client=".$silp_client."&token=".$silp_token."&embed=true&project=".$project;
	$json_data2 = file_get_contents($silp_call);
	$obj3=json_decode($json_data2, true);

	return $obj3[$silp_client][0][embed];

}

add_shortcode( 'silp', 'silp_embed' );

//Hook the_content
add_filter('the_content', 'silp_content');

function silp_content($content = '') {
	$content .= do_shortcode("[silp]");
	return $content;
}

//Settings page content
add_action("admin_menu","silp_admin_menu");
function silp_admin_menu(){
	add_menu_page(/*page title*/'Dashboard', /*Menu Title*/'Shootitlive',/*access*/'administrator', 'shootitlive', 'silp_dashboard_page',plugins_url('sil.ico', __FILE__));
	add_submenu_page( 'shootitlive', 'Settings', 'Settings', 'administrator', 'dashboard', 'silp_settings_page' );
}

function silp_settings_page() { /*handler for above menu item*/

	?>
	<div class="wrap">
		<!-- Display Plugin Icon, Header, and Description -->
		<div class="icon32" id="icon-options-general"><br></div>
		<h2>Shootitlive</h2>

		<!-- Beginning of the Plugin Options Form -->
		<form method="post" action="options.php">
			<?php settings_fields('silp_plugin_options'); ?>
			<?php $options = get_option('silp_options'); ?>

			<!-- Table Structure Containing Form Controls -->
			<!-- Each Plugin Option Defined on a New Table Row -->
			<table class="form-table">
				<!-- Textbox Control -->
				<tr>
					<th scope="row">Enter Organisation Name:</th>
					<td>
						<input type="text" size="57" name="silp_options[client]" value="<?php echo $options['client']; ?>" />
					</td>
				</tr>

				<tr>
					<th scope="row">Enter API Key:</th>
					<td>
						<input type="text" size="57" name="silp_options[token]" value="<?php echo $options['token']; ?>" />
					</td>
				</tr>

			</table>
			<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>
	</div>
	<?
}


function silp_dashboard_page() { /*handler for above menu item*/
	?>
	<iframe src="http://admin.shootitlive.com/projects" frameBorder="0" marginwidth="0px" marginheight="0px" scrolling="yes" width="100%" height="100%"></iframe>
	<?
}

// Sanitize and validate input. Accepts an array, return a sanitized array.
function silp_validate_options($input) {
	 // strip html from textboxes
	$input['client'] = wp_filter_nohtml_kses($input['client']); // Sanitize textarea input (strip html tags, and escape characters)
	$input['token'] = wp_filter_nohtml_kses($input['token']); // Sanitize textbox input (strip html tags, and escape characters)
	return $input;
}


?>
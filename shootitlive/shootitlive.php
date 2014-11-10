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
	$selected = isset( $values['silp_project'] ) ? esc_attr( $values['silp_project'][0] ) : '';
	$silp_params = isset( $values['silp_params'] ) ? $values['silp_params'][0] : '';
	$options = get_option('silp_options');
    $silp_client = $options['client'];
    $silp_token = $options['token'];


    /*
    If the post already have a silp embedded, we look if there's
    custom silp_options present and then store them in the $silp_paramsDB array
    as we need to make current silp adopt those stored options and the
    checkboxes/dropdown have those values checked.
    */
	if($silp_params) {
		$silp_paramsDB = explode('&',$silp_params);
		foreach ($silp_paramsDB as $key) {
			 if($key) {
			 	$key = explode('=',$key);
			 	$silp_paramsDB[$key[0]] = $key[1];
			 }
		}
	}

	if ($options['client'] =='Enter Organisation Name' or $options['client'] =='') {
		//nothing here
		echo "Please, enter API settings";
	}

	else {
		$silp_call = apiBaseUrl."/v1/projects/?client=".$silp_client."&token=".$silp_token."&embed=true";
		if($silp_params) $silp_call .= $silp_params; //add the stored DB silp_params to api call
		$json_data2 = file_get_contents($silp_call);
		$obj2=json_decode($json_data2, true);

	}

	wp_nonce_field( 'silp_meta_box_nonce', 'meta_box_nonce' );


	echo "<div style='float:left;'>Project:</div>";

	echo "<div style='margin-left:60px;'>";
	echo "<select name='silp_project' id='silp_project' onchange='goEmbed();'>\n\n";
	echo "\n\n<option value='0'>Select a project:</option>\n\n";

	foreach($obj2[$silp_client] as $p) {
		echo "<option value='".$p[project]."'".selected( $selected, $p[project]).">";
		//limit project description to 23chars, so it fits in dropdown
		$description = (strlen($p[description]) > 23) ? substr($p[description],0,19).'...' : $p[description];
		echo $description;
		echo "</option>\n";
		if($selected == $p[project]) $embedcode = $p[embed];
	}

	echo "</select>";
	echo "</div>\n\n";
	$checkboxHtml;
	$ratioHtml;

	foreach ($obj2[silp_options] as $key => $value) {
		$hiddenArr = explode(',', $obj2[silp_options][hidden]); //convert "hidden" to an array
		if(!in_array($key, (array)$hiddenArr)) { //only list not hidden silp_options

			if(is_bool($value) || $key == "ratio") { //only include key with true/false value and the ratio key

				if($key !="ratio") {
					$checkboxHtml .= "<div style='margin-left:60px;'>\n";
					$checked = ($value) ? "checked" : "";
					$checkboxHtml .= "<input type='checkbox' id='".$key."' name='".$key."' onchange='goEmbed();' value='".$value."' ".$checked."> ".$key."\n";
					$checkboxHtml .= "</div>\n";
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

					$ratioHtml .= "<div style='margin-left:60px;'>\n";

					$ratioHtml .= "<select name='silp_ratio_box_select' id='silp_ratio_box_select' onchange='goEmbed();'>\n\n";
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
	echo "<div id='silp_settings'>\n";

	//only ouput "Option" if we have silp_settings to display
	if( ($checkboxHtml) || ($checkboxHtml) ) echo "<div style='float:left;'>Options:</div>\n";

	if($checkboxHtml) echo $checkboxHtml;
	if($ratioHtml) echo $ratioHtml;

	echo "</div>\n\n"; //silp_settings div

	echo "<div id='player-area'>";
	//hold our seected values
	echo $embedcode;
	echo "</div>\n\n";

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

	if( isset( $_POST['silp_project'] ) )
		update_post_meta( $post_id, 'silp_project', esc_attr( $_POST['silp_project'] ) );

	// Saving the current silp_params to db. So we can get them later ad display the admin silp accordingly
	if( isset( $_POST['silp_params'] ) )
	update_post_meta( $post_id, 'silp_params', $_POST['silp_params'] );

	//Saving the embed code
	if( isset( $_POST['silp_project'] ) ) {
		global $post;
		$project = get_post_meta($post->ID, 'silp_project', true);
		$options = get_option('silp_options');
		$silp_client = $options['client'];
		$silp_token = $options['token'];
		$silp_call = apiBaseUrl."/v1/projects/?client=".$silp_client."&token=".$silp_token."&embed=true&project=".$project;
		//if user have changes silp settings, we're passing them to the api-call when reuqesting embed code and store in db
		if( isset( $_POST['silp_params'] ) ) $silp_call .= $_POST['silp_params'];
		$json_data2 = file_get_contents($silp_call);
		$obj3=json_decode($json_data2, true);

		update_post_meta( $post_id, 'silp_embed', $obj3[$silp_client][0][embed] );
	}

}

//[silp]
function silp_embed()  {
	global $post;
	return get_post_meta($post->ID, 'silp_embed', true);

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
	add_menu_page(/*page title*/'Dashboard', /*Menu Title*/'Shootitlive',/*access*/'administrator', 'shootitlive', 'silp_settings_page',plugins_url('sil.ico', __FILE__));
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

// Sanitize and validate input. Accepts an array, return a sanitized array.
function silp_validate_options($input) {
	 // strip html from textboxes
	$input['client'] = wp_filter_nohtml_kses($input['client']); // Sanitize textarea input (strip html tags, and escape characters)
	$input['token'] = wp_filter_nohtml_kses($input['token']); // Sanitize textbox input (strip html tags, and escape characters)
	return $input;
}

?>

<script>

	function updatePlayer(project,silp_settings) {

		var player = document.getElementById('player-area');
		while (player.firstChild) {
		    player.removeChild(player.firstChild);
		}

		// Make sure to reload Silp entirely
	    if(window.Silp) window.Silp = {};

	    if(project != 0) {
		    player.style.display = 'block';
		    var script = document.createElement('script');
		    script.type = 'text/javascript';
		    <?
		    $options = get_option('silp_options');
		    $client = $options['client'];
		    ?>
		    script.src = '//s3-eu-west-1.amazonaws.com/shootitlive/shootitlive.load.v1.1.<?echo $client;?>.js?project='+project+silp_settings;
		    player.appendChild(script);

		    //creating a hidden input that will hold our silp_params
		    var input = document.createElement('input');
		    input.type = 'hidden';
		    input.id = 'silp_params';
		    input.name = 'silp_params';
		    input.value = silp_settings;
		    player.appendChild(input);


		}
		else {
			player.style.display = 'none';
		}
	}

	function goEmbed() {

		//Get project number
	    var projectID = document.getElementById('silp_project');
		var project = projectID.options[projectID.selectedIndex].value;

		//Get silp_settings from checkboxes
		if(document.getElementById('silp_settings').getElementsByTagName('input')) {
			var silp_settings_input = document.getElementById('silp_settings').getElementsByTagName('input');
			var silp_settings_string = "";

			// loop through each checkbox and save key:value silp_settings_string
			 for (x = 0; x < silp_settings_input.length; x++) {
				 if (silp_settings_input.item(x).type == 'checkbox') {
				 	silp_settings_string += "&"+silp_settings_input.item(x).name+"="+document.getElementById(silp_settings_input.item(x).name).checked;
				 }
			}
		}


		//Get silp_settings from ratio dropdown
		if(document.getElementById('silp_ratio_box_select')) {
			var ratioDiv = document.getElementById('silp_ratio_box_select');
			var ratio = ratioDiv.options[ratioDiv.selectedIndex].value;
			silp_settings_string += "&ratio="+ratio;
		}



		updatePlayer(project,silp_settings_string);

	}

</script>

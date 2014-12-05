<?php
defined('ABSPATH') or die("No script kiddies please!");
ini_set('default_charset', 'UTF-8');
date_default_timezone_set('Europe/Stockholm');
/*
Plugin Name: Shootitlive
Plugin URI: http://shootitlive.com
Description: Plugin for embedding live feeds from Shootitlive.com
Author: Martin Levy
Version: 1.4
Author URI: http://shootitlive.com
License: GPL2
*/

/*  Copyright 2014  Martin Levy  (email : martin@shootitlive.com)

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 2, as
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

register_activation_hook(__FILE__, 'silp_add_defaults');
register_uninstall_hook(__FILE__, 'silp_delete_plugin_options');
add_action('admin_init', 'silp_init' );
define('apiBaseUrl', 'http://cdn.api.shootitlive.com');
define('silp_option_params', 'share,ads,ratio,thumbnails'); //What options to display in page

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
$typePost = array('post','page');
function silp_meta_box_add() {
	add_meta_box( 'silp-meta-box-id', 'Shootitlive', 'silp_meta_box_cb', $typePost, 'side', 'high' );
}


//API call & drop down menu
function silp_meta_box_cb( $post ) {
	$values = get_post_custom( $post->ID );
	$selectedProject = isset( $values['silp_project'] ) ? esc_attr( $values['silp_project'][0] ) : '';
	$selectedVideo = isset( $values['silp_video'] ) ? esc_attr( $values['silp_video'][0] ) : '';
	$silp_type = isset( $values['silp_type'] ) ? $values['silp_type'][0] : '';
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

	//If there's no API info provided
	if ($options['client'] =='Enter Organisation Name' or $options['client'] =='') {
		echo "Please, enter API settings";
	}

	else {
		$silp_call_project = apiBaseUrl."/v1/projects/?client=".$silp_client."&token=".$silp_token."&embed=true";
		if($silp_params) $silp_call_project .= $silp_params; //add the stored DB silp_params to api call
		$json_project = @file_get_contents($silp_call_project);

		if($silp_type == "video") {
			$silp_call_video = apiBaseUrl."/v1/media/?client=".$silp_client."&token=".$silp_token."&embed=true&type=video";
			if($silp_params) $silp_call_video .= $silp_params; //add the stored DB silp_params to api call
			$json_video = @file_get_contents($silp_call_video);
			$objVideo=json_decode($json_video, true);
		}

	}

	//If provided API info is incorrect
	if($json_project === false) echo "Please check your API settings";

	if($json_project != false) {
		$objProject=json_decode($json_project, true);
		wp_nonce_field( 'silp_meta_box_nonce', 'meta_box_nonce' );

		echo "<div id='silp_type_area'>";
		echo "<div style='float:left;'>Type:</div>";

		echo "<div style='margin-left:60px;'>";
		echo "<input type='radio' name='silp_type' value='project' onchange='goEmbed();'";
		if( ($silp_type == 'project') || ($silp_type == '') ) echo "checked";
		echo ">Galleries";
		// echo "</div>";

		// echo "<div style='margin-left:30px;'>";
		echo "&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;";
		echo "<input type='radio' name='silp_type' id='silp_type_video' value='video' onchange='goEmbed();'";
		if($silp_type == 'video') echo "checked";
		echo ">Videos";
		echo "</div>";

		echo "</div>";

		echo "<div style='float:left;' id='silp_type_text'>Project:</div>";
		echo "<div style='margin-left:60px;'>";

		echo "<select id='silp_video' name='silp_video' onchange='goEmbed();' style='display:none;'>";
		if($silp_type == 'video') {
			echo "\n\n<option value='0'>Select a video:</option>\n\n";
			foreach($objVideo as $p) {
				$captured = date("ymd H:i",$p[captured]);
				$caption = ($p[caption]) ? $p[caption] : '';
				$filename = ($p[original_filename]) ? '('.substr($p[original_filename],0,15).')' : '(no name)';
				$id = substr($p[embed], strpos($p[embed],'single=')+7, 8);

				echo "<option value='".$id."'".selected( $selectedVideo, $id).">";
				echo $captured." ".$filename;
				echo "</option>\n";
				if($selectedVideo == $id) $embedcode_video = $p["embed"];
			}
		}
		echo "</select>";

		echo "<select name='silp_project' id='silp_project' onchange='goEmbed();'>\n\n";
		//if( ($silp_type == 'project') ||  (!$silp_type) ) {
			echo "\n\n<option value='0'>Select a project:</option>\n\n";
			foreach($objProject[$silp_client] as $p) {
				echo "<option value='".$p["project"]."'".selected( $selectedProject, $p["project"]).">";
				//limit project description to 23chars, so it fits in dropdown
				$description = (strlen($p["description"]) > 23) ? substr($p["description"],0,19).'...' : $p["description"];
				echo $description;
				echo "</option>\n";
				if($selectedProject == $p["project"]) $embedcode = $p["embed"];
			}
		//}
		echo "</select>";

		echo "</div>\n\n";
		$checkboxHtml ='';
		$ratioHtml ='';

		function createCheckbox($name, $value) {
			$html = "<div style='margin-left:60px;'>\n";
			$checked = ($value) ? "checked" : "";
			$html .= "<input type='checkbox' id='".$name."' name='".$name."' onchange='goEmbed();' value='".$value."' ".$checked."> ".$name."\n";
			$html .= "</div>\n";
			return $html;
		}

		function createRatio($name, $value) {
			// echo "YOLO: ".$value;
			if($value == "on") $value = 1.5;
			if($value == 1.5) $valueDescription = "Standard";
			if($value == 1.7777777778) $valueDescription = "Wide";
			if($value == 1) $valueDescription = "Square";
			$html = "<div style='margin-left:60px;'>\n";

			$html .= "<select name='silp_ratio_box_select' id='silp_ratio_box_select' onchange='goEmbed();'>\n\n";
			$html .= "<option value='".$value."' selected='selected'>".$valueDescription."</option>\n";

			if($valueDescription != "Standard") $html .= "<option value='1.5'>Standard</option>\n";
			if($valueDescription != "Wide") $html .= "<option value='1.7777777778'>Wide</option>\n";
			if($valueDescription != "Square") $html .= "<option value='1'>Square</option>\n";
			$html .= "</select>";
			//$html .= " ".$name."\n";
			$html .= "</div>\n";

			return $html;
		}

		$silp_option_params = explode(',', silp_option_params); //Getting what checkboxes to display from admin
		foreach ($objProject["silp_options"] as $key => $value) {
			$display = true;
			$hiddenArr = explode(',', $objProject["silp_options"]["hidden"]); //convert "hidden" to an array
			if(!in_array($key, (array)$hiddenArr)) { //only list not hidden silp_options

				if(is_bool($value) || $key == "ratio") { //only include key with true/false value and the ratio key

					/*
					From plugin settings page, we're checking what default options to display.
					These settings will overrule the settings from admin.
					If we have other silp options from api (that not present at all in plugin settings page)
					We will
					*/
					if(in_array($key, $silp_option_params)) {
						$silp_option_params = array_diff($silp_option_params, array($key));
						if(!$options[$key]) $display = false;
					}


					if( ($key !="ratio") && ($display) ) {
						$checkboxHtml .= createCheckbox($key, $value);
					}

					if( ($key =="ratio") && ($display) ) {
						if($silp_paramsDB[$key]) $value = $silp_paramsDB[$key];
						$ratioHtml .= createRatio($key, $value);
					}


				}
	 		}
		}

		//Adding silp params options that not present in API, but on settings page.
		foreach ($silp_option_params as $key => $value) {
			$key = $value;
			$value = $options[$key];

			//Add option if checkbox on plugn settings page is ticked.
			if($value) {
				if( ($key !="ratio") && ($display) ) {
				$checkboxHtml .= createCheckbox($key, $value);
				}

				if( ($key =="ratio") && ($display) ) {
					$ratioHtml .= createRatio($key, $value);
				}
			}

		}


		echo "<div id='silp_settings'>\n";

		echo "<div style='float:left;' id='options-area'>";
		//only ouput "Option" if we have silp_settings to display
		if( ($checkboxHtml) || ($checkboxHtml) ) echo "Options:";
		if($checkboxHtml) echo $checkboxHtml;
		if($ratioHtml) echo $ratioHtml;
		echo "</div>"; //options-area

		echo "<div style='float:left;margin-top:5px;' id='placement-area'>";
		echo "Placement:";
		//Placement radiobutton. Grab default from DB if present
		$placement = (get_post_meta($post->ID, 'silp_placement', true)) ? get_post_meta($post->ID, 'silp_placement', true) : '';

		echo "<div style='margin-left:60px;'>";
		echo "<input type='radio' name='silp_placement' value='top' ";
		if( ($placement == 'top') || ($placement == '') ) echo "checked";
		echo ">top of post";
		echo "</div>";

		echo "<div style='margin-left:60px;'>";
		echo "<input type='radio' name='silp_placement' value='bottom' ";
		if($placement == 'bottom') echo "checked";
		echo ">bottom of post";
		echo "</div>";

		echo "</div>\n\n";//placement-area

		echo "</div>\n\n"; //silp_settings div

		echo "<div id='player-area' style='margin-top:40px;'>";
		$embedcode = ($silp_type == 'video') ? $embedcode_video : $embedcode;
		echo $embedcode;
		echo "</div>\n\n";
		echo "<div align='right' id='editMedia'></div>";

	}//end if we have a correct api client/token
}//end function

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

	if( isset( $_POST['silp_video'] ) ) {
		update_post_meta( $post_id, 'silp_video', esc_attr( $_POST['silp_video'] ) );
		$silp_video = $_POST['silp_video'];
	}

	if( (isset( $_POST['silp_project'])) && (!$silp_video)  )
		update_post_meta( $post_id, 'silp_project', esc_attr( $_POST['silp_project'] ) );

	// Saving the current silp_params to db. So we can get them later ad display the admin silp accordingly
	if( isset( $_POST['silp_params'] ) )
	update_post_meta( $post_id, 'silp_params', $_POST['silp_params'] );

	// Saving the current silp_placement to db.
	if( isset( $_POST['silp_placement'] ) )
	update_post_meta( $post_id, 'silp_placement', $_POST['silp_placement'] );

	// Saving the silp_type
	if( isset( $_POST['silp_type'] ) )
	update_post_meta( $post_id, 'silp_type', $_POST['silp_type'] );


	//Saving the embed code
	if( (isset( $_POST['silp_project'])) || (isset($_POST['silp_video'])) ) {
		global $post;

		//$silp_video = ((isset($_POST['silp_video']))) ? (isset($_POST['silp_video'])) : '';
		$project = get_post_meta($post->ID, 'silp_project', true);
		$video = get_post_meta($post->ID, 'silp_video', true);

		if( ($project != "0") || ($video != "0") ) {
			$options = get_option('silp_options');
			$silp_client = $options['client'];
			$silp_token = $options['token'];
			$silp_call_project = apiBaseUrl."/v1/projects/?client=".$silp_client."&token=".$silp_token."&embed=true&project=".$project;
			$silp_call_video = apiBaseUrl."/v2/embeds/media-".$video;

			$silp_call = ( $_POST['silp_type'] == "video" ) ? $silp_call_video : $silp_call_project;

			//if user have changes silp settings, we're passing them to the api-call when reuqesting embed code and store in db
			if( isset( $_POST['silp_params'] ) ) {
				//if video we neew to replace first "&" with "?" in silp_params
				$silp_params = ( $_POST['silp_type'] == "video" ) ? "?".substr($_POST['silp_params'], 1) : $_POST['silp_params'];
				$silp_call .= $silp_params;
			}
			$json_data2 = file_get_contents($silp_call);
			$obj3=json_decode($json_data2, true);

			if( ($video != "0") && ( $_POST['silp_type'] == "video" ) ) update_post_meta( $post_id, 'silp_embed', $obj3[embed_code] );
			if( ($video != "0") && ( $_POST['silp_type'] == "video" ) ) update_post_meta( $post_id, 'silp_type', 'video' );
			if( ($video == "0") || ( $_POST['silp_type'] == "project" ) ) update_post_meta( $post_id, 'silp_embed', $obj3[$silp_client][0][embed] );
			if( ($video == "0") || ( $_POST['silp_type'] == "project" ) ) update_post_meta( $post_id, 'silp_type', 'project' );

		}

		if( ($project == "0") && ($video == "0") ) {
			//if we've unset the silp, lets remove all db entrys
			if(get_post_meta($post->ID, 'silp_project', true)) update_post_meta( $post_id, 'silp_project', '' );
			if(get_post_meta($post->ID, 'silp_video', true)) update_post_meta( $post_id, 'silp_video', '' );
			if(get_post_meta($post->ID, 'silp_type', true)) update_post_meta( $post_id, 'silp_type', '' );
			if(get_post_meta($post->ID, 'silp_embed', true)) update_post_meta( $post_id, 'silp_embed', '' );
			if(get_post_meta($post->ID, 'silp_params', true)) update_post_meta( $post_id, 'silp_params', '' );
			if(get_post_meta($post->ID, 'silp_placement', true)) update_post_meta( $post_id, 'silp_placement', '' );
		}
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
	global $post;
	$placement = get_post_meta($post->ID, 'silp_placement', true);

	//Silp in top of post
	if( ($placement == 'top') || (!$placement) ){
		$content = do_shortcode("[silp]").$content;
	}

	//Silp in bottom of post
	if( $placement == 'bottom') {
		$content .= do_shortcode("[silp]");
	}

	// $content .= do_shortcode("[silp]");

	return $content;
}

//Settings page content
add_action("admin_menu","silp_admin_menu");
function silp_admin_menu(){
	//"add_options_page" = sub_page under Settings, "add_page" = page straight ,
	add_options_page(
		/*page title*/'Dashboard',
		/*Menu Title*/'Shootitlive',
		/*access*/'administrator',
		'shootitlive',
		'silp_settings_page',
		plugins_url('sil.ico', __FILE__)
	);
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

			<table class="form-table">
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
				<tr>
					<th scope="row">
						Options:
						<div style='color:grey;font-weight:lighter;font-size:small'>
							Enable options to appear when embedding
							Shootitlive in a post or page.
						</div>
					</th>
					<td>
						<?
							$silp_option_params = explode(',', silp_option_params);
							foreach ($silp_option_params as $key) {
								$checked = ($options[$key] == "on") ? "checked" : "";
								echo "<input type='checkbox' name='silp_options[".$key."]' ".$checked."> ".ucfirst($key);
								echo "\n</br>\n";
							}
						?>
					</td>
				</tr>

			</table>
			<p class="submit">
			<input type="submit" class="button-primary" value="<?php _e('Save Changes') ?>" />
			</p>
		</form>

		<div style='color:grey;font-weight:bold;font-size:large'>
			<b>F.A.Q</b>
		</div>
		<div style='color:grey;font-weight:lighter;font-size:small'>
			</br>
			<b>Where can I find my API information</b>
			</br>Find the <i>Organisation name</i> and <i>API key</i> on your <a href='https://admin.shootitlive.com/' target='_blank'>profile page</a>
			</br>Make sure you are entitled to use the API according to your <a href='http://shootitlive.com/signup/' target='_blank'>plan</a>
			</br>
			</br>
			<b>How do I switch between galleries and videos</b>
			</br>When creating a new post or page, you choose to list your galleries or videos and then select from the dropdown
			</br>If a video is selected, you must first unselect it to be able to list your galleries.
			</br>
			</br>
			<b>I need support</b>
			</br><a href="mailto:support@shootitlive.com?Subject=API Support" target="_top">support@shootitlive.com</a>
		</div>


	</div>
	<?
}

// Sanitize and validate input. Accepts an array, return a sanitized array.
function silp_validate_options($input) {
	 // strip html from textboxes
	$input['client'] = wp_filter_nohtml_kses($input['client']); // Sanitize textarea input (strip html tags, and escape characters)
	$input['token'] = wp_filter_nohtml_kses($input['token']); // Sanitize textbox input (strip html tags, and escape characters)
	$silp_option_params = explode(',', silp_option_params);
	foreach ($silp_option_params as $key) {
		$input[$key] = wp_filter_nohtml_kses($input[$key]); // Sanitize textbox input (strip html tags, and escape characters)
	}
	return $input;
}


//only load script if we're on post page
global $pagenow;
if (! empty($pagenow) && ('post-new.php' === $pagenow || $_GET['action'] == 'edit')) {
?>

<script>
	window.onload = function(){
		goEmbed(); //This is mainly do remove the option-div when there's no project set in dropdown
	}

	function fetchJSONFile(path, callback) {
	    var httpRequest = new XMLHttpRequest();
	    httpRequest.onreadystatechange = function() {
	        if (httpRequest.readyState === 4) {
	            if (httpRequest.status === 200) {
	                var data = JSON.parse(httpRequest.responseText);
	                if (callback) callback(data);
	            }
	        }
	    };
	    httpRequest.open('GET', path);
	    httpRequest.send();
	}

	function updatePlayer(project,silp_settings,silp_video_id) {

		var player = document.getElementById('player-area');
		var editMedia = document.getElementById('editMedia');
		var option_area = document.getElementById('options-area')
		var placement_area = document.getElementById('placement-area')
		var project_area = document.getElementById('silp_project');
		var silp_type = document.getElementById("silp_type_video").checked ? 'video' : 'project';
		var silp_type_area = document.getElementById('silp_type_area');
		while (player.firstChild) {
		    player.removeChild(player.firstChild);
		}

		// Make sure to reload Silp entirely
	    if(window.Silp) window.Silp = {};

	    if( (project != 0) || ( (silp_type == 'video') && (silp_video_id != 0) && (silp_video_id != 'doApi') ) ) {

	    	player.style.display = 'block';
		    var script = document.createElement('script');
		    script.type = 'text/javascript';

		    <?
		    $options = get_option('silp_options');
		    $client = $options['client'];
		    $token = $options['token'];
			?>
			baseLoad = '//s3-eu-west-1.amazonaws.com/shootitlive/shootitlive.load.v1.1.<?echo $client;?>.js';

	    	if(silp_type == 'project') {
		    	//Lets display our option & placement div's
		    	if(option_area) option_area.style.display = 'block';
		    	if(placement_area) placement_area.style.display = 'block';
		    	silp_type_area.style.display = 'none'; //when a project project is selected, we're removing the radio btn
		    	project_area.options[0].text = "Remove player from post";
			    script.src = baseLoad+'?project='+project+silp_settings;
			    var editHtml = '<a href="https://admin.shootitlive.com/projects/edit/<?echo $client;?>/'+project+'" target="_blank">Edit in Shootitlive admin</a>';
				editMedia.innerHTML = editHtml;
			    editMedia.style.display = 'block';
			}

	    	if(silp_type == 'video') {
		    	//Lets display our option & placement div's
		    	if(option_area) option_area.style.display = 'block';
		    	if(placement_area) placement_area.style.display = 'block';
			    script.src = baseLoad+'?single='+silp_video_id+silp_settings;
			    //var editHtml = 'Edit project in Shootitlive admin';
			}





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
			//if project = 0, we're hiding the options area & placement area and remove the silp
			if(option_area) option_area.style.display = 'none';
			if(placement_area) placement_area.style.display = 'none';
			if( (silp_type_area) && (silp_type != 'video') ) silp_type_area.style.display = 'block'; //when no project is selected, were displaying type btn
			project_area.options[0].text = "Select a project:";
			player.style.display = 'none';
			editMedia.style.display = 'none';
		}
	}

	function goEmbed() {
		var project_area = document.getElementById('silp_project');
		var silp_type = document.getElementById("silp_type_video").checked ? 'video' : 'project';
		var silp_type_area = document.getElementById('silp_type_area');
		var silp_video = document.getElementById('silp_video');
		if(silp_type == 'project') {
			document.getElementById("silp_type_text").innerHTML = 'Project:';
			if(project_area) project_area.style.display = 'block'; //Display project dropdown
			if(silp_video) silp_video.style.display = 'none'; //Hide video dropdown
		}

		if(silp_type == 'video') {
			if(project_area) project_area.style.display = 'none'; //Hide project dropdown
			if(silp_video) silp_video.style.display = 'block'; //display video dropdown


			if(typeof silp_video.options[silp_video.selectedIndex] !== "undefined") {
				var silp_video_id = (silp_video.options[silp_video.selectedIndex].value) ? silp_video.options[silp_video.selectedIndex].value : 0;
			}
			else {
				var silp_video_id = 'doApi';
			}


			document.getElementById("silp_type_text").innerHTML = 'Video:';
			//Fetch videos from API
			var url = "<?echo apiBaseUrl;?>/v1/media/?";
			var params = "client=<?echo $client;?>&token=<?echo $token;?>&type=video&embed=true";
			var silp_call = url+params;

			function format_two_digits(n) {
			    return n < 10 ? '0' + n : n;
			}

			function DateTime(captured) {
				d = new Date(captured * 1000);
				year = d.getFullYear().toString().substr(2,2);;
				month = format_two_digits(d.getMonth() + 1);
				day = format_two_digits(d.getDate());
				hours = format_two_digits(d.getHours());
    			minutes = format_two_digits(d.getMinutes());
				var DateTimeString = year+''+month+''+day+' '+hours+':'+minutes;
				return DateTimeString;
			}


			if(silp_video_id == 'doApi') {
				var opt = document.createElement('option');
				opt.value = '0';
				opt.innerHTML = 'Select a video';
				silp_video.appendChild(opt);

				fetchJSONFile(silp_call, function(data){
				    if(data) {
				    	for (var key in data) {
				    		var captured = DateTime(data[key].captured);
				    		var caption = (data[key].caption) ? data[key].caption : '';
				    		var filename = (data[key].original_filename) ? data[key].original_filename.substr(0,15) : 'no name';
				    		var n = data[key].embed.search('single=');
				    		var single_embed = data[key].embed.substr(n+7, 8)


				    		var opt = document.createElement('option');
						    opt.value = single_embed;
						    //opt.onchange = goEmbed();
						    opt.innerHTML = captured+' ('+filename+')';
						    silp_video.appendChild(opt);
				    	}

				    }

				});
			} //if video_id not doApi

			if( (silp_video_id != 0) && (silp_video_id != 'doApi') ) {
				silp_video.options[0].text = "Remove video from post";
				silp_type_area.style.display = 'none';
			}
			else {
				silp_video.options[0].text = "Select a video";
				silp_type_area.style.display = 'block';
			}

		} //if video

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

		if(!silp_video_id) silp_video_id = 0;

		updatePlayer(project,silp_settings_string,silp_video_id);

	}

</script>
<?
} //end script load
?>
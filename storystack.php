<?php
/*
Plugin Name: Storystack
Plugin URI: http://storystack.com/
Description: Display the Storystack widget below your posts.
Version: 0.13
Author: Storystack
Author URI: http://storystack.com/
License: GPLv2 or later
*/

// Make sure we don't expose any info if called directly
if (!function_exists( 'add_action')) {
	header('HTTP/1.0 404 Not Found');
	header('Location: ../../../404');
	exit;
}

define('STORYSTACK_WEBCLIENT_URL', 'http://web-clients.storystack.com/bootstrap/stst-configs-bootstrap.js');
define('STORYSTACK_HOMEPAGE_URL', 'http://storystack.com/');

add_action('admin_menu', 'storystack_admin_menu');
add_filter('plugin_action_links_storystack', 'storystack_settings_link');
add_action('admin_init', 'storystack_init');
add_filter('the_content', 'storystack_display');

function storystack_init() {
	register_setting('storystack_options', 'storystack_options', 'storystack_options_validate');

	add_settings_section('the_storystack', '', 'storystack_consumer_key_details', 'storystack');
	add_settings_field('storystack_consumer_key', __('Consumer Key'), 'storystack_consumer_key_field', 'storystack', 'the_storystack');

	add_settings_field('storystack_categories', __('Categories'), 'storystack_categories_field', 'storystack', 'the_storystack');

	add_action('admin_notices', 'storystack_admin_notices');
}

function storystack_settings_link($links) {
	$settings_link = '<a href="options-general.php?page=storystack">' . __('Settings') . '</a>';
	array_unshift($links, $settings_link);
	return $links;
}

function storystack_display($content) {
	$options = get_option('storystack_options');

	if ($options['consumer_key'] && is_single() && !is_feed() && !is_home() && 'post' === get_post_type()) {
		$selected_cats = is_array($options['categories']) ? $options['categories'] : array();

		if (!$selected_cats || in_category($selected_cats)) {
			// Make Rocket Loader ignore widget javascript
			// https://support.cloudflare.com/hc/en-us/articles/200169436--How-can-I-have-Rocket-Loader-ignore-my-script-s-in-Automatic-Mode-
			$storystack_script = '<script data-cfasync="false">var storyStack = { consumerKey: "'.$options['consumer_key'].'", use_external_settings: true }</script><script data-cfasync="false" type="text/javascript" src="'.STORYSTACK_WEBCLIENT_URL.'" async="true"></script>';

			// shows above Social Sharing Toolkit plugin, if displayed on bottom of post
			// http://wordpress.org/plugins/social-sharing-toolkit/
			if (in_array(get_option('mr_social_sharing_position'), array('bottom', 'both'))) {
				$pos = strrpos($content, '<div class="mr_social_sharing_wrapper">');
				if (false !== $pos) {
					return substr($content, 0, $pos).$storystack_script.substr($content, $pos);
				}
			}

			return $content.$storystack_script;
		}
	}

	return $content;
}

function storystack_admin_notices() {
	$options = get_option('storystack_options');
	if (!$options['consumer_key']) {
		echo '<div class="updated"><p>' . __('You need to enter your consumer key before Storystack can display your widget.') . ' <a class="button action" href="options-general.php?page=storystack">'.__('Enter Consumer Key').'</a></p></div>';
	}
}

function storystack_admin_menu() {
	add_options_page(__('Storystack'), __('Storystack'), 'manage_options', 'storystack', 'storystack_settings_page');
}

function storystack_settings_page() {
	echo '<h2>Storystack Settings</h2>';
	echo '<form action="options.php" method="post">';
	settings_fields('storystack_options');
	do_settings_sections('storystack');
	echo '<input type="submit" value="' . __('Save Changes') . '" class="button button-primary"/>';
	echo '</form>';
}

function storystack_consumer_key_details() {
}

function storystack_consumer_key_field() {
	$options = get_option('storystack_options');
	echo '<input id="storystack_consumer_key" name="storystack_options[consumer_key]" size="40" type="text" value="' . $options['consumer_key'] . '"/>';
	echo '<p class="description">'.sprintf(__('Follow the widget install instructions on %s to generate your consumer key.'), '<a href="' . STORYSTACK_HOMEPAGE_URL . '">' . STORYSTACK_HOMEPAGE_URL . '</a>').'</p>';
}

function storystack_categories_field() {
	$options = get_option('storystack_options');
	$selected_cats = is_array($options['categories']) ? $options['categories'] : array();

	echo '
		<div id="taxonomy-category" class="categorydiv">
			<div id="category-all" class="tabs-panel">
				<ul id="categorychecklist" data-wp-lists="list:category" class="categorychecklist form-no-clear">
	';

	$checkboxes = array();
	foreach (get_categories() as $cat) {
		$checked = '';
		$append = true;
		if (in_array($cat->term_id, $selected_cats)) {
			$checked = ' checked="checked"';
			$append = false;
		}

		$checkbox = '
			<li id="category-'.$cat->term_id.'">
				<label class="selectit">
					<input type="checkbox" id="in-category-'.$cat->term_id.'" name="storystack_options[categories][]" value="'.$cat->term_id.'"'.$checked.'/>
					'.$cat->name.'
				</label>
			</li>
		';

		// keeps checked categories at the top of the list
		// to avoid having to scroll through the whole list
		// to see what is checked
		if ($append) {
			array_push($checkboxes, $checkbox);
		} else {
			array_unshift($checkboxes, $checkbox);
		}
	}

	echo implode('', $checkboxes);

	echo '
				</ul>
			</div>
		</div>
	';
	echo '<p class="description">' . __('Choose one or more categories, to have your widget displayed only in those sections.') . '<br/>' . __('Choose no categories to have your widget displayed on all posts.') . '</p>';
}

function storystack_options_validate($input) {
	if (!ctype_alnum($input['consumer_key']) || 22 != strlen($input['consumer_key'])) {
		$input['consumer_key'] = '';
	}

	return $input;
}
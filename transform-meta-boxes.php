<?php
/*
Plugin Name: Transform Meta Boxes
Version: 0.1
Plugin URI: http://drakard.com/
Description: Change the appearance of a taxonomy's meta box input in the Edit screen to something other than the Category checkboxes or Tag free text entry, allowing you to have your users easily pick from a predefined set of terms.
Author: Keith Drakard
Author URI: http://drakard.com/
*/


if (! defined('WPINC')) die;

class TransformMetaBoxesPlugin {

	public function __construct() {
		if (! is_admin()) return;

		load_plugin_textdomain('TransformMetaBoxes', false, dirname(plugin_basename(__FILE__)).'/languages');

		if (! ($this->options = get_option('TransformMetaBoxesPluginOptions')))
			add_option('TransformMetaBoxesPluginOptions', array('taxonomies_to_change' => array()), null, false);

		if (sizeof($this->options['taxonomies_to_change'])) {
			require_once 'meta-box-transformations.php';
			add_action('init', array($this, 'alter_taxonomies'), 99);
			add_action('admin_footer', array($this, 'enqueue_styles_and_scripts'));
			add_filter('post_updated', array($this, 'remove_terms_if_all_unset'), 10, 2);
		}

		add_action('current_screen', array($this, 'load_vars_for_settings'));
		add_action('admin_init', array($this, 'settings_init'));
		add_action('admin_menu', array($this, 'add_settings_page'));
	}
	

	public function alter_taxonomies() {
		foreach ($this->options['taxonomies_to_change'] as $taxonomy => $settings) {

			$tax_obj = get_taxonomy($taxonomy);
    		$tax_obj->meta_box_cb = array(
				new MetaBoxTransformations(array(
					'taxonomy' =>	$taxonomy,
					'name' =>		$settings['name'],
					'field' =>		$settings['field'],
					'multiple' =>	$settings['multiple'],
				)),					$settings['transformation']
			);

    		register_taxonomy($settings['taxonomy'], $tax_obj->object_type, (array) $tax_obj);
    	}
    }

    public function enqueue_styles_and_scripts() {
    	// NOTE: only needed when one of the transformations is the toggle buttons
		wp_register_style('TransformMetaBoxes-style', plugins_url('meta-boxes.css', __FILE__), false, '1.0');
    	wp_enqueue_style('TransformMetaBoxes-style');
    }


	public function remove_terms_if_all_unset($post_id, $post) {
		foreach ($this->options['taxonomies_to_change'] as $taxonomy => $settings) {
			$first = strtok($settings['name'], '[]'); $second = strtok('[]');
			if (! isset($_POST[$first]) OR ! sizeof($_POST[$first]) OR											// post_category and tags_input
				! (false === $second OR (isset($_POST[$first][$second]) AND sizeof($_POST[$first][$second])))	// everything else...
			) {
				wp_delete_object_term_relationships($post_id, $taxonomy);
			}
		}
	}


	public function load_vars_for_settings() {
		$screen = get_current_screen();
		if (	('settings_page_TransformMetaBoxesPlugin/settings' == $screen->id) 
			OR	('options' == $screen->id AND isset($_POST['option_page']) AND 'TransformMetaBoxesPluginOptions' == $_POST['option_page'])
		) {
			$this->taxonomies = get_taxonomies(array(
				'show_ui' => true,
			), 'names');
			sort($this->taxonomies);

			$this->transformations = array(
				'none'				=> __('Leave Unchanged', 'TransformMetaBoxes'),
				'dropdown'			=> __('Dropdown (Single)', 'TransformMetaBoxes'),
				'dropdown-multi'	=> __('Dropdown (Multiple)', 'TransformMetaBoxes'),
				'toggles-multi'		=> __('Toggle Buttons (Multiple)', 'TransformMetaBoxes'),
			);
		}
	}

	public function settings_init() {
		register_setting('TransformMetaBoxesPluginOptions', 'TransformMetaBoxesPluginOptions', array($this, 'validate_settings'));
		add_settings_section('TransformMetaBoxesSettings', __('Taxonomies', 'TransformMetaBoxes'), array($this, 'taxonomies_settings_form'), 'TransformMetaBoxesPlugin');
	}

	public function add_settings_page() {
		add_options_page(__('Transform Meta Boxes Options', 'TransformMetaBoxes'), __('Transform Meta Boxes', 'TransformMetaBoxes'), 'manage_options', __CLASS__.'/settings.php', array(__CLASS__, 'display_settings_page'));
	}

	public function display_settings_page() {
		echo '<div class="wrap"><h2>'.__('Transform Meta Boxes Options', 'TransformMetaBoxes').'</h2>'
			.'<form action="options.php" method="post">';
				settings_fields('TransformMetaBoxesPluginOptions');
		echo '<table class="form-table"><tbody>';
				do_settings_sections('TransformMetaBoxesPlugin');
		echo '</tbody></table>';
				submit_button();
		echo '</form></div>';
	}

	public function taxonomies_settings_form() {
		$output = '';
		foreach ($this->taxonomies as $tax_name) {
			$output.= '<tr><th scope="row">'.$tax_name.'</th><td><fieldset>'
					. '<select name="TransformMetaBoxesPluginOptions[taxonomies_to_change]['.$tax_name.']">';
			foreach ($this->transformations as $key => $text) {
				$selected = '';
				if (	isset($this->options['taxonomies_to_change'][$tax_name])
					AND (
						(! $this->options['taxonomies_to_change'][$tax_name]['multiple'] AND $this->options['taxonomies_to_change'][$tax_name]['transformation'] == $key)
						OR 
						($this->options['taxonomies_to_change'][$tax_name]['multiple'] AND $this->options['taxonomies_to_change'][$tax_name]['transformation'].'-multi' == $key)
					)
				) {
					$selected = ' selected';
				}
				$output.= '<option value="'.$key.'"'.$selected.'>'.$text.'</option>';
			}
			$output.= '</select>';

			// TODO: add select for user roles

			$output.= '</fieldset></td></tr>';
		}

		echo $output;
	}


	public function validate_settings($input) {
		if (isset($input['taxonomies_to_change']) AND is_array($input['taxonomies_to_change'])) {

			// get shot of the first "None" option...
			array_shift($this->transformations); $possible_transformations = array_keys($this->transformations);
			$this->options['taxonomies_to_change'] = array();

			foreach ($input['taxonomies_to_change'] as $taxonomy => $transformation) {
				if (in_array($taxonomy, $this->taxonomies) AND in_array($transformation, $possible_transformations)) {

					$name = 'tax_input['.$taxonomy.']'; if ('category' == $taxonomy) $name = 'post_category'; elseif ('post_tag' == $taxonomy) $name = 'tags_input';
					$tax_obj = get_taxonomy($taxonomy);

					$this->options['taxonomies_to_change'][$taxonomy] = array(
						'taxonomy'			=> $taxonomy,
						'name'				=> $name,
						'field'				=> ($tax_obj->hierarchical) ? 'term_id' : 'name',
						'multiple'			=> (0 < strpos($transformation, '-multi')),
						'transformation'	=> str_replace('-multi', '', $transformation),
					);

				}
			}

		}

		return $this->options;
	}


}


$TransformMetaBoxes = new TransformMetaBoxesPlugin();
<?php

if (! defined('WPINC')) die;

class MetaBoxTransformations {

	public function __construct($params = array()) {
		foreach ($params as $key => $value) $this->$key = $value;

		if (! isset($this->taxonomy))		$this->taxonomy = 'category';
		if (! isset($this->name))			$this->name = 'post_category';
		if (! isset($this->field))			$this->field = 'term_id';
		if (! isset($this->multiple))		$this->multiple = false;
		if (! isset($this->select_size))	$this->select_size = '10';

		// TODO: $this->include AND $this->exclude

		$this->terms = get_terms($this->taxonomy, array(
			'orderby'			=> 'name',
			'hide_empty'		=> false,
			'exclude'			=> '',
		));
		if ('term_id' == $this->field) { // means it's hierarchical and we can't rely on wp's dropdown category function to sort it for us
			$sorted = array(); $this->sort_terms_hierarchically($this->terms, $sorted); $this->terms = $sorted;
		}
	}


	public function dropdown($post) {
		$this->selected = $this->get_selected($post->ID);
		$multiple = ($this->multiple) ? ' size="10" multiple="true"' : '';

		$output = '<div id="taxonomy-'.$this->taxonomy.'" class="categorydiv">'
				. '<select id="'.$this->name.'[]" class="widefat" name="'.$this->name.'[]"'.$multiple.'>'
				. '<option value="">'.__('None', 'TransformMetaBoxes').'</option>'
				. $this->display_options_recursively($this->terms)
				. '</select></div>';

		echo $output;
	}
	private function display_options_recursively($terms = array(), $level = 0) {
		$output = '';
		foreach ($terms as $i => $term) {
			$checked = (in_array($term->term_id, $this->selected)) ? ' selected="selected"' : '';
			$padded_name = str_repeat('-- ', $level).$term->name;
			$output.= '<option class="level-'.$level.'" value="'.$term->{$this->field}.'"'.$checked.'>'.$padded_name.' ('.$term->count.')</option>';
			if (isset($term->children) AND sizeof($term->children)) $output.= $this->display_options_recursively($term->children, $level+1);
		}
		return $output;
	}


	public function toggles($post) {
		$this->selected = $this->get_selected($post->ID);

		$output = '<div id="taxonomy-'.$this->taxonomy.'" class="categorydiv checkbox-toggles">'
				. $this->display_buttons_recursively($this->terms)
				. '</div>';

		echo $output;
	}
	// only really here for the sake of completeness
	private function display_buttons_recursively($terms = array(), $level = 0) {
		$output = '';
		foreach ($terms as $i => $term) {
			$checked = (in_array($term->term_id, $this->selected)) ? ' checked="checked"' : '';
			$output.= '<input type="checkbox" name="'.$this->name.'[]" id="'.$this->name.'['.$i.']" value="'.$term->{$this->field}.'"'.$checked.'>'
					. '<label class="level-'.$level.'" for="'.$this->name.'['.$i.']">'.$term->name.' ('.$term->count.')</label>';
			if (isset($term->children) AND sizeof($term->children)) $output.= $this->display_buttons_recursively($term->children, $level+1);
		}
		return $output;
	}


	private function get_selected($post_id) {
		$post_has = wp_get_object_terms($post_id, $this->taxonomy);
		$selected = array(); foreach ($post_has as $term) $selected[] = $term->term_id;
		return $selected;
	}

	// by pospi @ http://wordpress.stackexchange.com/a/99516
	private function sort_terms_hierarchically(array &$cats, array &$into, $parentId = 0) {
		foreach ($cats as $i => $cat) {
			if ($cat->parent == $parentId) {
				$into[$cat->term_id] = $cat;
				unset($cats[$i]);
			}
		}
		foreach ($into as $topCat) {
			$topCat->children = array();
			$this->sort_terms_hierarchically($cats, $topCat->children, $topCat->term_id);
		}
	}
}

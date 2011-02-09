<?php

/*
	Phoronix Test Suite
	URLs: http://www.phoronix.com, http://www.phoronix-test-suite.com/
	Copyright (C) 2011, Phoronix Media
	Copyright (C) 2011, Michael Larabel

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

class pts_HeatMapBarGraph
{
	protected $bars;
	protected $last_updated;
	protected $keys;

	public function __construct($keys = array(), $last_updated = null)
	{
		$this->bars = array();
		$this->keys = $keys;
		$this->last_updated = $last_updated;
	}
	public function add_result_bar($min_value, $max_value, $sections, $lines, $test_data, $results = array())
	{
		array_push($this->bars, array(
			'min_value' => $min_value,
			'max_value' => $max_value,
			'sections' => $sections,
			'draw_lines' => $lines,
			'test_data' => $test_data,
			'results' => $results
			));
	}
	public function get_count()
	{
		return count($this->bars);
	}
	public function generate_display()
	{
		$bar_width = 620;
		$bar_height = 38;
		$heading_per_bar = 16;
		$title_bar_height = 35;
		$footer_bar_height = 14;
		$border = 3;

		if(!empty($this->keys))
		{
			list($longest_key_width, $key_line_height) = bilde_renderer::soft_text_string_dimensions(max($this->keys), '', 10, true);
			$key_line_height += 18;
			$keys_per_line = floor($bar_width / ($longest_key_width + 10));
			$title_key_offset = ceil(count($this->keys) / $keys_per_line) * $key_line_height;
		}
		else
		{
			$title_key_offset = 0;
		}

		$bilde_renderer = bilde_renderer::setup_renderer('SVG', $bar_width + ($border * 2), ($bar_height + $heading_per_bar + $border) * count($this->bars) + $border + $title_bar_height + $title_key_offset + $footer_bar_height);

		$border_color = $bilde_renderer->convert_hex_to_type('#222');
		$text_color = $bilde_renderer->convert_hex_to_type('#e12128');
		$alt_text_color = $bilde_renderer->convert_hex_to_type('#646464');

		// Setup
		$start_x = $border;
		$end_x = $start_x + $bar_width;

		// Title bar
		$bilde_renderer->image_copy_merge($bilde_renderer->png_image_to_type('http://openbenchmarking.org/media/logo-183x32.png'), $end_x - 183, $border, 0, 0, 183, 32);

		if(!empty($this->keys))
		{
			$color_cache = array('e12128', '065695', '007400');

			for($i = 0, $c = count($this->keys); $i < $c; $i++)
			{
				$component_x = $border + ($i % $keys_per_line) * ($longest_key_width + 10);
				$component_y = (floor($i / $keys_per_line) * $key_line_height) + $title_bar_height + 3;
				$key_color = $bilde_renderer->convert_hex_to_type(bilde_renderer::color_cache('opc', $this->keys[$i], $color_cache));

				//$key_color = $bilde_renderer->convert_hex_to_type(bilde_renderer::color_gradient('e12128', '065695', ($i / $c)));
				$key_colors[$this->keys[$i]] = $key_color;

				$bilde_renderer->draw_rectangle_with_border($component_x + 1, $component_y, $component_x + 11, $component_y + 10, $key_color, $border_color);
				$bilde_renderer->write_text_left($this->keys[$i], null, 10, $key_color, $component_x + 15, $component_y + 5, $component_x, $component_y + 5);
			}
		}

		foreach($this->bars as $i => &$rank)
		{
			$upper_y = ($i * ($bar_height + $border + $heading_per_bar)) + $border + $title_bar_height + $title_key_offset + $heading_per_bar;
			$lower_y = $upper_y + $bar_height;

			$value_size = $bar_width / ($rank['max_value'] - $rank['min_value']);
			$prev_color = $bilde_renderer->convert_hex_to_type('#ff');
			$last_plot_x = $start_x;

			$bilde_renderer->write_text_left($rank['test_data']['t'], '', 12, $text_color, $start_x, $upper_y - 10, $start_x + ($bar_width / 2), $upper_y - 10);
			$bilde_renderer->write_text_right($rank['test_data']['a'], '', 10, $alt_text_color, $end_x, $upper_y - 9, $end_x, $upper_y - 9);

			if($rank['test_data']['p'] == 'LIB' && true)
			{
				// Invert results
				$new_sections = array();

				foreach($rank['sections'] as $next_section => $next_section_value)
				{
					$new_sections[($rank['max_value'] - $next_section)] = $next_section_value;
				}

				ksort($new_sections);
				$rank['sections'] = $new_sections;

				foreach($rank['draw_lines'] as &$value)
				{
					$value = $rank['max_value'] - $value;
				}

				foreach($rank['results'] as &$value)
				{
					$value = $rank['max_value'] - $value;
				}

				sort($rank['draw_lines']);

				$rank['max_value'] -= $rank['min_value'];
				$rank['min_value'] = 0;
			}

			$prev_section = $rank['min_value'];
			$max_section_value = max($rank['sections']);

			foreach($rank['sections'] as $next_section => $next_section_value)
			{
				$color_weight = 0.61 - ($next_section_value / $max_section_value * 0.5);
				$color = $bilde_renderer->convert_hex_to_type(bilde_renderer::color_gradient('FFFFFF', '000000', $color_weight));

				if($next_section > $rank['min_value'])
				{
					$next_section = $next_section > $rank['max_value'] ? $rank['max_value'] : $next_section;

					$plot_x = $last_plot_x + (($next_section - $prev_section) * $value_size);
					$plot_x = $plot_x > $end_x ? $end_x : $plot_x;
					$bilde_renderer->draw_rectangle_gradient($last_plot_x, $upper_y, ceil($plot_x), $lower_y, $prev_color, $color);
					$last_plot_x = floor($plot_x - 0.6);
					$prev_section = $next_section;

					if($next_section > $rank['max_value'])
					{
						break;
					}
				}
				$prev_color = $color;
			}

			if($last_plot_x < $end_x)
			{
				// Fill in the blank
				$bilde_renderer->draw_rectangle($last_plot_x, $upper_y, $end_x, $lower_y, $prev_color);
			}

			foreach($rank['draw_lines'] as $line_value)
			{
				$line_x = $start_x + ($line_value - $rank['min_value']) * $value_size;
				$bilde_renderer->draw_line($line_x, $upper_y, $line_x, $lower_y, $border_color, 1);
			}

			foreach($rank['results'] as $identifier => $value)
			{
				$line_x = $start_x + ($value - $rank['min_value']) * $value_size;

				if(($start_x + 10) < $line_x && $line_x < ($end_x - 10))
				{
					$bilde_renderer->draw_arrow($line_x, $lower_y - 10, $line_x, $lower_y - 1, $key_colors[$identifier], $key_colors[$identifier], 1);
					$bilde_renderer->draw_arrow($line_x, $upper_y + 10, $line_x, $upper_y + 1, $key_colors[$identifier], $key_colors[$identifier], 1);
				}

				$bilde_renderer->draw_line($line_x, $lower_y, $line_x, $upper_y, $key_colors[$identifier], 2);
			}

			$bilde_renderer->draw_rectangle_border($start_x, $upper_y, $end_x, $lower_y, $border_color);
		}

		// Footer
		$bilde_renderer->draw_arrow($start_x + 8, $lower_y + 8, $start_x + 1, $lower_y + 8, $alt_text_color, $border_color, 1);
		$bilde_renderer->write_text_left('Higher Is Better;' . ($this->last_updated != null ? ' Performance Index As Of ' . pts_strings::time_stamp_to_string($this->last_updated, 'j F Y') : null), '', 7, $alt_text_color, $start_x + 13, $lower_y + 8, $start_x + 13, $lower_y + 8);
		$bilde_renderer->write_text_right('OpenBenchmarking.org', '', 7, $alt_text_color, $end_x, $lower_y + 8, $end_x, $lower_y + 8);


		return $bilde_renderer;
	}
}

?>
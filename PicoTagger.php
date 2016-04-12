<?php
/**
 * PicoTagger - a real Tags plugin for the Pico CMS
 * Pico version used for development: 1.0.2
 *
 * @author  Ingo Wedler
 * @link    http://ingowedler.com
 * @link    https://github.com/iwedler/PicoTagger
 * @license https://opensource.org/licenses/gpl-license
 * @version 1.0
 */
 
final class PicoTagger extends AbstractPicoPlugin {
	
	private $is_tagcloud = false;
	private $is_tag = false;
	private $tag_found = false;
	private $current_tag = '';
	private $tags_array = [];
	private $found_pages = [];
	private $tags_occurance = [];
	private $tags_min_size = 12;
	private $tags_max_size = 24;
	private $widget_mode = false;
	private $tags_template = 'tags.twig';
	private $tags_meta_title = 'Tagged with: {{ current_tag }}';
	private $tags_meta_description = 'Pages tagged with: {{ current_tag }}';
	private $tagcloud_template = 'tagcloud.twig';
	private $tagcloud_meta_title = 'Tag Cloud';
	private $tagcloud_meta_description = 'This is the Tag Cloud for this site.';
	
	/**
	 * This plugin is enabled by default?
	 *
	 * @see AbstractPicoPlugin::$enabled
	 * @var boolean
	 */
	protected $enabled = false;
	
	/**
	 * This plugin depends on ...
	 *
	 * @see AbstractPicoPlugin::$dependsOn
	 * @var string[]
	 */
	protected $dependsOn = array();
	
	/**
	 * Triggered after Pico has read its configuration
	 *
	 * @see    Pico::getConfig()
	 * @param  array &$config array of config variables
	 * @return void
	 */
	public function onConfigLoaded(array &$config)
	{
		if (isset($config['tags_min_size'])) {
			$this->tags_min_size = (int)$config['tags_min_size'];
		}
		if (isset($config['tags_max_size'])) {
			$this->tags_max_size = (int)$config['tags_max_size'];
		}
		if (isset($config['widget_mode'])) {
			$this->widget_mode = (bool)$config['widget_mode'];
		}
		if (isset($config['tagcloud_template'])) {
			$this->tagcloud_template = $config['tagcloud_template'];
		}
		if (isset($config['tags_template'])) {
			$this->tags_template = $config['tags_template'];
		}
		if (isset($config['tags_meta_title'])) {
			$this->tags_meta_title = $config['tags_meta_title'];
		}
		if (isset($config['tags_meta_description'])) {
			$this->tags_meta_description = $config['tags_meta_description'];
		}
		if (isset($config['tagcloud_meta_title'])) {
			$this->tagcloud_meta_title = $config['tagcloud_meta_title'];
		}
		if (isset($config['tagcloud_meta_description'])) {
			$this->tagcloud_meta_description = $config['tagcloud_meta_description'];
		}
	}
	
	/**
	 * Triggered after Pico has evaluated the request URL
	 *
	 * @see    Pico::getRequestUrl()
	 * @param  string &$url part of the URL describing the requested contents
	 * @return void
	 */
	public function onRequestUrl(&$url)
	{
		if ($url == 'tags') {
			$this->is_tagcloud = true;
		} else if (substr($url, 0, 5) == 'tags/') {
			$parts = explode('/', $url);
			if (count($parts) == 2) {
				$this->is_tag = true;
				$this->current_tag = urldecode($parts[1]);
			}
		}
	}
	
	/**
	 * Triggered when Pico reads its known meta header fields
	 *
	 * @see    Pico::getMetaHeaders()
	 * @param  string[] &$headers list of known meta header
	 *     fields; the array value specifies the YAML key to search for, the
	 *     array key is later used to access the found value
	 * @return void
	 */
	public function onMetaHeaders(array &$headers)
	{
		 $headers['tags'] = 'Tags';
	}
	
	/**
	 * Triggered after Pico has read all known pages
	 *
	 * See {@link DummyPlugin::onSinglePageLoaded()} for details about the
	 * structure of the page data.
	 *
	 * @see    Pico::getPages()
	 * @see    Pico::getCurrentPage()
	 * @see    Pico::getPreviousPage()
	 * @see    Pico::getNextPage()
	 * @param  array[]    &$pages        data of all known pages
	 * @param  array|null &$currentPage  data of the page being served
	 * @param  array|null &$previousPage data of the previous page
	 * @param  array|null &$nextPage     data of the next page
	 * @return void
	 */
	public function onPagesLoaded(
		array &$pages,
		array &$currentPage = null,
		array &$previousPage = null,
		array &$nextPage = null)
	{
		if ($this->is_tagcloud || $this->is_tag || $this->widget_mode) {
			foreach ($pages as $page) {
				if ($page['meta']['tags']) {
					$tags = PicoTagger::parseTags($page['meta']['tags']);
					foreach ($tags as $tag) {
						$this->tags_array[$tag]['name'] = $tag;
						$this->tags_array[$tag]['page_ids'][] = $page['id'];
						$this->tags_array[$tag]['occurance'] = count($this->tags_array[$tag]['page_ids']);
						$this->tags_occurance[] = count($this->tags_array[$tag]['page_ids']);
					}
				}
			}
			
			// calculate largest and smallest array values
			$max_qty = max(array_values($this->tags_occurance));
			$min_qty = min(array_values($this->tags_occurance));
			
			// find the range of values
			$spread = $max_qty - $min_qty;
			if ($spread == 0) $spread = 1; // we don't want to divide by zero
			
			// set the font-size increment
			$step = ($this->tags_max_size - $this->tags_min_size) / ($spread);
			
			// set the font-size for each tag
			foreach ($this->tags_array as $key => $val) {
				$this->tags_array[$key]['size'] = round($this->tags_min_size + (($val['occurance'] - $min_qty) * $step));
			}
			
			if ($this->is_tag) {
				foreach ($this->tags_array as $key => $key) {
					if (strcasecmp($key, $this->current_tag) == 0) {
						$this->tag_found = true;
						foreach ($this->tags_array[$key]['page_ids'] as $skey => $sval) {
							$this->found_pages[] = $sval;
						}
					}
				}
				$this->found_pages = PicoTagger::key_values_intersect($pages, $this->found_pages);
			}
		}
	}
	
	/**
	 * Triggered before Pico renders the page
	 *
	 * @see    Pico::getTwig()
	 * @see    DummyPlugin::onPageRendered()
	 * @param  Twig_Environment &$twig          twig template engine
	 * @param  array            &$twigVariables template variables
	 * @param  string           &$templateName  file name of the template
	 * @return void
	 */
	public function onPageRendering(Twig_Environment &$twig, array &$twigVariables, &$templateName)
	{
		// Since there is no content file and the 404 template is called, we have to overwrite some stuff
		if ($this->is_tagcloud) {
			header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
			$templateName = $this->tagcloud_template;
			$twigVariables['meta']['title'] = $this->tagcloud_meta_title;
			$twigVariables['meta']['description'] = $this->tagcloud_meta_description;
			$twigVariables['tag_list'] = $this->tags_array;
			
		} else if ($this->is_tag) {
			if ($this->tag_found) {
				header($_SERVER['SERVER_PROTOCOL'] . ' 200 OK');
				$templateName = $this->tags_template;
				$twigVariables['meta']['title'] = PicoTagger::parseConfigString($this->tags_meta_title, $this->current_tag);
				$twigVariables['meta']['description'] = PicoTagger::parseConfigString($this->tags_meta_description, $this->current_tag);
				$twigVariables['current_tag'] = $this->current_tag;
				$twigVariables['tags_pages'] = $this->found_pages;
			}
		} else if ($this->widget_mode) {
			$twigVariables['tag_list'] = $this->tags_array;
		}
	}
	
	private static function parseTags($tags) {
		if (is_string($tags)) {
			$tags = explode(',', $tags);
		}
		return is_array($tags) ? array_map('trim', $tags) : [];
	}
	
	private static function key_values_intersect($values, $keys) {
		$key_val_int = [];
		foreach ($keys as $key) {
			$key_val_int[$key] = $values[$key];
		}
		return $key_val_int;
	}
	
	private static function parseConfigString($theString, $theTag) {
		$opentag = '{{';
		$closetag = '}}';
		$output = '';
		$startpos = strpos($theString, $opentag);
		if ($startpos !== false) { //we have the startpos, a var exists
			$endpos = strpos($theString, $closetag);
			$output .= substr($theString, 0, $startpos); //add first part
			if (strcasecmp(trim(substr($theString, $startpos + 2, $endpos - ($startpos + 2))), 'current_tag' == 0)) {  // check 'current_tag'
				$output .= $theTag;
			}
			$output .= substr($theString, $endpos + 2, strlen($theString));
		} else {
			$output = $theString;
		}
		return $output;
	}
}

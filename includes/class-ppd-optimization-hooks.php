<?php

/**
 * Optimization Hooks class.
 *
 * Applies active optimizations to WordPress.
 *
 * @package PerformanceDoctor
 */

// If this file is called directly, abort.
if (! defined('WPINC')) {
	die;
}

/**
 * Optimization Hooks class.
 */
class PPD_Optimization_Hooks
{

	/**
	 * Initialize hooks.
	 */
	public function init()
	{
		// Lazy loading.
		if (get_option('ppd_opt_lazy_loading')) {
			add_filter('wp_lazy_loading_enabled', '__return_true');
			add_filter('the_content', array($this, 'add_lazy_loading_to_images'));
		}

		// Defer JavaScript.
		if (get_option('ppd_opt_defer_js')) {
			add_filter('script_loader_tag', array($this, 'defer_scripts'), 10, 2);
		}

		// Disable emoji.
		if (get_option('ppd_opt_disable_emoji')) {
			$this->disable_emoji();
		}

		// Minify HTML.
		if (get_option('ppd_opt_minify_html')) {
			add_action('template_redirect', array($this, 'start_html_minification'));
		}

		// Preload fonts.
		if (get_option('ppd_opt_preload_fonts')) {
			add_action('wp_head', array($this, 'preload_fonts'), 1);
		}

		// Disable embeds.
		if (get_option('ppd_opt_disable_embeds')) {
			$this->disable_embeds();
		}

		// Limit revisions.
		$revision_limit = get_option('ppd_opt_limit_revisions');
		if ($revision_limit) {
			if (! defined('WP_POST_REVISIONS')) {
				define('WP_POST_REVISIONS', (int) $revision_limit);
			}
		}

		// Optimize heartbeat.
		if (get_option('ppd_opt_disable_heartbeat')) {
			add_action('init', array($this, 'optimize_heartbeat'));
		}

		// Disable XML-RPC.
		if (get_option('ppd_opt_disable_xmlrpc')) {
			add_filter('xmlrpc_enabled', '__return_false');
			add_filter('xmlrpc_methods', array($this, 'disable_xmlrpc_methods'));
		}

		// Remove query strings.
		if (get_option('ppd_opt_remove_query_strings')) {
			add_filter('script_loader_src', array($this, 'remove_query_strings'), 15, 1);
			add_filter('style_loader_src', array($this, 'remove_query_strings'), 15, 1);
		}

		// Disable self pingbacks.
		if (get_option('ppd_opt_disable_self_pingbacks')) {
			add_action('pre_ping', array($this, 'disable_self_pingbacks'));
		}

		// Disable Dashicons.
		if (get_option('ppd_opt_disable_dashicons')) {
			add_action('wp_enqueue_scripts', array($this, 'disable_dashicons'));
		}

		// Cleanup Head.
		if (get_option('ppd_opt_cleanup_head')) {
			$this->cleanup_head();
		}
	}

	/**
	 * Add lazy loading to images.
	 *
	 * @param string $content Post content.
	 * @return string Modified content.
	 */
	public function add_lazy_loading_to_images($content)
	{
		if (is_feed() || is_admin()) {
			return $content;
		}

		$content = preg_replace('/<img(.*?)src=/i', '<img$1loading="lazy" src=', $content);
		return $content;
	}

	/**
	 * Defer JavaScript files.
	 *
	 * @param string $tag Script tag.
	 * @param string $handle Script handle.
	 * @return string Modified tag.
	 */
	public function defer_scripts($tag, $handle)
	{
		// Don't defer jQuery and admin scripts.
		$exclude = array('jquery', 'jquery-core', 'jquery-migrate');

		if (in_array($handle, $exclude, true) || is_admin()) {
			return $tag;
		}

		// Add defer attribute.
		return str_replace(' src', ' defer src', $tag);
	}

	/**
	 * Disable WordPress emoji.
	 */
	private function disable_emoji()
	{
		remove_action('wp_head', 'print_emoji_detection_script', 7);
		remove_action('admin_print_scripts', 'print_emoji_detection_script');
		remove_action('wp_print_styles', 'print_emoji_styles');
		remove_action('admin_print_styles', 'print_emoji_styles');
		remove_filter('the_content_feed', 'wp_staticize_emoji');
		remove_filter('comment_text_rss', 'wp_staticize_emoji');
		remove_filter('wp_mail', 'wp_staticize_emoji_for_email');

		add_filter('tiny_mce_plugins', array($this, 'disable_emoji_tinymce'));
		add_filter('wp_resource_hints', array($this, 'disable_emoji_dns_prefetch'), 10, 2);
	}

	/**
	 * Disable emoji in TinyMCE.
	 *
	 * @param array $plugins TinyMCE plugins.
	 * @return array Modified plugins.
	 */
	public function disable_emoji_tinymce($plugins)
	{
		if (is_array($plugins)) {
			return array_diff($plugins, array('wpemoji'));
		}
		return array();
	}

	/**
	 * Disable emoji DNS prefetch.
	 *
	 * @param array  $urls URLs to prefetch.
	 * @param string $relation_type Relation type.
	 * @return array Modified URLs.
	 */
	public function disable_emoji_dns_prefetch($urls, $relation_type)
	{
		if ('dns-prefetch' === $relation_type) {
			$emoji_svg_url = apply_filters('emoji_svg_url', 'https://s.w.org/images/core/emoji/2/svg/');
			$urls          = array_diff($urls, array($emoji_svg_url));
		}
		return $urls;
	}

	/**
	 * Start HTML minification.
	 */
	public function start_html_minification()
	{
		if (! is_admin()) {
			ob_start(array($this, 'minify_html_output'));
		}
	}

	/**
	 * Minify HTML output.
	 *
	 * @param string $html HTML content.
	 * @return string Minified HTML.
	 */
	public function minify_html_output($html)
	{
		// Remove HTML comments (except IE conditional comments).
		$html = preg_replace('/<!--(?!\s*(?:\[if [^\]]+]|<!|>))(?:(?!-->).)*-->/s', '', $html);

		// Remove whitespace.
		$html = preg_replace('/\s+/', ' ', $html);
		$html = preg_replace('/>\s+</', '><', $html);

		return trim($html);
	}

	/**
	 * Preload fonts.
	 */
	public function preload_fonts()
	{
		// Get theme fonts (this is a simplified version).
		$fonts = apply_filters('ppd_preload_fonts', array());

		foreach ($fonts as $font) {
			echo '<link rel="preload" href="' . esc_url($font) . '" as="font" type="font/woff2" crossorigin>' . "\n";
		}
	}

	/**
	 * Disable WordPress embeds.
	 */
	private function disable_embeds()
	{
		// Remove embed JavaScript.
		remove_action('wp_head', 'wp_oembed_add_discovery_links');
		remove_action('wp_head', 'wp_oembed_add_host_js');

		// Remove embed rewrite rules.
		add_filter('rewrite_rules_array', array($this, 'disable_embeds_rewrites'));

		// Remove embed query var.
		add_filter('query_vars', array($this, 'disable_embeds_query_vars'));
	}

	/**
	 * Disable embed rewrite rules.
	 *
	 * @param array $rules Rewrite rules.
	 * @return array Modified rules.
	 */
	public function disable_embeds_rewrites($rules)
	{
		foreach ($rules as $rule => $rewrite) {
			if (strpos($rewrite, 'embed=true') !== false) {
				unset($rules[$rule]);
			}
		}
		return $rules;
	}

	/**
	 * Disable embed query vars.
	 *
	 * @param array $vars Query vars.
	 * @return array Modified vars.
	 */
	public function disable_embeds_query_vars($vars)
	{
		return array_diff($vars, array('embed'));
	}

	/**
	 * Optimize heartbeat API.
	 */
	public function optimize_heartbeat()
	{
		// Disable on frontend.
		if (! is_admin()) {
			wp_deregister_script('heartbeat');
		}

		// Slow down in admin.
		add_filter('heartbeat_settings', array($this, 'heartbeat_settings'));
	}

	/**
	 * Modify heartbeat settings.
	 *
	 * @param array $settings Heartbeat settings.
	 * @return array Modified settings.
	 */
	public function heartbeat_settings($settings)
	{
		$settings['interval'] = 60; // 60 seconds instead of 15.
		return $settings;
	}

	/**
	 * Disable XML-RPC methods.
	 *
	 * @param array $methods XML-RPC methods.
	 * @return array Modified methods.
	 */
	public function disable_xmlrpc_methods($methods)
	{
		unset($methods['pingback.ping']);
		return $methods;
	}

	/**
	 * Remove query strings from static resources.
	 *
	 * @param string $src Resource URL.
	 * @return string Modified URL.
	 */
	public function remove_query_strings($src)
	{
		if (strpos($src, '?ver=') !== false) {
			$src = remove_query_arg('ver', $src);
		}
		return $src;
	}

	/**
	 * Disable self pingbacks.
	 *
	 * @param array $links Pingback links.
	 */
	public function disable_self_pingbacks(&$links)
	{
		$home = get_option('home');
		foreach ($links as $l => $link) {
			if (0 === strpos($link, $home)) {
				unset($links[$l]);
			}
		}
	}

	/**
	 * Disable Dashicons on frontend.
	 */
	public function disable_dashicons()
	{
		if (! is_admin() && ! is_user_logged_in()) {
			wp_dequeue_style('dashicons');
		}
	}

	/**
	 * Cleanup WordPress Head.
	 */
	private function cleanup_head()
	{
		remove_action('wp_head', 'rsd_link');
		remove_action('wp_head', 'wlwmanifest_link');
		remove_action('wp_head', 'wp_generator');
		remove_action('wp_head', 'wp_shortlink_wp_head');
		remove_action('wp_head', 'start_post_rel_link');
		remove_action('wp_head', 'index_rel_link');
		remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
	}
}

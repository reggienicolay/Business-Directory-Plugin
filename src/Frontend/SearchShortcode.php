<?php
/**
 * Search Box Shortcode
 *
 * Displays a standalone business search box.
 * Usage: [bd_search], [bd_search placeholder="Find a local business..."]
 *
 * @package BusinessDirectory
 */
namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class SearchShortcode {

	public static function init() {
		add_shortcode( 'bd_search', array( __CLASS__, 'render_shortcode' ) );
	}

	public static function render_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'placeholder' => 'Search businesses...',
				'redirect'    => '/local/',
				'class'       => '',
			),
			$atts,
			'bd_search'
		);

		$action      = esc_url( home_url( $atts['redirect'] ) );
		$placeholder = esc_attr( $atts['placeholder'] );
		$class       = $atts['class'] ? ' ' . esc_attr( $atts['class'] ) : '';

		$html  = '<form class="bd-search-box' . $class . '" action="' . $action . '" method="get" role="search">';
		$html .= '<input type="text" name="search" placeholder="' . $placeholder . '" aria-label="' . esc_attr__( 'Search businesses', 'business-directory' ) . '" autocomplete="off" />';
		$html .= '<button type="submit">';
		$html .= '<svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>';
		$html .= ' ' . esc_html__( 'Explore', 'business-directory' );
		$html .= '</button>';
		$html .= '</form>';

		return $html;
	}
}

SearchShortcode::init();

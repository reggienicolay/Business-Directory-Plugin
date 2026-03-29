<?php
/**
 * Badge SVG Renderer
 *
 * Generates premium metallic SVG badges — PHP port of badge-v10.jsx.
 * Pure SVG, no foreignObject, no CSS conic-gradient hacks.
 * Metallic surfaces via layered radial + linear SVG gradients.
 *
 * @package BusinessDirectory
 * @subpackage Gamification
 * @version 10.0
 */

namespace BD\Gamification;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BadgeSVG {

	/**
	 * Material definitions — one per rarity tier.
	 * Direct port from badge-v10.jsx MAT constant.
	 */
	const MATERIALS = array(
		'common'    => array(
			'label'     => 'Silver',
			'rarity'    => 'Common',
			'base'      => '#c0cad4',
			'light'     => '#e8edf2',
			'mid'       => '#b0bcc8',
			'dark'      => '#8896a6',
			'shine'     => '#f4f7fa',
			'iconColor' => '#4a5668',
			'iconGlow'  => '#dce2e8',
			'textFill'  => '#3a4658',
			'textHi'    => '#ffffff',
			'glow'      => 'rgba(148,163,184,0.3)',
			'textColor' => '#64748b',
			'lockTint'  => '#94a3b8',
			'shimmer'   => '#e2e8f0',
			'shadow'    => 'rgba(100,116,139,0.4)',
			'desc'      => 'Brushed silver — the journey begins',
			'swatch'    => 'radial-gradient(circle at 35% 35%, #e8edf2, #b0bcc8, #8896a6)',
		),
		'rare'      => array(
			'label'     => 'Copper',
			'rarity'    => 'Rare',
			'base'      => '#b87a5a',
			'light'     => '#deb89a',
			'mid'       => '#a87050',
			'dark'      => '#7a4832',
			'shine'     => '#f0d0b8',
			'iconColor' => '#4a2818',
			'iconGlow'  => '#deb89a',
			'textFill'  => '#3a1e10',
			'textHi'    => '#f0d0b8',
			'glow'      => 'rgba(184,122,90,0.35)',
			'textColor' => '#9a6244',
			'lockTint'  => '#b87a5a',
			'shimmer'   => '#deb89a',
			'shadow'    => 'rgba(122,72,50,0.4)',
			'desc'      => 'Burnished copper — warming with distinction',
			'swatch'    => 'radial-gradient(circle at 35% 35%, #deb89a, #b87a5a, #7a4832)',
		),
		'epic'      => array(
			'label'     => 'Amethyst',
			'rarity'    => 'Epic',
			'base'      => '#8b7abf',
			'light'     => '#c4b5fd',
			'mid'       => '#7a6ab4',
			'dark'      => '#5a4a8a',
			'shine'     => '#e0d8f0',
			'iconColor' => '#3a2a62',
			'iconGlow'  => '#d0c8e8',
			'textFill'  => '#2e2054',
			'textHi'    => '#e0d8f0',
			'glow'      => 'rgba(139,92,246,0.35)',
			'textColor' => '#7c3aed',
			'lockTint'  => '#8b7abf',
			'shimmer'   => '#c4b5fd',
			'shadow'    => 'rgba(107,90,175,0.4)',
			'desc'      => 'Amethyst steel — rare alloy, unmistakable',
			'swatch'    => 'radial-gradient(circle at 35% 35%, #c4b5fd, #8b7abf, #5a4a8a)',
		),
		'legendary' => array(
			'label'     => 'Gold',
			'rarity'    => 'Legendary',
			'base'      => '#d4a830',
			'light'     => '#f8e88c',
			'mid'       => '#c8a028',
			'dark'      => '#9A7B1A',
			'shine'     => '#f8e88c',
			'iconColor' => '#5a4008',
			'iconGlow'  => '#f8e17c',
			'textFill'  => '#4a3406',
			'textHi'    => '#f8e88c',
			'glow'      => 'rgba(245,197,34,0.4)',
			'textColor' => '#b89020',
			'lockTint'  => '#d4a830',
			'shimmer'   => '#f8e17c',
			'shadow'    => 'rgba(154,123,26,0.45)',
			'desc'      => 'Struck gold — forged legacy',
			'swatch'    => 'radial-gradient(circle at 35% 35%, #f8e88c, #d4a830, #9A7B1A)',
		),
		'special'   => array(
			'label'     => 'Navy & Gold',
			'rarity'    => 'Special',
			'base'      => '#1a3a5a',
			'light'     => '#2a5a80',
			'mid'       => '#163d5c',
			'dark'      => '#0c1e32',
			'shine'     => '#3a6a92',
			'iconColor' => '#C9A227',
			'iconGlow'  => '#f5c522',
			'textFill'  => '#C9A227',
			'textHi'    => '#f8e17c',
			'glow'      => 'rgba(201,162,39,0.4)',
			'textColor' => '#C9A227',
			'lockTint'  => '#C9A227',
			'shimmer'   => '#f5c522',
			'shadow'    => 'rgba(201,162,39,0.3)',
			'desc'      => 'Navy enamel with gold leaf — singular',
			'swatch'    => 'radial-gradient(circle at 35% 35%, #2a5a80, #1a3a5a, #0c1e32)',
		),
	);

	/**
	 * Seal bump pattern (irregular wax seal edge).
	 */
	const SEAL_BUMPS = array( 10, 6, 8, 13, 5, 9, 12, 7, 10, 8, 14, 5, 9, 7, 11, 8, 13, 6, 10, 7, 9, 14, 5, 12 );

	// =========================================================================
	// Shape Path Generators — port from badge-v10.jsx lines 75-82
	// =========================================================================

	/**
	 * Circle SVG path (common badges).
	 */
	private static function circle_d( float $cx, float $cy, float $r ): string {
		return sprintf(
			'M%.1f,%.1fA%.1f,%.1f 0 1,1 %.1f,%.1fA%.1f,%.1f 0 1,1 %.1f,%.1fZ',
			$cx - $r,
			$cy,
			$r,
			$r,
			$cx + $r,
			$cy,
			$r,
			$r,
			$cx - $r,
			$cy
		);
	}

	/**
	 * Shield SVG path (rare badges).
	 */
	private static function shield_d( float $cx, float $cy, float $r ): string {
		$w   = $r * 2;
		$h   = $r * 2.2;
		$hw  = $w / 2;
		$top = $cy - $h * 0.46;
		$mid = $cy + $h * 0.1;
		$bot = $cy + $h * 0.46;

		return sprintf(
			'M%.1f,%.1f L%.1f,%.1f L%.1f,%.1f Q%.1f,%.1f %.1f,%.1f Q%.1f,%.1f %.1f,%.1f L%.1f,%.1f Z',
			$cx,
			$top,
			$cx + $hw,
			$top + $h * 0.17,
			$cx + $hw,
			$mid,
			$cx + $hw,
			$bot - $h * 0.08,
			$cx,
			$bot,
			$cx - $hw,
			$bot - $h * 0.08,
			$cx - $hw,
			$mid,
			$cx - $hw,
			$top + $h * 0.17
		);
	}

	/**
	 * Hexagon SVG path (epic badges).
	 */
	private static function hex_d( float $cx, float $cy, float $r ): string {
		$points = array();
		for ( $i = 0; $i < 6; $i++ ) {
			$a        = ( $i / 6 ) * M_PI * 2 - M_PI / 2;
			$prefix   = ( 0 === $i ) ? 'M' : 'L';
			$points[] = sprintf( '%s%.1f,%.1f', $prefix, $cx + cos( $a ) * $r, $cy + sin( $a ) * $r );
		}
		return implode( ' ', $points ) . ' Z';
	}

	/**
	 * Scalloped SVG path (legendary badges).
	 */
	private static function scallop_d( float $cx, float $cy, float $radius, int $n, float $bump ): string {
		$d = '';
		for ( $i = 0; $i < $n; $i++ ) {
			$a1 = ( $i / $n ) * M_PI * 2;
			$a2 = ( ( $i + 1 ) / $n ) * M_PI * 2;
			$am = ( $a1 + $a2 ) / 2;

			$x1 = $cx + cos( $a1 ) * $radius;
			$y1 = $cy + sin( $a1 ) * $radius;
			$xm = $cx + cos( $am ) * ( $radius + $bump );
			$ym = $cy + sin( $am ) * ( $radius + $bump );
			$x2 = $cx + cos( $a2 ) * $radius;
			$y2 = $cy + sin( $a2 ) * $radius;

			$prefix = ( 0 === $i ) ? 'M' : '';
			$d     .= sprintf(
				'%s%.1f,%.1f Q%.1f,%.1f %.1f,%.1f ',
				$prefix,
				$x1,
				$y1,
				$xm,
				$ym,
				$x2,
				$y2
			);
		}
		return $d . 'Z';
	}

	/**
	 * Wax seal SVG path (special badges).
	 */
	private static function seal_d( float $cx, float $cy, float $radius, int $n ): string {
		$w = self::SEAL_BUMPS;
		$d = '';
		for ( $i = 0; $i < $n; $i++ ) {
			$a1   = ( $i / $n ) * M_PI * 2;
			$a2   = ( ( $i + 1 ) / $n ) * M_PI * 2;
			$am   = ( $a1 + $a2 ) / 2;
			$bump = $w[ $i % count( $w ) ] * 0.7;

			$x1 = $cx + cos( $a1 ) * $radius;
			$y1 = $cy + sin( $a1 ) * $radius;
			$xm = $cx + cos( $am ) * ( $radius + $bump );
			$ym = $cy + sin( $am ) * ( $radius + $bump );
			$x2 = $cx + cos( $a2 ) * $radius;
			$y2 = $cy + sin( $a2 ) * $radius;

			$prefix = ( 0 === $i ) ? 'M' : '';
			$d     .= sprintf(
				'%s%.1f,%.1f Q%.1f,%.1f %.1f,%.1f ',
				$prefix,
				$x1,
				$y1,
				$xm,
				$ym,
				$x2,
				$y2
			);
		}
		return $d . 'Z';
	}

	// =========================================================================
	// SVG Gradient & Filter Definitions
	// =========================================================================

	/**
	 * Generate SVG <defs> for a badge (gradients, filters, clip paths).
	 * Port of MetalDefs from badge-v10.jsx lines 85-134.
	 */
	private static function render_defs( string $id, array $m, string $rarity, string $outer_shape ): string {
		$is_special_rim = ( 'special' === $rarity );

		// Rim gradient stops vary by rarity.
		if ( $is_special_rim ) {
			$rim_stops = '<stop offset="0%" stop-color="#C9A227"/>'
				. '<stop offset="50%" stop-color="#8a6a10"/>'
				. '<stop offset="100%" stop-color="#3a2a08"/>';
		} elseif ( 'legendary' === $rarity ) {
			$rim_stops = '<stop offset="0%" stop-color="#c8a028"/>'
				. '<stop offset="50%" stop-color="#7a5f14"/>'
				. '<stop offset="100%" stop-color="#2a2008"/>';
		} else {
			$rim_stops = sprintf(
				'<stop offset="0%%" stop-color="%s"/>'
				. '<stop offset="40%%" stop-color="%s"/>'
				. '<stop offset="70%%" stop-color="%s"/>'
				. '<stop offset="100%%" stop-color="%s"/>',
				$m['shine'],
				$m['light'],
				$m['base'],
				$m['dark']
			);
		}

		return sprintf(
			'<defs>'
			// Face gradient — radial, off-center highlight.
			. '<radialGradient id="%1$s-face" cx="42%%" cy="38%%" r="58%%">'
			. '<stop offset="0%%" stop-color="%2$s"/>'
			. '<stop offset="30%%" stop-color="%3$s"/>'
			. '<stop offset="60%%" stop-color="%4$s"/>'
			. '<stop offset="85%%" stop-color="%5$s"/>'
			. '<stop offset="100%%" stop-color="%6$s"/>'
			. '</radialGradient>'
			// Depth gradient — subtle shadow from bottom-right.
			. '<radialGradient id="%1$s-depth" cx="60%%" cy="62%%" r="55%%">'
			. '<stop offset="0%%" stop-color="%4$s" stop-opacity="0"/>'
			. '<stop offset="100%%" stop-color="%6$s" stop-opacity="0.35"/>'
			. '</radialGradient>'
			// Rim gradient.
			. '<radialGradient id="%1$s-rim" cx="50%%" cy="50%%" r="55%%">'
			. '%7$s'
			. '</radialGradient>'
			// Shine overlay — diagonal linear.
			. '<linearGradient id="%1$s-shine" x1="0%%" y1="0%%" x2="100%%" y2="100%%">'
			. '<stop offset="0%%" stop-color="white" stop-opacity="0.2"/>'
			. '<stop offset="35%%" stop-color="white" stop-opacity="0"/>'
			. '<stop offset="65%%" stop-color="white" stop-opacity="0"/>'
			. '<stop offset="100%%" stop-color="white" stop-opacity="0.06"/>'
			. '</linearGradient>'
			// Drop shadow filter.
			. '<filter id="%1$s-drop">'
			. '<feDropShadow dx="0" dy="3" stdDeviation="5" flood-color="#000" flood-opacity="0.3"/>'
			. '</filter>'
			// Emboss text filter.
			. '<filter id="%1$s-em">'
			. '<feGaussianBlur in="SourceAlpha" stdDeviation="0.4" result="b"/>'
			. '<feOffset in="b" dx="-0.8" dy="-0.8" result="lo"/>'
			. '<feOffset in="b" dx="1" dy="1" result="do2"/>'
			. '<feFlood flood-color="%8$s" flood-opacity="0.5" result="lc"/>'
			. '<feFlood flood-color="black" flood-opacity="0.25" result="dc"/>'
			. '<feComposite in="lc" in2="lo" operator="in" result="ls"/>'
			. '<feComposite in="dc" in2="do2" operator="in" result="ds"/>'
			. '<feMerge><feMergeNode in="ds"/><feMergeNode in="SourceGraphic"/><feMergeNode in="ls"/></feMerge>'
			. '</filter>'
			// Clip path for shimmer.
			. '<clipPath id="%1$s-clip"><path d="%9$s"/></clipPath>'
			. '</defs>',
			$id,
			$m['shine'],  // 2
			$m['light'],  // 3
			$m['base'],   // 4
			$m['mid'],    // 5
			$m['dark'],   // 6
			$rim_stops,   // 7
			$m['textHi'], // 8
			$outer_shape  // 9
		);
	}

	// =========================================================================
	// Icon Rendering
	// =========================================================================

	/**
	 * Get the icon name from a badge's Font Awesome HTML.
	 * e.g. '<i class="fa-solid fa-certificate"></i>' → 'certificate'
	 */
	private static function extract_icon_name( string $icon_html ): string {
		if ( preg_match( '/fa-(?:solid|regular|brands)\s+fa-([a-z0-9-]+)/', $icon_html, $matches ) ) {
			return $matches[1];
		}
		return 'star'; // fallback.
	}

	/**
	 * Render the icon as an SVG <path> element, centered and scaled.
	 */
	private static function render_icon_path( string $icon_name, float $cx, float $cy, float $icon_size, string $color ): string {
		$icon = BadgeIcons::get( $icon_name );
		if ( ! $icon ) {
			return '';
		}

		// Parse viewBox.
		$vb      = explode( ' ', $icon['viewBox'] );
		$vw      = (float) ( $vb[2] ?? 512 );
		$vh      = (float) ( $vb[3] ?? 512 );
		$max_dim = max( $vw, $vh );

		if ( $max_dim <= 0 || $icon_size <= 0 ) {
			return '';
		}

		// Scale to fit icon_size, centered at cx,cy.
		$scale    = $icon_size / $max_dim;
		$offset_x = $cx - ( $vw * $scale / 2 );
		$offset_y = $cy - ( $vh * $scale / 2 );

		return sprintf(
			'<path d="%s" fill="%s" transform="translate(%.1f,%.1f) scale(%.4f)"/>',
			esc_attr( $icon['d'] ),
			esc_attr( $color ),
			$offset_x,
			$offset_y,
			$scale
		);
	}

	// =========================================================================
	// Main Render Method
	// =========================================================================

	/**
	 * Render a metallic SVG badge.
	 *
	 * @param string $badge_key Badge key from BadgeSystem::BADGES.
	 * @param array  $options   {
	 *     @type int   $size     Pixel width (default 160).
	 *     @type bool  $earned   Whether badge is earned (default true).
	 *     @type int   $progress Current progress toward goal (default 0).
	 *     @type int   $goal     Total needed to earn (default 1).
	 *     @type bool  $animate  Include shimmer animation (default true).
	 *     @type string $class   Additional CSS class on wrapper.
	 * }
	 * @return string SVG HTML string.
	 */
	public static function render( string $badge_key, array $options = array() ): string {
		$badge = BadgeSystem::BADGES[ $badge_key ] ?? null;
		if ( ! $badge ) {
			return '';
		}

		$size     = max( 1, (int) ( $options['size'] ?? 160 ) );
		$earned   = (bool) ( $options['earned'] ?? true );
		$progress = max( 0, (int) ( $options['progress'] ?? 0 ) );
		$goal     = max( 1, (int) ( $options['goal'] ?? 1 ) );
		$animate  = (bool) ( $options['animate'] ?? true );
		$class    = $options['class'] ?? '';

		$rarity = $badge['rarity'] ?? 'common';
		$m      = self::MATERIALS[ $rarity ] ?? self::MATERIALS['common'];
		$name   = $badge['name'] ?? '';

		// Shape flags.
		$is_shield  = ( 'rare' === $rarity );
		$is_hex     = ( 'epic' === $rarity );
		$is_scallop = ( 'legendary' === $rarity );
		$is_seal    = ( 'special' === $rarity );

		// Canvas dimensions.
		$vw = 200;
		$vh = ( $is_shield || $is_hex ) ? 226 : 200;
		$cx = $vw / 2;
		$cy = $vh / 2;

		// Unique ID for this instance (for gradient references).
		$id = 'bd-' . $badge_key . '-' . wp_rand( 1000, 99999 );

		// Compute radii.
		$outer_r = ( $is_scallop || $is_seal ) ? 80 : 90;
		$inner_r = ( $is_scallop || $is_seal ) ? 74 : 84;
		$ring_r  = ( $is_scallop || $is_seal ) ? 60 : ( ( $is_shield || $is_hex ) ? 68 : 72 );
		$dot_r   = $ring_r - 5;
		$text_r  = $ring_r - 11;

		// Generate shape paths.
		if ( $is_shield ) {
			$outer_path = self::shield_d( $cx, $cy, $outer_r );
			$inner_path = self::shield_d( $cx, $cy, $inner_r );
		} elseif ( $is_hex ) {
			$outer_path = self::hex_d( $cx, $cy, $outer_r );
			$inner_path = self::hex_d( $cx, $cy, $inner_r );
		} elseif ( $is_scallop ) {
			$outer_path = self::scallop_d( $cx, $cy, 74, 18, 14 );
			$inner_path = self::scallop_d( $cx, $cy, 71, 18, 13 );
		} elseif ( $is_seal ) {
			$outer_path = self::seal_d( $cx, $cy, 70, 24 );
			$inner_path = self::seal_d( $cx, $cy, 67, 24 );
		} else {
			$outer_path = self::circle_d( $cx, $cy, $outer_r );
			$inner_path = self::circle_d( $cx, $cy, $inner_r );
		}

		// Build SVG.
		$svg = '';

		// Defs (gradients, filters, clip path).
		$svg .= self::render_defs( $id, $m, $rarity, $outer_path );

		// Outer rim.
		$filter = ( $is_scallop || $is_seal ) ? '' : sprintf( ' filter="url(#%s-drop)"', $id );
		$svg   .= sprintf( '<path d="%s" fill="url(#%s-rim)"%s/>', $outer_path, $id, $filter );

		// Inner surface layers.
		$svg .= sprintf( '<path d="%s" fill="url(#%s-face)"/>', $inner_path, $id );
		$svg .= sprintf( '<path d="%s" fill="url(#%s-depth)"/>', $inner_path, $id );
		$svg .= sprintf( '<path d="%s" fill="url(#%s-shine)"/>', $inner_path, $id );

		// Inner circular ring.
		$svg .= sprintf(
			'<circle cx="%.1f" cy="%.1f" r="%.1f" fill="none" stroke="white" stroke-width="1.5" opacity="0.2"/>',
			$cx,
			$cy,
			$ring_r
		);
		$svg .= sprintf(
			'<circle cx="%.1f" cy="%.1f" r="%.1f" fill="none" stroke="black" stroke-width="0.5" opacity="0.06"/>',
			$cx,
			$cy,
			$ring_r - 2
		);

		// Dot ring (28 dots).
		$dot_count = 28;
		for ( $i = 0; $i < $dot_count; $i++ ) {
			$a   = ( $i / $dot_count ) * M_PI * 2 - M_PI / 2;
			$x   = $cx + cos( $a ) * $dot_r;
			$y   = $cy + sin( $a ) * $dot_r;
			$big = ( 0 === $i % 5 );

			if ( $big && ( $is_scallop || $is_seal ) ) {
				$svg .= sprintf(
					'<rect x="%.1f" y="%.1f" width="4.4" height="4.4" rx="0.6" fill="%s" opacity="0.35" transform="rotate(45 %.1f %.1f)"/>',
					$x - 2.2,
					$y - 2.2,
					$m['textFill'],
					$x,
					$y
				);
			} else {
				$svg .= sprintf(
					'<circle cx="%.1f" cy="%.1f" r="%.1f" fill="%s" opacity="%.2f"/>',
					$x,
					$y,
					$big ? 1.8 : 0.8,
					$m['textFill'],
					$big ? 0.28 : 0.1
				);
			}
		}

		// Lock overlay (before text so text shows through).
		if ( ! $earned ) {
			$svg .= sprintf( '<path d="%s" fill="black" opacity="0.5"/>', $inner_path );
		}

		// Progress ring (unearned, multi-step badges).
		if ( ! $earned && $goal > 1 ) {
			$pct  = min( $progress / $goal, 1 );
			$pr   = $ring_r + 5;
			$circ = 2 * M_PI * $pr;

			$svg .= sprintf(
				'<circle cx="%.1f" cy="%.1f" r="%.1f" fill="none" stroke="white" stroke-width="2.5" opacity="0.05"/>',
				$cx,
				$cy,
				$pr
			);
			$svg .= sprintf(
				'<circle cx="%.1f" cy="%.1f" r="%.1f" fill="none" stroke="%s" stroke-width="2.5" opacity="0.45"'
				. ' stroke-dasharray="%.1f %.1f" stroke-dashoffset="%.1f" stroke-linecap="round"/>',
				$cx,
				$cy,
				$pr,
				$m['lockTint'],
				$circ * $pct,
				$circ * ( 1 - $pct ),
				$circ * 0.25
			);
		}

		// Text arcs.
		$text_fill    = $earned ? $m['textFill'] : $m['shimmer'];
		$text_opacity = $earned ? 0.65 : 0.8;
		$name_opacity = 0.75;

		// Top arc — "LOVE TRIVALLEY".
		$svg .= sprintf(
			'<path id="%s-ta" d="M%.1f,%.1f A%.1f,%.1f 0 0,1 %.1f,%.1f" fill="none"/>',
			$id,
			$cx - $text_r,
			$cy,
			$text_r,
			$text_r,
			$cx + $text_r,
			$cy
		);
		$svg .= sprintf(
			'<text filter="url(#%s-em)" opacity="%.1f">'
			. '<textPath href="#%s-ta" startOffset="50%%" text-anchor="middle">'
			. '<tspan fill="%s" font-size="10" font-weight="800" font-family="system-ui,-apple-system,sans-serif" letter-spacing="2" opacity="%.2f">'
			. 'LOVE TRIVALLEY'
			. '</tspan></textPath></text>',
			$id,
			$earned ? 1 : 0.7,
			$id,
			$text_fill,
			$text_opacity
		);

		// Bottom arc — badge name.
		$name_upper = strtoupper( $name );
		$name_size  = ( strlen( $name ) > 14 ) ? '9.5' : '11.5';
		$name_space = ( strlen( $name ) > 14 ) ? '1' : '2';

		$svg .= sprintf(
			'<path id="%s-ba" d="M%.1f,%.1f A%.1f,%.1f 0 0,0 %.1f,%.1f" fill="none"/>',
			$id,
			$cx - $text_r,
			$cy,
			$text_r,
			$text_r,
			$cx + $text_r,
			$cy
		);
		$svg .= sprintf(
			'<text filter="url(#%s-em)" opacity="%.1f">'
			. '<textPath href="#%s-ba" startOffset="50%%" text-anchor="middle">'
			. '<tspan fill="%s" font-size="%s" font-weight="800" font-family="system-ui,-apple-system,sans-serif" letter-spacing="%s" opacity="%.2f">'
			. '%s'
			. '</tspan></textPath></text>',
			$id,
			$earned ? 1 : 0.7,
			$id,
			$text_fill,
			$name_size,
			$name_space,
			$name_opacity,
			esc_html( $name_upper )
		);

		// Icon in center.
		$icon_name  = self::extract_icon_name( $badge['icon'] ?? '' );
		$icon_size  = ( $is_shield || $is_hex ) ? 42 : 40;
		$icon_color = $earned ? $m['iconColor'] : $m['lockTint'];
		$icon_cy    = ( $is_shield || $is_hex ) ? $cy - 3 : $cy;

		$svg .= sprintf(
			'<g opacity="%s">',
			$earned ? '1' : '0.5'
		);
		$svg .= self::render_icon_path( $icon_name, $cx, $icon_cy, $icon_size, $icon_color );
		$svg .= '</g>';

		// Lock icon (unearned).
		if ( ! $earned ) {
			$lock_size = 16;
			$lock_x    = $cx + 22;
			$lock_y    = $cy + 22;
			$svg      .= sprintf(
				'<circle cx="%.1f" cy="%.1f" r="10" fill="rgba(0,0,0,0.7)" stroke="%s" stroke-width="1" stroke-opacity="0.3"/>',
				$lock_x,
				$lock_y,
				$m['lockTint']
			);
			$svg      .= self::render_icon_path( 'lock', $lock_x, $lock_y, $lock_size, $m['lockTint'] );
		}

		// Check icon (earned special/seal badges).
		if ( $earned && $is_seal ) {
			$check_x = $cx + 22;
			$check_y = $cy + 22;
			$svg    .= self::render_icon_path( 'check', $check_x, $check_y, 14, '#C9A227' );
		}

		// Shimmer overlay (earned only).
		if ( $earned && $animate ) {
			$svg .= sprintf(
				'<g clip-path="url(#%s-clip)">'
				. '<rect x="0" y="-20" width="80" height="%d" fill="url(#%s-shine)" opacity="0.4"'
				. ' transform="translate(-200,0)">'
				. '<animateTransform attributeName="transform" type="translate" from="-200,0" to="320,0"'
				. ' dur="3s" repeatCount="indefinite"/>'
				. '</rect></g>',
				$id,
				$vh + 40,
				$id
			);
		}

		// Wrap in <svg> element.
		$aspect    = $vh / $vw;
		$height    = (int) round( $size * $aspect );
		$css_class = 'bd-badge-svg';
		if ( $class ) {
			$css_class .= ' ' . esc_attr( $class );
		}
		if ( $earned ) {
			$css_class .= ' bd-badge-svg--earned';
		} else {
			$css_class .= ' bd-badge-svg--locked';
		}
		$css_class .= ' bd-badge-svg--' . esc_attr( $rarity );

		$output  = sprintf(
			'<svg class="%s" viewBox="0 0 %d %d" width="%d" height="%d" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="%s">',
			$css_class,
			$vw,
			$vh,
			$size,
			$height,
			esc_attr( $name . ' — ' . $m['rarity'] . ' badge' )
		);
		$output .= $svg;
		$output .= '</svg>';

		return $output;
	}

	/**
	 * Render a small inline badge (for review cards, comment authors).
	 * Simplified version: just the shape + icon, no text arcs or dot ring.
	 *
	 * @param string $badge_key Badge key.
	 * @param int    $size      Size in pixels (default 32).
	 * @return string SVG HTML.
	 */
	public static function render_inline( string $badge_key, int $size = 32 ): string {
		$badge = BadgeSystem::BADGES[ $badge_key ] ?? null;
		if ( ! $badge ) {
			return '';
		}

		$rarity = $badge['rarity'] ?? 'common';
		$m      = self::MATERIALS[ $rarity ] ?? self::MATERIALS['common'];
		$name   = $badge['name'] ?? '';

		$is_shield  = ( 'rare' === $rarity );
		$is_hex     = ( 'epic' === $rarity );
		$is_scallop = ( 'legendary' === $rarity );
		$is_seal    = ( 'special' === $rarity );

		$vw = 200;
		$vh = ( $is_shield || $is_hex ) ? 226 : 200;
		$cx = $vw / 2;
		$cy = $vh / 2;
		$id = 'bd-i-' . $badge_key . '-' . wp_rand( 1000, 99999 );

		$outer_r = ( $is_scallop || $is_seal ) ? 80 : 90;
		$inner_r = ( $is_scallop || $is_seal ) ? 74 : 84;

		if ( $is_shield ) {
			$outer_path = self::shield_d( $cx, $cy, $outer_r );
			$inner_path = self::shield_d( $cx, $cy, $inner_r );
		} elseif ( $is_hex ) {
			$outer_path = self::hex_d( $cx, $cy, $outer_r );
			$inner_path = self::hex_d( $cx, $cy, $inner_r );
		} elseif ( $is_scallop ) {
			$outer_path = self::scallop_d( $cx, $cy, 74, 18, 14 );
			$inner_path = self::scallop_d( $cx, $cy, 71, 18, 13 );
		} elseif ( $is_seal ) {
			$outer_path = self::seal_d( $cx, $cy, 70, 24 );
			$inner_path = self::seal_d( $cx, $cy, 67, 24 );
		} else {
			$outer_path = self::circle_d( $cx, $cy, $outer_r );
			$inner_path = self::circle_d( $cx, $cy, $inner_r );
		}

		$icon_name = self::extract_icon_name( $badge['icon'] ?? '' );
		$icon_cy   = ( $is_shield || $is_hex ) ? $cy - 3 : $cy;

		$aspect = $vh / $vw;
		$height = (int) round( $size * $aspect );

		$svg = sprintf(
			'<svg class="bd-badge-svg-inline bd-badge-svg--%s" viewBox="0 0 %d %d" width="%d" height="%d"'
			. ' xmlns="http://www.w3.org/2000/svg" role="img" aria-label="%s"'
			. ' data-tooltip="%s">',
			esc_attr( $rarity ),
			$vw,
			$vh,
			$size,
			$height,
			esc_attr( $name ),
			esc_attr( $name . ' — ' . $m['rarity'] )
		);

		// Minimal defs: face gradient + rim gradient only.
		$svg .= '<defs>';
		$svg .= sprintf(
			'<radialGradient id="%s-face" cx="42%%" cy="38%%" r="58%%">'
			. '<stop offset="0%%" stop-color="%s"/>'
			. '<stop offset="50%%" stop-color="%s"/>'
			. '<stop offset="100%%" stop-color="%s"/>'
			. '</radialGradient>',
			$id,
			$m['light'],
			$m['base'],
			$m['dark']
		);
		$svg .= sprintf(
			'<radialGradient id="%s-rim" cx="50%%" cy="50%%" r="55%%">'
			. '<stop offset="0%%" stop-color="%s"/>'
			. '<stop offset="100%%" stop-color="%s"/>'
			. '</radialGradient>',
			$id,
			$m['light'],
			$m['dark']
		);
		$svg .= '</defs>';

		// Shapes.
		$svg .= sprintf( '<path d="%s" fill="url(#%s-rim)"/>', $outer_path, $id );
		$svg .= sprintf( '<path d="%s" fill="url(#%s-face)"/>', $inner_path, $id );

		// Icon.
		$svg .= self::render_icon_path( $icon_name, $cx, $icon_cy, 52, $m['iconColor'] );

		$svg .= '</svg>';

		return $svg;
	}

	/**
	 * Get material data for a rarity tier.
	 *
	 * @param string $rarity Rarity key.
	 * @return array Material data.
	 */
	public static function get_material( string $rarity ): array {
		return self::MATERIALS[ $rarity ] ?? self::MATERIALS['common'];
	}
}

<?php
/**
 * Review Prompt Suggestions
 *
 * Lowers the cognitive load of writing a review by offering 3-4 click-to-prefill
 * suggestions tailored to the business category. Aligned with the Love Tri-Valley
 * mission: "share what you love, no rants."
 *
 * @package BusinessDirectory
 * @subpackage Frontend
 */

namespace BD\Frontend;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ReviewPrompts {

	/**
	 * Category-slug → prompt set. The category slugs match the lovetrivalley.com
	 * `bd_category` taxonomy. Order matters: the first matching category wins.
	 *
	 * @var array<string,array<int,string>>
	 */
	private static $prompts_by_category = array(
		'eat-drink'       => array(
			"What's the must-order dish here?",
			'Best time to visit',
			"What's the vibe?",
			'Why I keep coming back',
		),
		'adult-outings'   => array(
			'Best drink on the menu',
			'Why this is my go-to spot',
			"What's the vibe?",
			'When to come',
		),
		'get-outside'     => array(
			'Best feature of this spot',
			'Who would love it here',
			'When to visit',
			'What to bring',
		),
		'family-time'     => array(
			'Why my kids love it',
			'Best age range',
			'When to visit',
			'What to know before you go',
		),
		'wellness'        => array(
			'What I love about this place',
			'Who I\'d send here',
			'Best class or service',
			'What keeps me coming back',
		),
		'shop-local'      => array(
			'Favorite find here',
			'What this place is great for',
			'Why I shop here over the chains',
			'Best time to browse',
		),
		'local-favorites' => array(
			'Why this place is special',
			'What I love most',
			'When to visit',
			'Why I\'d send a friend',
		),
	);

	/**
	 * Generic fallback when no category matches.
	 *
	 * @var array<int,string>
	 */
	private static $generic_prompts = array(
		'What I love about this place',
		'Why I\'d send a friend here',
		'When to visit',
		'What to know before you go',
	);

	/**
	 * Per-request memo: business_id => prompts.
	 *
	 * @var array<int,array<int,string>>
	 */
	private static $cache = array();

	/**
	 * Get the prompt suggestions for a business.
	 *
	 * Walks the business's `bd_category` terms in registered slug order and
	 * returns the first matching set. Falls back to generic prompts.
	 *
	 * @param int $business_id Business post ID.
	 * @return array<int,string>
	 */
	public static function get_for_business( $business_id ) {
		$business_id = (int) $business_id;
		if ( ! $business_id ) {
			return self::$generic_prompts;
		}

		if ( isset( self::$cache[ $business_id ] ) ) {
			return self::$cache[ $business_id ];
		}

		$terms = get_the_terms( $business_id, 'bd_category' );
		if ( is_array( $terms ) && ! empty( $terms ) ) {
			foreach ( $terms as $term ) {
				if ( isset( self::$prompts_by_category[ $term->slug ] ) ) {
					self::$cache[ $business_id ] = self::$prompts_by_category[ $term->slug ];
					return self::$cache[ $business_id ];
				}
			}
		}

		self::$cache[ $business_id ] = self::$generic_prompts;
		return self::$cache[ $business_id ];
	}
}

<?php
/**
 * Template Name: Claim Your Business
 *
 * Landing page promoting the free business claim feature.
 * Lives at lovetrivalley.com/claim.
 * Registered by BD Pro so it works on any theme (main site uses parent Kadence).
 *
 * @package BusinessDirectory
 * @since 0.1.7
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

get_header();

// Enqueue Font Awesome if not already loaded.
if ( ! wp_style_is( 'font-awesome', 'enqueued' ) && ! wp_style_is( 'font-awesome-6', 'enqueued' ) ) {
	wp_enqueue_style(
		'font-awesome-6',
		'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css',
		array(),
		'6.5.1'
	);
}
?>

<style>
	/* ========== CLAIM PAGE — PAGE-SPECIFIC STYLES ========== */
	:root {
		--claim-navy-deep: #0e2740;
		--claim-livermore: #2CB1BC;
		--claim-pleasanton: #9E6071;
		--claim-dublin: #3DA87A;
		--claim-san-ramon: #B07050;
		--claim-danville: #B89A4A;
	}

	/* Remove Kadence default content wrapper padding and heading overrides */
	.claim-page .entry-content-wrap,
	.claim-page .content-wrap {
		padding: 0 !important;
		max-width: 100% !important;
	}
	.claim-page .site-main {
		padding: 0;
	}
	.claim-page h1,
	.claim-page h2,
	.claim-page h3 {
		text-transform: none !important;
		letter-spacing: normal !important;
	}

	/* ===== HERO ===== */
	.claim-hero {
		position: relative;
		min-height: 85vh;
		display: flex;
		align-items: center;
		justify-content: center;
		text-align: center;
		padding: 80px 24px;
		background: linear-gradient(160deg, var(--claim-navy-deep) 0%, var(--vv-primary-dark, #133453) 40%, var(--vv-primary, #1a5c6b) 100%);
		overflow: hidden;
	}
	.claim-hero::before {
		content: '';
		position: absolute;
		inset: 0;
		background:
			radial-gradient(ellipse 600px 400px at 20% 80%, rgba(44, 177, 188, 0.15), transparent),
			radial-gradient(ellipse 500px 350px at 80% 20%, rgba(158, 96, 113, 0.1), transparent),
			radial-gradient(ellipse 400px 300px at 50% 50%, rgba(61, 168, 122, 0.08), transparent);
		pointer-events: none;
	}
	.claim-hero::after {
		content: '';
		position: absolute;
		bottom: -2px;
		left: 0;
		right: 0;
		height: 120px;
		background: linear-gradient(to top, var(--vv-surface, #fbf9f5), transparent);
		pointer-events: none;
	}

	/* Floating hearts */
	.claim-floating-hearts {
		position: absolute;
		inset: 0;
		pointer-events: none;
		overflow: hidden;
	}
	.claim-floating-hearts .claim-heart {
		position: absolute;
		font-size: 18px;
		opacity: 0;
		animation: claimFloatHeart 8s ease-in-out infinite;
	}
	.claim-floating-hearts .claim-heart:nth-child(1) { left: 10%; top: 25%; color: var(--claim-livermore); animation-delay: 0s; }
	.claim-floating-hearts .claim-heart:nth-child(2) { left: 85%; top: 35%; color: var(--claim-pleasanton); animation-delay: 1.5s; }
	.claim-floating-hearts .claim-heart:nth-child(3) { left: 25%; top: 70%; color: var(--claim-dublin); animation-delay: 3s; }
	.claim-floating-hearts .claim-heart:nth-child(4) { left: 75%; top: 65%; color: var(--claim-san-ramon); animation-delay: 4.5s; }
	.claim-floating-hearts .claim-heart:nth-child(5) { left: 50%; top: 20%; color: var(--claim-danville); animation-delay: 6s; }

	@keyframes claimFloatHeart {
		0%, 100% { opacity: 0; transform: translateY(0) scale(0.8); }
		20%, 80% { opacity: 0.3; }
		50% { opacity: 0.5; transform: translateY(-20px) scale(1); }
	}

	.claim-hero-content {
		position: relative;
		z-index: 2;
		max-width: 780px;
	}
	.claim-hero-badge {
		display: inline-flex;
		align-items: center;
		gap: 8px;
		padding: 8px 20px;
		background: rgba(255,255,255,0.1);
		border: 1px solid rgba(255,255,255,0.15);
		border-radius: 50px;
		color: rgba(255,255,255,0.85);
		font-family: var(--vv-font-label, 'Manrope', sans-serif);
		font-size: 14px;
		font-weight: 600;
		letter-spacing: 0.04em;
		text-transform: uppercase;
		margin-bottom: 28px;
		backdrop-filter: blur(8px);
		animation: claimFadeIn 0.8s ease-out 0.2s both;
	}
	.claim-hero-badge i { color: var(--claim-livermore); }

	.claim-hero h1 {
		font-family: var(--vv-font-heading, 'Onest', sans-serif);
		font-size: clamp(2.4rem, 5.5vw, 3.8rem);
		font-weight: 700;
		color: #fff;
		line-height: 1.15;
		margin-bottom: 24px;
		animation: claimFadeIn 0.8s ease-out 0.4s both;
	}
	.claim-hero h1 em {
		font-style: italic;
		color: var(--claim-livermore);
	}
	.claim-hero-sub {
		font-family: var(--vv-font-body, 'Source Sans 3', sans-serif);
		font-size: clamp(1.05rem, 2vw, 1.25rem);
		color: rgba(255,255,255,0.75);
		max-width: 580px;
		margin: 0 auto 40px;
		line-height: 1.65;
		animation: claimFadeIn 0.8s ease-out 0.6s both;
	}
	.claim-cta {
		display: inline-flex;
		align-items: center;
		gap: 10px;
		padding: 16px 36px;
		background: var(--claim-livermore);
		color: #fff;
		font-family: var(--vv-font-heading, 'Onest', sans-serif);
		font-size: 17px;
		font-weight: 700;
		text-decoration: none;
		border-radius: 50px;
		transition: all 0.3s ease;
		box-shadow: 0 4px 20px rgba(44, 177, 188, 0.35);
		animation: claimFadeIn 0.8s ease-out 0.8s both;
	}
	.claim-cta:hover {
		transform: translateY(-2px);
		box-shadow: 0 6px 28px rgba(44, 177, 188, 0.5);
		background: #34c5d1;
		color: #fff;
	}
	.claim-cta i { transition: transform 0.3s; }
	.claim-cta:hover i { transform: translateX(3px); }
	.claim-hero-free {
		display: block;
		margin-top: 16px;
		font-size: 14px;
		color: rgba(255,255,255,0.5);
		animation: claimFadeIn 0.8s ease-out 1s both;
	}
	.claim-hero-secondary {
		margin-top: 24px;
		font-size: 15px;
		color: rgba(255,255,255,0.55);
		animation: claimFadeIn 0.8s ease-out 1.2s both;
	}
	.claim-hero-secondary a {
		color: var(--claim-livermore);
		text-decoration: underline;
		text-underline-offset: 2px;
	}
	.claim-hero-secondary a:hover {
		color: #34c5d1;
	}
	.claim-final-secondary {
		margin-top: 24px;
		font-size: 15px;
		color: rgba(255,255,255,0.55);
	}
	.claim-final-secondary a {
		color: var(--claim-livermore);
		text-decoration: underline;
		text-underline-offset: 2px;
	}
	.claim-final-secondary a:hover {
		color: #34c5d1;
	}

	@keyframes claimFadeIn {
		from { opacity: 0; transform: translateY(16px); }
		to { opacity: 1; transform: translateY(0); }
	}

	/* ===== PROBLEM ===== */
	.claim-problem {
		padding: 100px 24px 80px;
		text-align: center;
		background: var(--vv-surface, #fbf9f5);
	}
	.claim-problem-inner {
		max-width: 800px;
		margin: 0 auto;
	}
	.claim-section-label {
		display: inline-block;
		font-family: var(--vv-font-heading, 'Onest', sans-serif);
		font-size: 12px;
		font-weight: 700;
		letter-spacing: 0.12em;
		text-transform: uppercase;
		color: var(--claim-livermore);
		margin-bottom: 16px;
	}
	.claim-problem h2 {
		font-family: var(--vv-font-heading, 'Onest', sans-serif);
		font-size: clamp(1.8rem, 3.5vw, 2.6rem);
		font-weight: 700;
		color: var(--vv-on-surface, #1b1c1a);
		line-height: 1.25;
		margin-bottom: 20px;
	}
	.claim-problem p {
		font-family: var(--vv-font-body, 'Source Sans 3', sans-serif);
		font-size: 1.1rem;
		color: var(--vv-on-surface-variant, #43474e);
		line-height: 1.7;
		max-width: 620px;
		margin: 0 auto;
	}

	/* ===== VALUE GRID ===== */
	.claim-values {
		padding: 40px 24px 100px;
		background: var(--vv-surface, #fbf9f5);
	}
	.claim-values-inner {
		max-width: 1080px;
		margin: 0 auto;
	}
	.claim-values-grid {
		display: grid;
		grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
		gap: 24px;
	}
	.claim-value-card {
		padding: 36px 32px;
		background: #fff;
		border: var(--vv-ghost-border, 1px solid rgba(195, 198, 206, 0.15));
		border-radius: var(--vv-radius-card, 0.75rem);
		transition: all 0.35s ease;
	}
	.claim-value-card:hover {
		transform: translateY(-4px);
		box-shadow: var(--vv-shadow-hover, 0 12px 32px rgba(27, 28, 26, 0.1));
		border-color: rgba(44, 177, 188, 0.25);
	}
	.claim-value-icon {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 52px;
		height: 52px;
		border-radius: 14px;
		margin-bottom: 20px;
		font-size: 22px;
	}
	.claim-value-card:nth-child(1) .claim-value-icon { background: rgba(44, 177, 188, 0.1); color: var(--claim-livermore); }
	.claim-value-card:nth-child(2) .claim-value-icon { background: rgba(158, 96, 113, 0.1); color: var(--claim-pleasanton); }
	.claim-value-card:nth-child(3) .claim-value-icon { background: rgba(61, 168, 122, 0.1); color: var(--claim-dublin); }
	.claim-value-card:nth-child(4) .claim-value-icon { background: rgba(176, 112, 80, 0.1); color: var(--claim-san-ramon); }
	.claim-value-card:nth-child(5) .claim-value-icon { background: rgba(184, 154, 74, 0.1); color: var(--claim-danville); }
	.claim-value-card:nth-child(6) .claim-value-icon { background: rgba(19, 52, 83, 0.08); color: var(--vv-primary-dark, #133453); }
	.claim-value-card h3 {
		font-family: var(--vv-font-heading, 'Onest', sans-serif);
		font-size: 1.15rem;
		font-weight: 700;
		color: var(--vv-on-surface, #1b1c1a);
		margin-bottom: 10px;
	}
	.claim-value-card p {
		font-family: var(--vv-font-body, 'Source Sans 3', sans-serif);
		font-size: 0.95rem;
		color: var(--vv-on-surface-muted, #5c6370);
		line-height: 1.6;
	}

	/* ===== HOW IT WORKS ===== */
	.claim-how {
		padding: 100px 24px;
		background: var(--vv-surface-low, #f5f3ef);
		border-top: var(--vv-ghost-border, 1px solid rgba(195, 198, 206, 0.15));
		border-bottom: var(--vv-ghost-border, 1px solid rgba(195, 198, 206, 0.15));
	}
	.claim-how-inner {
		max-width: 900px;
		margin: 0 auto;
		text-align: center;
	}
	.claim-how h2 {
		font-family: var(--vv-font-heading, 'Onest', sans-serif);
		font-size: clamp(1.6rem, 3.5vw, 2.4rem);
		font-weight: 700;
		color: var(--vv-on-surface, #1b1c1a);
		margin-bottom: 56px;
	}
	.claim-steps {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 40px;
		text-align: center;
	}
	.claim-step-num {
		display: flex;
		align-items: center;
		justify-content: center;
		width: 48px;
		height: 48px;
		margin: 0 auto 20px;
		background: var(--vv-primary-dark, #133453);
		color: #fff;
		font-family: var(--vv-font-heading, 'Onest', sans-serif);
		font-size: 18px;
		font-weight: 800;
		border-radius: 50%;
	}
	.claim-step h3 {
		font-family: var(--vv-font-heading, 'Onest', sans-serif);
		font-size: 1.05rem;
		font-weight: 700;
		color: var(--vv-on-surface, #1b1c1a);
		margin-bottom: 8px;
	}
	.claim-step p {
		font-family: var(--vv-font-body, 'Source Sans 3', sans-serif);
		font-size: 0.92rem;
		color: var(--vv-on-surface-muted, #5c6370);
		line-height: 1.55;
	}

	/* ===== SOCIAL PROOF ===== */
	.claim-proof {
		padding: 100px 24px;
		text-align: center;
		background: var(--vv-surface, #fbf9f5);
	}
	.claim-proof-inner {
		max-width: 900px;
		margin: 0 auto;
	}
	.claim-proof h2 {
		font-family: var(--vv-font-heading, 'Onest', sans-serif);
		font-size: clamp(1.6rem, 3.5vw, 2.4rem);
		font-weight: 700;
		color: var(--vv-on-surface, #1b1c1a);
		margin-bottom: 48px;
	}
	.claim-stats-row {
		display: grid;
		grid-template-columns: repeat(3, 1fr);
		gap: 32px;
		margin-bottom: 56px;
	}
	.claim-stat-number {
		font-family: var(--vv-font-heading, 'Onest', sans-serif);
		font-size: clamp(2.2rem, 4vw, 3rem);
		font-weight: 800;
		color: var(--vv-primary-dark, #133453);
		line-height: 1;
	}
	.claim-stat-label {
		font-family: var(--vv-font-body, 'Source Sans 3', sans-serif);
		font-size: 0.92rem;
		color: var(--vv-on-surface-muted, #5c6370);
		margin-top: 6px;
	}
	.claim-city-bar {
		display: flex;
		justify-content: center;
		gap: 28px;
		flex-wrap: wrap;
	}
	.claim-city-tag {
		display: inline-flex;
		align-items: center;
		gap: 6px;
		padding: 10px 22px;
		border-radius: 50px;
		font-family: var(--vv-font-heading, 'Onest', sans-serif);
		font-size: 15px;
		font-weight: 600;
		border: 1.5px solid;
	}
	.claim-city-tag.livermore   { color: var(--claim-livermore);   border-color: var(--claim-livermore);   background: rgba(44,177,188,0.06); }
	.claim-city-tag.pleasanton { color: var(--claim-pleasanton); border-color: var(--claim-pleasanton); background: rgba(158,96,113,0.06); }
	.claim-city-tag.dublin     { color: var(--claim-dublin);     border-color: var(--claim-dublin);     background: rgba(61,168,122,0.06); }
	.claim-city-tag.san-ramon  { color: var(--claim-san-ramon);  border-color: var(--claim-san-ramon);  background: rgba(176,112,80,0.06); }
	.claim-city-tag.danville   { color: var(--claim-danville);   border-color: var(--claim-danville);   background: rgba(184,154,74,0.06); }
	.claim-city-tag i { font-size: 12px; }

	/* ===== FINAL CTA ===== */
	.claim-final {
		padding: 100px 24px;
		text-align: center;
		background: linear-gradient(160deg, var(--claim-navy-deep), var(--vv-primary-dark, #133453) 50%, var(--vv-primary, #1a5c6b));
		position: relative;
		overflow: hidden;
	}
	.claim-final::before {
		content: '';
		position: absolute;
		inset: 0;
		background: radial-gradient(ellipse 500px 400px at 50% 60%, rgba(44,177,188,0.12), transparent);
		pointer-events: none;
	}
	.claim-final-inner {
		position: relative;
		z-index: 2;
		max-width: 720px;
		margin: 0 auto;
	}
	.claim-final h2 {
		font-family: var(--vv-font-heading, 'Onest', sans-serif);
		font-size: clamp(1.8rem, 4vw, 2.6rem);
		font-weight: 700;
		color: #fff;
		line-height: 1.25;
		margin-bottom: 16px;
	}
	.claim-final p {
		font-family: var(--vv-font-body, 'Source Sans 3', sans-serif);
		font-size: 1.1rem;
		color: rgba(255,255,255,0.7);
		margin-bottom: 36px;
		line-height: 1.6;
	}
	.claim-final .claim-cta {
		animation: none;
		font-size: 18px;
		padding: 18px 42px;
	}
	.claim-final .claim-hero-free {
		animation: none;
	}

	/* ===== RESPONSIVE ===== */
	@media (max-width: 768px) {
		.claim-hero { min-height: 75vh; padding: 60px 20px; }
		.claim-steps { grid-template-columns: 1fr; gap: 32px; }
		.claim-stats-row { grid-template-columns: 1fr; gap: 24px; }
		.claim-values-grid { grid-template-columns: 1fr; }
		.claim-city-bar { gap: 12px; }
		.claim-city-tag { padding: 8px 16px; font-size: 14px; }
	}
</style>

<div class="claim-page">

	<!-- ==================== HERO ==================== -->
	<section class="claim-hero">
		<div class="claim-floating-hearts">
			<span class="claim-heart"><i class="fa-solid fa-heart"></i></span>
			<span class="claim-heart"><i class="fa-solid fa-heart"></i></span>
			<span class="claim-heart"><i class="fa-solid fa-heart"></i></span>
			<span class="claim-heart"><i class="fa-solid fa-heart"></i></span>
			<span class="claim-heart"><i class="fa-solid fa-heart"></i></span>
		</div>
		<div class="claim-hero-content">
			<div class="claim-hero-badge">
				<i class="fa-solid fa-heart"></i>
				For Tri-Valley Businesses
			</div>
			<h1>Your neighbors are looking for you.<br><em>Make sure they find you.</em></h1>
			<p class="claim-hero-sub">Love TriValley is the community-powered guide to local businesses, events, and experiences across five cities. Claim your free listing and show up where 368,000+ residents are discovering what's local.</p>
			<a href="<?php echo esc_url( home_url( '/add-your-business/' ) ); ?>" class="claim-cta">
				Add Your Business <i class="fa-solid fa-arrow-right"></i>
			</a>
			<span class="claim-hero-free">100% free &middot; No contracts &middot; Takes 5 minutes</span>
			<p class="claim-hero-secondary">Already listed? <a href="<?php echo esc_url( home_url( '/explore/' ) ); ?>">Find your business</a> and click "Claim" to take ownership.</p>
		</div>
	</section>

	<!-- ==================== THE PROBLEM ==================== -->
	<section class="claim-problem">
		<div class="claim-problem-inner">
			<span class="claim-section-label">The Challenge</span>
			<h2>There's a whole community out there that hasn't found you yet.</h2>
			<p>Residents are searching for local recommendations every day &mdash; the best coffee, the right plumber, the Saturday morning spot for their family. But without a strong local presence, great businesses stay invisible to the neighbors who'd love them most.</p>
		</div>
	</section>

	<!-- ==================== VALUE GRID ==================== -->
	<section class="claim-values">
		<div class="claim-values-inner">
			<div class="claim-values-grid">

				<div class="claim-value-card">
					<div class="claim-value-icon"><i class="fa-solid fa-magnifying-glass-location"></i></div>
					<h3>Get Found by Your Neighbors</h3>
					<p>When someone searches for local businesses, your listing appears &mdash; powered by AI-driven local search that connects residents to the right businesses.</p>
				</div>

				<div class="claim-value-card">
					<div class="claim-value-icon"><i class="fa-solid fa-star"></i></div>
					<h3>Build Trust Through Reviews</h3>
					<p>Community reviews and ratings signal quality to new customers. Claimed businesses with active review profiles earn trust badges that set them apart.</p>
				</div>

				<div class="claim-value-card">
					<div class="claim-value-icon"><i class="fa-solid fa-pen-to-square"></i></div>
					<h3>Control Your Story</h3>
					<p>Update your hours, photos, menus, and description anytime. A claimed listing means you decide how your business shows up.</p>
				</div>

				<div class="claim-value-card">
					<div class="claim-value-icon"><i class="fa-solid fa-calendar-days"></i></div>
					<h3>Promote Events for Free</h3>
					<p>Hosting a tasting, a grand opening, or a community event? Your events appear across the Love TriValley network &mdash; reaching all five cities.</p>
				</div>

				<div class="claim-value-card">
					<div class="claim-value-icon"><i class="fa-solid fa-wand-magic-sparkles"></i></div>
					<h3>Get Recommended by the Guides</h3>
					<p>Our AI-powered Local Scout recommends businesses based on what residents are looking for. Rich, complete profiles get surfaced more often.</p>
				</div>

				<div class="claim-value-card">
					<div class="claim-value-icon"><i class="fa-solid fa-shield-heart"></i></div>
					<h3>Join a Community That Has Your Back</h3>
					<p>This isn't a national directory that treats you like a data point. Love TriValley is built by neighbors, for neighbors.</p>
				</div>

			</div>
		</div>
	</section>

	<!-- ==================== HOW IT WORKS ==================== -->
	<section class="claim-how">
		<div class="claim-how-inner">
			<span class="claim-section-label">The Plan</span>
			<h2>Claim your listing in three easy steps</h2>
			<div class="claim-steps">
				<div class="claim-step">
					<div class="claim-step-num">1</div>
					<h3>Find Your Business</h3>
					<p>Search for your business on LoveTriValley.com. If it's already listed, you're halfway there. If not, add it in minutes.</p>
				</div>
				<div class="claim-step">
					<div class="claim-step-num">2</div>
					<h3>Claim &amp; Customize</h3>
					<p>Verify you're the owner, then add your photos, hours, description, website, and everything that makes your place special.</p>
				</div>
				<div class="claim-step">
					<div class="claim-step-num">3</div>
					<h3>Get Discovered</h3>
					<p>Your listing goes live across all five city sites &mdash; Livermore, Pleasanton, Dublin, San Ramon, and Danville. You're now part of the network.</p>
				</div>
			</div>
		</div>
	</section>

	<!-- ==================== SOCIAL PROOF ==================== -->
	<section class="claim-proof">
		<div class="claim-proof-inner">
			<span class="claim-section-label">The Community</span>
			<h2>You're joining something bigger</h2>
			<div class="claim-stats-row">
				<div>
					<div class="claim-stat-number">368K+</div>
					<div class="claim-stat-label">Tri-Valley Residents</div>
				</div>
				<div>
					<div class="claim-stat-number">5</div>
					<div class="claim-stat-label">Cities</div>
				</div>
				<div>
					<div class="claim-stat-number">1</div>
					<div class="claim-stat-label">Community</div>
				</div>
			</div>
			<div class="claim-city-bar">
				<span class="claim-city-tag livermore"><i class="fa-solid fa-heart"></i> Livermore</span>
				<span class="claim-city-tag pleasanton"><i class="fa-solid fa-heart"></i> Pleasanton</span>
				<span class="claim-city-tag dublin"><i class="fa-solid fa-heart"></i> Dublin</span>
				<span class="claim-city-tag san-ramon"><i class="fa-solid fa-heart"></i> San Ramon</span>
				<span class="claim-city-tag danville"><i class="fa-solid fa-heart"></i> Danville</span>
			</div>
		</div>
	</section>

	<!-- ==================== FINAL CTA ==================== -->
	<section class="claim-final" id="claim">
		<div class="claim-final-inner">
			<h2>Your listing is waiting. Your neighbors are searching.</h2>
			<p>Claim your free listing today and show up where it matters most &mdash; in the community guide that 368,000+ Tri-Valley residents trust for local recommendations.</p>
			<a href="<?php echo esc_url( home_url( '/add-your-business/' ) ); ?>" class="claim-cta">
				Add Your Business <i class="fa-solid fa-arrow-right"></i>
			</a>
			<span class="claim-hero-free">No credit card &middot; No contracts &middot; Always free</span>
			<p class="claim-final-secondary">Already listed? <a href="<?php echo esc_url( home_url( '/explore/' ) ); ?>">Find your business</a> and click "Claim" to take ownership.</p>
		</div>
	</section>

</div>

<?php
get_footer();

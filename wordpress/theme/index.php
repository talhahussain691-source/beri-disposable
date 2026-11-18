<?php
/**
 * Fallback for anything that is not one of the BERI pages (posts, other pages, search, 404).
 * Plain content on the BERI colours, with a link back to the site.
 */

defined( 'ABSPATH' ) || exit;
$beri = get_template_directory_uri();
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Anton&amp;family=Inter:wght@400;500;600;700&amp;display=swap">
<style>
body{margin:0;background:#F6F3EE;color:#111113;font-family:'Inter',system-ui,sans-serif;font-size:17px;line-height:1.65}
.beri-bar{background:#111113;padding:18px 24px}
.beri-bar img{display:block;height:40px;width:auto}
.beri-main{max-width:820px;margin:0 auto;padding:64px 24px 120px}
.beri-main h1,.beri-main h2{font-family:'Anton',Impact,sans-serif;font-weight:400;text-transform:uppercase;line-height:1}
.beri-main h1{font-size:56px;margin:0 0 24px}
.beri-main a{color:#7A5F14}
.beri-main img{max-width:100%;height:auto}
.beri-foot{background:#111113;color:#B0ACA5;font-size:14px;padding:32px 24px;text-align:center}
</style>
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="beri-bar"><a href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="BERI home"><img src="<?php echo esc_url( $beri ); ?>/assets/blob/bd8d6ee885cd447f39d73ea5ec4478b3.png" alt="BERI"></a></div>
<main class="beri-main">
<?php if ( have_posts() ) : ?>
	<?php while ( have_posts() ) : the_post(); ?>
		<article <?php post_class(); ?>>
			<h1><?php the_title(); ?></h1>
			<?php the_content(); ?>
		</article>
	<?php endwhile; ?>
<?php else : ?>
	<h1>Page not found</h1>
	<p>This page doesn’t exist. <a href="<?php echo esc_url( home_url( '/' ) ); ?>">Go to the BERI home page</a>.</p>
<?php endif; ?>
</main>
<div class="beri-foot">Sale of these products is restricted to adults 21 and over. WARNING: This product contains nicotine. Nicotine is an addictive chemical.</div>
<?php wp_footer(); ?>
</body>
</html>

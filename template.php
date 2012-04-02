<!DOCTYPE html>
<html>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>" />
<meta name="viewport" content="width=device-width" />
<title><?php
wp_title( '|', true, 'right' );
bloginfo( 'name' );
?></title>
<link rel="profile" href="http://gmpg.org/xfn/11" />
<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>" />
<meta name="apple-mobile-web-app-capable" content="yes" />
<?php wp_head(); ?>
</head>
<body class="impress-not-supported">

<div class="fallback-message">
    <p>Your browser <b>doesn't support the features required</b> by impress.js, so you are presented with a simplified version of this presentation.</p>
    <p>For the best experience please use the latest <b>Chrome</b>, <b>Safari</b> or <b>Firefox</b> browser.</p>
</div>

<div id="impress">
	<div id="<?php echo sanitize_title( get_the_title() ) ?>" class="step" data-x="-1000" data-y="-1500">
		<?php while ( have_posts() ) : the_post(); the_content(); endwhile; ?>
    </div>


	<?php
		if( ! ( $slides = get_transient( 'presentation_' . get_the_ID() ) ) ) {
			$slides = new WP_Query(array(
				'post_type' => 'presentation',
				'post_parent' => get_the_ID(),
				'orderby' => 'menu_order',
				'order' => 'ASC',
				'posts_per_page' => -1
			));
			set_transient( 'presentation_' . get_the_ID(), $slides );
		}

		if( $slides->have_posts() ) :
			while( $slides->have_posts() ) : $slides->the_post();
				$data_x = get_post_meta( get_the_ID(), 'data-x', true );
				$data_y = get_post_meta( get_the_ID(), 'data-y', true );
				$data_z = get_post_meta( get_the_ID(), 'data-z', true );
				$data_scale = get_post_meta( get_the_ID(), 'data-scale', true );
				$data_rotate = get_post_meta( get_the_ID(), 'data-rotate', true );
				?>
				<div 
					id="<?php echo sanitize_title( get_the_title() ) ?>" 
					class="step"
					data-x="<?php echo esc_attr( $data_x ); ?>"
					data-y="<?php echo esc_attr( $data_y ); ?>"
					data-z="<?php echo esc_attr( $data_z ); ?>"
					data-rotate="<?php echo esc_attr( $data_rotate ); ?>"
					data-scale="<?php echo esc_attr( $data_scale ); ?>">
					<?php the_content(); ?>
				</div>
				<?php
			endwhile;
			wp_reset_postdata();
		endif;
	?>
</div>

<div class="hint">
    <p>Use a spacebar or arrow keys to navigate</p>
</div>

<script>
if ("ontouchstart" in document.documentElement) { 
	document.querySelector(".hint").innerHTML = "<p>Tap on the left or right to navigate</p>";
}

jQuery(document).ready(function($) {
	impress().init();
})
</script>
<?php wp_footer(); ?>

</body>
</html>
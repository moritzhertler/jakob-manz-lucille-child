<?php

	$artist_id = get_the_ID();

	/*general options*/
	$artist_nickname = esc_html(get_post_meta($artist_id, 'artist_nickname', true));
	$artist_website = esc_url(get_post_meta($artist_id, 'artist_website', true));

	/*social options*/
	$available_artist_profiles = array(
		/*'icon name fa-[icon name]'	=> 'settings name'*/
		'facebook'			=> 'artist_facebook',
		'twitter'			=>'artist_twitter',		
		'instagram'			=> 'artist_instagram',
		'soundcloud'		=>'artist_soundcloud',	
		'youtube'			=>'artist_youtube',
		'spotify'			=>'artist_spotify'
	);

	$artist_profiles = array();
	foreach ($available_artist_profiles as $key =>	$profile) {
		$profile_url = esc_url(get_post_meta($artist_id, $profile, true));

		if (!empty($profile_url)) {
			$single_profile = array();
			$single_profile['url'] 	= $profile_url;
			$single_profile['icon'] 	= $key;

			$artist_profiles[] = $single_profile;
		}
	}

?>
<!--
	<div class="lc_swp_boxed swp_artist_social_web clearfix">
		<div class="artist_website">
			<?php if (!empty($artist_website)) { ?>
				<a href="<?php echo esc_attr(esc_url($artist_website)) ;?>" target="_blank">
					<?php echo esc_html__("Official website", "lucille"); ?>
				</a>	
			<?php } ?>
		</div>

		<div class="artist_social_single">
			<?php if (count($artist_profiles)) { ?>
				<span class="artist_follow">
					<?php echo esc_html__("Follow:", "lucille"); ?>
				</span>			
			<?php } ?>


			<?php foreach ($artist_profiles as $social_profile) { ?>
				<div class="artist_social_profile artist_single">
					<a href="<?php echo esc_url($social_profile['url']); ?>" target="_blank">
						<i class="fab fa-<?php echo esc_attr($social_profile['icon']); ?>"></i>
					</a>
				</div>
			<?php }	?>		
		</div>
	</div>
-->

	<div class="lc_swp_full lc_basic_content_padding">
		<?php the_content(); ?>
		<?php
			if (LUCILLE_SWP_has_cpt_comments()) {
				comments_template();
			}
		?>
	</div>

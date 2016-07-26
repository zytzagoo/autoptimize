<?php
/*
Classlessly add a "more tools" tab to promote (future) AO addons and/ or affiliate services
*/

add_action('admin_init', 'ao_partner_tabs_preinit');

function ao_partner_tabs_preinit() {
    if (apply_filters('autoptimize_filter_show_partner_tabs',true)) {
        add_filter('autoptimize_filter_settingsscreen_tabs','ao_add_partner_tabs');
    }
}

function ao_add_partner_tabs($in) {
	$in=array_merge($in,array('ao_partners' => 'Optimize More!'));
	return $in;
}

add_action('admin_menu','ao_partners_init');
function ao_partners_init() {
	$hook=add_submenu_page(NULL,'AO partner','AO partner','manage_options','ao_partners','ao_partners');
	// register_settings here as well if needed
}

function ao_partners() {
	?>
    <style>
	.itemDetail {
		background: #fff;
		width: 250px;
		height: 270px;
		border: 1px solid #ccc;
		float: left;
		padding: 15px;
		position: relative;
		margin: 0 10px 10px 0;
	}
	.itemTitle {
		margin-top:0px;
	}
	.itemImage {
		max-width: 95%;
	}
	.itemButton {
		position:absolute;
		bottom: 20px;
		right: 20px;
	}
	.itemButton a {
		text-decoration: none;
		color: #555;
	}
	.itemButton a:hover {
		text-decoration: none;
		color: #23282d;
	}
    </style>
	<div class="wrap">
		<h1><?php _e('Autoptimize Settings','autoptimize'); ?></h1>
		<?php echo autoptimizeConfig::ao_admin_tabs(); ?>
		<?php
				_e("<h2>These Autoptimize power-ups and related services will improve your site's performance even more!</h2>","autoptimize");
		?>
		<div>
			<?php getAOPartnerFeed(); ?>
		</div>
	</div>
	<?php
}

function getAOPartnerFeed() {
	$noFeedText=__( 'Have a look at <a href="http://optimizingmatters.com/">optimizingmatters.com</a> for Autoptimize power-ups!', 'autoptimize' );

	if (apply_filters('autoptimize_settingsscreen_remotehttp',true)) {
		$rss = fetch_feed( "http://optimizingmatters.com/downloads/feed/" );
		$maxitems = 0;

		if ( ! is_wp_error( $rss ) ) {
			$maxitems = $rss->get_item_quantity( 10 );
			$rss_items = $rss->get_items( 0, $maxitems );
		} ?>
		<ul>
			<?php
			if ( $maxitems == 0 ) {
				echo $noFeedText;
			} else {
				foreach ( $rss_items as $item ) : ?>
					<li class="itemDetail">
						<h3 class="itemTitle"><a href="<?php echo esc_url( $item->get_permalink() ); ?>"><?php echo esc_html( $item->get_title() ); ?></a></h3>
						<?php
						if (($enclosure = $item->get_enclosure()) && (strpos($enclosure->get_type(),"image")!==false) ) {
							$img_url=esc_url($enclosure->get_link());
							echo "<img class=\"itemImage\" src=\"".$img_url."\"/>";
						}
						?>
						<div class="itemDescription"><?php echo wp_kses_post($item -> get_description() ); ?></div>
						<div class="itemButton button-secondary"><a href="<?php echo esc_url( $item->get_permalink() ); ?>">More info</a></div>
					</li>
				<?php endforeach; ?>
			<?php } ?>
		</ul>
		<?php
	} else {
		echo $noFeedText;
	}
}
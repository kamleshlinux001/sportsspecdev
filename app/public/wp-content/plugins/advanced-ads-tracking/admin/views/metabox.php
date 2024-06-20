<?php
/**
 * Tracking meta-box markup.
 *
 * @var string $public_id   Public ad id, either auto-generated or set by user.
 * @var string $public_link The public stats link for this ad.
 * @var string $public_name The public name for this ad, as set by the user.
 * @var array  $warnings    Array holding admin notices/warnings.
 */
global $post;

$the_ad = new Advanced_Ads_Ad( $post->ID );


?>
	<style type="text/css">
		#tracking-ads-box .form-group {
			margin: 8px;
			padding: 6px;
		}

		#tracking-ads-box .form-group label {
			display: block;
			font-weight: bold;
			margin: 6px 0 8px 0;
		}
	</style>
<?php if ( ! empty( $warnings ) ) : ?>
	<ul id="tracking-ads-box-notices" class="advads-metabox-notices">
	<?php
	foreach ( $warnings as $_warning ) {
		?>
			<li <?php echo isset( $_warning['class'] ) ? 'class="' . esc_attr( $_warning['class'] ) . '"' : ''; ?>>
		<?php
		echo wp_kses(
			$_warning['text'],
			[
				'a' => [
					'href'   => [],
					'target' => [],
				],
			]
		);
		?>
			</li>
		<?php
	}
endif;
// hide options if Google Analytics tracking method is used
if ( $this->plugin->get_tracking_method() !== 'ga' ) :
	?>
	</ul>
	<div class="advads-option-list">
		<?php
		global $wpdb;
		$admin_ad_title = $post->post_title;

		$to   = date_create( 'today', Advanced_Ads_Tracking_Util::get_wp_timezone() );
		$from = date_create( '14 days ago', Advanced_Ads_Tracking_Util::get_wp_timezone() );

		$clicks_stats = $this->load_stats(
			[
				'ad_id'   => [ $post->ID ],
				'period'  => 'custom',
				'groupby' => 'day',
				'from'    => $from->format( 'm/d/Y' ),
				'to'      => $to->format( 'm/d/Y' ),
			],
			$wpdb->prefix . 'advads_clicks'
		);

		$impressions_stats = $this->load_stats(
			[
				'ad_id'   => [ $post->ID ],
				'period'  => 'custom',
				'groupby' => 'day',
				'from'    => $from->format( 'm/d/Y' ),
				'to'      => $to->format( 'm/d/Y' ),
			],
			$wpdb->prefix . 'advads_impressions'
		);

		// fill missing dates with empty values in clicks and impressions stats.
		while ( $to >= $from ) {
			$to_key = $to->format( 'Y-m-d' );
			if ( ! array_key_exists( $to_key, $impressions_stats ) ) {
				$impressions_stats[ $to_key ] = [ $post->ID => null ];
			}
			if ( ! array_key_exists( $to_key, $clicks_stats ) ) {
				$clicks_stats[ $to_key ] = [ $post->ID => null ];
			}
			$to->modify( '-1 day' );
		}

		ksort( $impressions_stats );
		ksort( $clicks_stats );

		$stats = [
			'ID'          => $post->ID,
			'impressions' => $impressions_stats,
			'clicks'      => $clicks_stats,
		];

		?>
		<script type="text/javascript">
			var advads_stats = <?php echo json_encode( $stats ); ?>;
		</script>
		<div id="stats-jqplot"></div>
		<?php if ( false !== $impressions_stats ) : ?>
			<hr/>
		<?php endif; ?>
		<span class="label"><?php _e( 'Reports', 'advanced-ads-tracking' ); ?></span>
		<div>
			<b>
				<a href="<?php echo Advanced_Ads_Tracking_Admin::admin_30days_stats_url( $post->ID ); ?>" id="ad-dashboard-stats"><?php _e( 'Dashboard', 'advanced-ads-tracking' ); ?></a>
				<?php if ( ! defined( 'ADVANCED_ADS_TRACKING_NO_PUBLIC_STATS' ) && $public_id ) : ?>
				<a id="ad-public-link" href="<?php echo esc_url( $public_link ); ?>" style="margin-left:1.5rem;margin-right:1.5rem;"><?php _e( 'Shareable Link', 'advanced-ads-tracking' ); ?></a>
				<a href="#" id="regenerateSharableLink"><i class="dashicons dashicons-update" style="text-decoration:none;" title="<?php esc_attr_e( 'Generate a new sharable link', 'advanced-ads-tracking' ); ?>"></i></a>
				<span style="color:#dc3232;display:none;" id="save-new-public-link"><?php esc_html_e( 'Save the ad to confirm the change', 'advanced-ads-tracking' ); ?><span/>
				<?php else : ?>
					<i class="dashicons dashicons-info" style="color:#ff9800;margin-left:.5em;font-size:1.75em;cursor:pointer;" title="<?php echo esc_attr( __( 'The public report URL for this ad will be generated the next time it is saved.', 'advanced-ads-tracking' ) ); ?>"></i>
				<?php endif; ?>
			</b>
		</div>
		<hr/>
		<span class="label"><?php _e( 'Public name', 'advanced-ads-tracking' ); ?></span>
		<div>
			<input type="text" name="advanced_ad[tracking][public-name]" value="<?php echo $public_name; ?>"/>
			<p class="description">
				<?php _e( 'Will be used as ad name instead of the internal ad title', 'advanced-ads-tracking' ); ?>
				&nbsp;<?php echo ( ! empty( $admin_ad_title ) ) ? '(' . $admin_ad_title . ')' : ''; ?>
			</p>
		</div>
		<hr/>
		<?php
		// separate limiter into own view.
		require AAT_BASE_PATH . 'admin/views/metabox-limiter.php';
		?>
		<input type="hidden" name="advanced_ad[tracking][public-id]" value="<?php echo esc_attr( $public_id ); ?>"/>
		<span class="label"><?php _e( 'report recipient', 'advanced-ads-tracking' ); ?></span>
		<div>
			<?php if ( $billing_email ) : ?>
				<input type="hidden" name="advanced_ad[tracking][report-recip]" value=""/>
				<input type="text" style="width:66%;" disabled value="<?php echo esc_attr( $billing_email ); ?>"/>
			<?php else : ?>
				<input type="text" style="width:66%;" name="advanced_ad[tracking][report-recip]" value="<?php echo esc_attr( $report_recip ); ?>"/>
			<?php endif; ?>
			<p class="description">
				<?php _e( 'Email address to send the performance report for this ad', 'advanced-ads-tracking' ); ?>.&nbsp;<?php _e( 'Separate multiple emails with commas', 'advanced-ads-tracking' ); ?></p>
		</div>
		<hr>
		<span class="label"><?php _e( 'report period', 'advanced-ads-tracking' ); ?></span>
		<div>
			<select name="advanced_ad[tracking][report-period]">
				<option value="last30days" <?php selected( $report_period, 'last30days' ); ?>><?php _e( 'last 30 days', 'advanced-ads-tracking' ); ?></option>
				<option value="lastmonth" <?php selected( $report_period, 'lastmonth' ); ?>><?php _e( 'last month', 'advanced-ads-tracking' ); ?></option>
				<option value="last12months" <?php selected( $report_period, 'last12months' ); ?>><?php _e( 'last 12 months', 'advanced-ads-tracking' ); ?></option>
			</select>
			<p class="description"><?php _e( 'Period used in the report', 'advanced-ads-tracking' ); ?></p>
		</div>
		<hr>
		<span class="label"><?php _e( 'report frequency', 'advanced-ads-tracking' ); ?></span>
		<div>
			<select name="advanced_ad[tracking][report-frequency]">
				<option value="never" <?php selected( $report_frequency, 'never' ); ?>><?php _e( 'never', 'advanced-ads-tracking' ); ?></option>
				<option value="daily" <?php selected( $report_frequency, 'daily' ); ?>><?php _e( 'daily', 'advanced-ads-tracking' ); ?></option>
				<option value="weekly" <?php selected( $report_frequency, 'weekly' ); ?>><?php _e( 'weekly', 'advanced-ads-tracking' ); ?></option>
				<option value="monthly" <?php selected( $report_frequency, 'monthly' ); ?>><?php _e( 'monthly', 'advanced-ads-tracking' ); ?></option>
			</select>
			<p class="description"><?php _e( 'How often to send email reports', 'advanced-ads-tracking' ); ?></p>
		</div>
		<hr>
	</div>
	<?php
endif;

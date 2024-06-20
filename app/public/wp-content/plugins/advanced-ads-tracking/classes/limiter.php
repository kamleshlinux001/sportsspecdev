<?php

/**
 * Class Advanced_Ads_Tracking_Limiter
 * This class handles ad that have a limit on impressions/clicks.
 * Don't display ads if no further impressions/clicks are allowed.
 * Register cron jobs to generate sums every minute; recalculate the hourly pace every hour.
 */
class Advanced_Ads_Tracking_Limiter {
	// meta key for internal records.
	const META_KEY = 'advanced_ads_limiter';

	// cron job name for recalculating hourly pace.
	const PACE_CRON = 'advanced_ads_tracking_limit_recalculate_pace';

	/**
	 * The ad id.
	 *
	 * @var int
	 */
	public $id;

	/**
	 * Is click tracking allowed for the ad type.
	 *
	 * @var bool
	 */
	private $use_clicks;

	/**
	 * Options for the current ad.
	 *
	 * @var array
	 */
	private $options;

	/**
	 * Array that holds current pace and sums.
	 *
	 * @var array
	 */
	private $pace;

	/**
	 * Whether this ad has an expiration time.
	 *
	 * @var bool
	 */
	private $has_expiration;

	/**
	 * Array with sums from impression and clicks tables.
	 *
	 * @var array
	 */
	private $sums;

	/**
	 * Advanced_Ads_Tracking_Limiter constructor.
	 *
	 * @param int $ad_id Current ad id.
	 */
	public function __construct( $ad_id ) {
		$this->id         = (int) $ad_id;
		$this->options    = get_post_meta( $this->id, Advanced_Ads_Ad::$options_meta_field, true );
		$type             = isset( $this->options['type'] ) ? $this->options['type'] : '';
		$this->use_clicks = in_array( $type, Advanced_Ads_Tracking_Plugin::get_clickable_types(), true );

		$this->migrate();

		// Set the pace or recalculate on ad insert update.
		// do this with priority 20, i.e. after the ad (and expiration date) has been saved.
		add_action( 'save_post_' . Advanced_Ads::POST_TYPE_SLUG, [ $this, 'update_ad_limit_on_save' ], 20, 3 );
	}

	/**
	 * Set the pace on a new ad.
	 *
	 * @param int     $post_ID Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated or not.
	 */
	public function update_ad_limit_on_save( $post_ID, $post, $update ) {
		// get the ad start date.
		$start = $this->parse_start_date( $post );

		// refresh options.
		$this->options = get_post_meta( $this->id, Advanced_Ads_Ad::$options_meta_field, true );
		if (
			! in_array( $post->post_status, [ 'publish', 'future' ], true )
			|| ( $this->get_expiration() && ( time() > $this->get_expiration() || $start > $this->get_expiration() ) )
		) {
			self::remove_events_for_ad( $post_ID );

			return;
		}

		if ( ! $this->has_limit() ) {
			self::remove_events_for_ad( $post_ID );
		}

		// if this is an update, check if relevant fields changed.
		if ( $update ) {
			$this->get_pace();
			if (
				$this->get_expiration() === $this->pace['end']
				&& $start === $this->pace['start']
				&& $this->get_impressions_limit() === $this->pace['limit']['impressions']
				&& $this->get_clicks_limit() === $this->pace['limit']['clicks']
			) {
				// nothing relevant has changed.
				return;
			}
		}

		$this->set_pace( $start );

		// if the start is before the next full hour, add to sums array.
		if ( $start < strtotime( 'next hour' ) ) {
			$this->maybe_add_current_hour();
		}

		if ( empty( $this->get_expiration() ) ) {
			self::remove_events_for_ad( $post_ID );
		} else {
			$this->add_events_for_ad( $post_ID );
		}
		$this->update_pace();
	}

	/**
	 * Remove limiter sums if ad stats get reset.
	 * This method gets called statically as an action callback.
	 *
	 * @param string|int $ad_id One of 'deleted-ads', 'all-ads' or and integer ad id.
	 */
	public static function reset_stats( $ad_id ) {
		if ( $ad_id === 'deleted-ads' ) {
			return;
		}

		if ( $ad_id === 'all-ads' ) {
			global $wpdb;
			$rows = $wpdb->get_results(
				$wpdb->prepare( "SELECT post_id, meta_value from {$wpdb->postmeta} where meta_key = %s", self::META_KEY ),
				ARRAY_A
			);
			foreach ( $rows as $row ) {
				$value = maybe_unserialize( $row['meta_value'] );
				if ( ! is_array( $value ) ) {
					delete_post_meta( $row['post_id'], self::META_KEY );
					continue;
				}
				$value['sums'] = [];
				$value['pace'] = [];
				unset( $value['start'] );
				update_post_meta( $row['post_id'], self::META_KEY, $value );
			}

			return;
		}

		if ( (int) $ad_id > 0 ) {
			$meta         = get_post_meta( $ad_id, self::META_KEY, true );
			$meta['sums'] = [];
			update_post_meta( $ad_id, self::META_KEY, $meta );
			( new self( $ad_id ) )->recalculate_pace();
		}
	}

	/**
	 * Get the current pace array, retrieve from db if not set.
	 *
	 * @return array
	 */
	public function get_pace() {
		if ( ! is_null( $this->pace ) ) {
			return $this->pace;
		}
		$pace = get_post_meta( $this->id, self::META_KEY, true );
		if ( empty( $pace ) ) {
			$pace = [];
		}
		if ( ! array_key_exists( 'start', $pace ) ) {
			$this->set_pace( date_create( get_post( $this->id )->post_date_gmt )->getTimestamp() );
			if ( array_key_exists( 'count', $pace ) ) {
				foreach ( $pace['count'] as $hour => $count ) {
					$this->pace['sums'][ $hour ] = $count;
				}
			}

			return $this->pace;
		}

		$this->pace = $pace;

		return $this->pace;
	}

	/**
	 * Get impression limit.
	 *
	 * @return int
	 */
	public function get_impressions_limit() {
		return ! empty( $this->options['tracking']['impression_limit'] ) ? (int) $this->options['tracking']['impression_limit'] : 0;
	}

	/**
	 * Get the click limit.
	 *
	 * @return int
	 */
	public function get_clicks_limit() {
		return ( $this->use_clicks && ! empty( $this->options['tracking']['click_limit'] ) ) ? (int) $this->options['tracking']['click_limit'] : 0;
	}

	/**
	 * Remove recalculating sums and pace cron for this ad.
	 *
	 * @param int $ad_id The ad that expired.
	 */
	public static function remove_events_for_ad( $ad_id ) {
		$next = wp_next_scheduled( self::PACE_CRON, [ $ad_id ] );
		if ( $next ) {
			wp_unschedule_event( $next, self::PACE_CRON, [ $ad_id ] );
		}
	}

	/**
	 * Add cron for recalculating hourly pace for this ad.
	 *
	 * @param int $ad_id The ad id to schedule events for.
	 */
	public function add_events_for_ad( $ad_id ) {
		$start = $this->get_pace()['start'];
		$now   = time();
		if ( $now > $start ) {
			$start = $now;
		}
		$start = $start + ( HOUR_IN_SECONDS - $start % HOUR_IN_SECONDS );
		$next  = wp_next_scheduled( self::PACE_CRON, [ $ad_id ] );
		if ( $next ) {
			wp_unschedule_event( $next, self::PACE_CRON, [ $ad_id ] );
		}
		wp_schedule_event( $start, 'hourly', self::PACE_CRON, [ $ad_id ] );
	}

	/**
	 * Register callback functions for cron actions.
	 */
	public static function register_event_hooks() {
		add_action( self::PACE_CRON, [ self::class, 'recalculate_pace' ] );
	}

	/**
	 * Recalculate the impression and click sums for limited ads.
	 */
	public function recalculate_sums() {
		if ( ! $this->has_limit() ) {
			return;
		}
		$this->get_pace();
		$count = [
			'impressions' => 0,
			'clicks'      => 0,
		];
		$this->maybe_add_current_hour();
		foreach ( $this->pace['sums'] as $hour => $stats ) {
			$timestamp = str_split( (string) $hour, 2 );
			unset( $timestamp[2] );
			$date_time = date_create_from_format( 'ymdH', implode( '', $timestamp ), Advanced_Ads_Tracking_Util::get_wp_timezone() );
			if ( ! $date_time || (string) $hour === $this->get_timestamp() || $date_time->format( 'Ymd' ) !== current_time( 'Ymd' ) ) {
				continue;
			}
			$count['impressions'] += $stats['impressions'];
			if ( $this->use_clicks ) {
				$count['clicks'] += $stats['clicks'];
			}
		}

		global $wpdb;
		$util            = Advanced_Ads_Tracking_Util::get_instance();
		$today_timestamp = $util->get_timestamp( null, true );
		$impressions     = 0;
		if ( ! empty( $this->pace['limit']['impressions'] ) ) {
			$impressions = (int) $wpdb->get_var(
				$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- we can't prepare the impression table.
					"SELECT SQL_NO_CACHE SUM(`count`) FROM {$util->get_impression_table()} WHERE ad_id = %d and `timestamp` = %d;",
					$this->id,
					$today_timestamp
				)
			);
		}

		$clicks = 0;
		if ( $this->use_clicks && ! empty( $this->pace['limit']['clicks'] ) ) {
			$clicks = (int) $wpdb->get_var(
				$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- we can't prepare the click table.
					"SELECT SQL_NO_CACHE SUM(`count`) FROM {$util->get_click_table()} WHERE ad_id = %d and `timestamp` = %d;",
					$this->id,
					$today_timestamp
				)
			);
		}

		$timestamp                                       = $this->get_timestamp();
		$this->pace['sums'][ $timestamp ]['impressions'] = max( $impressions - $count['impressions'], 0 );
		$this->pace['sums'][ $timestamp ]['clicks']      = max( $clicks - $count['clicks'], 0 );

		$this->update_pace();
	}

	/**
	 * Recalculate the hourly pace on hourly cron.
	 * This method gets called statically as cron callback.
	 */
	private function recalculate_pace() {
		$this->recalculate_sums();
		foreach ( [ 'impressions', 'clicks' ] as $metric ) {
			if ($this->get_expiration()) {
				$this->pace['pace'][ $metric ] = $this->calculate_pace( time(), $this->pace['limit'][ $metric ] - $this->get_sums()[ $metric ] );
			} else {
				$get_limit_method = 'get_'.$metric.'_limit';
				$this->pace['pace'][ $metric ] = $this->$get_limit_method();
			}
		}
		$this->update_pace();
	}

	/**
	 * Instantiate a new Limiter object when calling the cron handler methods statically.
	 *
	 * @param string $method The method name.
	 * @param array  $args   The passed arguments.
	 */
	public static function __callStatic( $method, $args ) {
		if ( $method !== 'recalculate_pace' ) {
			return;
		}

		( new self( ...$args ) )->$method();
	}

	/**
	 * Remove all crons on deactivation.
	 */
	public static function deactivate() {
		wp_unschedule_hook( self::PACE_CRON );
		delete_post_meta_by_key( self::META_KEY );
	}

	/**
	 * Get remaining impressions/clicks for the current hour.
	 *
	 * @return int[]
	 */
	public function get_remaining() {
		$this->get_pace();
		if ( empty( $this->pace ) || ! $this->has_limit() ) {
			return [
				'impressions' => 1,
				'clicks'      => 1,
			];
		}

		if ( ! empty( $this->pace['end'] ) ) {
			$this->maybe_add_current_hour();
			$sums = $this->pace['sums'][ $this->get_timestamp() ];
		} else {
			$sums = $this->get_sums();
		}

		return array_filter(
			[
				'impressions' => max( $this->pace['pace']['impressions'] - $sums['impressions'], 0 ),
				'clicks'      => max( $this->pace['pace']['clicks'] - $sums['clicks'], 0 ),
			]
		);
	}

	/**
	 * Check whether this ad has limits set.
	 *
	 * @return bool
	 */
	private function has_limit() {
		return ! empty( $this->get_impressions_limit() ) || ! empty( $this->get_clicks_limit() );
	}

	/**
	 * Get the string for the remaining time for this ad.
	 *
	 * @return string
	 */
	public function get_remaining_time_string() {
		$now               = time();
		$pace              = $this->get_pace();
		$remaining         = $pace['end'] - ( $pace['start'] < $now ? $now : $pace['start'] );
		$remaining_days    = floor( $remaining / DAY_IN_SECONDS );
		$remaining_hours   = floor( $remaining / HOUR_IN_SECONDS );
		$remaining_minutes = floor( ( $remaining - ( $remaining_hours * HOUR_IN_SECONDS ) ) / 60 );
		if ( $remaining_days ) {
			$remaining_hours = $remaining_hours - ( $remaining_days * 24 );
		}

		return trim(
			sprintf(
				'%s %s %s',
				$remaining_days ? sprintf( advads_n( '%s day', '%s days', $remaining_days ), $remaining_days ) : '',
				$remaining_hours ? sprintf( advads_n( '%s hour', '%s hours', $remaining_hours ), $remaining_hours ) : '',
				$remaining_minutes ? sprintf( advads_n( '%s minute', '%s minutes', $remaining_minutes ), $remaining_minutes ) : ''
			)
		);
	}

	/**
	 *  Check if the ad can ad displayed in the front end.
	 *
	 * @return bool
	 */
	public function can_display() {
		if ( $this->has_limit() && empty( $this->pace['pace'] ) ) {
			$this->recalculate_pace();
		}

		// get remaining clicks and impressions.
		$remaining = $this->get_remaining();

		// we haven't yet reached the limit, recalculate current hourly impressions and/or clicks.
		if ( ! empty( $remaining ) ) {
			$this->recalculate_sums();
		}

		// if there are still impressions left, but click budget left (vice versa), return false.
		if ( ! empty( array_diff_key( array_filter( $this->pace['pace'] ), $remaining ) ) ) {
			return false;
		}

		return ! empty( $remaining );
	}

	/**
	 * Get the expiration date for the current ad. 0 if not set.
	 *
	 * @return int
	 */
	private function get_expiration() {
		return ( $this->is_hourly_limit_disabled() || empty( $this->options['expiry_date'] ) )
			? 0
			: (int) $this->options['expiry_date'];
	}

	/**
	 * Check if the hourly limits are disabled.
	 *
	 * @return bool
	 */
	public function is_hourly_limit_disabled() {
		return defined( 'ADVANCED_ADS_TRACKING_NO_HOURLY_LIMIT' ) && ADVANCED_ADS_TRACKING_NO_HOURLY_LIMIT;
	}

	/**
	 * Whether this ad has an expiration date in the future.
	 *
	 * @return bool
	 */
	public function has_expiration() {
		if ( is_null( $this->has_expiration ) ) {
			$this->has_expiration = $this->get_expiration() > time();
		}

		return $this->has_expiration;
	}

	/**
	 * Calculate impressions/clicks per hour.
	 *
	 * @param int $start  Timestamp for ad start.
	 * @param int $budget Remaining until limit.
	 *
	 * @return int
	 */
	private function calculate_pace( $start, $budget ) {
		$now   = time();
		$hours = max( ceil( ( $this->get_expiration() - ( $start < $now ? $now : $start ) ) / 3600 ), 1 );

		return max( (int) ceil( $budget / $hours ), 0 );
	}

	/**
	 * Update the limiter post meta.
	 */
	private function update_pace() {
		// remove all hours from sums, that have neither click nor impression in given hour.
		$this->pace['sums'] = array_filter(
			$this->pace['sums'],
			function( $value ) {
				return is_array( $value ) ? ! empty( array_filter( $value ) ) : ! empty( $value );
			}
		);

		// save pace to database.
		update_post_meta( $this->id, self::META_KEY, $this->pace );
	}

	/**
	 * Add the current hour to sums if not yet present.
	 */
	private function maybe_add_current_hour() {
		if ( ! array_key_exists( $this->get_timestamp(), $this->pace['sums'] ) ) {
			$this->pace['sums'][ $this->get_timestamp() ] = [
				'impressions' => 0,
				'clicks'      => 0,
			];
		}
	}

	/**
	 * Get the timestamp for the current hour.
	 *
	 * @return string
	 */
	private function get_timestamp() {
		static $timestamp;
		if ( is_null( $timestamp ) ) {
			$timestamp = Advanced_Ads_Tracking_Util::get_instance()->get_timestamp();
		}

		return (string) $timestamp;
	}

	/**
	 * Set the pace meta.
	 *
	 * @param int $start Timestamp for ad start.
	 */
	private function set_pace( $start ) {
		// Get the previous sums from the db.
		$previous_sums      = $this->get_sums();
		$impressions_limit  = $this->get_impressions_limit();
		$clicks_limit       = $this->get_clicks_limit();
		$impressions_budget = $impressions_limit - $previous_sums['impressions'];
		$clicks_budget      = $clicks_limit - $previous_sums['clicks'];

		// Add to total if there are already stats for the current hour.
		$sums = isset( $this->pace['sums'] ) ? $this->pace['sums'] : [];
		if ( array_key_exists( $this->get_timestamp(), $sums ) ) {
			$impressions_budget += $sums[ $this->get_timestamp() ]['impressions'];
			$clicks_budget      += $sums[ $this->get_timestamp() ]['clicks'];
		}

		$impressions_budget = max( $impressions_budget, 0 );
		$clicks_budget      = max( $clicks_budget, 0 );

		$this->pace = [
			'start' => $start,
			'end'   => $this->get_expiration(),
			'limit' => [
				'impressions' => $impressions_limit,
				'clicks'      => $clicks_limit,
			],
			'pace'  => [
				'impressions' => $this->get_expiration() ? $this->calculate_pace( $start, $impressions_budget ) : $impressions_limit,
				'clicks'      => $this->get_expiration() ? $this->calculate_pace( $start, $clicks_budget ) : $clicks_limit,
			],
			'sums'  => $sums,
		];
	}

	/**
	 * Check if ad has old limiter schema.
	 */
	private function migrate() {
		if ( get_post_meta( $this->id, self::META_KEY, true ) !== $this->get_pace() ) {
			$this->update_pace();
		}
	}

	/**
	 * Get the sums for the current hour.
	 *
	 * @return array
	 */
	public function get_current_hour() {
		$this->maybe_add_current_hour();

		return $this->pace['sums'][ $this->get_timestamp() ];
	}

	/**
	 * Get sums for this ad from db.
	 *
	 * @return int[] [impressions, clicks]
	 */
	private function get_sums() {
		if ( is_null( $this->sums ) ) {
			$this->sums = Advanced_Ads_Tracking_Util::get_instance()->get_sums_for_ad( $this->id, $this->use_clicks );
		}

		return $this->sums;
	}

	/**
	 * Has the overall click limit been reached?
	 *
	 * @return bool
	 */
	public function is_click_limit_reached() {
		return $this->is_limit_reached( 'clicks' );
	}

	/**
	 * Has the overall impression limit been reached?
	 *
	 * @return bool
	 */
	public function is_impression_limit_reached() {
		return $this->is_limit_reached( 'impressions' );
	}

	/**
	 * Has the limit been reached for dimension?
	 *
	 * @param string $dimension The dimension, either impressions or clicks.
	 *
	 * @return bool
	 */
	private function is_limit_reached( $dimension ) {
		$this->get_pace();
		if ( empty( $this->pace['limit'][ $dimension ] ) ) {
			return false;
		}
		$sum = array_reduce( $this->pace['sums'], function( $sum, $hourly_sum ) use ( $dimension ) {
			$sum += $hourly_sum[ $dimension ];

			return $sum;
		}, 0 );

		return $sum >= $this->pace['limit'][ $dimension ];
	}

	/**
	 * If post_date_gmt is empty, try to get the gmt from the post_date.
	 * If that is still empty, use the current time.
	 *
	 * @param WP_Post $post The saved post object of post_type advancde_ads.
	 *
	 * @return int
	 */
	private function parse_start_date( WP_Post $post ) {
		$date_gmt = $post->post_date_gmt === '0000-00-00 00:00:00'
			? get_gmt_from_date( $post->post_date )
			: $post->post_date_gmt;

		if ( $date_gmt === '0000-00-00 00:00:00' ) {
			$date_gmt = current_time( 'mysql' );
		}

		return date_create( $date_gmt )->getTimestamp();
	}
}

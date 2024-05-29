<?php

namespace Rnoc\App\Controller\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class ScheduleAction extends BaseController {
	/**
	 * Check any pending hooks already exists
	 *
	 * @param $meta_value
	 * @param $hook
	 * @param $meta_key
	 *
	 * @return bool|mixed
	 */
	public static function hasAnyActiveScheduleExists( $hook, $meta_value, $meta_key ) {
		$actions = new \WP_Query( array(
			'post_title'     => $hook,
			'post_status'    => 'pending',
			'post_type'      => 'scheduled-action',
			'meta_query'     => array(
				array(
					'key'     => $meta_key,
					'value'   => $meta_value,
					'compare' => '='
				)
			),
			'posts_per_page' => 1
		) );

		return $actions->have_posts();
	}

	/**
	 * Schedule events
	 *
	 * @param $hook
	 * @param $timestamp
	 * @param array $args
	 * @param string $type
	 * @param null $interval_in_seconds
	 * @param string $group
	 */
	public static function scheduleEvents( $hook, $timestamp, $args = array(), $type = "single", $interval_in_seconds = null, $group = '' ) {
		if ( class_exists( 'ActionScheduler' ) ) {
			switch ( $type ) {
				case "recurring":
					if ( ! self::nextScheduledAction( $hook ) ) {
						\ActionScheduler::factory()->recurring( $hook, $args, $timestamp, $interval_in_seconds, $group );
					}
					break;
				case 'single':
				default:
					$action_id = \ActionScheduler::factory()->single( $hook, $args, $timestamp );
					self::addPostMeta( $action_id, $args );
					break;
			}
		} else {
			switch ( $type ) {
				case "recurring":
					if ( function_exists( 'as_schedule_recurring_action' ) && function_exists( 'as_next_scheduled_action' ) ) {
						if ( ! as_next_scheduled_action( $hook ) ) {
							as_schedule_recurring_action( $timestamp, $interval_in_seconds, $hook, $args, $group );
						}
					}
					break;
				case 'single':
				default:
					if ( function_exists( 'as_schedule_single_action' ) ) {
						$action_id = as_schedule_single_action( $timestamp, $hook, $args );
						self::addPostMeta( $action_id, $args );
					}
					break;
			}
		}
	}

	/**
	 * @param string $hook
	 * @param array $args
	 * @param string $group
	 *
	 * @return int|bool The timestamp for the next occurrence, or false if nothing was found
	 */
	public static function nextScheduledAction( $hook, $args = null, $group = '' ) {
		$params = array();
		if ( is_array( $args ) ) {
			$params['args'] = $args;
		}
		if ( ! empty( $group ) ) {
			$params['group'] = $group;
		}
		if ( defined( 'WC_VERSION' ) && version_compare( WC_VERSION, '4.0', '>=' ) ) {
			$params['status'] = \ActionScheduler_Store::STATUS_RUNNING;
			$job_id           = \ActionScheduler::store()->find_action( $hook, $params );
			if ( ! empty( $job_id ) ) {
				return true;
			}
			$params['status'] = \ActionScheduler_Store::STATUS_PENDING;
			$job_id           = \ActionScheduler::store()->find_action( $hook, $params );
			if ( empty( $job_id ) ) {
				return false;
			}
			$job            = \ActionScheduler::store()->fetch_action( $job_id );
			$scheduled_date = $job->get_schedule()->get_date();
			if ( $scheduled_date ) {
				return (int) $scheduled_date->format( 'U' );
			} elseif ( null === $scheduled_date ) { // pending async action with NullSchedule
				return true;
			}

			return false;
		} else {
			$job_id = \ActionScheduler::store()->find_action( $hook, $params );
			if ( empty( $job_id ) ) {
				return false;
			}
			$job  = \ActionScheduler::store()->fetch_action( $job_id );
			$next = $job->get_schedule()->next();
			if ( $next ) {
				return (int) ( $next->format( 'U' ) );
			}

			return false;
		}
	}

	/**
	 * Add post meta
	 *
	 * @param $post_id
	 * @param $args
	 *
	 * @return false|int
	 */
	public static function addPostMeta( $post_id, $args ) {
		if ( ! empty( $args ) ) {
			foreach ( $args as $meta_key => $meta_value ) {
				add_post_meta( $post_id, $meta_key, $meta_value );
			}

			return true;
		}

		return false;
	}


}
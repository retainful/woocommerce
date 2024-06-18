<?php

namespace RNOC\App\Helpers;

defined( 'ABSPATH' ) || exit;

class WP {

	/**
	 * Has admin privilege.
	 *
	 * @return bool
	 */
	public static function hasAdminPrivilege() {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Check is customer page.
	 *
	 * @return bool
	 */
	public static function isCustomerPage() {
		if ( is_ajax() ) {
			return true;
		}

		return ! is_admin();
	}

	/**
	 * Get login user.
	 *
	 * @return false|\WP_User|null
	 */
	public static function getLoginUser() {
		return is_user_logged_in() ? wp_get_current_user() : false;
	}

	/**
	 * Get login user email.
	 *
	 * @return string
	 */
	public static function getLoginUserEmail() {
		$user = self::getLoginUser();

		return is_object( $user ) && ! empty( $user->user_email ) ? $user->user_email : '';
	}

	/**
	 * Format date field.
	 *
	 * @param   int  $timestamp  Time stamp.
	 *
	 * @return string|null
	 */
	public static function formatToIso8601( $timestamp ) {
		if ( empty( $timestamp ) ) {
			$timestamp = current_time( 'timestamp', true );
		}
		if ( $timestamp instanceof \WC_DateTime ) {
			$timestamp = $timestamp->getTimestamp();
		}

		try {
			$date      = date( 'Y-m-d H:i:s', $timestamp );
			$date_time = new \DateTime( $date );

			return $date_time->format( \DateTime::ATOM );
		}
		catch ( \Exception $e ) {

		}

		return null;
	}

	/**
	 * Get user role by email.
	 *
	 * @param   string  $email  User email.
	 *
	 * @return array
	 */
	public static function getUserRoles( $email ) {
		if ( empty( $email ) ) {
			return [];
		}

		try {
			$user = get_user_by( 'email', sanitize_email( $email ) );
			if ( is_object( $user ) && isset( $user->roles ) ) {
				return (array) $user->roles;
			}
		}
		catch ( \Exception $e ) {

		}

		return [];
	}

	/**
	 * Create nonce for woocommerce.
	 *
	 * @param   string  $action
	 *
	 *
	 * @return false|string
	 */
	public static function createNonce( $action = '' ) {
		if ( empty( $action ) ) {
			return false;
		}

		return wp_create_nonce( $action );
	}


	/**
	 * Check the validity of a security nonce and the admin privilege.
	 *
	 * @param   string  $nonce_name  The name of the nonce.
	 *
	 * @return bool
	 */
	public static function isSecurityValid( $nonce_name = '' ) {
		$nonce = Input::get( 'rnoc_nonce', '' );
		if ( ! self::hasAdminPrivilege() || ! self::verifyNonce( $nonce, $nonce_name ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Verify nonce.
	 *
	 * @param   string  $nonce   Nonce.
	 *
	 * @param   string  $action  Action.
	 *
	 * @return bool
	 */
	public static function verifyNonce( $nonce, $action ) {
		if ( empty( $nonce ) || empty( $action ) ) {
			return false;
		}

		return wp_verify_nonce( $nonce, $action );
	}

	/**
	 * Get the default language.
	 *
	 * @return string|null
	 */
	public static function getDefaultLanguage() {
		$current_lang = null;
		$wpml_options = get_option( 'icl_sitepress_settings' );
		if ( ! empty( $wpml_options ) ) {
			return ( isset( $wpml_options['default_language'] ) ) ? $wpml_options['default_language'] : null;
		}
		if ( function_exists( 'pll_default_language' ) ) {
			return pll_default_language();
		}
		if ( function_exists( 'get_locale' ) ) {
			$current_lang = get_locale();
			if ( empty( $current_lang ) ) {
				$current_lang = 'en';
			}
		}

		return $current_lang;
	}

	/**
	 * Get the current language.
	 *
	 * @return string|null
	 */
	public static function getCurrentLanguage() {
		if ( defined( 'ICL_LANGUAGE_CODE' ) ) {
			return ICL_LANGUAGE_CODE;
		}
		if ( function_exists( 'pll_current_language' ) ) {
			return pll_current_language();
		}

		return self::getDefaultLanguage();
	}


	/**
	 * Set the auth cookie.
	 *
	 * @param   int  $user_id  User id.
	 *
	 */
	public static function setAuthCookie( $user_id ) {
		function_exists( 'wp_set_auth_cookie' ) && wp_set_auth_cookie( $user_id );
	}


	/**
	 * Update the user meta data.
	 *
	 * @param   int              $user_id     User id.
	 * @param   string           $meta_key    User meta key.
	 * @param   string|int|bool  $meta_value  User meta value.
	 *
	 * @return void
	 */
	public static function updateUserMeta( $user_id, $meta_key, $meta_value ) {
		if ( function_exists( 'update_user_meta' ) ) {
			update_user_meta( $user_id, $meta_key, $meta_value );
		}
	}

	/**
	 * Get current user id.
	 *
	 * @return int
	 */
	public static function getCurrentUserId() {
		return function_exists( 'get_current_user_id' ) ? (int) get_current_user_id() : 0;
	}


	/**
	 * Set current user.
	 *
	 * @param   int  $user_id  User id
	 *
	 * @return void
	 */
	public static function setCurrentUser( $user_id ) {
		function_exists( 'set_current_user' ) && set_current_user( $user_id );
	}


	/**
	 * Add post meta.
	 *
	 * @param   int    $post_id  Post id.
	 * @param   array  $args     Argument.
	 *
	 * @return bool
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

	/**
	 * Check any pending hooks already exists.
	 *
	 * @param   mixed   $meta_value  meta value.
	 * @param   string  $hook        hook name.
	 * @param   string  $meta_key    meta key.
	 *
	 * @return bool
	 */
	public static function hasAnyActiveScheduleExists( $hook, $meta_value, $meta_key ) {
		$actions = new \WP_Query( [
			'post_title'     => $hook,
			'post_status'    => 'pending',
			'post_type'      => 'scheduled-action',
			'meta_query'     => [
				[
					'key'     => $meta_key,
					'value'   => $meta_value,
					'compare' => '='
				]
			],
			'posts_per_page' => 1
		] );

		return $actions->have_posts();
	}


	/**
	 * Schedule events.
	 *
	 * @param   string      $hook                 Hook name.
	 * @param   int|string  $timestamp            Time.
	 * @param   array       $args                 Arguments.
	 * @param   string      $type                 Type.
	 * @param   null        $interval_in_seconds  Interval seconds.
	 * @param   string      $group                Group.
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
	 * @param   string  $hook
	 * @param   array   $args
	 * @param   string  $group
	 *
	 * @return int|bool The timestamp for the next occurrence, or false if nothing was found
	 */
	public static function nextScheduledAction( $hook, $args = null, $group = '' ) {
		if ( empty( $hook ) && ! class_exists( 'ActionScheduler' ) ) {
			return false;
		}
		$params = [];
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


}
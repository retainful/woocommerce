<?php

namespace RNOC\App\Modules\Integrations;
class Currency {
	/**
	 * Currency filters
	 */
	function __construct() {
		add_filter( 'rnoc_get_current_currency_code', [ self::class, 'getCurrentCurrencyCode' ] );
		add_filter( 'rnoc_get_default_currency_code', [ self::class, 'getDefaultCurrencyCode' ] );
		add_filter( 'rnoc_get_currency_rate', [ self::class, 'getCurrencyRate' ], 10, 2 );
		add_filter( 'rnoc_set_current_currency_code', [ self::class, 'setCurrentCurrency' ] );
		add_filter( 'rnoc_get_available_currencies', [ self::class, 'getAllCurrenciesList' ] );
	}

	/**
	 * Get the current currency code.
	 *
	 * @param string $default_currency_code Currency code.
	 *
	 * @return mixed
	 */
	public static function getCurrentCurrencyCode( $default_currency_code ) {

		if ( class_exists( 'WOOMULTI_CURRENCY_F_Data' ) ) {
			$setting               = new \WOOMULTI_CURRENCY_F_Data();
			$default_currency_code = $setting->get_current_currency();
		} elseif ( class_exists( 'WOOMULTI_CURRENCY_Data' ) ) {
			$setting               = new \WOOMULTI_CURRENCY_Data();
			$default_currency_code = $setting->get_current_currency();
		}

		return $default_currency_code;
	}

	/**
	 * Get default currency code.
	 *
	 * @param string $default_currency_code Currency code.
	 *
	 * @return mixed
	 */
	public static function getDefaultCurrencyCode( $default_currency_code ) {
		if ( class_exists( 'WOOMULTI_CURRENCY_F_Data' ) ) {
			$setting               = new \WOOMULTI_CURRENCY_F_Data();
			$default_currency_code = $setting->get_default_currency();
		} elseif ( class_exists( 'WOOMULTI_CURRENCY_Data' ) ) {
			$setting               = new \WOOMULTI_CURRENCY_Data();
			$default_currency_code = $setting->get_default_currency();
		}

		return $default_currency_code;
	}

	/**
	 * Set the current currency code.
	 *
	 * @param string $currency_code Currency code.
	 *
	 * @return void
	 */
	public static function setCurrentCurrency( $currency_code ) {
		if ( class_exists( 'WOOMULTI_CURRENCY_F_Data' ) ) {
			$setting = new \WOOMULTI_CURRENCY_F_Data();
			$setting->set_current_currency( $currency_code );
		} elseif ( class_exists( 'WOOMULTI_CURRENCY_Data' ) ) {
			$setting = new \WOOMULTI_CURRENCY_Data();
			$setting->set_current_currency( $currency_code );
		}
	}

	/**
	 * Get current currency rate.
	 *
	 * @param float $value Total.
	 * @param string $currency_code Currency code.
	 *
	 * @return mixed|null
	 */
	public static function getCurrencyRate( $value, $currency_code ) {

		if ( class_exists( 'WOOMULTI_CURRENCY_F_Data' ) ) {
			$setting             = new \WOOMULTI_CURRENCY_F_Data();
			$selected_currencies = $setting->get_list_currencies();
			$value               = isset( $selected_currencies[ $currency_code ]['rate'] ) ? $selected_currencies[ $currency_code ]['rate'] : null;
		} elseif ( class_exists( 'WOOMULTI_CURRENCY_Data' ) ) {
			$setting             = new \WOOMULTI_CURRENCY_Data();
			$selected_currencies = $setting->get_list_currencies();
			$value               = isset( $selected_currencies[ $currency_code ]['rate'] ) ? $selected_currencies[ $currency_code ]['rate'] : null;
		}

		return $value;
	}

	/**
	 * Get all currencies list.
	 *
	 * @param array $currencies Currencies.
	 *
	 * @return array
	 */
	public static function getAllCurrenciesList( $currencies ) {
		if ( class_exists( 'WOOMULTI_CURRENCY_F_Data' ) ) {
			$setting             = new \WOOMULTI_CURRENCY_F_Data();
			$selected_currencies = $setting->get_list_currencies();
		} elseif ( class_exists( 'WOOMULTI_CURRENCY_Data' ) ) {
			$setting             = new \WOOMULTI_CURRENCY_Data();
			$selected_currencies = $setting->get_list_currencies();
		}
		if ( ! empty( $selected_currencies ) ) {
			foreach ( $selected_currencies as $code => $value ) {
				if ( ! empty( $code ) ) {
					$currencies[] = $code;
				}
			}
		}

		return array_unique( array_filter( $currencies ) );
	}
}

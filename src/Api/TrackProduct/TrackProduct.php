<?php


namespace Rnoc\Retainful\Api\TrackProduct;

use Rnoc\Retainful\library\RetainfulApi;

class  TrackProduct {

	public static function RequestUrl(){
		return apply_filters( 'retainful_track_product_api_url', 'https://api-beta.retainful.com/v1/woocommerce/');
	}

	/**
	 * Init
	 * @return void
	 */
	public static function trackViewedProduct() {
		// At the very top of your script
		if (session_status() === PHP_SESSION_NONE) {
			session_start();
		}
		if ( ! is_product()  ) {
			return;
		}
//		if(!is_user_logged_in() ) {
//
//		}

		$product_id = get_the_ID(); // Safer than using $post->ID
		if ( ! $product_id || ! is_numeric( $product_id ) ) {
			return;
		}
		$get_product_data = self::getProductDate($product_id);
		$decode_data = [];

		if( !empty($get_product_data) ) {

			$decode_data = json_decode(base64_decode(self::getValueSessionValue('viewed_product_data')) ,true);

			if( !empty($decode_data) && array_key_exists($product_id,$decode_data) ) {
				$decode_data[$product_id]['viewed_count'] = (int)$decode_data[$product_id]['viewed_count'] + 1  ;
		    }else{
			    $decode_data[$product_id] = $get_product_data;
		    }
		}
		$encode_data = base64_encode(json_encode($decode_data));
		self::setValueWithTimeout('viewed_product_data',$encode_data,86400); // 24 hours timeout
		self::sendApiRequest($decode_data);

	}

	public static function setValueWithTimeout($key, $value, $timeoutSeconds = 3600) {
		if(empty($key) && empty($value)) {
			return;
		}
		$_SESSION[$key] = [
			'data' => $value,
			'expires_at' => time() + $timeoutSeconds
		];
	}

	public static  function getValueSessionValue($key) {
		if (isset($_SESSION[$key])) {
			$item = $_SESSION[$key];
			if (isset($item['expires_at']) && $item['expires_at'] < time()) {
				unset($_SESSION[$key]);
				return null;
			}
			return $item['data'];
		}
		return null;
	}

	public static function getProductDate($product_id) {
		$product = wc_get_product( $product_id );
		if ( ! $product ) {
			return [];
		}

		$product_data = [
			'id' => $product->get_id(),
			'name' => $product->get_name(),
			'price' => $product->get_price(),
			'permalink' => $product->get_permalink(),
			'image' => wp_get_attachment_url( $product->get_image_id() ),
			'sku' => $product->get_sku(),
			'type' => $product->get_type(),
			'viewed_count' => 1,
			'stock_status' => $product->get_stock_status(),
			'categories' => wp_get_post_terms( $product_id, 'product_cat', ['fields' => 'names'] ),
			'tags' => wp_get_post_terms( $product_id, 'product_tag', ['fields' => 'names'] ),
		];

		return $product_data;
	}

	public static function sendApiRequest($data) {
		if (empty($data)) {
			return;
		}
		$url = self::RequestUrl();
		$api = new RetainfulApi();
		$api->request($url , [], 'post', json_encode($data), ['Content-Type' => 'application/json']);

	}
}
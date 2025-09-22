<?php


namespace Rnoc\Retainful\Api\TrackProduct;

use Rnoc\Retainful\Admin\Settings;
use Rnoc\Retainful\Api\AbandonedCart\Storage\Cookie;
use Rnoc\Retainful\library\RetainfulApi;
use Rnoc\Retainful\WcFunctions;
class  TrackProduct {

	public static function RequestUrl(){
		return apply_filters( 'retainful_track_product_api_url', 'https://webhooks.retainful.net/v3/event/woocommerce/product/viewed');
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
		$email = function_exists('wp_get_current_user') ? wp_get_current_user()->user_email: '';
		if(!is_user_logged_in() ) {
			if(empty($email) ){
				$cookie = new Cookie();
				$cookie_data = json_decode(base64_decode($cookie->getValue('_wc_rnoc_tk_session')));
				if( !empty($cookie_data) && is_object($cookie_data) && isset($cookie_data->email) && !empty($cookie_data->email) ) {
					$email = $cookie_data->email;
				}
			}
		}

		if(empty($email) || !is_email($email)) {
			return;
		}

		$product_id = get_the_ID(); // Safer than using $post->ID
		if ( ! $product_id || ! is_numeric( $product_id ) ) {
			return;
		}
		$session_id = self::getValueSessionValue('rnoc_session_id_track_product') ?? self::generateSessionID();
		$get_product_data = self::getProductDate($product_id);
		$decode_data = [];
		if( !empty($get_product_data) ) {
			$decode_data = json_decode(base64_decode(self::getValueSessionValue($session_id)) ,true);
			$need_to_update = true;
			if(!empty($decode_data)) {
				foreach ( $decode_data['products'] as &$product_data ) {
					if ( is_array( $product_data ) && ! empty( $product_data['variantId'] ) && $product_data['variantId'] == $product_id ) {
						$product_data['viewed_count'] = (int) $product_data['viewed_count'] + 1;
						$need_to_update = false;
						break;
					}
				}
			}
			if($need_to_update){
				$decode_data['products'][]= $get_product_data;
			}
		}
		$request_data = self::apiRequestData($session_id, $email, $decode_data);
		$encode_data = base64_encode(json_encode($request_data));

		self::setValueWithTimeout($session_id,$encode_data,86400); // 86400 24 hours timeout
		self::sendApiRequest($encode_data);

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
		$WC = new WcFunctions();
		$product = $WC->getProduct( $product_id );
		if ( ! $product ) {
			return [];
		}
		$product_data = [
			"variantId" =>  $WC->getItemId($product),
			"variantTitle" => $WC->getItemTitle($product),
			"variantUntranslatedTitle" => $WC->getItemName($product),
			"sku" => $WC->getItemSku($product),
			"priceAmount" =>  $WC->getItemPrice($product),
			"currencyCode" => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '',
			"productId" => $WC->getItemId($product),
			"productVendor" => '',
			"productTitle" => $WC->getItemTitle($product),
			"productUrl" => method_exists($product,'get_permalink') ? $product->get_permalink() : '',
			"productType" =>   method_exists($product,'get_type') ? $product->get_type() : '',
			'viewed_count' => 1,
			'imageUrl' => $WC->getProductImageSrc($product),
			"viewedAt" => current_time('Y-m-d H:i:s'),
		];

		return  $product_data;
	}

	public static function sendApiRequest($data) {
		if (empty($data)) {
			return;
		}
		$body =json_encode(['data' => $data]);
		$url = self::RequestUrl();
		$api = new RetainfulApi();
		$response = $api->request($url , [], 'post', $body, ['Content-Type' => 'application/json']);
	}

	public static function generateSessionID() {
		$session_id =  sprintf(
			'%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
			mt_rand(0, 0xffff), mt_rand(0, 0xffff),
			mt_rand(0, 0xffff),
			mt_rand(0, 0x0fff) | 0x4000,
			mt_rand(0, 0x3fff) | 0x8000,
			mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
		);
		self::setValueWithTimeout('rnoc_session_id_track_product', $session_id, 86400); // 24 hours timeout
		return $session_id;
	}


	public static function apiRequestData($session_id, $email, $data) {
		if(empty($email) || !is_email($email) || empty($session_id) || !is_string($session_id)) {
			return [];
		}
		$user = get_user_by('email', $email);
		return [
			'eventHash' =>  bin2hex(random_bytes(32)),
			'sessionUUID' => $session_id,
			'customer' => [
				'id' => $user && !empty($user->ID) ? $user->ID : 0,
				'email' => $email,
				'firstName' => $user &&  !empty($user->first_name) ? $user->first_name : '',
				'lastName' => $user &&  !empty($user->last_name) ? $user->last_name : '',
				 "phone" => $user && !empty($user->phone ) ? $user->phone : '',
                 "ordersCount" => $user && function_exists('wc_get_customer_order_count') ? wc_get_customer_order_count($user->ID) : 0,
			],
			'products' => $data['products'] ?? [],
			'shopId' => (new Settings())->getApiKey()
		];
	}
}
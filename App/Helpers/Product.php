<?php

namespace RNOC\App\Helpers;

defined( 'ABSPATH' ) || exit;

class Product {

	/**
	 * Get product category ids.
	 *
	 * @param int $product_id Product id.
	 *
	 * @return array
	 */
	public static function getProductCategoryIds( $product_id ) {
		if ( empty( $product_id ) ) {
			return [];
		}

		if ( function_exists( 'wc_get_product_term_ids' ) ) {
			return wc_get_product_term_ids( $product_id, 'product_cat' );
		}

		return [];
	}

	/**
	 * Get product name.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return string|null
	 */
	public static function getItemName( $product ) {
		if ( Util::isMethodExists( $product, 'get_name' ) ) {
			return apply_filters( 'rnoc_get_item_name', $product->get_name(), $product );
		}

		return null;
	}

	/**
	 * Get product image id.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return int
	 */
	public static function getProductImageId( $product ) {
		if ( Util::isMethodExists( $product, 'get_image_id' ) ) {
			return $product->get_image_id();
		}

		return 0;
	}

	/**
	 * Get product image source.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return string
	 */
	public static function getProductImageSrc( $product ) {
		$image_id = self::getProductImageId( $product );
		if ( $image_id < 0 ) {
			return '';
		}
		$src = wp_get_attachment_image_src( $image_id, 'woocommerce_thumbnail' );

		$src = ! empty( $src ) ? $src : wc_placeholder_img_src();

		return apply_filters( 'rnoc_get_product_image_src', $src, $product );
	}

	/**
	 * Get product sku.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return string|null
	 */
	public static function getItemSku( $product ) {
		if ( Util::isMethodExists( $product, 'get_sku' ) ) {
			return $product->get_sku();
		}

		return null;
	}

	/**
	 * Get product category name.
	 *
	 * @param int $product_id Product id.
	 *
	 * @return array
	 */
	public static function getProductCategoryName( $product_id ) {
		if ( empty( $product_id ) ) {
			return [];
		}
		$terms = get_the_terms( $product_id, 'product_cat' );

		return ( empty( $terms ) || is_wp_error( $terms ) ) ? [] : wp_list_pluck( $terms, 'name' );
	}

	/**
	 * Get product.
	 *
	 * @param int|\WC_Product $product Product id or object.
	 *
	 * @return false|\WC_Product
	 */
	public static function getProduct( $product ) {
		return function_exists( 'wc_get_product' ) ? wc_get_product( $product ) : false;
	}

	/**
	 * Get product url.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return string
	 */
	public static function getProductUrl( $product ) {
		if ( Util::isMethodExists( $product, 'get_permalink' ) ) {
			return $product->get_permalink();
		}

		return '';
	}

	/**
	 * Get price including tax.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return float
	 */
	public static function getPriceIncludingTax( $product ) {
		$price = 0.0;
		if ( is_object( $product ) && function_exists( 'wc_get_price_including_tax' ) ) {
			$price = wc_get_price_including_tax( $product );
		}

		return $price;
	}

	/**
	 * Get price excluding tax.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return float
	 */
	public static function getPriceExcludingTax( $product ) {
		$price = 0.0;
		if ( is_object( $product ) && function_exists( 'wc_get_price_excluding_tax' ) ) {
			$price = wc_get_price_excluding_tax( $product );
		}

		return $price;
	}

	/**
	 * Get cart item price.
	 *
	 * @param \WC_Product $product Product object.
	 *
	 * @return float
	 */
	public static function getItemPrice( $product ) {
		if ( WC::isPriceExcludingTax() ) {
			$price = Product::getPriceExcludingTax( $product );
		} else {
			$price = Product::getPriceIncludingTax( $product );
		}

		return $price;
	}

	/**
	 * Get Item title from Item object.
	 *
	 * @param object $item item.
	 *
	 * @return null
	 */
	public static function getItemTitle( $item ) {
		if ( Util::isMethodExists( $item, 'get_title' ) ) {
			return $item->get_title();
		}

		return null;
	}

	/**
	 * Get Item Id from Item object.
	 *
	 * @param object $item item.
	 *
	 * @return null
	 */
	public static function getItemId( $item ) {
		if ( Util::isMethodExists( $item, 'get_id' ) ) {
			return $item->get_id();
		}

		return null;
	}
}
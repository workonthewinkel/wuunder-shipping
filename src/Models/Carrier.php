<?php

namespace Wuunder\Shipping\Models;

use Wuunder\Shipping\Contracts\Model;

/**
 * Carrier Model.
 */
class Carrier extends Model {

	/**
	 * Table name.
	 *
	 * @var string
	 */
	protected static string $table = 'wuunder_carriers';

	/**
	 * Carrier code.
	 *
	 * @var string
	 */
	public string $carrier_code = '';

	/**
	 * Carrier product code.
	 *
	 * @var string
	 */
	public string $carrier_product_code = '';

	/**
	 * Service code.
	 *
	 * @var string
	 */
	public string $service_code = '';

	/**
	 * Carrier name.
	 *
	 * @var string
	 */
	public string $carrier_name = '';

	/**
	 * Product name.
	 *
	 * @var string
	 */
	public string $product_name = '';

	/**
	 * Service level.
	 *
	 * @var string
	 */
	public string $service_level = '';

	/**
	 * Carrier product description.
	 *
	 * @var string
	 */
	public string $carrier_product_description = '';

	/**
	 * Price.
	 *
	 * @var float
	 */
	public float $price = 0.0;

	/**
	 * Currency.
	 *
	 * @var string
	 */
	public string $currency = 'EUR';

	/**
	 * Carrier image URL.
	 *
	 * @var string
	 */
	public string $carrier_image_url = '';

	/**
	 * Pickup date.
	 *
	 * @var string
	 */
	public string $pickup_date = '';

	/**
	 * Pickup before time.
	 *
	 * @var string
	 */
	public string $pickup_before = '';

	/**
	 * Pickup after time.
	 *
	 * @var string
	 */
	public string $pickup_after = '';

	/**
	 * Pickup address type.
	 *
	 * @var string
	 */
	public string $pickup_address_type = '';

	/**
	 * Delivery date.
	 *
	 * @var string
	 */
	public string $delivery_date = '';

	/**
	 * Delivery before time.
	 *
	 * @var string
	 */
	public string $delivery_before = '';

	/**
	 * Delivery after time.
	 *
	 * @var string
	 */
	public string $delivery_after = '';

	/**
	 * Delivery address type.
	 *
	 * @var string
	 */
	public string $delivery_address_type = '';

	/**
	 * Modality.
	 *
	 * @var string
	 */
	public string $modality = '';

	/**
	 * Is return shipment.
	 *
	 * @var bool
	 */
	public bool $is_return = false;

	/**
	 * Is parcelshop drop off.
	 *
	 * @var bool
	 */
	public bool $is_parcelshop_drop_off = false;

	/**
	 * Accepts parcelshop delivery.
	 *
	 * @var bool
	 */
	public bool $accepts_parcelshop_delivery = false;

	/**
	 * Includes ad hoc pickup.
	 *
	 * @var bool
	 */
	public bool $includes_ad_hoc_pickup = false;

	/**
	 * Additional info.
	 *
	 * @var string
	 */
	public string $info = '';

	/**
	 * Tags (JSON string).
	 *
	 * @var string
	 */
	public string $tags = '';

	/**
	 * Surcharges (JSON string).
	 *
	 * @var string
	 */
	public string $surcharges = '';

	/**
	 * Carrier product settings (JSON string).
	 *
	 * @var string
	 */
	public string $carrier_product_settings = '';

	/**
	 * Is enabled.
	 *
	 * @var bool
	 */
	public bool $enabled = false;

	/**
	 * Get table name with prefix.
	 *
	 * @return string
	 */
	public static function get_table_name(): string {
		global $wpdb;
		return $wpdb->prefix . self::$table;
	}

	/**
	 * Find carrier by method ID.
	 *
	 * @param string $method_id Method ID.
	 * @return self|null
	 */
	public static function find_by_method_id( $method_id ): ?self { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		global $wpdb;
		$table = self::get_table_name();

		$parts = explode( ':', $method_id );
		if ( count( $parts ) !== 2 ) {
			return null;
		}

		$row = $wpdb->get_row( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT * FROM %i WHERE carrier_code = %s AND carrier_product_code = %s', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$table,
				$parts[0],
				$parts[1]
			),
			ARRAY_A
		);

		if ( ! $row ) {
			return null;
		}

		$carrier = new self();
		foreach ( $row as $key => $value ) {
			if ( property_exists( $carrier, $key ) ) {
				$carrier->$key = $value;
			}
		}

		return $carrier;
	}

	/**
	 * Get all carriers.
	 *
	 * @param bool $enabled_only Only get enabled carriers.
	 * @return array
	 */
	public static function get_all( $enabled_only = false ): array { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		global $wpdb;
		$table = self::get_table_name();

		if ( $enabled_only ) {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					'SELECT * FROM %i WHERE enabled = 1 ORDER BY carrier_name, product_name', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					$table
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
				$wpdb->prepare(
					'SELECT * FROM %i ORDER BY carrier_name, product_name', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
					$table
				),
				ARRAY_A
			);
		}

		$carriers = [];
		foreach ( $rows as $row ) {
			$carrier = new self();
			foreach ( $row as $key => $value ) {
				if ( property_exists( $carrier, $key ) ) {
					$carrier->$key = $value;
				}
			}
			$carriers[] = $carrier;
		}

		return $carriers;
	}

	/**
	 * Get carriers that accept parcelshop delivery.
	 *
	 * @return array
	 */
	public static function get_parcelshop_carriers(): array { // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		global $wpdb;
		$table = self::get_table_name();

		$rows = $wpdb->get_results( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT * FROM %i WHERE accepts_parcelshop_delivery = 1 ORDER BY carrier_name, product_name', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$table
			),
			ARRAY_A
		);

		$carriers = [];
		foreach ( $rows as $row ) {
			$carrier = new self();
			foreach ( $row as $key => $value ) {
				if ( property_exists( $carrier, $key ) ) {
					$carrier->$key = $value;
				}
			}

			$carriers[] = $carrier;
		}

		return $carriers;
	}

	/**
	 * Save carrier to database.
	 *
	 * @return bool
	 */
	public function save(): bool {
		global $wpdb;
		$table = self::get_table_name();

		$data = [
			'carrier_code'                 => $this->carrier_code,
			'carrier_product_code'         => $this->carrier_product_code,
			'service_code'                 => $this->service_code,
			'carrier_name'                 => $this->carrier_name,
			'product_name'                 => $this->product_name,
			'service_level'                => $this->service_level,
			'carrier_product_description'  => $this->carrier_product_description,
			'price'                        => $this->price,
			'currency'                     => $this->currency,
			'carrier_image_url'            => $this->carrier_image_url,
			'pickup_date'                  => $this->pickup_date,
			'pickup_before'                => $this->pickup_before,
			'pickup_after'                 => $this->pickup_after,
			'pickup_address_type'          => $this->pickup_address_type,
			'delivery_date'                => $this->delivery_date,
			'delivery_before'              => $this->delivery_before,
			'delivery_after'               => $this->delivery_after,
			'delivery_address_type'        => $this->delivery_address_type,
			'modality'                     => $this->modality,
			'is_return'                    => $this->is_return ? 1 : 0,
			'is_parcelshop_drop_off'       => $this->is_parcelshop_drop_off ? 1 : 0,
			'accepts_parcelshop_delivery'  => $this->accepts_parcelshop_delivery ? 1 : 0,
			'includes_ad_hoc_pickup'       => $this->includes_ad_hoc_pickup ? 1 : 0,
			'info'                         => $this->info,
			'tags'                         => $this->tags,
			'surcharges'                   => $this->surcharges,
			'carrier_product_settings'     => $this->carrier_product_settings,
			'enabled'                      => $this->enabled ? 1 : 0,
		];

		// Check if exists
		$exists = $wpdb->get_var( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery,WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->prepare(
				'SELECT COUNT(*) FROM %i WHERE carrier_code = %s AND carrier_product_code = %s', // phpcs:ignore WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare
				$table,
				$this->carrier_code,
				$this->carrier_product_code
			)
		);

		if ( $exists ) {
			return $wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
				$table,
				$data,
				[
					'carrier_code'         => $this->carrier_code,
					'carrier_product_code' => $this->carrier_product_code,
				]
			) !== false;
		} else {
			return $wpdb->insert( $table, $data ) !== false; // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}
	}

	/**
	 * Delete carrier from database.
	 *
	 * @return bool
	 */
	public function delete(): bool {
		global $wpdb;
		$table = self::get_table_name();

		return $wpdb->delete( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$table,
			[
				'carrier_code'         => $this->carrier_code,
				'carrier_product_code' => $this->carrier_product_code,
			]
		) !== false;
	}

	/**
	 * Get method ID.
	 *
	 * @return string
	 */
	public function get_method_id(): string {
		return $this->carrier_code . ':' . $this->carrier_product_code;
	}
}

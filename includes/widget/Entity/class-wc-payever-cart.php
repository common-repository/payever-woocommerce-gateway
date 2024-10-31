<?php
/**
 * The cart object.
 */

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Widget_Cart' ) ) {
	return;
}

/**
 * Class WC_Payever_Widget_Cart
 */
class WC_Payever_Widget_Cart {

	/**
	 * The name.
	 *
	 * @var string
	 */
	protected $name;

	/**
	 * The description.
	 *
	 * @var string
	 */
	protected $description;

	/**
	 * The identifier.
	 *
	 * @var string
	 */
	protected $identifier;

	/**
	 * The amount.
	 *
	 * @var float
	 */
	protected $amount;

	/**
	 * The price.
	 *
	 * @var float
	 */
	protected $price;

	/**
	 * The quantity.
	 *
	 * @var integer
	 */
	protected $quantity;

	/**
	 * The thumbnail.
	 *
	 * @var string
	 */
	protected $thumbnail;

	/**
	 * The unit.
	 *
	 * @var string
	 */
	protected $unit;

	/**
	 * @param string $name
	 * @param string $description
	 * @param string $identifier
	 * @param float $amount
	 * @param float $price
	 * @param int $quantity
	 * @param string $unit
	 * @param string|null $thumbnail
	 */
	public function __construct(
		string $name,
		string $description,
		string $identifier,
		float $price,
		float $amount,
		int $quantity,
		string $thumbnail = null,
		string $unit = 'EACH'
	) {
		$this->name        = wp_strip_all_tags( $name );
		$this->description = wp_strip_all_tags( $description );
		$this->identifier  = $identifier;
		$this->amount      = $amount;
		$this->price       = $price;
		$this->quantity    = $quantity;
		$this->unit        = $unit;
		$this->thumbnail   = $thumbnail;
	}

	/**
	 * Converts the object of this class to array
	 *
	 * @return array
	 */
	public function toArray() {
		$result = array();

		$object = get_object_vars( $this );

		foreach ( $object as $property => $value ) {
			if ( ! is_null( $value ) && false !== $value ) {
				$result[ $property ] = $value;
			}
		}

		return $result;
	}
}

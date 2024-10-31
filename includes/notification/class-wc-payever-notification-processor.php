<?php

defined( 'ABSPATH' ) || exit;

if ( class_exists( 'WC_Payever_Notification_Processor' ) ) {
	return;
}

use Payever\Sdk\Payments\Http\RequestEntity\NotificationRequestEntity;
use Payever\Sdk\Payments\Notification\NotificationRequestProcessor;

class WC_Payever_Notification_Processor extends NotificationRequestProcessor {
	use WC_Payever_Helper_Trait;
	use WC_Payever_Api_Wrapper_Trait;
	use WC_Payever_Api_Payment_Service_Trait;

	const NOTIFICATION_TYPE = 'raw_request';
	const HEADER_SIGNATURE = 'X-PAYEVER-SIGNATURE';

	/**
	 * @var bool
	 */
	private $is_valid_signature = false;

	/**
	 * @inheritdoc
	 */
	public function getRequestPayload() {
		$headers    = array_change_key_case(
			$this->get_helper()->get_request_headers(),
			CASE_UPPER
		);
		$signature  = $headers[ self::HEADER_SIGNATURE ] ?? null;
		$raw_body   = parent::getRequestPayload();
		$payload    = json_decode( $raw_body, true );
		$payment_id = $payload['data']['payment']['id'] ?? '';

		$signature && $this->validate_signature( $payment_id, $signature );
		if ( $this->is_valid_signature ) {
			return $raw_body;
		}

		$this->get_api_wrapper()->get_logger()->warning(
			sprintf(
				'Notification was wrong signature. Getting response body by Payment ID: %s',
				$payment_id
			)
		);

		$this->skip_signature_validation();

		$retrieve_payment = $this->get_api_payment_service()->retrieve( $payment_id );
		$raw_data         = json_decode( $raw_body, true );

		return wp_json_encode(
			array(
				'created_at' => $raw_data['created_at'],
				'data'       => array(
					'payment' => $retrieve_payment->toArray(),
				),
			)
		);
	}

	/**
	 * @inheritDoc
	 * @param string $payload
	 *
	 * @return NotificationRequestEntity
	 */
	protected function unserializePayload( $payload ) {
		$notificationEntity = new WC_Payever_Notification_Entity( json_decode( $payload, true ) );

		$notificationEntity->add_available_notification_type( self::NOTIFICATION_TYPE );
		if ( $this->is_valid_signature ) {
			$notificationEntity->setNotificationType( self::NOTIFICATION_TYPE );
		}

		return $notificationEntity;
	}

	/**
	 * @param $payment_id
	 * @param $signature
	 *
	 * @return $this
	 * @throws Exception
	 */
	public function validate_signature( $payment_id, $signature ) {
		$signature_hash = $this->get_helper()->get_hash( $payment_id );

		$this->logger->debug( sprintf( 'Validating signature %s %s', $signature, $signature_hash ) );

		if ( $signature === $signature_hash ) {
			$this->is_valid_signature = true;
		}

		return $this;
	}

	/**
	 * @return $this
	 */
	public function skip_signature_validation() {
		$this->is_valid_signature = true;

		return $this;
	}
}

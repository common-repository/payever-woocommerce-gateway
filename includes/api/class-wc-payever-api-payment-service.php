<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Payments\Enum\Status;
use Payever\Sdk\Payments\Http\MessageEntity\RetrievePaymentResultEntity;
use Payever\Sdk\Payments\Http\ResponseEntity\RetrievePaymentResponse;

class WC_Payever_Api_Payment_Service {
	use WC_Payever_Api_Wrapper_Trait;

	/**
	 * @var RetrievePaymentResultEntity[]
	 */
	private $payment_result_list = array();

	/**
	 * @param string $payment_id
	 *
	 * @return RetrievePaymentResultEntity
	 * @throws LogicException
	 * @throws Exception
	 */
	public function retrieve( $payment_id ) {
		if ( empty( $this->payment_result_list[ $payment_id ] ) ) {
			$response = $this->get_api_wrapper()
				->get_payments_api_client()
				->retrievePaymentRequest( $payment_id );

			/** @var RetrievePaymentResponse $responseEntity */
			$response_entity = $response->getResponseEntity();

			if ( ! $response->isSuccessful() ) {
				throw new LogicException(
					sprintf(
						'Unable to retrieve payment: %s. Error: %s. %s.',
						esc_html( $payment_id ),
						esc_html( $response_entity->getError() ),
						esc_html( $response_entity->getErrorDescription() )
					)
				);
			}

			/** @var RetrievePaymentResultEntity $result */
			$this->payment_result_list[ $payment_id ] = $response_entity->getResult();
		}

		return $this->payment_result_list[ $payment_id ];
	}

	/**
	 * Checks if the payment status is successful.
	 *
	 * @param RetrievePaymentResultEntity $payment_result
	 *
	 * @return bool
	 */
	public function is_successful( RetrievePaymentResultEntity $payment_result ) {
		return in_array(
			$payment_result->getStatus(),
			array( Status::STATUS_IN_PROCESS, Status::STATUS_ACCEPTED, Status::STATUS_PAID )
		);
	}

	/**
	 * Checks if the payment status is failed.
	 *
	 * @param RetrievePaymentResultEntity $payment_result
	 *
	 * @return bool
	 */
	public function is_failed( RetrievePaymentResultEntity $payment_result ) {
		return in_array(
			$payment_result->getStatus(),
			array( Status::STATUS_FAILED, Status::STATUS_DECLINED )
		);
	}

	/**
	 * Checks if the payment status is cancelled.
	 *
	 * @param RetrievePaymentResultEntity $payment_result
	 *
	 * @return bool
	 */
	public function is_cancelled( RetrievePaymentResultEntity $payment_result ) {
		return Status::STATUS_CANCELLED === $payment_result->getStatus();
	}

	/**
	 * Checks if the payment status is paid.
	 *
	 * @param RetrievePaymentResultEntity $payment_result
	 *
	 * @return bool
	 */
	public function is_paid( RetrievePaymentResultEntity $payment_result ) {
		return Status::STATUS_PAID === $payment_result->getStatus();
	}

	/**
	 * Checks if the payment status is new.
	 *
	 * @param RetrievePaymentResultEntity $payment_result
	 *
	 * @return bool
	 */
	public function is_new( RetrievePaymentResultEntity $payment_result ) {
		return Status::STATUS_NEW === $payment_result->getStatus();
	}

	/**
	 * Checks if the payment status is in process.
	 *
	 * @param RetrievePaymentResultEntity $payment_result
	 *
	 * @return bool
	 */
	public function is_in_process( RetrievePaymentResultEntity $payment_result ) {
		return Status::STATUS_IN_PROCESS === $payment_result->getStatus();
	}
}

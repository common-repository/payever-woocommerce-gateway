<?php

use Psr\Log\LoggerInterface;

defined( 'ABSPATH' ) || exit;

if ( ! interface_exists( '\Psr\Log\LoggerInterface', true ) ) {
	return;
}

/**
 * PSR-3 compatible Logger class to allow logging using WooCommerce logging system.
 * @SuppressWarnings(PHPMD.TooManyPublicMethods)
 */
class WC_Payever_Logger implements LoggerInterface {
	use WC_Payever_WP_Wrapper_Trait;

	const DEFAULT_LOG_LEVEL = 'debug';

	private $source = 'payever';

	/**
	 * System is unusable.
	 *
	 * @param string $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function emergency( $message, array $context = array() ) {
		$context['source'] = $this->get_source( $context );

		$this->get_wp_wrapper()->wc_get_logger()->log(
			WC_Log_Levels::EMERGENCY,
			$message,
			$context
		);
	}

	/**
	 * Action must be taken immediately.
	 *
	 * Example: Entire website down, database unavailable, etc. This should
	 * trigger the SMS alerts and wake you up.
	 *
	 * @param string $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function alert( $message, array $context = array() ) {
		$context['source'] = $this->get_source( $context );

		$this->get_wp_wrapper()->wc_get_logger()->log(
			WC_Log_Levels::ALERT,
			$message,
			$context
		);
	}

	/**
	 * Critical conditions.
	 *
	 * Example: Application component unavailable, unexpected exception.
	 *
	 * @param string $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function critical( $message, array $context = array() ) {
		$context['source'] = $this->get_source( $context );

		$this->get_wp_wrapper()->wc_get_logger()->log(
			WC_Log_Levels::CRITICAL,
			$message,
			$context
		);
	}

	/**
	 * Runtime errors that do not require immediate action but should typically
	 * be logged and monitored.
	 *
	 * @param string $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function error( $message, array $context = array() ) {
		$context['source'] = $this->get_source( $context );

		$this->get_wp_wrapper()->wc_get_logger()->log(
			WC_Log_Levels::ERROR,
			$message,
			$context
		);
	}

	/**
	 * Exceptional occurrences that are not errors.
	 *
	 * Example: Use of deprecated APIs, poor use of an API, undesirable things
	 * that are not necessarily wrong.
	 *
	 * @param string $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function warning( $message, array $context = array() ) {
		$context['source'] = $this->get_source( $context );

		$this->get_wp_wrapper()->wc_get_logger()->log(
			WC_Log_Levels::WARNING,
			$message,
			$context
		);
	}

	/**
	 * Normal but significant events.
	 *
	 * @param string $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function notice( $message, array $context = array() ) {
		$context['source'] = $this->get_source( $context );

		$this->get_wp_wrapper()->wc_get_logger()->log(
			WC_Log_Levels::NOTICE,
			$message,
			$context
		);
	}

	/**
	 * Interesting events.
	 *
	 * Example: User logs in, SQL logs.
	 *
	 * @param string $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function info( $message, array $context = array() ) {
		$context['source'] = $this->get_source( $context );

		$this->get_wp_wrapper()->wc_get_logger()->log(
			WC_Log_Levels::INFO,
			$message,
			$context
		);
	}

	/**
	 * Detailed debug information.
	 *
	 * @param string $message
	 * @param mixed[] $context
	 *
	 * @return void
	 */
	public function debug( $message, array $context = array() ) {
		$context['source'] = $this->get_source( $context );

		$this->get_wp_wrapper()->wc_get_logger()->log(
			WC_Log_Levels::DEBUG,
			$message,
			$context
		);
	}

	/**
	 * Logs with an arbitrary level.
	 *
	 * @param mixed $level
	 * @param string $message
	 * @param mixed[] $context
	 *
	 * @return void
	 *
	 * @throws \Psr\Log\InvalidArgumentException
	 */
	public function log( $level, $message, array $context = array() ) {
		$context['source'] = $this->get_source( $context );

		$this->get_wp_wrapper()->wc_get_logger()->log(
			$level,
			$message,
			$context
		);
	}

	/**
	 * Get source.
	 *
	 * @param array $context
	 *
	 * @return string
	 */
	private function get_source( array $context = array() ) {
		$source = $this->source;
		if ( ! empty( $context['source'] ) ) {
			$source = $context['source'];
		}

		return $source;
	}
}

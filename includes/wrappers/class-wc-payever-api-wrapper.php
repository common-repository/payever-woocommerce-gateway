<?php

defined( 'ABSPATH' ) || exit;

use Payever\Sdk\Core\Lock\LockInterface;
use Payever\Sdk\Inventory\InventoryApiClient;
use Payever\Sdk\Payments\PaymentsApiClient;
use Payever\Sdk\Payments\ThirdPartyPluginsApiClient;
use Payever\Sdk\Payments\WidgetsApiClient;
use Payever\Sdk\Plugins\PluginsApiClient;
use Payever\Sdk\Plugins\WhiteLabelPluginsApiClient;
use Payever\Sdk\Plugins\Command\PluginCommandManager;
use Payever\Sdk\Products\ProductsApiClient;
use Payever\Sdk\ThirdParty\Action;
use Payever\Sdk\ThirdParty\ThirdPartyApiClient;
use Psr\Log\LoggerInterface;

/**
 * @method PaymentsApiClient get_payments_api_client()
 * @method PluginsApiClient get_plugins_api_client()
 * @method ThirdPartyPluginsApiClient get_third_party_plugins_api_client()
 * @method WidgetsApiClient get_payment_widgets_api_client()
 * @method WhiteLabelPluginsApiClient get_white_label_plugin_api_client()
 * @method ThirdPartyApiClient get_third_party_api_client()
 * @method ProductsApiClient get_products_api_client()
 * @method InventoryApiClient get_inventory_api_client()
 * @method PluginCommandManager get_plugin_command_manager()
 * @method LoggerInterface get_logger()
 * @method LockInterface get_locker()
 * @method Action\InwardActionProcessor get_inward_sync_action_processor()
 * @method Action\BidirectionalActionProcessor get_bidirectional_sync_action_processor()
 * @method Action\OutwardActionProcessor get_outward_sync_action_processor()
 */
class WC_Payever_API_Wrapper {
	/**
	 * @param string $method
	 * @param array $args
	 * @return false|mixed|null
	 */
	public function __call( $method, $args ) {
		$api = WC_Payever_Api::get_instance();
		return method_exists( $api, $method ) ? call_user_func_array( array( $api, $method ), $args ) : null;
	}
}

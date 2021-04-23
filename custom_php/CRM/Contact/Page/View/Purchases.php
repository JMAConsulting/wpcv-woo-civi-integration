<?php
/**
 * QuickForm class that renders the Purchases Tab on CiviCRM Contact screens.
 *
 * @package WPCV_Woo_Civi
 * @since 2.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * QuickForm class that renders the Purchases Tab on CiviCRM Contact screens.
 *
 * @since 2.0
 */
class CRM_Contact_Page_View_Purchases extends CRM_Core_Page {

	/**
	 * Render the QuickForm page.
	 *
	 * @since 2.0
	 */
	function run() {
		CRM_Utils_System::setTitle( ts( 'Purchases' ) );

		$cid = CRM_Utils_Request::retrieve( 'cid', 'Positive', $this );

		$orders = WCI()->orders_tab->get_orders( $cid );

		$this->assign( 'i18n', array(
			'orderNumber' 	=> __('Order Number', 'woocommerce-civicrm'),
		    'date' 			=> __('Date', 'woocommerce-civicrm'),
		    'billingName' 	=> __('Billing Name', 'woocommerce-civicrm'),
		    'shippingName' 	=> __('Shipping Name', 'woocommerce-civicrm'),
		    'itemCount' 	=> __('Item count', 'woocommerce-civicrm'),
		    'amount'		=> __('Amount', 'woocommerce-civicrm'),
		    'actions' 		=> __('Actions', 'woocommerce-civicrm'),
		    'emptyUid' 		=> __('This contact is not linked to any WordPress user or WooCommerce Customer', 'woocommerce-civicrm'),
				'orders' 		=> __('Orders', 'woocommerce-civicrm'),
				'addOrder' 		=> __('Add Order', 'woocommerce-civicrm'),
		) );
		$this->assign( 'orders', $orders );
		$uid = abs(CRM_Core_BAO_UFMatch::getUFId( $cid ));
		if ( $uid ) {
			$this->assign(
				'newOrderUrl',
				apply_filters('woocommerce_civicrm_add_order_url', add_query_arg(
					array( 'post_type' => 'shop_order', 'user_id' => $uid ),
					admin_url('post-new.php')) ,$uid
				)
			);
		}
		parent::run();
	}

}

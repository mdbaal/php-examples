<?php
	use Exception;
	use WC_Order;
	
	class ExternalOrder
	{
		private string $orderId;
		private WC_Order $wcOrder;
		private string $wcOrderId;
		private string $orderPlacedDateTime;
		private array $shipmentDetails = [];
		private array $billingDetails = [];
		private array $orderItemsIds = [];
		private string $orderShippingStatus = 'Niet Verzonden';
		private array $pluginMeta = [];
		private string $deliveryDate;
		
		public function __construct(string $wcOrderId = null, array $orderData = null)
		{
			if (isset($wcOrderId)) {
				$this->wcOrder = wc_get_order($wcOrderId);
				$pluginMeta = json_decode($this->wcOrder->get_meta('_plugin_order_meta'), true);
				
				if (!isset($pluginMeta))
					throw new Exception("This order does not have plugin metadata");
				
				
				$this->pluginMeta = [
					"orderId" => $pluginMeta['orderId'],
					"wcOrderId" => $pluginMeta['wcOrderId'],
					"orderPlacedDateTime" => $pluginMeta['orderPlacedDateTime'],
					"orderItems" => $pluginMeta['orderItems'],
					"shipmentDetails" => $pluginMeta['shipmentDetails'],
					"billingDetails" => $pluginMeta['billingDetails'],
					'orderShippingStatus' => $pluginMeta['orderShippingStatus']
				];
				
				$this->orderId = $pluginMeta['orderId'];
				$this->wcOrderId = $pluginMeta['wcOrderId'];
				$this->orderPlacedDateTime = $pluginMeta['orderPlacedDateTime'];
				$this->orderItemsIds = $pluginMeta['orderItems'];
				$this->shipmentDetails = $pluginMeta['shipmentDetails'];
				$this->billingDetails = $pluginMeta['billingDetails'];
				$this->deliveryDate = $this->wcOrder->get_meta('_deliver_date');
				$this->orderShippingStatus = $pluginMeta['orderShippingStatus'];
				
			}
			elseif (isset($orderData)) {
				try {
					$this->createOrder($orderData);
				} catch (Exception $e) {
					throw new Exception("Woocommerce order could not be made");
				}
				
				$this->pluginMeta = [
					"orderId" => $this->orderId,
					"wcOrderId" => $this->wcOrderId,
					"orderPlacedDateTime" => $this->orderPlacedDateTime,
					"orderItems" => $this->orderItemsIds,
					"shipmentDetails" => $this->shipmentDetails,
					"billingDetails" => $this->billingDetails,
					'orderShippingStatus' => $this->orderShippingStatus
				];
				
				$this->savePluginMeta($this->pluginMeta);
			}
			else {
				throw new Exception("OrderId nor orderData has been given.");
			}
		}
		
		public function createOrder(array $data)
		{
			$newOrder = wc_create_order();
			
			if (is_wp_error($newOrder)) {
				throw new Exception("Couldn't create an order in woocommerce");
			}
			$latestDeliveryDate = date('d-m-Y',
				strtotime($data['orderItems'][0]['fulfilment']['latestDeliveryDate'] ?? 'now +3 days'));
			
			$this->orderId = $data['orderId'];
			
			$newOrder->set_created_via('plugin.com');
			
			$newOrder->set_date_created(date('c', strtotime('now')));
			$this->setOrderPlacedDateTime($data['orderPlacedDateTime']);
			
			$newOrder->update_meta_data('plugin-order-id', $data['orderId']);
			
			$this->setShipmentDetails($data['shipmentDetails'], $newOrder);
			
			$this->setBillingDetails($data['billingDetails'], $newOrder);
			
			$this->setOrderItems($data['orderItems'], $newOrder);
			
			$newOrder->update_meta_data('_delivery_date', $latestDeliveryDate);
			$this->setDeliveryDate($latestDeliveryDate);
			$newOrder->update_meta_data('_billing_address_index', implode(" ", $this->billingDetails) . ' ' . $this->orderId);
			
			$newOrder->update_meta_data('_shipping_address_index', implode(" ", $this->shipmentDetails) . ' ' . $this->orderId);
			
			$newOrder->calculate_totals(false);
			
			
			$newOrder->set_new_order_email_sent(true);
			$newOrder->set_status('wc-processing');
			
			$newOrder->set_payment_method("api.com");
			$newOrder->set_payment_method_title("api.com");
			
			$newOrder->set_date_paid($data['orderPlacedDateTime']);
			
			$newOrder->save();
			
			$this->wcOrder = $newOrder;
			$this->wcOrderId = $newOrder->get_id();
			
			do_action('woocommerce_checkout_update_order_meta', $newOrder->get_id());
			
			return $newOrder;
		}
		
		private function setShipmentDetails(array $shipment, WC_Order &$newOrder)
		{
			$shippingAddress = [
				'first_name' => $shipment['firstName'],
				'last_name' => $shipment['surname'],
				'company' => $shipment['company'] ?? "",
				'email' => $shipment['email'],
				'phone' => $shipment['deliveryPhoneNumber'] ?? "",
				'address_1' => $shipment['streetName'] . " " . $shipment['houseNumber'] . ($shipment['houseNumberExtension'] ?? ''),
				'city' => $shipment['city'],
				'postcode' => $shipment['zipCode'],
				'country' => $shipment['countryCode']
			];
			
			$this->shipmentDetails = $shippingAddress;
			$newOrder->set_address($shippingAddress, 'shipping');
		}
		
		
		private function setBillingDetails(array $billing, WC_Order &$newOrder)
		{
			$billingAddress = [
				'first_name' => $billing['firstName'],
				'last_name' => $billing['surname'],
				'company' => $billing['company'] ?? "",
				'email' => $billing['email'],
				'address_1' => $billing['streetName'] . " " . $billing['houseNumber'] . ($billing['houseNumberExtension'] ?? ''),
				'city' => $billing['city'],
				'phone' => $billing['shippingPhoneNumber'] ?? "",
				'postcode' => $billing['zipCode'],
				'country' => $billing['countryCode'],
				'vat' => $billing['vatNumber'] ?? ""
			];
			
			$this->billingDetails = $billingAddress;
			$newOrder->set_address($billingAddress, 'billing');
		}
		
		private function setOrderItems(array $items, WC_Order &$newOrder)
		{
			foreach ($items as $item) {
				$reference = $item['offer']['reference'];
				
				if (PLUGIN_DEMO_MODE)
					$reference = "ID:123645";
				
				if (str_contains($reference, "ID:")) {
					$productId = (int)str_replace("ID:", "", $reference);
				} else {
					$productId = wc_get_product_id_by_sku($reference);
				}
				
				$pluginProduct = new PluginProduct($productId);
				
				$orderItemPrice = $item['unitPrice'] * $item['quantity'];
				
				$itemId = $newOrder->add_product($pluginProduct->getWcProduct(), $item['quantity'], [
					'subtotal' => $orderItemPrice,
					'total' => $orderItemPrice
				]);
				
				$order_item = $newOrder->get_item($itemId);
				
				$order_item->add_meta_data("_plugin_item_id", $item['orderItemId'], true);
				
				$order_item->save();
				
				if ($item['cancellationRequest'])
					$this->cancelledItems[] = $item;
				
				$this->orderItemsIds[] = $order_item->get_id();
			}
		}
		
		public function savePluginMeta(array $meta)
		{
			$this->wcOrder->update_meta_data('_plugin_order_meta', json_encode($meta));
			$this->wcOrder->save();
		}
		
		public function getOrderId()
		{
			return $this->orderId;
		}
		
		public function getWcOrderId()
		{
			return $this->wcOrderId;
		}
		
		public function setWcOrderId(string $wcOrderId)
		{
			$this->wcOrderId = $wcOrderId;
		}
		
		public function getOrderPlacedDateTime()
		{
			return $this->orderPlacedDateTime;
		}
		
		public function setOrderPlacedDateTime(string $orderPlacedDateTime)
		{
			$this->orderPlacedDateTime = $orderPlacedDateTime;
		}
		
		public function getShipmentDetails()
		{
			return $this->shipmentDetails;
		}
		
		public function getBillingDetails()
		{
			return $this->billingDetails;
		}
		
		public function getOrderItemsIds()
		{
			return $this->orderItemsIds;
		}
		
		public function getOrderItems()
		{
			return $this->wcOrder->get_items();
		}
		
		public function getWcOrder()
		{
			return $this->wcOrder;
		}
		
		public function getDeliveryDate()
		{
			return $this->deliveryDate;
		}
		
		public function setDeliveryDate(string $deliveryDate)
		{
			$this->deliveryDate = $deliveryDate;
		}
		
		public function getOrderShippingStatus(): string
		{
			return $this->orderShippingStatus;
		}
		
		public function setOrderShippingStatus(string $orderShippingStatus)
		{
			$this->orderShippingStatus = $orderShippingStatus;
			$this->pluginMeta['orderShippingStatus'] = $orderShippingStatus;
			$this->savePluginMeta($this->pluginMeta);
		}
	}
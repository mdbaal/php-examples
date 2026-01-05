<?php	
	use WC_Product_Query;
	
	class SettingsManager
	{
		private array $pluginOrders;
		private array $pluginProducts;
		
		public function __construct($orders, $products)
		{
			$this->pluginOrders = $orders;
			$this->pluginProducts = $products;
		}
		
		public function renderSettingsPage()
		{
			$orderRetrieveTime = wp_date('H:i', get_option("plugin_cron_time")) ?: '';
			
			$nextScheduledDateTime = wp_next_scheduled('cron_plugin_get_open_orders');
			$nextScheduledDateTime = wp_date('d-m-Y H:i', $nextScheduledDateTime) ?: '<b>Nog niet ingesteld</b>'
			?>
			<div class="plugin-settings">
				<h1 class="plugin-settings-title">Plugin.com Instellingen</h1>
				
				<ul class="plugin-settings-tabs">
					<li class="plugin-settings-tab plugin-settings-tab-active">Bestellingen</li>
					<li class="plugin-settings-tab">Producten</li>
					<li class="plugin-settings-tab">API Credentials</li>
					<li class="plugin-settings-tab">Help &amp; info</li>
				</ul>
				
				<div id="plugin-settings-orders" class="plugin-settings-page plugin-settings-page-active">
					<h2>Bestellingen</h2>
					<div>
						<button class="order-btn" type="button" onclick="GetNewOrders()">Bestellingen ophalen</button>
						<button class="order-btn" type="button" onclick="MakeTestOrder()">Test api bestelling
						</button>
						<button class="order-btn" type="button" onclick="OpenPluginManualOrderModal()">Handmatig plugin.com
							order
							aanmaken
						</button>
					</div>
					<p>Bekijk hier alle bestelling die binnen zijn gekomen via plugin.com</p>
					<div class="plugin-orders-table">
						<?php $this->renderOrdersTable(); ?>
					</div>
				</div>
				
				<div id="plugin-settings-products" class="plugin-settings-page">
					<h2>Producten</h2>
					<p>Bekijk hier alle producten die zijn geüpload op plugin.com</p>
					<div class="plugin-products-table">
						<?php $this->renderProductsTable(); ?>
					</div>
				</div>
				
				<div id="plugin-settings-credentials" class="plugin-settings-page">
					<h2>Api Credentials</h2>
					<p><strong>Let op!</strong> Onjuist aanpassen van deze gegevens zorgt er voor dat de koppeling met
						plugin.com niet meer werkt.</p>
					<form action='options.php' method='post'>
						<?php
							settings_fields('plugin_settings');
							do_settings_sections('plugin_settings');
							submit_button();
						?>
					</form>
					
					<div class="plugin-time-settings">
						<h2>Tijd ophalen plugin.com bestellingen</h2>
						<p><strong>Let op!</strong> Het eerder instellen dan de huidige tijd annuleert de eerst volgende
							keer ophalen en zet
							het op een dag later.</p>
						<label for="plugin-cron-time">Hoe laat moeten de bestellingen worden opgehaald?</label>
						<input name="plugin-cron-time" id="plugin-cron-time" type="time" value="<?= $orderRetrieveTime ?>">
						<p>Volgende moment dat de bestellingen worden opgehaald: <?= $nextScheduledDateTime ?></p>
						<button type="submit" onclick="SetPluginOrderTime()">Bevestigen</button>
					</div>
				</div>
				
				
			
			</div>
			<?php
			$this->renderCreateModal();
		}
		
		private function renderOrdersTable()
		{
			?>
			<table>
				<thead>
					<tr>
						<th>Bestelling</th>
						<th>API order-nr.</th>
						<th>Datum</th>
						<th>Status</th>
						<th>Verzending</th>
						<th>Totaal (API.com prijs)</th>
					</tr>
				</thead>
				<tbody>
					<?php
						foreach ($this->pluginOrders as $order) {
							?>
							<tr>
								<td>
									<a href=<?= $order->getWcOrder()->get_edit_order_url() ?>>
										<?= '#' . $order->getWcOrder()->get_id() . ' ' . $order->getWcOrder()->get_formatted_billing_full_name() ?>
									</a>
								</td>
								<td>
									<?= $order->getOrderId() ?>
								</td>
								<td><?= date('d-m-Y', strtotime($order->getOrderPlacedDateTime())) ?></td>
								<td><?= ucfirst($order->getWcOrder()->get_status()) ?></td>
								<td><?= $order->getOrderShippingStatus() ?></td>
								<td>&euro;<?= $order->getWcOrder()->get_total() ?></td>
							</tr>
							<?php
						}
					?>
				</tbody>
			</table>
			<?php
		}
		
		private function renderProductsTable()
		{
			?>
			<table>
				<thead>
					<tr>
						<th>Product</th>
						<th>Voorraad</th>
						<th>WooCommerce prijs</th>
						<th>Verzending</th>
						<th>API commissie</th>
						<th>API prijs</th>
					</tr>
				</thead>
				<tbody>
					<?php
						usort($this->pluginProducts, function ($a, $b) {
							return strcmp($a->getTitle(), $b->getTitle());
						});
						foreach ($this->pluginProducts as $product) {
							?>
							<tr>
								<td>
									<a href="<?= get_edit_post_link($product->getProductId()) ?>"><?= $product->getTitle() ?></a>
								</td>
								<td><?= $product->getStock() ?></td>
								<td><?= $product->getPrice() ?></td>
								<td><?= $product->getShipping() ?></td>
								<td><?= $product->getCommission() ?></td>
								<td><?= $product->getPluginPrice() ?></td>
							</tr>
							<?php
						}
					?>
				</tbody>
			</table>
			<?php
		}
		
		public function setPluginOrderTime()
		{
			if (!isset($_POST['newTime'])) wp_die('No time set');
			$newTime = strtotime('Today ' . $_POST['newTime'] . ' -1 hours');
			$now = strtotime('now');
			
				update_option('plugin_cron_time', $newTime + 86400);
			else
				update_option('plugin_cron_time', $newTime);
			
			
			$this->scheduleOrderCronJob();
			
			wp_die();
		}
		
		public function scheduleOrderCronJob()
		{
			$scheduledTime = get_option("plugin_cron_time");
			
			if (!wp_next_scheduled('cron_plugin_get_open_orders')) {
				wp_schedule_event($scheduledTime, 'daily', 'cron_plugin_get_open_orders');
			}
			else {
				$currentScheduled = wp_next_scheduled('cron_plugin_get_open_orders');
				wp_unschedule_event($currentScheduled, 'cron_plugin_get_open_orders');
				
				wp_schedule_event($scheduledTime, 'daily', 'cron_plugin_get_open_orders');
			}
		}
		
		public function makeTestOrder()
		{
			$data = [
				"orderId" => "A2K8290LP8",
				"pickupPoint" => true,
				"orderPlacedDateTime" => date('c', strtotime('now')),
				"shipmentDetails" => [
					"pickupPointName" => "Albert Heijn=> UTRECHT",
					"salutation" => "MALE",
					"firstName" => "Test",
					"surname" => "Plugincom",
					"streetName" => "Dorpstraat",
					"houseNumber" => "1",
					"houseNumberExtension" => "B",
					"extraAddressInformation" => "Apartment",
					"zipCode" => "1111ZZ",
					"city" => "Utrecht",
					"countryCode" => "NL",
					"email" => "plugin@plugin.com",
					"company" => "plugin.com",
					"deliveryPhoneNumber" => "012123456",
					"language" => "nl"
				],
				"billingDetails" => [
					"salutation" => "MALE",
					"firstName" => "Test",
					"surname" => "Plugincom",
					"streetName" => "Dorpstraat",
					"houseNumber" => "1",
					"houseNumberExtension" => "B",
					"extraAddressInformation" => "Apartment",
					"zipCode" => "1111ZZ",
					"city" => "Utrecht",
					"countryCode" => "NL",
					"email" => "plugin@plugin.com",
					"company" => "plugin.com",
					"vatNumber" => "NL999999999B99",
					"kvkNumber" => "99887766",
					"orderReference" => "MijnReferentie"
				],
				"orderItems" => [
					[
						"orderItemId" => "2012345678",
						"cancellationRequest" => false,
						"fulfilment" => [
							"method" => "FBR",
							"distributionParty" => "RETAILER",
							"latestDeliveryDate" => date('c', strtotime('now')),
							"exactDeliveryDate" => date('c', strtotime('now')),
							"expiryDate" => "2050-02-13",
							"timeFrameType" => "REGULAR"
						],
						"offer" => [
							"offerId" => "123456789",
							"reference" => "134725"
						],
						"product" => [
							"ean" => "0000007740404",
							"title" => "Product Title"
						],
						"quantity" => 1,
						"quantityShipped" => 1,
						"quantityCancelled" => 0,
						"unitPrice" => 12.99,
						"commission" => 5.18,
						"additionalServices" => [
							[
								"serviceType" => "PLACEMENT_AND_INSTALLATION"
							]
						],
						"latestChangedDateTime" => date('c', strtotime('now'))
					]
				]
			];
			
			$order = new PluginOrder(null, $data);
			
			wp_die();
		}
		
		
		public function renderCreateModal()
		{
			?>
			<div class="plugin-manual-order-modal-background">
				<div class="plugin-manual-order-modal">
					<div class="plugin-modal-header">
						<h4 class="plugin-modal-title">Handmatig plugin.com product aanmaken</h4>
						<span class="plugin-close-modal dashicons dashicons-no"></span>
					</div>
					<div class="plugin-modal-body">
						<p>Gebruik deze manier alleen als de bestelling wel is verzonden maar nog niet in WooComerce
							staat.<br>Dit set de bestelling als voltooid in het systeem en verstuurt geen mail naar de
							klant.</p>
						<div class="form-group">
							<label for="name">Voornaam</label>
							<input type="text" id="name" name="name" required placeholder="Voornaam"/>
							
							<label for="nameLast">Achternaam</label>
							<input type="text" id="nameLast" name="nameLast" required placeholder="Achternaam"/>
							
							<label for="street">Straat + huisnummer + toevoeging (Gescheiden met spaties)</label>
							<input type="text" id="street" name="street" required placeholder="Straat 1 A"/>
							
							<label for="zipcode">Postcode</label>
							<input type="text" id="zipcode" name="zipcode" required placeholder="1234AB"/>
							
							<label for="city">Plaats</label>
							<input type="text" id="city" name="city" required placeholder="Winsum"/>
						</div>
						
						<div class="form-group">
							<label for="plugin_order_id">Plugin.com bestelnummer</label>
							<input type="text" id="plugin_order_id" name="plugin_order_id" required/>
							
							<label for="order_date">Bestel datum</label>
							<input type="date" id="order_date" name="order_date" required/>
						</div>
						
						<div class="form-group">
							<label for="products-select">Producten</label>
							<select id="products-select" name="products-select[]" required multiple="multiple">
								<?php
									$products = new WC_Product_Query(array(
										'limit' => -1,
										'return' => 'objects'
									));
									foreach ($products->get_products() as $product) {
										?>
										<option value="<?= $product->get_id() ?>"><?= $product->get_title() ?></option>
										<?php
									}
								?>
							</select>
							<div class="product-and-price">
							
							</div>
						</div>
					
					</div>
					<div class="plugin-modal-footer">
						<button type="button" class="plugin-create-order-btn" onclick="createManualOrder()">Maak
							bestelling
						</button>
					</div>
				</div>
			</div>
			<?php
		}
		
		
	}
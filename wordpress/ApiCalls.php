<?php
	class ApiCalls
	{
		public static function apiGetProcessStatus($processId)
		{
			$headers = [
				'Accept' => 'application/vnd.retailer.v10+json',
				'Content-Type' => 'application/vnd.retailer.v10+json'
			];
			
			return self::apiCallPlugin('GET', 'shared/process-status/' . $processId, $headers);
		}
		
		private static function apiCallPlugin(string $method, string $endpoint, array $headers, string $body = '', array $queries = null, bool $ignoreDemoMode = false): PluginResponse|false
		{
			if (!self::canRequest()) return new PluginResponse(false);
			// Remove '/' from start of $endpoint
			if (str_starts_with($endpoint, '/'))
				$endpoint = substr($endpoint, 1);
			
			$headers['Authorization'] = 'Bearer ' . self::getToken();
			
			// If in demo mode replace normal endpoints with the demo endpoints
			if (defined('PLUGIN_DEMO_MODE') && !$ignoreDemoMode) {
				if (PLUGIN_DEMO_MODE) {
					$endpoint = str_replace('retailer', 'retailer-demo', $endpoint);
					$endpoint = str_replace('shared', 'shared-demo', $endpoint);
				}
			}
			
			$pluginUrl = 'https://apicall.com/' . $endpoint;
			
			// Add Queries
			if (isset($queries)) {
				$pluginUrl .= '?';
				foreach ($queries as $query) {
					$pluginUrl .= $query;
					//If not the last one
					if ($query !== $queries[array_key_last($queries)])
						$pluginUrl .= '&';
				}
			}
			
			// Make the actual request
			$response = wp_remote_request($pluginUrl, array(
				'method' => strtoupper($method),
				'headers' => $headers,
				'body' => $body
			));
			
			$pluginResponse = new PluginResponse($response);
			
			self::updateRequestData($pluginResponse);
			
			return $pluginResponse;
		}
		
		private static function canRequest()
		{
			// Get the option or return true if option does not exist yet
			if (time() > get_option('plugin_rate_reset')) {
				update_option('plugin_can_request', true);
				return true;
			}
			
			return get_option('plugin_can_request', true);
		}
		
		private static function getToken()
		{
			$token = get_option('plugin_token');
			
			if (!$token || $token->expires_in < time()) {
				
				$options = get_option("plugin_settings");
				
				$header = ['Authorization' => 'jwt_token'];
				
				$responseBody = wp_remote_request('https://apicall.com/token', array(
					'method' => 'POST',
					'headers' => $header
				));
				
				$pluginResponse = new PluginResponse($responseBody);
				$token = $pluginResponse->objectFromBody();
				
				if (isset($token->expires_in)) {
					$token->expires_in = time() + 599;
					update_option("plugin_token", $token);
					return $token->access_token;
				} else {
					wp_die(
						'<div id="Plugin_tab_data" class="panel woocommerce_options_panel">
                    <p>Kon geen verbinding maken met de api. Check de credentials en internetverbinding en probeer het opnieuw. ' . $pluginResponse->message . ' ' . $pluginResponse->code . '</p>
                    </div>');
				}
			}
			return $token->access_token;
		}
		
		
		private static function updateRequestData(PluginResponse $pluginResponse)
		{
			update_option('plugin_can_request', true);
			
			if ($pluginResponse->rateLimitRemaining === 0) {
				update_option('plugin_can_request', false);
				update_option('plugin_rate_reset', time() + $pluginResponse->rateLimitResetTime);
			}
		}
		
		public static function apiUploadProductOffer($offer)
		{
			$headers = [
				'Accept' => 'application/vnd.retailer.v10+json',
				'Content-Type' => 'application/vnd.retailer.v10+json'
			];
			
			return self::apiCallPlugin('POST', 'retailer/offers', $headers, $offer);
		}
		
		public static function apiUploadProductContent($content)
		{
			$headers = [
				'Accept' => 'application/vnd.retailer.v10+json',
				'Content-Type' => 'application/vnd.retailer.v10+json'
			];
			
			return self::apiCallPlugin('POST', 'retailer/content/products', $headers, $content, null);
		}
		
		public static function apiRemoveProduct($offerId)
		{
			$headers = [
				'Content-Type' => 'application/vnd.retailer.v10+json',
				'Accept' => 'application/vnd.retailer.v10+json'
			];
			
			return self::apiCallPlugin('DELETE', 'retailer/offers/' . $offerId, $headers);
		}
		
		public static function apiGetOpenOrders()
		{
			$headers = [
				'Content-Type' => 'application/vnd.retailer.v10+json',
				'Accept' => 'application/vnd.retailer.v10+json'
			];
			
			$response = self::apiCallPlugin('GET', 'retailer/orders', $headers, '', ['status=OPEN']);
			
			// If there are no orders return an empty list
			return $response->dictionaryFromBody()['orders'] ?? [];
		}
		
		public static function apiGetSpecificOrder($orderId)
		{
			$headers = [
				'Content-Type' => 'application/vnd.retailer.v10+json',
				'Accept' => 'application/vnd.retailer.v10+json'
			];
			
			return self::apiCallPlugin('GET', 'retailer/orders/' . $orderId, $headers);
		}
		
		
		public static function apiConfirmShipment($orderItemPluginId, $orderId, $transportCompany, $trackAndTrace)
		{
			switch ($transportCompany) {
				case "transporter":
					$transporter = "TRANSPORTER";
			}
			
			$body = json_encode(array(
				"orderItems" => [
					"orderItemId" => $orderItemPluginId
				],
				"shipmentReference" => $orderId,
				"transport" => [
					"transporterCode" => $transporter,
					"trackAndTrace" => $trackAndTrace ?? ""
				]
			));
			
			$headers = [
				'Content-Type' => 'application/vnd.retailer.v10+json',
				'Accept' => 'application/vnd.retailer.v10+json'
			];
			
			return self::apiCallPlugin('POST', 'retailer/shipments', $headers, $body);
		}
		
		
		public static function apiGetCommission($ean, $price)
		{
			$headers = [
				'Content-Type' => 'application/vnd.retailer.v10+json',
				'Accept' => 'application/vnd.retailer.v10+json'
			];
			
			$response = self::apiCallPlugin('GET', 'retailer/commission/' . $ean, $headers, '', ['unit-price=' . $price], true);
			
			
			if ($response->callWasSuccess()) {
				$body = $response->objectFromBody();
				return $body->totalCost;
			}
			
			return 0;
		}
		
		public static function apiGetChunkRecommendation(string $requestJson)
		{
			if (!isset($requestJson) || $requestJson === '') return false;
			
			$headers = [
				'Accept' => 'application/vnd.retailer.v10+json',
				'Content-Type' => 'application/vnd.retailer.v10+json'
			];
			
			return self::apiCallPlugin('POST', 'retailer/content/chunk-recommendations', $headers, $requestJson, null, true);
		}
		
		public static function apiUpdateOffer($offerId, $data)
		{
			if (!isset($offerId, $data)) return false;
			
			$headers = [
				'Accept' => 'application/vnd.retailer.v10+json',
				'Content-Type' => 'application/vnd.retailer.v10+json'
			];
			
			return self::apiCallPlugin('PUT', 'retailer/offers/' . $offerId, $headers, $data, null, true);
		}
		
		public static function apiUpdateOfferPrice($offerId, $data)
		{
			if (!isset($offerId, $data)) return false;
			
			$headers = [
				'Accept' => 'application/vnd.retailer.v10+json',
				'Content-Type' => 'application/vnd.retailer.v10+json'
			];
			
			return self::apiCallPlugin('PUT', 'retailer/offers/' . $offerId . '/price', $headers, $data, null, true);
		}
		
		public static function apiUpdateOfferStock($offerId, $data)
		{
			if (!isset($offerId, $data)) return false;
			
			$headers = [
				'Accept' => 'application/vnd.retailer.v10+json',
				'Content-Type' => 'application/vnd.retailer.v10+json'
			];
			
			return self::apiCallPlugin('PUT', 'retailer/offers/' . $offerId . '/stock', $headers, $data, null, true);
		}
	}
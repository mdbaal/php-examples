<?php

	enum PluginEnviromentType
	{
		case EditProduct;
		case EditOrder;
		
		case Settings;
		
		case None;
	}
	
	class Plugin
	{
		private static Plugin $instance;
		
		private PluginProductManager $productManager;
		private PluginOrderManager $orderManager;
		
		private PluginEnviromentType $pluginEnviromentType;
		private PluginSettingsManager $settingsManager;
		
		private function __construct()
		{
			add_action('admin_init', [$this, 'registerSettings']);
			add_action('admin_menu', [$this, 'addSettingsMenu'], 8);
			add_filter('woocommerce_duplicate_product_exclude_meta', function ($excludes) {
				$excludes[] = '_plugin_product_data';
				$excludes[] = '_plugin_product_uploaded';
				$excludes[] = 'sp_wc_barcode_field';
				$excludes[] = '_global_unique_id';
				
				return $excludes;
			});
			
			$this->determineEnviroment();
			$this->loadDependencies();
		}
		
		public function determineEnviroment()
		{
			if (isset($_GET['post'])) {
				$postId = $_GET['post'];
				if (metadata_exists('post', $postId, '_sku')) {
					$this->pluginEnviromentType = PluginEnviromentType::EditProduct;
					return;
				}
				
				if (metadata_exists('post', $postId, '_plugin_order_meta')) {
					$this->pluginEnviromentType = PluginEnviromentType::EditOrder;
					return;
				}
			}
			
			if (isset($_GET['page'])) {
				if ($_GET['page'] === 'plugin-instellingen') {
					$this->pluginEnviromentType = PluginEnviromentType::Settings;
					return;
				}
			}
			
			$this->pluginEnviromentType = PluginEnviromentType::None;
		}
		
		private function loadDependencies()
		{
			switch ($this->pluginEnviromentType) {
				case PluginEnviromentType::EditProduct:
					$this->initProductModal();
					break;
				case PluginEnviromentType::EditOrder:
					$this->initOrderManager();
					break;
				case PluginEnviromentType::Settings:
					$this->initSettingsPage();
					break;
				case PluginEnviromentType::None:
			}
		}
		
		public function initProductModal()
		{
			add_filter('woocommerce_product_data_tabs', [$this->getProductManager(), 'add_plugin_tab']);
			add_action('woocommerce_product_data_panels', [$this->getProductManager(), 'renderProductTab']);
			
			wp_enqueue_script('pluginuploadmodal', plugin_dir_url(__FILE__) . '../assets/js/pluginuploadmodal.js');
			wp_localize_script('pluginuploadmodal', 'product_content', $this->getProductManager()->getCurrentProduct()->getContentData());
			wp_localize_script('pluginuploadmodal', 'product_data', $this->getProductManager()->getCurrentProduct()->getProductData());
			
			wp_enqueue_style('googlefonts', "https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined");
			wp_enqueue_style('plugintabcss', plugin_dir_url(__FILE__) . '../assets/css/plugin-product-tab.css');
		}
		
		public function getProductManager()
		{
			if (!isset($this->productManager)) {
				$this->productManager = new PluginProductManager();
			}
			
			return $this->productManager;
		}
		
		private function initOrderManager()
		{
			add_action('woocommerce_admin_order_data_after_shipping_address', [$this->getOrderManager(), 'renderPluginShippingButton'], 11, 3);
			add_filter('woocommerce_hidden_order_itemmeta', function ($hidden_fields) {
				return array_merge($hidden_fields, array(
						'_plugin_item_id',
						'_plugin_shipment_company',
						'_plugin_shipment_tracktrace',
						'_plugin_confirmed_shipment',
						'_plugin_item_shipping_status'
					)
				);
			});
			wp_enqueue_script('pluginshippingjs', plugin_dir_url(__FILE__) . '../assets/js/pluginshippingmodal.js');
			wp_enqueue_style('googlefonts', "https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined");
			wp_enqueue_style('pluginshippingcss', plugin_dir_url(__FILE__) . '../assets/css/plugin-shipping-modal.css');
		}
		
		public function getOrderManager()
		{
			if (!isset($this->orderManager)) {
				$this->orderManager = new PluginOrderManager();
			}
			
			return $this->orderManager;
		}
		
		public function initSettingsPage()
		{
			wp_enqueue_style('googlefonts', "https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined");
			
			wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
			wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js');
			
			wp_enqueue_style('pluginsettingscss', plugin_dir_url(__FILE__) . '../assets/css/plugin-settings.css');
			wp_enqueue_script('pluginsettingsjs', plugin_dir_url(__FILE__) . '../assets/js/pluginsettings.js');
		}
		
		public static function getInstance()
		{
			if (!isset(self::$instance)) {
				self::$instance = new Plugin();
			}
			
			return self::$instance;
		}
		
		public function registerSettings()
		{
			register_setting("plugin_settings", "plugin_settings");
			
			add_settings_section(
				"plugin_credentials",
				'',
				false,
				'plugin_settings'
			);
			
			add_settings_field(
				"plugin_id",
				__("Client ID", 'plugin.com'),
				[$this, 'renderClientIdInput'],
				'plugin_settings',
				'plugin_credentials'
			);
			
			add_settings_field(
				"plugin_secret",
				__("Client Secret", 'plugin.com'),
				[$this, 'renderSecretInput'],
				'plugin_settings',
				'plugin_credentials'
			);
		}
		
		public function addSettingsMenu()
		{
			add_options_page(
				"Plugin.com",
				"Plugin.com Instellingen",
				'manage_options',
				'plugin-instellingen',
				[$this->getSettingsManager(), 'renderSettingsPage']
			);
		}
		
		public function getSettingsManager()
		{
			if (!isset($this->settingsManager)) {
				$orders = $this->getOrderManager()->getAllPluginOrders();
				$products = $this->getProductManager()->getUploadedProducts();
				
				$this->settingsManager = new PluginSettingsManager($orders, $products);
			}
			
			return $this->settingsManager;
		}
		
		public function getEnviroment()
		{
			return $this->pluginEnviromentType;
		}
		
		public function renderClientIdInput()
		{
			$options = get_option('plugin_settings');
			?>
			<input type='text' name='plugin_settings[plugin_id]' value='<?php echo $options['plugin_id'] ?? ""; ?>'>
			<?php
		}
		
		public function renderSecretInput()
		{
			$options = get_option('plugin_settings');
			?>
			<input type='password' name='plugin_settings[plugin_secret]' value='<?php echo $options['plugin_secret'] ?? ""; ?>'>
			<?php
		}
		
	}
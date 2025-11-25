<?php

/*** We create a file named coinremitter.php’ in the admin/controller/extension/module/ folder. Since we named the file coinremitter.php and put it at admin/controller/extension/module/ folder, the controller class name will be ControllerExtensionModuleCoinremitter which inherits the Controller. ***/

namespace Opencart\Admin\Controller\Extension\Coinremitter\Module;

use Opencart\Admin\Controller\Common\Pagination;

define("MINIMUM_USD_AMOUNT", 5);
class Coinremitter extends \Opencart\System\Engine\Controller
{

	/*** private error property for this class only which will hold the error message if occurs any. ***/
	private $error = array();
	/*** install_module will install this module also which contains wallets stuff.This is called from controller->extension->payment->coinremitter->install(). We are installing here because users dont need to install manually. This method is automatically called when user install coinremitter payment module from payment extensions. This method is different from above this page's install() (if defined) ***/
	public function install()
	{
		$this->load->model('setting/extension');
		$this->model_setting_extension->install('module', 'coinremitter', 'coinremitter');

		$this->load->model('setting/extension');
		$this->model_setting_extension->install('module', 'coinremitter', 'coinremitter');

		//add event start
		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('coinremitter');
		$this->model_setting_event->deleteEventByCode('coinremitter_admin_order_list');
		$this->model_setting_event->deleteEventByCode('coinremitter_admin_order_detail');
		$this->model_setting_event->deleteEventByCode('coinremitter_user_order_list');
		$this->model_setting_event->deleteEventByCode('coinremitter_user_order_detail');
		$data = array(
			'code' => 'coinremitter_admin_order_list',
			'description' => 'Display coinremitter order list',
			'trigger' => 'admin/view/sale/order_list/before',
			'action' => 'extension/coinremitter/module/view_sale_order_list_before',
			'status' => 1,
			'sort_order' => 1
		);
		/*** Event will fire when admin clicked on order list   ***/
		$this->model_setting_event->addEvent($data);

		$data = array(
			'code' => 'coinremitter_admin_order_detail',
			'description' => 'Display coinremitter order detail',
			// 'trigger' => 'admin/view/sale/order_info/before',
			'trigger' => 'admin/controller/sale/order.info/before',
			'action' => 'extension/coinremitter/admin/controller/module/coinremitter.adminOrderDetail',
			'status' => 1,
			'sort_order' => 1
		);
		/*** Event will fire when admin clicked on particular order detail  ***/
		$this->model_setting_event->addEvent($data);


		$data = array(
			'code' => 'coinremitter_user_order_list',
			'description' => 'Display coinremitter order list',
			'trigger' => 'catalog/view/account/order_list/before',
			'action' => 'extension/coinremitter/module/view_account_order_list_before',
			'status' => 1,
			'sort_order' => 1
		);
		/*** Event will fire when user clicked on order list   ***/
		$this->model_setting_event->addEvent($data);


		$data = array(
			'code' => 'coinremitter_user_order_detail',
			'description' => 'Display coinremitter order detail',
			'trigger' => 'catalog/view/account/order_info/before',
			'action' => 'extension/coinremitter/module/view_account_order_info_before',
			'status' => 1,
			'sort_order' => 1
		);
		/*** Event will fire when user clicked on particular order detail  ***/
		$this->model_setting_event->addEvent($data);

		//add event end

		$json = [];

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('module_coinremitter', $this->request->post);

			$json['success'] = $this->language->get('text_success');

			$default_settings = array(
				'module_coinremitter_title' => 'Coinremitter',
				'module_coinremitter_status' => 1,
			);
			$this->model_setting_setting->editSetting('module_coinremitter', $default_settings);
		}

		/*** add permission for users ***/
		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/coinremitter/module/coinremitter');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/coinremitter/module/coinremitter');
	}

	public function view_sale_order_list_before(&$route, &$args, &$output)
	{

		$this->load->model('extension/coinremitter/module/coinremitter');

		$orderIds = [];
		$orders = $args['orders'] ?? [];
		foreach ($orders as $order) {
			$orderId = $order['order_id'];
			/*** check if order is of coinremitter's order or not ***/
			$order_detail = $this->model_extension_coinremitter_module_coinremitter->getOrder($orderId);
			if ($order_detail) {
				$orderIds[] = $orderId;
			}
		}

		if (!empty($orderIds)) {
			$json = array();
			$url = HTTPS_CATALOG;

			$curl = curl_init();

			$data = array('order_ids' => $orderIds);

			// Set SSL if required
			if (substr($url, 0, 5) == 'https') {
				curl_setopt($curl, CURLOPT_PORT, 443);
			}

			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLINFO_HEADER_OUT, true);
			curl_setopt($curl, CURLOPT_USERAGENT, $this->request->server['HTTP_USER_AGENT']);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_FORBID_REUSE, false);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_URL, $url . 'index.php?route=extension/coinremitter/module/coinremitter|changeOrderStatus');
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));

			$json = curl_exec($curl);

			curl_close($curl);
		}
	}

	public function adminOrderDetail(&$route, &$args, &$output)
	{
		$this->load->model('extension/coinremitter/module/coinremitter');

		$orderId = $args['order_id'] ?? 0;

		/*** check if order is of coinremitter's order or not ***/
		$order_detail = $this->model_extension_coinremitter_module_coinremitter->getOrder($orderId);

		if ($order_detail) {

			$json = array();

			$url = HTTPS_CATALOG;

			$curl = curl_init();

			$data = array('order_ids' => [$orderId]);

			// Set SSL if required
			if (substr($url, 0, 5) == 'https') {
				curl_setopt($curl, CURLOPT_PORT, 443);
			}

			curl_setopt($curl, CURLOPT_HEADER, false);
			curl_setopt($curl, CURLINFO_HEADER_OUT, true);
			curl_setopt($curl, CURLOPT_USERAGENT, $this->request->server['HTTP_USER_AGENT']);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_FORBID_REUSE, false);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_URL, $url . 'index.php?route=extension/coinremitter/module/coinremitter|changeOrderStatus');
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));

			$json = curl_exec($curl);

			curl_close($curl);
		}
	}


	/*** The index method is default method, it is called whenever the main controller ControllerExtensionModuleCoinremitter is called through route URL, like http://opencart.loc/admin/index.php?route=extension/module/coinremitter&user_token=5XdZM31DgqUkg4uEmrInmL3pp7uiaYUr.
	 * Here the language file is loaded
	 * Then Document title is set
	 * Then model file is loaded
	 * Then protected method getList is called which list out all the wallets. Thus default page is the listing page because we called getList in index() method. ***/
	public function index()
	{
		$this->load->language('extension/coinremitter/module/coinremitter');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/coinremitter/module/coinremitter');
		$this->getList();
	}

	/*** add() - This method is called when someone clicks the add button in the listing page and the save button on the form. If the add button is clicked then it shows the forms with blank fields. If the save button is clicked on the form then it validates the data and saves data in the database and redirects to the listing page. ***/
	public function add()
	{

		// $this->obj_curl = $this->coinremitter->get_instance($this->registry);
		$this->load->language('extension/coinremitter/module/coinremitter');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/coinremitter/module/coinremitter');

		/*** This is the section when someone clicks save button while adding the wallet. It checks if the request method is post and if form is validated. Then it will call the addWallet method of model class which save the new wallet to the database ***/
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {

			/*** check if api_key and password is valid for selected coin ***/
			$param = $this->request->post;
			$walletRes = $this->checkCredentials(['api_key' => $param['api_key'], 'password' => $param['password']]);
			if ($walletRes) {

				$priceInUsd = $this->getPriceInUsd($walletRes['coin_symbol']);
				$usdMinValue = $walletRes['minimum_deposit_amount'] * $priceInUsd;
				$baseFiatCurrency = $this->config->get('config_currency');
				$minimumInvFiatAmount = $this->currency->convert($usdMinValue, 'USD', $baseFiatCurrency);
				$minimumInvFiatAmount = number_format($minimumInvFiatAmount, 2, '.', '');
				if ($param['minimum_invoice_amount'] < $minimumInvFiatAmount) {
					$this->error['minimum_invoice_amount'] = $this->language->get('error_minimum_value') . $minimumInvFiatAmount . ' ' . $baseFiatCurrency;
					$this->getForm();
					return;
				}
				// load coinremitter library
				$this->load->model('extension/coinremitter/payment/coinremitter_api');
				$obj_curl = $this->model_extension_coinremitter_payment_coinremitter_api->get_instance($this->registry);

				$isWalletExists = $this->model_extension_coinremitter_module_coinremitter->getWalletByCoin($walletRes['coin_symbol']);
				if (empty($isWalletExists)) {
					$insertData = array(
						'wallet_name' => $walletRes['wallet_name'],
						'coin_symbol' => $walletRes['coin_symbol'],
						'coin_name' => $walletRes['coin'],
						'api_key' => $param['api_key'],
						'password' => $obj_curl->encrypt($param['password']),
						'minimum_invoice_amount' => $param['minimum_invoice_amount'],
						'exchange_rate_multiplier' => $param['exchange_rate_multiplier'],
						'unit_fiat_amount' => $priceInUsd,
						'base_fiat_symbol' => $baseFiatCurrency,
					);
					$this->model_extension_coinremitter_module_coinremitter->addWallet($insertData);
					$this->session->data['success'] = $this->language->get('text_success');
				}
				/*download coin image if not exists*/
				$coin_image_path = dirname(__DIR__, 2) . '/view/image/coinremitter/' . $walletRes['coin_symbol'] . '.png';
				if (!file_exists($coin_image_path)) {
					$url = $walletRes['coin_logo'];
					if (getimagesize($url)) {
						copy($url, $coin_image_path);
					}
				}

				$url = '';
				if (isset($this->request->get['sort'])) {
					$url .= '&sort=' . $this->request->get['sort'];
				}
				if (isset($this->request->get['order'])) {
					$url .= '&order=' . $this->request->get['order'];
				}
				if (isset($this->request->get['page'])) {
					$url .= '&page=' . $this->request->get['page'];
				}
				/*** This line of code is to redirect to the listing page ***/
				$this->response->redirect($this->url->link('extension/coinremitter/module/coinremitter', 'user_token=' . $this->session->data['user_token'] . $url, true));
			}
		}
		/*** This is to show the form ***/
		$this->getForm();
	}

	/*** edit() - Edit method is called when someone clicks the edit button in the listing page of the wallet which will show the form with the data, and similarly it is called when someone clicks the save button on the form while editing, when saved it will validate the form and update the data in the database and redirects to the listing page. ***/
	public function edit()
	{
		$this->load->language('extension/coinremitter/module/coinremitter');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/coinremitter/module/coinremitter');
		/*** This is the section when someone clicks edit button and save the wallet. It checks if the request method is post and if form is validated. Then it will call the editWallet method of model class which save the updated testimonial to the database ***/
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()) {

			// load coinremitter library
			$this->load->model('extension/coinremitter/payment/coinremitter_api');
			$obj_curl = $this->model_extension_coinremitter_payment_coinremitter_api->get_instance($this->registry);

			/*** check if api_key and password is valid for selected coin ***/
			$param = $this->request->post;
			$walletRes = $this->checkCredentials(['api_key' => $param['api_key'], 'password' => $param['password']]);
			if ($walletRes) {
				$wallet = $this->model_extension_coinremitter_module_coinremitter->getWallet($this->request->get['id']);
				if (!empty($wallet)) {
					if ($wallet['coin_symbol'] != $walletRes['coin_symbol']) {

						$this->error['api_key'] = $this->language->get('error_coin_exists');
					} else {

						$priceInUsd = $this->getPriceInUsd($walletRes['coin_symbol']);
						$usdMinValue = $walletRes['minimum_deposit_amount'] * $priceInUsd;
						$baseFiatCurrency = $this->config->get('config_currency');
						$minimumInvFiatAmount = $this->currency->convert($usdMinValue, 'USD', $baseFiatCurrency);
						$minimumInvFiatAmount = number_format($minimumInvFiatAmount, 2, '.', '');
						if ($param['minimum_invoice_amount'] < $minimumInvFiatAmount) {
							$this->error['minimum_invoice_amount'] = $this->language->get('error_minimum_value') . $minimumInvFiatAmount . ' ' . $baseFiatCurrency;
							$this->getForm();
							return;
						}
						$updateData = array(
							'coin_symbol' => $walletRes['coin_symbol'],
							'coin_name' => $walletRes['coin'],
							'wallet_name' => $walletRes['wallet_name'],
							'api_key' => $param['api_key'],
							'password' => $obj_curl->encrypt($param['password']),
							'minimum_invoice_amount' => $param['minimum_invoice_amount'],
							'exchange_rate_multiplier' => $param['exchange_rate_multiplier'],
							'unit_fiat_amount' => $priceInUsd,
							'base_fiat_symbol' => $baseFiatCurrency,
						);
						$this->model_extension_coinremitter_module_coinremitter->editWallet($this->request->get['id'], $updateData);
						$this->session->data['success'] = $this->language->get('text_success');
						$url = '';
						if (isset($this->request->get['sort'])) {
							$url .= '&sort=' . $this->request->get['sort'];
						}
						if (isset($this->request->get['order'])) {
							$url .= '&order=' . $this->request->get['order'];
						}
						if (isset($this->request->get['page'])) {
							$url .= '&page=' . $this->request->get['page'];
						}
						/*** This line of code is to redirect to the listing page ***/
						$this->response->redirect($this->url->link('extension/coinremitter/module/coinremitter', 'user_token=' . $this->session->data['user_token'] . $url, true));
					}
				}
			}
		}
		/*** This is to show the form ***/
		$this->getForm();
	}


	/*** refresh() - refresh method is called when someone clicks the refresh button in the listing page of the wallet which will refresh the all wallets balances with api call and update the balance in their respective wallet and redirects to the listing page. ***/
	public function refresh()
	{
		$this->load->language('extension/coinremitter/module/coinremitter');
		$this->load->model('extension/coinremitter/module/coinremitter');
		// load coinremitter library
		$this->load->model('extension/coinremitter/payment/coinremitter_api');
		$this->error['warning'] = null;
		$this->session->data['success'] = $this->language->get('text_success');
		$this->getList();
	}

	/*** delete() - Delete method is called when someone clicks delete button by selecting the wallet to delete. Once wallet/s is/are deleted then it is redirected to the listing page.***/
	public function delete()
	{
		$this->load->language('extension/coinremitter/module/coinremitter');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/coinremitter/module/coinremitter');
		/*** This is the section which find which wallets are selected that need to be deleted. The deleteWallet method of the model class is called which remove the wallet from the database ***/
		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $id) {
				$this->model_extension_coinremitter_module_coinremitter->deleteWallet($id);
			}
			$this->session->data['success'] = $this->language->get('text_success');
			$url = '';
			if (isset($this->request->get['sort'])) {
				$url .= '&sort=' . $this->request->get['sort'];
			}
			if (isset($this->request->get['order'])) {
				$url .= '&order=' . $this->request->get['order'];
			}
			if (isset($this->request->get['page'])) {
				$url .= '&page=' . $this->request->get['page'];
			}
			$this->response->redirect($this->url->link('extension/coinremitter/module/coinremitter', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}
		$this->getList();
	}

	/*** getList() - This method creates logic to create a listing and pass variables to template twig files where they are manipulated and shown in the table.
	the listing page will look like in the image url https://webocreation.com/blog/wp-content/uploads/2019/09/testimonial-listings.jpg  ***/
	protected function getList()
	{


		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'coin_symbol';
		}
		if (isset($this->request->get['order'])) {
			$order = $this->request->get['order'];
		} else {
			$order = 'ASC';
		}
		if (isset($this->request->get['page'])) {
			$page = $this->request->get['page'];
		} else {
			$page = 1;
		}
		$url = '';
		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}
		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}
		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}
		/*** Breadcrumbs variables set ***/
		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/coinremitter/module/coinremitter', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);
		/*** Add, Refresh and delete button URL setup for the form ***/
		$data['add'] = $this->url->link('extension/coinremitter/module/coinremitter|add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['refresh'] = $this->url->link('extension/coinremitter/module/coinremitter|refresh', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('extension/coinremitter/module/coinremitter|delete', 'user_token=' . $this->session->data['user_token'] . $url, true);
		/*** wallets variables is set to empty array, latter we will set the wallets in it ***/
		$data['wallets'] = array();
		/*** We set filter_data like below, $sort, $order, $page are assigned in above code, we can get from the URL paramaters or the config values. We pass this array and in model the SQL will be create as per this filter data   ***/

		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_pagination_admin'),
			'limit' => $this->config->get('config_pagination_admin')
		);
		/*** This is to get the total of number of wallets as this is needed for the pagination ***/
		$wallets_total = $this->model_extension_coinremitter_module_coinremitter->getTotalWallets();
		/*** This is to get filtered wallets ***/
		$results = $this->model_extension_coinremitter_module_coinremitter->getWallets($filter_data);
		/*** This is how we set data to the wallets array, we can get many variables in the $results variables so we separate what is needed in template twig file and pass them to it ***/

		$baseFiatCurrency = $this->config->get('config_currency');
		
		foreach ($results as $result) {	
			$minimumInvFiatAmount = $result['minimum_invoice_amount'];
			if($result['base_fiat_symbol'] != $baseFiatCurrency){
				$minimumInvFiatAmount = $this->currency->convert($result['minimum_invoice_amount'], $result['base_fiat_symbol'], $baseFiatCurrency);
				$this->model_extension_coinremitter_module_coinremitter->updateWalletMinInvRate($result['id'],array('minimum_invoice_amount' => $minimumInvFiatAmount, 'base_fiat_symbol' => $baseFiatCurrency));
			}
			$this->load->model('extension/coinremitter/payment/coinremitter_api');
			$obj_curl = $this->model_extension_coinremitter_payment_coinremitter_api->get_instance($this->registry);
			$result['password'] = $obj_curl->decrypt($result['password']);
			$ccRes = $this->checkCredentials($result);
			if ($ccRes) {
				if ($ccRes['balance'] > 0) {
					$balance = number_format($ccRes['balance'], 8, '.', '');
				} else {
					$balance = 0;
				}
			} else {
				$balance = '<span title="Invalid API key or password. Please check credential again."><i class="fa fa-exclamation-circle"></i></span>';
			}
			$data['wallets'][] = array(
				'id' => $result['id'],
				'logo' 		=> str_replace('/admin/index.php?route=', '/', $this->url->link('extension/coinremitter/admin/view/image/coinremitter/')) . $result['coin_symbol'] . '.png',
				'coin_symbol'      => $result['coin_symbol'],
				'coin_name' => $result['coin_name'],
				'wallet_name' => $result['wallet_name'],
				'balance' => $balance,
				'minimum_invoice_amount' => number_format($minimumInvFiatAmount, 2, '.', ''),
				'fiat_symbol' => $this->config->get('config_currency'),
				'exchange_rate_multiplier' => $result['exchange_rate_multiplier'],
				'created_at' => date('d-m-Y H:i:s', strtotime($result['created_at'])),
				'edit'        => $this->url->link('extension/coinremitter/module/coinremitter|edit', 'user_token=' . $this->session->data['user_token'] . '&id=' . $result['id'] . $url, true),
				'delete'      => $this->url->link('extension/coinremitter/module/coinremitter|delete', 'user_token=' . $this->session->data['user_token'] . '&id=' . $result['id'] . $url, true)
			);
		}
		if (isset($this->error['warning'])) {
			$data['error_warning'] = "";
			unset($this->error['warning']);
		} else {
			$data['error_warning'] = '';
		}
		if (isset($this->session->data['success'])) {
			$data['success'] = $this->session->data['success'];
			unset($this->session->data['success']);
		} else {
			$data['success'] = '';
		}
		if (isset($this->request->post['selected'])) {
			$data['selected'] = (array) $this->request->post['selected'];
		} else {
			$data['selected'] = array();
		}
		$url = '';
		if ($order == 'ASC') {
			$url .= '&order=DESC';
		} else {
			$url .= '&order=ASC';
		}
		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}
		$data['coin'] = $this->url->link('extension/coinremitter/coinremitter', 'user_token=' . $this->session->data['user_token'] . '&sort=coin' . $url, true);
		$url = '';
		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}
		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}
		
		$data['results'] = sprintf($this->language->get('text_pagination'), ($wallets_total) ? (($page - 1) * $this->config->get('config_pagination_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_pagination_admin')) > ($wallets_total - $this->config->get('config_pagination_admin'))) ? $wallets_total : ((($page - 1) * $this->config->get('config_pagination_admin')) + $this->config->get('config_pagination_admin')), $wallets_total, ceil($wallets_total / $this->config->get('config_pagination_admin')));
		$data['sort'] = $sort;
		$data['order'] = $order;
		/*** Pass the header, column_left and footer to the coinremitter_list.twig template ***/
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		/*** Set the response output ***/

		$webhook_url_link = str_replace('/admin/', '/', $this->url->link('extension/coinremitter/payment/coinremitter|webhook'));
		$data['webhook_url_msg'] = "For all these wallets, add this <strong>" . $webhook_url_link . "</strong> URL in the Webhook URL field of your Coinremitter wallet's General Settings.";
		$data['button_add'] = "Add Wallet";
		$data['button_refresh'] = "Refresh wallet list";
		$data['button_delete'] = "Delete Wallet";
		$this->response->setOutput($this->load->view('extension/coinremitter/module/coinremitter_list', $data));
	}

	/*** getForm() - This method creates logic to create a form. When someone clicks the add button then it shows form with blank fields, if someone clicks the edit button then it shows form with data of that wallet.
	 ***/
	protected function getForm()
	{
		$data['text_form'] = !isset($this->request->get['id']) ? $this->language->get('text_add') : $this->language->get('text_edit');
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}
		if (isset($this->error['api_key'])) {
			$data['error_api_key'] = $this->error['api_key'];
		} else {
			$data['error_api_key'] = array();
		}
		if (isset($this->error['password'])) {
			$data['error_password'] = $this->error['password'];
		} else {
			$data['error_password'] = array();
		}
		if (isset($this->error['exchange_rate_multiplier'])) {
			$data['error_exchange_rate_multiplier'] = $this->error['exchange_rate_multiplier'];
		} else {
			$data['error_exchange_rate_multiplier'] = array();
		}
		if (isset($this->error['minimum_invoice_amount'])) {
			$data['error_minimum_invoice_amount'] = $this->error['minimum_invoice_amount'];
		} else {
			$data['error_minimum_invoice_amount'] = array();
		}
		$url = '';
		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}
		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}

		if (isset($this->request->get['page'])) {
			$url .= '&page=' . $this->request->get['page'];
		}
		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/coinremitter/module/coinremitter', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);
		/*** This is the code which separate the action of edit or add action, if the URL parameter contains id then it is edit else it is add  ***/
		if (!isset($this->request->get['id'])) {
			$data['action'] = $this->url->link('extension/coinremitter/module/coinremitter|add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('extension/coinremitter/module/coinremitter|edit', 'user_token=' . $this->session->data['user_token'] . '&id=' . $this->request->get['id'] . $url, true);
		}
		$data['cancel'] = $this->url->link('extension/coinremitter/module/coinremitter', 'user_token=' . $this->session->data['user_token'] . $url, true);
		/*** This is the code which pulls the wallet that we have to edit  ***/
		if (isset($this->request->get['id'])) {
			// load coinremitter library
			$this->load->model('extension/coinremitter/payment/coinremitter_api');
			$obj_curl = $this->model_extension_coinremitter_payment_coinremitter_api->get_instance($this->registry);

			$wallet_info = $this->model_extension_coinremitter_module_coinremitter->getWallet($this->request->get['id']);
			$wallet_info['password'] = $obj_curl->decrypt($wallet_info['password']);
		}
		$data['user_token'] = $this->session->data['user_token'];

		/*** This is for api_key field ***/
		if (isset($this->request->post['api_key'])) {
			$data['api_key'] = $this->request->post['api_key'];
		} elseif (!empty($wallet_info)) {
			$data['api_key'] = $wallet_info['api_key'];
		} else {
			$data['api_key'] = '';
		}

		/*** This is for password field ***/
		if (isset($this->request->post['password'])) {
			$data['password'] = $this->request->post['password'];
		} elseif (!empty($wallet_info)) {
			$data['password'] = $wallet_info['password'];
		} else {
			$data['password'] = '';
		}

		/*** This is for exchange_rate_multiplier field ***/
		if (isset($this->request->post['exchange_rate_multiplier'])) {
			$data['exchange_rate_multiplier'] = $this->request->post['exchange_rate_multiplier'];
		} elseif (!empty($wallet_info)) {
			$data['exchange_rate_multiplier'] = $wallet_info['exchange_rate_multiplier'];
		} else {
			$data['exchange_rate_multiplier'] = '1';
		}

		/*** This is for minimum_value field ***/
		if (isset($this->request->post['minimum_invoice_amount'])) {
			$data['minimum_invoice_amount'] = $this->request->post['minimum_invoice_amount'];
		} elseif (!empty($wallet_info)) {
			$data['minimum_invoice_amount'] = $wallet_info['minimum_invoice_amount'];
		} else {
			$data['minimum_invoice_amount'] = '0';
		}

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/coinremitter/module/coinremitter_form', $data));
	}

	/***
	 * validateForm() - This method is to check whether the user has permission to edit or add the data from the form. In this method, we can validate any form field if needed.
	 ***/
	protected function validateForm()
	{
		/*** This is how we check if the user has permission to modify or not. ***/
		if (!$this->user->hasPermission('modify', 'extension/coinremitter/module/coinremitter')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		/*** This is to check if the api_key and password is not blank ***/
		if ($this->request->post['api_key'] == '') {
			$this->error['api_key'] = $this->language->get('error_api_key');
		}
		if ($this->request->post['password'] == '') {
			$this->error['password'] = $this->language->get('error_password');
		}
		/* Minimum invoice field validation */
		if ($this->request->post['exchange_rate_multiplier'] == '') {
			$this->error['exchange_rate_multiplier'] = $this->language->get('error_exchange_rate_required');
		} else if (!is_numeric($this->request->post['exchange_rate_multiplier'])) {
			$this->error['exchange_rate_multiplier'] = $this->language->get('error_exchange_rate_numeric');
		} else if ($this->request->post['exchange_rate_multiplier'] <= 0 || $this->request->post['exchange_rate_multiplier'] >= 101) {
			$this->error['exchange_rate_multiplier'] = $this->language->get('error_exchange_rate');
		}

		/*** Minimum invoice field validation ***/
		if ($this->request->post['minimum_invoice_amount'] == '') {
			$this->error['minimum_invoice_amount'] = $this->language->get('error_minimum_value_required');
		} else if (!is_numeric($this->request->post['minimum_invoice_amount'])) {
			$this->error['minimum_invoice_amount'] = $this->language->get('error_minimum_value_numeric');
		}

		if ($this->error && !isset($this->error['warning'])) {
			$this->error['warning'] = $this->language->get('error_warning');
		}

		return !$this->error;
	}

	/***
	 * checkCredentials() - This method is to check given creds for selected coin
	 *					  - Takes data as parameter	
	 ***/
	protected function checkCredentials($data = array())
	{
		// load coinremitter library
		$this->load->model('extension/coinremitter/payment/coinremitter_api');
		$obj_curl = $this->model_extension_coinremitter_payment_coinremitter_api->get_instance($this->registry);

		/*** make an api call for wallet balance ***/
		$api_key = $data['api_key'];
		$password = $obj_curl->encrypt($data['password']);

		$endPoint = '/wallet/balance';
		$params = array('api_key' => $api_key, 'password' => $password);

		$api_response = $obj_curl->apiCall($endPoint, $params);
		if ($api_response) {
			if (!$api_response['success']) {
				$this->error['warning'] = $api_response['msg'];
				return !$this->error;
			}
			return $api_response['data'];
		}
		return [];
	}

	/***
	 * getFiatToCryptoRate() - This method is to get fiat to crypto rate
	 ***/
	protected function getFiatToCryptoRate($data = array())
	{
		// load coinremitter library
		$this->load->model('extension/coinremitter/payment/coinremitter_api');
		$obj_curl = $this->model_extension_coinremitter_payment_coinremitter_api->get_instance($this->registry);

		$endPoint = '/rate/fiat-to-crypto';

		$api_response = $obj_curl->apiCall($endPoint);
		if ($api_response) {
			if (!$api_response['success']) {
				$this->error['warning'] = $api_response['msg'];
				return !$this->error;
			}
			return $api_response['data'];
		}
		return [];
	}

	/***
	 * getPriceInUsd() - This method is to usd price of coin
	 ***/
	protected function getPriceInUsd($coin_symbol)
	{
		// load coinremitter library
		$this->load->model('extension/coinremitter/payment/coinremitter_api');
		$obj_curl = $this->model_extension_coinremitter_payment_coinremitter_api->get_instance($this->registry);

		$endPoint = '/rate/supported-currency';

		$api_response = $obj_curl->apiCall($endPoint);
		if ($api_response) {
			if (!$api_response['success']) {
				$this->error['warning'] = $api_response['msg'];
				return !$this->error;
			}
			$allCoins = $api_response['data'];
			$priceInUsd = 0;
			foreach ($allCoins as $coin) {
				if ($coin['coin_symbol'] == $coin_symbol) {
					$priceInUsd = $coin['price_in_usd'];
					break;
				}
			}
			return $priceInUsd;
		}
		return 0;
	}

	/*** validateDelete() - This method is to check if the user has permission to delete or not ***/
	protected function validateDelete()
	{
		if (!$this->user->hasPermission('modify', 'extension/coinremitter/module/coinremitter')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		return !$this->error;
	}

	/*** view to not found page ***/
	public function pagenotfound()
	{

		$this->load->language('extension/coinremitter/module/coinremitter');
		$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/coinremitter/module/coinremitter', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['text_not_found'] = 'Opps! There is some problem occured. Please check your internet and api url or try again later. ';
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('error/not_found', $data));
	}

	/*** uninstall() - This method will be called on uninstall coinremitter extension and it will remove permission from oc_user_group table. This method is automatically called when user uninstall coinremitter wallet module from  modules extensions***/
	public function uninstall()
	{
		$this->load->model('user/user_group');
		$this->model_user_user_group->removePermission($this->user->getGroupId(), 'access', 'extension/coinremitter/module/coinremitter');
		$this->model_user_user_group->removePermission($this->user->getGroupId(), 'modify', 'extension/coinremitter/module/coinremitter');

		$this->load->model('setting/extension');
		$this->model_setting_extension->uninstall('module', 'coinremitter');

		$this->load->model('setting/module');
		$this->model_setting_module->deleteModulesByCode('coinremitter');

		$this->load->model('setting/event');
		$this->model_setting_event->deleteEventByCode('coinremitter');
		$this->model_setting_event->deleteEventByCode('coinremitter_admin_order_list');
		$this->model_setting_event->deleteEventByCode('coinremitter_admin_order_detail');
		$this->model_setting_event->deleteEventByCode('coinremitter_user_order_list');
		$this->model_setting_event->deleteEventByCode('coinremitter_user_order_detail');
	}


	/*** uninstall_module will uninstall coinremitter module also which contains wallets stuff and which is called from controller->extension->payment->coinremitter->uninstall(). We are uninstalling here because users dont need to uninstall manually. This method is automatically called when user uninstall coinremitter payment module from payment extensions. This method is different from above uninstall()  ***/
	public function uninstall_module()
	{

		/*** Remove permission of coinremitter module extension ***/
		$this->load->model('user/user_group');
		$this->model_user_user_group->removePermission($this->user->getGroupId(), 'access', 'extension/coinremitter/module/coinremitter');
		$this->model_user_user_group->removePermission($this->user->getGroupId(), 'modify', 'extension/coinremitter/module/coinremitter');
	}

	public function before_install(&$route, &$data, &$code)
	{
		exit("sdf");
	}
}

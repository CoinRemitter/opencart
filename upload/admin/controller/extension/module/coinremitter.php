<?php 

/*** We create a file named coinremitter.php’ in the admin/controller/extension/module/ folder. Since we named the file coinremitter.php and put it at admin/controller/extension/module/ folder, the controller class name will be ControllerExtensionModuleCoinremitter which inherits the Controller. ***/
class ControllerExtensionModuleCoinremitter extends Controller {
	
	/*** private error property for this class only which will hold the error message if occurs any. ***/
	private $error = array();

	/*** install_module will install this module also which contains wallets stuff.This is called from controller->extension->payment->coinremitter->install(). We are installing here because users dont need to install manually. This method is automatically called when user install coinremitter payment module from payment extensions. This method is different from above this page's install() (if defined) ***/
	public function install_module(){

		$this->load->model('setting/extension');
		$this->model_setting_extension->install('module','coinremitter');

		/*** add permission for users ***/
		$this->load->model('user/user_group');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/module/coinremitter');
		$this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/module/coinremitter');
	}

	/*** The index method is default method, it is called whenever the main controller ControllerExtensionModuleCoinremitter is called through route URL, like http://opencart.loc/admin/index.php?route=extension/module/coinremitter&user_token=5XdZM31DgqUkg4uEmrInmL3pp7uiaYUr.
	 * Here the language file is loaded
	 * Then Document title is set
	 * Then model file is loaded
	 * Then protected method getList is called which list out all the wallets. Thus default page is the listing page because we called getList in index() method. ***/
	public function index(){

		$this->load->language('extension/module/coinremitter');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/coinremitter');
		$this->getList();
	}

	/*** add() - This method is called when someone clicks the add button in the listing page and the save button on the form. If the add button is clicked then it shows the forms with blank fields. If the save button is clicked on the form then it validates the data and saves data in the database and redirects to the listing page. ***/
	public function add()
	{
		$this->load->language('extension/module/coinremitter');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/coinremitter');
		/*** This is the section when someone clicks save button while adding the wallet. It checks if the request method is post and if form is validated. Then it will call the addWallet method of model class which save the new wallet to the database ***/
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()){

			/*** check if api_key and password is valid for selected coin ***/
			$add_fields = $this->request->post;
			$ccRes = $this->checkCredentials($add_fields);

			if($ccRes){

				$add_fields['balance'] = $ccRes['balance']; 
				$add_fields['name'] = $ccRes['wallet_name']; 
				$add_fields['coin_name'] = $ccRes['coin_name']; 

				$this->model_extension_module_coinremitter->addWallet($add_fields);
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
				$this->response->redirect($this->url->link('extension/module/coinremitter', 'user_token=' . $this->session->data['user_token'] . $url, true));
			}
			
		}
		/*** This is to show the form ***/
		$this->getForm();
	}

	/*** edit() - Edit method is called when someone clicks the edit button in the listing page of the wallet which will show the form with the data, and similarly it is called when someone clicks the save button on the form while editing, when saved it will validate the form and update the data in the database and redirects to the listing page. ***/
	public function edit()
	{
		$this->load->language('extension/module/coinremitter');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/coinremitter');
		/*** This is the section when someone clicks edit button and save the wallet. It checks if the request method is post and if form is validated. Then it will call the editWallet method of model class which save the updated testimonial to the database ***/
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm()){

			/*** check if api_key and password is valid for selected coin ***/
			$edit_fields = $this->request->post;
			$ccRes = $this->checkCredentials($edit_fields);

			if($ccRes){

				$edit_fields['balance'] = $ccRes['balance']; 
				$edit_fields['name'] = $ccRes['wallet_name']; 
				$edit_fields['coin_name'] = $ccRes['coin_name']; 

				$this->model_extension_module_coinremitter->editWallet($this->request->get['id'], $edit_fields);
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
				$this->response->redirect($this->url->link('extension/module/coinremitter', 'user_token=' . $this->session->data['user_token'] . $url, true));
			}

		}
		/*** This is to show the form ***/
		$this->getForm();
	}


	/*** refresh() - refresh method is called when someone clicks the refresh button in the listing page of the wallet which will refresh the all wallets balances with api call and update the balance in their respective wallet and redirects to the listing page. ***/
	public function refresh()
	{	
		$this->load->language('extension/module/coinremitter');
		$this->load->model('extension/module/coinremitter');

		/*** Get all wallet list ***/
		$allWallets = $this->model_extension_module_coinremitter->getAllWallets();

		$is_any_error = 0;
		foreach ($allWallets as $wallet) {
			$ccRes = $this->checkCredentials($wallet);

			if ($ccRes) {

				$edit_fields = array();
				$edit_fields['balance'] = $ccRes['balance']; 
				$edit_fields['name'] = $ccRes['wallet_name']; 
				$edit_fields['coin_name'] = $ccRes['coin_name']; 
				$edit_fields['api_key'] = $wallet['api_key']; 
				$edit_fields['password'] = $wallet['password']; 

				$this->model_extension_module_coinremitter->editWallet($wallet['id'], $edit_fields);
			}else{
				$is_any_error = 1;
			}
		}
		if($is_any_error == 0){
			$this->session->data['success'] = $this->language->get('text_success');
		}else{
			$this->error['warning'] = 'There are some '.$this->error['warning']; 
		}

		$this->getList();
	}

	/*** delete() - Delete method is called when someone clicks delete button by selecting the wallet to delete. Once wallet/s is/are deleted then it is redirected to the listing page.***/
	public function delete()
	{
		$this->load->language('extension/module/coinremitter');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('extension/module/coinremitter');
		/*** This is the section which find which wallets are selected that need to be deleted. The deleteWallet method of the model class is called which remove the wallet from the database ***/
		if (isset($this->request->post['selected']) && $this->validateDelete()) {
			foreach ($this->request->post['selected'] as $id) {
				$this->model_extension_module_coinremitter->deleteWallet($id);
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
			$this->response->redirect($this->url->link('extension/module/coinremitter', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}
		$this->getList();
	}

	/*** getList() - This method creates logic to create a listing and pass variables to template twig files where they are manipulated and shown in the table.
	the listing page will look like in the image url https://webocreation.com/blog/wp-content/uploads/2019/09/testimonial-listings.jpg  ***/
	protected function getList() {

		if (isset($this->request->get['sort'])) {
			$sort = $this->request->get['sort'];
		} else {
			$sort = 'coin';
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
			'href' => $this->url->link('extension/module/coinremitter', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);
		/*** Add, Refresh and delete button URL setup for the form ***/
		$data['add'] = $this->url->link('extension/module/coinremitter/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['refresh'] = $this->url->link('extension/module/coinremitter/refresh', 'user_token=' . $this->session->data['user_token'] . $url, true);
		$data['delete'] = $this->url->link('extension/module/coinremitter/delete', 'user_token=' . $this->session->data['user_token'] . $url, true);
		/*** wallets variables is set to empty array, latter we will set the wallets in it ***/
		$data['wallets'] = array();
		/*** We set filter_data like below, $sort, $order, $page are assigned in above code, we can get from the URL paramaters or the config values. We pass this array and in model the SQL will be create as per this filter data   ***/
		$filter_data = array(
			'sort'  => $sort,
			'order' => $order,
			'start' => ($page - 1) * $this->config->get('config_limit_admin'),
			'limit' => $this->config->get('config_limit_admin')
		);
		/*** This is to get the total of number of wallets as this is needed for the pagination ***/
		$wallets_total = $this->model_extension_module_coinremitter->getTotalWallets();
		/*** This is to get filtered wallets ***/
		$results = $this->model_extension_module_coinremitter->getWallets($filter_data);
		/*** This is how we set data to the wallets array, we can get many variables in the $results variables so we separate what is needed in template twig file and pass them to it ***/
		$imgArr = array(
			'BCH' => 'view/image/coinremitter/BCH.png',
			'BTC' => 'view/image/coinremitter/BTC.png',
			'BTG' => 'view/image/coinremitter/BTG.png',
			'DOGE' => 'view/image/coinremitter/DOGE.png',
			'KOIN' => 'view/image/coinremitter/KOIN.png',
			'LTC' => 'view/image/coinremitter/LTC.png',
			'TCN' => 'view/image/coinremitter/TCN.png',
			'USDT' => 'view/image/coinremitter/USDT.png',
			'XRP' => 'view/image/coinremitter/XRP.png'
		);
		foreach ($results as $result) {
			$logo = '';
			if( array_key_exists($result['coin'], $imgArr)){
				$logo = $imgArr[$result['coin']];
			}
			$data['wallets'][] = array(
				'id' => $result['id'],
				'logo' 		=> $logo,
				'coin'      => $result['coin'],
				'coin_name' => $result['coin_name'],
				'wallet_name' => $result['name'],
				'balance' => $result['balance'],
				'created_at' => date('d-m-Y H:i:s', strtotime($result['created_at'])),
				'edit'        => $this->url->link('extension/module/coinremitter/edit', 'user_token=' . $this->session->data['user_token'] . '&id=' . $result['id'] . $url, true),
				'delete'      => $this->url->link('extension/module/coinremitter/delete', 'user_token=' . $this->session->data['user_token'] . '&id=' . $result['id'] . $url, true)
			);
		}
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
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
		$data['coin'] = $this->url->link('extension/module/coinremitter', 'user_token=' . $this->session->data['user_token'] . '&sort=coin' . $url, true);
		$url = '';
		if (isset($this->request->get['sort'])) {
			$url .= '&sort=' . $this->request->get['sort'];
		}
		if (isset($this->request->get['order'])) {
			$url .= '&order=' . $this->request->get['order'];
		}
		/*** Pagination in Opencart they are self explainatory ***/
		$pagination = new Pagination();
		$pagination->total = $wallets_total;
		$pagination->page = $page;
		$pagination->limit = $this->config->get('config_limit_admin');
		$pagination->url = $this->url->link('extension/module/coinremitter', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);
		$data['pagination'] = $pagination->render();
		$data['results'] = sprintf($this->language->get('text_pagination'), ($wallets_total) ? (($page - 1) * $this->config->get('config_limit_admin')) + 1 : 0, ((($page - 1) * $this->config->get('config_limit_admin')) > ($wallets_total - $this->config->get('config_limit_admin'))) ? $wallets_total : ((($page - 1) * $this->config->get('config_limit_admin')) + $this->config->get('config_limit_admin')), $wallets_total, ceil($wallets_total / $this->config->get('config_limit_admin')));
		$data['sort'] = $sort;
		$data['order'] = $order;
		/*** Pass the header, column_left and footer to the coinremitter_list.twig template ***/
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		/*** Set the response output ***/
		$this->response->setOutput($this->load->view('extension/module/coinremitter_list', $data));
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
			'href' => $this->url->link('extension/module/coinremitter', 'user_token=' . $this->session->data['user_token'] . $url, true)
		);
		/*** This is the code which separate the action of edit or add action, if the URL parameter contains id then it is edit else it is add  ***/
		if (!isset($this->request->get['id'])) {
			$data['action'] = $this->url->link('extension/module/coinremitter/add', 'user_token=' . $this->session->data['user_token'] . $url, true);
		} else {
			$data['action'] = $this->url->link('extension/module/coinremitter/edit', 'user_token=' . $this->session->data['user_token'] . '&id=' . $this->request->get['id'] . $url, true);
		}
		$data['cancel'] = $this->url->link('extension/module/coinremitter', 'user_token=' . $this->session->data['user_token'] . $url, true);
		/*** This is the code which pulls the wallet that we have to edit  ***/
		if (isset($this->request->get['id'])) {
			$wallet_info = $this->model_extension_module_coinremitter->getWallet($this->request->get['id']);
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
		
		/*** This is for coin field ***/
		$is_all_coin_needed = FALSE;		
		if (isset($this->request->post['coin']) && !isset($this->request->get['id'])) {
			$data['coin'] = $this->request->post['coin'];
			$is_all_coin_needed = TRUE;		
		} elseif (!empty($wallet_info)) {
			$data['coin'] = $wallet_info['coin'];
		} else {
			$data['coin'] = '';
			$is_all_coin_needed = TRUE;
		}			

		$data['coin_list'] = array();

		if($is_all_coin_needed){
			/*** get all coins list ***/		
			$endPoint = 'get-coin-rate';
			$get_request = TRUE;
			$post_data = array();

			// load coinremitter library
			$this->load->library('coinremitter');
			$obj_curl = Coinremitter::get_instance($this->registry);
			$api_response = $obj_curl->apiCall($endPoint,$post_data,$get_request);
			
			if($api_response){

				//get all coin list from database
				$allWallets = $this->model_extension_module_coinremitter->getAllWallets();

				/*** User can only add one wallet per one coin ***/
				$db_coin_list = array();
				foreach ($allWallets as $allwal) {
					$db_coin_list[] = $allwal['coin'];
				}

				$coin_list = array();
				foreach ($api_response['data'] as $value) {
					if(!in_array($value['symbol'], $db_coin_list)){
						$coin_list[] = $value['symbol']; 
					}
				}
				$data['coin_list'] = $coin_list;	
			}else{
				$this->response->redirect($this->url->link('extension/module/coinremitter/pagenotfound', 'user_token=' . $this->session->data['user_token'] . $url, true));
			}
			
		}
		

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');
		$this->response->setOutput($this->load->view('extension/module/coinremitter_form', $data));
	}

	/***
	 * validateForm() - This method is to check whether the user has permission to edit or add the data from the form. In this method, we can validate any form field if needed.
	 ***/
	protected function validateForm()
	{
		/*** This is how we check if the user has permission to modify or not. ***/
		if (!$this->user->hasPermission('modify', 'extension/module/coinremitter')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		/*** This is to check if the api_key and password is not blank ***/
		if ($this->request->post['api_key'] == '') {
			$this->error['api_key'] = $this->language->get('error_api_key');
		}
		if ($this->request->post['password'] == '') {
			$this->error['password'] = $this->language->get('error_password');
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
		/*** make an api call for wallet balance ***/
		$coin = $data['coin'];
		$api_key = $data['api_key'];
		$password = $data['password'];

		$endPoint = $coin.'/get-balance';
		$params = array('api_key' => $api_key, 'password' => $password ); 

		// load coinremitter library
		$this->load->library('coinremitter');
		$obj_curl = Coinremitter::get_instance($this->registry);
		$api_response = $obj_curl->apiCall($endPoint,$params);

		if($api_response){
			if($api_response['flag'] == 0){
				$this->error['warning'] = $api_response['msg'];
				return !$this->error;	
			}
			return $api_response['data'];
		}else{
			$this->response->redirect($this->url->link('extension/module/coinremitter/pagenotfound', 'user_token=' . $this->session->data['user_token'] . $url, true));
		}
		
	}

	/*** validateDelete() - This method is to check if the user has permission to delete or not ***/
	protected function validateDelete()
	{
		if (!$this->user->hasPermission('modify', 'extension/module/coinremitter')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}
		return !$this->error;
	}

    /*** view to not found page ***/
    public function pagenotfound(){

 		$this->load->language('extension/module/coinremitter');   	
    	$data['breadcrumbs'] = array();
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/module/coinremitter', 'user_token=' . $this->session->data['user_token'], true)
		);
		$data['text_not_found'] = 'Opps! There is some problem occured. Please check your internet and api url or try again later. ';
    	$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('error/not_found',$data));
    }

    /*** uninstall() - This method will be called on uninstall coinremitter extension and it will remove permission from oc_user_group table. This method is automatically called when user uninstall coinremitter wallet module from  modules extensions***/
	public function uninstall() {  
        $this->load->model('user/user_group');
        $this->model_user_user_group->removePermission($this->user->getGroupId(), 'access', 'extension/module/coinremitter');
        $this->model_user_user_group->removePermission($this->user->getGroupId(), 'modify', 'extension/module/coinremitter');
    }


    /*** uninstall_module will uninstall coinremitter module also which contains wallets stuff and which is called from controller->extension->payment->coinremitter->uninstall(). We are uninstalling here because users dont need to uninstall manually. This method is automatically called when user uninstall coinremitter payment module from payment extensions. This method is different from above uninstall()  ***/ 
    public function uninstall_module(){

		/*** Remove permission of coinremitter module extension ***/
		$this->load->model('user/user_group');
		$this->model_user_user_group->removePermission($this->user->getGroupId(), 'access', 'extension/module/coinremitter');
        $this->model_user_user_group->removePermission($this->user->getGroupId(), 'modify', 'extension/module/coinremitter');

        $this->load->model('setting/extension');
		$this->model_setting_extension->uninstall('module', 'coinremitter');

		$this->load->model('setting/module');
		$this->model_setting_module->deleteModulesByCode('coinremitter');
    }
}
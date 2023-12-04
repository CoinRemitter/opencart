<?php
class ControllerExtensionPaymentCoinremitter extends Controller {
	private $error = array();
    public function install() {

    	/*** Insert default setting for coinremitter extension ***/
    	$this->load->model('setting/setting');
    	$default_settings = array(
    		'payment_coinremitter_title' => 'Payment with cryptocurrency',
    		'payment_coinremitter_status' => 1,
    		'payment_coinremitter_order_status' => 1,
    		'payment_coinremitter_invoice_expiry' => 60,
    		// 'payment_coinremitter_exchange_rate' => 1.00
    	);
		$this->model_setting_setting->editSetting('payment_coinremitter', $default_settings);

		/*** Create default tables that coinremitter required   ***/
        $this->load->model('extension/payment/coinremitter');
        $this->model_extension_payment_coinremitter->install();

		/*** Call install_module method to install coinremitter wallet module ***/
		$this->load->controller('extension/module/coinremitter/install_module');

    }

    /*** index() : This will call when user click edit after install coinremitter plugin ***/
	public function index() {
        
		$this->load->language('extension/payment/coinremitter');

		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');
		
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			
			$this->model_setting_setting->editSetting('payment_coinremitter', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true));
		}
		
		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['payment_coinremitter_title'])) {
			$data['error_title'] = $this->error['payment_coinremitter_title'];
		} else {
			$data['error_title'] = '';
		}

		// if (isset($this->error['payment_coinremitter_exchange_rate'])) {
		// 	$data['error_exchange_rate'] = $this->error['payment_coinremitter_exchange_rate'];
		// } else {
		// 	$data['error_exchange_rate'] = '';
		// } remove this statement

		if (isset($this->error['payment_coinremitter_invoice_expiry'])) {
			$data['error_invoice_expiry'] = $this->error['payment_coinremitter_invoice_expiry'];
		} else {
			$data['error_invoice_expiry'] = '';
		}
		
		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/payment/coinremitter', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['action'] = $this->url->link('extension/payment/coinremitter', 'user_token=' . $this->session->data['user_token'], true);

		$data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment', true);

		if (isset($this->request->post['payment_coinremitter_title'])) {
			$data['payment_coinremitter_title'] = $this->request->post['payment_coinremitter_title'];
		} else {
			$data['payment_coinremitter_title'] = $this->config->get('payment_coinremitter_title');
		}
		
		if (isset($this->request->post['payment_coinremitter_description'])) {
			$data['payment_coinremitter_description'] = $this->request->post['payment_coinremitter_description'];
		} 	else {
			$data['payment_coinremitter_description'] = $this->config->get('payment_coinremitter_description');
		}

	
		if (isset($this->request->post['payment_coinremitter_exchange_rate'])) {
			$data['payment_coinremitter_exchange_rate'] = $this->request->post['payment_coinremitter_exchange_rate'];
		} else {
			$data['payment_coinremitter_exchange_rate'] = $this->config->get('payment_coinremitter_exchange_rate');
		}


		if (isset($this->request->post['payment_coinremitter_invoice_expiry'])) {
			$data['payment_coinremitter_invoice_expiry'] = $this->request->post['payment_coinremitter_invoice_expiry'];
		} else {
			$data['payment_coinremitter_invoice_expiry'] = $this->config->get('payment_coinremitter_invoice_expiry');
		}

		if (isset($this->request->post['payment_coinremitter_order_status'])) {
			$data['payment_coinremitter_order_status'] = $this->request->post['payment_coinremitter_order_status'];
		} else {
			$data['payment_coinremitter_order_status'] = $this->config->get('payment_coinremitter_order_status');
		}

		if (isset($this->request->post['payment_coinremitter_status'])) {
			$data['payment_coinremitter_status'] = $this->request->post['payment_coinremitter_status'];
		} else {
			$data['payment_coinremitter_status'] = $this->config->get('payment_coinremitter_status');
		}

		
		#echo '<pre>';
		#print_r($data);
		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/coinremitter', $data));
	}

	protected function validate() {

		if (!$this->user->hasPermission('modify', 'extension/payment/coinremitter')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ($this->request->post['payment_coinremitter_title'] == '') {
			$this->error['payment_coinremitter_title'] = $this->language->get('error_title');
		}

		// if ($this->request->post['payment_coinremitter_exchange_rate'] == '' ) {
		// 	$this->error['payment_coinremitter_exchange_rate'] = $this->language->get('error_exchange_rate_required');
		// }		

		// if (!is_numeric($this->request->post['payment_coinremitter_exchange_rate'])) {
		// 	$this->error['payment_coinremitter_exchange_rate'] = $this->language->get('error_exchange_rate_numeric');
		// } remove this statement

		// if ($this->request->post['payment_coinremitter_exchange_rate'] < 1 || $this->request->post['payment_coinremitter_exchange_rate'] > 100 ) {
		// 	$this->error['payment_coinremitter_exchange_rate'] = $this->language->get('error_exchange_rate');
		// }

		if ($this->request->post['payment_coinremitter_invoice_expiry'] == '' || $this->request->post['payment_coinremitter_invoice_expiry'] < 0 ) {
			$this->error['payment_coinremitter_invoice_expiry'] = $this->language->get('error_invoice_expiry');
		}

		if ($this->request->post['payment_coinremitter_invoice_expiry'] > 10080 ) {
			$this->error['payment_coinremitter_invoice_expiry'] = $this->language->get('error_invoice_expiry_max');
		}

		if(!preg_match('/^[0-9]*$/', $this->request->post['payment_coinremitter_invoice_expiry'])){
			$this->error['payment_coinremitter_invoice_expiry'] = $this->language->get('error_invoice_expiry_int');	
		}
		return !$this->error;
	}

	public function uninstall()
	{	
		/*** Delete default settings for coinremitter extension ***/
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('payment_coinremitter');

		/*** Drop default tables that coinremitter has created   ***/
		$this->load->model('extension/payment/coinremitter');
        $this->model_extension_payment_coinremitter->uninstall();

        /*** Remove permission of coinremitter payment extension ***/
        $this->load->model('user/user_group');
        $this->model_user_user_group->removePermission($this->user->getGroupId(), 'access', 'extension/payment/coinremitter');
        $this->model_user_user_group->removePermission($this->user->getGroupId(), 'modify', 'extension/payment/coinremitter');

		/*** Call uninstall_module method to uninstall coinremitter wallet module ***/
		$this->load->controller('extension/module/coinremitter/uninstall_module');

	}
}

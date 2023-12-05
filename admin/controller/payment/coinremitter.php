<?php
namespace Opencart\Admin\Controller\Extension\Coinremitter\Payment;
class Coinremitter extends \Opencart\System\Engine\Controller {
	private $error = array();
    /*** index() : This will call when user click edit after install coinremitter plugin ***/
	public function index(): void {

		$this->load->language('extension/coinremitter/payment/coinremitter');

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

		if (isset($this->error['payment_coinremitter_title_length'])) {
			$data['error_title_length'] = $this->error['payment_coinremitter_title_length'];
		} else {
			$data['error_title_length'] = '';
		}

		if (isset($this->error['payment_coinremitter_invoice_expiry'])) {
			$data['error_invoice_expiry'] = $this->error['payment_coinremitter_invoice_expiry'];
		} else {
			$data['error_invoice_expiry'] = '';
		}
		
		$data['breadcrumbs'] = [];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment')
		];

		$data['breadcrumbs'][] = [
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/coinremitter/payment/coinremitter', 'user_token=' . $this->session->data['user_token'])
		];

		$data['save'] = $this->url->link('extension/coinremitter/payment/coinremitter|save', 'user_token=' . $this->session->data['user_token']);

		$data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=payment');

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

		$data['payment_free_checkout_order_status_id'] = $this->config->get('payment_free_checkout_order_status_id');

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$data['payment_free_checkout_status'] = $this->config->get('payment_free_checkout_status');
		$data['payment_free_checkout_sort_order'] = $this->config->get('payment_free_checkout_sort_order');

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

	

		$this->response->setOutput($this->load->view('extension/coinremitter/payment/coinremitter', $data));
	}
	
	protected function validate() {

		if (!$this->user->hasPermission('modify', 'extension/coinremitter/payment/coinremitter')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if ($this->request->post['payment_coinremitter_title'] == '') {
			$this->error['payment_coinremitter_title'] = $this->language->get('error_title');
		}
		if (strlen($this->request->post['payment_coinremitter_title']) > 50) {
			$this->error['payment_coinremitter_title_length'] = $this->language->get('error_title_length');
		}

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

	public function install(): void {

		// $this->load->model('extension/opencart/fraud/ip');
		// $this->model_extension_opencart_fraud_ip->install();
		// echo 'Pyamnet Installation'; 
		// die;
		
		$this->load->model('extension/coinremitter/payment/coinremitter');
        $this->model_extension_coinremitter_payment_coinremitter->install();

		$json = [];

		if (!$json) {
			$this->load->model('setting/setting');

			$this->model_setting_setting->editSetting('payment_coinremitter', $this->request->post);

			$json['success'] = $this->language->get('text_success');

			   	$default_settings = array(
		    		'payment_coinremitter_title' => 'Payment with cryptocurrency',
		    		'payment_coinremitter_status' => 1,
		    		'payment_coinremitter_order_status' => 1,
		    		'payment_coinremitter_invoice_expiry' => 60,
		    	);
			$this->model_setting_setting->editSetting('payment_coinremitter', $default_settings);
		}
		
	}

	public function uninstall(): void
	{	
		/*** Delete default settings for coinremitter extension ***/
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('payment_coinremitter');

		/*** Drop default tables that coinremitter has created   ***/
		$this->load->model('extension/coinremitter/payment/coinremitter');
        $this->model_extension_coinremitter_payment_coinremitter->uninstall();

        /*** Remove permission of coinremitter payment extension ***/
        $this->load->model('user/user_group');
        $this->model_user_user_group->removePermission($this->user->getGroupId(), 'access', 'extension/coinremitter/payment/coinremitter');
        $this->model_user_user_group->removePermission($this->user->getGroupId(), 'modify', 'extension/coinremitter/payment/coinremitter');

		/*** Call uninstall_module method to uninstall coinremitter wallet module ***/
		$this->load->controller('extension/coinremitter/module/coinremitter/uninstall_module');

	}
}

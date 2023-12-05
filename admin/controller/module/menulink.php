<?php
namespace Opencart\Admin\Controller\Extension\coinremitter\Module;

class MenuLink extends \Opencart\System\Engine\Controller
{
    public function index(): void
    {   
        $this->load->language('extension/coinremitter/module/menulink');

        $this->document->setTitle($this->language->get('heading_title'));

        $data['breadcrumbs'] = [];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token']),
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module'),
        ];
        $data['breadcrumbs'][] = [
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/coinremitter/module/menulink', 'user_token=' . $this->session->data['user_token']),
        ];

        $data['save'] = $this->url->link('extension/coinremitter/module/menulink|save', 'user_token=' . $this->session->data['user_token']);
        $data['back'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module');

        $data['module_coinremitter_status'] = $this->config->get('module_coinremitter_status');
       
        $data['success'] = '';
        if (!empty($this->session->data['module_coinremitter_success'])) {
            $data['success'] = $this->session->data['module_coinremitter_success'];
            unset($this->session->data['module_coinremitter_success']);
        }
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/coinremitter/module/menulink', $data));

        $this->load->model('user/user_group');
        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'access', 'extension/coinremitter/module/coinremitter');
        $this->model_user_user_group->addPermission($this->user->getGroupId(), 'modify', 'extension/coinremitter/module/coinremitter');
    }

    public function save(): void
    {
        $this->load->language('extension/coinremitter/module/menulink');

        $json = [];

        if (!$this->user->hasPermission('modify', 'extension/coinremitter/module/menulink')) {
            $json['error'] = $this->language->get('error_permission');
        }

        if (!$json) {
            $this->load->model('setting/setting');

            $this->model_setting_setting->editSetting('module_coinremitter', $this->request->post);

            $json['redirect'] = str_replace('&amp;', '&', $this->url->link('extension/coinremitter/module/menulink', 'user_token=' . $this->session->data['user_token']));
            $this->session->data['module_coinremitter_success'] = $this->language->get('text_success');
        }

        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($json));
    }

    public function install(): void
    {
        // add events
        $this->load->model('setting/event');
        if (version_compare(VERSION, '4.0.1.1', '>')) {
            $data = [
                'code' => 'module_coinremitter',
                'description' => '',
                'trigger' => 'admin/view/common/column_left/before',
                'action' => 'extension/coinremitter/module/menulink.eventViewCommonColumnLeftBefore',
                'status' => true,
                'sort_order' => 0,
            ];
            $this->model_setting_event->addEvent($data);
        } else if (version_compare(VERSION, '4.0.1.0', '>=')) {
            $data = [
                'code' => 'module_coinremitter',
                'description' => '',
                'trigger' => 'admin/view/common/column_left/before',
                'action' => 'extension/coinremitter/module/menulink|eventViewCommonColumnLeftBefore',
                'status' => true,
                'sort_order' => 0,
            ];
            $this->model_setting_event->addEvent($data);
        } else {
            $this->model_setting_event->addEvent('module_coinremitter', '', 'admin/view/common/column_left/before', 'extension/coinremitter/module/menulink|eventViewCommonColumnLeftBefore');
        }      
    }

    public function uninstall(): void
    {
        // remove events
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('module_coinremitter');
    }

    public function eventViewCommonColumnLeftBefore(&$route, &$data, &$code)
    {
        
        if (!$this->config->get('module_coinremitter_status')) {
            return null;
        }

        $this->load->language('extension/coinremitter/module/menulink');
        $text_coinremitter = $this->language->get('menu_coinremitter');

        $data['menus'][] = array(
        'id'       => 'menu-coinremitter',
        'icon'     => '', 
        'name'     => '<img src="../extension/coinremitter/admin/view/image/coinremitter/crlogo.png" style="margin-left:-9px; margin-right:4px; margin-top:-3px;" /> Coinremitter',
        'href'     => $this->url->link('extension/coinremitter/module/coinremitter', 'user_token=' . $this->session->data['user_token'], true),
        'children' => array()
    );
        return null;
    }
}
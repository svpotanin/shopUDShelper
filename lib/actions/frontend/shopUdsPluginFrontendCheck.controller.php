<?php

class shopUdsPluginFrontendCheckController extends waJsonController
{

  public function execute()
  {
    $settings = $this->getSettings();
    $uds_helper = new shopUdsHelper();
    $code = waRequest::get('code');
    $type = waRequest::get('type');
    $action = waRequest::get('action');
    wa()->getStorage()->set('shop/udsdiscount', '');
    wa()->getStorage()->set('shop/udspoints', '');
    wa()->getStorage()->set('shop/udsdiscount/user', '');
    if ($action == 'check') {
      if ($code && $type) {
        $this->response = $uds_helper->customerFind(array('code' => $code, 'type' => $type));
      } else {
        $this->response = array('status' => 'error', 'message' => 'Произошла ошибка. Пожалуйста, попробуйте еще раз!');
      }
    } else if ($action == 'cancel') {
      wa()->getStorage()->set('shop/udsdiscount', '');
      wa()->getStorage()->set('shop/udspoints', '');
      wa()->getStorage()->set('shop/udsdiscount/user', '');
    } else {
      $this->response = array('status' => 'error', 'message' => 'Произошла ошибка. Пожалуйста, попробуйте еще раз!');
    }
  }

  private function getSettings()
  {
    $plugin = wa('shop')->getPlugin('uds');
    $settings = $plugin->getSettings();
    return $settings;
  }
}

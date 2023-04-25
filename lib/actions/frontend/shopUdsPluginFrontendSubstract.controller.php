<?php

class shopUdsPluginFrontendSubstractController extends waJsonController
{

  public function execute()
  {
    $settings = $this->getSettings();
    $uds_helper = new shopUdsHelper();
    $code = waRequest::get('code');
    $type = waRequest::get('type');
    $points = waRequest::get('points');
    $action = waRequest::get('action');

    if ($action == 'substract') {
      if ($code && $type) {
        $check = $uds_helper->customerFind(array('code' => $code, 'type' => $type));
        if ($check['status'] == 'ok') {
          $user_points = $check['user']['user_maxpoints'];
          if ($points > $user_points) {
            $this->response = array('status' => 'error', 'message' => 'Вы пытаетесь применить больше баллов, которые у вас есть. На данный момент у вас ' . $user_points . ' доступные баллы.!');
          } else {
            if ($points && $points > 0) {
              if($type != 'uid'){
              wa()->getStorage()->set('shop/udspoints', $points);
              $this->response = array('status' => 'success', 'message' => '');
              }
            } else {
              $this->response = array('status' => 'error', 'message' => 'Указано неверное количество баллов.');
            }
          }
        } else {
          $this->response = array('status' => 'error', 'message' => 'Произошла ошибка. Пожалуйста, попробуйте еще раз!');
        }
      } else {
        $this->response = array('status' => 'error', 'message' => 'Произошла ошибка. Пожалуйста, попробуйте еще раз!');
      }
    } else if ($action == 'cancel') {
      wa()->getStorage()->set('shop/udspoints', '');
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

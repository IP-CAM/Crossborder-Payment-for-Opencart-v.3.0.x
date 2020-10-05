<?php

	abstract class Opencart_Globepay_Helper extends Controller
	{
		public $license_id = 'globepay-for-opencart';
		public $is_authoirzed = false;
		abstract function get_notify_url();

		public function http_to_https($client_url)
		{
			$isSecure = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443 ? true : false;

			if (!$isSecure) {
				return $client_url;
			}

			if (strpos($client_url, 'https://') === 0) {
				return $client_url;
			}

			if (strpos($client_url, 'http://') === 0) {
				return 'https://' . substr($client_url, 8);
			}

			return $client_url;
		}

		public function m1($order_info,$order_id)
		{
			$http_host = $_SERVER['HTTP_HOST'];
			$http_host = strtolower($http_host);
			if (strpos($http_host, 'http://') === 0) {
				$http_host = substr($http_host, 7);
			} else {
				if (strpos($http_host, 'https://') === 0) {
					$http_host = substr($http_host, 8);
				}
			}
			if (strpos($http_host, '/') !== false) {
				$new_http_host = explode('/', $http_host);
				$http_host = $new_http_host[0];
			}

				$partner_code = $this->config->get('payment_globepay_account');
				$time = time() . '000';
				$nonce_str = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 10);
				$credential_code = $this->config->get('payment_globepay_secret');
				$valid_string = "{$partner_code}&{$time}&{$nonce_str}&{$credential_code}";
				$sign = strtolower(hash('sha256', $valid_string));
				$new_order_id = date('ymdHis') . '-' . $order_id;
				$this->session->data['new_order_id'] = $new_order_id;
				if (!$this->is_app_client()) {
					$url = "https://pay.globepay.co/api/v1.0/gateway/partners/{$partner_code}/orders/{$new_order_id}";
				} else {
					$url = "https://pay.globepay.co/api/v1.0/h5_payment/partners/{$partner_code}/orders/{$new_order_id}";
				}
				$url .= "?time={$time}&nonce_str={$nonce_str}&sign={$sign}";
				$head_arr = array();
				$head_arr[] = 'Content-Type: application/json';
				$head_arr[] = 'Accept: application/json';
				$head_arr[] = 'Accept-Language: ' . $this->session->data['language'];
				$json = new stdClass();
				$json->description = $this->get_order_title($order_id);
				$json->price = (int) (round(floatval($this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false)), 2) * 100);
				$json->currency = $order_info['currency_code'];
				$json->notify_url = $this->get_notify_url();
				$json->channel = "Alipay";
				$json = json_encode($json);
				$ch = curl_init();
				curl_setopt($ch, CURLOPT_URL, $url);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_PUT, true);
				curl_setopt($ch, CURLOPT_HTTPHEADER, $head_arr);
				$temp_pointer = tmpfile();
				fwrite($temp_pointer, $json);
				fseek($temp_pointer, 0);
				curl_setopt($ch, CURLOPT_INFILE, $temp_pointer);
				curl_setopt($ch, CURLOPT_INFILESIZE, strlen($json));
				curl_setopt($ch, CURLOPT_TIMEOUT, 120);
				$result = curl_exec($ch);
				$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
				$error_msg = curl_error($ch);
				curl_close($ch);
				if ($http_code != 200) {
					throw new Exception("invalid httpstatus:{$http_code} ,response:{$result},detail_error:" . $error_msg, $http_code);
				}
				$resArr = $result;
				if ($temp_pointer) {
					fclose($temp_pointer);
					unset($temp_pointer);
				}
				return json_decode($resArr, false);
		}

		public function m2(&$payment_id)
		{
			$post_data = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
			if (empty($post_data)) {
				$post_data = file_get_contents('php://input');
			}
			if (empty($post_data)) {
				print json_encode(array('return_code' => 'FAIL'));
				exit;
			}
			$json = json_decode($post_data, false);
			if (!$json) {
				print json_encode(array('return_code' => 'FAIL'));
				exit;
			}
			$partner_code = $this->config->get('payment_globepay_account');
			$credential_code = $this->config->get('payment_globepay_secret');
			$time = $json->{'time'};
			$nonce_str = $json->{'nonce_str'};
			$valid_string = "{$partner_code}&{$time}&{$nonce_str}&{$credential_code}";
			$sign = strtolower(hash('sha256', $valid_string));
			if ($sign != $json->{'sign'}) {
				print json_encode(array('return_code' => 'FAIL'));
				exit;
			}
			$order_id = $json->{'partner_order_id'};
			$get_payment = explode('-', $order_id);
			if (count($get_payment) != 2) {
				print json_encode(array('return_code' => 'FAIL'));
				exit;
			}
			$payment_id = $get_payment[1];
			$url = "https://pay.globepay.co/api/v1.0/gateway/partners/{$partner_code}/orders/{$order_id}";
			$time = time() . '000';
			$nonce_str = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 10);
			$valid_string = "{$partner_code}&{$time}&{$nonce_str}&{$credential_code}";
			$sign = strtolower(hash('sha256', $valid_string));
			$url .= "?time={$time}&nonce_str={$nonce_str}&sign={$sign}";
			$head_arr = array();
			$head_arr[] = 'Accept: application/json';
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_POST, false);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $head_arr);
			curl_setopt($ch, CURLOPT_TIMEOUT, 120);
			$result = curl_exec($ch);
			curl_close($ch);
			$json_result = json_decode($result, false);
			if (!$json_result) {
				print json_encode(array('return_code' => 'FAIL'));
				exit;
			}
			if ($json_result->{'result_code'} != 'PAY_SUCCESS') {
				print json_encode(array('return_code' => 'SUCCESS'));
				exit;
			}
			return $json;
		}

		public function m3($order)
		{
			$order_total = (int) ($order['total'] * 100);
			$fee = $order_total;
			$partner_code = $this->config->get('payment_globepay_account');
			$credential_code = $this->config->get('payment_globepay_secret');
			$time = time() . '000';
			$nonce_str = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0, 10);
			$valid_string = "{$partner_code}&{$time}&{$nonce_str}&{$credential_code}";
			$sign = strtolower(hash('sha256', $valid_string));
			$order_id = $order['custom_field'];
			if (empty($order_id) || !in_array($order['payment_code'], array('globepayalipay', 'globepay'))) {
				echo json_encode(array('errcode' => -1, 'errmsg' => '交易ID未找到或当前订单支付网关不是globepay!'));
				exit;
			}
			$refund_id = time();
			$url = "https://pay.globepay.co/api/v1.0/gateway/partners/{$partner_code}/orders/{$order_id}/refunds/{$refund_id}";
			$url .= "?time={$time}&nonce_str={$nonce_str}&sign={$sign}";
			$head_arr = array();
			$head_arr[] = 'Content-Type: application/json';
			$head_arr[] = 'Accept: application/json';
			$head_arr[] = 'Accept-Language: ' . $this->session->data['language'];
			$total = new stdClass();
			$total->fee = $fee;
			$total = json_encode($total);
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_PUT, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $head_arr);
			$temp_pointer = tmpfile();
			fwrite($temp_pointer, $total);
			fseek($temp_pointer, 0);
			curl_setopt($ch, CURLOPT_INFILE, $temp_pointer);
			curl_setopt($ch, CURLOPT_INFILESIZE, strlen($total));
			curl_setopt($ch, CURLOPT_TIMEOUT, 120);
			$result = curl_exec($ch);
			curl_close($ch);
			if ($temp_pointer) {
				fclose($temp_pointer);
				unset($temp_pointer);
			}
			$json_result = json_decode($result, false);
			if (!$json_result || !isset($json_result->result_code)) {
				echo json_encode(array('errcode' => -1, 'errmsg' => $result));
				exit;
			}
			if ($json_result->{'result_code'} != 'SUCCESS') {
				echo json_encode(array('errcode' => -1, 'errmsg' => sprintf('ERROR CODE:%s', empty($json_result->{'result_code'}) ? $json_result->{'return_code'} : $json_result->{'result_code'})));
				exit;
			}
			$order_id = $order['order_id'];
			if (method_exists($this->model_checkout_order, 'confirm')) {
				$order_status = $this->model_checkout_order->getOrder($order_id);
				if ($order_status && $order_status['order_status_id']) {
					$this->model_checkout_order->update($order_id, 11, "Refund ID:{$json_result->refund_id}", true);
				} else {
					$this->model_checkout_order->confirm($order_id, 11, "Refund ID:{$json_result->refund_id}", true);
				}
			} else {
				$this->model_checkout_order->addOrderHistory($order_id, 11, "Refund ID:{$json_result->refund_id}", true);
			}
			echo json_encode(array('errcode' => 0, 'errmsg' => ''));
			exit;
		}

		public function isWeixinClient()
		{
			return strripos($_SERVER['HTTP_USER_AGENT'], 'micromessenger') != false;
		}

		public function is_app_client()
		{
			if (!isset($_SERVER['HTTP_USER_AGENT'])) {
				return false;
			}
			$user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
			if ($user_agent == null || strlen($user_agent) == 0) {
				return false;
			}
			$matches = null;
			preg_match('/(android|bb\\d+|meego).+mobile|avantgo|bada\\/|blackberry|blazer|compal|elaine|fennec|hiptop|iemobile|ip(hone|od)|iris|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\\.(browser|link)|vodafone|wap|windows ce|xda|xiino/', $user_agent, $matches);
			if ($matches && count($matches) > 0) {
				return true;
			}
			if (strlen($user_agent) < 4) {
				return false;
			}
			preg_match('/1207|6310|6590|3gso|4thp|50[1-6]i|770s|802s|a wa|abac|ac(er|oo|s\\-)|ai(ko|rn)|al(av|ca|co)|amoi|an(ex|ny|yw)|aptu|ar(ch|go)|as(te|us)|attw|au(di|\\-m|r |s )|avan|be(ck|ll|nq)|bi(lb|rd)|bl(ac|az)|br(e|v)w|bumb|bw\\-(n|u)|c55\\/|capi|ccwa|cdm\\-|cell|chtm|cldc|cmd\\-|co(mp|nd)|craw|da(it|ll|ng)|dbte|dc\\-s|devi|dica|dmob|do(c|p)o|ds(12|\\-d)|el(49|ai)|em(l2|ul)|er(ic|k0)|esl8|ez([4-7]0|os|wa|ze)|fetc|fly(\\-|_)|g1 u|g560|gene|gf\\-5|g\\-mo|go(\\.w|od)|gr(ad|un)|haie|hcit|hd\\-(m|p|t)|hei\\-|hi(pt|ta)|hp( i|ip)|hs\\-c|ht(c(\\-| |_|a|g|p|s|t)|tp)|hu(aw|tc)|i\\-(20|go|ma)|i230|iac( |\\-|\\/)|ibro|idea|ig01|ikom|im1k|inno|ipaq|iris|ja(t|v)a|jbro|jemu|jigs|kddi|keji|kgt( |\\/)|klon|kpt |kwc\\-|kyo(c|k)|le(no|xi)|lg( g|\\/(k|l|u)|50|54|\\-[a-w])|libw|lynx|m1\\-w|m3ga|m50\\/|ma(te|ui|xo)|mc(01|21|ca)|m\\-cr|me(rc|ri)|mi(o8|oa|ts)|mmef|mo(01|02|bi|de|do|t(\\-| |o|v)|zz)|mt(50|p1|v )|mwbp|mywa|n10[0-2]|n20[2-3]|n30(0|2)|n50(0|2|5)|n7(0(0|1)|10)|ne((c|m)\\-|on|tf|wf|wg|wt)|nok(6|i)|nzph|o2im|op(ti|wv)|oran|owg1|p800|pan(a|d|t)|pdxg|pg(13|\\-([1-8]|c))|phil|pire|pl(ay|uc)|pn\\-2|po(ck|rt|se)|prox|psio|pt\\-g|qa\\-a|qc(07|12|21|32|60|\\-[2-7]|i\\-)|qtek|r380|r600|raks|rim9|ro(ve|zo)|s55\\/|sa(ge|ma|mm|ms|ny|va)|sc(01|h\\-|oo|p\\-)|sdk\\/|se(c(\\-|0|1)|47|mc|nd|ri)|sgh\\-|shar|sie(\\-|m)|sk\\-0|sl(45|id)|sm(al|ar|b3|it|t5)|so(ft|ny)|sp(01|h\\-|v\\-|v )|sy(01|mb)|t2(18|50)|t6(00|10|18)|ta(gt|lk)|tcl\\-|tdg\\-|tel(i|m)|tim\\-|t\\-mo|to(pl|sh)|ts(70|m\\-|m3|m5)|tx\\-9|up(\\.b|g1|si)|utst|v400|v750|veri|vi(rg|te)|vk(40|5[0-3]|\\-v)|vm40|voda|vulc|vx(52|53|60|61|70|80|81|83|85|98)|w3c(\\-| )|webc|whit|wi(g |nc|nw)|wmlb|wonu|x700|yas\\-|your|zeto|zte\\-/', substr($user_agent, 0, 4), $matches);
			if ($matches && count($matches) > 0) {
				return true;
			}
			$is_ipad = '/(ipad|ipad2)/i';
			preg_match($is_ipad, $user_agent, $matches);
			if ($matches && count($matches) > 0) {
				return true;
			}
			return false;
		}
	}

	class Controllerextensionpaymentglobepayalipay extends Opencart_Globepay_Helper {
	var $id='globepayalipay';
	public function get_notify_url()
	{
	    // TODO Auto-generated method stub
	    return $this->url->link('extension/payment/globepayalipay/notify');
	}

	public function index() {
		$this -> load->model('checkout/order');
		$this->load->language('extension/payment/globepayalipay');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
		$data['button_confirm']           = $this->language->get('button_confirm');
		$data['button_loading']           = $this->language->get('button_loading');
		$data['button_back']              = $this->language->get('button_back');

		if ($this->request->get['route'] != 'checkout/guest_step_3') {
			$data['back'] =$this->http_to_https($this->url->link('checkout/checkout'));
		} else {
			$data['back'] =$this->http_to_https($this->url->link('checkout/guest_step_2'));
		}

		$data['payment_submit_url']            = $this->http_to_https($this->url->link('extension/payment/globepayalipay/send','',true));

		return $this->load->view('extension/payment/globepayalipay', $data);
	}

	public function refund(){
	    $this->load->model('checkout/order');
	    $this->language->load('extension/payment/globepayalipay');
	    $request = array(
	        'i'=>isset($_REQUEST['i'])?$_REQUEST['i']:0,
	        'n'=>isset($_REQUEST['n'])?$_REQUEST['n']:0
	    );
	    if(!isset($_REQUEST['h'])||md5(http_build_query($request).'&key='.DB_PASSWORD)!=$_REQUEST['h']){
	        echo json_encode(array(
	            'errcode'=>-1,
	            'errmsg'=>'invalid request!'
	        ));
	        exit;
	    }

	    $result =$this->db->query(
	        "select *
	         from `" . DB_PREFIX . "order`
	         where order_status_id in (15,2,3)
	           and order_id = '{$request['i']}'
	        limit 1;");

	    $order = $result->row;
	    if(empty($order)){
	        echo json_encode(array(
	            'errcode'=>-1,
	            'errmsg'=>'invalid request!'
	        ));
	        exit;
	    }

	    $this->m3($order);
	}


	public function callback(){
	$this->load->model('checkout/order');
	    $this->language->load('extension/payment/globepayalipay');
	    $order_id = $this->session->data['order_id'];
	    if(!$order_id){
	        $this->response->redirect($this->url->link('checkout/success'));
	        exit;
	    }

	    $wait_status = $this->config->get('payment_globepayalipay_order_payWait_status_id');
	    $succeed_status = $this->config->get('payment_globepayalipay_order_succeed_status_id');
	    $result =$this->db->query(
	       "select *
	        from `" . DB_PREFIX . "order`
	        where order_id = '$order_id'
	             and order_status_id='$wait_status'
	        limit 1;");

	    if($result->num_rows){
	        //再次检查下是否已支付
	        $partner_code = $this->config->get('payment_globepayalipay_account');
	        $credential_code = $this->config->get('payment_globepayalipay_secret');

	        $payment_id= $this->session->data['new_order_id'];
	        if($payment_id){
    	        $order_ids = explode('-', $payment_id);
    	        if(count($order_ids)!=2){
    	            print json_encode(array('return_code'=>'FAIL'));
    	            exit;
    	        }

    	        $order_id=$order_ids[1];

    	        $url="https://pay.globepay.co/api/v1.0/gateway/partners/$partner_code/orders/$payment_id";

    	        $time=time().'000';
    	        $nonce_str = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0,10);
    	        $valid_string="$partner_code&$time&$nonce_str&$credential_code";
    	        $sign=strtolower(hash('sha256',$valid_string));
    	        $url.="?time=$time&nonce_str=$nonce_str&sign=$sign";

    	        $head_arr = array();
    	        $head_arr[] = 'Accept: application/json';
    	        $ch = curl_init();
    	        curl_setopt($ch, CURLOPT_URL, $url);
    	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    	        curl_setopt($ch, CURLOPT_POST, false);
    	        curl_setopt($ch, CURLOPT_HTTPHEADER, $head_arr);
    	        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    	        $result = curl_exec($ch);
    	        curl_close($ch);

    	        $resArr = json_decode($result,false);
    	        if($resArr&&$resArr->result_code=='PAY_SUCCESS'){

    	            $result =$this->db->query(
            	       "select *
            	        from `" . DB_PREFIX . "order`
            	        where order_id = '$order_id'
            	             and order_status_id='$wait_status'
            	        limit 1;");

    	            $transaction_id           = $resArr->order_id;
    	            $msg                      = "Transaction ID:{$transaction_id},SN:{$resArr->partner_order_id}";
    	            if($this->db->countAffected()){
    	                if(method_exists($this->model_checkout_order,'confirm')){
    	                    $order_info =  $this->model_checkout_order->getOrder($order_id);
    	                    if ($order_info && $order_info['order_status_id']) {
    	                        $this->model_checkout_order->update($order_id, $succeed_status, $msg, true);
    	                    }else{
    	                        $this->model_checkout_order->confirm($order_id, $succeed_status, $msg, true);
    	                    }
    	                }else{
    	                    $this->model_checkout_order->addOrderHistory($order_id, $succeed_status, $msg, true);
    	                }
    	            }
    	            $this->session->data['new_order_id']=null;
    	            $this->response->redirect($this->url->link('checkout/success'));
    	            exit;
    	        }
	        }

	        $this->response->redirect($this->url->link('checkout/checkout'));
	    }else{
	        $this->response->redirect($this->url->link('checkout/success'));
	    }
	}

	public function send() {
	    $this->load->model('checkout/order');
	    $this->language->load('extension/payment/globepayalipay');

	    if(!isset($this->session->data['payment_method'])
	        ||!isset($this->session->data['payment_method']['code'])
	        ||$this->session->data['payment_method']['code']!=$this->id){

	        $json['error'] ='Ops!Something is wrong.';
	        $this->response->addHeader('Content-Type: application/json');
	        echo json_encode($json);
		    exit;
	    }

	    $order_id                 = $this->session->data['order_id'];
	    $succeed_status = $this->config->get('payment_globepayalipay_order_succeed_status_id');
	    $wait_status = $this->config->get('payment_globepayalipay_order_payWait_status_id');
	    $order_info               = $this->model_checkout_order->getOrder($order_id);
	    if(!$order_info){
	        $json['error'] ='Ops!Something is wrong.';
	        $this->response->addHeader('Content-Type: application/json');
	        echo json_encode($json);
	        exit;
	    }

	    $result =$this->db->query(
	        "select order_id
	         from `" . DB_PREFIX . "order`
	         where order_status_id='$succeed_status'
	               and order_id = '$order_id'
	        limit 1;");

	    if($result->num_rows){
	        $json['error']=null;
	        $this->session->data['cart'] = array();
	        $json['success'] = $this->http_to_https($this->url->link('checkout/success', '', 'SSL'));
	        $this->response->addHeader('Content-Type: application/json');
	        echo json_encode($json);
		    exit;
	    }

	    try {

	        $partner_code = $this->config->get('payment_globepayalipay_account');
	        $credential_code = $this->config->get('payment_globepayalipay_secret');

	        $resArr = $this->m1($order_info,$order_id);

	        if(!$resArr){
	            throw new Exception(('This request has been rejected by the remote service!'));
	        }

	        if(!isset($resArr->result_code)||$resArr->result_code!='SUCCESS'){
	            $errcode =empty($resArr->result_code)?$resArr->return_code:$resArr->result_code;
	            throw new Exception((sprintf('ERROR CODE:%s;ERROR MSG:%s.',$errcode,$resArr->return_msg)));
	        }

	        $time=time().'000';

	        $nonce_str = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), 0,10);
	        $valid_string="$partner_code&$time&$nonce_str&$credential_code";
	        $sign=strtolower(hash('sha256',$valid_string));

	        try {

	            if(method_exists($this->model_checkout_order,'confirm')){
	                if ($order_info && $order_info['order_status_id']) {
	                    $this->model_checkout_order->update($order_id, $wait_status, $_POST['comment'], true);
	                }else{
	                    $this->model_checkout_order->confirm($order_id, $wait_status, $_POST['comment'], true);
	                }
	            }else{
	                $this->model_checkout_order->addOrderHistory($order_id, $wait_status, $_POST['comment'], true);
	            }
	        } catch (Exception $e) {
	            throw new Exception("内部错误：{$e->getMessage()}",500);
	        }

	        $this->session->data['cart'] = array();
	        $callback = $this->http_to_https($this->url->link('extension/payment/globepayalipay/callback','',true));

	        $json['success'] = $resArr->pay_url.(strpos($resArr->pay_url, '?')==false?'?':'&')."directpay=true&time=$time&nonce_str=$nonce_str&sign=$sign&redirect=".urlencode($callback);
	        $this->response->addHeader('Content-Type: application/json');

	        echo json_encode($json);
	        exit;

	    } catch (Exception $e) {
	        $json['error'] ="errcode:{$e->getCode()},errmsg:{$e->getMessage()}";
	        $this->response->addHeader('Content-Type: application/json');
	        print(json_encode($json));
	        exit;
	    }
	}

	public function notify(){
	    $this->load->model('checkout/order');
	    $this->language->load('extension/payment/globepayalipay');

	    $payment_id=null;
	    $object = $this->m2($payment_id);

	    try {
    	    $order_id                 = $payment_id;
    	    $transaction_id           = $object->order_id;
    	    $msg                      = "Transaction ID:{$transaction_id},SN:{$object->partner_order_id}";

    	    $wait_status = $this->config->get('payment_globepayalipay_order_payWait_status_id');
    	    $succeed_status = $this->config->get('payment_globepayalipay_order_succeed_status_id');
    	    $result =$this->db->query(
    	        "update `" . DB_PREFIX . "order`
    	         set custom_field ='{$object->partner_order_id}'
    	         where order_id = '$order_id'
    	               and order_status_id='$wait_status';");

    	    if($this->db->countAffected()){
    	        if(method_exists($this->model_checkout_order,'confirm')){
    	            $order_info =  $this->model_checkout_order->getOrder($order_id);
    	            if ($order_info && $order_info['order_status_id']) {
    	                $this->model_checkout_order->update($order_id, $succeed_status, $msg, true);
    	            }else{
    	                $this->model_checkout_order->confirm($order_id, $succeed_status, $msg, true);
    	            }
    	        }else{
    	            $this->model_checkout_order->addOrderHistory($order_id, $succeed_status, $msg, true);
    	        }
    	    }

	    } catch (Exception $e) {
	        print json_encode(array('return_code'=>'FAIL'));
	        exit;
	    }

	    print json_encode(array('return_code'=>'SUCCESS'));
	    exit;
	}

	public function get_order_title($order_id){
	    $title="";
	    $products = $this->cart->getProducts();
	    foreach($products as $product){
	        $title.="{$product['name']}";
	        break;
	    }

	    if(count($products)>1){
	        $title.="...";
	    }

	    return $title;
	}
}

?>

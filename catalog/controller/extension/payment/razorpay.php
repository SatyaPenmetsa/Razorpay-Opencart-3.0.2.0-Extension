<?php
require_once 'system/library/razorpay-sdk/Razorpay.php';
use Razorpay\Api\Api;

class ControllerExtensionPaymentRazorpay extends Controller
{
    public function index()
    {
        $data['button_confirm'] = $this->language->get('button_confirm');

        $this->load->model('checkout/order');

        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        // Orders API with payment autocapture
        $api = new Api($this->config->get('payment_razorpay_key_id'), $this->config->get('payment_razorpay_key_secret'));
        $order_data = $this->get_order_creation_data($this->session->data['order_id']);
        $razorpay_order = $api->order->create($order_data);
        $this->session->data['razorpay_order_id'] = $razorpay_order['id'];

        $data['key_id'] = $this->config->get('payment_razorpay_key_id');
        $data['currency_code'] = $order_info['currency_code'];
        $data['total'] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false) * 100;
        $data['merchant_order_id'] = $this->session->data['order_id'];
        $data['card_holder_name'] = $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'];
        $data['email'] = $order_info['email'];
        $data['phone'] = $order_info['telephone'];
        $data['name'] = $this->config->get('config_name');
        $data['lang'] = $this->session->data['language'];
        $data['return_url'] = $this->url->link('extension/payment/razorpay/callback', '', 'true');
        $data['razorpay_order_id'] = $razorpay_order['id'];

        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/extension/payment/razorpay')) {

            return $this->load->view($this->config->get('config_template') . '/template/extension/payment/razorpay', $data);
        } else {

            return $this->load->view('extension/payment/razorpay', $data);
        }
    }

    public function get_order_creation_data($order_id)
    {
        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);

        $data = [
            'receipt' => $order_id,
            'amount' => $this->currency->format($order['total'], $order['currency_code'], $order['currency_value'], false) * 100,
            'currency' => $order['currency_code'],
            'payment_capture' => ($this->payment_action === 'authorize') ? 0 : 1,
        ];

        return $data;
    }

    public function callback()
    {
        $this->load->model('checkout/order');

        if ($this->request->request['razorpay_payment_id']) {

            $razorpay_payment_id = $this->request->request['razorpay_payment_id'];
            $merchant_order_id = $this->session->data['order_id'];
            $razorpay_order_id = $this->session->data['razorpay_order_id'];
            $razorpay_signature = $this->request->request['razorpay_signature'];

            $order_info = $this->model_checkout_order->getOrder($merchant_order_id);

            $amount = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false) * 100;

            $key_secret = $this->config->get('payment_razorpay_key_secret');

            $success = false;
            $error = "";

            $signature = hash_hmac('sha256', $razorpay_order_id . '|' . $razorpay_payment_id, $key_secret);

            $success = $this->hash_equals($signature, $razorpay_signature);

            if ($success === true) {
                if (!$order_info['order_status_id']) {
                    $this->model_checkout_order->addOrderHistory($merchant_order_id, 1, 'Payment Successful. Razorpay Payment Id:' . $razorpay_payment_id, true);
                } else {
                    $this->model_checkout_order->addOrderHistory($merchant_order_id, 1, 'Payment Successful. Razorpay Payment Id:' . $razorpay_payment_id, true);
                }

                header('Location:' . $this->url->link('checkout/success'));

                exit();
            } else {

                if (!$order_info['order_status_id']) {
                    $this->model_checkout_order->confirm($merchant_order_id, '0', $error . ' Payment Failed! Check Razorpay dashboard for details of Payment Id:' . $razorpay_payment_id, true);
                } else {
                    $this->model_checkout_order->update($merchant_order_id, '0', $error . ' Payment Failed! Check Razorpay dashboard for details of Payment Id:' . $razorpay_payment_id, true);
                }

                header('Location:' . $this->url->link('checkout/checkout'));
                exit();
            }
        } else {
            if (isset($_POST['error']) === true) {
                $error = $_POST['error'];

                $message = 'An error occured. Description : ' . $error['description'] . '. Code : ' . $error['code'];

                if (isset($error['field']) === true) {
                    $message .= 'Field : ' . $error['field'];
                }
            } else {
                $message = 'An error occured. Please contact administrator for assistance';
            }

            echo $message;
        }
    }

    protected function hash_equals($expected, $actual)
    {
        if (function_exists('hash_equals')) {
            return hash_equals($expected, $actual);
        }

        if (strlen($expected) !== strlen($actual)) {
            return false;
        }

        $result = 0;

        for ($i = 0; $i < strlen($expected); $i++) {
            $result |= ord($expected[$i]) ^ ord($actual[$i]);
        }

        return ($result == 0);
    }

}

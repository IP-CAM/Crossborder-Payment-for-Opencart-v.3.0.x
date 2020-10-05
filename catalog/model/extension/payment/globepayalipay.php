<?php

class ModelExtensionPaymentglobepayalipay extends Model
{

    public function getMethod($address)
    {
        $this->load->language('extension/payment/globepayalipay');

        $status = $this->validate($address);
        if (! $status) {
            return array();
        }

        return array(
            'code' => 'globepayalipay',
            'title' =>'支付宝支付<img src="/catalog/view/theme/default/image/globepay_alipay_logo.png" />',
            'terms' => '',
            'sort_order' => intval($this->config->get('payment_globepayalipay_sort_order'))
        );
    }

    private function validate($address)
    {
        if (! $this->config->get('payment_globepayalipay_status')) {
            return false;
        }

        if (! $this->config->get('payment_globepayalipay_geo_zone_id')) {
            return true;
        }

        $query = $this->db->query("SELECT geo_zone_id FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int) $this->config->get('payment_globepayalipay_geo_zone_id') . "' AND country_id = '" . (int) $address['country_id'] . "' AND (zone_id = '" . (int) $address['zone_id'] . "' OR zone_id = '0')");
        return $query->num_rows;
    }
}
?>

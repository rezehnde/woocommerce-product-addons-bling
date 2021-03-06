<?php
/**
 * Plugin Name: WooCommerce Product Addons Bling
 * Description: Integrate WooCommerce Product Addons to Bling Platform
 * Version: 1.1.0
 * Author: Marcos Rezende
 * Author URI: https://github.com/rezehnde
 * Requires at least: 3.8
 * Tested up to: 5.0
 * WC tested up to: 3.6
 * WC requires at least: 2.6.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 *
 * @param mixed $order_id
 */

/**
 * Create an Bling Order after new order creation.
 *
 * @param int $order_id WooCommerce Order ID
 */
function bling_addons_checkout_update_order_meta($order_id)
{
    $order = wc_get_order($order_id);
    $xml = bling_addons_get_xml($order);

    file_put_contents(__DIR__.'/request/order-'.$order_id.'-request.xml', print_r($xml, true));

    $bling_api_key = get_option('woocommerce_bling_api_key');

    if (!empty($bling_api_key)) {
        $data = [
            'apikey' => $bling_api_key,
            'xml' => rawurlencode($xml),
        ];

        $response = '';
        $send_order = function() use($response) {
            $curl_handle = curl_init();
            curl_setopt($curl_handle, CURLOPT_URL, 'https://bling.com.br/Api/v2/pedido/json/');
            curl_setopt($curl_handle, CURLOPT_POST, count($data));
            curl_setopt($curl_handle, CURLOPT_POSTFIELDS, $data);
            curl_setopt($curl_handle, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($curl_handle);
            curl_close($curl_handle);
        };
        $send_order();

        file_put_contents(__DIR__.'/response/order-'.$order_id.'-response.json', print_r($response, true));
    }
}
add_action('woocommerce_checkout_update_order_meta', 'bling_addons_checkout_update_order_meta', 10, 1);

/**
 * Return a Bling well-formed XML string based on a WC_Order object.
 *
 * @return string a well-formed XML string based on SimpleXML element
 */
function bling_addons_get_xml(WC_Order $order)
{
    $pedido = new SimpleXMLElement('<pedido></pedido>');
    $pedido->addAttribute('encoding', 'UTF-8', 'pedido');
    $pedido->addChild('numero_loja', $order->get_id());

    // Cliente
    $cliente = $pedido->addChild('cliente');
    $tipoPessoa = $order->get_meta('_billing_persontype');
    if ('1' == $tipoPessoa) {
        $cliente->addChild('tipoPessoa', 'F');
        $cliente->addChild('cpf_cnpj', $order->get_meta('_billing_cpf'));
        $cliente->addChild('ie_rg', $order->get_meta('_billing_rg'));
    }
    if ('2' == $tipoPessoa) {
        $cliente->addChild('tipoPessoa', $tipoPessoa);
        $cliente->addChild('cpf_cnpj', $order->get_meta('_billing_cnpj'));
        $cliente->addChild('ie_rg', $order->get_meta('_billing_ie'));
    }
    $cliente->addChild('nome', $order->get_billing_first_name().' '.$order->get_billing_last_name());
    $cliente->addChild('endereco', $order->get_billing_address_1());
    $cliente->addChild('complemento', $order->get_billing_address_2());
    $cliente->addChild('numero', $order->get_meta('_billing_number'));
    $cliente->addChild('bairro', $order->get_meta('_billing_neighborhood'));
    $cliente->addChild('cep', $order->get_billing_postcode());
    $cliente->addChild('cidade', $order->get_billing_city());
    $cliente->addChild('uf', $order->get_billing_state());
    $cliente->addChild('fone', $order->get_billing_phone());
    $cliente->addChild('email', $order->get_billing_email());

    // Itens
    $itens = $pedido->addChild('itens');
    $obs = '';
    foreach ($order->get_items() as $order_item) {
        $obs .= $order_item->get_name();
        $item_total_price = $order->get_item_total($order_item);
        $item_quantity = $order_item->get_quantity();
        $item_single_price = $item_total_price / $item_quantity;
        $item = $itens->addChild('item');
        $item->addChild('codigo', $order_item->get_id());
        $item->addChild('descricao', $order_item->get_name());
        $item->addChild('qtde', $item_quantity);
        $item->addChild('un', 'UN');
        $item->addChild('vlr_unit', $item_single_price);

        foreach ($order_item->get_formatted_meta_data() as $addons) {
            $obs .= ' - '.$addons->key.': '.$addons->value.'<br/>';
        }
    }
    $pedido->addChild('obs', $obs);

    foreach ($order->get_shipping_methods() as $shipping_item) {
        $transporte = $pedido->addChild('transporte');
        $transporte->addChild('tipo_frete', $shipping_item->get_id());
    }

    $pedido->addChild('idFormaPagamento', $order->get_payment_method());

    $pedido->addChild('vlr_frete', $order->get_shipping_total());

    return str_replace('<?xml version="1.0"?>', '<?xml version="1.0" encoding="UTF-8"?>', $pedido->asXML());
}

/**
 * Create the apy key configuration on WooCommerce General Option settings.
 *
 * @param array $settings Settings sections
 */
function bling_addons_general_settings($settings)
{
    $updated_settings = [];

    foreach ($settings as $section) {
        // at the bottom of the General Options section
        if (isset($section['id']) && 'general_options' == $section['id'] &&
            isset($section['type']) && 'sectionend' == $section['type']) {
            $updated_settings[] = [
                'name' => __('Bling API Key', 'woocommerce_bling_api_key'),
                'id' => 'woocommerce_bling_api_key',
                'type' => 'text',
                'css' => 'min-width:300px;',
            ];
        }

        $updated_settings[] = $section;
    }

    return $updated_settings;
}
add_filter('woocommerce_general_settings', 'bling_addons_general_settings');

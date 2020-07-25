<?php
/*
 * Plugin Name: WooCommerce Product Addons Bling
 * Description: Integrate WooCommerce Product Addons to Bling Platform
 * Version: 1.0.5
 * Author: Marcos Rezende
 * Author URI: https://github.com/rezehnde
 * Requires at least: 3.8
 * Tested up to: 5.0
 * WC tested up to: 3.6
 * WC requires at least: 2.6
 */

 function woocommerce_custom_function($item, $cart_item_key, $values, $order)
 {
     $item_title = $item->get_name();
     if (count($values['addons']) > 0) {
         foreach ($values['addons'] as $addons) {
             $item_title .= ' - '.$addons['name'].': '.$addons['value'];
         }
     }

     $item->set_name($item_title);
 }
add_action('woocommerce_checkout_create_order_line_item', 'woocommerce_custom_function', 10, 4);

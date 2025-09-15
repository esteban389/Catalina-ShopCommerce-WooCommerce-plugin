<?php

$produc_sync_hook_name = 'provider_product_sync_hook';
$produc_sync_hook_interval = 'hourly';
$timeout = 14 * 60;


add_action($produc_sync_hook_name, 'provider_product_sync_hook');

function product_sync_plugin_activate()
{
  global $produc_sync_hook_name;
  global $produc_sync_hook_interval;
  if (!wp_next_scheduled($produc_sync_hook_name)) {
    add_filter('cron_schedules', function($schedules) {
      $schedules['every_minute'] = [
          'interval' => 60,
          'display' => __('Every Minute')
      ];
      return $schedules;
    });
    wp_schedule_event(time(), $produc_sync_hook_interval, $produc_sync_hook_name);
  }
}

function product_sync_plugin_deactivate()
{
  wp_clear_scheduled_hook($produc_sync_hook_name);
}

$base_url = 'https://shopcommerce.mps.com.co:7965/';

function provider_product_sync_hook()
{
  global $base_url;
  global $timeout;

  $token = get_api_token();
  if (!is_string($token) || empty($token)) {
    error_log('Something failed on the token request: ' . $token);
    return;
  }
  $api_url = $base_url . 'api/Webapi/VerCatalogo';

  $response = wp_remote_post($api_url, array(
    'headers' => array(
      'Authorization' => 'Bearer ' . $token
    ),
    'timeout' => $timeout,
  ));

  if (is_wp_error($response)) {
    error_log('Error during request when retrieving catalog of products: ' . $response->get_error_message());
    return;
  }

  $body = wp_remote_retrieve_body($response);
  $jsonResponse = json_decode($body, true);

  error_log('jsonResponse: ' . print_r($jsonResponse, true));
  error_log('jsonResponse2: ' . var_dump($response));

  return;
}

function get_api_token()
{
  global $base_url;
  global $timeout;
  $username = 'pruebas@hekalsoluciones.com';
  $password = 'Mo3rdoadzy1M';

  $api_url = $base_url . 'Token';
  $response = wp_remote_post($api_url, array(
    'body' => array(
      'password' => $password,
      'username' => $username,
      'grant_type' => 'password'
    ),
    'timeout' => $timeout,
  ));

  if (is_wp_error($response)) {
    // Log the error
    error_log('Error during request when retrieving API token: ' . $response->get_error_message());
    return;
  }

  $body = wp_remote_retrieve_body($response);
  $jsonResponse = json_decode($body, true);

  if (!array_key_exists('access_token', $jsonResponse)) {
    error_log('Error in response when retrieving API token: ' . print_r($jsonResponse, true));
    return;
  }
  return $jsonResponse['access_token'];
}

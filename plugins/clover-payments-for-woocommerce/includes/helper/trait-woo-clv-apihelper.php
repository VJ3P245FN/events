<?php
	/**
	 * Apihelper class
	 *
	 * @package woo-clover-payments
	 */

	if (!defined('ABSPATH')) {
		exit; // Exit if accessed directly.
	}
	
	/**
	 * Helper file.
	 */
	trait ApiHelper
	{
		/**
		 * @var type sandbox endpoint.
		 */
		private $sandboxcharge_url = 'https://scl-sandbox.dev.clover.com/v1/charges';
		
		/**
		 * Live charge endpoint.
		 *
		 * @var type live endpoint.
		 */
		private $livecharge_url = 'https://scl.clover.com/v1/charges';
		
		/**
		 * Sandbox refund endpoint.
		 *
		 * @var type sandbox refund endpoint.
		 */
		private $sandboxrefund_url = 'https://scl-sandbox.dev.clover.com/v1/refunds';
		
		/**
		 * Live refund endpoint.
		 *
		 * @var type live refund endpoint.
		 */
		private $liverefund_url = 'https://scl.clover.com/v1/refunds';
		
		/**
		 * Merchant endpoint sandbox.
		 *
		 * @var type merchant endpoint sandbox.
		 */
		private $surchargeinfo_url = 'https://apisandbox.dev.clover.com/v3/merchants/';
		
		/**
		 * Merchant endpoint live.
		 *
		 * @var type merchant endpoint live.
		 */
		private $livesurchargeinfo_url = 'https://api.clover.com/v3/merchants/';
		
		/**
		 * Generate UUID.
		 *
		 * @return type
		 * returns uuid4 idempotent key for API call.
		 * @throws Exception
		 */
		
		public function uuidv4()
		{
			$data = random_bytes(16);
			$data[6] = chr(ord($data[6]) & 0x0f | 0x40);
			$data[8] = chr(ord($data[8]) & 0x3f | 0x80);
			return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
		}
		
		/**
		 * add card details to the post meta table.
		 *
		 * @param type $order_id Input
		 * @param type $response Response.
		 * @return boolean
		 */
		public function add_card_details($order_id, $response)
		{
			$api_arr = json_decode($response['result']);
			
			if (strcasecmp($api_arr->source->brand, 'MC') == 0) {
				$brand = 'MasterCard';
			} else {
				$brand = $api_arr->source->brand;
			}
			
			$card_details = $brand . ' ending in ' . $api_arr->source->last4;
			
			add_post_meta($order_id, '_brand', $api_arr->source->brand);
			add_post_meta($order_id, '_last4', $api_arr->source->last4);
			add_post_meta($order_id, '_card_details', $card_details);
		}
		
		/**
		 * Curl method.
		 *
		 * @param type $url URL.
		 * @param type $header Curl header.
		 * @param type $data Form data.
		 * @param type $method POST or GET.
		 * @return type
		 */
		public function call_api_post($url, $header, $data, $method)
		{
			
			$max_attempts = 10;
			$attempts = 0;
			
			while ($attempts < $max_attempts) {
				// Make a request to Clover REST API.
				try {
					$response = array();
					$result = wp_remote_post(
						$url,
						array(
							'method' => $method,
							'timeout' => 60,
							'sslverify' => false,
							'headers' => $header,
							'body' => !empty($data) ? wp_json_encode($data) : null,
						)
					);
					if (is_wp_error($result)) {
						$response['status_code'] = 0;
						$response['result'] = $result->get_error_message();
					} else {
						$response['status_code'] = wp_remote_retrieve_response_code($result);   // get here response status code.
						$response['result'] = wp_remote_retrieve_body($result);
					}
				} catch (\Exception $e) {
					$response['status_code'] = 0;
					$response['result'] = $e->getMessage();
				}
				// If not rate limited, break out of while loop and continue with the rest of the code.
				if (429 !== $response['status_code']) {
					break;
				}
				// If rate limited, wait and try again.
				sleep((2 ** $attempts) + (wp_rand(1, 10) / 10));
				++$attempts;
			}
			
			return $response;
		}
		
		/**
		 * Handle Response for all api calls.
		 *
		 * @param type $request Request.
		 * @param type $response Response.
		 * @return type
		 */
		public function handle_response($request, $response)
		{
			$request['captured'] = 0;
			$request['TXN_ID'] = '';
			$request['ref_num'] = '';
			$request['message'] = '';
			$request['error_code'] = null;
			
			$api_arr = json_decode($response['result']);
			if (200 === $response['status_code']) {
				$request['captured'] = 1;
				$request['TXN_ID'] = $api_arr->id;
				$request['ref_num'] = isset($api_arr->ref_num) ? $api_arr->ref_num : '';
				$request['message'] = $api_arr->status;
				
			} elseif (0 === $response['status_code']) {
				$request['message'] = $response['result'];
				$request['error_code'] = 'unexpected';
				
			} else {
				if (isset($api_arr->error)) {
					$request['error_code'] = isset($api_arr->error->code) ? $api_arr->error->code : '';
					$request['message'] = isset($api_arr->error->message) ? $api_arr->error->message : '';
				} else {
					if (400 === $response['status_code']) {
						$request['message'] = isset($api_arr->message) ? $api_arr->message : 'Invalid Source';
						$request['error_code'] = 'invalid_details';
					} elseif (401 === $response['status_code']) {
						$request['message'] = isset($api_arr->message) ? $api_arr->message : 'Unauthorized';
						$request['error_code'] = 'invalid_key';
					} else {
						$request['message'] = __('Unable to complete this transaction', 'woo-clv-payments');
						$request['error_code'] = 'unexpected';
					}
				}
			}
			$request['result'] = $response;
			return $request;
		}
		
		/**
		 * *
		 *
		 * @param type $amount Conversion amount.
		 * @param type $currency Conversion currency.
		 * @return int
		 */
		public function converttocents($amount, $currency)
		{
			if ('USD' === $currency) {
				return isset($amount) ? (int)Round($amount * 100) : '';
			}
			if ('CAD' === $currency) {
				return isset($amount) ? (int)Round($amount * 100) : '';
			}
		}
		
		/**
		 * Log information.
		 *
		 * @return type
		 */
		public function framework_version()
		{
			global $wp_version;
			return 'WP ' . $wp_version . ' | WC ' . WC_VERSION . ' | 1.0.11';
		}
		
		/**
		 * Testmode check.
		 *
		 * @param type $environment Environment.
		 * @return boolean
		 */
		private function test_mode($environment)
		{
			if ('sandbox' === $environment) {
				return true;
			}
			return false;
		}
		
		/**
		 * Charge endpoint return.
		 *
		 * @param type $environment Environment.
		 * @return type
		 */
		public function get_charge_url($environment)
		{
			if ($this->test_mode($environment)) {
				return $this->sandboxcharge_url;
			} else {
				return $this->livecharge_url;
			}
		}
		
		/**
		 * Refund endpoint return.
		 *
		 * @param type $environment Environment.
		 * @return type
		 */
		private function get_refund_url($environment)
		{
			if ($this->test_mode($environment)) {
				return $this->sandboxrefund_url;
			} else {
				return $this->liverefund_url;
			}
		}
		
		/**
		 * Capture endpoint return.
		 *
		 * @param type $environment Environment.
		 * @param type $trans_id Transaction id.
		 * @return type
		 */
		public function get_capture_url($environment, $trans_id)
		{
			$url = $this->get_charge_url($environment) . '/' . $trans_id . '/capture';
			return $url;
		}
		
		/**
		 * Merchant endpoint return..
		 *
		 * @param type $merchantid Merchant id.
		 * @param type $test_mode Testmode.
		 * @return type
		 */
		public function get_surcharge_url($merchantid, $test_mode)
		{
			if ($test_mode) {
				return $this->surchargeinfo_url . $merchantid . '/ecomm_payment_configs';
			} else {
				return $this->livesurchargeinfo_url . $merchantid . '/ecomm_payment_configs';
			}
		}
		
		/**
		 * Surcharge text.
		 *
		 * @param type $response Input.
		 * @return string
		 */
		public function parse_surcharge($response)
		{
			$surcharge = array();
			$surcharge['issurcharge'] = false;
			if (200 === $response['status_code']) { // 200 indicates success api response
				$api_arr = json_decode($response['result']);
				$surcharge['message'] = '';
				if (isset($api_arr->surcharging)) {
					$surcharging = $api_arr->surcharging;
					$surcharge['supported'] = $api_arr->surcharging->supported;
					if ($surcharge['supported']) {
						$surcharge['message'] = 'Note: A surcharge of ';
					}
					if (isset($surcharging->rate)) {
						$surcharge['rate'] = $api_arr->surcharging->rate;
						$surcharge['message'] .= ($api_arr->surcharging->rate * 100);
					}
					$surcharge['message'] .= '% may be applied to credit cards transactions';
				}
			} elseif (0 === $response['status_code']) { // 0 indicates internal error
				$surcharge['message'] = 'Unable to display surcharge information at this moment ';
			} elseif (401 === $response['status_code']) {
				$surcharge['message'] = 'Merchant ID is invalid, so we are not able to display surcharge information';
			} else {
				$surcharge['message'] = 'Unable to display surcharge information at this moment';
			}
			return $surcharge;
		}
		
		/**
		 * retrieve payment details from post meta data.
		 *
		 * @param type $order Input.
		 * @return string
		 */
		public function get_card_details($order): string
		{
			return get_post_meta($order->id, '_card_details', true);
		}
	}

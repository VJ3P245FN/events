<?php
/**
 * Gateway class
 *
 * @package woo-clover-payments
 */

if (! defined('ABSPATH') ) {
    exit; // Exit if accessed directly.
}

/**
 * Class WOO_CLV_GATEWAY
 */
class WOO_CLV_GATEWAY extends WC_Payment_Gateway_CC
{

    use ApiHelper;
    /**
     * *
     *
     * @param  type $order_id Order id.
     * @param  type $amount   Refund value.
     * @param  type $reason   Refund reason.
     * @return WP_Error|boolean
     * @throws Exception Handler.
     */
    public function process_refund( $order_id, $amount = null, $reason = '' )
    {
        try {
            if (is_null($amount) ) {
                return new WP_Error(
                    'clover_error',
                    sprintf(
                        __('Please enter refund value', 'woo-clv-payments')
                    )
                );
            }
            $order = wc_get_order($order_id);

            if (in_array($order->get_status(), array( 'refunded', 'failed' ), true) ) {
                return new WP_Error(
                    'clover_error',
                    sprintf(
                        __('Please select a valid order to refund', 'woo-clv-payments')
                    )
                );
            }

            $ordertotal = $order->get_total();
            $ispartial  = $this->isPartial($ordertotal, $amount);

            if ($ispartial && ! $order->get_date_paid() ) {
                return new WP_Error(
                    'clover_error',
                    sprintf(
                        __('Unable to process partial void transaction', 'woo-clv-payments')
                    )
                );
            }

            $environment = $this->environment;
            $private_key = $this->private_key;

            $refund_data   = $this->getRefundData($order_id, $order, $amount, $reason, $ispartial);
            $refund_url    = $this->get_refund_url($environment);
            $header        = $this->buildRefundHeader($private_key);
            $response      = $this->call_api_post($refund_url, $header, $refund_data, 'POST');
            $parseresponse = $this->handle_response($refund_data, $response);
            $log           = array(
            'request'  => $refund_data,
            'response' => $response,
            );
            $this->log(wp_json_encode($log));

            if ($parseresponse['captured'] ) {
                $refund_message = $parseresponse['message'];
                if ($order->get_date_paid() ) {
                    /* translators: %1$s %2$s %3$s: amount txid refund-message */
                    $refund_message = sprintf(__('Refunded %1$s - Refund ID: %2$s - Status: %3$s', 'woo-clv-payments'), $amount, $parseresponse['TXN_ID'], $refund_message);
                    $order->update_meta_data('_clover_refund_id', $parseresponse['TXN_ID']);
                } else {
                    /* translators: %1$s %2$s %3$s: amount txid refund-message */
                    $refund_message = sprintf(__('Voided %1$s - Void ID: %2$s - Status: %3$s', 'woo-clv-payments'), $amount, $parseresponse['TXN_ID'], $refund_message);
                    $order->update_meta_data('_clover_void_id', $parseresponse['TXN_ID']);
                }

                $order->add_order_note($refund_message);
                return true;
            } else {
                $failure_message = WOO_CLV_ERRORMAPPER::get_localized_error_message($parseresponse);
                return new WP_Error(
                    'clover_error',
                    sprintf('Error:' . $failure_message)
                );
            }
        } catch ( Exception $e ) {
            throw new Exception($e->getMessage());
        }
    }

    /**
     * *
     *
     * @param  type $order_id     Order id.
     * @param  type $order        Order object.
     * @param  type $refundamount Value.
     * @param  type $reason       reason.
     * @param  type $ispartial    partial payment check.
     * @return string
     */
    private function getRefundData( $order_id, $order, $refundamount, $reason, $ispartial )
    {
        $charge_id = $order->get_transaction_id();
        $currency  = $order->get_currency();
        $post_data = array(
        'metadata' => array( 'shopping_cart' => $this->framework_version() ),
        );
        if ($ispartial && $order->get_date_paid() ) {
            $post_data['amount'] = $this->converttocents($refundamount, $currency);
        }
        $post_data['charge']                = $charge_id;
        $post_data['external_reference_id'] = $order_id;
        $post_data['reason']                = 'requested_by_customer';
        return $post_data;
    }

    /**
     * Partial check.
     *
     * @param  type $ordertotal Total.
     * @param  type $refund     Value.
     * @return boolean
     */
    private function isPartial( $ordertotal, $refund )
    {
        if ($refund < $ordertotal ) {
            return true;
        }
        return false;
    }

    /**
     * *
     *
     * @param  type $private_key private key.
     * @return type
     */
    public function buildRefundHeader( $private_key )
    {
        $header = array(
        'Content-Type'  => 'application/json',
        'Accept'        => 'application/json',
        'authorization' => 'Bearer ' . $private_key,
        );
        return $header;
    }

}

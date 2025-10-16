<?php
/**
 * Stripe Payment Handler
 * 
 * Handles all Stripe payment operations including checkout sessions and webhooks
 */

if (!defined('ABSPATH')) {
    exit;
}

class RP_Stripe_Handler {
    
    private $secret_key;
    private $publishable_key;
    
    public function __construct() {
        $stripe_settings = get_option('rp_stripe_settings');
        $this->secret_key = !empty($stripe_settings['stripe_secret_key']) ? $stripe_settings['stripe_secret_key'] : '';
        $this->publishable_key = !empty($stripe_settings['stripe_publishable_key']) ? $stripe_settings['stripe_publishable_key'] : '';
    }
    
    /**
     * Create a Stripe Checkout Session
     */
    public function create_checkout_session($report_id, $email, $first_name, $last_name) {
        if (empty($this->secret_key)) {
            return array(
                'success' => false,
                'message' => 'Stripe is not configured. Please contact the site administrator.'
            );
        }
        
        try {
            \Stripe\Stripe::setApiKey($this->secret_key);
            
            // Get report details
            $price = get_post_meta($report_id, '_rp_price', true);
            $currency = get_post_meta($report_id, '_rp_currency', true);
            $report_title = get_the_title($report_id);
            
            if (empty($price) || $price <= 0) {
                return array(
                    'success' => false,
                    'message' => 'Invalid report price.'
                );
            }
            
            // Convert price to cents (Stripe uses smallest currency unit)
            $amount = intval($price * 100);
            
            // Create checkout session
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'product_data' => [
                            'name' => $report_title,
                            'description' => 'Digital Report Download',
                        ],
                        'unit_amount' => $amount,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'customer_email' => $email,
                'success_url' => get_permalink($report_id) . '?payment=success',
                'cancel_url' => get_permalink($report_id) . '?payment=cancelled',
                'metadata' => [
                    'report_id' => $report_id,
                    'user_email' => $email,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                ]
            ]);
            
            return array(
                'success' => true,
                'data' => array(
                    'sessionId' => $session->id,
                    'url' => $session->url
                )
            );
            
        } catch (\Stripe\Exception\ApiErrorException $e) {
            error_log('Stripe API Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'Payment processing error: ' . $e->getMessage()
            );
        } catch (Exception $e) {
            error_log('General Error: ' . $e->getMessage());
            return array(
                'success' => false,
                'message' => 'An error occurred. Please try again.'
            );
        }
    }
    
    /**
     * Handle Stripe Webhook
     */
    public function handle_webhook($request) {
        if (empty($this->secret_key)) {
            return new WP_REST_Response(array('error' => 'Stripe not configured'), 400);
        }
        
        \Stripe\Stripe::setApiKey($this->secret_key);
        
        $payload = $request->get_body();
        $sig_header = $request->get_header('stripe-signature');
        
        // For testing, you can skip signature verification
        // In production, always verify the signature
        try {
            $event = \Stripe\Event::constructFrom(json_decode($payload, true));
        } catch (Exception $e) {
            error_log('Webhook Error: ' . $e->getMessage());
            return new WP_REST_Response(array('error' => 'Invalid payload'), 400);
        }
        
        // Handle the event
        switch ($event->type) {
            case 'checkout.session.completed':
                $session = $event->data->object;
                $this->handle_successful_payment($session);
                break;
                
            default:
                error_log('Unhandled event type: ' . $event->type);
        }
        
        return new WP_REST_Response(array('success' => true), 200);
    }
    
    /**
     * Process successful payment
     */
    private function handle_successful_payment($session) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reports_purchases';
        
        // Extract metadata
        $report_id = isset($session->metadata->report_id) ? intval($session->metadata->report_id) : 0;
        $user_email = isset($session->metadata->user_email) ? sanitize_email($session->metadata->user_email) : '';
        $first_name = isset($session->metadata->first_name) ? sanitize_text_field($session->metadata->first_name) : '';
        $last_name = isset($session->metadata->last_name) ? sanitize_text_field($session->metadata->last_name) : '';
        
        if (!$report_id || !$user_email) {
            error_log('Missing required metadata in webhook');
            return;
        }
        
        // Check if purchase already exists
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE stripe_session_id = %s",
            $session->id
        ));
        
        if ($exists) {
            error_log('Purchase already recorded for session: ' . $session->id);
            return;
        }
        
        // Get amount and currency
        $amount = $session->amount_total / 100; // Convert from cents
        $currency = strtoupper($session->currency);
        
        // Save purchase to database
        $data = array(
            'report_id' => $report_id,
            'user_email' => $user_email,
            'stripe_session_id' => $session->id,
            'stripe_payment_intent' => isset($session->payment_intent) ? $session->payment_intent : '',
            'amount' => $amount,
            'currency' => $currency,
            'purchase_date' => current_time('mysql'),
            'download_count' => 0,
        );
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result) {
            // Send confirmation email
            $this->send_purchase_confirmation_email($user_email, $report_id, $first_name, $last_name);
            error_log('Purchase recorded successfully for: ' . $user_email);
        } else {
            error_log('Failed to record purchase in database');
        }
    }
    
    /**
     * Send purchase confirmation email
     */
    private function send_purchase_confirmation_email($email, $report_id, $first_name, $last_name) {
        $report_title = get_the_title($report_id);
        $report_url = get_permalink($report_id);
        $download_link = get_post_meta($report_id, '_rp_download_link', true);
        
        $subject = 'Your Purchase Confirmation - ' . $report_title;
        
        $message = "Hi " . $first_name . ",\n\n";
        $message .= "Thank you for your purchase!\n\n";
        $message .= "Report: " . $report_title . "\n\n";
        $message .= "You can access your report at:\n";
        $message .= $report_url . "\n\n";
        $message .= "Direct download link:\n";
        $message .= $download_link . "\n\n";
        $message .= "This link will remain active for your records.\n\n";
        $message .= "If you have any questions, please don't hesitate to contact us.\n\n";
        $message .= "Best regards,\n";
        $message .= get_bloginfo('name');
        
        $headers = array('Content-Type: text/plain; charset=UTF-8');
        
        wp_mail($email, $subject, $message, $headers);
    }
}
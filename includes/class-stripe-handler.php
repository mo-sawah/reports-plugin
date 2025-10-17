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
            
            // Get featured image URL
            $image_url = get_the_post_thumbnail_url($report_id, 'medium');
            if (empty($image_url)) {
                // Fallback to a default image or site logo
                $custom_logo_id = get_theme_mod('custom_logo');
                if ($custom_logo_id) {
                    $image_url = wp_get_attachment_image_url($custom_logo_id, 'medium');
                }
            }
            
            // Get excerpt for description
            $post = get_post($report_id);
            $description = !empty($post->post_excerpt) ? wp_trim_words($post->post_excerpt, 20) : 'Comprehensive industry report with detailed insights and analysis.';
            
            if (empty($price) || $price <= 0) {
                return array(
                    'success' => false,
                    'message' => 'Invalid report price.'
                );
            }
            
            // Convert price to cents (Stripe uses smallest currency unit)
            $amount = intval($price * 100);
            
            // Prepare product data
            $product_data = array(
                'name' => $report_title,
                'description' => $description,
            );
            
            // Add image if available
            if (!empty($image_url)) {
                $product_data['images'] = array($image_url);
            }
            
            // Create checkout session
            $session = \Stripe\Checkout\Session::create([
                'payment_method_types' => ['card'],
                'line_items' => [[
                    'price_data' => [
                        'currency' => strtolower($currency),
                        'product_data' => $product_data,
                        'unit_amount' => $amount,
                    ],
                    'quantity' => 1,
                ]],
                'mode' => 'payment',
                'customer_email' => $email,
                'success_url' => get_permalink($report_id) . '?session_id={CHECKOUT_SESSION_ID}',
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
     * Verify and record a checkout session when user returns
     */
    public function verify_and_record_session($session_id, $report_id) {
        if (empty($this->secret_key)) {
            return array('success' => false, 'message' => 'Stripe not configured');
        }
        
        try {
            \Stripe\Stripe::setApiKey($this->secret_key);
            
            // Retrieve the session
            $session = \Stripe\Checkout\Session::retrieve($session_id);
            
            // Check if payment was successful
            if ($session->payment_status !== 'paid') {
                return array('success' => false, 'message' => 'Payment not completed');
            }
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'reports_purchases';
            
            // Check if already recorded
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE stripe_session_id = %s",
                $session_id
            ));
            
            if ($exists) {
                // Already recorded, just return success
                $user_email = isset($session->metadata->user_email) ? $session->metadata->user_email : $session->customer_email;
                return array('success' => true, 'email' => $user_email);
            }
            
            // Get metadata
            $user_email = isset($session->metadata->user_email) ? $session->metadata->user_email : $session->customer_email;
            $first_name = isset($session->metadata->first_name) ? $session->metadata->first_name : '';
            $last_name = isset($session->metadata->last_name) ? $session->metadata->last_name : '';
            
            // Get amount and currency
            $amount = $session->amount_total / 100;
            $currency = strtoupper($session->currency);
            
            // Record purchase
            $data = array(
                'report_id' => $report_id,
                'user_email' => $user_email,
                'stripe_session_id' => $session_id,
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
                return array('success' => true, 'email' => $user_email);
            } else {
                return array('success' => false, 'message' => 'Database error');
            }
            
        } catch (Exception $e) {
            error_log('Session verification error: ' . $e->getMessage());
            return array('success' => false, 'message' => $e->getMessage());
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
        $site_name = get_bloginfo('name');
        
        $subject = 'Purchase Confirmation - ' . $report_title;
        
        // Create HTML email
        $message = "<!DOCTYPE html><html><head><meta charset='UTF-8'></head><body style='font-family: Arial, sans-serif; line-height: 1.6; color: #333;'>";
        $message .= "<div style='max-width: 600px; margin: 0 auto; padding: 20px;'>";
        $message .= "<h2 style='color: #2271b1;'>Hi " . esc_html($first_name) . ",</h2>";
        $message .= "<p style='font-size: 16px;'>Thank you for your purchase!</p>";
        $message .= "<div style='background: #f9f9f9; padding: 20px; border-radius: 5px; margin: 20px 0;'>";
        $message .= "<p style='margin: 0 0 10px 0;'><strong>Report:</strong> " . esc_html($report_title) . "</p>";
        $message .= "</div>";
        $message .= "<p>You can access your report at any time:</p>";
        $message .= "<div style='text-align: center; margin: 30px 0;'>";
        $message .= "<a href='" . esc_url($report_url) . "' style='display: inline-block; background: #2271b1; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 0 10px 10px 0;'>View Report Page</a>";
        $message .= "<a href='" . esc_url($download_link) . "' style='display: inline-block; background: #28a745; color: white; padding: 15px 30px; text-decoration: none; border-radius: 5px; font-weight: bold; margin: 0 10px 10px 0;'>Download Now</a>";
        $message .= "</div>";
        $message .= "<p style='font-size: 14px; color: #666;'>Direct download link: <a href='" . esc_url($download_link) . "'>" . esc_url($download_link) . "</a></p>";
        $message .= "<hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>";
        $message .= "<p style='font-size: 12px; color: #666;'>These links will remain active for your records. If you have any questions, please don't hesitate to contact us.</p>";
        $message .= "<p style='font-size: 12px; color: #666;'>Best regards,<br>" . esc_html($site_name) . "</p>";
        $message .= "</div></body></html>";
        
        $headers = array(
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <noreply@' . str_replace('www.', '', parse_url(home_url(), PHP_URL_HOST)) . '>'
        );
        
        $sent = wp_mail($email, $subject, $message, $headers);
        
        if (!$sent) {
            error_log('Failed to send purchase confirmation email to: ' . $email);
        }
        
        return $sent;
    }
}
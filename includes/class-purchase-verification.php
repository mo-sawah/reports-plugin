<?php
/**
 * Purchase Verification Handler
 * 
 * Verifies if a user has purchased a specific report
 */

if (!defined('ABSPATH')) {
    exit;
}

class RP_Purchase_Verification {
    
    /**
     * Verify if a user has purchased a report
     * 
     * @param string $email User email
     * @param int $report_id Report post ID
     * @return bool True if purchased, false otherwise
     */
    public function verify_purchase($email, $report_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reports_purchases';
        
        if (!is_email($email) || !$report_id) {
            return false;
        }
        
        $purchase = $wpdb->get_row($wpdb->prepare(
            "SELECT id FROM $table_name WHERE user_email = %s AND report_id = %d",
            $email,
            $report_id
        ));
        
        return ($purchase !== null);
    }
    
    /**
     * Get purchase details
     * 
     * @param string $email User email
     * @param int $report_id Report post ID
     * @return object|null Purchase details or null
     */
    public function get_purchase_details($email, $report_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reports_purchases';
        
        if (!is_email($email) || !$report_id) {
            return null;
        }
        
        return $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_email = %s AND report_id = %d",
            $email,
            $report_id
        ));
    }
    
    /**
     * Increment download count
     * 
     * @param string $email User email
     * @param int $report_id Report post ID
     * @return bool Success or failure
     */
    public function increment_download_count($email, $report_id) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reports_purchases';
        
        if (!is_email($email) || !$report_id) {
            return false;
        }
        
        $result = $wpdb->query($wpdb->prepare(
            "UPDATE $table_name SET download_count = download_count + 1 WHERE user_email = %s AND report_id = %d",
            $email,
            $report_id
        ));
        
        return ($result !== false);
    }
    
    /**
     * Get all purchases by email
     * 
     * @param string $email User email
     * @return array Array of purchase objects
     */
    public function get_user_purchases($email) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'reports_purchases';
        
        if (!is_email($email)) {
            return array();
        }
        
        return $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $table_name WHERE user_email = %s ORDER BY purchase_date DESC",
            $email
        ));
    }
}
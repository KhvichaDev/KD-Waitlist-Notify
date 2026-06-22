<?php
/**
 * Backend AJAX handler for subscriber signup.
 * Validates requests, verifies nonces, and inserts subscriber records with custom fields.
 */

if (!defined('ABSPATH')) {
    exit;
}

class kd_Signup_Handler {
    /**
     * Set up hooks for processing AJAX requests.
     */
    public function __construct() {
        add_action('wp_ajax_nopriv_kd_early_bird_signup', array($this, 'kd_handle_signup_request'));
        add_action('wp_ajax_kd_early_bird_signup', array($this, 'kd_handle_signup_request'));
        add_action('wp_ajax_nopriv_kd_refresh_signup_nonce', array($this, 'kd_refresh_signup_nonce'));
        add_action('wp_ajax_kd_refresh_signup_nonce', array($this, 'kd_refresh_signup_nonce'));
    }

    /**
     * Process the frontend AJAX signup request.
     */
    public function kd_handle_signup_request() {
        // Verify security nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST['nonce'])), 'kd_signup_nonce')) {
            wp_send_json_error(array(
                'message' => __('Security check failed. Please refresh the page.', 'kd-earlybird-notify')
            ));
        }

        $service_id = isset($_POST['service_id']) ? (int) $_POST['service_id'] : 1;

        // Honeypot spam bot check
        if (!empty($_POST['kd_hp_email'])) {
            $form_texts = kd_Database::kd_get_form_texts($service_id);
            wp_send_json_success(array(
                'message' => $form_texts['success_msg']
            ));
        }

        // Get fields configurations
        $fields_config = kd_Database::kd_get_fields_config($service_id);
        $email_enabled   = isset($fields_config['email']['enabled']) ? (bool) $fields_config['email']['enabled'] : true;
        $email_required  = isset($fields_config['email']['required']) ? (bool) $fields_config['email']['required'] : true;

        $phone_enabled   = isset($fields_config['phone']['enabled']) ? (bool) $fields_config['phone']['enabled'] : false;
        $phone_required  = isset($fields_config['phone']['required']) ? (bool) $fields_config['phone']['required'] : false;

        $whatsapp_enabled  = isset($fields_config['whatsapp']['enabled']) ? (bool) $fields_config['whatsapp']['enabled'] : false;
        $whatsapp_required = isset($fields_config['whatsapp']['required']) ? (bool) $fields_config['whatsapp']['required'] : false;
        $consent_enabled   = isset($fields_config['consent_enabled']) ? (bool) $fields_config['consent_enabled'] : false;

        // Validate Consent Checkbox if enabled
        if ($consent_enabled && (!isset($_POST['kd_notification_consent']) || $_POST['kd_notification_consent'] !== '1')) {
            wp_send_json_error(array('message' => __('You must agree to receive launch notifications.', 'kd-earlybird-notify')));
        }

        // Validate and sanitize Name (always required)
        $name = isset($_POST['name']) ? sanitize_text_field(wp_unslash($_POST['name'])) : '';
        if (empty($name)) {
            wp_send_json_error(array('message' => __('Please enter your name.', 'kd-earlybird-notify')));
        }
        if (mb_strlen($name) > 100) {
            wp_send_json_error(array('message' => __('Name cannot exceed 100 characters.', 'kd-earlybird-notify')));
        }

        // Validate and sanitize Email
        $email = isset($_POST['email']) ? sanitize_email(wp_unslash($_POST['email'])) : '';
        if ($email_enabled) {
            if ($email_required && empty($email)) {
                wp_send_json_error(array('message' => __('Email address is required.', 'kd-earlybird-notify')));
            }
            if (!empty($email) && !is_email($email)) {
                wp_send_json_error(array('message' => __('Please enter a valid email address.', 'kd-earlybird-notify')));
            }
            if (mb_strlen($email) > 100) {
                wp_send_json_error(array('message' => __('Email address cannot exceed 100 characters.', 'kd-earlybird-notify')));
            }
            if (!empty($email) && kd_Database::kd_subscriber_exists($email, $service_id)) {
                wp_send_json_error(array('message' => __('This email address is already registered.', 'kd-earlybird-notify')));
            }
        } else {
            $email = ''; // Reset if not enabled
        }

        // Validate and sanitize Phone
        $phone = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
        if ($phone_enabled) {
            if ($phone_required && empty($phone)) {
                wp_send_json_error(array('message' => __('Phone number is required.', 'kd-earlybird-notify')));
            }
            if (!empty($phone)) {
                $default_code = isset($fields_config['default_country_code']) ? $fields_config['default_country_code'] : '+995';
                $normalized_phone = $this->kd_normalize_phone($phone, $default_code);
                if ($normalized_phone === false) {
                    wp_send_json_error(array('message' => __('Please enter a valid phone number (e.g. +995599123456).', 'kd-earlybird-notify')));
                }
                if (mb_strlen($normalized_phone) > 50) {
                    wp_send_json_error(array('message' => __('Phone number cannot exceed 50 characters.', 'kd-earlybird-notify')));
                }
                $phone = $normalized_phone;
            }
            if (!empty($phone) && kd_Database::kd_subscriber_exists_by_phone($phone, $service_id)) {
                wp_send_json_error(array('message' => __('This phone number is already registered.', 'kd-earlybird-notify')));
            }
        } else {
            $phone = ''; // Reset if not enabled
        }

        // Validate and sanitize WhatsApp
        $whatsapp = isset($_POST['whatsapp']) ? sanitize_text_field(wp_unslash($_POST['whatsapp'])) : '';
        if ($whatsapp_enabled) {
            if ($whatsapp_required && empty($whatsapp)) {
                wp_send_json_error(array('message' => __('WhatsApp number is required.', 'kd-earlybird-notify')));
            }
            if (!empty($whatsapp)) {
                $default_code = isset($fields_config['default_country_code']) ? $fields_config['default_country_code'] : '+995';
                $normalized_whatsapp = $this->kd_normalize_phone($whatsapp, $default_code);
                if ($normalized_whatsapp === false) {
                    wp_send_json_error(array('message' => __('Please enter a valid WhatsApp number (e.g. +995599123456).', 'kd-earlybird-notify')));
                }
                if (mb_strlen($normalized_whatsapp) > 50) {
                    wp_send_json_error(array('message' => __('WhatsApp number cannot exceed 50 characters.', 'kd-earlybird-notify')));
                }
                $whatsapp = $normalized_whatsapp;
            }
            if (!empty($whatsapp) && kd_Database::kd_subscriber_exists_by_whatsapp($whatsapp, $service_id)) {
                wp_send_json_error(array('message' => __('This WhatsApp number is already registered.', 'kd-earlybird-notify')));
            }
        } else {
            $whatsapp = ''; // Reset if not enabled
        }

        // Save new subscriber to the database
        $inserted = kd_Database::kd_add_subscriber($name, $email, $phone, $whatsapp, $service_id);

        if ($inserted) {
            $form_texts = kd_Database::kd_get_form_texts($service_id);
            wp_send_json_success(array(
                'message' => $form_texts['success_msg']
            ));
        } else {
            wp_send_json_error(array(
                'message' => __('Failed to register. Please try again later.', 'kd-earlybird-notify')
            ));
        }
    }

    /**
     * Refresh signup nonce dynamically to bypass page caching plugins.
     */
    public function kd_refresh_signup_nonce() {
        wp_send_json_success(array(
            'nonce' => wp_create_nonce('kd_signup_nonce')
        ));
    }

    /**
     * Normalize and validate a phone number according to E.164 format rules.
     *
     * @param string $phone Raw phone number.
     * @param string $default_code Default country code (e.g. '+995').
     * @return string|false Normalized phone number, or false if invalid.
     */
    private function kd_normalize_phone($phone, $default_code = '+995') {
        // Remove all characters except digits and plus sign
        $phone = preg_replace('/[^\d+]/', '', $phone);

        if (empty($phone)) {
            return '';
        }

        // If it doesn't start with '+', process default country code prefixing
        if (strpos($phone, '+') !== 0) {
            // Strip leading zeros
            $phone = ltrim($phone, '0');
            
            // Ensure default code starts with '+'
            if (strpos($default_code, '+') !== 0) {
                $default_code = '+' . $default_code;
            }
            $phone = $default_code . $phone;
        }

        // Validate E.164 format (plus followed by 7 to 15 digits)
        if (!preg_match('/^\+[1-9]\d{6,14}$/', $phone)) {
            return false;
        }

        return $phone;
    }
}

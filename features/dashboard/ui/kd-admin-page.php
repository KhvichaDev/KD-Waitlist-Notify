<?php
/**
 * Admin dashboard renderer class.
 * Outputs the HTML markup for stats, subscribers lists (with filters),
 * email/SMS/WhatsApp campaign runners (with manual and auto views),
 * and the field/gateway settings forms.
 */

if (!defined('ABSPATH')) {
    exit;
}

class kd_Admin_Page {
    /**
     * Renders the administrative dashboard interface.
     */
    public static function kd_render_dashboard() {
        if (!current_user_can('manage_options')) {
            return;
        }

        global $wpdb;
        $table_name = kd_Database::kd_get_table_name();

        $services = kd_Database::kd_get_services();

        $active_service_id = isset($_GET['service_id']) ? (int) $_GET['service_id'] : 1;
        if (!isset($services[$active_service_id])) {
            $active_service_id = !empty($services) ? min(array_keys($services)) : 1;
        }

        // Handle saving settings form
        $settings_saved = false;
        if (isset($_POST['kd_save_settings_nonce'])) {
            $nonce = sanitize_key(wp_unslash($_POST['kd_save_settings_nonce']));
            if (wp_verify_nonce($nonce, 'kd_save_settings_action')) {
                // Save global option for delete data on uninstall
                update_option('kd_eb_delete_data_on_uninstall', isset($_POST['delete_data_on_uninstall']) ? 1 : 0);

                // Save form fields visibility and requirements configurations
                $fields_config = array(
                    'email' => array(
                        'enabled' => isset($_POST['field_email_enabled']),
                        'required' => isset($_POST['field_email_required'])
                    ),
                    'phone' => array(
                        'enabled' => isset($_POST['field_phone_enabled']),
                        'required' => isset($_POST['field_phone_required'])
                    ),
                    'whatsapp' => array(
                        'enabled' => isset($_POST['field_whatsapp_enabled']),
                        'required' => isset($_POST['field_whatsapp_required'])
                    ),
                    'default_country_code'  => isset($_POST['default_country_code']) ? sanitize_text_field(wp_unslash($_POST['default_country_code'])) : '',
                    'show_subscriber_count' => isset($_POST['show_subscriber_count']),
                    'consent_enabled'       => isset($_POST['consent_enabled'])
                );
                $option_suffix = ($active_service_id > 1) ? '_' . $active_service_id : '';
                update_option('kd_eb_fields_config' . $option_suffix, $fields_config);

                // Save gateway configurations for Twilio and Custom HTTP Gateways
                $existing_gateway_config = kd_Database::kd_get_gateway_config($active_service_id);
                $existing_token = isset($existing_gateway_config['twilio_token']) ? $existing_gateway_config['twilio_token'] : '';
                $submitted_token = isset($_POST['twilio_token']) ? sanitize_text_field(wp_unslash($_POST['twilio_token'])) : '';
                if (empty($submitted_token) || strpos($submitted_token, '•') !== false) {
                    $twilio_token = $existing_token;
                } else {
                    $twilio_token = $submitted_token;
                }

                $gateway_config = array(
                    'twilio_sid'      => isset($_POST['twilio_sid']) ? sanitize_text_field(wp_unslash($_POST['twilio_sid'])) : '',
                    'twilio_token'    => $twilio_token,
                    'twilio_sms_from' => isset($_POST['twilio_sms_from']) ? sanitize_text_field(wp_unslash($_POST['twilio_sms_from'])) : '',
                    'twilio_wa_from'  => isset($_POST['twilio_wa_from']) ? sanitize_text_field(wp_unslash($_POST['twilio_wa_from'])) : '',
                    'custom_sms_url'  => isset($_POST['custom_sms_url']) ? sanitize_text_field(wp_unslash($_POST['custom_sms_url'])) : '',
                    'custom_wa_url'   => isset($_POST['custom_wa_url']) ? sanitize_text_field(wp_unslash($_POST['custom_wa_url'])) : ''
                );
                update_option('kd_eb_gateway_config' . $option_suffix, $gateway_config);

                // Save customizable frontend form texts
                $form_texts_saved = array(
                    'form_title'        => isset($_POST['form_title']) ? sanitize_text_field(wp_unslash($_POST['form_title'])) : '',
                    'form_subtitle'     => isset($_POST['form_subtitle']) ? sanitize_text_field(wp_unslash($_POST['form_subtitle'])) : '',
                    'name_label'        => isset($_POST['name_label']) ? sanitize_text_field(wp_unslash($_POST['name_label'])) : '',
                    'email_label'       => isset($_POST['email_label']) ? sanitize_text_field(wp_unslash($_POST['email_label'])) : '',
                    'phone_label'       => isset($_POST['phone_label']) ? sanitize_text_field(wp_unslash($_POST['phone_label'])) : '',
                    'whatsapp_label'    => isset($_POST['whatsapp_label']) ? sanitize_text_field(wp_unslash($_POST['whatsapp_label'])) : '',
                    'submit_btn'        => isset($_POST['submit_btn']) ? sanitize_text_field(wp_unslash($_POST['submit_btn'])) : '',
                    'success_title'     => isset($_POST['success_title']) ? sanitize_text_field(wp_unslash($_POST['success_title'])) : '',
                    'success_msg'       => isset($_POST['success_msg']) ? sanitize_text_field(wp_unslash($_POST['success_msg'])) : '',
                    'social_proof_text' => isset($_POST['social_proof_text']) ? sanitize_text_field(wp_unslash($_POST['social_proof_text'])) : '',
                    'badge_label'       => isset($_POST['badge_label']) ? sanitize_text_field(wp_unslash($_POST['badge_label'])) : '',
                    'consent_label'     => isset($_POST['consent_label']) ? sanitize_text_field(wp_unslash($_POST['consent_label'])) : ''
                );
                update_option('kd_eb_form_texts' . $option_suffix, $form_texts_saved);

                $settings_saved = true;
            }
        }

        // Fetch configurations
        $fields_config  = kd_Database::kd_get_fields_config($active_service_id);
        $gateway_config = kd_Database::kd_get_gateway_config($active_service_id);
        $form_texts     = kd_Database::kd_get_form_texts($active_service_id);

        // Filter and Search parameters
        $search = isset($_GET['s']) ? sanitize_text_field(wp_unslash($_GET['s'])) : '';
        $channel_filter = isset($_GET['channel']) ? sanitize_text_field(wp_unslash($_GET['channel'])) : '';

        // Pagination setup
        $limit = isset($_GET['limit']) ? max(1, (int) $_GET['limit']) : 20;
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $offset = ($paged - 1) * $limit;

        // Query subscribers list with search, channel and service filters
        $subscribers = kd_Database::kd_get_subscribers($limit, $offset, $search, $channel_filter, $active_service_id);
        $total_subscribers = kd_Database::kd_get_subscribers_count($search, $channel_filter, $active_service_id);
        $total_pages = ceil($total_subscribers / $limit);

        // Fetch dashboard general stats filtered by service_id
        $count_all = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}kd_subscribers WHERE service_id = %d", $active_service_id));
        $count_subscribed = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}kd_subscribers WHERE status = %s AND service_id = %d", 'subscribed', $active_service_id));
        $count_notified = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}kd_subscribers WHERE status = %s AND service_id = %d", 'notified', $active_service_id));
        $count_failed = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}kd_subscribers WHERE status = %s AND service_id = %d", 'failed', $active_service_id));

        $csv_nonce = wp_create_nonce('kd_export_csv_nonce');
        ?>
        <div class="kd-admin-wrap" data-service-id="<?php echo (int) $active_service_id; ?>">
            <?php if ($settings_saved) : ?>
                <div class="kd-admin-notice kd-notice-success">
                    <span class="dashicons dashicons-yes-alt"></span>
                    <span class="kd-notice-text"><?php esc_html_e('Settings updated successfully.', 'kd-earlybird-notify'); ?></span>
                    <button type="button" class="kd-notice-close-btn" onclick="this.parentElement.style.display='none';">
                        <span class="dashicons dashicons-dismiss"></span>
                    </button>
                </div>
            <?php endif; ?>
            <div class="kd-admin-header-row">
                <div class="kd-admin-title-area">
                    <h1 class="kd-admin-main-title"><?php esc_html_e('Early Bird Notify Dashboard', 'kd-earlybird-notify'); ?></h1>
                    <p class="kd-admin-tagline"><?php esc_html_e('Manage fields settings, database list, and multi-channel campaigns.', 'kd-earlybird-notify'); ?></p>
                </div>
                
                <div class="kd-header-actions" style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
                    <!-- Add Service Button -->
                    <button type="button" id="kd-add-service-trigger-btn" class="kd-admin-btn kd-btn-outline" style="height: 50px !important; padding: 0 15px !important;" title="<?php esc_attr_e('Add New Service', 'kd-earlybird-notify'); ?>">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg> <?php esc_html_e('Add Service', 'kd-earlybird-notify'); ?>
                    </button>

                    <!-- Service Switcher Selector -->
                    <?php
                    $active_service_desc = isset($services[$active_service_id]['description']) ? $services[$active_service_id]['description'] : '';
                    ?>
                    <select id="kd-service-selector" class="kd-admin-select" style="width: 220px !important; height: 50px !important; margin: 0 !important;" title="<?php echo esc_attr($active_service_desc); ?>">
                        <?php foreach ($services as $s_id => $s_data) : ?>
                            <option value="<?php echo (int) $s_id; ?>" <?php selected($s_id, $active_service_id); ?> title="<?php echo esc_attr($s_data['description']); ?>">
                                <?php echo esc_html($s_data['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Delete Service Button (Only for custom services) -->
                    <?php if ($active_service_id > 1) : ?>
                        <button type="button" id="kd-delete-service-btn" class="kd-admin-btn" style="height: 50px !important; padding: 0 15px !important; background: rgba(239, 68, 68, 0.15) !important; border: 1px solid rgba(239, 68, 68, 0.3) !important; color: #f87171 !important;" title="<?php esc_attr_e('Delete Active Service', 'kd-earlybird-notify'); ?>">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 18px; height: 18px; color: #f87171 !important;">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                            </svg> <?php esc_html_e('Delete Service', 'kd-earlybird-notify'); ?>
                        </button>
                    <?php endif; ?>

                    <!-- Export CSV Button -->
                    <a href="<?php echo esc_url(admin_url('admin.php?page=kd-early-bird&action=kd_export_csv&service_id=' . $active_service_id . '&_wpnonce=' . $csv_nonce)); ?>" class="kd-admin-btn kd-btn-outline" style="height: 50px !important;">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width: 18px; height: 18px;">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 005.25 21h13.5A2.25 2.25 0 0021 18.75V16.5M16.5 12L12 16.5m0 0L7.5 12m4.5 4.5V3" />
                        </svg> <?php esc_html_e('Export CSV', 'kd-earlybird-notify'); ?>
                    </a>
                </div>
            </div>


            <!-- Dashboard Stats Grid -->
            <div class="kd-stats-grid">
                <div class="kd-stat-card kd-stat-total">
                    <div class="kd-stat-icon">
                        <span class="dashicons dashicons-groups"></span>
                    </div>
                    <div class="kd-stat-info">
                        <span class="kd-stat-number"><?php echo esc_html($count_all); ?></span>
                        <span class="kd-stat-label"><?php esc_html_e('Total Subscribers', 'kd-earlybird-notify'); ?></span>
                    </div>
                </div>

                <div class="kd-stat-card kd-stat-pending">
                    <div class="kd-stat-icon">
                        <span class="dashicons dashicons-clock"></span>
                    </div>
                    <div class="kd-stat-info">
                        <span class="kd-stat-number" id="kd-count-subscribed"><?php echo esc_html($count_subscribed); ?></span>
                        <span class="kd-stat-label"><?php esc_html_e('Subscribed (Unnotified)', 'kd-earlybird-notify'); ?></span>
                    </div>
                </div>

                <div class="kd-stat-card kd-stat-completed">
                    <div class="kd-stat-icon">
                        <span class="dashicons dashicons-email-alt"></span>
                    </div>
                    <div class="kd-stat-info">
                        <span class="kd-stat-number" id="kd-count-notified"><?php echo esc_html($count_notified); ?></span>
                        <span class="kd-stat-label"><?php esc_html_e('Notified', 'kd-earlybird-notify'); ?></span>
                    </div>
                </div>

                <div class="kd-stat-card kd-stat-failed">
                    <div class="kd-stat-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <div class="kd-stat-info">
                        <span class="kd-stat-number" id="kd-count-failed"><?php echo esc_html($count_failed); ?></span>
                        <span class="kd-stat-label"><?php esc_html_e('Delivery Failed', 'kd-earlybird-notify'); ?></span>
                    </div>
                </div>
            </div>

            <!-- Tabs Navigation -->
            <h2 class="nav-tab-wrapper kd-nav-tab-wrapper">
                <a href="#subscribers-tab" class="nav-tab nav-tab-active" id="kd-tab-trigger-list"><?php esc_html_e('Subscribers List', 'kd-earlybird-notify'); ?></a>
                <a href="#campaign-tab" class="nav-tab" id="kd-tab-trigger-campaign"><?php esc_html_e('Notification Campaign', 'kd-earlybird-notify'); ?></a>
                <a href="#settings-tab" class="nav-tab" id="kd-tab-trigger-settings"><?php esc_html_e('Form & Gateway Settings', 'kd-earlybird-notify'); ?></a>
            </h2>

            <div class="kd-tab-container">
                <!-- Tab 1: Subscribers list -->
                <div id="subscribers-tab" class="kd-tab-content kd-tab-content-active">
                    <div class="kd-list-toolbar">
                        <form method="get" action="<?php echo esc_url(admin_url('admin.php')); ?>" class="kd-search-form">
                            <input type="hidden" name="page" value="kd-early-bird" />
                            <input type="hidden" name="service_id" value="<?php echo (int) $active_service_id; ?>" />
                            <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="<?php esc_attr_e('Search database...', 'kd-earlybird-notify'); ?>" class="kd-search-input" />
                            
                            <select name="channel" class="kd-search-input" style="min-width: 150px;" onchange="this.form.submit();">
                                <option value=""><?php esc_html_e('All Channels', 'kd-earlybird-notify'); ?></option>
                                <option value="email" <?php selected($channel_filter, 'email'); ?>><?php esc_html_e('With Email', 'kd-earlybird-notify'); ?></option>
                                <option value="phone" <?php selected($channel_filter, 'phone'); ?>><?php esc_html_e('With Phone', 'kd-earlybird-notify'); ?></option>
                                <option value="whatsapp" <?php selected($channel_filter, 'whatsapp'); ?>><?php esc_html_e('With WhatsApp', 'kd-earlybird-notify'); ?></option>
                            </select>

                            <select name="limit" class="kd-search-input" style="min-width: 110px;" onchange="this.form.submit();" title="<?php esc_attr_e('Subscribers per page', 'kd-earlybird-notify'); ?>">
                                <option value="20" <?php selected($limit, 20); ?>>
                                    <?php
                                    /* translators: %d: number of items per page */
                                    echo esc_html( sprintf( __( '%d / page', 'kd-earlybird-notify' ), 20 ) );
                                    ?>
                                </option>
                                <option value="50" <?php selected($limit, 50); ?>>
                                    <?php
                                    /* translators: %d: number of items per page */
                                    echo esc_html( sprintf( __( '%d / page', 'kd-earlybird-notify' ), 50 ) );
                                    ?>
                                </option>
                                <option value="100" <?php selected($limit, 100); ?>>
                                    <?php
                                    /* translators: %d: number of items per page */
                                    echo esc_html( sprintf( __( '%d / page', 'kd-earlybird-notify' ), 100 ) );
                                    ?>
                                </option>
                            </select>

                            <?php if (!empty($search) || !empty($channel_filter) || isset($_GET['limit'])) : ?>
                                <a href="<?php echo esc_url(admin_url('admin.php?page=kd-early-bird&service_id=' . $active_service_id)); ?>" class="kd-admin-btn kd-btn-outline"><?php esc_html_e('Clear', 'kd-earlybird-notify'); ?></a>
                            <?php endif; ?>
                        </form>

                        <div style="display: flex; gap: 0.6rem; align-items: center; flex-wrap: wrap;">
                            <button id="kd-reset-status-btn" class="kd-admin-btn kd-btn-warning" <?php echo ($count_notified === 0 && $count_failed === 0) ? 'style="display: none;"' : ''; ?>>
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width: 18px; height: 18px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" />
                                </svg> <?php esc_html_e('Reset Notified to Subscribed', 'kd-earlybird-notify'); ?>
                            </button>

                            <?php if ($count_all > 0) : ?>
                                <button id="kd-delete-all-btn" class="kd-admin-btn kd-btn-danger">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width: 18px; height: 18px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                                    </svg> <?php esc_html_e('Delete All Subscribers', 'kd-earlybird-notify'); ?>
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="kd-table-wrapper">
                        <table class="wp-list-table widefat fixed striped table-view-list kd-data-table">
                            <thead>
                                <tr>
                                    <th scope="col" class="manage-column"><?php esc_html_e('Name', 'kd-earlybird-notify'); ?></th>
                                    <th scope="col" class="manage-column"><?php esc_html_e('Email', 'kd-earlybird-notify'); ?></th>
                                    <th scope="col" class="manage-column"><?php esc_html_e('Phone (SMS)', 'kd-earlybird-notify'); ?></th>
                                    <th scope="col" class="manage-column"><?php esc_html_e('WhatsApp', 'kd-earlybird-notify'); ?></th>
                                    <th scope="col" class="manage-column"><?php esc_html_e('Status', 'kd-earlybird-notify'); ?></th>
                                    <th scope="col" class="manage-column"><?php esc_html_e('Registered Date', 'kd-earlybird-notify'); ?></th>
                                    <th scope="col" class="manage-column" style="width: 130px;"><?php esc_html_e('Actions', 'kd-earlybird-notify'); ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($subscribers)) : ?>
                                    <?php foreach ($subscribers as $row) : 
                                        if ($row['status'] === 'notified') {
                                            $status_class = 'kd-badge-notified';
                                        } elseif ($row['status'] === 'failed') {
                                            $status_class = 'kd-badge-failed';
                                        } else {
                                            $status_class = 'kd-badge-subscribed';
                                        }
                                        ?>
                                        <tr id="kd-subscriber-row-<?php echo (int) $row['id']; ?>">
                                            <td class="font-weight-bold">
                                                <strong><?php echo esc_html($row['name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php if (!empty($row['email'])) : ?>
                                                    <a href="mailto:<?php echo esc_attr($row['email']); ?>"><?php echo esc_html($row['email']); ?></a>
                                                <?php else : ?>
                                                    <span class="kd-field-empty">—</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php echo !empty($row['phone']) ? esc_html($row['phone']) : '<span class="kd-field-empty">—</span>'; ?>
                                            </td>
                                            <td>
                                                <?php echo !empty($row['whatsapp']) ? esc_html($row['whatsapp']) : '<span class="kd-field-empty">—</span>'; ?>
                                            </td>
                                            <td>
                                                <span class="kd-status-badge <?php echo esc_attr($status_class); ?>">
                                                    <?php echo esc_html(ucfirst($row['status'])); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php echo esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), strtotime($row['created_at']))); ?>
                                            </td>
                                            <td>
                                                <button class="kd-delete-btn" data-id="<?php echo (int) $row['id']; ?>" title="<?php esc_attr_e('Delete subscriber', 'kd-earlybird-notify'); ?>">
                                                    <span class="dashicons dashicons-trash"></span> <?php esc_html_e('Delete', 'kd-earlybird-notify'); ?>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else : ?>
                                    <tr>
                                        <td colspan="7" style="text-align: center; padding: 2rem;"><?php esc_html_e('No subscribers found.', 'kd-earlybird-notify'); ?></td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination Links -->
                    <?php if ($total_pages > 1) : ?>
                        <div class="kd-pagination">
                            <?php
                            echo wp_kses_post(
                                paginate_links(array(
                                    'base'      => add_query_arg('paged', '%#%'),
                                    'format'    => '',
                                    'prev_text' => __('&laquo; Previous', 'kd-earlybird-notify'),
                                    'next_text' => __('Next &raquo;', 'kd-earlybird-notify'),
                                    'total'     => $total_pages,
                                    'current'   => $paged,
                                    'type'      => 'plain',
                                ))
                            );
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Tab 2: Notification Campaign -->
                <div id="campaign-tab" class="kd-tab-content">
                    <div class="kd-campaign-layout">
                        <div class="kd-campaign-form-card">
                            <h3 class="kd-card-title"><?php esc_html_e('Send Broadcast Notifications', 'kd-earlybird-notify'); ?></h3>
                            <p class="kd-card-description"><?php esc_html_e('Compose launch notifications and broadcast them to subscribers over email, SMS, or WhatsApp channels.', 'kd-earlybird-notify'); ?></p>

                            <form id="kd-campaign-form">
                                <div class="kd-admin-form-group">
                                    <label for="kd-campaign-channel" class="kd-admin-label"><?php esc_html_e('Delivery Channel', 'kd-earlybird-notify'); ?></label>
                                    <select id="kd-campaign-channel" class="kd-admin-select">
                                        <optgroup label="<?php esc_attr_e('Automated (via Gateways)', 'kd-earlybird-notify'); ?>">
                                            <option value="email" selected><?php esc_html_e('Email (wp_mail)', 'kd-earlybird-notify'); ?></option>
                                            <option value="sms"><?php esc_html_e('SMS (Twilio)', 'kd-earlybird-notify'); ?></option>
                                            <option value="whatsapp"><?php esc_html_e('WhatsApp (Twilio)', 'kd-earlybird-notify'); ?></option>
                                            <option value="custom_sms"><?php esc_html_e('Automated SMS (via Custom HTTP Gateway)', 'kd-earlybird-notify'); ?></option>
                                            <option value="custom_whatsapp"><?php esc_html_e('Automated WhatsApp (via Custom HTTP Gateway)', 'kd-earlybird-notify'); ?></option>
                                        </optgroup>
                                        <optgroup label="<?php esc_attr_e('Manual (Free Gateways)', 'kd-earlybird-notify'); ?>">
                                            <option value="manual_sms"><?php esc_html_e('Manual SMS (via Device Link)', 'kd-earlybird-notify'); ?></option>
                                            <option value="manual_whatsapp"><?php esc_html_e('Manual WhatsApp (via WhatsApp Web)', 'kd-earlybird-notify'); ?></option>
                                            <option value="manual_whatsapp_app"><?php esc_html_e('Manual WhatsApp (via Desktop App - No Reload)', 'kd-earlybird-notify'); ?></option>
                                        </optgroup>
                                    </select>
                                    <p class="kd-field-desc"><?php esc_html_e('Notifications will be sent only to subscribers who registered this specific channel.', 'kd-earlybird-notify'); ?></p>
                                </div>

                                <div class="kd-admin-form-group" id="kd-subject-group">
                                    <label for="kd-campaign-subject" class="kd-admin-label"><?php esc_html_e('Email Subject', 'kd-earlybird-notify'); ?></label>
                                    <input type="text" id="kd-campaign-subject" class="kd-admin-input" placeholder="<?php esc_attr_e('Our application is officially live!', 'kd-earlybird-notify'); ?>" />
                                </div>

                                <div class="kd-admin-form-group">
                                    <label for="kd-campaign-message" class="kd-admin-label" id="kd-message-label"><?php esc_html_e('Email Body (HTML supported)', 'kd-earlybird-notify'); ?></label>
                                    <textarea id="kd-campaign-message" class="kd-admin-textarea" rows="10" placeholder="<?php esc_attr_e("Hi {name},\n\nWe are excited to announce that our app is ready! Click the link below to get started...", 'kd-earlybird-notify'); ?>" required></textarea>
                                </div>

                                <div class="kd-admin-form-group" id="kd-batch-size-group">
                                    <label for="kd-campaign-batch-size" class="kd-admin-label"><?php esc_html_e('Batch Size (Subscribers per request)', 'kd-earlybird-notify'); ?></label>
                                    <select id="kd-campaign-batch-size" class="kd-admin-select">
                                        <option value="5">
                                            <?php
                                            /* translators: %d: number of subscribers */
                                            echo esc_html( sprintf( __( '%d subscribers (Slow / API limits)', 'kd-earlybird-notify' ), 5 ) );
                                            ?>
                                        </option>
                                        <option value="15" selected>
                                            <?php
                                            /* translators: %d: number of subscribers */
                                            echo esc_html( sprintf( __( '%d subscribers (Recommended)', 'kd-earlybird-notify' ), 15 ) );
                                            ?>
                                        </option>
                                        <option value="30">
                                            <?php
                                            /* translators: %d: number of subscribers */
                                            echo esc_html( sprintf( __( '%d subscribers (Fast VPS / SMTP)', 'kd-earlybird-notify' ), 30 ) );
                                            ?>
                                        </option>
                                        <option value="50">
                                            <?php
                                            /* translators: %d: number of subscribers */
                                            echo esc_html( sprintf( __( '%d subscribers (High performance)', 'kd-earlybird-notify' ), 50 ) );
                                            ?>
                                        </option>
                                    </select>
                                    <p class="kd-field-desc"><?php esc_html_e('Prevents timeouts during bulk operations.', 'kd-earlybird-notify'); ?></p>
                                </div>

                                <button type="submit" class="kd-admin-btn kd-btn-primary kd-btn-large" id="kd-start-campaign-btn">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width: 18px; height: 18px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" />
                                    </svg> <?php esc_html_e('Send Broadcast Campaign', 'kd-earlybird-notify'); ?>
                                </button>
                            </form>
                        </div>

                        <!-- Sidebar Tips -->
                        <div class="kd-campaign-sidebar">
                            <div class="kd-sidebar-card">
                                <h4 class="kd-sidebar-title"><?php esc_html_e('Dynamic Personalization', 'kd-earlybird-notify'); ?></h4>
                                <p><?php esc_html_e('You can use the following tags in your text. They will be resolved per subscriber:', 'kd-earlybird-notify'); ?></p>
                                <ul class="kd-placeholder-list">
                                    <li><code>{name}</code> - <?php esc_html_e("Inserts subscriber's full name", 'kd-earlybird-notify'); ?></li>
                                    <li><code>{email}</code> - <?php esc_html_e("Inserts subscriber's email", 'kd-earlybird-notify'); ?></li>
                                </ul>
                                
                                <div class="kd-sidebar-alert" style="margin-top: 15px;">
                                    <strong><?php esc_html_e('Important Note:', 'kd-earlybird-notify'); ?></strong>
                                    <div style="margin-top: 5px;" id="kd-channel-disclaimer">
                                        <?php esc_html_e('Email campaigns send using standard WordPress mailers. Configure an SMTP plugin to improve inbox delivery rates.', 'kd-earlybird-notify'); ?>
                                    </div>
                                </div>
                            </div>

                            <!-- New Clipboard Copier Card -->
                            <div class="kd-sidebar-card">
                                <h4 class="kd-sidebar-title"><?php esc_html_e('Clipboard Copier (Free)', 'kd-earlybird-notify'); ?></h4>
                                <p><?php esc_html_e('Copy all active phone/WhatsApp numbers to clipboard as a comma-separated list for easy importing into desktop softwares or broadcast groups:', 'kd-earlybird-notify'); ?></p>
                                <div style="display: flex; flex-direction: column; gap: 0.5rem; margin-top: 12px;">
                                    <button type="button" id="kd-copy-phones-btn" class="kd-admin-btn kd-btn-outline" style="justify-content: center; width: 100%;">
                                        <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Copy Pending Phones', 'kd-earlybird-notify'); ?>
                                    </button>
                                    <button type="button" id="kd-copy-was-btn" class="kd-admin-btn kd-btn-outline" style="justify-content: center; width: 100%;">
                                        <span class="dashicons dashicons-clipboard"></span> <?php esc_html_e('Copy Pending WhatsApps', 'kd-earlybird-notify'); ?>
                                    </button>
                                </div>
                                <div id="kd-copy-status-msg" style="display: none; font-size: 0.85rem; color: #10b981; margin-top: 10px; font-weight: 600; text-align: center;"><?php esc_html_e('Numbers copied!', 'kd-earlybird-notify'); ?></div>
                            </div>
                        </div>
                    </div>
                </div> <!-- Closes kd-campaign-layout -->
            </div> <!-- Closes campaign-tab -->

            <!-- Tab 3: Form & Gateway Settings -->
            <div id="settings-tab" class="kd-tab-content">
                        <form method="post" action="" class="kd-settings-layout">
                            <?php wp_nonce_field('kd_save_settings_action', 'kd_save_settings_nonce'); ?>

                            <?php if ($active_service_id === 1) : ?>
                                <div class="kd-info-banner" style="background: rgba(99, 102, 241, 0.08); border: 1px dashed rgba(99, 102, 241, 0.25); padding: 1.2rem 1.5rem; border-radius: 14px; color: #c7d2fe; display: flex; gap: 0.8rem; align-items: flex-start;">
                                    <span class="dashicons dashicons-info" style="font-size: 20px; width: 20px; height: 20px; color: #818cf8; flex-shrink: 0; margin-top: 2px;"></span>
                                    <div style="font-size: 0.95rem; line-height: 1.5;">
                                        <strong><?php esc_html_e('Default Service Settings:', 'kd-earlybird-notify'); ?></strong> <?php esc_html_e("These settings are global defaults. When you create any new service, these configurations will be copied automatically as its initial setup. You can then modify them individually from that service's dashboard.", 'kd-earlybird-notify'); ?>
                                    </div>
                                </div>
                            <?php else : ?>
                                <div class="kd-info-banner" style="background: rgba(245, 158, 11, 0.08); border: 1px dashed rgba(245, 158, 11, 0.25); padding: 1.2rem 1.5rem; border-radius: 14px; color: #fde047; display: flex; gap: 0.8rem; align-items: flex-start;">
                                    <span class="dashicons dashicons-info" style="font-size: 20px; width: 20px; height: 20px; color: #fbbf24; flex-shrink: 0; margin-top: 2px;"></span>
                                    <div style="font-size: 0.95rem; line-height: 1.5;">
                                        <strong><?php esc_html_e('Individual Campaign Settings:', 'kd-earlybird-notify'); ?></strong> <?php esc_html_e("This service was initialized with parameters cloned from the default service. You can now modify and customize them to define individual settings specifically for this service/product waitlist campaign.", 'kd-earlybird-notify'); ?>
                                    </div>
                                </div>
                            <?php endif; ?>

                            <!-- Shortcode Integration Card -->
                            <div class="kd-settings-card">
                                <h3 class="kd-card-title"><?php esc_html_e('Shortcodes & Integration', 'kd-earlybird-notify'); ?></h3>
                                <p class="kd-card-description"><?php esc_html_e('Copy these shortcodes and paste them into any page, post, or widget on your WordPress site to display the registration form or subscriber count for this service.', 'kd-earlybird-notify'); ?></p>
                                
                                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem; align-items: start;">
                                    <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 1.2rem; display: flex; flex-direction: column; justify-content: space-between;">
                                        <div>
                                            <h4 style="margin: 0 0 8px 0; font-size: 1rem; color: #ffffff; font-weight: 600;"><?php esc_html_e('1. Registration Form Shortcode', 'kd-earlybird-notify'); ?></h4>
                                            <p style="font-size: 0.85rem; color: #94a3b8; margin: 0 0 15px 0; line-height: 1.4;"><?php esc_html_e('Renders the early access signup form with the active fields and texts configured for this service.', 'kd-earlybird-notify'); ?></p>
                                        </div>
                                        <div style="display: flex; gap: 0.5rem; align-items: center; background: rgba(0, 0, 0, 0.2); padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.05);">
                                            <code style="color: #818cf8; font-family: monospace; font-size: 0.9rem; flex: 1; word-break: break-all;" id="kd-shortcode-form-code">[kd_early_bird_signup service="<?php echo esc_attr($services[$active_service_id]['name']); ?>"]</code>
                                            <button type="button" class="kd-admin-btn kd-btn-outline kd-copy-shortcode-btn" data-target="kd-shortcode-form-code" style="height: 32px !important; padding: 0 10px !important; font-size: 0.75rem; border-radius: 6px !important; flex-shrink: 0;" title="<?php esc_attr_e('Copy to clipboard', 'kd-earlybird-notify'); ?>"><?php esc_html_e('Copy', 'kd-earlybird-notify'); ?></button>
                                        </div>
                                    </div>
                                    
                                    <div style="background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 12px; padding: 1.2rem; display: flex; flex-direction: column; justify-content: space-between;">
                                        <div>
                                            <h4 style="margin: 0 0 8px 0; font-size: 1rem; color: #ffffff; font-weight: 600;"><?php esc_html_e('2. Subscriber Count Shortcode', 'kd-earlybird-notify'); ?></h4>
                                            <p style="font-size: 0.85rem; color: #94a3b8; margin: 0 0 15px 0; line-height: 1.4;"><?php esc_html_e('Displays the total number of subscribed users for this service as a beautifully styled badge. Add <code>format="raw"</code> to output only the plain number (e.g. <code>[kd_early_bird_subscriber_count service="..." format="raw"]</code>).', 'kd-earlybird-notify'); ?></p>
                                        </div>
                                        <div style="display: flex; gap: 0.5rem; align-items: center; background: rgba(0, 0, 0, 0.2); padding: 8px 12px; border-radius: 8px; border: 1px solid rgba(255, 255, 255, 0.05);">
                                            <code style="color: #34d399; font-family: monospace; font-size: 0.9rem; flex: 1; word-break: break-all;" id="kd-shortcode-count-code">[kd_early_bird_subscriber_count service="<?php echo esc_attr($services[$active_service_id]['name']); ?>"]</code>
                                            <button type="button" class="kd-admin-btn kd-btn-outline kd-copy-shortcode-btn" data-target="kd-shortcode-count-code" style="height: 32px !important; padding: 0 10px !important; font-size: 0.75rem; border-radius: 6px !important; flex-shrink: 0;" title="<?php esc_attr_e('Copy to clipboard', 'kd-earlybird-notify'); ?>"><?php esc_html_e('Copy', 'kd-earlybird-notify'); ?></button>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Fields Config Card -->
                            <div class="kd-settings-card">
                                <h3 class="kd-card-title"><?php esc_html_e('Form Field Requirements', 'kd-earlybird-notify'); ?></h3>
                                <p class="kd-card-description"><?php esc_html_e('Choose which fields to enable on your early bird registration form, and configure which ones are required.', 'kd-earlybird-notify'); ?></p>
                                
                                <table class="form-table kd-settings-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e('Field Name', 'kd-earlybird-notify'); ?></th>
                                            <th style="text-align: center; width: 120px;"><?php esc_html_e('Enabled', 'kd-earlybird-notify'); ?></th>
                                            <th style="text-align: center; width: 120px;"><?php esc_html_e('Required', 'kd-earlybird-notify'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><strong><?php esc_html_e('Full Name', 'kd-earlybird-notify'); ?></strong></td>
                                            <td style="text-align: center;"><span class="dashicons dashicons-yes-alt" style="color: #10b981;"></span></td>
                                            <td style="text-align: center;"><span class="dashicons dashicons-yes-alt" style="color: #10b981;"></span></td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php esc_html_e('Email Address', 'kd-earlybird-notify'); ?></strong></td>
                                            <td style="text-align: center;">
                                                <input type="checkbox" name="field_email_enabled" value="1" <?php checked(isset($fields_config['email']['enabled']) && $fields_config['email']['enabled']); ?> />
                                            </td>
                                            <td style="text-align: center;">
                                                <input type="checkbox" name="field_email_required" value="1" <?php checked(isset($fields_config['email']['required']) && $fields_config['email']['required']); ?> />
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php esc_html_e('Phone Number (SMS)', 'kd-earlybird-notify'); ?></strong></td>
                                            <td style="text-align: center;">
                                                <input type="checkbox" name="field_phone_enabled" value="1" <?php checked(isset($fields_config['phone']['enabled']) && $fields_config['phone']['enabled']); ?> />
                                            </td>
                                            <td style="text-align: center;">
                                                <input type="checkbox" name="field_phone_required" value="1" <?php checked(isset($fields_config['phone']['required']) && $fields_config['phone']['required']); ?> />
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong><?php esc_html_e('WhatsApp Number', 'kd-earlybird-notify'); ?></strong></td>
                                            <td style="text-align: center;">
                                                <input type="checkbox" name="field_whatsapp_enabled" value="1" <?php checked(isset($fields_config['whatsapp']['enabled']) && $fields_config['whatsapp']['enabled']); ?> />
                                            </td>
                                            <td style="text-align: center;">
                                                <input type="checkbox" name="field_whatsapp_required" value="1" <?php checked(isset($fields_config['whatsapp']['required']) && $fields_config['whatsapp']['required']); ?> />
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                                <p class="kd-field-desc" style="margin-top: 15px; color: #f59e0b;"><?php esc_html_e('* If multiple fields are enabled, the validation rules will enforce requirements according to these options.', 'kd-earlybird-notify'); ?></p>
                                
                                <div class="kd-admin-setting-separator" style="margin: 2rem 0 1.5rem 0; border-top: 1px dashed rgba(255, 255, 255, 0.08);"></div>
                                
                                <div class="kd-admin-setting-row" style="display: flex; align-items: center; justify-content: space-between; background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.06); padding: 1.2rem 1.5rem; border-radius: 12px; margin-bottom: 2rem;">
                                    <div>
                                        <h4 style="margin: 0 0 6px 0; font-size: 1rem; color: #ffffff; font-weight: 600;"><?php esc_html_e('Display Subscriber Count (Social Proof)', 'kd-earlybird-notify'); ?></h4>
                                        <p style="margin: 0; font-size: 0.85rem; color: #94a3b8; line-height: 1.4;"><?php esc_html_e('Show the total number of registered early birds directly inside the signup form header to build trust.', 'kd-earlybird-notify'); ?></p>
                                    </div>
                                    <div style="display: flex; align-items: center; padding-left: 1rem;">
                                        <input type="checkbox" name="show_subscriber_count" value="1" <?php checked(isset($fields_config['show_subscriber_count']) && $fields_config['show_subscriber_count']); ?> style="width: 20px; height: 20px; cursor: pointer; accent-color: #6366f1;" />
                                    </div>
                                </div>

                                <div class="kd-admin-setting-row" style="display: flex; align-items: center; justify-content: space-between; background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.06); padding: 1.2rem 1.5rem; border-radius: 12px; margin-bottom: 2rem;">
                                    <div>
                                        <h4 style="margin: 0 0 6px 0; font-size: 1rem; color: #ffffff; font-weight: 600;"><?php esc_html_e('Require Notification Consent Checkbox', 'kd-earlybird-notify'); ?></h4>
                                        <p style="margin: 0; font-size: 0.85rem; color: #94a3b8; line-height: 1.4;"><?php esc_html_e('Add a mandatory opt-in checkbox to the registration form requesting consent to send launch notifications.', 'kd-earlybird-notify'); ?></p>
                                    </div>
                                    <div style="display: flex; align-items: center; padding-left: 1rem;">
                                        <input type="checkbox" name="consent_enabled" value="1" <?php checked(isset($fields_config['consent_enabled']) && $fields_config['consent_enabled']); ?> style="width: 20px; height: 20px; cursor: pointer; accent-color: #6366f1;" />
                                    </div>
                                </div>

                                <div class="kd-admin-setting-row" style="display: flex; align-items: center; justify-content: space-between; background: rgba(255, 255, 255, 0.02); border: 1px solid rgba(255, 255, 255, 0.06); padding: 1.2rem 1.5rem; border-radius: 12px; margin-bottom: 2rem;">
                                    <div>
                                        <h4 style="margin: 0 0 6px 0; font-size: 1rem; color: #ffffff; font-weight: 600;"><?php esc_html_e('Delete Data on Plugin Deletion', 'kd-earlybird-notify'); ?></h4>
                                        <p style="margin: 0; font-size: 0.85rem; color: #f87171; line-height: 1.4;"><?php esc_html_e('Caution: If enabled, all subscribers, services, and configurations will be permanently deleted from the database when you delete the plugin.', 'kd-earlybird-notify'); ?></p>
                                    </div>
                                    <div style="display: flex; align-items: center; padding-left: 1rem;">
                                        <input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked((bool) get_option('kd_eb_delete_data_on_uninstall', false)); ?> style="width: 20px; height: 20px; cursor: pointer; accent-color: #6366f1;" />
                                    </div>
                                </div>
                                
                                <div class="kd-admin-form-group">
                                    <label for="default_country_code" class="kd-admin-label"><?php esc_html_e('Default Country Dialing Code', 'kd-earlybird-notify'); ?></label>
                                    <div style="display: flex; gap: 0.8rem; align-items: center; max-width: 450px;">
                                        <select id="default_country_code" name="default_country_code" class="kd-admin-select" style="max-width: 320px; flex: 1;">
                                            <?php 
                                            $countries = kd_Database::kd_get_countries_list();
                                            $default_code = isset($fields_config['default_country_code']) ? $fields_config['default_country_code'] : '+995';
                                            foreach ($countries as $c_key => $c_data) : ?>
                                                <option value="<?php echo esc_attr($c_data['code']); ?>" <?php selected($c_data['code'], $default_code); ?>>
                                                    <?php echo esc_html($c_data['name'] . ' (' . $c_data['code'] . ')'); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button type="button" id="kd-add-country-trigger-btn" class="kd-admin-btn kd-btn-outline" style="height: 50px; padding: 0 15px !important; flex-shrink: 0;" title="<?php esc_attr_e('Add Custom Country', 'kd-earlybird-notify'); ?>">
                                            <span class="dashicons dashicons-plus"></span> <?php esc_html_e('Add', 'kd-earlybird-notify'); ?>
                                        </button>
                                    </div>
                                    <div id="kd-add-country-box" style="display: none; margin-top: 12px; padding: 15px; background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; max-width: 450px;">
                                        <h4 style="margin: 0 0 10px 0; font-size: 0.95rem; color: #ffffff;"><?php esc_html_e('Add Custom Dial Code', 'kd-earlybird-notify'); ?></h4>
                                        <div style="display: flex; gap: 0.6rem; margin-bottom: 12px;">
                                            <div style="flex: 1;">
                                                <label class="kd-admin-label" style="font-size: 0.8rem; margin-bottom: 4px; display: block;"><?php esc_html_e('Initials (e.g. GE, US)', 'kd-earlybird-notify'); ?></label>
                                                <input type="text" id="kd-new-country-name" class="kd-admin-input" placeholder="GE" style="height: 40px !important; padding: 8px 12px !important;" maxlength="10" />
                                            </div>
                                            <div style="flex: 1;">
                                                <label class="kd-admin-label" style="font-size: 0.8rem; margin-bottom: 4px; display: block;"><?php esc_html_e('Dial Code (e.g. +995)', 'kd-earlybird-notify'); ?></label>
                                                <input type="text" id="kd-new-country-code" class="kd-admin-input" placeholder="+995" style="height: 40px !important; padding: 8px 12px !important;" />
                                            </div>
                                        </div>
                                        <div style="display: flex; gap: 0.5rem; justify-content: flex-end;">
                                            <button type="button" id="kd-cancel-country-btn" class="kd-admin-btn kd-btn-outline" style="height: 36px !important; padding: 0 12px !important; font-size: 0.85rem; border-radius: 8px !important;"><?php esc_html_e('Cancel', 'kd-earlybird-notify'); ?></button>
                                            <button type="button" id="kd-save-country-btn" class="kd-admin-btn kd-btn-primary" style="height: 36px !important; padding: 0 12px !important; font-size: 0.85rem; border-radius: 8px !important;"><?php esc_html_e('Add Code', 'kd-earlybird-notify'); ?></button>
                                        </div>
                                        <div id="kd-country-error-msg" style="display: none; color: #f87171; font-size: 0.8rem; margin-top: 8px; font-weight: 500;"></div>
                                    </div>
                                    <p class="kd-field-desc"><?php esc_html_e('This country code will be pre-selected for phone and WhatsApp input fields on the registration form.', 'kd-earlybird-notify'); ?></p>
                                </div>
                            </div>

                        <!-- Frontend Form Labels & Content Texts -->
                        <div class="kd-settings-card">
                            <h3 class="kd-card-title"><?php esc_html_e('Frontend Form Labels & Custom Texts', 'kd-earlybird-notify'); ?></h3>
                            <p class="kd-card-description"><?php esc_html_e('Customize all texts, placeholders, and descriptions displayed to subscribers on the early access registration form.', 'kd-earlybird-notify'); ?></p>

                            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 1.5rem;">
                                <div>
                                    <div class="kd-admin-form-group">
                                        <label for="form_title" class="kd-admin-label"><?php esc_html_e('Form Main Title', 'kd-earlybird-notify'); ?></label>
                                        <input type="text" id="form_title" name="form_title" class="kd-admin-input" value="<?php echo esc_attr($form_texts['form_title']); ?>" placeholder="<?php esc_attr_e('Get Early Access', 'kd-earlybird-notify'); ?>" required />
                                    </div>
                                    <div class="kd-admin-form-group">
                                        <label for="form_subtitle" class="kd-admin-label"><?php esc_html_e('Form Description Subtitle', 'kd-earlybird-notify'); ?></label>
                                        <textarea id="form_subtitle" name="form_subtitle" class="kd-admin-textarea" rows="3" style="min-height: 100px;" required><?php echo esc_textarea($form_texts['form_subtitle']); ?></textarea>
                                    </div>
                                    <div class="kd-admin-form-group" style="margin-bottom: 1.2rem;">
                                        <label for="submit_btn" class="kd-admin-label"><?php esc_html_e('Submit Button Label', 'kd-earlybird-notify'); ?></label>
                                        <input type="text" id="submit_btn" name="submit_btn" class="kd-admin-input" value="<?php echo esc_attr($form_texts['submit_btn']); ?>" placeholder="<?php esc_attr_e('Join Early Bird', 'kd-earlybird-notify'); ?>" required />
                                    </div>
                                    <div class="kd-admin-form-group" style="margin-bottom: 1.2rem;">
                                        <label for="social_proof_text" class="kd-admin-label"><?php esc_html_e('Form Subscriber Count Text', 'kd-earlybird-notify'); ?></label>
                                        <input type="text" id="social_proof_text" name="social_proof_text" class="kd-admin-input" value="<?php echo esc_attr($form_texts['social_proof_text']); ?>" placeholder="<?php esc_attr_e('Joined by {count} early birds', 'kd-earlybird-notify'); ?>" required />
                                        <p class="kd-field-desc">
                                            <?php
                                            /* translators: %s: placeholder tags */
                                            echo wp_kses_post( sprintf( __( 'Use %s as a dynamic placeholder for the subscriber number.', 'kd-earlybird-notify' ), '<code>{count}</code>' ) );
                                            ?>
                                        </p>
                                    </div>
                                    <div class="kd-admin-form-group" style="margin-bottom: 1.2rem;">
                                        <label for="success_title" class="kd-admin-label"><?php esc_html_e('Success Screen Title', 'kd-earlybird-notify'); ?></label>
                                        <input type="text" id="success_title" name="success_title" class="kd-admin-input" value="<?php echo esc_attr($form_texts['success_title']); ?>" placeholder="<?php esc_attr_e("You're on the list!", 'kd-earlybird-notify'); ?>" required />
                                    </div>
                                    <div class="kd-admin-form-group" style="margin-bottom: 0;">
                                        <label for="success_msg" class="kd-admin-label"><?php esc_html_e('Success Screen Message', 'kd-earlybird-notify'); ?></label>
                                        <input type="text" id="success_msg" name="success_msg" class="kd-admin-input" value="<?php echo esc_attr($form_texts['success_msg']); ?>" placeholder="<?php esc_attr_e('Thank you! You have successfully signed up.', 'kd-earlybird-notify'); ?>" required />
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="kd-admin-form-group" style="margin-bottom: 1.2rem;">
                                        <label for="name_label" class="kd-admin-label"><?php esc_html_e('Full Name Field Label', 'kd-earlybird-notify'); ?></label>
                                        <input type="text" id="name_label" name="name_label" class="kd-admin-input" value="<?php echo esc_attr($form_texts['name_label']); ?>" placeholder="<?php esc_attr_e('Full Name', 'kd-earlybird-notify'); ?>" required />
                                    </div>
                                    <div class="kd-admin-form-group" style="margin-bottom: 1.2rem;">
                                        <label for="email_label" class="kd-admin-label"><?php esc_html_e('Email Address Field Label', 'kd-earlybird-notify'); ?></label>
                                        <input type="text" id="email_label" name="email_label" class="kd-admin-input" value="<?php echo esc_attr($form_texts['email_label']); ?>" placeholder="<?php esc_attr_e('Email Address', 'kd-earlybird-notify'); ?>" required />
                                    </div>
                                    <div class="kd-admin-form-group" style="margin-bottom: 1.2rem;">
                                        <label for="phone_label" class="kd-admin-label"><?php esc_html_e('Phone Number Field Label', 'kd-earlybird-notify'); ?></label>
                                        <input type="text" id="phone_label" name="phone_label" class="kd-admin-input" value="<?php echo esc_attr($form_texts['phone_label']); ?>" placeholder="<?php esc_attr_e('Phone Number', 'kd-earlybird-notify'); ?>" required />
                                    </div>
                                    <div class="kd-admin-form-group" style="margin-bottom: 1.2rem;">
                                        <label for="whatsapp_label" class="kd-admin-label"><?php esc_html_e('WhatsApp Number Field Label', 'kd-earlybird-notify'); ?></label>
                                        <input type="text" id="whatsapp_label" name="whatsapp_label" class="kd-admin-input" value="<?php echo esc_attr($form_texts['whatsapp_label']); ?>" placeholder="<?php esc_attr_e('WhatsApp Number', 'kd-earlybird-notify'); ?>" required />
                                    </div>
                                    <div class="kd-admin-form-group" style="margin-bottom: 1.2rem;">
                                        <label for="badge_label" class="kd-admin-label"><?php esc_html_e('Standalone Badge Default Label', 'kd-earlybird-notify'); ?></label>
                                        <input type="text" id="badge_label" name="badge_label" class="kd-admin-input" value="<?php echo esc_attr($form_texts['badge_label']); ?>" placeholder="<?php esc_attr_e('Early Birds Joined', 'kd-earlybird-notify'); ?>" required />
                                        <p class="kd-field-desc"><?php esc_html_e('Default label displayed next to the number in the standalone shortcode badge.', 'kd-earlybird-notify'); ?></p>
                                    </div>
                                    <div class="kd-admin-form-group" style="margin-bottom: 0;">
                                        <label for="consent_label" class="kd-admin-label"><?php esc_html_e('Consent Checkbox Label', 'kd-earlybird-notify'); ?></label>
                                        <input type="text" id="consent_label" name="consent_label" class="kd-admin-input" value="<?php echo esc_attr($form_texts['consent_label']); ?>" placeholder="<?php esc_attr_e('I agree to receive launch notifications and updates.', 'kd-earlybird-notify'); ?>" required />
                                        <p class="kd-field-desc"><?php esc_html_e('Label displayed next to the mandatory opt-in checkbox on the registration form.', 'kd-earlybird-notify'); ?></p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- SMS & WhatsApp Gateway Configuration -->
                        <div class="kd-settings-card">
                            <h3 class="kd-card-title"><?php esc_html_e('Twilio Gateway Integration (Optional)', 'kd-earlybird-notify'); ?></h3>
                            <p class="kd-card-description"><?php esc_html_e('Configure optional Twilio API credentials to send automated SMS and WhatsApp launch notifications.', 'kd-earlybird-notify'); ?></p>

                            <div class="kd-admin-form-group">
                                <label for="twilio_sid" class="kd-admin-label"><?php esc_html_e('Twilio Account SID', 'kd-earlybird-notify'); ?></label>
                                <input type="text" id="twilio_sid" name="twilio_sid" class="kd-admin-input" value="<?php echo esc_attr($gateway_config['twilio_sid']); ?>" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx" />
                            </div>

                            <div class="kd-admin-form-group">
                                <label for="twilio_token" class="kd-admin-label"><?php esc_html_e('Twilio Auth Token', 'kd-earlybird-notify'); ?></label>
                                <?php 
                                $masked_token = '';
                                if (!empty($gateway_config['twilio_token'])) {
                                    $masked_token = str_repeat('•', 32);
                                }
                                ?>
                                <input type="password" id="twilio_token" name="twilio_token" class="kd-admin-input" value="<?php echo esc_attr($masked_token); ?>" placeholder="••••••••••••••••••••••••••••••••" />
                            </div>

                            <div class="kd-admin-form-group">
                                <label for="twilio_sms_from" class="kd-admin-label"><?php esc_html_e('Twilio Sender Phone Number (SMS)', 'kd-earlybird-notify'); ?></label>
                                <input type="text" id="twilio_sms_from" name="twilio_sms_from" class="kd-admin-input" value="<?php echo esc_attr($gateway_config['twilio_sms_from']); ?>" placeholder="+1415xxxxxxx" />
                                <p class="kd-field-desc"><?php esc_html_e('Your active Twilio SMS sender number in E.164 format.', 'kd-earlybird-notify'); ?></p>
                            </div>

                            <div class="kd-admin-form-group">
                                <label for="twilio_wa_from" class="kd-admin-label"><?php esc_html_e('Twilio Sender WhatsApp Number', 'kd-earlybird-notify'); ?></label>
                                <input type="text" id="twilio_wa_from" name="twilio_wa_from" class="kd-admin-input" value="<?php echo esc_attr($gateway_config['twilio_wa_from']); ?>" placeholder="+14155238886" />
                                <p class="kd-field-desc"><?php esc_html_e('Your Twilio WhatsApp Business sender number. For sandbox test accounts, use the assigned sandbox number.', 'kd-earlybird-notify'); ?></p>
                            </div>
                        </div>

                        <!-- Custom HTTP API Gateway Configuration (Free/Local) -->
                        <div class="kd-settings-card">
                            <h3 class="kd-card-title"><?php esc_html_e('Custom HTTP Gateway Integration (Optional)', 'kd-earlybird-notify'); ?></h3>
                            <p class="kd-card-description"><?php esc_html_e('Configure custom API endpoints to send SMS and WhatsApp notifications automatically using third-party services or local Android apps (e.g. SMS Gateway apps).', 'kd-earlybird-notify'); ?></p>

                            <div class="kd-admin-form-group">
                                <label for="custom_sms_url" class="kd-admin-label"><?php esc_html_e('Custom SMS Gateway API URL', 'kd-earlybird-notify'); ?></label>
                                <input type="text" id="custom_sms_url" name="custom_sms_url" class="kd-admin-input" value="<?php echo esc_attr($gateway_config['custom_sms_url']); ?>" placeholder="http://192.168.1.50:8080/send?to={phone}&msg={message}" />
                                <p class="kd-field-desc">
                                    <?php
                                    /* translators: 1: phone placeholder tag, 2: message placeholder tag */
                                    echo wp_kses_post( sprintf( __( 'Enter the gateway API URL. Use %1$s and %2$s as dynamic placeholders.', 'kd-earlybird-notify' ), '<code>{phone}</code>', '<code>{message}</code>' ) );
                                    ?>
                                </p>
                            </div>

                            <div class="kd-admin-form-group">
                                <label for="custom_wa_url" class="kd-admin-label"><?php esc_html_e('Custom WhatsApp Gateway API URL', 'kd-earlybird-notify'); ?></label>
                                <input type="text" id="custom_wa_url" name="custom_wa_url" class="kd-admin-input" value="<?php echo esc_attr($gateway_config['custom_wa_url']); ?>" placeholder="http://192.168.1.50:3000/send?phone={phone}&text={message}" />
                                <p class="kd-field-desc">
                                    <?php
                                    /* translators: 1: phone placeholder tag, 2: message placeholder tag */
                                    echo wp_kses_post( sprintf( __( 'Enter the local or cloud WhatsApp gateway API URL. Use %1$s and %2$s as dynamic placeholders.', 'kd-earlybird-notify' ), '<code>{phone}</code>', '<code>{message}</code>' ) );
                                    ?>
                                </p>
                            </div>
                        </div>

                        <div style="margin-top: 1.5rem;">
                            <button type="submit" class="kd-admin-btn kd-btn-primary kd-btn-large">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width: 18px; height: 18px;">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                </svg> <?php esc_html_e('Save Configurations', 'kd-earlybird-notify'); ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Campaign Progress Overlay Modal -->
            <div id="kd-campaign-modal" class="kd-modal-overlay" style="display: none;">
                <div class="kd-modal-card">
                    <!-- Automated Queue View -->
                    <div id="kd-auto-campaign-view">
                        <h3 class="kd-modal-title"><?php esc_html_e('Sending Broadcast Campaign', 'kd-earlybird-notify'); ?></h3>
                        <p class="kd-modal-subtitle"><?php esc_html_e('Please keep this browser window open until the campaign is completed.', 'kd-earlybird-notify'); ?></p>
                        
                        <div class="kd-progress-container">
                            <div class="kd-progress-bar-track">
                                <div id="kd-progress-bar-fill" class="kd-progress-bar-fill" style="width: 0%;"></div>
                            </div>
                            <div class="kd-progress-meta">
                                <span id="kd-progress-percentage">0%</span>
                                <span><span id="kd-progress-ratio">0 / 0</span> <?php esc_html_e('notified', 'kd-earlybird-notify'); ?></span>
                            </div>
                        </div>

                        <div class="kd-log-container">
                            <div class="kd-log-header"><?php esc_html_e('Delivery Activity Log', 'kd-earlybird-notify'); ?></div>
                            <div id="kd-campaign-log" class="kd-log-body">
                                <p class="kd-log-placeholder"><?php esc_html_e('Initializing campaign...', 'kd-earlybird-notify'); ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Manual Queue View (For Free SMS/WhatsApp Sending) -->
                    <div id="kd-manual-campaign-view" style="display: none;">
                        <h3 class="kd-modal-title"><?php esc_html_e('Manual Notification Queue', 'kd-earlybird-notify'); ?></h3>
                        <p class="kd-modal-subtitle"><?php esc_html_e('Follow the queue to send messages manually using your device or WhatsApp Web.', 'kd-earlybird-notify'); ?></p>

                        <div class="kd-manual-queue-card">
                            <div class="kd-queue-meta">
                                <span class="kd-queue-badge"><?php esc_html_e('Remaining in Queue:', 'kd-earlybird-notify'); ?> <strong id="kd-manual-progress-ratio">0 / 0</strong></span>
                            </div>
                            <div class="kd-subscriber-card-inline">
                                <div class="kd-sub-avatar"><span class="dashicons dashicons-admin-users"></span></div>
                                <div class="kd-sub-details">
                                    <h4 id="kd-manual-sub-name"><?php esc_html_e('Loading...', 'kd-earlybird-notify'); ?></h4>
                                    <p id="kd-manual-sub-number"><?php esc_html_e('Loading...', 'kd-earlybird-notify'); ?></p>
                                </div>
                            </div>
                            <div class="kd-message-preview-box">
                                <div class="kd-preview-header"><?php esc_html_e('Message Preview:', 'kd-earlybird-notify'); ?></div>
                                <div id="kd-manual-message-preview" class="kd-preview-body"></div>
                            </div>
                            <div class="kd-manual-actions">
                                <button type="button" id="kd-manual-send-btn" class="kd-admin-btn kd-btn-primary kd-btn-large" style="width: 100%; justify-content: center; margin-bottom: 0.8rem; height: 50px;">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor" style="width: 18px; height: 18px;">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 003 8.25v10.5A2.25 2.25 0 005.25 21h10.5A2.25 2.25 0 0018 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                    </svg> <?php esc_html_e('Open & Send Message', 'kd-earlybird-notify'); ?>
                                </button>
                                <div style="display: flex; gap: 0.5rem;">
                                    <button type="button" id="kd-manual-skip-btn" class="kd-admin-btn kd-btn-outline" style="flex: 1; justify-content: center;"><?php esc_html_e('Skip User', 'kd-earlybird-notify'); ?></button>
                                    <button type="button" id="kd-manual-mark-btn" class="kd-admin-btn kd-btn-warning" style="flex: 1; justify-content: center;"><?php esc_html_e('Mark as Sent', 'kd-earlybird-notify'); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="kd-modal-footer">
                        <button id="kd-close-modal-btn" class="kd-admin-btn kd-btn-outline" style="display: none;"><?php esc_html_e('Close Dialog', 'kd-earlybird-notify'); ?></button>
                    </div>
                </div>
            </div>

            <!-- Add Service Modal -->
            <div id="kd-service-modal" class="kd-modal-overlay" style="display: none;">
                <div class="kd-modal-card" style="max-width: 480px;">
                    <h3 class="kd-modal-title"><?php esc_html_e('Create New Service', 'kd-earlybird-notify'); ?></h3>
                    <p class="kd-modal-subtitle"><?php esc_html_e('Define a new service or product waitlist campaign.', 'kd-earlybird-notify'); ?></p>
                    
                    <form id="kd-create-service-form" style="display: flex; flex-direction: column; gap: 1.2rem;">
                        <div class="kd-admin-form-group" style="margin-bottom: 0;">
                            <label for="kd-new-service-name" class="kd-admin-label"><?php esc_html_e('Service Name *', 'kd-earlybird-notify'); ?></label>
                            <input type="text" id="kd-new-service-name" class="kd-admin-input" placeholder="<?php esc_attr_e('e.g. Mobile App Beta', 'kd-earlybird-notify'); ?>" required />
                        </div>
                        
                        <div class="kd-admin-form-group" style="margin-bottom: 0;">
                            <label for="kd-new-service-desc" class="kd-admin-label"><?php esc_html_e('Description (Optional)', 'kd-earlybird-notify'); ?></label>
                            <textarea id="kd-new-service-desc" class="kd-admin-textarea" rows="3" placeholder="<?php esc_attr_e('Brief description of the service...', 'kd-earlybird-notify'); ?>" style="min-height: 80px;"></textarea>
                        </div>
                        
                        <div id="kd-service-error-msg" style="display: none; color: #f87171; font-size: 0.85rem; font-weight: 500;"></div>
                        
                        <div style="display: flex; gap: 0.6rem; justify-content: flex-end; margin-top: 0.5rem;">
                            <button type="submit" id="kd-save-service-btn" class="kd-admin-btn kd-btn-primary"><?php esc_html_e('Create Service', 'kd-earlybird-notify'); ?></button>
                            <button type="button" id="kd-close-service-modal-btn" class="kd-admin-btn kd-btn-outline"><?php esc_html_e('Cancel', 'kd-earlybird-notify'); ?></button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <?php
    }
}

<?php
/**
 * Email Helper Functions
 * Provides email sending functionality with SMTP support
 */

/**
 * Get email settings from database
 */
function getEmailSettings($pdo) {
    $defaults = [
        'smtp_enabled' => '0',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_encryption' => 'tls',
        'smtp_username' => '',
        'smtp_password' => '',
        'smtp_from_email' => '',
        'smtp_from_name' => '',
        'notification_emails' => '',
    ];

    if (!$pdo) return $defaults;

    try {
        $stmt = $pdo->query("SELECT setting_key, setting_value FROM email_settings");
        $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return array_merge($defaults, $rows);
    } catch (Exception $e) {
        return $defaults;
    }
}

/**
 * Send email using SMTP or PHP mail()
 */
function sendEmail($pdo, $to, $subject, $body, $isHtml = true) {
    $settings = getEmailSettings($pdo);
    
    // If SMTP is not enabled, use PHP mail()
    if ($settings['smtp_enabled'] !== '1') {
        $headers = "From: " . $settings['smtp_from_name'] . " <" . $settings['smtp_from_email'] . ">\r\n";
        if ($isHtml) {
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        }
        return mail($to, $subject, $body, $headers);
    }
    
    // Use SMTP
    return sendSMTPEmail($settings, $to, $subject, $body, $isHtml);
}

/**
 * Send email via SMTP using fsockopen
 */
function sendSMTPEmail($settings, $to, $subject, $body, $isHtml = true) {
    $lastError = '';
    
    try {
        // Validate settings
        if (empty($settings['smtp_host']) || empty($settings['smtp_port']) || 
            empty($settings['smtp_username']) || empty($settings['smtp_password']) ||
            empty($settings['smtp_from_email'])) {
            throw new Exception('SMTP settings are incomplete');
        }

        // Connect to SMTP server
        $host = $settings['smtp_host'];
        $port = (int)$settings['smtp_port'];
        $encryption = $settings['smtp_encryption'];
        
        // Determine connection type
        if ($encryption === 'ssl') {
            $host = 'ssl://' . $host;
        }
        
        $socket = @fsockopen($host, $port, $errno, $errstr, 30);
        if (!$socket) {
            throw new Exception("Failed to connect to SMTP server: $errstr ($errno)");
        }
        
        // Read server response
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '220') {
            throw new Exception('SMTP connection failed: ' . $response);
        }
        
        // Send EHLO
        $serverName = $_SERVER['SERVER_NAME'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
        fputs($socket, "EHLO " . $serverName . "\r\n");
        // Read all EHLO responses (multi-line)
        do {
            $response = fgets($socket, 515);
        } while ($response && $response[3] === '-');
        
        // Start TLS if needed
        if ($encryption === 'tls') {
            fputs($socket, "STARTTLS\r\n");
            $response = fgets($socket, 515);
            // STARTTLS should return 220, but some servers return 250
            if (substr($response, 0, 3) !== '220' && substr($response, 0, 3) !== '250') {
                throw new Exception('STARTTLS failed: ' . $response);
            }
            
            if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new Exception('Failed to enable TLS encryption');
            }
            
            // Send EHLO again after TLS
            fputs($socket, "EHLO " . $serverName . "\r\n");
            // Read all EHLO responses (multi-line)
            do {
                $response = fgets($socket, 515);
            } while ($response && $response[3] === '-');
        }
        
        // Authenticate
        fputs($socket, "AUTH LOGIN\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '334') {
            throw new Exception('AUTH LOGIN failed: ' . $response);
        }
        
        fputs($socket, base64_encode($settings['smtp_username']) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '334') {
            throw new Exception('Username authentication failed: ' . $response);
        }
        
        fputs($socket, base64_encode($settings['smtp_password']) . "\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '235') {
            throw new Exception('Password authentication failed: ' . $response);
        }
        
        // Send MAIL FROM
        fputs($socket, "MAIL FROM: <" . $settings['smtp_from_email'] . ">\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '250') {
            throw new Exception('MAIL FROM failed: ' . $response);
        }
        
        // Send RCPT TO
        fputs($socket, "RCPT TO: <$to>\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '250') {
            throw new Exception('RCPT TO failed: ' . $response);
        }
        
        // Send DATA
        fputs($socket, "DATA\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '354') {
            throw new Exception('DATA command failed: ' . $response);
        }
        
        // Build email headers and body
        $fromName = !empty($settings['smtp_from_name']) ? $settings['smtp_from_name'] : $settings['smtp_from_email'];
        $headers = "From: $fromName <" . $settings['smtp_from_email'] . ">\r\n";
        $headers .= "To: $to\r\n";
        $headers .= "Subject: $subject\r\n";
        $headers .= "Date: " . date('r') . "\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        
        if ($isHtml) {
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        } else {
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        }
        
        $headers .= "\r\n";
        
        // Send email content
        fputs($socket, $headers . $body . "\r\n.\r\n");
        $response = fgets($socket, 515);
        if (substr($response, 0, 3) !== '250') {
            throw new Exception('Email sending failed: ' . $response);
        }
        
        // Send QUIT
        fputs($socket, "QUIT\r\n");
        fclose($socket);
        
        return true;
        
    } catch (Exception $e) {
        $lastError = $e->getMessage();
        error_log('SMTP Error: ' . $lastError);
        // Store error in global variable for retrieval
        $GLOBALS['smtp_last_error'] = $lastError;
        return false;
    }
}

/**
 * Get last SMTP error
 */
function getLastSMTPError() {
    return $GLOBALS['smtp_last_error'] ?? 'Unknown error';
}

/**
 * Send test email
 */
function sendTestEmail($pdo, $to) {
    $settings = getEmailSettings($pdo);
    $fromName = !empty($settings['smtp_from_name']) ? $settings['smtp_from_name'] : 'Universal Corporate';
    
    $subject = 'Test Email from ' . $fromName;
    $body = '
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #3498db; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
            .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; border-radius: 0 0 5px 5px; }
            .success { color: #27ae60; font-weight: bold; }
            .footer { margin-top: 20px; text-align: center; color: #888; font-size: 12px; }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <h2>Test Email</h2>
            </div>
            <div class="content">
                <p class="success">✓ Success!</p>
                <p>This is a test email from your SMTP configuration.</p>
                <p>If you received this email, your SMTP settings are working correctly.</p>
                <hr style="border: none; border-top: 1px solid #ddd; margin: 20px 0;">
                <p><strong>Configuration Details:</strong></p>
                <ul>
                    <li>SMTP Host: ' . htmlspecialchars($settings['smtp_host']) . '</li>
                    <li>SMTP Port: ' . htmlspecialchars($settings['smtp_port']) . '</li>
                    <li>Encryption: ' . strtoupper(htmlspecialchars($settings['smtp_encryption'])) . '</li>
                    <li>From Email: ' . htmlspecialchars($settings['smtp_from_email']) . '</li>
                </ul>
            </div>
            <div class="footer">
                <p>Sent at ' . date('Y-m-d H:i:s') . '</p>
            </div>
        </div>
    </body>
    </html>
    ';
    
    return sendEmail($pdo, $to, $subject, $body, true);
}


/**
 * Send enquiry notification to admin emails (non-blocking)
 */
function sendEnquiryNotificationAsync($pdo, $enquiryData) {
    // This ensures the form submission doesn't fail if email fails
    
    $settings = getEmailSettings($pdo);
    
    // Check if SMTP is enabled and notification emails are set
    if ($settings['smtp_enabled'] !== '1') {
        error_log('Enquiry notification skipped: SMTP not enabled');
        return;
    }
    
    if (empty($settings['notification_emails'])) {
        error_log('Enquiry notification skipped: No notification emails configured');
        return;
    }
    
    // Parse notification emails (handle both \r\n and \n line endings, also commas)
    $emailsRaw = str_replace(["\r\n", "\r", ","], "\n", $settings['notification_emails']);
    $emails = array_filter(array_map('trim', explode("\n", $emailsRaw)));
    
    if (empty($emails)) {
        error_log('Enquiry notification skipped: No valid emails after parsing');
        return;
    }
    
    error_log('Enquiry notification: Sending to ' . count($emails) . ' recipients: ' . implode(', ', $emails));
    
    // Send notification (in try-catch to prevent any failure from affecting the main process)
    try {
        sendEnquiryNotification($pdo, $enquiryData, $emails);
    } catch (Exception $e) {
        error_log('Enquiry notification error: ' . $e->getMessage());
    }
}

/**
 * Send enquiry notification email to admins
 */
function sendEnquiryNotification($pdo, $enquiryData, $emails) {
    $settings = getEmailSettings($pdo);
    $fromName = !empty($settings['smtp_from_name']) ? $settings['smtp_from_name'] : 'Website Enquiry';
    
    $subject = 'New Enquiry: ' . ($enquiryData['subject'] ?: 'Website Contact Form');
    
    // Build HTML email
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
            .wrapper { padding: 20px; }
            .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background: #2c3e50; color: white; padding: 25px 30px; }
            .header h1 { margin: 0; font-size: 22px; font-weight: 600; }
            .header p { margin: 8px 0 0; opacity: 0.9; font-size: 14px; }
            .content { padding: 30px; }
            .field { margin-bottom: 20px; }
            .field-label { font-size: 12px; text-transform: uppercase; color: #7f8c8d; font-weight: 600; margin-bottom: 5px; }
            .field-value { font-size: 15px; color: #2c3e50; }
            .message-box { background: #f8f9fa; padding: 15px; border-radius: 6px; border-left: 4px solid #3498db; }
            .message-box p { margin: 0; white-space: pre-wrap; }
            .meta { border-top: 1px solid #eee; padding-top: 20px; margin-top: 20px; }
            .meta-grid { display: flex; flex-wrap: wrap; gap: 15px; }
            .meta-item { flex: 1; min-width: 120px; }
            .meta-label { font-size: 11px; text-transform: uppercase; color: #95a5a6; }
            .meta-value { font-size: 13px; color: #7f8c8d; }
            .footer { background: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #eee; }
            .footer p { margin: 0; font-size: 12px; color: #95a5a6; }
            .btn { display: inline-block; background: #3498db; color: white; padding: 10px 25px; text-decoration: none; border-radius: 5px; font-weight: 600; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class="wrapper">
            <div class="container">
                <div class="header">
                    <h1>New Enquiry Received</h1>
                    <p>' . date('F j, Y \a\t g:i A') . '</p>
                </div>
                <div class="content">
                    <div class="field">
                        <div class="field-label">Name</div>
                        <div class="field-value">' . htmlspecialchars($enquiryData['name']) . '</div>
                    </div>
                    
                    <div class="field">
                        <div class="field-label">Email</div>
                        <div class="field-value"><a href="mailto:' . htmlspecialchars($enquiryData['email']) . '">' . htmlspecialchars($enquiryData['email']) . '</a></div>
                    </div>
                    
                    ' . (!empty($enquiryData['phone']) ? '
                    <div class="field">
                        <div class="field-label">Phone</div>
                        <div class="field-value"><a href="tel:' . htmlspecialchars($enquiryData['phone']) . '">' . htmlspecialchars($enquiryData['phone']) . '</a></div>
                    </div>
                    ' : '') . '
                    
                    ' . (!empty($enquiryData['subject']) ? '
                    <div class="field">
                        <div class="field-label">Subject</div>
                        <div class="field-value">' . htmlspecialchars($enquiryData['subject']) . '</div>
                    </div>
                    ' : '') . '
                    
                    <div class="field">
                        <div class="field-label">Message</div>
                        <div class="message-box">
                            <p>' . nl2br(htmlspecialchars($enquiryData['message'])) . '</p>
                        </div>
                    </div>
                    
                    <div class="meta">
                        <div class="meta-grid">
                            <div class="meta-item">
                                <div class="meta-label">Source Page</div>
                                <div class="meta-value">' . htmlspecialchars($enquiryData['page_name'] ?? 'Unknown') . '</div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">Location</div>
                                <div class="meta-value">' . htmlspecialchars(($enquiryData['city'] ?? 'Unknown') . ', ' . ($enquiryData['country'] ?? 'Unknown')) . '</div>
                            </div>
                            <div class="meta-item">
                                <div class="meta-label">IP Address</div>
                                <div class="meta-value">' . htmlspecialchars($enquiryData['ip_address'] ?? 'Unknown') . '</div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="footer">
                    <p>This is an automated notification from your website contact form.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    // Send to each admin email
    foreach ($emails as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log('Enquiry notification: Attempting to send to ' . $email);
            $result = sendEmail($pdo, $email, $subject, $body, true);
            if ($result) {
                error_log('Enquiry notification: Successfully sent to ' . $email);
            } else {
                error_log('Enquiry notification: Failed to send to ' . $email . ' - ' . getLastSMTPError());
            }
        } else {
            error_log('Enquiry notification: Invalid email skipped - ' . $email);
        }
    }
}

/**
 * Send order notification to admin emails (non-blocking)
 */
function sendOrderNotificationAsync($pdo, $orderId) {
    $settings = getEmailSettings($pdo);
    
    // Check if SMTP is enabled and notification emails are set
    if ($settings['smtp_enabled'] !== '1') {
        error_log('Order notification skipped: SMTP not enabled');
        return;
    }
    
    if (empty($settings['notification_emails'])) {
        error_log('Order notification skipped: No notification emails configured');
        return;
    }
    
    // Parse notification emails
    $emailsRaw = str_replace(["\r\n", "\r", ","], "\n", $settings['notification_emails']);
    $emails = array_filter(array_map('trim', explode("\n", $emailsRaw)));
    
    if (empty($emails)) {
        error_log('Order notification skipped: No valid emails after parsing');
        return;
    }
    
    error_log('Order notification: Sending to ' . count($emails) . ' recipients: ' . implode(', ', $emails));
    
    // Send notification
    try {
        sendOrderNotification($pdo, $orderId, $emails);
    } catch (Exception $e) {
        error_log('Order notification error: ' . $e->getMessage());
    }
}

/**
 * Send order notification email to admins
 */
function sendOrderNotification($pdo, $orderId, $emails) {
    // Fetch order details
    $stmt = $pdo->prepare("SELECT o.*, u.email as user_email FROM orders o LEFT JOIN site_users u ON o.user_id = u.id WHERE o.id = ?");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    
    if (!$order) {
        error_log('Order notification: Order not found - ID ' . $orderId);
        return;
    }
    
    // Fetch order items
    $stmt = $pdo->prepare("SELECT * FROM order_items WHERE order_id = ? ORDER BY id");
    $stmt->execute([$orderId]);
    $orderItems = $stmt->fetchAll();
    
    $settings = getEmailSettings($pdo);
    $fromName = !empty($settings['smtp_from_name']) ? $settings['smtp_from_name'] : 'Order System';
    
    $subject = 'New Order Placed: ' . $order['order_number'];
    
    // Build items table HTML
    $itemsHtml = '';
    foreach ($orderItems as $item) {
        $unitPrice = $item['unit_price'] ? '₹' . number_format($item['unit_price'], 2) : '<span style="color: #f39c12;">TBD</span>';
        $lineTotal = $item['line_total'] ? '₹' . number_format($item['line_total'], 2) : '<span style="color: #f39c12;">TBD</span>';
        $customBadge = $item['is_custom_item'] ? ' <span style="background: #f39c12; color: white; padding: 2px 6px; border-radius: 3px; font-size: 10px;">CUSTOM</span>' : '';
        
        $itemsHtml .= '
        <tr>
            <td style="padding: 12px 10px; border-bottom: 1px solid #ecf0f1;">' . htmlspecialchars($item['product_name']) . $customBadge . '</td>
            <td style="padding: 12px 10px; border-bottom: 1px solid #ecf0f1;">' . htmlspecialchars($item['product_sku'] ?? '-') . '</td>
            <td style="padding: 12px 10px; border-bottom: 1px solid #ecf0f1; text-align: center;">' . $item['quantity'] . ' ' . htmlspecialchars($item['unit']) . '</td>
            <td style="padding: 12px 10px; border-bottom: 1px solid #ecf0f1; text-align: right;">' . $unitPrice . '</td>
            <td style="padding: 12px 10px; border-bottom: 1px solid #ecf0f1; text-align: center;">' . number_format($item['tax_rate'], 2) . '%</td>
            <td style="padding: 12px 10px; border-bottom: 1px solid #ecf0f1; text-align: right;">' . $lineTotal . '</td>
        </tr>';
    }
    
    // Shipping address section
    $shippingHtml = '';
    if ($order['shipping_same_as_billing']) {
        $shippingHtml = '<p style="font-style: italic; color: #7f8c8d;">Same as billing address</p>';
    } else {
        $shippingHtml = '
            <p style="margin: 5px 0; font-weight: 600; font-size: 15px;">' . htmlspecialchars($order['shipping_name']) . '</p>
            <p style="margin: 3px 0;">' . htmlspecialchars($order['shipping_address']) . '</p>
            <p style="margin: 3px 0;">' . htmlspecialchars($order['shipping_city'] . ', ' . $order['shipping_state'] . ' - ' . $order['shipping_pincode']) . '</p>
            <p style="margin: 3px 0;">Phone: ' . htmlspecialchars($order['shipping_phone']) . '</p>';
    }
    
    // Unpriced items notice
    $unpricedNotice = '';
    if ($order['has_unpriced_items']) {
        $unpricedNotice = '
        <div style="background: #fff3cd; border-left: 4px solid #f39c12; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <p style="margin: 0; color: #856404; font-weight: 600;">⚠️ This order contains custom/unpriced items</p>
            <p style="margin: 5px 0 0; color: #856404; font-size: 13px;">Please review and confirm pricing before processing.</p>
        </div>';
    }
    
    // Build HTML email
    $body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; background: #f5f5f5; }
            .wrapper { padding: 20px; }
            .container { max-width: 700px; margin: 0 auto; background: white; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { background: #27ae60; color: white; padding: 25px 30px; }
            .header h1 { margin: 0; font-size: 24px; font-weight: 600; }
            .header p { margin: 8px 0 0; opacity: 0.9; font-size: 14px; }
            .content { padding: 30px; }
            .order-info { background: #f8f9fa; padding: 15px 20px; border-radius: 6px; margin-bottom: 20px; }
            .order-info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
            .info-item { }
            .info-label { font-size: 11px; text-transform: uppercase; color: #7f8c8d; font-weight: 600; }
            .info-value { font-size: 14px; color: #2c3e50; font-weight: 600; margin-top: 3px; }
            .section-title { font-size: 14px; text-transform: uppercase; color: #7f8c8d; font-weight: 600; margin: 25px 0 12px; border-bottom: 2px solid #ecf0f1; padding-bottom: 8px; }
            .addresses { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 25px; }
            .address-block { }
            .address-block h3 { font-size: 12px; text-transform: uppercase; color: #7f8c8d; margin-bottom: 10px; }
            .address-block p { margin: 3px 0; font-size: 13px; color: #2c3e50; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            th { background: #34495e; color: white; padding: 12px 10px; text-align: left; font-size: 11px; font-weight: 600; text-transform: uppercase; }
            .totals { margin-top: 20px; }
            .total-row { display: flex; justify-content: space-between; padding: 10px 15px; font-size: 14px; }
            .total-row.subtotal { border-top: 2px solid #ecf0f1; }
            .total-row.tax { background: #f8f9fa; }
            .total-row.grand-total { background: #27ae60; color: white; font-size: 16px; font-weight: 700; border-radius: 6px; margin-top: 5px; }
            .footer { background: #f8f9fa; padding: 20px 30px; text-align: center; border-top: 1px solid #eee; }
            .footer p { margin: 0; font-size: 12px; color: #95a5a6; }
            .btn { display: inline-block; background: #3498db; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; font-weight: 600; margin-top: 15px; }
        </style>
    </head>
    <body>
        <div class="wrapper">
            <div class="container">
                <div class="header">
                    <h1>New Order Received</h1>
                    <p>' . date('F j, Y \a\t g:i A') . '</p>
                </div>
                <div class="content">
                    ' . $unpricedNotice . '
                    
                    <div class="order-info">
                        <div class="order-info-grid">
                            <div class="info-item">
                                <div class="info-label">Order Number</div>
                                <div class="info-value">' . htmlspecialchars($order['order_number']) . '</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Customer Email</div>
                                <div class="info-value"><a href="mailto:' . htmlspecialchars($order['user_email']) . '">' . htmlspecialchars($order['user_email']) . '</a></div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Payment Mode</div>
                                <div class="info-value">' . ucwords(str_replace('_', ' ', $order['payment_mode'])) . '</div>
                            </div>
                            <div class="info-item">
                                <div class="info-label">Status</div>
                                <div class="info-value">' . ucfirst($order['status']) . '</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="section-title">Customer Information</div>
                    <div class="addresses">
                        <div class="address-block">
                            <h3>Billing Address</h3>
                            <p style="font-weight: 600; font-size: 15px;">' . htmlspecialchars($order['billing_name']) . '</p>
                            <p>' . htmlspecialchars($order['billing_address']) . '</p>
                            <p>' . htmlspecialchars($order['billing_city'] . ', ' . $order['billing_state'] . ' - ' . $order['billing_pincode']) . '</p>
                            <p>Phone: ' . htmlspecialchars($order['billing_phone']) . '</p>
                            <p>Email: <a href="mailto:' . htmlspecialchars($order['billing_email']) . '">' . htmlspecialchars($order['billing_email']) . '</a></p>
                            ' . ($order['gstin'] ? '<p>GSTIN: ' . htmlspecialchars($order['gstin']) . '</p>' : '') . '
                        </div>
                        <div class="address-block">
                            <h3>Shipping Address</h3>
                            ' . $shippingHtml . '
                        </div>
                    </div>
                    
                    ' . ($order['preferred_delivery_date'] ? '<p style="margin: 10px 0;"><strong>Preferred Delivery:</strong> ' . date('F j, Y', strtotime($order['preferred_delivery_date'])) . '</p>' : '') . '
                    ' . ($order['delivery_instructions'] ? '<p style="margin: 10px 0;"><strong>Delivery Instructions:</strong> ' . htmlspecialchars($order['delivery_instructions']) . '</p>' : '') . '
                    
                    <div class="section-title">Order Items</div>
                    <table>
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>SKU</th>
                                <th style="text-align: center;">Quantity</th>
                                <th style="text-align: right;">Unit Price</th>
                                <th style="text-align: center;">Tax %</th>
                                <th style="text-align: right;">Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ' . $itemsHtml . '
                        </tbody>
                    </table>
                    
                    <div class="totals">
                        <div class="total-row subtotal">
                            <span>Subtotal (Gross):</span>
                            <strong>₹' . number_format($order['subtotal'], 2) . '</strong>
                        </div>
                        <div class="total-row tax">
                            <span>Tax Amount:</span>
                            <strong>₹' . number_format($order['tax_amount'], 2) . '</strong>
                        </div>
                        <div class="total-row grand-total">
                            <span>Total Amount:</span>
                            <strong>₹' . number_format($order['total_amount'], 2) . '</strong>
                        </div>
                    </div>
                    
                    ' . ($order['notes'] ? '<div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 6px;"><strong>Notes:</strong><br>' . nl2br(htmlspecialchars($order['notes'])) . '</div>' : '') . '
                </div>
                <div class="footer">
                    <p>This is an automated notification from your order management system.</p>
                    <p>Please log in to the admin panel to manage this order.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    // Send to each admin email
    foreach ($emails as $email) {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            error_log('Order notification: Attempting to send to ' . $email);
            $result = sendEmail($pdo, $email, $subject, $body, true);
            if ($result) {
                error_log('Order notification: Successfully sent to ' . $email);
            } else {
                error_log('Order notification: Failed to send to ' . $email . ' - ' . getLastSMTPError());
            }
        } else {
            error_log('Order notification: Invalid email skipped - ' . $email);
        }
    }
}

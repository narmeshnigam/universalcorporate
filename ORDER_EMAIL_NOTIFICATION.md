# Order Email Notification Feature

## Overview
When a customer places an order, the system automatically sends a detailed email notification to all configured admin email addresses.

## Features

### Email Content Includes:
- **Order Information**: Order number, customer email, payment mode, and status
- **Customer Details**: Complete billing and shipping addresses
- **Order Items**: Full product list with SKU, quantity, unit price, tax rate, and totals
- **Financial Summary**: Subtotal, tax amount, and grand total
- **Special Indicators**: 
  - Custom/unpriced items are highlighted with badges
  - Warning notice for orders containing unpriced items
- **Additional Details**: Delivery preferences, instructions, and notes

### Email Design:
- Professional HTML email template
- Responsive layout
- Color-coded sections for easy reading
- Mobile-friendly design

## Configuration

### 1. Enable SMTP
Navigate to: `Admin Panel > Email Settings`
- Enable SMTP
- Configure SMTP server details (host, port, username, password)
- Set "From" email and name

### 2. Set Notification Emails
In the Email Settings page:
- Add admin email addresses in the "Notification Emails" field
- Multiple emails can be added (one per line or comma-separated)
- Example:
  ```
  admin@example.com
  sales@example.com
  manager@example.com
  ```

## How It Works

1. Customer completes order form and submits
2. Order is saved to database
3. System automatically sends email notification to all configured admin emails
4. Email sending is non-blocking (won't fail order if email fails)
5. All email activity is logged for debugging

## Technical Details

### Files Modified:
- `config/email.php` - Added `sendOrderNotification()` and `sendOrderNotificationAsync()` functions
- `user/place-order.php` - Added email notification trigger after successful order placement

### Functions:
- `sendOrderNotificationAsync($pdo, $orderId)` - Non-blocking wrapper that validates settings and triggers email
- `sendOrderNotification($pdo, $orderId, $emails)` - Fetches order details and sends formatted email to admins

### Error Handling:
- Graceful failure: Order placement succeeds even if email fails
- Comprehensive logging for troubleshooting
- Email validation before sending
- SMTP error tracking

## Testing

To test the notification:
1. Ensure SMTP is configured and enabled
2. Add at least one notification email address
3. Place a test order as a customer
4. Check admin email inbox for notification

## Troubleshooting

### Email Not Received:
1. Check SMTP settings are correct
2. Verify notification emails are configured
3. Check server error logs for email errors
4. Test SMTP connection using "Send Test Email" in Email Settings
5. Check spam/junk folder

### Common Issues:
- **SMTP not enabled**: Enable in Email Settings
- **No notification emails**: Add emails in Email Settings
- **Invalid email format**: Ensure emails are properly formatted
- **SMTP authentication failed**: Verify username/password

## Notes

- Email sending is asynchronous and won't block order placement
- Custom/unpriced items are clearly marked in the email
- All monetary values are formatted in Indian Rupees (₹)
- Timestamps use server timezone

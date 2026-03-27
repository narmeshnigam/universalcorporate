# Enquiry Form System

## Overview
A professional, reusable enquiry form component with IP-based geolocation tracking, clean two-column UI design, and comprehensive admin management.

## Features

### Form Features
- Clean, professional two-column layout (CTA + Form)
- Responsive design for all screen sizes
- AJAX form submission (no page reload)
- Real-time validation
- Loading states and user feedback
- Required and optional fields
- Phone number formatting support

### Data Capture
- **User Input**: Name, Email, Phone, Subject, Message
- **Metadata**: 
  - IP Address (with proxy detection)
  - City & Country (via IP geolocation)
  - Page name where form was submitted
  - User agent (browser/device info)
  - Submission timestamp

### Admin Features
- View all enquiries in a data table
- Filter by status (New, Read, Replied, Closed)
- Update enquiry status
- View location and metadata
- Responsive admin interface

## File Structure

```
├── api/
│   └── submit-enquiry.php          # Form submission handler
├── includes/
│   └── enquiry-form.php            # Reusable form component
├── css/
│   └── enquiry-form.css            # Form styling
├── admin/
│   └── enquiries.php               # Admin management page
└── setup-database.sql              # Database schema
```

## Database Schema

The `enquiries` table includes:
- `id` - Auto-increment primary key
- `name` - Customer name (required)
- `email` - Customer email (required)
- `phone` - Phone number (optional)
- `subject` - Enquiry subject (optional)
- `message` - Enquiry message (required)
- `page_name` - Page where form was submitted
- `ip_address` - Client IP address
- `user_agent` - Browser/device information
- `city` - Estimated city from IP
- `country` - Estimated country from IP
- `submitted_at` - Timestamp
- `status` - Enquiry status (new/read/replied/closed)

## Usage

### Integrate on Any Page

Simply include the form component:

```php
<?php include 'includes/enquiry-form.php'; ?>
```

### Example Integration

```php
<section id="contact" class="section">
    <?php include 'includes/enquiry-form.php'; ?>
</section>
```

The form automatically:
- Detects the current page name
- Generates unique form IDs for multiple instances
- Handles AJAX submission
- Shows success/error messages

### Multiple Forms on Same Page

The form component supports multiple instances on the same page with unique IDs.

## IP Geolocation

The system uses **ip-api.com** (free, no API key required) to estimate location:
- Automatically detects client IP (handles proxies/CDNs)
- Fetches city and country data
- Falls back gracefully for local/private IPs
- 3-second timeout to prevent delays

## Admin Access

1. Navigate to `/admin/enquiries.php`
2. View all enquiries in a sortable table
3. Update status using dropdown
4. See full metadata including location and IP

## Security Features

- Input validation and sanitization
- SQL injection protection (prepared statements)
- XSS prevention (htmlspecialchars)
- CSRF protection ready
- Email validation
- Phone number sanitization

## Customization

### Styling
Edit `css/enquiry-form.css` to customize:
- Colors and gradients
- Spacing and layout
- Button styles
- Form field appearance

### Form Fields
Modify `includes/enquiry-form.php` to:
- Add/remove fields
- Change field types
- Update validation rules
- Customize CTA content

### Validation
Update `api/submit-enquiry.php` to:
- Add custom validation rules
- Change required fields
- Modify error messages

## Testing

1. Navigate to homepage: `http://localhost/index.php`
2. Scroll to contact section
3. Fill out the form
4. Submit and verify success message
5. Check admin panel: `http://localhost/admin/enquiries.php`
6. Verify data is captured correctly

## Browser Support

- Chrome/Edge (latest)
- Firefox (latest)
- Safari (latest)
- Mobile browsers (iOS Safari, Chrome Mobile)

## Dependencies

- PHP 7.4+
- MySQL 5.7+ / MariaDB 10.2+
- PDO extension
- JSON extension
- Modern browser with JavaScript enabled

## Notes

- The form uses AJAX, so JavaScript must be enabled
- IP geolocation may not work for local development (shows "Local")
- For production, consider adding rate limiting
- Consider adding CAPTCHA for spam prevention

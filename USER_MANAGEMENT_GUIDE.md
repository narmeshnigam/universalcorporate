# User Management Implementation Guide

## Overview
This guide documents the implementation of enhanced user registration and admin user management features.

## Changes Implemented

### 1. Enhanced User Registration

#### Database Changes
- Added `full_name` column to `site_users` table
- Added `mobile` column to `site_users` table
- Added `is_active` column to `site_users` table for account status management
- Added corresponding columns to `pending_registrations` table

#### Migration Script
Run the following SQL script to update your database:
```bash
mysql -u [username] -p [database_name] < migrate-users.sql
```

#### Updated Files
- **user/register.php**: Enhanced registration form with Full Name and Mobile Number fields
  - Full Name validation (minimum 2 characters)
  - Mobile Number validation (10-digit format)
  - Duplicate mobile number check
  - Updated form UI with new fields

- **config/otp.php**: Updated OTP functions
  - `storePendingRegistration()`: Now accepts full_name and mobile parameters
  - `completePendingRegistration()`: Inserts full_name and mobile into site_users table

### 2. Admin User Management

#### New Admin Pages

##### users.php - User List Page
Features:
- Display all registered site users in a table format
- Search functionality (by name, email, or mobile)
- Filter by status (Active/Inactive)
- User statistics dashboard showing:
  - Total Users
  - Active Users
  - Inactive Users
- For each user, displays:
  - User ID
  - Full Name
  - Email
  - Mobile Number
  - Order Count
  - Total Amount Spent
  - Account Status
  - Registration Date
- Actions:
  - View Details button (links to user-details.php)
  - Enable/Disable account toggle
  - Delete user (only if no orders exist)

##### user-details.php - User Details Page
Features:
- Complete user information display:
  - User ID
  - Full Name
  - Email
  - Mobile Number
  - Registration Date
  - Account Status
- Order statistics:
  - Total Orders
  - Total Amount Spent
  - Average Order Value
- Complete order history table showing:
  - Order Number
  - Order Date
  - Customer Details
  - Contact Information
  - Order Amount
  - Payment Mode
  - Order Status
  - View Order button (links to orders.php)
- Back to Users button for easy navigation

#### Updated Files
- **admin/includes/sidebar.php**: Added "Site Users" menu item between Orders and Email Settings

#### CSS Updates
- **css/admin.css**: Added styles for:
  - Stats grid layout
  - Filter form styling
  - User info grid
  - Table responsive design
  - Button styles

## Usage Instructions

### For Site Users (Registration)

1. Navigate to the registration page: `/user/register.php`
2. Fill in all required fields:
   - Full Name (minimum 2 characters)
   - Mobile Number (10 digits, numbers only)
   - Email Address (valid email format)
   - Password (minimum 8 characters)
   - Confirm Password
3. Click "Continue" to proceed
4. Enter the OTP sent to your email
5. Click "Verify & Create Account"
6. Upon success, login with your credentials

### For Administrators

#### Accessing User Management
1. Login to admin panel
2. Click "Site Users" in the sidebar menu

#### Managing Users
1. **View All Users**: The main page displays all registered users
2. **Search Users**: Use the search box to find users by name, email, or mobile
3. **Filter by Status**: Select "Active" or "Inactive" from the status dropdown
4. **View User Details**: Click the "View" button next to any user
5. **Enable/Disable Account**: Click "Enable" or "Disable" to toggle user account status
6. **Delete User**: Click "Delete" (only available for users with no orders)

#### Viewing User Details
1. Click "View" button on any user in the list
2. View complete user information and statistics
3. Scroll down to see the user's complete order history
4. Click "View" on any order to see full order details
5. Click "← Back to Users" to return to the user list

## Database Schema

### site_users Table
```sql
CREATE TABLE site_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL UNIQUE,
    full_name VARCHAR(255) DEFAULT NULL,
    mobile VARCHAR(20) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_mobile (mobile),
    INDEX idx_active (is_active)
);
```

### pending_registrations Table
```sql
CREATE TABLE pending_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) DEFAULT NULL,
    mobile VARCHAR(20) DEFAULT NULL,
    password_hash VARCHAR(255) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

## Security Features

1. **Email Verification**: OTP-based email verification before account creation
2. **Password Hashing**: Passwords are hashed using PHP's password_hash()
3. **Duplicate Prevention**: Checks for existing email and mobile numbers
4. **Input Validation**: Server-side validation for all user inputs
5. **Account Status**: Admins can disable user accounts without deletion
6. **Protected Deletion**: Users with orders cannot be deleted

## Notes

- Users with existing orders cannot be deleted (data integrity)
- Disabled accounts cannot login but data is preserved
- Mobile numbers must be unique across all users
- Full Name and Mobile Number are now required fields for new registrations
- Existing users (registered before this update) may have NULL values for full_name and mobile

## Future Enhancements

Potential improvements:
- Bulk user actions (enable/disable multiple users)
- Export user list to CSV
- User activity logs
- Email notifications to users when account status changes
- Password reset functionality for users
- User profile editing by admin

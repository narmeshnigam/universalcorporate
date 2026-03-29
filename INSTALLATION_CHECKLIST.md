# Installation Checklist

## Pre-Installation

- [ ] Backup your database
- [ ] Backup your files
- [ ] Ensure you have database access credentials
- [ ] Verify PHP version (7.4+ recommended)
- [ ] Verify MySQL/MariaDB is running

## Installation Steps

### Step 1: Database Migration
```bash
# Navigate to your project directory
cd /path/to/your/project

# Run the migration script
mysql -u your_username -p your_database_name < migrate-users.sql

# Enter your database password when prompted
```

**Expected Output:**
- No errors
- Tables `site_users` and `pending_registrations` updated with new columns

**Verify Migration:**
```sql
-- Login to MySQL
mysql -u your_username -p

-- Use your database
USE your_database_name;

-- Check site_users table structure
DESCRIBE site_users;

-- You should see: full_name, mobile, is_active columns
```

### Step 2: Verify File Updates

Check that all files are in place:

- [ ] `user/register.php` - Updated registration form
- [ ] `config/otp.php` - Updated OTP functions
- [ ] `admin/users.php` - New user management page
- [ ] `admin/user-details.php` - New user details page
- [ ] `admin/includes/sidebar.php` - Updated with "Site Users" menu
- [ ] `css/admin.css` - Updated with new styles
- [ ] `migrate-users.sql` - Database migration script

### Step 3: File Permissions

Ensure proper permissions:
```bash
# Make sure PHP can read all files
chmod 644 user/register.php
chmod 644 config/otp.php
chmod 644 admin/users.php
chmod 644 admin/user-details.php
chmod 644 admin/includes/sidebar.php
chmod 644 css/admin.css
```

### Step 4: Test User Registration

1. [ ] Open browser and navigate to: `http://yoursite.com/user/register.php`
2. [ ] Fill in the registration form:
   - Full Name: Test User
   - Mobile: 9876543210
   - Email: test@example.com
   - Password: testpass123
   - Confirm Password: testpass123
3. [ ] Click "Continue"
4. [ ] Check email for OTP
5. [ ] Enter OTP and verify
6. [ ] Confirm success message appears
7. [ ] Try logging in with new credentials

### Step 5: Test Admin User Management

1. [ ] Login to admin panel: `http://yoursite.com/admin/login.php`
2. [ ] Click "Site Users" in the sidebar
3. [ ] Verify user list displays correctly
4. [ ] Test search functionality:
   - Search by name
   - Search by email
   - Search by mobile
5. [ ] Test status filter:
   - Filter by "Active"
   - Filter by "Inactive"
   - Filter by "All Users"
6. [ ] Click "View" on a user
7. [ ] Verify user details page displays:
   - User information
   - Order statistics
   - Order history
8. [ ] Test "Enable/Disable" button
9. [ ] Test "Delete" button (on user with no orders)

## Post-Installation Verification

### Database Checks

```sql
-- Check if new columns exist
SELECT 
    full_name, 
    mobile, 
    is_active, 
    email 
FROM site_users 
LIMIT 5;

-- Check pending_registrations table
DESCRIBE pending_registrations;
```

### Functionality Checks

- [ ] New users can register with full name and mobile
- [ ] Duplicate mobile numbers are rejected
- [ ] OTP verification works correctly
- [ ] Admin can view all users
- [ ] Admin can search users
- [ ] Admin can filter by status
- [ ] Admin can view user details
- [ ] Admin can see user's order history
- [ ] Admin can enable/disable users
- [ ] Admin can delete users (without orders)
- [ ] Users with orders cannot be deleted

### UI/UX Checks

- [ ] Registration form displays all fields correctly
- [ ] Mobile number field accepts only digits
- [ ] Mobile number is limited to 10 digits
- [ ] Form validation messages are clear
- [ ] Admin user list table is responsive
- [ ] Statistics cards display correct numbers
- [ ] Status badges show correct colors
- [ ] Action buttons work as expected
- [ ] Navigation between pages works smoothly

## Troubleshooting

### Issue: Migration fails with "column already exists"
**Solution:** The columns may already exist. Check with:
```sql
DESCRIBE site_users;
```
If columns exist, skip migration.

### Issue: "Table doesn't exist" error
**Solution:** Ensure you've run the base setup first:
```bash
mysql -u username -p database < setup-database.sql
mysql -u username -p database < setup-otp.sql
```

### Issue: OTP functions not working
**Solution:** 
1. Clear PHP opcache: `service php-fpm reload`
2. Check file permissions
3. Verify config/otp.php was updated correctly

### Issue: "Site Users" menu not showing
**Solution:**
1. Clear browser cache
2. Verify admin/includes/sidebar.php was updated
3. Check file permissions

### Issue: Styles not loading
**Solution:**
1. Clear browser cache (Ctrl+Shift+R)
2. Verify css/admin.css was updated
3. Check file permissions

### Issue: Search/Filter not working
**Solution:**
1. Check database connection
2. Verify SQL query syntax
3. Check PHP error logs

## Rollback Procedure

If you need to rollback the changes:

```sql
-- Remove new columns from site_users
ALTER TABLE site_users 
DROP COLUMN full_name,
DROP COLUMN mobile,
DROP COLUMN is_active;

-- Remove new columns from pending_registrations
ALTER TABLE pending_registrations 
DROP COLUMN full_name,
DROP COLUMN mobile;
```

Then restore the original files from your backup.

## Support & Documentation

- **USER_MANAGEMENT_GUIDE.md** - Complete usage guide
- **IMPLEMENTATION_SUMMARY.md** - Technical overview
- **ADMIN_USERS_INTERFACE.md** - UI/UX documentation

## Success Criteria

Installation is successful when:

✅ Database migration completes without errors
✅ New users can register with full name and mobile
✅ Admin can access "Site Users" page
✅ Admin can view, search, and filter users
✅ Admin can view individual user details
✅ Admin can see user order history
✅ Admin can enable/disable user accounts
✅ Admin can delete users without orders
✅ All UI elements display correctly
✅ No PHP errors in error logs
✅ No JavaScript console errors

## Next Steps

After successful installation:

1. Test with real user registrations
2. Monitor error logs for any issues
3. Train admin users on new features
4. Consider adding profile update feature for existing users
5. Review and adjust user permissions as needed

## Contact

For issues or questions:
- Check error logs: `/var/log/php-errors.log`
- Check database logs
- Review documentation files
- Test in development environment first

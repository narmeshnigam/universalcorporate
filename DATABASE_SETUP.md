# Database Setup Guide

## Quick Start

### 1. Configure Environment Variables

Copy the example environment file and update with your credentials:

```bash
cp .env.example .env
```

Edit `.env` and set your database credentials:

```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=universal_corporate
DB_USER=root
DB_PASS=your_password_here
DB_CHARSET=utf8mb4
```

### 2. Create Database

Run the SQL setup script to create the database and tables:

```bash
mysql -u root -p < setup-database.sql
```

Or manually in MySQL:

```sql
mysql -u root -p
source setup-database.sql;
```

### 3. Test Connection

Visit the database status page to verify everything is working:

```
http://localhost/db-status.php
```

## Files Created

- `.env` - Environment configuration (not tracked in git)
- `.env.example` - Template for environment variables
- `config/database.php` - Database connection handler
- `setup-database.sql` - Database schema and initial data
- `db-status.php` - Connection diagnostics page
- `.gitignore` - Excludes sensitive files from git

## Database Schema

### Tables

1. **contacts** - Stores contact form submissions
   - id (INT, PRIMARY KEY)
   - name (VARCHAR 255)
   - email (VARCHAR 255)
   - message (TEXT)
   - created_at (TIMESTAMP)

2. **users** - User accounts (for future use)
   - id (INT, PRIMARY KEY)
   - username (VARCHAR 100, UNIQUE)
   - email (VARCHAR 255, UNIQUE)
   - password_hash (VARCHAR 255)
   - created_at (TIMESTAMP)
   - updated_at (TIMESTAMP)

## Troubleshooting

### Connection Failed

1. Check MySQL is running:
   ```bash
   # macOS
   brew services list
   
   # Linux
   sudo systemctl status mysql
   ```

2. Verify credentials in `.env` file

3. Ensure database exists:
   ```sql
   SHOW DATABASES;
   ```

4. Check user permissions:
   ```sql
   SHOW GRANTS FOR 'root'@'localhost';
   ```

### PDO Extension Missing

Install PHP PDO extensions:

```bash
# macOS (Homebrew)
brew install php
# PDO usually comes with PHP

# Ubuntu/Debian
sudo apt-get install php-mysql php-pdo

# Check if installed
php -m | grep -i pdo
```

### Access Denied Error

Grant proper permissions:

```sql
GRANT ALL PRIVILEGES ON universal_corporate.* TO 'root'@'localhost';
FLUSH PRIVILEGES;
```

## Using the Database Connection

In your PHP files:

```php
<?php
require_once 'config/database.php';

// Get connection
$pdo = getDatabaseConnection();

if ($pdo) {
    // Execute queries
    $stmt = $pdo->prepare("SELECT * FROM contacts WHERE email = ?");
    $stmt->execute([$email]);
    $results = $stmt->fetchAll();
}
?>
```

## Security Notes

- Never commit `.env` file to version control
- Use prepared statements to prevent SQL injection
- Store passwords hashed (use `password_hash()`)
- Limit database user permissions in production
- Enable SSL for database connections in production

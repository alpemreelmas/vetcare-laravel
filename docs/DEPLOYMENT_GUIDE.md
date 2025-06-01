# 🚀 VetCare Deployment Guide

## 🎯 **Overview**

This guide provides comprehensive instructions for deploying the VetCare veterinary management system in various environments, from local development to production servers.

## 📋 **Prerequisites**

### **System Requirements**
- **PHP**: 8.1 or higher
- **Composer**: Latest version
- **Database**: MySQL 8.0+ or PostgreSQL 13+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Node.js**: 16+ (for asset compilation)
- **Redis**: 6.0+ (recommended for caching and queues)

### **PHP Extensions**
```bash
# Required PHP extensions
php -m | grep -E "(bcmath|ctype|fileinfo|json|mbstring|openssl|pdo|tokenizer|xml|curl|gd|zip)"
```

Required extensions:
- BCMath
- Ctype
- Fileinfo
- JSON
- Mbstring
- OpenSSL
- PDO
- Tokenizer
- XML
- cURL
- GD
- Zip

## 🏗️ **Environment Setup**

### **1. Local Development Environment**

#### **Clone Repository**
```bash
git clone <repository-url>
cd vetcare-laravel
```

#### **Install Dependencies**
```bash
# Install PHP dependencies
composer install

# Install Node.js dependencies (if using frontend assets)
npm install
```

#### **Environment Configuration**
```bash
# Copy environment file
cp .env.example .env

# Generate application key
php artisan key:generate
```

#### **Basic .env Configuration**
```env
APP_NAME="VetCare"
APP_ENV=local
APP_KEY=base64:generated_key_here
APP_DEBUG=true
APP_URL=http://localhost:8000

# Database Configuration
DB_CONNECTION=sqlite
DB_DATABASE=/absolute/path/to/database.sqlite

# Or for MySQL
# DB_CONNECTION=mysql
# DB_HOST=127.0.0.1
# DB_PORT=3306
# DB_DATABASE=vetcare
# DB_USERNAME=root
# DB_PASSWORD=

# Mail Configuration (for notifications)
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@vetcare.local"
MAIL_FROM_NAME="${APP_NAME}"

# File Storage
FILESYSTEM_DISK=local

# Queue Configuration
QUEUE_CONNECTION=database

# Cache Configuration
CACHE_DRIVER=file
SESSION_DRIVER=file
```

#### **Database Setup**
```bash
# Run migrations
php artisan migrate

# Seed database with sample data
php artisan db:seed

# Or use quick test data
php artisan db:seed --class=QuickTestSeeder
```

#### **Start Development Server**
```bash
php artisan serve
```

### **2. Staging Environment**

#### **Environment Configuration**
```env
APP_NAME="VetCare Staging"
APP_ENV=staging
APP_KEY=base64:generated_key_here
APP_DEBUG=false
APP_URL=https://staging.vetcare.com

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=staging-db-host
DB_PORT=3306
DB_DATABASE=vetcare_staging
DB_USERNAME=vetcare_user
DB_PASSWORD=secure_password

# Redis Configuration
REDIS_HOST=staging-redis-host
REDIS_PASSWORD=redis_password
REDIS_PORT=6379

# Cache Configuration
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=mailtrap_username
MAIL_PASSWORD=mailtrap_password
MAIL_ENCRYPTION=tls

# File Storage (S3 recommended)
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=vetcare-staging-files
```

### **3. Production Environment**

#### **Environment Configuration**
```env
APP_NAME="VetCare"
APP_ENV=production
APP_KEY=base64:generated_key_here
APP_DEBUG=false
APP_URL=https://vetcare.com

# Database Configuration
DB_CONNECTION=mysql
DB_HOST=production-db-host
DB_PORT=3306
DB_DATABASE=vetcare_production
DB_USERNAME=vetcare_user
DB_PASSWORD=very_secure_password

# Redis Configuration
REDIS_HOST=production-redis-host
REDIS_PASSWORD=redis_secure_password
REDIS_PORT=6379

# Cache Configuration
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=sendgrid_api_key
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@vetcare.com"

# File Storage
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=production_access_key
AWS_SECRET_ACCESS_KEY=production_secret_key
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=vetcare-production-files

# Security
SESSION_SECURE_COOKIE=true
SANCTUM_STATEFUL_DOMAINS=vetcare.com,www.vetcare.com
```

## 🗄️ **Database Configuration**

### **MySQL Setup**

#### **Create Database and User**
```sql
-- Connect as root
mysql -u root -p

-- Create database
CREATE DATABASE vetcare_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Create user
CREATE USER 'vetcare_user'@'localhost' IDENTIFIED BY 'secure_password';

-- Grant privileges
GRANT ALL PRIVILEGES ON vetcare_production.* TO 'vetcare_user'@'localhost';
FLUSH PRIVILEGES;
```

#### **MySQL Configuration Optimization**
```ini
# /etc/mysql/mysql.conf.d/mysqld.cnf

[mysqld]
# Performance tuning
innodb_buffer_pool_size = 1G
innodb_log_file_size = 256M
innodb_flush_log_at_trx_commit = 2
innodb_flush_method = O_DIRECT

# Character set
character-set-server = utf8mb4
collation-server = utf8mb4_unicode_ci

# Connection limits
max_connections = 200
max_user_connections = 180

# Query cache
query_cache_type = 1
query_cache_size = 128M
```

### **PostgreSQL Setup**

#### **Create Database and User**
```sql
-- Connect as postgres user
sudo -u postgres psql

-- Create user
CREATE USER vetcare_user WITH PASSWORD 'secure_password';

-- Create database
CREATE DATABASE vetcare_production OWNER vetcare_user;

-- Grant privileges
GRANT ALL PRIVILEGES ON DATABASE vetcare_production TO vetcare_user;
```

## 🔧 **Web Server Configuration**

### **Nginx Configuration**

#### **Site Configuration**
```nginx
# /etc/nginx/sites-available/vetcare.com

server {
    listen 80;
    server_name vetcare.com www.vetcare.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name vetcare.com www.vetcare.com;
    
    root /var/www/vetcare/public;
    index index.php;
    
    # SSL Configuration
    ssl_certificate /path/to/ssl/certificate.crt;
    ssl_certificate_key /path/to/ssl/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    
    # File Upload Limits
    client_max_body_size 100M;
    
    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;
    
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
    
    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }
    
    error_page 404 /index.php;
    
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
    
    location ~ /\.(?!well-known).* {
        deny all;
    }
    
    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|pdf)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

### **Apache Configuration**

#### **Virtual Host Configuration**
```apache
# /etc/apache2/sites-available/vetcare.com.conf

<VirtualHost *:80>
    ServerName vetcare.com
    ServerAlias www.vetcare.com
    Redirect permanent / https://vetcare.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName vetcare.com
    ServerAlias www.vetcare.com
    DocumentRoot /var/www/vetcare/public
    
    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /path/to/ssl/certificate.crt
    SSLCertificateKeyFile /path/to/ssl/private.key
    SSLProtocol all -SSLv3 -TLSv1 -TLSv1.1
    SSLCipherSuite ECDHE-ECDSA-AES128-GCM-SHA256:ECDHE-RSA-AES128-GCM-SHA256
    
    # Security Headers
    Header always set X-Frame-Options SAMEORIGIN
    Header always set X-Content-Type-Options nosniff
    Header always set X-XSS-Protection "1; mode=block"
    
    # PHP Configuration
    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/run/php/php8.1-fpm.sock|fcgi://localhost"
    </FilesMatch>
    
    # Directory Configuration
    <Directory /var/www/vetcare/public>
        AllowOverride All
        Require all granted
    </Directory>
    
    # File Upload Limits
    LimitRequestBody 104857600  # 100MB
    
    # Compression
    LoadModule deflate_module modules/mod_deflate.so
    <Location />
        SetOutputFilter DEFLATE
        SetEnvIfNoCase Request_URI \
            \.(?:gif|jpe?g|png)$ no-gzip dont-vary
        SetEnvIfNoCase Request_URI \
            \.(?:exe|t?gz|zip|bz2|sit|rar)$ no-gzip dont-vary
    </Location>
    
    ErrorLog ${APACHE_LOG_DIR}/vetcare_error.log
    CustomLog ${APACHE_LOG_DIR}/vetcare_access.log combined
</VirtualHost>
```

## 🔐 **Security Configuration**

### **File Permissions**
```bash
# Set proper ownership
sudo chown -R www-data:www-data /var/www/vetcare

# Set directory permissions
sudo find /var/www/vetcare -type d -exec chmod 755 {} \;

# Set file permissions
sudo find /var/www/vetcare -type f -exec chmod 644 {} \;

# Make storage and cache writable
sudo chmod -R 775 /var/www/vetcare/storage
sudo chmod -R 775 /var/www/vetcare/bootstrap/cache
```

### **Environment Security**
```bash
# Secure .env file
chmod 600 .env
chown www-data:www-data .env

# Remove sensitive files from public access
rm -f public/.env
rm -f public/composer.json
rm -f public/composer.lock
```

### **Firewall Configuration**
```bash
# UFW (Ubuntu)
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS
sudo ufw enable

# Fail2ban for SSH protection
sudo apt install fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

## 📦 **Deployment Process**

### **Automated Deployment Script**
```bash
#!/bin/bash
# deploy.sh

set -e

echo "🚀 Starting VetCare deployment..."

# Variables
APP_DIR="/var/www/vetcare"
BACKUP_DIR="/var/backups/vetcare"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Create backup
echo "📦 Creating backup..."
mkdir -p $BACKUP_DIR
mysqldump -u vetcare_user -p vetcare_production > $BACKUP_DIR/database_$TIMESTAMP.sql
tar -czf $BACKUP_DIR/files_$TIMESTAMP.tar.gz $APP_DIR

# Pull latest code
echo "📥 Pulling latest code..."
cd $APP_DIR
git pull origin main

# Install/update dependencies
echo "📚 Installing dependencies..."
composer install --no-dev --optimize-autoloader

# Clear and cache configuration
echo "🔧 Optimizing application..."
php artisan config:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
echo "🗄️ Running database migrations..."
php artisan migrate --force

# Clear application cache
php artisan cache:clear
php artisan queue:restart

# Set permissions
echo "🔒 Setting permissions..."
chown -R www-data:www-data $APP_DIR
chmod -R 775 storage bootstrap/cache

# Restart services
echo "🔄 Restarting services..."
systemctl reload nginx
systemctl restart php8.1-fpm

echo "✅ Deployment completed successfully!"
```

### **Zero-Downtime Deployment**
```bash
#!/bin/bash
# zero-downtime-deploy.sh

set -e

RELEASES_DIR="/var/www/vetcare/releases"
CURRENT_DIR="/var/www/vetcare/current"
SHARED_DIR="/var/www/vetcare/shared"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
RELEASE_DIR="$RELEASES_DIR/$TIMESTAMP"

# Create directories
mkdir -p $RELEASES_DIR $SHARED_DIR

# Clone repository
git clone <repository-url> $RELEASE_DIR
cd $RELEASE_DIR

# Install dependencies
composer install --no-dev --optimize-autoloader

# Link shared files
ln -nfs $SHARED_DIR/.env $RELEASE_DIR/.env
ln -nfs $SHARED_DIR/storage $RELEASE_DIR/storage

# Optimize application
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Run migrations
php artisan migrate --force

# Switch to new release
ln -nfs $RELEASE_DIR $CURRENT_DIR

# Restart services
systemctl reload nginx
php artisan queue:restart

# Cleanup old releases (keep last 5)
cd $RELEASES_DIR && ls -t | tail -n +6 | xargs rm -rf

echo "✅ Zero-downtime deployment completed!"
```

## 🔄 **Queue and Job Processing**

### **Supervisor Configuration**
```ini
# /etc/supervisor/conf.d/vetcare-worker.conf

[program:vetcare-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/vetcare/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=4
redirect_stderr=true
stdout_logfile=/var/www/vetcare/storage/logs/worker.log
stopwaitsecs=3600
```

### **Cron Jobs**
```bash
# Add to crontab: crontab -e

# Laravel Scheduler
* * * * * cd /var/www/vetcare && php artisan schedule:run >> /dev/null 2>&1

# Database backup (daily at 2 AM)
0 2 * * * mysqldump -u vetcare_user -p vetcare_production > /var/backups/vetcare/daily_$(date +\%Y\%m\%d).sql

# Log rotation (weekly)
0 0 * * 0 find /var/www/vetcare/storage/logs -name "*.log" -mtime +7 -delete
```

## 📊 **Monitoring and Logging**

### **Log Configuration**
```php
// config/logging.php

'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'slack'],
        'ignore_exceptions' => false,
    ],
    
    'single' => [
        'driver' => 'single',
        'path' => storage_path('logs/laravel.log'),
        'level' => env('LOG_LEVEL', 'debug'),
    ],
    
    'slack' => [
        'driver' => 'slack',
        'url' => env('LOG_SLACK_WEBHOOK_URL'),
        'username' => 'VetCare Logger',
        'emoji' => ':boom:',
        'level' => 'error',
    ],
],
```

### **Health Check Endpoint**
```php
// routes/web.php

Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'cache' => Cache::store()->getStore() ? 'connected' : 'disconnected',
    ]);
});
```

## 🔧 **Performance Optimization**

### **PHP-FPM Configuration**
```ini
# /etc/php/8.1/fpm/pool.d/www.conf

[www]
user = www-data
group = www-data
listen = /var/run/php/php8.1-fpm.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 500
```

### **Redis Configuration**
```conf
# /etc/redis/redis.conf

maxmemory 256mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

### **Application Optimization**
```bash
# Production optimizations
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# Composer optimizations
composer install --optimize-autoloader --no-dev
composer dump-autoload --optimize
```

## 🔄 **Backup Strategy**

### **Database Backup Script**
```bash
#!/bin/bash
# backup-database.sh

DB_NAME="vetcare_production"
DB_USER="vetcare_user"
DB_PASS="secure_password"
BACKUP_DIR="/var/backups/vetcare"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Create backup directory
mkdir -p $BACKUP_DIR

# Create database backup
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/database_$TIMESTAMP.sql.gz

# Upload to S3 (optional)
aws s3 cp $BACKUP_DIR/database_$TIMESTAMP.sql.gz s3://vetcare-backups/database/

# Cleanup old backups (keep last 30 days)
find $BACKUP_DIR -name "database_*.sql.gz" -mtime +30 -delete

echo "Database backup completed: database_$TIMESTAMP.sql.gz"
```

### **File Backup Script**
```bash
#!/bin/bash
# backup-files.sh

APP_DIR="/var/www/vetcare"
BACKUP_DIR="/var/backups/vetcare"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)

# Create backup
tar -czf $BACKUP_DIR/files_$TIMESTAMP.tar.gz \
    --exclude='node_modules' \
    --exclude='vendor' \
    --exclude='storage/logs' \
    --exclude='storage/framework/cache' \
    $APP_DIR

# Upload to S3 (optional)
aws s3 cp $BACKUP_DIR/files_$TIMESTAMP.tar.gz s3://vetcare-backups/files/

# Cleanup old backups
find $BACKUP_DIR -name "files_*.tar.gz" -mtime +7 -delete

echo "File backup completed: files_$TIMESTAMP.tar.gz"
```

## 🚨 **Troubleshooting**

### **Common Issues**

#### **Permission Issues**
```bash
# Fix storage permissions
sudo chown -R www-data:www-data storage/
sudo chmod -R 775 storage/

# Fix cache permissions
sudo chown -R www-data:www-data bootstrap/cache/
sudo chmod -R 775 bootstrap/cache/
```

#### **Database Connection Issues**
```bash
# Test database connection
php artisan tinker
>>> DB::connection()->getPdo();

# Check database credentials
cat .env | grep DB_
```

#### **Queue Issues**
```bash
# Restart queue workers
php artisan queue:restart

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

#### **Cache Issues**
```bash
# Clear all caches
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

### **Log Locations**
- **Application logs**: `storage/logs/laravel.log`
- **Nginx logs**: `/var/log/nginx/`
- **PHP-FPM logs**: `/var/log/php8.1-fpm.log`
- **MySQL logs**: `/var/log/mysql/`

---

This deployment guide provides a comprehensive foundation for deploying the VetCare application in various environments with proper security, performance, and monitoring considerations. 
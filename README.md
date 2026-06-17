# CPHL ICT Support Portal (CIS)

CPHL ICT Support Portal (CIS) is a centralized ICT support and ticket management platform for CPHL-supported digital systems. It provides a public issue reporting portal, ticket tracking, transparency dashboards, and an internal ICT operations dashboard for assignment, follow-up, reporting, and accountability.

## Core Purpose

CIS is designed to:

- let users report ICT issues without creating accounts
- generate unique support ticket numbers in the format `CIS-YYMMDD-XXX`
- allow public ticket tracking using ticket number plus email or phone
- give ICT teams an authenticated dashboard for assignment and status updates
- support public-facing transparency and performance dashboards
- provide configurable manager lists instead of hardcoded dropdown values
- support future integration from other CPHL systems through web links and API endpoints

## Current Modules

The current codebase includes:

- public ticket submission at `/report`
- public ticket tracking at `/track`
- public dashboard at `/dashboard/public`
- staff login and internal ICT dashboard at `/dashboard`
- ticket management pages for viewing and updating tickets
- manager list screens for systems, modules, regions, facilities, departments, issue types, priorities, statuses, resolution categories, and closure reasons
- email mailable classes for new ticket alerts, user confirmations, assignment updates, and status updates
- audit logging and ticket status history tables
- API endpoints for system/module lookups and ticket creation

## Technology Stack

- Laravel 12
- PHP 8.2+
- Blade views
- Bootstrap 5 UI
- MySQL
- SMTP-compatible mail configuration

## Project Structure

- [routes/web.php](/var/www/html/cisPortal/routes/web.php): browser routes
- [routes/api.php](/var/www/html/cisPortal/routes/api.php): integration-ready API routes
- [app/Http/Controllers](/var/www/html/cisPortal/app/Http/Controllers): public, admin, auth, and API controllers
- [app/Models](/var/www/html/cisPortal/app/Models): ticket, list, user, and audit models
- [database/migrations](/var/www/html/cisPortal/database/migrations): schema definition
- [database/seeders/DatabaseSeeder.php](/var/www/html/cisPortal/database/seeders/DatabaseSeeder.php): starter data and default staff users
- [resources/views](/var/www/html/cisPortal/resources/views): Bootstrap Blade templates

## Default Seeded Users

After seeding, these users are available:

- `admin@cphl.go.ug` / `password123`
- `supervisor@cphl.go.ug` / `password123`
- `support@cphl.go.ug` / `password123`

Change these passwords immediately on any shared or production server.

## Local Setup

1. Install system dependencies:

```bash
sudo apt update
sudo apt install -y php php-cli php-common php-mbstring php-xml php-mysql php-curl php-zip unzip composer nodejs npm
```

2. Clone or copy the project to the target folder:

```bash
git clone <your-repository-url> cisPortal
cd cisPortal
```

3. Install PHP dependencies:

```bash
composer install
```

4. Install frontend dependencies:

```bash
npm install
```

5. Prepare the environment file:

```bash
cp .env.example .env
```

6. Generate an application key if needed:

```bash
php artisan key:generate
```

7. Create the MySQL database and user:

```bash
mysql -u root -p
```

Then run:

```sql
CREATE DATABASE cis_portal CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cis_user'@'localhost' IDENTIFIED BY 'change_this';
GRANT ALL PRIVILEGES ON cis_portal.* TO 'cis_user'@'localhost';
FLUSH PRIVILEGES;
```

8. Update `.env` with the correct local settings:

```env
APP_NAME="CPHL ICT Support Portal"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cis_portal
DB_USERNAME=cis_user
DB_PASSWORD=change_this

MAIL_MAILER=log
MAIL_FROM_ADDRESS=ictsupport@cphl.go.ug
MAIL_FROM_NAME="CPHL ICT Support Portal"
```

9. Run migrations and seed starter data:

```bash
php artisan migrate --seed
```

10. Build frontend assets:

```bash
npm run build
```

11. Start the app locally:

```bash
php artisan serve
```

## Deploying On Another Server

These steps assume an Ubuntu server with Apache or Nginx.

### 1. Install server packages

```bash
sudo apt update
sudo apt install -y php php-fpm php-cli php-common php-mbstring php-xml php-mysql php-curl php-zip unzip composer nodejs npm git
```

### 2. Copy the application

```bash
cd /var/www/html
git clone <your-repository-url> cisPortal
cd cisPortal
```

### 3. Install dependencies

```bash
composer install --no-dev --optimize-autoloader
npm install
npm run build
```

### 4. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Then set production values in `.env`. Example:

```env
APP_NAME="CPHL ICT Support Portal"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://support.cphl.go.ug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cis_portal
DB_USERNAME=cis_user
DB_PASSWORD=change_this

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=your_smtp_user
MAIL_PASSWORD=your_smtp_password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=ictsupport@cphl.go.ug
MAIL_FROM_NAME="CPHL ICT Support Portal"

FILESYSTEM_DISK=local
```

### 5. Prepare the database

Create the production database and database user, then run:

```bash
php artisan migrate --seed --force
```

### 6. Set permissions

Make sure the web server can write to `storage` and `bootstrap/cache`:

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

### 7. Optimize Laravel for production

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 8. Configure the web server

Point the site root to:

```text
/var/www/html/cisPortal/public
```

If using Apache, ensure `mod_rewrite` is enabled.

If using Nginx, use a Laravel-compatible config that routes requests through `public/index.php`.

### 9. Background processing

If you enable queued mail or jobs later, run a queue worker using Supervisor or systemd:

```bash
php artisan queue:work
```

### 10. Final checks

After deployment, verify:

- home page loads
- `/report` submits tickets successfully
- `/track` finds seeded or submitted tickets
- login works for ICT staff
- email settings send correctly
- manager lists are editable
- `storage/logs/laravel.log` is clean

## Integration Pattern

External CPHL systems can link users into CIS using URLs like:

```text
https://support.cphl.go.ug/report?system=RDS&module=Results
```

or:

```text
https://support.cphl.go.ug/report?system_name=RDS&module_name=Results
```

The public form will preselect the system and module when matching records exist.

## API Endpoints

Current API routes:

- `GET /api/systems`
- `GET /api/systems/{system}/modules`
- `POST /api/tickets`

These are intended as the foundation for future integrations with other CPHL-supported applications.

## Important Notes

- manager lists should be inactivated rather than deleted when values are already in use
- production should use strong passwords and a proper SMTP configuration
- MySQL is the expected database for both local and server deployments in this project
- the seeded credentials are only for initial setup and testing

## Suggested First Commands After Cloning

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

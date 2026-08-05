# INCA Product Catalog - Deployment Guide

This guide describes how to deploy the INCA Product Catalog Management System onto a standard cPanel shared hosting or virtual private server running Apache and PHP 8.0+.

---

## 📋 Prerequisites

- Shared hosting with cPanel access (Apache web server)
- PHP 8.0 or higher enabled (PHP 8.3 recommended)
- PHP `GD` Extension enabled (required for automatic thumbnail generation)
- PHP `PDO` and `PDO_MySQL` Extensions enabled (required for database connections)
- MySQL 5.7+ or MariaDB 10.3+ database instance

---

## 🛠️ Step 1: Prepare Database in cPanel

1. Log in to your **cPanel Dashboard**.
2. Scroll to the **Databases** section and open **MySQL® Database Wizard** (MySQL® Veritabanı Sihirbazı).
3. **Create a Database**: Enter a database name (e.g., `yourusername_inca`) and click _Next Step_.
4. **Create Database User**: Enter a username (e.g., `yourusername_incaut`) and a strong password. Click _Create User_.
5. **Assign Privileges**: Check the box for **ALL PRIVILEGES** (TÜM YETKİLERİ VER) to assign the user to the database. Click _Make Changes_.
6. Save these credentials; you will need them to configure the connection:
   - **DB Host**: `localhost` (almost always `localhost` on shared hosting)
   - **DB Name**: `yourusername_inca`
   - **DB User**: `yourusername_incaut`
   - **DB Password**: `[your_strong_password]`

---

## 🗄️ Step 2: Import MySQL Schema

1. In cPanel, go back to the home dashboard and click **phpMyAdmin** in the Databases section.
2. Select your newly created database from the left-hand menu.
3. Click on the **Import** (İçe Aktar) tab at the top.
4. Click _Choose File_ (Dosya Seç) and select the [schema.sql](schema.sql) file located in the root of the project.
5. Click **Import / Go** (Git) at the bottom. phpMyAdmin should display a success message confirming all tables were imported successfully.

---

## 🔑 Step 3: Seed Default Administrator & Site Settings

Since cPanel shared hosting does not always provide SSH access to run CLI seeding utilities (`config/create_admin.php` and `config/seed_settings.php`), you can seed your first admin account and default settings directly inside phpMyAdmin:

### 1. Seed Administrator Account (Username: `admin`, Password: `admin123`)

1. In phpMyAdmin, click on your database and open the **SQL** tab.
2. Paste the following query:
   ```sql
   INSERT INTO `users` (`username`, `password_hash`, `role`, `created_at`)
   VALUES ('admin', '$2y$10$cgDFmC1K3b5rjdCI1khXgOade/WOeaVksmXVLh59qif5U9s4xtdvG', 'admin', NOW());
   ```
3. Click **Go** (Git) to run the query.
4. **IMPORTANT**: Once you log in, make sure to change your password immediately or create a new user and delete this default user to secure the system.

### 2. Seed Default Site Settings (İncaksesuar Configuration)

1. Still inside the **SQL** tab of phpMyAdmin, paste the following query:
   ```sql
   INSERT INTO `site_settings` (`setting_key`, `setting_value`) VALUES
   ('homepage_title', 'İncaksesuar | Hırdavat ve Mobilya Aksesuarları'),
   ('homepage_description', 'Blum, Hafele, Samet, Starax ve Fors gibi sektörün öncü markalarının menteşe, ray ve mobilya aksesuar gruplarını uygun fiyatlarla sunuyoruz.'),
   ('homepage_keywords', 'incaksesuar, inca hırdavat, mobilya aksesuarları, blum menteşe, hafele, samet, starax, fors, çekmece rayı, ikitelli keresteciler sitesi, başakşehir'),
   ('homepage_slider_title', 'Kaliteli Hırdavat & Mobilya Malzemeleri'),
   ('homepage_slider_desc', 'İncaksesuar olarak, en seçkin markaların ürünlerini en uygun fiyatlarla mağazamızda sizlerle buluşturuyoruz. En iyi fiyat tekliflerimiz için dükkanımıza davetlisiniz.'),
   ('company_phone', '0555 066 33 27'),
   ('company_email', 'info@incaksesuar.com'),
   ('company_address', 'İkitelli Keresteciler Sitesi 21. Blok No:15, Başakşehir / İstanbul'),
   ('facebook_url', 'https://facebook.com/incaksesuar'),
   ('instagram_url', 'https://instagram.com/incaksesuar')
   ON DUPLICATE KEY UPDATE `setting_value` = VALUES(`setting_value`);
   ```
2. Click **Go** (Git) to run the query. This will pre-populate all site configurations.

---

## 📁 Step 4: Configure Database Connection

1. Locate the configuration file [config/db.php](config/db.php).
2. Edit the database constants to match your cPanel credentials:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'yourusername_inca'); // Replace with cPanel DB Name
   define('DB_USER', 'yourusername_incaut'); // Replace with cPanel DB User
   define('DB_PASS', 'your_strong_password'); // Replace with cPanel DB Password
   ```
3. Save the file.

---

## 📤 Step 5: Upload Files to Hosting

1. Compress all project files into a `.zip` archive on your computer.
2. In cPanel, open **File Manager** (Dosya Yöneticisi).
3. Navigate to **`public_html`** (or the target subfolder if you are deploying to a subdomain).
4. Click **Upload** (Yükle) at the top, select your `.zip` archive, and upload it.
5. Once uploaded, select the `.zip` file in File Manager and click **Extract** (Çıkar) in the top toolbar.
6. Delete the uploaded `.zip` archive for security.

---

## ⚙️ Step 6: Verify PHP Version & Directory Permissions

### 1. Select PHP Version

- In cPanel, search for **Select PHP Version** (PHP Sürümü Seç) or **MultiPHP Manager**.
- Set the PHP version for your domain to **8.0, 8.1, 8.2, or 8.3**.
- Ensure the extensions `gd`, `pdo_mysql`, and `fileinfo` are enabled in the PHP extensions list.

### 2. Configure File Permissions

- Standard files (PHP, HTML, CSS, JS) should be set to **`0644`**.
- Directories should be set to **`0755`**.
- Ensure the `/uploads` directory and its subfolders are writeable:
  - `/uploads/products/` -> **`0755`** (or **`0777`** if your hosting environment is strictly restricted)
  - `/uploads/pdf/` -> **`0755`** (or **`0777`**)

---

## 🌐 Step 7: Verify Clean URL Rewriting

The system contains a pre-configured [.htaccess](.htaccess) file. This allows Apache to handle clean URLs (e.g., rendering `/product/some-slug` via `/public/product.php?slug=some-slug`).

- Visit `https://yourdomain.com/` (Home page)
- Visit `https://yourdomain.com/products` (Product list)
- Visit `https://yourdomain.com/admin` (Admin login)

If these load without showing a `404 Not Found` or `500 Internal Server Error`, rewrite rules are working correctly.

---

## 🔍 Troubleshooting & Common Errors

### 1. `500 Internal Server Error`

- **Cause**: Incorrect `.htaccess` commands or invalid directory permissions.
- **Solution**: Check cPanel **Errors** log. If your hosting does not allow custom directory indexing configurations, edit `.htaccess` and comment out the `Options -Indexes` line by prefixing it with `#`:
  ```apache
  # Options -Indexes
  ```
  Ensure folders do not have `0777` permissions if your server runs `suPHP` or `FastCGI` (which block execution on 777 folders for security). Change them back to `0755`.

### 2. `Database connection error` (500 Error / Die message)

- **Cause**: Incorrect credentials inside `config/db.php` or the MySQL user does not have permission to access that database.
- **Solution**: Re-verify DB name, user, and password. Make sure you associated the user with the database inside cPanel MySQL Database Wizard and granted **ALL PRIVILEGES**.

### 3. File upload fails: "Dosya sunucuya kaydedilemedi" or "Klasör izinlerini kontrol edin"

- **Cause**: The web server cannot write files to `/uploads/products/` or `/uploads/pdf/`.
- **Solution**: In cPanel File Manager, right-click on the `uploads` directory, select _Change Permissions_, and set it to `0755`. If it still fails, set it to `0777` temporarily to see if it fixes the permission problem.

### 4. Uploaded images do not show thumbnails

- **Cause**: PHP GD extension is missing or disabled.
- **Solution**: Enable `gd` in cPanel _Select PHP Version_ under the _Extensions_ tab.

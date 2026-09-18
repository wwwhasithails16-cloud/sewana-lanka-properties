SEWANA LANKA WEBSITE — XAMPP SETUP

NEW FEATURES
- Only a logged-in administrator can upload property posts.
- Each property supports 1–10 photos and an optional single video.
- Administrator can mark a property Available or Sold Out at any time.
- Customers can filter properties by any Sri Lankan district.
- Customers can filter by property type (House, Land, Commercial or Apartment) and minimum/maximum price.
- Each property has a separate page where photos and video are viewed individually.
- Property deletion also removes its uploaded media.
- Footer includes Powered by Dark Forest and the supplied contact information.
- Admin login is removed from the public viewer and uses a direct private URL.
- Permanent-OTP password recovery includes expiry, rate limiting and attempt locking.

A. NEW INSTALLATION

1. Copy the folder "sewana_lanka_district_media_status" into:
   C:\xampp\htdocs\

2. Start Apache and MySQL in XAMPP Control Panel.

3. Open:
   http://localhost/phpmyadmin

4. Click Import and select:
   schema.sql

5. Open the website:
   http://localhost/sewana_lanka_district_media_status/

6. Admin login:
   http://localhost/sewana_lanka_district_media_status/sewana-admin-access.php

   Username: admin
   Password: DarkForest@123

   Permanent recovery OTP: 618427

B. UPGRADING YOUR EXISTING DATABASE

1. Back up your current database and uploads folder.
2. Replace the website PHP/CSS files with the new files.
3. In phpMyAdmin, select sewana_lanka_db.
4. Import update_database.sql.
5. Keep your existing uploads folder and existing uploaded files.

PHP PHOTO AND VIDEO UPLOAD SETTINGS

Open:
C:\xampp\php\php.ini

Recommended settings:
upload_max_filesize = 1024M
post_max_size = 1700M
max_file_uploads = 20
max_execution_time = 1800
max_input_time = 1800

Save php.ini and restart Apache.

These values allow one complete property post containing up to ten 50 MB
photos and one 1 GB video. The included .htaccess applies matching values when
the website runs through the PHP module supplied with XAMPP.

IMPORTANT
- Change the default administrator password before publishing online.
- Change the supplied permanent OTP hash in sms_config.php before publishing.
- Keep the direct admin URL private; it is not shown anywhere on the public viewer.
- Keep the uploads folder writable.
- Database settings are in db.php.
- Supported photos: JPG, PNG and WEBP, maximum 50 MB each.
- Supported video: MP4, WEBM or OGG, maximum 1 GB.
- Email addresses do not use "www"; the correct email is hasithails16@gmail.com.
IMPORTANT DATABASE SETUP
------------------------
For a new installation, import schema.sql in phpMyAdmin.
For an existing/older installation, import update_database.sql once.
The property type and price filters require the price_amount and property_type columns.

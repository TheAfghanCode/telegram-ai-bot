# استفاده از نسخه PHP با Apache (وب‌سرور)
FROM php:8.2-apache

# کپی کردن فایل‌های پروژه به پوشه وب‌سرور
COPY . /var/www/html/

# فعال‌سازی مود بازنویسی (برای پروژه‌هایی که نیاز دارن)
RUN a2enmod rewrite

# EXPOSE برای پورت 80
EXPOSE 80

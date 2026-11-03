FROM php:8.2-apache

RUN docker-php-ext-install pdo_mysql \
 && echo "date.timezone = Asia/Bangkok" > /usr/local/etc/php/conf.d/timezone.ini \
 && echo "upload_max_filesize = 5M" > /usr/local/etc/php/conf.d/uploads.ini

COPY . /var/www/html/

# Apache รันในชื่อ www-data ต้องเป็นเจ้าของ uploads ถึงจะบันทึกรูปและสลิปได้
# chmod จำเป็นเพราะ OneDrive ติด read-only ให้โฟลเดอร์บน Windows แล้ว COPY ได้สิทธิ์ r-x มา
# volume uploads คัดลอกโฟลเดอร์และสิทธิ์นี้จาก image ตอนสร้างครั้งแรก
RUN mkdir -p uploads/products uploads/slips \
 && chown -R www-data:www-data uploads \
 && chmod -R u+rwX uploads

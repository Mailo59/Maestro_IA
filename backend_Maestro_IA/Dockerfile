FROM php:8.3-apache

# 1. Instalar dependencias del sistema y extensiones de PHP necesarias para PostgreSQL
RUN apt-get update && apt-get install -y \
    libpq-dev \
    zip \
    unzip \
    git \
    && docker-php-ext-install pdo pdo_pgsql

# 2. Habilitar el módulo mod_rewrite de Apache para el enrutamiento de Laravel
RUN a2enmod rewrite

# 3. Cambiar el DocumentRoot de Apache para que apunte a la carpeta public de Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# 4. Copiar los archivos del proyecto al contenedor
WORKDIR /var/www/html
COPY . .

# 5. Instalar Composer de forma global y descargar las dependencias de producción
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 6. Configurar los permisos correctos para las carpetas de almacenamiento de Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# 7. Exponer el puerto por defecto que requiere Render
EXPOSE 80

# 8. Comando de arranque del servidor Apache
CMD ["apache2-foreground"]
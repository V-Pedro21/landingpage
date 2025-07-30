FROM php:8.1-apache

# Copiar todos los archivos del proyecto al servidor web
COPY . /var/www/html/

# Dar permisos adecuados
RUN chown -R www-data:www-data /var/www/html

# Exponer el puerto 80 para acceder vía web
EXPOSE 80

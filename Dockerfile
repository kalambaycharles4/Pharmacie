FROM php:8.2-apache

# Installation des extensions MySQL PDO nécessaires
RUN docker-php-ext-install pdo pdo_mysql

# Activation du module rewrite d'Apache
RUN a2enmod rewrite

# Copie des fichiers de l'application
COPY . /var/www/html/

# Permissions pour le dossier d'images uploads
RUN chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

# Port d'écoute du conteneur
EXPOSE 80

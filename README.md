# info_401
FROM php:8.3-apache

RUN cp "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

# Installation des extensions PHP
RUN docker-php-ext-install mysqli pdo pdo_mysql 

# Si vous avez besoin d'activer le module rewrite (courant pour les frameworks PHP)
# RUN a2enmod rewrite && service apache2 restart

# Vous pouvez ajouter d'autres configurations PHP ou Apache ici

FROM ubuntu:latest

# Installation des packages nécessaires
RUN apt-get update && \
    apt-get install -y vim nano git links curl wget telnet dnsutils iproute2 net-tools mysql-client && \
    rm -rf /var/lib/apt/lists/*

# Commande pour maintenir le conteneur actif
CMD ["tail", "-f", "/dev/null"]


fait par favreau phélim et guessoum yanis
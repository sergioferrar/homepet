FROM php:8.3-apache

# Instala dependências do sistema e bibliotecas necessárias
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    curl \
    libicu-dev \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    && docker-php-ext-configure gd \
        --with-freetype \
        --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        intl \
        zip \
        opcache \
        gd \
    && a2enmod rewrite headers ssl \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Cria a pasta para armazenar os certificados SSL com segurança
RUN mkdir -p /etc/apache2/ssl

# Instala o Composer 2
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configura o VirtualHost do Apache para o Symfony (HTTP e HTTPS)
RUN printf '%s\n' \
    '<VirtualHost *:80>' \
    '    DocumentRoot /var/www/html/public' \
    '    <Directory /var/www/html/public>' \
    '        AllowOverride All' \
    '        Require all granted' \
    '        Options FollowSymLinks' \
    '        DirectoryIndex index.php' \
    '    </Directory>' \
    '    # Redireciona todo o tráfego HTTP para HTTPS' \
    '    RewriteEngine On' \
    '    RewriteCond %{HTTPS} off' \
    '    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]' \
    '    ErrorLog ${APACHE_LOG_DIR}/error.log' \
    '    CustomLog ${APACHE_LOG_DIR}/access.log combined' \
    '</VirtualHost>' \
    '' \
    '<VirtualHost *:443>' \
    '    DocumentRoot /var/www/html/public' \
    '    <Directory /var/www/html/public>' \
    '        AllowOverride All' \
    '        Require all granted' \
    '        Options FollowSymLinks' \
    '        DirectoryIndex index.php' \
    '    </Directory>' \
    '' \
    '    SSLEngine on' \
    '    SSLCertificateFile /etc/apache2/ssl/certificado.crt' \
    '    SSLCertificateFileKey /etc/apache2/ssl/chave.key' \
    '' \
    '    ErrorLog ${APACHE_LOG_DIR}/error_ssl.log' \
    '    CustomLog ${APACHE_LOG_DIR}/access_ssl.log combined' \
    '</VirtualHost>' \
    > /etc/apache2/sites-available/000-default.conf

# Define o diretório de trabalho da aplicação
WORKDIR /var/www/html

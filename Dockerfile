# Используем официальный образ PHP 8.3
FROM php:8.3-cli

# Устанавливаем расширение mysqli
RUN docker-php-ext-install mysqli

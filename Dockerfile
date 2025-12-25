FROM php:8.4-cli

RUN apt update && apt install --no-install-recommends -y \
  python3-pip firefox-esr unzip curl libzip-dev \
  && rm -rf /var/lib/apt/lists/* /var/cache/apt/archives

RUN docker-php-ext-install zip

RUN cd /usr/bin && curl https://getcomposer.org/installer | php

WORKDIR /app

COPY composer.json composer.lock requirements.txt /app/

RUN composer.phar install

RUN pip install -r requirements.txt --break-system-packages

RUN curl -OL https://github.com/mozilla/geckodriver/releases/download/v0.36.0/geckodriver-v0.36.0-linux64.tar.gz && \
  tar -xzvf geckodriver-v0.36.0-linux64.tar.gz && \
  mv geckodriver /usr/bin/geckodriver

CMD php -S 0.0.0.0:8000 &

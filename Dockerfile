FROM debian

USER root

# Install PHP
RUN apt-get update
RUN apt-get install -y -q php5-cli git curl

# Install composer
RUN curl -s http://getcomposer.org/installer | php && mv composer.phar /bin/composer

# create non privileged user
RUN mkdir /opt/composer-dist-cache && \
	groupadd -r cache && \
	useradd -r -g cache -d /opt/composer-dist-cache -s /sbin/nologin cache && \
	chown -R cache:cache /opt/composer-dist-cache

USER cache

# install composer-dist-cache
RUN cd /opt && git clone https://github.com/Vektah/composer-dist-cache.git
RUN cd /opt/composer-dist-cache && composer install --no-dev
RUN mkdir /opt/composer-dist-cache/cache

WORKDIR /opt/composer-dist-cache
EXPOSE 1234

ENTRYPOINT ["/usr/bin/php", "/opt/composer-dist-cache/bin/run", "web", "0.0.0.0", "1234", "-vvv"]
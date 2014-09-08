FROM debian:wheezy

USER root

# Install PHP
RUN ["/bin/bash", "-c", "echo deb http://ftp.debian.org/debian/ wheezy-backports main non-free contrib >> /etc/apt/sources.list"]
RUN apt-get update && apt-get -y -q -t wheezy-backports install git
RUN apt-get install -y -q php5-cli curl zip bzip2 gzip

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

RUN echo '{"hostname": "0.0.0.0", "port": 1234}' > /opt/composer-dist-cache/config.json

WORKDIR /opt/composer-dist-cache
EXPOSE 1234

CMD ["web", "-vvv"]
ENTRYPOINT ["/usr/bin/php", "/opt/composer-dist-cache/bin/run"]
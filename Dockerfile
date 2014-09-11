FROM debian:wheezy

USER root

#Doesn't like this old version of git.
RUN ["/bin/bash", "-c", "echo deb http://ftp.debian.org/debian/ wheezy-backports main non-free contrib >> /etc/apt/sources.list"]
RUN apt-get update && apt-get -y -q -t wheezy-backports install git
# Install PHP
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

#Add directory need RSA key git git:// protocol to download libraries
ADD ssh /opt/composer-dist-cache/.ssh

#Hack here to chown and permission in one line, docker not playing nice.
USER root
RUN chown -R cache:cache /opt/composer-dist-cache/.ssh && chmod -R 700 /opt/composer-dist-cache/.ssh
USER cache

ADD config.json /opt/composer-dist-cache/config.json

WORKDIR /opt/composer-dist-cache
EXPOSE 1234

CMD ["web", "-vvv"]
ENTRYPOINT ["/usr/bin/php", "/opt/composer-dist-cache/bin/run"]

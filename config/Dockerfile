FROM alpine:latest
MAINTAINER xico <xico@simbio.se>
USER root

RUN echo "@testing http://nl.alpinelinux.org/alpine/edge/testing" >> /etc/apk/repositories && apk update && \
  apk upgrade && apk add --update tzdata && cp /usr/share/zoneinfo/UTC /etc/localtime && echo "UTC" > /etc/timezone && \
  apk add --update shadow@testing daemontools@testing bash vim curl tree git nginx htop xz logrotate rsyslog msmtp mailx \
  gettext nodejs@testing postgresql postgresql-client php7-curl@testing php7-fpm@testing php7-json@testing php7-opcache@testing \
  php7-openssl@testing php7-pdo@testing php7-pdo_pgsql@testing php7-phar@testing php7-iconv@testing php7-apcu@testing && \
  apk del tzdata && rm -rf /var/cache/apk/* && curl -sS https://getcomposer.org/installer | php7 && \
  mv composer.phar /usr/sbin/composer && chmod +x /usr/sbin/composer && useradd www-data && adduser www-data www-data

# php7-dom@edge -> /alpine/edge/community

ADD defaults/root    /root/
ADD defaults/etc     /etc/
ADD defaults/service /service/

RUN chown root /etc/bootstrap && chmod +x /etc/bootstrap && chmod 1755 /service/*/run && \
  chmod 1755 /service/*/log/run && mkdir -m 700 -p /var/postgresql/data && \
  chown postgres /var/postgresql/data

ENV BOOTSTRAP /etc/bootstrap

EXPOSE 5432 80

CMD ["/etc/bootstrap", "-bash"]

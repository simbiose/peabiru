FROM alpine:3.7
MAINTAINER xico <xico@simbio.se>
USER root

RUN echo "@testing http://nl.alpinelinux.org/alpine/edge/testing" >> /etc/apk/repositories && apk update && \
  apk upgrade && apk add --update tzdata && cp /usr/share/zoneinfo/UTC /etc/localtime && echo "UTC" > /etc/timezone && \
  apk add shadow daemontools@testing bash vim curl tree git nginx htop xz logrotate rsyslog msmtp mailx \
  gettext nodejs postgresql postgresql-client 'php7=~7.1' php7-curl php7-fpm php7-json php7-opcache \
  php7-openssl php7-pdo php7-pdo_pgsql php7-phar php7-iconv php7-apcu && apk del tzdata && \
  rm -rf /var/cache/apk/* && curl -sS https://getcomposer.org/installer | php7 && \
  mv composer.phar /usr/sbin/composer && chmod +x /usr/sbin/composer && useradd www-data -g www-data

ADD defaults/root    /root/
ADD defaults/etc     /etc/
ADD defaults/service /service/

RUN chown root /etc/bootstrap && chmod +x /etc/bootstrap && chmod 1755 /service/*/run && \
  chmod 1755 /service/*/log/run && mkdir -m 700 -p /var/postgresql/data && \
  chown postgres /var/postgresql/data

ENV BOOTSTRAP /etc/bootstrap

EXPOSE 5432 80

CMD ["/etc/bootstrap", "-bash"]

FROM webdevops/php-apache:7.2

ADD ./config /app/config
ADD ./public /app/public
ADD ./resources /app/resources
ADD ./src /app/src
ADD ./vendor /app/vendor
ADD ./dump_sql /app/dump_sql
ADD ./bin /app/bin

RUN mkdir /app/logs
RUN chown -R application:application /app/logs
RUN chown -R application:application /app/public/uploads

WORKDIR /app/

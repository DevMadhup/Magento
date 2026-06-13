FROM magento-php:latest

USER root

RUN apt-get update && apt-get install -y cron

COPY cron.sh /cron.sh

RUN chmod +x /cron.sh

USER test

CMD ["/cron.sh"]

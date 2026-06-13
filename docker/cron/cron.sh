#!/bin/bash

while true
do
    php /var/www/html/bin/magento cron:run
    sleep 60
done

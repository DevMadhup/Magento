# AWS Magento Deployment Guide

> **Docker-Based Production Setup on EC2 (Debian 12)**  
> Stack: Magento 2.4.8 · PHP 8.3 · MySQL 8.0 · OpenSearch 2.11 · Redis 7 · Nginx

Flow:

<img src="https://github.com/DevMadhup/Magento/blob/main/ecommerce_request_flow_v2.png?raw=true" width="550" alt="Magento Architecture" />

---

## Table of Contents

1. [Phase 1 — AWS EC2 Preparation](#phase-1--aws-ec2-preparation)
2. [Phase 2 — Create Repository Structure](#phase-2--create-repository-structure)
3. [Phase 3 — Build the Magento PHP Image](#phase-3--build-the-magento-php-image)
4. [Phase 4 — MySQL Configuration](#phase-4--mysql-configuration)
5. [Phase 5 — OpenSearch & System Configuration](#phase-5--opensearch--system-configuration)
6. [Phase 6 — Cron Container](#phase-6--cron-container)
7. [Phase 7 — Nginx Configuration](#phase-7--nginx-configuration)
8. [Phase 8 — Magento Installation](#phase-8--magento-installation)
9. [Phase 9 — Troubleshooting](#phase-9--troubleshooting)
10. [Phase 10 — Post-Installation Steps](#phase-10--post-installation-steps)

---

## Phase 1 — AWS EC2 Preparation

### Step 1: Launch EC2 Instance

Create a new EC2 instance with the following settings:

| Parameter     | Value             |
|---------------|-------------------|
| Name          | `magento-devops`  |
| AMI           | Debian 12 (Bookworm) |
| Instance Type | `t3.small`        |
| Storage       | 30 GB gp3         |

**Security Group Rules:**

| Port | Source      | Purpose |
|------|-------------|---------|
| 22   | Your IP     | SSH     |
| 80   | 0.0.0.0/0   | HTTP    |
| 443  | 0.0.0.0/0   | HTTPS   |

---

### Step 2: Update the Operating System

```bash
sudo apt update
sudo apt upgrade -y
```

---

### Step 3: Create Required User and Group

The assessment requires:
- **user**: `test`
- **group**: `clp`

```bash
sudo groupadd clp

sudo useradd \
  -u 1001 \
  -g clp \
  -m \
  -s /bin/bash \
  test
```

> **Note:** UID `1001` must match the UID defined inside the Docker container.

---

### Step 4: Configure Swap

Magento + OpenSearch requires more memory than a t3.small provides by default. Create a 4 GB swap file:

```bash
sudo fallocate -l 4G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
```

Persist across reboots:

```bash
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
free -h
```

---

### Step 5: Install Docker

```bash
sudo apt install -y docker.io
sudo systemctl enable docker
sudo systemctl start docker
docker --version
```

---

### Step 6: Install Docker Compose Plugin

```bash
sudo apt install -y docker-compose-v2
docker compose version
```

---

### Step 7: Add User to Docker Group

```bash
sudo usermod -aG docker test
```

---

### Step 8: Create Project Directory

```bash
sudo mkdir -p /opt/magento-devops
sudo chown -R test:clp /opt/magento-devops
sudo su - test
cd /opt/magento-devops
```

---

## Phase 2 — Create Repository Structure

### Directory Layout

```bash
cd /opt/magento-devops

mkdir -p \
  docker/php \
  docker/nginx/ssl \
  docker/varnish \
  docker/cron \
  php-fpm \
  mysql \
  scripts
```

---

### Create `.env` File

```bash
nano .env
```

```env
MYSQL_ROOT_PASSWORD=rootpass123
MYSQL_DATABASE=magento
MYSQL_USER=magento
MYSQL_PASSWORD=magento123

MAGENTO_ADMIN_USER=admin
MAGENTO_ADMIN_PASSWORD=Admin@123456
MAGENTO_ADMIN_EMAIL=admin@test.dyna.com

DOMAIN=test.dyna.com
```

---

### Create `docker-compose.yml`

```bash
nano docker-compose.yml
```

```yaml
services:

  php:
    build:
      context: ./docker/php
    container_name: php
    restart: unless-stopped
    volumes:
      - ./app:/var/www/html
    networks:
      - magento

  cron:
    build:
      context: ./docker/cron
    container_name: cron
    restart: unless-stopped
    depends_on:
      - php
    volumes:
      - ./app:/var/www/html
    networks:
      - magento

  nginx:
    image: nginx:alpine
    container_name: nginx
    restart: unless-stopped
    depends_on:
      - php
    ports:
      - "80:80"
    volumes:
      - ./app:/var/www/html
      - ./nginx/default.conf:/etc/nginx/conf.d/default.conf
    networks:
      - magento

  mysql:
    image: mysql:8.0
    container_name: mysql
    restart: unless-stopped
    env_file:
      - .env
    command:
      - --default-authentication-plugin=mysql_native_password
      - --log-bin-trust-function-creators=1
    volumes:
      - mysql_data:/var/lib/mysql
      - ./mysql/my.cnf:/etc/mysql/conf.d/my.cnf
    networks:
      - magento

  redis:
    image: redis:7
    container_name: redis
    restart: unless-stopped
    command:
      - redis-server
      - --appendonly
      - "yes"
    volumes:
      - redis_data:/data
    networks:
      - magento

  opensearch:
    image: opensearchproject/opensearch:2.11.1
    container_name: opensearch
    restart: unless-stopped
    environment:
      discovery.type: single-node
      bootstrap.memory_lock: "true"
      OPENSEARCH_JAVA_OPTS: "-Xms512m -Xmx512m"
      DISABLE_SECURITY_PLUGIN: "true"
    ulimits:
      memlock:
        soft: -1
        hard: -1
    volumes:
      - opensearch_data:/usr/share/opensearch/data
    networks:
      - magento

  phpmyadmin:
    image: phpmyadmin/phpmyadmin
    container_name: phpmyadmin
    restart: unless-stopped
    networks:
      - magento

networks:
  magento:
    driver: bridge

volumes:
  mysql_data:
  redis_data:
  opensearch_data:
```

---

## Phase 3 — Build the Magento PHP Image

### Create `docker/php/Dockerfile`

```bash
cd /opt/magento-devops/docker/php
nano Dockerfile
```

```dockerfile
FROM composer:2.8 AS composer

FROM php:8.3-fpm-bookworm

RUN apt-get update && apt-get install -y \
    git curl unzip zip cron wget vim \
    libzip-dev libicu-dev libxml2-dev libxslt1-dev \
    libpng-dev libjpeg62-turbo-dev libfreetype6-dev \
    libonig-dev libcurl4-openssl-dev libssl-dev \
    libgmp-dev libldap2-dev pkg-config \
    && rm -rf /var/lib/apt/lists/*

RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg

RUN docker-php-ext-install -j$(nproc) \
    bcmath ftp sockets gd intl mysqli \
    pdo_mysql soap xsl zip

RUN pecl install redis && \
    docker-php-ext-enable redis

COPY --from=composer /usr/bin/composer /usr/bin/composer

COPY php.ini /usr/local/etc/php/conf.d/custom.ini

RUN groupadd -g 1001 clp && \
    useradd -u 1001 -g clp -m -s /bin/bash test

WORKDIR /var/www/html

USER test

CMD ["php-fpm"]
```

---

### Create `docker/php/php.ini`

```bash
nano php.ini
```

```ini
memory_limit = 2G
max_execution_time = 1800
max_input_vars = 100000
upload_max_filesize = 128M
post_max_size = 128M
date.timezone = UTC

opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=2
```

---

### Build and Test

```bash
cd docker/php
docker build -t magento-php .
docker run --rm -it magento-php whoami
# Expected output: test
```

---

## Phase 4 — MySQL Configuration

### Create `mysql/my.cnf`

```bash
cd /opt/magento-devops/mysql
nano my.cnf
```

```ini
[mysqld]
default_authentication_plugin=mysql_native_password
log_bin_trust_function_creators=1
innodb_buffer_pool_size=256M
innodb_log_file_size=64M
innodb_flush_method=O_DIRECT
max_connections=100
character-set-server=utf8mb4
collation-server=utf8mb4_unicode_ci
```

> **Note:** `log_bin_trust_function_creators=1` is required for Magento trigger creation when binary logging is enabled.

---

## Phase 5 — OpenSearch & System Configuration

### Create OpenSearch Directory

```bash
mkdir -p /opt/magento-devops/opensearch
```

### Set Kernel vm.max_map_count

OpenSearch requires a higher virtual memory map limit:

```bash
sudo sysctl -w vm.max_map_count=262144
echo "vm.max_map_count=262144" | sudo tee -a /etc/sysctl.conf
sudo sysctl -p
```

---

## Phase 6 — Cron Container

### Create `docker/cron/Dockerfile`

```bash
cd /opt/magento-devops/docker/cron
nano Dockerfile
```

```dockerfile
FROM magento-php:latest

USER root

RUN apt-get update && apt-get install -y cron

COPY cron.sh /cron.sh

RUN chmod +x /cron.sh

USER test

CMD ["/cron.sh"]
```

### Create `docker/cron/cron.sh`

```bash
nano cron.sh
```

```bash
#!/bin/bash

while true
do
    php /var/www/html/bin/magento cron:run
    sleep 60
done
```

---

## Phase 7 — Nginx Configuration

```bash
mkdir -p /opt/magento-devops/nginx
vi nginx/default.conf
```

```nginx
server {
    listen 80;
    server_name test.dyna.com;

    set $MAGE_ROOT /var/www/html;
    set $MAGE_MODE developer;

    root $MAGE_ROOT/pub;
    index index.php;

    charset utf-8;
    error_page 404 403 = /errors/404.php;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location /pub/ {
        location ~ ^/pub/media/(downloadable|customer|import|custom_options|theme_customization/.*\.xml) {
            deny all;
        }
        alias $MAGE_ROOT/pub/;
        add_header X-Frame-Options "SAMEORIGIN";
    }

    location /static/ {
        expires max;
        location ~ ^/static/version {
            rewrite ^/static/(version\d*/)?(.*)$ /static/$2 last;
        }
        location ~* \.(ico|jpg|jpeg|png|gif|svg|js|css|woff|woff2|ttf|eot)$ {
            expires 1y;
            add_header Cache-Control "public";
        }
        if (!-f $request_filename) {
            rewrite ^/static/(version\d*/)?(.*)$ /static.php?resource=$2 last;
        }
        add_header X-Frame-Options "SAMEORIGIN";
    }

    location /media/ {
        try_files $uri $uri/ /get.php$is_args$args;
        location ~ ^/media/theme_customization/.*\.xml { deny all; }
        location ~* \.(ico|jpg|jpeg|png|gif|svg|js|css|woff|woff2|ttf|eot)$ {
            expires 1y;
            add_header Cache-Control "public";
        }
        add_header X-Frame-Options "SAMEORIGIN";
    }

    location ~ (index|get|static|report|404|503)\.php$ {
        try_files $uri =404;
        fastcgi_pass php:9000;
        fastcgi_buffers 16 16k;
        fastcgi_buffer_size 32k;
        fastcgi_param PHP_FLAG  "session.auto_start=off";
        fastcgi_param PHP_VALUE "memory_limit=2G";
        fastcgi_read_timeout 600s;
        fastcgi_connect_timeout 600s;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_param MAGE_RUN_TYPE store;
        fastcgi_param MAGE_RUN_CODE default;
    }

    location ~* \.(php|phtml|htaccess|git)$ {
        deny all;
    }
}
```

---

## Phase 8 — Magento Installation

### Obtain Magento Marketplace Credentials

1. Go to [https://marketplace.magento.com/](https://marketplace.magento.com/)
2. Log in → **My Profile** → **Access Keys**
3. Click **Create New Access Key**
4. Copy your **Public Key** and **Private Key**

### Start All Containers

```bash
mkdir -p /opt/magento-devops/app
docker compose down
docker compose up -d --build
docker ps
```

### Configure Composer Authentication

```bash
docker exec -it php bash
composer --version
composer config --global http-basic.repo.magento.com <PUBLIC_KEY> <PRIVATE_KEY>
```

### Install Magento via Composer

```bash
cd /var/www/html

# Clean any previous attempt
find /var/www/html -mindepth 1 -maxdepth 1 ! -name '.composer' -exec rm -rf {} \;

composer create-project \
  --repository-url=https://repo.magento.com/ \
  magento/project-community-edition=2.4.8 .
```

### Verify Prerequisites

**MySQL:**
```bash
docker exec -it mysql mysql -uroot -p
SHOW DATABASES;
exit
```

**OpenSearch:**
```bash
docker exec -it opensearch curl -k -u admin:admin https://localhost:9200
```

### Run the Magento Installer

```bash
docker exec -it php bash
cd /var/www/html

php bin/magento setup:install \
  --base-url=http://test.dyna.com \
  --db-host=mysql \
  --db-name=magento \
  --db-user=magento \
  --db-password=magento123 \
  --admin-firstname=Admin \
  --admin-lastname=User \
  --admin-email=admin@test.dyna.com \
  --admin-user=admin \
  --admin-password='Admin@123456' \
  --language=en_US \
  --currency=USD \
  --timezone=UTC \
  --use-rewrites=1 \
  --search-engine=opensearch \
  --opensearch-host=opensearch \
  --opensearch-port=9200 \
  --opensearch-enable-auth=1 \
  --opensearch-username=admin \
  --opensearch-password='Strong@OpenSearch2026!'
```

---

## Phase 9 — Troubleshooting

### Fix: `SQLSTATE[HY000]` Error 1419 — Binary Logging / SUPER Privilege

Magento creates MySQL triggers during installation. With binary logging enabled you must allow function creators.

**Apply at runtime:**
```bash
docker exec -it mysql mysql -uroot -p
SET GLOBAL log_bin_trust_function_creators = 1;
SHOW VARIABLES LIKE 'log_bin_trust_function_creators';
exit
```

Expected output:
```
+---------------------------------+-------+
| Variable_name                   | Value |
+---------------------------------+-------+
| log_bin_trust_function_creators | ON    |
+---------------------------------+-------+
```

**Make it persistent** — add to `mysql/my.cnf`:
```ini
[mysqld]
log_bin_trust_function_creators=1
```

```bash
docker compose restart mysql
docker exec -it mysql mysql -uroot -prootpass123 \
  -e "SHOW VARIABLES LIKE 'log_bin_trust_function_creators';"
```

### Re-run After Fixing

```bash
docker exec -it php bash
cd /var/www/html
php bin/magento setup:uninstall -n
```

```bash
docker exec -it mysql mysql -uroot -prootpass123
DROP DATABASE magento;
CREATE DATABASE magento;
EXIT;
```

Then re-run the `setup:install` command from Phase 8.

---

## Phase 10 — Post-Installation Steps

### Fix Permissions

```bash
chmod -R 777 app/etc
```

### Disable Two-Factor Authentication *(Dev only)*

```bash
cd /var/www/html

php bin/magento module:disable \
  Magento_AdminAdobeImsTwoFactorAuth \
  Magento_TwoFactorAuth

php bin/magento setup:upgrade
php bin/magento cache:flush
```

### Deploy Static Content

```bash
cd /var/www/html

rm -rf pub/static/*
rm -rf var/view_preprocessed/*

php bin/magento setup:static-content:deploy en_US -f

# Verify
ls -lh pub/static/frontend/Magento/luma/en_US/css/styles-m.css
```

### Flush Cache & Restart Nginx

```bash
php bin/magento cache:flush
docker restart nginx
```

### Verify CSS is Accessible

```bash
curl -I http://<EC2_PUBLIC_IP>/static/frontend/Magento/luma/en_US/css/styles-m.css
# Expected: HTTP/1.1 200 OK
```

### Understanding the 302 Redirect

After installation you may see:

```
HTTP/1.1 302 Found
Location: http://test.dyna.com/
```

This is expected. Magento redirects all requests to the base URL configured during `setup:install`. You have two options to access the site:

---

### Option 1 (Recommended): Add DNS / Hosts Entry

**On the EC2 server:**
```bash
sudo nano /etc/hosts
# Add:
127.0.0.1  test.dyna.com
```

**On your local machine:**
- **Windows:** `C:\Windows\System32\drivers\etc\hosts`
- **Linux/Mac:** `sudo nano /etc/hosts`

```
<EC2_PUBLIC_IP>  test.dyna.com
```

Then browse to `http://test.dyna.com`

---

### Option 2: Change Magento Base URL

Inside the PHP container:

```bash
docker exec -it php bash
cd /var/www/html
```

Check the current URL:

```bash
bin/magento config:show web/unsecure/base_url
```

Change it to your EC2 public IP:

```bash
bin/magento setup:store-config:set \
  --base-url="http://<EC2_PUBLIC_IP>/"
```

Flush cache:

```bash
bin/magento cache:flush
```

Verify Nginx is serving Magento:

```bash
curl -I http://test.dyna.com
# or
curl -I http://<EC2_PUBLIC_IP>
```

---

### Access Your Magento Installation

| URL | Link |
|-----|------|
| Storefront | `http://test.dyna.com` |
| Admin Panel | `http://test.dyna.com/admin_sqjdvv1` |
| Username | `admin` |
| Password | `Admin@123456` |

> **Note:** The admin URI suffix (e.g. `/admin_sqjdvv1`) is randomly generated during installation — check your `setup:install` output for the exact path.

---

### Final Verification

Check all containers are running:

```bash
docker ps
```

Verify CSS is loading correctly:

```bash
curl -I http://<EC2_PUBLIC_IP>/static/frontend/Magento/luma/en_US/css/styles-m.css
# Expected: HTTP/1.1 200 OK
```

Then open your browser and navigate to `http://<EC2_PUBLIC_IP>` — your Magento storefront should be live.

<img src="https://raw.githubusercontent.com/DevMadhup/Magento/main/IMG_20260612_122338.jpg"
     alt="Magento Image"
     width="800"
     style="max-width:100%; height:auto;">

---

<img src="https://raw.githubusercontent.com/DevMadhup/Magento/main/IMG_20260612_122419.jpg"
     alt="Magento Image"
     style="max-width:100%; height:auto;">

---

*Document generated from AWS Magento DevOps deployment session.*

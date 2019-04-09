### Setup of https://btc-explorer.com on Ubuntu 16.04

    apt update
    apt upgrade
    apt install git python-software-properties software-properties-common nginx gcc g++ make
    curl -o- https://raw.githubusercontent.com/creationix/nvm/v0.33.11/install.sh | bash
    nvm install 10.14.1 ## LTS release of NodeJS as of 2018-11-29, via https://nodejs.org/en/
    npm install pm2 --global
    add-apt-repository ppa:certbot/certbot
    apt update
    apt upgrade
    apt install python-certbot-nginx
    
Copy content from [./btc-explorer.com.conf](./btc-explorer.com.conf) into `/etc/nginx/sites-available/btc-explorer.com.conf`

    certbot --nginx -d btc-explorer.com
    cd /etc/ssl/certs
    openssl dhparam -out dhparam.pem 4096
    cd /home/bitcoin
    git clone https://github.com/janoside/btc-rpc-explorer.git
    cd /home/bitcoin/btc-rpc-explorer
    npm install
    npm run build
    pm2 start bin/www --name "btc-rpc-explorer"

### Syncing databases with the blockchain

transpuller.php (located in scripts/) is used for updating the local databases. This script must be called from the explorers root directory.

    Usage: php scripts/transpuller.php

    notes:
    * You first need to install mysql [https://www.digitalocean.com/community/tutorials/how-to-install-mysql-on-ubuntu-16-04].
    * Create database schema but running scripts/schema.sql
    * Edit scripts/transpuller.php, make sure it can connect to your local node and local MySQL

*It is recommended to have this script launched via a cronjob at 3+ min intervals.*

**crontab**

*Example crontab; update index every 3 minutes*

    */3 * * * * cd /path/to/explorer && /usr/bin/php scripts/transpuller.php > /dev/null 2>&1

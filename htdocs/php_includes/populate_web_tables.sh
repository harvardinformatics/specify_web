#!/bin/bash

echo "Starting populate_web_tables at $(date)"
mysql  --defaults-extra-file=/root/.my.cnf -e 'stop slave' specify
/usr/bin/php /var/www/phpincludes/specify_web/populate_web_tables.php
mysql  --defaults-extra-file=/root/.my.cnf -e 'start slave' specify
echo "Finished populate_web_tables at $(date)"

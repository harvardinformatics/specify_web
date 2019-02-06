FROM harvardinformatics/wheezy-php55

RUN a2enmod php5 && \
    sed -i -e 's?ErrorLog.*?ErrorLog /dev/stderr?' /etc/apache2/apache2.conf && \
    sed -i -e 's?ErrorLog.*?ErrorLog /dev/stderr?' /etc/apache2/sites-enabled/000-default && \
    printf "\nAddHandler php5-script .html\n" >> /etc/apache2/sites-enabled/000-default && \
    printf "\nRedirectMatch 302  ^/$ /databases\n" >> /etc/apache2/sites-enabled/000-default && \
    printf "display_error = stderr\nerror_log = /dev/stderr\n" > /etc/php5/apache2/conf.d/20-logging.ini && \
    sed -i -e 's?;include_path = ".:/usr/share/php"?include_path = ".:/var/php/includes:/var/php/includes/specify_web:/usr/share/php"?' /etc/php5/apache2/php.ini

EXPOSE 80

ADD ./htdocs/htdocs /var/www

CMD ["apachectl", "-DFOREGROUND"]

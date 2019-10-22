## Simple File Uploader
Simple File Uploader is a script, mainly for sharing random files with people using [ShareX](https://github.com/ShareX/ShareX). It was mainly developed by [@Rouji](https://github.com/Rouji/single_php_filehost).

Puts a file sent via POST into a configured directory with a randomised filename but preserving the original filename and file extension, and returns a link to it.
Actually serving the file to people is left to nginx to figure out.

### Installation
In this installation I assume that a working lemp server is already running (but MySQL/MariaDB is actually not needed).  
Firstly create a directory like ```/var/www/share``` and unpack all PHP scripts into it. After that, change the owner to www-data respectively nginx.  
**Dont forget to add read and write permissions to the data/ folder!**

Install ```php7.2-sqlite3``` and restart php-fpm. Before continuing setup your nginx config.

Create a new [Github OAuth Application](https://github.com/settings/applications/new) and set the **Authorization callback URL** to ```https://share.example.com/``` if you want to use a subdomain else ```https://example.com/share/```.  
In the *config.php* set ```$ALLOW_REGISTER = true;```, save and press the login button on your site. After a successfull login open *config.php* again and undo the change by setting ```$ALLOW_REGISTER = false;```.

Perfect you are ready.

### NGINX Config
If you want to use this script via a subdomain like *share.example.com* you should use the following server configuration. Dont forget to use HTTPS, as you have a login on the website!

    server {
        listen 80 default_server;
        listen [::]:80 default_server;
        server_name share.example.com;
        return 302 https://$host$request_uri;
    }

    server {
        listen 443 ssl http2;
        listen [::]:443 ssl http2;
    
        root /var/www/share/;
        index index.php index.html index.htm;
        server_name share.example.com;
    
        ssl_certificate /etc/letsencrypt/live/example.com/fullchain.pem;
        ssl_certificate_key /etc/letsencrypt/live/example.com/privkey.pem;
    
        add_header Strict-Transport-Security "max-age=31536000;" always;
    
        location / {
            location ~ /data.db {
                    deny all;
            }
            if (-f $request_filename) {
                break;
            }
            rewrite ^/$ /index.php last;
            rewrite ^/(.*) /index.php?file=$1;
        }
    
        location ~ \.php$ {
                try_files $uri =404;
                fastcgi_pass unix:/var/run/php/php7.2-fpm.sock;
                fastcgi_index index.php;
                fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
                include fastcgi_params;
        }
    }

If you are using the script in a subdirectory like *example.com/share/* use the following location directive for your server.

    location /share/ {
        location ~ /data.db {
    
            deny all;
        }
        if (-f $request_filename) {
    
            break;
        }
        rewrite ^/share/$ /share/index.php last;
        rewrite ^/share/(.*) /share/index.php?file=$1;
    }

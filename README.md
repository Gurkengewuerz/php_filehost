## Simple File Uploader

### NGINX Config
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

Fork from https://github.com/Rouji/single_php_filehost
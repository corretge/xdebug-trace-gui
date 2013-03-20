# XDebug Trace GUI

Original code from: http://www.rdlt.com/xdebug-trace-file-parser.html

Detects all the traces in your xdebug trace directory and offers to look at them in a nice way.

## Installing XDebug

- Install it typing `pecl install xdebug`
- `chown www-data /var/log/php`
- Put `xdebug.ini` in /etc/php5/conf.d 
- `service apache2 restart`

## Apache Configuration

If the `ini_set` doesn't run, add this to your Apache VirtualHost file:

    <Directory /srv/www/lab/xdebug-trace-gui>
        Order allow,deny
        allow from 10.10.10
        deny from all
        php_value 'xdebug.auto_trace' 'Off'
    </Directory>

Look at the file `xdebug.httpd.conf` for an Apache VirtualHost sample configuration.
 
Change the directory and the IP range to match your setup.

## Trace GUI usage

There are 3 main files:

#### `noutrace.php` 

Xdebug trace gui with pagination, 1024 sentences in a page. For big traces. Memory and time consumition calculed by difference with previous instruction.

#### `trace.php`

Old style trace gui all one page, with summaries.

#### `graph.php`

Xdebug trace graph. Statistics memory consumition MB per centesims. You need the parent folder of <a href="https://github.com/corretge/JpGraph" target="_blank">jpGraph</a> in the include_path.

The code was written pretty quickly and isn't great. Next time it'll be better `;-)`.

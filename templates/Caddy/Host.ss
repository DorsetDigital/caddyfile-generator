$HostName<% if not $EnableHTTPS %>:80<% end_if %> {
<% if $NeedsTLSConfig%>
    <% include Caddy\TLS %>
<% end_if %>
    root * $CaddyRoot
<% if $EnablePHP %>
    php_fastcgi $PHPCGIURI {
        root $PHPRoot
    }
<% end_if %>
    encode gzip
    file_server
}

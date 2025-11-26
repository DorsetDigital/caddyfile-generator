$HostName<% if not $EnableHTTPS %>:80<% end_if %> {
<% if $TemporaryNeedsTLSConfig %><% include Caddy\TLS %>
<% end_if %>
header x-hosting "BBP Advanced Hosting"
root * $CurrentCaddyRoot
<% if $EnablePHP %>
    php_fastcgi $PHPCGIURI {
    root $CurrentPHPRoot
    env SS_BASE_URL $BaseURL
    }
<% end_if %>
encode gzip
file_server
}

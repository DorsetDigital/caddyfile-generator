$HostName<% if not $EnableHTTPS %>:80<% end_if %> {
    log access
<% if $TemporaryNeedsTLSConfig %><% include Caddy\TLS %>
<% end_if %>
header x-hosting "BBP Advanced Hosting"
root * $CurrentCaddyRoot
<% if $hasCustomHoldingPage %>rewrite * /index.html
<% end_if %><% if $EnablePHP && not $hasCustomHoldingPage %>
    php_fastcgi $PHPCGIURI {
    root $CurrentPHPRoot
    env SS_BASE_URL $BaseURL
    }
<% end_if %>
encode gzip
file_server
}

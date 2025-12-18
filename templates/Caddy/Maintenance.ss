$HostName<% if not $EnableHTTPS %>:80<% end_if %> {
<% if $TemporaryNeedsTLSConfig %><% include Caddy\TLS %>
<% end_if %>
    header x-hosting "BBP Advanced Hosting"
    header Retry-After "3600"
    encode gzip

    error 503

    handle_errors {
        root * $CurrentCaddyRoot
        rewrite * /index.html
        file_server
    }
}

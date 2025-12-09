$CurrentHostName<% if not $EnableHTTPS %>:80<% end_if %> {
<% if $NeedsTLSConfig %><% include Caddy\TLS %>
<% end_if %>
<% if $WAFEnabled %><% include Caddy\WAF %>
<% end_if %>
<% if $AuthCredentialsID > 0 %><% include Caddy\BasicAuth %>
<% end_if %>
<% if $RedirectRules %><% include Caddy\Redirects %>
<% end_if %>
    header x-hosting "BBP Advanced Hosting"
    root * $CaddyRoot
<% if $EnablePHP %>
    php_fastcgi $PHPCGIURI {
        root $PHPRoot
        env SS_BASE_URL $BaseURL
    }
<% end_if %>
    encode gzip
    file_server
}

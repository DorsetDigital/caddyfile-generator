(cache_static) {
    @fonts {
        path *.woff2 *.woff *.ttf *.otf
    }
    @assets {
        path *.css *.js
    }
    @images {
        path *.png *.jpg *.jpeg *.gif *.svg *.webp *.avif *.ico
    }

    header @fonts Cache-Control "public, max-age=31536000, immutable"
    header @assets Cache-Control "public, max-age=31536000, immutable"
    header @images Cache-Control "public, max-age=31536000, immutable"
}

(block_wordpress) {
    @wordpress path /wp-admin /wp-admin/* /wp-login.php /wp-login.php/* /wp-content/* /wp-includes/* /xmlrpc.php
    respond @wordpress 404
}
{
    log default {
        exclude http.log.access.access
    }

    log access {
        include http.log.access.access
        output file /var/log/caddy/access.log {
            roll_size 100MiB
            roll_keep 3
            roll_keep_for 24h
        }
        format json
    }

<% if $EnableWAF %>
    order coraza_waf first
<% end_if %>
servers {
    protocols h1 h2
}
<% if $RedisHost %>
    storage redis <% if $RedisCluster %>cluster<% end_if %> {
        host           $RedisHost
        port           $RedisPort
        username       "$RedisUser"
        password       "$RedisPassword"
        db             0
        timeout        5
        key_prefix     "$RedisKeyPrefix"
        tls_enabled    <% if $RedisTLS %>true<% else %>false<% end_if %>
        tls_insecure   true
    }
<% end_if %>
}

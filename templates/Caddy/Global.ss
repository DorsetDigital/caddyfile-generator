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
{
<% if $EnableWAF %>
    order coraza_waf first
<% end_if %>
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

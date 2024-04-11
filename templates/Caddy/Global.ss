{
<% if $RedisHost %>
    storage redis {
        host           $RedisHost
        port           $RedisPort
        username       "$RedisUser"
        password       "$RedisPassword"
        db             0
        timeout        5
        key_prefix     "$RedisKeyPrefix"
        tls_enabled    false
        tls_insecure   true
    }
<% end_if %>
}


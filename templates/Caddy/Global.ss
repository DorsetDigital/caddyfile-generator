{
<% if $EnableWAF %>
    order coraza_waf first
<% end_if %>
<% if $RedisHost %>
    storage redis {
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

:80 {
    respond "OK"
}


$Source<% if not $EnableHTTPS %>:80<% end_if %> {
<% if $NeedsTLSConfig%>
<% include Caddy\TLS %>
<% end_if %>
    redir $Target{uri}
}

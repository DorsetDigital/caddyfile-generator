$CurrentHostName<% if not $EnableHTTPS %>:80<% end_if %> {
<% if $NeedsTLSConfig%>
<% include Caddy\TLS %>
<% end_if %>
<% if $WAFEnabled %><% include Caddy\WAF %>
<% end_if %>
    redir $RedirectTo{uri}
}

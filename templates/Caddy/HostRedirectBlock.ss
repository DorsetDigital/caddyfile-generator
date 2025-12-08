$Source<% if not $EnableHTTPS %>:80<% end_if %> {
<% if $NeedsTLSConfig%>
<% include Caddy\TLS %>
<% end_if %>
    redir $Target<% if $RedirectPaths %>{uri}<% end_if %><% if $RedirectPermanent %> permanent<% end_if %>
}

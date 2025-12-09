$CurrentHostName<% if not $EnableHTTPS %>:80<% end_if %> {
<% if $NeedsTLSConfig%>
<% include Caddy\TLS %>
<% end_if %>
<% if $WAFEnabled %><% include Caddy\WAF %>
<% end_if %>
<% if $RedirectRules %><% include Caddy\Redirects %>
<% end_if %>
    redir $RedirectTo<% if $RedirectPaths %>{uri}<% end_if %><% if $RedirectPermanent %> permanent<% end_if %>
}

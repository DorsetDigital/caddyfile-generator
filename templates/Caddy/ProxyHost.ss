$CurrentHostName<% if not $EnableHTTPS %>:80<% end_if %> {
<% if $NeedsTLSConfig%>
<% include Caddy\TLS %>
<% end_if %>
<% if $AuthCredentialsID > 0 %><% include Caddy\BasicAuth %>
<% end_if %>
    header x-hosting "BBP Advanced Hosting"
<% if $WAFEnabled %><% include Caddy\WAF %>
<% end_if %>
<% if $RedirectRules %><% include Caddy\Redirects %>
<% end_if %>
    reverse_proxy $ProxyHost <% if $UpstreamHostHeader || $IsHTTPSUpstream || $RemoveForwardedHeader %>{
<% if $RemoveForwardedHeader %>     header_up -X-Forwarded-Host <% end_if %>
<% if $UpstreamHostHeader %>        header_up Host $UpstreamHostHeader<% end_if %>
<% if $IsHTTPSUpstream %>       transport http {
         tls
         tls_insecure_skip_verify
        }<% end_if %>
    }
    <% end_if %>
}

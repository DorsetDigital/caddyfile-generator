<% loop $RedirectRules %>
    redir $Path $NewLocation <% if $Permanent %>permanent<% end_if %>
<% end_loop %>
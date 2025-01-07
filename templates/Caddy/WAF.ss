    coraza_waf {
        load_owasp_crs
        directives `
           <% if $CorazaConfigFile %>Include $CorazaConfigFile<% end_if %>
           <% if $CRSConfigFile %>Include $CRSConfigFile<% end_if %>
           <% if $SiteConfig.IncludeOWASPRules %>Include @owasp_crs/*.conf<% end_if %>
           SecRuleEngine On
        `
    }
    header x-waf "Enabled"
coraza_waf {
load_owasp_crs
directives `
SecRuleEngine On
<% if $CorazaConfigFile %>Include $CorazaConfigFile<% end_if %>
<% if $CRSConfigFile %>Include $CRSConfigFile<% end_if %>
<% if $SiteConfig.IncludeOWASPRules %>
Include @owasp_crs/*.conf
<% if $CRSOverridesConfigFile %>Include $CRSOverridesConfigFile<% end_if %>
<% end_if %>
`
}
header x-waf "Enabled"

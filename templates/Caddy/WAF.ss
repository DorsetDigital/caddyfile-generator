coraza_waf {
load_owasp_crs
directives `
SecRuleEngine On
<% if $CorazaConfigFile %>Include $CorazaConfigFile<% end_if %>
<% if $CRSConfigFile %>Include $CRSConfigFile<% end_if %>
<% if $SiteConfig.IncludeOWASPRules %>Include @owasp_crs/*.conf<% end_if %>
<% if $SiteConfig.IncludeOWASPRules %>SecRuleUpdateTargetById 942550 "!REQUEST_COOKIES:CookieScriptConsent"<% end_if %>
`
}
header x-waf "Enabled"

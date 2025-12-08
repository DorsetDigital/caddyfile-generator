<% with $AuthCredentials %>
basic_auth {
    $Username $HashedPassword
}
<% end_with %>
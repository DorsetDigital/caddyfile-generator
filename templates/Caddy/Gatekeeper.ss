    handle_path /.gatekeeper/* {
        reverse_proxy $GatekeeperAuthUpstream
    }

    @gatekeeper_protected path $GatekeeperPathMatcher
    forward_auth @gatekeeper_protected $GatekeeperAuthUpstream {
        uri /auth/check
    }

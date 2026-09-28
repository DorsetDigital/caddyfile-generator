rate_limit {
    zone $RateLimitZoneName {
        match {
            not path *.woff2 *.woff *.ttf *.otf *.css *.js *.png *.jpg *.jpeg *.gif *.svg *.webp *.avif *.ico
        }
        key {remote_host}
        events $RateLimitEffectiveEvents
        window $RateLimitWindowDuration
    }
}

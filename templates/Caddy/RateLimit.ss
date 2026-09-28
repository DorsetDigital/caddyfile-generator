rate_limit {
    zone $RateLimitZoneName {
        match {
            not file
        }
        key {remote_host}
        events $RateLimitEffectiveEvents
        window $RateLimitWindowDuration
    }
}

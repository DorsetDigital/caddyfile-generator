rate_limit {
    zone $RateLimitZoneName {
        match {
            not path *.css *.js *.mjs *.cjs *.ts *.tsx *.map *.png *.jpg *.jpeg *.gif *.svg *.webp *.avif *.ico *.bmp *.tif *.tiff *.woff *.woff2 *.ttf *.otf *.webmanifest *.json *.xml *.txt *.mp4 *.webm *.mp3 *.ogg *.wav *.pdf
        }
        key {remote_host}
        events $RateLimitEffectiveEvents
        window $RateLimitWindowDuration
    }
}

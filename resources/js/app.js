/*
| Student player behaviour
|--------------------------------------------------------------------------
| Alpine components consumed by the per-type lesson players
| (resources/views/student/lessons/players/*).
|
| Alpine ships inside Livewire, so it is not an npm dependency here. It
| dispatches `alpine:init` before starting, which is the only safe point at
| which to register components — registering earlier throws, registering later
| silently does nothing.
|
| ═════════════════════════════════════════════════════════════════════════
| WHY THE PLAYER ASKS THE SERVER FOR EVERY URL.
|
| Neither component is ever handed a storage path. Each fetches a short-lived,
| authorised URL from `media.url`, which runs MediaFilePolicy before issuing
| anything. That is what stops a permanent link to protected content sitting
| in the page source of every lesson (architecture.md §16.2).
|
| The URL expires in five minutes by design (NFR-SEC-22). A forty-minute
| lecture therefore outlives its own URL, so expiry is treated as an ordinary
| event with a visible recovery, not as an error.
| ═════════════════════════════════════════════════════════════════════════
*/

document.addEventListener('alpine:init', () => {
    /**
     * Video lesson: resume, throttled position reporting, expiry recovery.
     */
    Alpine.data('lessonVideo', ({ mediaId, resumeAt, urlEndpoint }) => ({
        failed: false,
        resumeAt: resumeAt || 0,
        // Guards the resume seek so it happens once. `loadedmetadata` fires
        // again on every reload, and seeking back on each one would drag a
        // student who had scrubbed forward straight back to where they were.
        resumed: false,

        async load() {
            if (!urlEndpoint) {
                return;
            }

            this.failed = false;

            try {
                const response = await fetch(urlEndpoint, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    // 403 here is correct behaviour, not a bug: an enrollment
                    // can be revoked between opening the page and pressing
                    // play, and the server is the authority on that.
                    this.failed = true;

                    return;
                }

                const { url } = await response.json();

                this.$refs.video.src = url;
            } catch {
                this.failed = true;
            }
        },

        seekToResume() {
            if (this.resumed || this.resumeAt <= 0) {
                return;
            }

            const video = this.$refs.video;

            // Never seek to the very end. A student who finished a lesson
            // would otherwise reopen it to a frozen final frame with nothing
            // left to play (AC-18).
            if (Number.isFinite(video.duration) && this.resumeAt >= video.duration - 2) {
                this.resumed = true;

                return;
            }

            video.currentTime = this.resumeAt;
            this.resumed = true;
        },

        /**
         * Report the playback head to the server.
         *
         * Throttled in the markup to once every ten seconds, plus on pause and
         * on end. A `timeupdate` fires roughly four times a second — reporting
         * each one would be hundreds of writes per lesson for a resume point
         * accurate to the same ten seconds either way.
         */
        report(completed = false) {
            const video = this.$refs.video;

            if (!video || !Number.isFinite(video.currentTime)) {
                return;
            }

            this.$wire?.recordProgress(Math.floor(video.currentTime), completed);
        },
    }));

    /**
     * Document lesson: fetch an authorised URL into the viewer frame.
     */
    Alpine.data('lessonDocument', ({ urlEndpoint }) => ({
        failed: false,

        async load() {
            if (!urlEndpoint) {
                return;
            }

            this.failed = false;

            try {
                const response = await fetch(urlEndpoint, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (!response.ok) {
                    this.failed = true;

                    return;
                }

                const { url } = await response.json();

                this.$refs.frame.src = url;
            } catch {
                this.failed = true;
            }
        },
    }));
});

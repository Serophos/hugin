# Heartbeat and renderer regression analysis

This note records the evidence used for the `1.1-dev` heartbeat and black-slide
fix. It is intentionally focused on reproducible behavior and the lifecycle
invariants that future changes must preserve.

## Baseline and rollback

The investigation started at `50c1d31d799644a6b33bfa31dd0e9cc5633647f7`.
The worktree also contained uncommitted failed-attempt edits in only:

- `public/assets/css/display.css`
- `public/assets/js/display-heartbeat.js`
- `tests/display_heartbeat_test.mjs`

Those three uncommitted edits were restored before reproduction. A detached,
clean worktree at the original HEAD was kept at
`/tmp/hugin-original-50c1d31` during the investigation.

The committed attempts were then inverted narrowly:

- `08b76bb`: removed the late session-close workaround and restored the
  180-second monitoring threshold and 90-second heartbeat cadence.
- `c57d29e`: removed its media-failure/transition behavior while retaining its
  playlist-selection fix and bounded state-request retry behavior.
- `50c1d31`: removed eager first-image `src`, the discarded startup-readiness
  result, and the unproven service-worker MIME/cache workaround.

## Heartbeat reproduction

1. Start a PHP worker with the application session name and a known session ID.
2. Call `session_start()` and hold the worker before `session_write_close()`.
3. Send `POST /display/{slug}/heartbeat` with the same session cookie.
4. Observe that the request enters `app/bootstrap.php` and blocks in
   `session_start()` before `public/index.php` can run its late close.
5. Release the holder.

The request remained blocked for 10.84 seconds and completed immediately after
the holder released the session lock. The synchronized regression is now
executable as:

```sh
php tests/session_policy_test.php
```

The first meaningful client-side divergence from working `main` is `d5c9e65`,
which replaced one fixed interval with request-completion-chained scheduling.
That made server stalls alter future cadence and allowed a stalled request to
own the loop. It was an amplifier, not the source of the PHP lock.

## Eager image reproduction

1. Open an image-slide preview in a fresh browser target.
2. Intercept and abort the first request for the image exactly once.
3. Let all later requests proceed.
4. Inspect the image and request log.

At `50c1d31`, the template assigned the first image's `src` before
`slideshow.js` installed lifecycle handlers. The browser made one failed
request; the element remained `complete === true` with `naturalWidth === 0`;
no retry/fallback handler observed the failure; and the active slide was black.
Working `main` leaves the URL in `data-src` until handlers own the attempt.

## Failed video reproduction

1. Fail a video while it is prepared under the startup overlay.
2. Keep the failed element in the playlist for more than 30 seconds.
3. Continue cycling the renderer.

The startup path discarded readiness, while the transition eligibility logic
treated elapsed quarantine time as if it proved readiness. The same failed
video became eligible after roughly 40 seconds, was activated with no playable
data, and remained black for its configured duration.

## Iframe reproduction

1. On an HTTPS display, include both browser-policy-allowed and blocked HTTP
   website slides.
2. Attach `load` and `error` probes before assigning each frame URL.
3. Cycle the complete playlist and capture each active website slide.

Six policy-blocked frames emitted neither `load` nor `error` and their captured
content regions were uniformly black. A different HTTP frame emitted `load`
and rendered correctly. Therefore HTTP URLs cannot be rejected categorically:
activation must require a matching navigation `load`, with a bounded timeout
for browsers that emit no terminal event.

An iframe reset also requires real-browser verification. Chromium did not emit
`load` for fragment-only `about:blank#...` changes, while distinct
`about:blank?...` navigations did. Fake-element tests alone did not reveal that
difference.

## Required invariants

- Production heartbeat POSTs do not acquire a PHP session; preview and admin
  requests retain session/authentication behavior.
- Exactly one page-global heartbeat owner uses a completion-independent fixed
  cadence and at most one logical transport.
- Failed media stays failed until an explicit new attempt succeeds.
- Event handlers and deadlines exist before a media URL is assigned.
- A slide is activated only after all required media is verified ready.
- A verified current slide remains visible until a verified replacement is
  committed; otherwise the nonblack playback-status surface is active.
- Iframe readiness is navigation-specific and stale blank/reset events cannot
  satisfy a target attempt.
- Core display-runtime content changes alter both versioned runtime URLs and
  the stable display-state signature; dynamic plugin asset lists remain outside
  that signature.

## Final runtime verification

An initial kiosk lifecycle burst exposed one additional concurrency defect:
persisted `pagehide` queued a beacon immediately before persisted `pageshow`
sent the restore heartbeat. The two network requests overlapped. A BFCache
pause now only tears down the active transport/cadence; only a non-persisted,
intentional unload sends the final beacon.

After that correction, the real kiosk ran for 1,120 seconds:

- 12 fixed 90-second cadences completed, from 103.7 through 1093.7 seconds.
- A controlled offline heartbeat failed once and the online event recovered
  immediately; maximum observed heartbeat concurrency remained one.
- More than seven complete playlist cycles produced zero uniformly black
  captures.
- The six browser-blocked HTTP frames repeatedly reached explicit timeout
  failures and were skipped. The policy-allowed HTTP wallboard repeatedly
  reached `ready` and rendered.
- Core runtime URLs all carried the same content revision, and the heartbeat
  controller remained running with no residual failure at the end.

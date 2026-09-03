# Realtime Operations Runbook (R1.7)

Self-hosted Laravel Reverb + Echo. **REST is always authoritative.** Realtime is advisory signal delivery for notifications, dining/table sync, inventory hints, and presence.

Never use `php artisan serve` for this application.

## Required environment

| Variable | Purpose |
| --- | --- |
| `BROADCAST_CONNECTION` | `reverb` in environments that run Reverb; `log`/`null` when Reverb is off (API still works) |
| `COFFEE_REALTIME_ENABLED` | Optional override; defaults true when broadcast is `reverb` |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` | Reverb app credentials |
| `REVERB_HOST` / `REVERB_PORT` / `REVERB_SCHEME` | Client-facing host/port (`http`/`https`) |
| `REVERB_SERVER_HOST` / `REVERB_SERVER_PORT` | Process bind address (often `0.0.0.0:8080`) |
| `VITE_REVERB_*` | PWA build-time Echo config (must match REVERB_*) |
| `QUEUE_CONNECTION` | App listeners are sync today; keep `database`/`redis` ready for future workers |
| `CACHE_STORE` | Presence TTL heartbeats use cache (unique users, not tabs) |

Never commit secrets. Rotate `REVERB_APP_SECRET` if leaked.

## Health verification

```bash
php artisan coffee:realtime-health
php artisan coffee:realtime-health --json
php artisan coffee:realtime-health --probe
php artisan coffee:realtime-health --metrics
php artisan channel:list
```

Checks: app boot, broadcast driver, coffee realtime config, Reverb credentials when active, `/broadcasting/auth` route, advisory presence counts. `--probe` dispatches a tiny private-channel probe (delivery still needs Reverb). `--metrics` prints **computed** delay samples from lifecycle timestamps (not stored durations).

Client diagnostics (no secrets/payloads):

- Blade: `window.__COFFEE_REALTIME_DIAGNOSTICS__`
- PWA: `window.__COFFEE_REALTIME_DIAGNOSTICS__`

Fields: connection state, reconnect attempts, last connected/disconnected, last event kind/time, last REST reconcile, presence heartbeat time.

## Process model

Recommended processes (Supervisor/systemd examples):

1. PHP-FPM / Octane app
2. `php artisan reverb:start` (or `reverb:restart` after deploy)
3. Optional queue worker only if you introduce queued jobs later
4. Scheduler if already used for other domains

Example Supervisor program:

```ini
[program:coffee-reverb]
command=php /var/www/coffee/artisan reverb:start --host=0.0.0.0 --port=8080
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=/var/log/coffee-reverb.log
stopwaitsecs=10
```

## Reverse proxy / WebSocket

- Terminate TLS at the proxy; proxy `/app` (Echo/Reverb path) with WebSocket upgrade.
- Production clients must use `REVERB_SCHEME=https` / WSS — mixed content blocks browsers.
- Open firewall only for the public WSS port (or proxy port). Do not expose MySQL/Redis.
- `broadcasting/auth` stays on the app origin (same-site cookies / Sanctum).

## Broadcast auth

- Route: `POST /broadcasting/auth` (web + `AuthenticateBroadcastRequest`)
- Channels: `user.{id}`, `role.*`, `ops` (presence), `dining-session.{id}`, `table.{id}`, `realtime.probe`
- Customers never join staff role channels. Walk-in dining sessions have no customer subscription.

## Deployment sequence

1. Maintenance window if schema-breaking (optional for pure realtime config)
2. Deploy code + Composer/npm dependencies
3. `php artisan migrate --force`
4. `php artisan config:cache` / `route:cache` / `view:cache` as usual
5. Build frontends (`npm run build`, `customer-pwa` build) and publish assets
6. Restart PHP-FPM/Octane
7. Restart queue workers if any
8. `php artisan reverb:restart` (or stop/start Supervisor program)
9. `php artisan coffee:realtime-health --probe`
10. Smoke matrix: `docs/realtime-smoke-test.md`

**Reverb restart must not lose business state** — orders, dining sessions, notifications, and payments live in the database. Clients reconnect and REST-reconcile.

## Rollback

1. Redeploy previous release artifacts
2. `php artisan migrate:rollback` only if a migration shipped and is safe
3. Restart PHP + Reverb
4. Set `BROADCAST_CONNECTION=log` temporarily if Reverb is unhealthy — panels keep working without live sockets

## Common failures (diagnostic order)

### REST works, realtime does not

1. `php artisan coffee:realtime-health --json`
2. Is `BROADCAST_CONNECTION=reverb` and Reverb process up?
3. Browser Network → WS to Reverb host/port/scheme
4. Console Echo auth → `/broadcasting/auth` status
5. Confirm `VITE_REVERB_*` matches server for PWA builds

### Client stuck reconnecting

1. Read `__COFFEE_REALTIME_DIAGNOSTICS__`
2. Proxy WebSocket upgrade / idle timeouts
3. Reverb logs for dropped connections
4. Confirm REST still succeeds (business OK)

### 401/403 on broadcast auth

1. Session/CSRF/Sanctum cookie present?
2. Wrong guard (customer `web` vs staff `admin`)
3. Channel authorization in `routes/channels.php` (wrong role / not session owner)

### WebSocket handshake / WSS / mixed content

1. Page HTTPS ⇒ Reverb must be WSS (`REVERB_SCHEME=https`)
2. Proxy `Upgrade` / `Connection` headers
3. Host/SNI mismatch vs `REVERB_HOST`

### Reverb process stopped

1. Supervisor status / `reverb:start`
2. Port bind conflict
3. Health command still OK for app; clients show offline; REST continues

### Duplicate notifications / sounds / reminders

1. Confirm `bootstrap/app.php` has `withEvents(discover: false)`
2. `php artisan event:list` — each Wire* listener once
3. Client event-id dedupe + multi-tab **leader election is per browser profile**, not cross-device
4. Server idempotency_key on operational notifications

### Notification received but no sound

1. Is this tab the leader? Only leader plays sound/reminder
2. Browser autoplay policy — require a prior user gesture
3. Customer strong-alert types only (no 30s customer reminders)

### Reminders do not stop

1. Is notification still `action_required` and unresolved?
2. Dismiss toast ≠ resolve
3. Dining `ready_to_serve` resolves on Served / Completed / Cancelled / Rejected (L1.1 Mark Served)
4. After reconnect, REST sync must drop resolved items — check sync errors in diagnostics

### Presence incorrect

1. Heartbeat TTL 45s; interval ~20s
2. Unique user ids — duplicate tabs do not inflate counts
3. Unexpected close expires via TTL; logout should POST leave
4. Presence is advisory — never blocks BAR/KITCHEN/Dining

### Waiter session stale

1. Dining `.dining.ops` received? Check diagnostics `last_event_kind`
2. Soft REST reconcile on signal/reconnect/visibility
3. Capabilities from REST prevent illegal duplicate bill/close

### Customer misses Ready

1. Customer must be authenticated owner on `private-user.{id}`
2. Walk-in has no customer channel
3. After reconnect, Orders/Detail/Dining pages REST-reconcile
4. Background Web Push is **not** implemented (deferred past R1.7 hardening)

## Served / Delivered-to-table (L1.1)

**Current:** Waiter/Operator/Admin mark a dining **round** Served after all required preparation tickets are Ready (`orders.served_at` / `served_by_user_id`). Preparation Ready ≠ Served; Served ≠ session/order Completed.

* Resolves `dining.ready_to_serve` (and related Waiter-coverage escalation) with `resolution_action=served`; 30s reminders stop immediately; history retained.
* Emits safe `.dining.ops` `round.served` via `DiningRealtimePublisher` (REST remains authority).
* Does not close the session, freeze the bill, imply payment, or block further rounds.
* Idempotent: duplicate Mark Served returns canonical served state without duplicate history/signals.

## Related docs

- `docs/realtime-smoke-test.md` — manual matrix
- `docs/architecture.md` / `docs/scope.md` — R1 channel model

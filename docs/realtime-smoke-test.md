# Realtime Smoke Test Matrix (R1.7)

Use with Reverb running (`php artisan reverb:start`) and `BROADCAST_CONNECTION=reverb`.  
REST must succeed even if you stop Reverb mid-test.

For each case: perform **trigger**, confirm **recipients/UI/sound**, then **disconnect/reconnect** and confirm recovery without full product reload where noted.

Legend: **Leader** = elected tab in the same browser profile (not across devices).

---

## A — Customer → Operator new order

| | |
| --- | --- |
| Trigger | Authenticated customer places takeaway/dine-in retail order |
| Recipients | Operator (+ Admin per wiring); customer gets `customer.*` status as applicable |
| UI | Operator bell/toast; orders list soft-updates or refresh on signal/reconnect |
| Sound/reminder | Leader staff sound if actionable; customer strong alert only for Ready/reject/cancel types |
| Resolution | Operator accepts / pays flow progresses; attention notifications resolve with workflow |
| Reconnect | Missed socket → notification REST sync + orders page reconcile |

## B — Operator → Barista/Chef preparation

| | |
| --- | --- |
| Trigger | Order accepted → BAR/KITCHEN tickets pending |
| Recipients | Barista and/or Chef; Operator if monitoring |
| UI | Station queue updates; soft-reload on reconnect for preparations pages |
| Sound/reminder | Pending ticket actionable + 30s reminders (leader tab) |
| Resolution | Ticket Accepted/Preparing/Ready transitions |
| Reconnect | Queue must show current tickets after reconnect |

## C — Stations → customer Ready

| | |
| --- | --- |
| Trigger | All required stations Ready / order Ready |
| Recipients | Customer owner on `private-user.{id}` |
| UI | Orders list/detail soft REST reconcile; toast |
| Sound/reminder | One-time strong alert+sound (no 30s customer reminder) |
| Resolution | Customer pickup / complete per fulfilment |
| Reconnect | Detail page shows Ready without manual hard refresh |

## D — Dining → Waiter → stations → Ready-to-Serve

| | |
| --- | --- |
| Trigger | Waiter/customer round → prep → all stations ready |
| Recipients | Waiter (`dining.ready_to_serve` + `.dining.ops`); customer if attached |
| UI | Waiter tables/session live reconcile; ready chip |
| Sound/reminder | Waiter actionable reminder until Served / Completed / Cancelled / Rejected |
| Resolution | Waiter **Mark Served** (L1.1) preferred; also complete/cancel/reject |
| Reconnect | Table card + session rounds/status recover via REST |

## E — Payment proof

| | |
| --- | --- |
| Trigger | Customer/waiter uploads UPI proof; Operator confirm/reject |
| Recipients | Operator review; customer on confirm/reject |
| UI | Session/order payment state via REST after signal |
| Sound/reminder | Operator proof-review reminders; customer strong on reject |
| Resolution | Confirm/reject; Waiter must not gain Operator powers via socket |
| Reconnect | Canonical payment_status from REST |

## F — Inventory / refill

| | |
| --- | --- |
| Trigger | Stock low/out; refill request/approve/complete |
| Recipients | Admin/Operator (+ Barista for out/refill per rules) |
| UI | Inventory/refill Blade soft-reload on `.inventory.ops` |
| Sound/reminder | Out + pending refill actionable |
| Resolution | Restock / complete refill resolves open items |
| Reconnect | Soft-reload inventory pages; notification sync |

## G — No-staff escalation

| | |
| --- | --- |
| Trigger | BAR pending + no Barista online; KITCHEN + no Chef; dining ready + no Waiter |
| Recipients | Operator/Admin escalation types |
| UI | Escalation in bell; workflow still proceeds |
| Sound/reminder | Actionable reminders until resolved |
| Resolution | Target role heartbeats online **or** underlying work resolves; deduped per lifecycle |
| Reconnect | Escalation state from REST; presence advisory only |

## H — Two tabs leader election

| | |
| --- | --- |
| Trigger | Same Waiter/Operator user, two tabs; fire actionable notification |
| Recipients | Both tabs update drawer/list |
| UI | Both show item; only **leader** toast/sound/reminder POST `/reminded` |
| Sound/reminder | Close leader tab → other becomes leader; reminders continue |
| Resolution | Unchanged server rules |
| Reconnect | Each tab REST syncs; no cross-device single leader |

## I — Reverb stop / restart

| | |
| --- | --- |
| Trigger | Stop Reverb during active order/dining; place REST actions; restart Reverb |
| Recipients | N/A while down |
| UI | Indicator offline; REST actions succeed; after restart reconnect |
| Sound/reminder | Pause while offline; resume after sync if still unresolved |
| Resolution | DB state unchanged by Reverb restart |
| Reconnect | `coffee:realtime-health --probe`; clients reconcile |

## J — Network offline / reconnect

| | |
| --- | --- |
| Trigger | Browser offline, then online; or hide tab >15s |
| Recipients | Same |
| UI | Coalesced notification sync + page-level dining/order reconcile |
| Sound/reminder | Resume only for still-actionable unresolved items |
| Resolution | Resolved-while-offline → reminder stops after sync |
| Reconnect | Diagnostics `last_reconcile_at` updates |

## K — Logout / login / session expiry

| | |
| --- | --- |
| Trigger | Logout; session expire; login other role |
| Recipients | Channels re-authorized for new user only |
| UI | PWA clears notification store on logout; Echo disconnects |
| Sound/reminder | Stops |
| Resolution | N/A |
| Reconnect | New session must not see previous user’s private channel data |

---

## Quick health before/after smoke

```bash
php artisan coffee:realtime-health --probe
```

Inspect `window.__COFFEE_REALTIME_DIAGNOSTICS__` on Blade/PWA if a step fails.

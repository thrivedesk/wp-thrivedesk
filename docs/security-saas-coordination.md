# Listener hardening that needs the SaaS to move first

Two findings on the `?listener=thrivedesk` endpoint are real, but neither can
ship from this repository alone. The plugin verifies a signature the SaaS
computes; changing what the plugin expects without changing what the SaaS sends
turns every real request into `401 Request unauthorized`. Both are written up
here rather than implemented.

The signers live in the SaaS at `app/apps/<Integration>/Services/*Service.php`
(EDD, WooCommerce, FluentCRM, Autonami, WPPostSync). The plugin side is
`ThriveDesk\Api::verify_token()` and `ThriveDesk\Api::SIGNED_PARAMS` in
`src/Api.php`.

Both changes share the same shape, so they can ride the same transition window.

---

## 1. Replay protection

### The gap

A signature is valid forever and is reusable without limit. Nothing in the
signed contract ties a request to a point in time or marks it as already spent.
Anything that observes one request — a proxy log, an access log, an error
report, a shared browser session, a misconfigured CDN that logs query strings —
can replay it verbatim for as long as the integration keeps its `api_token`.

That matters most for the mutating actions, which are all replayable today:

| Action | Effect of a replay |
| --- | --- |
| `woocommerce_order_status_update` | Re-applies a status transition, re-firing its emails and any `woocommerce_order_status_*` side effects. |
| `woocommerce_order_quantity_update` | Re-applies the quantity; harmless alone, but pairs with a captured larger quantity. |
| `add_item_on_woocommerce_order` | **Adds the line again.** Every replay is another line item and another charge. |
| `woocommerce_order_apply_coupon` | Re-applies the discount. |
| `woocommerce_subscription_cancel` | Re-cancels; idempotent, but re-fires cancellation mail. |
| `disconnect` | Disconnects the store again after an admin reconnects it. |

`add_item_on_woocommerce_order` is the sharp one: it is genuinely
non-idempotent, so N replays mean N lines.

### The plugin-side change

Add two params to the signed contract and enforce them in `verify_token()`:

- `timestamp` — Unix seconds, as sent by the SaaS. Reject when
  `abs( time() - (int) $timestamp )` exceeds a tolerance window. Five minutes
  is the usual choice; it has to absorb clock skew between the SaaS host and
  a WordPress host whose clock nobody maintains.
- `nonce` — a random, single-use value. Reject when it has been seen before,
  within the same window.

```php
private const SIGNED_PARAMS = [
    'listener',
    'plugin',
    'action',
    'timestamp',   // new
    'nonce',       // new
    // ... unchanged
];
```

Both must be *inside* the signed set, or an attacker just edits them.

Nonce storage: a transient keyed by the nonce
(`set_transient( 'td_nonce_' . $nonce, 1, $tolerance )`), checked with
`get_transient()` before the handler runs and written only after the signature
verifies — so an unauthenticated caller cannot fill the options table. The
transient's own TTL does the cleanup, and it can never need to outlive the
timestamp window, because a request older than the window is already rejected.

Order matters: check the signature first, then the timestamp, then the nonce.
Checking the nonce first would let an anonymous caller probe or exhaust nonce
storage.

### Why it cannot ship plugin-first

The SaaS does not send `timestamp` or `nonce` today. A plugin that requires them
rejects every real request. The SaaS also has to include them in its own HMAC
input, in the same key order the plugin hashes, or the digests differ.

## 2. HMAC-SHA1 to HMAC-SHA256

### The gap

`verify_token()` uses `hash_hmac( 'SHA1', ... )`. HMAC-SHA1 is not broken in the
way bare SHA-1 is — the collision attacks on SHA-1 do not translate into a
forgery against HMAC-SHA1, and there is no practical break today. So this is
hygiene and compliance, not an exploitable hole: SHA-1 is disallowed by
NIST SP 800-131A for new work, and a security reviewer or a customer's
procurement questionnaire will flag it on sight.

### The plugin-side change

One line:

```php
$expected = hash_hmac( 'sha256', wp_json_encode( $payload ), $api_token );
```

Everything else is unchanged: same payload, same key, same `hash_equals()`
comparison. The digest goes from 40 hex characters to 64, which is only a
problem for anything that stores or column-limits the signature.

`tests/includes/listener-helpers.php::td_test_sign_payload()` is the test-side
mirror of this method and has to change with it.

### Why it cannot ship plugin-first

The header carries no algorithm identifier. A plugin that computes SHA-256 and
compares against a SHA-1 signature never matches, so every store on the new
plugin 401s until the SaaS is deployed — and stores update on their own
schedule, so "deploy the SaaS at the same moment" is not available.

---

## Suggested transition

The plugin auto-updates on a schedule nobody controls, so at any moment there
are stores on the old plugin and stores on the new one. The SaaS therefore has
to be the tolerant side first, and the plugin has to be the tolerant side
during the overlap.

1. **SaaS, first release.** Start sending `timestamp` and `nonce` on every
   request and including them in the signed payload. Old plugins ignore unknown
   params — `verify_token()` already hashes only `SIGNED_PARAMS` — so they keep
   verifying exactly as before. Send *both* signatures: the existing
   `X-TD-Signature` (SHA-1) plus a new `X-TD-Signature-256`.

2. **Plugin, accept-both release.** Add `timestamp`/`nonce` to `SIGNED_PARAMS`
   and prefer `X-TD-Signature-256` when the header is present, falling back to
   the SHA-1 header when it is not. Enforce the timestamp window and the nonce
   only when both params are present, so a store still talking to an older SaaS
   deployment is not locked out. Log (do not reject) a request that arrives
   without them, so the tail of un-upgraded senders is measurable.

3. **Wait out the tail.** Hold here until telemetry shows effectively no
   requests arriving without `timestamp`/`nonce` or without the SHA-256 header.
   Base the wait on WordPress.org's active-version data for the plugin, not on a
   fixed number of weeks — a long tail of stores with auto-updates disabled is
   normal.

4. **Plugin, enforcing release.** Require `X-TD-Signature-256`, require
   `timestamp` and `nonce`, and drop the SHA-1 path. Bump the plugin's minimum
   supported SaaS behaviour in the changelog.

5. **SaaS, cleanup release.** Stop sending `X-TD-Signature`.

Steps 1 and 2 can land in either order as long as both are out before step 4.
Step 4 is the only breaking one, and it breaks only stores that skipped step 2
entirely — which is why it waits on the version data.

## Not covered here

Everything else in the audit was plugin-only and has already been fixed on this
branch: the `$_GET`/`$_REQUEST` signature bypass, hashing sanitized instead of
raw values, the reversed `hash_equals()` arguments, the pre-auth plugin
enumeration, and the WooCommerce mutation bugs.

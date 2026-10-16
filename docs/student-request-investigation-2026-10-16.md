# Student portal request investigation — 16 October 2026

## Conclusion

The supplied request log strongly suggests automated batches of subject-page reads immediately after login. It does not show continuous polling at a fixed two-second interval. A wrapper app, injected script, or scraper obtaining a student's session and then collecting their subject pages is the leading explanation. The logs cannot identify the app, establish that a VPN/proxy is involved, identify individual accounts for the subject requests, or prove that the requests cover distinct subjects.

This investigation read the two supplied logs and current workspace PHP/JavaScript. It did not query the production database, inspect installed mobile apps, verify the deployed source revision, or change application behavior.

## Scope and measured evidence

`jntuaceastudents/logs/request_log.log` contains 2,072 records from **17:40:26 to 20:47:53** on 16 October 2026. These are the timestamps as written in the logs; deployment timezone was not independently verified. This file is a partial-day sample.

| Measurement | Result |
|---|---:|
| Requests to `/studentsubatt.php` | 1,513 |
| Subject-page POST requests | 1,512 |
| Subject-page GET requests | 1 |
| Requests to `/` | 559 |
| Distinct IP addresses for subject requests | 101 |
| Distinct IP + user-agent pairs for subject requests | 102 |
| Rapid bursts containing at least four subject requests | 155 |
| Subject requests within those bursts | 1,380 (91.2%) |
| IP + user-agent pairs exhibiting those bursts | 93 of 102 |
| Bursts containing exactly 8–10 subject requests | 143 of 155 |
| Median / maximum burst duration | 9 / 16 seconds |
| Bursts starting within 15 seconds after a login-page POST from the same IP + user-agent pair | 152 of 155 |
| Bursts starting within 60 seconds after that POST | 153 of 155 |
| Subject requests declaring Android WebView (`; wv)`) | 1,132 (74.8%) |
| Subject requests declaring `Sec-Fetch-Mode: cors` | 244 |
| Subject requests declaring generic `Chrome/120.0.0.0` | 281, across 20 IP + user-agent pairs |
| Requests marked `is_bot=NO` by the existing logger | All 2,072 |

Method: parse the CSV, group subject requests by exact IP + user-agent, sort each group by timestamp, and split a run whenever the next request is more than three seconds later. A rapid burst is a run with four or more requests. This is an investigative heuristic, not an identification of a device or proof of abuse. Shared IPs, spoofed user agents, address changes, and second-resolution timestamps limit precision.

Within the 102 groups, the 1,411 consecutive subject-request gaps include 875 one-second gaps, 201 two-second gaps, and 153 same-second gaps. The request cadence is predominantly one second, with some batches effectively concurrent. There are pauses between batches; no run under this definition lasts longer than 16 seconds.

## Concrete examples

Line numbers below refer to the CSV including its header. Other clients' requests may appear between the cited start and end lines.

| Example | Login-page submission | Subject requests | CSV lines |
|---|---|---|---|
| Android WebView, navigation mode | 17:40:51 | 9 from 17:40:52 to 17:41:04 | 11; 12–20 |
| Generic Chrome 120, fetch/CORS mode | 17:48:35 | 10 from 17:48:36 to 17:48:40 | 50; 51–61 |
| Generic Chrome 120, fetch/CORS mode | 17:49:38 | 10 from 17:49:38 to 17:49:43 | 75; 76–86 |
| Android WebView, fetch/CORS mode | Preceding login sequence available in CSV | 10 at 18:18:36, within the same logged second | 448–457 |

One generic Chrome 120 IP + user-agent pair makes six separate ten-request batches: 17:49:38–17:49:43, 17:53:46–17:53:51, 17:57:09–17:57:15, 17:59:04–17:59:09, 17:59:51–17:59:56, and 20:22:17–20:22:23. This is consistent with repeating a full collection step when signing in or refreshing an app.

## What the daily activity log contributes

`jntuaceastudents/logs/2026-10-16.log` has 1,780 records through 20:47:58: 1,604 successful password-authentication messages across 790 usernames and 176 failed-login messages. During the request-log time window there are 193 successful-authentication messages and 19 failure messages.

The request CSV has 286 login-page POSTs, 264 with a Turnstile field and 22 without. A field's presence does not establish successful verification. Some submissions may be rejected before password authentication, and the activity log has no IP, user-agent, or request identifier with which to reliably join its accounts to CSV requests.

Repeated logins alone do not establish automation. Do not accuse individual students by matching nearby timestamps: concurrent logins make that unreliable. The current database audit writer also supports login events with account, IP and user-agent (`dbcredentials.class.php`), so authorized production audit records may help narrow attribution. They still do not replace per-request account and subject logging, and deployment behavior must be checked.

## Code findings and likely mechanism

1. `studentsubjects.php:84` submits one subject's hidden form on a row click; the target is `studentsubatt.php` at line 106. No polling interval, auto-submission of every subject, or refresh loop was found in the inspected local flow, header, footer, or student JavaScript/PHP search.
2. `header.php:89–93` wraps fetch and XMLHttpRequest to attach CSRF headers. It does not initiate requests or iterate through subjects.
3. `studentsubatt.php:8` logs the request and subsequently checks ownership and enrollment. It then reads attendance, permissions, diary, outcomes, CIA and results. A batch multiplies database/render work across subjects. This is a read/display flow; these logs provide no evidence that attendance was changed.
4. `RequestSecurity::boot()` checks session identity, role and CSRF before the request logger is reached. **If production matches this revision**, recorded subject POSTs already passed those checks. They can still fail resource/ownership/enrollment checks later; the CSV records no response outcome. A scraper operating with the student's authenticated session can obtain CSRF tokens from the normal forms.
5. Request logging is called only by `index.php`, `studentsubatt.php` and `studentsubatt1.php` in the inspected student code. The sample therefore omits intermediate home and subject-list visits, rejected requests stopped before the logger, assets and most other endpoints. The apparent direct jump from login to detail pages is incomplete visibility.
6. WebView and same-origin headers are compatible with an app loading the real portal and running injected JavaScript or navigating its forms. Native automation can also mimic these headers. Their presence does not authenticate the application.
7. The CORS mode requests differ from ordinary form-navigation mode and fit programmatic fetching. **CORS mode does not mean a cross-origin request or a CORS vulnerability**; all subject records declare same-origin site context. The request mode is described in [MDN's documentation](https://developer.mozilla.org/en-US/docs/Web/HTTP/Reference/Headers/Sec-Fetch-Mode).
8. The best-supported sequence is: login or app refresh → obtain session and subject forms → collect a batch of subject detail pages → parse/display/cache the results → repeat after another login or refresh. The same burst shape across many browser signatures suggests common client behavior. A shared app bug, prefetch feature, deliberate scraping and automation in an official wrapper cannot be distinguished without inspecting a representative client and its network requests.

## Existing protection limitations

- `logger.php:10` treats the mere presence of `Sec-Fetch-Site` as `is_bot=NO`. All observed bursts pass this test. This is a metadata-presence flag, not bot detection.
- `logger.php:17` labels Chrome versions below 130 as fake. An old browser is not proof of spoofing, and a newer version is not proof of legitimacy.
- `logger.php:5` accepts `X-Forwarded-For` without checking the connecting proxy. Until the trusted proxy chain is verified, recorded IPs must be treated as potentially client-influenced. This also differs from the database audit writer's use of `REMOTE_ADDR`.
- The inspected request path has no account-based request limiter. Session-only counters would be insufficient because this pattern frequently creates new login sessions.
- Login has server-side Turnstile verification, but an app can use a real browser/WebView login. The current code checks `success` without explicitly checking the returned hostname or a configured action. Tightening those checks is useful hardening, not proof of the present cause. [Cloudflare recommends checking the expected fields and documents single-use token validation](https://developers.cloudflare.com/turnstile/get-started/server-side-validation/).
- Browser signatures, navigation headers, hidden form fields, referrers, CORS policy and CSRF tokens cannot independently prove that a human clicked a subject or that a specific authorized app is being used.

## Recommended response

### 1. Make the behavior attributable

Add structured logging for the student request flow, including login outcomes, home, subject-list and both detail endpoints. Record a request ID, millisecond time, authenticated account ID taken from the session, a keyed session-ID hash, validated integer student/class/subject IDs, request mode/destination/user-activation metadata, response status, rejection reason and elapsed time. Record the attempted identifiers separately from whether authorization succeeded.

Never log passwords, cookies, raw session IDs, CSRF values, Turnstile values or entire POST bodies. Resolve client IPs only through a verified trusted proxy configuration. Capture security denials as well as completed requests. Rotate and restrict access to the logs.

The decisive sequence to look for is the **same authenticated account requesting 8–10 distinct subject IDs in the same class within roughly 5–15 seconds**, particularly across repeated logins. A keyed session hash is a session correlation aid, not a physical-device identity.

### 2. Limit reads per authenticated account before expensive work

Use an atomic shared store, such as Redis or a bounded database-backed limiter, keyed by the server-authenticated user ID. Share the subject-detail budget across `studentsubatt.php` and `studentsubatt1.php`, and assess equivalent expensive student routes so changing the endpoint does not bypass the limit. Apply it after authentication but before service construction, resource queries and rendering. Add a separate pre-authentication login limiter.

Begin with observation-only counters while gathering a normal-use baseline. A **candidate policy to validate**, not an established safe threshold, is no more than five subject-detail requests in a rolling ten seconds and twenty per minute per account. Excess requests receive HTTP 429 and an appropriate `Retry-After`; repeated excessive bursts can require fresh verification and a temporary cooldown. Tune the policy against legitimate rapid navigation, multiple tabs and accessibility workflows. Avoid permanent account locks from one burst.

Account limits persist across login/session renewal and IP changes. Use per-session and trusted-IP limits as supplementary controls, with generous aggregate limits for campus/mobile shared networks. Avoid making the number of subjects requested a hard ban rule by itself. [OWASP recommends application/infrastructure rate limiting and establishing genuine traffic baselines](https://cheatsheetseries.owasp.org/cheatsheets/Denial_of_Service_Cheat_Sheet.html).

### 3. Challenge persistent automation and reduce repeated work

For repeated suspicious behavior, require a fresh server-validated Turnstile challenge with expected hostname and configured action, tied to the requesting account/session, before resuming protected reads. A successfully completed challenge does not remove account limits. Edge controls can reduce abusive traffic before it reaches PHP, but do not assume a Cloudflare proxy/WAF is deployed simply because Turnstile is used.

Cache authorized subject results briefly, for example 30–60 seconds, using keys that include student, subject, class and relevant visibility/permission context. Validate authorization before serving cached data and invalidate appropriately for attendance corrections, permissions and publication changes. A cached response must still count toward the request limit.

### 4. Inspect a representative client and address the product cause

Use the improved logs to identify an affected account and inspect a representative app's network sequence and app identity with the user's cooperation. Determine whether it batches requests at startup, retries due to a client defect, prefetches data, or synchronizes on refresh.

If students want an all-subject attendance overview, provide that officially through a bounded, authorized aggregate view and have any official wrapper fetch it once with sensible refresh intervals. This removes the incentive and load of scraping every detail page. The aggregate endpoint still needs rate limits and must return only the student's authorized, visible data.

A public browser portal cannot reliably guarantee that only one named app accesses it using HTTP headers or a secret embedded in client code. Strong app identity would require a separate authenticated client design with server-verified attestation and a defined browser access policy. It is a larger product decision; behavior-based protection is the practical immediate response.

## Immediate recommendation

Implement account/resource/outcome logging and an account-based limiter first, then use the evidence to confirm the client and tune enforcement. Preserve current authentication, ownership, enrollment and CSRF checks. Browser-version or WebView blocking alone would be easy to evade and could reject legitimate students. No application changes or production actions were performed in this investigation.

# Cloudflare Setup — Edge Defense Plan

> **Status:** Planned, not yet executed.
> **Decision date:** 2026-04-26
> **Owner:** Reggie
> **Path chosen:** Path 1 (free Cloudflare account, nameserver migration)

## Why we're doing this

The plugin-level defenses shipped in v0.1.13 (REST nonce gating, rate limits, RestGuard) and v0.1.14 (`/places/` 301) reject scrapers at the application layer — but only after the request has hit the origin server. Adding Cloudflare in front gives us:

1. **Edge bot detection** — most automated scraper traffic gets blocked before it ever touches Cloudways
2. **Edge rate limiting** — `/wp-json/*` rate limits enforced globally, no PHP execution needed
3. **WAF managed rules** — Cloudflare's curated rules catch generic exploit/scan patterns
4. **CDN caching** — static assets served from edge, faster pages worldwide
5. **Free SSL + DDoS protection** — better than what Cloudways offers natively

Cost: **$0/month** on the free tier. Cloudflare Pro ($20/mo) adds Super Bot Fight Mode and more rate limit rules — overkill for current traffic, revisit if we grow.

## Current state (audit, 2026-04-26)

- **Hosting:** Cloudways (DigitalOcean under the hood)
- **DNS:** Google Cloud DNS (`ns-cloud-bN.googledomains.com`) — likely leftover from a Google Domains registration that migrated to Squarespace in 2023
- **In front of origin:** nothing (no proxy, no WAF, no edge cache beyond what Cloudways' Breeze provides)
- **Email:** Confirm with Reggie before nameserver flip — likely Google Workspace or Squarespace mail. MX records MUST be recreated in Cloudflare before activation or email will break.

## Prerequisites — collect before starting

Inventory every existing DNS record at the current nameservers (Google Cloud DNS or wherever DNS now lives — possibly Squarespace if Google Domains migrated). Run:

```bash
dig lovetrivalley.com ANY +short
dig lovetrivalley.com MX +short
dig lovetrivalley.com TXT +short
dig www.lovetrivalley.com +short
dig staging.lovetrivalley.com +short    # check for staging subdomains
```

For each subsite (lovedublin, lovepleasanton, lovelivermore, lovedanville, lovesanramon — any custom domain that points to the network):

```bash
dig lovedublin.com ANY +short
# repeat for each subsite custom domain
```

Write down every record. Common ones to expect:
- `A` records → Cloudways IP
- `MX` records → Google Workspace or Squarespace mail servers
- `TXT` records → SPF (`v=spf1 include:...`), DKIM (`google._domainkey`), DMARC (`_dmarc`), domain verification (`google-site-verification`)
- `CNAME` records → `www`, possibly `mail`, others

**If you miss an MX or TXT record, email breaks the moment Cloudflare goes live.** Slow down here.

## Migration runbook (~30 min active work, +24h DNS propagation wait)

### 1. Sign up for Cloudflare (5 min)

- Visit https://dash.cloudflare.com/sign-up — create a free account
- Verify the email
- Click **Add a Site** → enter `lovetrivalley.com`
- Pick the **Free** plan
- Cloudflare will scan your existing DNS records and import what it can detect

### 2. Verify the imported records and add anything missing (10 min)

This is the careful step. Compare the records Cloudflare imported against your inventory from the prerequisites section.

For each record:
- **A/AAAA records** for the website (`@`, `www`) → click the cloud icon to make it **orange (Proxied)** — this is what activates Cloudflare's protection
- **MX records** → grey cloud (DNS only, never proxied — proxying breaks email)
- **TXT records** (SPF, DKIM, DMARC, verification) → grey cloud, just leave as DNS records
- **CNAME for staging or admin subdomains** → grey cloud unless you want Cloudflare protecting them too

If Cloudflare missed any records from your inventory, click **Add Record** and add them manually.

### 3. Switch nameservers at the registrar (5 min)

Cloudflare will show you two assigned nameservers like:
```
nina.ns.cloudflare.com
walt.ns.cloudflare.com
```

Log into wherever your domain is registered (if it was Google Domains, that's Squarespace now: https://account.squarespace.com/domains). Replace the existing nameservers with the two Cloudflare nameservers.

**This is the moment of truth.** From this point, DNS takes 5 minutes to 48 hours to propagate. Most propagation completes within 1-4 hours.

### 4. Wait for activation (1-24h)

Cloudflare emails you when activation completes. You can also check:
```bash
dig lovetrivalley.com NS +short
# expect: nina.ns.cloudflare.com (or whatever was assigned)
```

While waiting, the site stays up — propagation is gradual, not instant.

### 5. Configure SSL (5 min, once active)

In Cloudflare dashboard → **SSL/TLS → Overview**:
- Set encryption mode to **Full (strict)**
- Cloudways serves valid HTTPS, so "Full (strict)" works. Avoid "Flexible" — it's insecure.

In **SSL/TLS → Edge Certificates**:
- Toggle **Always Use HTTPS** ON
- Toggle **Automatic HTTPS Rewrites** ON

### 6. Apply security rules (10 min)

This is the payoff. All in the Cloudflare dashboard.

#### 6a. Bot Fight Mode

Path: **Security → Bots**
- Toggle **Bot Fight Mode** ON
- (Free tier; "Super Bot Fight Mode" requires Pro plan)

#### 6b. Rate limit rule for `/wp-json/*`

Path: **Security → Rate Limiting Rules → Create rule**
- Rule name: `Throttle wp-json`
- Field: **URI Path** | Operator: **starts with** | Value: `/wp-json/`
- When rate exceeds: **30 requests** per **10 seconds** per **IP**
- Action: **Block** (or **Managed Challenge** if you want to be gentler)
- Duration: **10 seconds**

The free tier includes 1 rate limit rule and 10k requests/month. This rule will fire only on actual rate limit hits, so 10k/mo is plenty.

#### 6c. Custom rule blocking known scraper UAs on `/wp-json/*`

Path: **Security → WAF → Custom Rules → Create rule**
- Rule name: `Block scraper UAs on REST`
- Expression (use the expression builder):
  ```
  (http.request.uri.path contains "/wp-json/")
    and (
      lower(http.user_agent) contains "curl"
      or lower(http.user_agent) contains "wget"
      or lower(http.user_agent) contains "python-requests"
      or lower(http.user_agent) contains "scrapy"
      or lower(http.user_agent) contains "headlesschrome"
      or http.user_agent eq ""
    )
  ```
- Action: **Block**

Note: legitimate Googlebot/Bingbot don't hit `/wp-json/*` and their UAs don't match these patterns — they go for `/places/*` and `/explore/*`. Safe to block.

#### 6d. WAF managed rules

Path: **Security → WAF → Managed Rules**
- Toggle **Cloudflare Managed Ruleset** ON (free)
- Set sensitivity to default

#### 6e. Caching basics

Path: **Caching → Configuration**
- **Browser Cache TTL**: Respect Existing Headers (let WP control it)
- **Crawler Hints**: ON (helps search engines crawl efficiently)

Path: **Speed → Optimization**
- **Auto Minify**: leave OFF (Cloudways' Breeze handles this; double-minifying can break things)
- **Brotli**: ON

### 7. Verify the rules are working (10 min)

Run from Terminal after activation:

```bash
# 1. Confirm Cloudflare is in front (should see CF-Ray header)
curl -sI https://lovetrivalley.com/ | grep -i "cf-ray\|server"
# expect: server: cloudflare + cf-ray: <hash>

# 2. Confirm SSL works
curl -sI https://lovetrivalley.com/ | grep -i "http/"
# expect: HTTP/2 200

# 3. Confirm the /places/ → /explore/ 301 still works (origin behavior)
curl -sI https://lovetrivalley.com/places/ | grep -E "HTTP|location"
# expect: HTTP/2 301 + location: https://lovetrivalley.com/explore/

# 4. Test scraper UA block (rule 6c)
curl -sI -A "python-requests/2.28.0" https://lovetrivalley.com/wp-json/ | head -3
# expect: HTTP/2 403 (blocked by Cloudflare)

# 5. Test rate limit (rule 6b) — burst 50 requests fast
for i in $(seq 1 50); do
  curl -s -o /dev/null -w "%{http_code} " https://lovetrivalley.com/wp-json/wp/v2/types
done
echo
# expect: first ~30 return 401 (RestGuard), then some return 429 (rate limit)

# 6. Confirm normal browser traffic still works
curl -sI -A "Mozilla/5.0 (Macintosh) AppleWebKit/605.1.15 Safari/605.1.15" https://lovetrivalley.com/explore/ | head -3
# expect: HTTP/2 200
```

### 8. Watch for 24-48h

- Search Console: any new crawl errors?
- Email: send + receive a test message to confirm MX records flow correctly
- Check Cloudways app: any error log spike?
- Cloudflare dashboard → Analytics: traffic patterns, bot vs human breakdown, blocked requests count

## Rollback plan

If something breaks badly during/after migration:

1. **Site down or wrong content:** Pause Cloudflare. Cloudflare dashboard → **Overview** → click **Pause Cloudflare on Site**. Traffic flows directly to origin while you investigate. Doesn't change DNS, just disables the proxy.
2. **Email broken:** Verify MX records in Cloudflare match what was at Google. Set them grey-cloud. Wait 5-10 min for propagation.
3. **Worst case (need to fully revert):** Switch nameservers back at the registrar (Squarespace) to the original Google Cloud DNS nameservers. DNS reverts within 1-4 hours typically. Cloudflare account can stay dormant for next attempt.

## Subsite considerations

The Tri-Valley network has city subsites at custom domains (lovedublin, lovepleasanton, etc.). Each subsite domain needs its OWN Cloudflare zone if you want them protected too:

- Free Cloudflare accounts can host **unlimited zones** (one per domain), all free
- Each subsite domain follows the same migration process above
- Recommend doing them one at a time — start with the main domain (`lovetrivalley.com`), validate, then roll the subsites individually

OR — leave the subsites on their current DNS for now. Plugin-level defenses (RestGuard, BusinessesController nonce gating) protect those subsites already at the application layer.

## What this does NOT solve

- **Patient distributed scrapers** — still essentially unstoppable at any tier
- **Authenticated scraping** — if someone makes an account and scrapes through legit user flows, the protections don't apply. Future work: rate limit per user_id, not just per IP.
- **Origin direct attacks** — if an attacker discovers your Cloudways IP, they can bypass Cloudflare entirely. Cloudways shouldn't expose it directly, but worth verifying. Consider Cloudflare's "Authenticated Origin Pulls" feature for hardened origin.

## When to revisit

- After 30 days: review Cloudflare Analytics. Confirm bot block rate, false positive rate (any complaints?)
- If traffic grows: consider Cloudflare Pro ($20/mo) for Super Bot Fight Mode + more rate limit rules
- If we add a checkout/payment flow: consider Cloudflare Turnstile (their CAPTCHA) for forms; integrates well with the existing `BD\Security\Captcha` class

## Related docs

- Application-layer defenses: `src/Security/RestGuard.php`, `src/Security/RateLimit.php`
- The `/places/` redirect that complements this: `src/SEO/ArchiveRedirect.php`
- Plugin changelog 0.1.13 + 0.1.14 entries: `README.md`

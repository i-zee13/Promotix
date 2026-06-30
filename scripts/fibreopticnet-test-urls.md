# fibreopticnet.com — test plan + URLs

**Ad landing page (use this, not homepage):** `https://fibreopticnet.com/optimum/`  
Campaign in Google: **New Sales** · `gad_campaignid=23927402524`

---

## Before you test — baseline (from DB)

| | Value |
|--|--------|
| Domain | `fibreopticnet.com` (#10) |
| Tag last seen | **26 Jun 2026** (no tag hits 27–30 Jun in DB) |
| Google **29 Jun** | **48 clicks**, tag **0** |
| Google **26 Jun** | **2 clicks**, tag **1** |
| Screenshot **Google: 3** | Selected **date range** ka Google API total — likely **today / short range** on live sync (DB day may differ) |

**Dashboard meaning:**
- **Paid traffic: 0** = tag ne `gclid`/`gbraid`/`wbraid` capture nahi kiye
- **Google Ads: 3 (reference)** = Google ne clicks report kiye — IP table in par depend nahi karti

Deploy **`IpFraudEvaluator.php` fix** (`$list` typo) before IP table test.

---

## Phase 1 — SKIP tests (Paid table mein NAHI aana chahiye)

| # | Device | Browser | URL |
|---|--------|---------|-----|
| 1 | iPhone | Safari Private | https://fibreopticnet.com/optimum/?gad_source=1&gad_campaignid=23927402524&promotix_test=fibre_skip_gad |
| 2 | Laptop Win | Chrome Incognito | https://fibreopticnet.com/optimum/?dclid=fibre_hit_dclid_01&promotix_test=fibre_skip_dclid |
| 3 | Laptop Win | Chrome Incognito | https://fibreopticnet.com/optimum/?utm_source=google&utm_medium=cpc&utm_campaign=new_sales&promotix_test=fibre_skip_utm |
| 4 | MacBook | Safari Private | https://fibreopticnet.com/optimum/?utm_source=google&utm_medium=cpc&gad_source=1&gad_campaignid=23927402524&promotix_test=fibre_skip_utm_gad |
| 5 | Laptop Win | Edge InPrivate | https://fibreopticnet.com/optimum/?msclkid=fibre_hit_msclkid_01&promotix_test=fibre_skip_bing |
| 6 | Android | Chrome Incognito | https://fibreopticnet.com/optimum/?fbclid=fibre_hit_fbclid_01&promotix_test=fibre_skip_meta |
| 7 | Android | Chrome Incognito | https://fibreopticnet.com/optimum/?ttclid=fibre_hit_ttclid_01&promotix_test=fibre_skip_tiktok |
| 8 | Laptop | Firefox Private | https://fibreopticnet.com/optimum/?promotix_test=fibre_skip_organic |

---

## Phase 2 — WORK tests (Paid Marketing + IP aana chahiye)

| # | Device | Browser | URL |
|---|--------|---------|-----|
| 9 | Laptop Win | Chrome Incognito | https://fibreopticnet.com/optimum/?gclid=fibre_hit_gclid_01&promotix_test=fibre_work_gclid |
| 10 | iPhone | Safari Private | https://fibreopticnet.com/optimum/?gbraid=fibre_hit_gbraid_01&promotix_test=fibre_work_gbraid |
| 11 | iPhone | Chrome Incognito | https://fibreopticnet.com/optimum/?wbraid=fibre_hit_wbraid_01&promotix_test=fibre_work_wbraid |
| 12 | Laptop Chrome | Incognito | https://fibreopticnet.com/optimum/?gclid=fibre_hit_gclid_02&gad_source=1&gad_campaignid=23927402524&promotix_test=fibre_work_gclid_gad |

**Rules:** har URL **new incognito** · **2 min gap** · same day max **2 paid hits** same IP (3rd invalid)

---

## Phase 3 — Real ad page check (no test params)

| # | Device | URL |
|---|--------|-----|
| 13 | iPhone Safari | https://fibreopticnet.com/optimum/ (homepage mat kholo) |
| 14 | Android Chrome | https://fibreopticnet.com/optimum/ |

View Source → PromoTix `/tag/` script · Network → `ingest/visit` POST

---

## Verify

```bash
php scripts/dump-domain.php fibreopticnet.com YYYY-MM-DD
```

Dashboard: domain **fibreopticnet.com** · date = jis din test kiya

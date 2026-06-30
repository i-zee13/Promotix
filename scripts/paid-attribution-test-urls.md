# Paid attribution test URLs

Use these on a page where the **PromoTix tag is installed** (plugin enabled, `wp_head` runs).

**Default site:** `https://insuranceforme.online/`  
Change the path if your ad landing page is different (e.g. `/lp/`, `/quote/`).

After each hit, wait ~10 seconds, then check:
- **Paid Marketing dashboard** → IP table / paid count
- Or run: `php scripts/check-paid-clicks.php insuranceforme.online YYYY-MM-DD`

Use **incognito** (or clear `localStorage` keys `pm_*`) between tests so old `gclid` does not stick.

---

## Should count as PAID (gclid family — current code)

| # | Label | URL |
|---|--------|-----|
| 1 | gclid | https://insuranceforme.online/?gclid=promotix_hit_gclid_01 |
| 2 | gbraid | https://insuranceforme.online/?gbraid=promotix_hit_gbraid_01 |
| 3 | wbraid | https://insuranceforme.online/?wbraid=promotix_hit_wbraid_01 |
| 4 | gclid + gad_* (gclid wins) | https://insuranceforme.online/?gclid=promotix_hit_gclid_02&gad_source=1&gad_campaignid=999888777 |
| 5 | gbraid + gad_* | https://insuranceforme.online/?gbraid=promotix_hit_gbraid_02&gad_source=1&gad_campaignid=999888777 |

---

## Should NOT count as PAID today (tag may still log IP as organic)

| # | Label | URL |
|---|--------|-----|
| 6 | gad_source + gad_campaignid only | https://insuranceforme.online/?gad_source=1&gad_campaignid=999888777 |
| 7 | dclid (Display / DV360) | https://insuranceforme.online/?dclid=promotix_hit_dclid_01 |
| 8 | UTM cpc only (no click ID) | https://insuranceforme.online/?utm_source=google&utm_medium=cpc&utm_campaign=promotix_test_campaign |
| 9 | UTM + gad_* only | https://insuranceforme.online/?utm_source=google&utm_medium=cpc&utm_campaign=promotix_test&gad_source=1&gad_campaignid=999888777 |
| 10 | msclkid (Microsoft Ads) | https://insuranceforme.online/?msclkid=promotix_hit_msclkid_01 |
| 11 | fbclid (Meta) | https://insuranceforme.online/?fbclid=promotix_hit_fbclid_01 |
| 12 | ttclid (TikTok) | https://insuranceforme.online/?ttclid=promotix_hit_ttclid_01 |
| 13 | No params (organic baseline) | https://insuranceforme.online/?promotix_test=organic_baseline |

---

## Control: tag not loaded = no IP at all

Open a page **without** the tag (or with plugin disabled):

| # | Label | URL |
|---|--------|-----|
| 14 | Same gclid, no tag | Use #1 URL on a page/domain where PromoTix tag is **not** installed |

---

## Quick copy — one per line

```
https://insuranceforme.online/?gclid=promotix_hit_gclid_01
https://insuranceforme.online/?gbraid=promotix_hit_gbraid_01
https://insuranceforme.online/?wbraid=promotix_hit_wbraid_01
https://insuranceforme.online/?gclid=promotix_hit_gclid_02&gad_source=1&gad_campaignid=999888777
https://insuranceforme.online/?gbraid=promotix_hit_gbraid_02&gad_source=1&gad_campaignid=999888777
https://insuranceforme.online/?gad_source=1&gad_campaignid=999888777
https://insuranceforme.online/?dclid=promotix_hit_dclid_01
https://insuranceforme.online/?utm_source=google&utm_medium=cpc&utm_campaign=promotix_test_campaign
https://insuranceforme.online/?utm_source=google&utm_medium=cpc&utm_campaign=promotix_test&gad_source=1&gad_campaignid=999888777
https://insuranceforme.online/?msclkid=promotix_hit_msclkid_01
https://insuranceforme.online/?fbclid=promotix_hit_fbclid_01
https://insuranceforme.online/?ttclid=promotix_hit_ttclid_01
https://insuranceforme.online/?promotix_test=organic_baseline
```

---

## Expected results (current PromoTix code)

| Hit # | Paid Marketing IP table | Visit logged (any) | `is_paid_traffic` |
|-------|-------------------------|--------------------|-------------------|
| 1–5 | Yes | Yes | 1 |
| 6–13 | No* | Yes (if tag fires) | 0 |
| 14 | No | No | — |

\*Organic visits may appear under Bot Protection / visits, not Paid Marketing.

---

## Verify in database (optional)

```bash
php scripts/check-paid-clicks.php insuranceforme.online 2026-05-26
```

Or search visits for your test token:

```sql
SELECT visited_at, ip, gclid, gbraid, wbraid, is_paid_traffic, url
FROM visits
WHERE url LIKE '%promotix_hit%' OR url LIKE '%promotix_test%'
ORDER BY visited_at DESC;
```

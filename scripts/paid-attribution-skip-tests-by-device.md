# Skip-test URLs (NOT paid today) + recommended device

Site: `https://insuranceforme.online/`  
Har hit **incognito/private** mein, hits ke beech **2 min** gap.

Legend: **SKIP** = tag fire hua to visit log ho sakti hai, lekin Paid Marketing IP table mein **nahi** aayegi.

---

## 1. gad_source + gad_campaignid only → **iPhone (Safari)**

Real world: iOS par kabhi sirf `gad_*` bachta hai, click ID nahi.

```
https://insuranceforme.online/?gad_source=1&gad_campaignid=999888777&promotix_test=skip_gad_only
```

**Device:** iPhone → Safari (Private tab)  
**Expected:** SKIP paid ❌ | visit log ho sakti ✅

---

## 2. dclid (Display / DV360) → **Laptop (Windows Chrome)**

```
https://insuranceforme.online/?dclid=promotix_hit_dclid_01&promotix_test=skip_dclid
```

**Device:** Laptop → Chrome (Incognito)  
**Expected:** SKIP paid ❌

---

## 3. UTM cpc only (no click ID) → **Laptop (Windows Chrome)**

Auto-tagging off / manual URL template jaisa.

```
https://insuranceforme.online/?utm_source=google&utm_medium=cpc&utm_campaign=promotix_skip_utm&promotix_test=skip_utm_cpc
```

**Device:** Laptop → Chrome (Incognito)  
**Expected:** SKIP paid ❌

---

## 4. UTM + gad_* (no gclid/gbraid/wbraid) → **MacBook (Safari)**

```
https://insuranceforme.online/?utm_source=google&utm_medium=cpc&utm_campaign=promotix_skip_utm_gad&gad_source=1&gad_campaignid=999888777&promotix_test=skip_utm_gad
```

**Device:** MacBook → Safari (Private window)  
**Expected:** SKIP paid ❌

---

## 5. msclkid (Microsoft / Bing Ads) → **Laptop (Microsoft Edge)**

```
https://insuranceforme.online/?msclkid=promotix_hit_msclkid_01&promotix_test=skip_msclkid
```

**Device:** Laptop → Edge (InPrivate)  
**Expected:** SKIP paid ❌

---

## 6. fbclid (Meta / Facebook) → **Android phone (Chrome)**

```
https://insuranceforme.online/?fbclid=promotix_hit_fbclid_01&promotix_test=skip_fbclid
```

**Device:** Android phone → Chrome (Incognito)  
**Expected:** SKIP paid ❌

---

## 7. ttclid (TikTok Ads) → **Android phone (Chrome)** or iPhone

```
https://insuranceforme.online/?ttclid=promotix_hit_ttclid_01&promotix_test=skip_ttclid
```

**Device:** Android phone → Chrome (Incognito)  
**Alt:** iPhone → Chrome incognito (same URL)  
**Expected:** SKIP paid ❌

---

## 8. Organic baseline (koi ad tag nahi) → **Laptop (Firefox)**

```
https://insuranceforme.online/?promotix_test=skip_organic_baseline
```

**Device:** Laptop → Firefox (Private window)  
**Expected:** SKIP paid ❌ | Bot Protection mein organic dikh sakti hai

---

## Quick checklist (copy for testing)

| # | Device | Browser | URL token | Paid table? |
|---|--------|---------|-----------|-------------|
| 1 | iPhone | Safari Private | `skip_gad_only` | No |
| 2 | Laptop Win | Chrome Incognito | `skip_dclid` | No |
| 3 | Laptop Win | Chrome Incognito | `skip_utm_cpc` | No |
| 4 | MacBook | Safari Private | `skip_utm_gad` | No |
| 5 | Laptop Win | Edge InPrivate | `skip_msclkid` | No |
| 6 | Android | Chrome Incognito | `skip_fbclid` | No |
| 7 | Android | Chrome Incognito | `skip_ttclid` | No |
| 8 | Laptop Win | Firefox Private | `skip_organic` | No |

---

## Compare: yeh 3 WORK karni chahiye (alag devices se)

Inhe skip list ke baad test karo — Paid Marketing mein **aani chahiye**.

| Tag | Device | URL |
|-----|--------|-----|
| gclid | Laptop Chrome | `?gclid=promotix_hit_gclid_01&promotix_test=work_gclid` |
| gbraid | iPhone Safari | `?gbraid=promotix_hit_gbraid_01&promotix_test=work_gbraid` |
| wbraid | iPhone Chrome | `?wbraid=promotix_hit_wbraid_01&promotix_test=work_wbraid` |

**Note:** iPhone se sirf 2 valid paid hits/day same IP — `gclid` laptop se, `gbraid`/`wbraid` phone se karo.

---

## Verify

```bash
php scripts/check-paid-clicks.php insuranceforme.online YYYY-MM-DD
```

DB:

```sql
SELECT ip, gclid, gbraid, wbraid, is_paid_traffic, url, visited_at
FROM visits
WHERE url LIKE '%promotix_test=skip_%' OR url LIKE '%promotix_test=work_%'
ORDER BY visited_at DESC;
```

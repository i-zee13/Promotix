<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Clickronix Transparent Click Tracker</title>
    <style>
        body { font-family: ui-sans-serif, system-ui, sans-serif; max-width: 760px; margin: 40px auto; padding: 0 20px; color: #1a1a1a; line-height: 1.5; }
        h1 { font-size: 1.4rem; }
        h2 { font-size: 1.05rem; margin-top: 1.6rem; }
        code, pre { background: #f4f2f7; border-radius: 6px; }
        code { padding: 0.1em 0.35em; font-size: 0.9em; }
        pre { padding: 12px 14px; overflow-x: auto; font-size: 0.82rem; }
        .note { color: #555; font-size: 0.92rem; }
    </style>
</head>
<body>
    <h1>Clickronix Transparent Click Tracker</h1>
    <p class="note">Public tracker documentation. Landing-page fraud detection is separate and is not replaced by this redirect.</p>

    <h2>Flow</h2>
    <p>Google Ads tracking template → <code>{{ $trackerHost }}/click</code> → 302 to the advertiser landing URL → Clickronix tag on the landing page.</p>

    <h2>Tracking template</h2>
    <pre>{{ $template }}</pre>
    <p><code>redirect={lpurl}</code> is the certification parameter. <code>final_url={lpurl}</code> is still accepted so existing campaigns keep working.</p>

    <h2>Registry (<code>cx_*</code>)</h2>
    <p>Optional Clickronix keys: <code>cx_account</code>, <code>cx_campaign</code>, <code>cx_adgroup</code>, <code>cx_creative</code>, <code>cx_keyword</code>, plus device/network/match/placement. Google ValueTrack aliases (<code>campaignid</code>, <code>adgroupid</code>, …) are stored into the same registry.</p>

    <h2>Audit ID</h2>
    <p>Every tracker hit mints a <code>CXTRK_</code> id, logs it server-side, and forwards <code>cxtrk</code> on the landing URL so the landing visit can be joined to the click.</p>

    <h2>What this endpoint does not do</h2>
    <ul>
        <li>No fingerprint / bot scoring on <code>/click</code> (those run on the landing tag only).</li>
        <li>Never blocks Google. Missing data still 302s to the landing URL when a redirect target is present.</li>
    </ul>

    <h2>Certification window</h2>
    <p class="note">Google Ads tracking-template review window referenced in the certification pack: 1 Sep – 30 Nov.</p>
</body>
</html>

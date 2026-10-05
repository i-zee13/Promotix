<?php

namespace App\Http\Controllers;

use App\Models\Domain;
use App\Models\DomainDetectionSetting;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TagController extends Controller
{
    public function js(Request $request, string $domainKey): Response
    {
        $domain = Domain::where('domain_key', $domainKey)->firstOrFail();
        if (($domain->status ?? 'pending') === 'disabled') {
            return response('// Domain tracking is disabled.', 200, [
                'Content-Type' => 'application/javascript; charset=UTF-8',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);
        }

        $collectUrl = url('/ingest/visit');
        $sessionRecordingUrl = url('/ingest/session-recording');
        $ipCheckUrl = url('/ip-check');

        $settings = DomainDetectionSetting::query()->where('domain_id', $domain->id)->first();
        $consentRequired = (bool) ($settings?->consent_required ?? false);
        $maskPasswords = ($settings?->recording_mask_passwords ?? true) !== false;
        $consentRegionsJson = $this->json(json_encode($settings?->consent_regions ?? [], JSON_UNESCAPED_UNICODE));

        // Tracking tag: records visits server-side and enforces block / captcha on client.
        $trackingParams = (array) ($domain->tracking_params ?? [
            'utm_source' => true,
            'utm_medium' => true,
            'utm_campaign' => true,
            'utm_term' => true,
        ]);
        $trackSource = ($trackingParams['utm_source'] ?? true) ? 'true' : 'false';
        $trackMedium = ($trackingParams['utm_medium'] ?? true) ? 'true' : 'false';
        $trackCampaign = ($trackingParams['utm_campaign'] ?? true) ? 'true' : 'false';
        $trackTerm = ($trackingParams['utm_term'] ?? true) ? 'true' : 'false';
        // Heredoc cannot embed ternaries like {$x ? 'true' : 'false'} (PHP ParseError).
        $consentRequiredJs = $consentRequired ? 'true' : 'false';
        $maskPasswordsJs = $maskPasswords ? 'true' : 'false';
        $domainKeyJson = $this->json($domainKey);
        $collectUrlJson = $this->json($collectUrl);
        $sessionRecordingUrlJson = $this->json($sessionRecordingUrl);
        $ipCheckUrlJson = $this->json($ipCheckUrl);
        // Customer-facing block page always uses Clickronix (not host/APP_NAME PromoTix).
        $brandNameJson = $this->json('Clickronix');
        $brandLogoUrlJson = $this->json(\App\Support\Branding::logoAsset('block'));

        $js = <<<JS
(function(){
  var domainKey = {$domainKeyJson};
  var collectUrl = {$collectUrlJson};
  var sessionRecordingUrl = {$sessionRecordingUrlJson};
  var ipCheckUrl = {$ipCheckUrlJson};
  var brandName = {$brandNameJson};
  var brandLogoUrl = {$brandLogoUrlJson};
  var consentRequired = {$consentRequiredJs};
  var maskPasswords = {$maskPasswordsJs};
  var consentRegions = {$consentRegionsJson};
  var trackSource = {$trackSource};
  var trackMedium = {$trackMedium};
  var trackCampaign = {$trackCampaign};
  var trackTerm = {$trackTerm};

  // Public Pixel Guard / Clickronix event API (works before recording starts).
  window.__pmEventQueue = window.__pmEventQueue || [];
  window.Clickronix = window.Clickronix || {};
  window.PixelGuard = window.PixelGuard || window.Clickronix;
  window.Clickronix.track = function(eventName, eventData){
    var payload = { name: String(eventName || 'custom_event'), data: eventData || {} };
    try {
      if (typeof window.__pmRecordingPush === 'function') {
        window.__pmRecordingPush(payload.name, payload.data);
        return true;
      }
    } catch (e) {}
    window.__pmEventQueue.push(payload);
    return true;
  };
  window.Clickronix.trackEvent = window.Clickronix.track;
  window.PixelGuard.track = window.Clickronix.track;

  function qp(obj){
    try{
      var p = new URLSearchParams();
      for (var k in obj){
        if (!Object.prototype.hasOwnProperty.call(obj,k)) continue;
        var v = obj[k];
        if (v === undefined || v === null || v === '') continue;
        if (typeof v === 'object') {
          try { v = JSON.stringify(v); } catch (e) { continue; }
        }
        p.set(k, String(v));
      }
      p.set('_', String(Date.now()));
      return p.toString();
    }catch(e){ return ''; }
  }

  function pixel(payload){
    try{
      payload = payload || {};
      payload.click_source = payload.click_source || 'pixel';
      var copy = {};
      for (var k in payload) {
        if (!Object.prototype.hasOwnProperty.call(payload, k)) continue;
        if (k === 'fingerprint_signals') continue;
        copy[k] = payload[k];
      }
      var img = new Image();
      img.referrerPolicy = 'no-referrer-when-downgrade';
      img.src = collectUrl + (collectUrl.indexOf('?') === -1 ? '?' : '&') + qp(copy);
    }catch(e){}
  }

  function consentKey(){ return 'pm_consent_' + domainKey; }

  function hasConsent(){
    if (!consentRequired) return true;
    try {
      return localStorage.getItem(consentKey()) === '1';
    } catch (e) { return false; }
  }

  function grantConsent(){
    try { localStorage.setItem(consentKey(), '1'); } catch (e) {}
    var banner = document.getElementById('pm-consent-banner');
    if (banner) banner.remove();
    bootstrap();
  }

  function showConsentBanner(){
    if (!consentRequired || hasConsent() || document.getElementById('pm-consent-banner')) return;
    try {
      var bar = document.createElement('div');
      bar.id = 'pm-consent-banner';
      bar.style.cssText = 'position:fixed;left:0;right:0;bottom:0;z-index:2147483647;background:#101010;color:#fff;padding:16px 20px;font:14px/1.4 system-ui,sans-serif;display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:center;border-top:1px solid #6400B2;';
      bar.innerHTML = '<span style="max-width:640px;opacity:.9;">We use analytics and fraud protection cookies to secure paid traffic. Accept to continue.</span><button type="button" id="pm-consent-accept" style="border:0;border-radius:6px;background:#6400B2;color:#fff;font-weight:600;padding:8px 16px;cursor:pointer;">Accept</button>';
      (document.body || document.documentElement).appendChild(bar);
      var btn = document.getElementById('pm-consent-accept');
      if (btn) btn.addEventListener('click', grantConsent);
    } catch (e) {}
  }

  function earlyIpCheck(done){
    try {
      fetch(ipCheckUrl, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({ domainKey: domainKey, path: String(location.pathname || ''), referrer: String(document.referrer || '') }),
        mode: 'cors',
        credentials: 'omit',
        keepalive: true
      }).then(function(r){ return r.json(); }).then(function(resp){
        applyProtection(resp);
        done(resp && resp.blocked);
      }).catch(function(){ done(false); });
    } catch (e) { done(false); }
  }

  function captchaKey(){ return 'pm_captcha_' + domainKey; }

  function captchaPassed(){
    try {
      var raw = localStorage.getItem(captchaKey());
      if (!raw) return false;
      var data = JSON.parse(raw);
      return data && data.until && Date.now() < Number(data.until);
    } catch (e) { return false; }
  }

  function markCaptchaPassed(){
    try {
      localStorage.setItem(captchaKey(), JSON.stringify({ until: Date.now() + 86400000 }));
    } catch (e) {}
  }

  function hidePage(){
    try {
      if (document.getElementById('pm-block-overlay')) return;
      var overlay = document.createElement('div');
      overlay.id = 'pm-block-overlay';
      overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483646;background:#0d0d0d;color:#fff;display:flex;align-items:center;justify-content:center;font:16px/1.4 system-ui,sans-serif;text-align:center;padding:24px;';
      var name = brandName || 'Clickronix';
      var logoUrl = brandLogoUrl || '';
      var logo = logoUrl
        ? '<img src="'+String(logoUrl).replace(/"/g,'&quot;')+'" alt="'+String(name).replace(/"/g,'&quot;')+'" width="220" height="220" decoding="async" referrerpolicy="no-referrer" style="display:block;margin:0 auto 20px;max-width:min(240px,72vw);height:auto;">'
        : '';
      overlay.innerHTML = '<div>'+logo+'<p style="font-size:20px;font-weight:600;margin:0 0 8px;">Access restricted</p><p style="opacity:.75;margin:0;">This visit was blocked by '+String(name).replace(/</g,'&lt;')+' protection.</p></div>';
      (document.body || document.documentElement).appendChild(overlay);
      document.documentElement.style.overflow = 'hidden';
    } catch (e) {}
  }

  function blankPage(){
    try {
      if (document.getElementById('pm-block-overlay')) return;
      var overlay = document.createElement('div');
      overlay.id = 'pm-block-overlay';
      overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483646;background:#ffffff;';
      (document.body || document.documentElement).appendChild(overlay);
      document.documentElement.style.overflow = 'hidden';
    } catch (e) {}
  }

  function forbidPage(){
    try {
      if (document.getElementById('pm-block-overlay')) return;
      var overlay = document.createElement('div');
      overlay.id = 'pm-block-overlay';
      overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483646;background:#0d0d0d;color:#fff;display:flex;align-items:center;justify-content:center;font:16px/1.4 system-ui,sans-serif;text-align:center;padding:24px;';
      var name = brandName || 'Clickronix';
      var logoUrl = brandLogoUrl || '';
      var logo = logoUrl
        ? '<img src="'+String(logoUrl).replace(/"/g,'&quot;')+'" alt="'+String(name).replace(/"/g,'&quot;')+'" width="220" height="220" decoding="async" referrerpolicy="no-referrer" style="display:block;margin:0 auto 16px;max-width:min(240px,72vw);height:auto;">'
        : '';
      overlay.innerHTML = '<div>'+logo+'<p style="font-size:42px;font-weight:700;margin:0 0 8px;">403</p><p style="opacity:.75;margin:0;">Forbidden — blocked by '+String(name).replace(/</g,'&lt;')+' protection.</p></div>';
      (document.body || document.documentElement).appendChild(overlay);
      document.documentElement.style.overflow = 'hidden';
    } catch (e) {}
  }

  function applyBlockResponse(resp){
    var mode = String(resp.block_response || 'hide');
    if (mode === 'redirect' && resp.block_redirect_url) {
      try { window.location.replace(String(resp.block_redirect_url)); return; } catch (e) {}
    }
    if (mode === 'blank') { blankPage(); return; }
    if (mode === 'forbid') { forbidPage(); return; }
    if (mode === 'challenge') {
      if (!captchaPassed()) showCaptcha();
      return;
    }
    hidePage();
  }

  function showCaptcha(){
    if (captchaPassed() || document.getElementById('pm-captcha-overlay')) return;
    try {
      var a = Math.floor(Math.random() * 8) + 2;
      var b = Math.floor(Math.random() * 8) + 2;
      var overlay = document.createElement('div');
      overlay.id = 'pm-captcha-overlay';
      overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483645;background:rgba(13,13,13,.92);display:flex;align-items:center;justify-content:center;padding:24px;box-sizing:border-box;';
      overlay.innerHTML = '<div style="box-sizing:border-box;width:min(360px,100%);background:#1a1a1a;border:1px solid #FF6600;border-radius:12px;padding:20px;color:#fff;font:14px system-ui,sans-serif;"><p style="margin:0 0 12px;font-weight:600;">Verify you are human</p><p style="margin:0 0 12px;opacity:.8;">Solve: <strong>' + a + ' + ' + b + '</strong></p><input id="pm-captcha-input" type="text" inputmode="numeric" autocomplete="off" style="box-sizing:border-box;display:block;width:100%;height:40px;border-radius:6px;border:1px solid rgba(255,102,0,.55);background:#101010;color:#fff;padding:0 12px;margin:0 0 10px;outline:none;"><button id="pm-captcha-submit" type="button" style="box-sizing:border-box;display:block;width:100%;height:40px;border:0;border-radius:6px;background:#FF6600;color:#fff;font-weight:600;cursor:pointer;">Continue</button><p id="pm-captcha-error" style="display:none;color:#f87171;margin:10px 0 0;">Incorrect answer</p></div>';
      (document.body || document.documentElement).appendChild(overlay);
      var input = document.getElementById('pm-captcha-input');
      var btn = document.getElementById('pm-captcha-submit');
      var err = document.getElementById('pm-captcha-error');
      function submit(){
        if (String(input.value || '').trim() === String(a + b)) {
          markCaptchaPassed();
          overlay.remove();
        } else if (err) {
          err.style.display = 'block';
        }
      }
      btn.addEventListener('click', submit);
      input.addEventListener('keydown', function(e){ if (e.key === 'Enter') submit(); });
      if (input) input.focus();
    } catch (e) {}
  }

  function applyProtection(resp){
    if (!resp || typeof resp !== 'object') return;
    var firedAudience = false;
    if (resp.fire_audience_event) {
      firedAudience = fireInvalidAudienceEvent(resp);
    }
    if (resp.fire_exclude_event) {
      fireExcludeAudienceEvent(resp);
    }
    // Start recording before block/captcha returns — otherwise paid+blocked
    // traffic never attaches CTA listeners despite record_session:true.
    if (resp.record_session) {
      startSessionRecording(resp);
    }
    // Spec §13: never cancel the Google tag request with an immediate hide/redirect.
    // Audience signal must dispatch before website protection.
    if (resp.blocked) {
      if (firedAudience || resp.fire_audience_event) {
        setTimeout(function(){ applyBlockResponse(resp); }, 180);
      } else {
        applyBlockResponse(resp);
      }
      return;
    }
    if (resp.captcha_required && !captchaPassed()) {
      if (firedAudience || resp.fire_audience_event) {
        setTimeout(function(){ showCaptcha(); }, 180);
      } else {
        showCaptcha();
      }
    }
  }

  /** Spec §9: Clickronix → GA4 clickronix_exclude (GA4 client_id; never upload DEV_ to Ads Device IDs). */
  function fireExcludeAudienceEvent(resp){
    try {
      if (!resp || !resp.fire_exclude_event) return;
      if (consentRequired && !hasConsent()) return;
      var eventName = String(resp.exclude_event || 'clickronix_exclude');
      var reason = String(resp.exclude_reason || 'repeat_nonconverter');
      var conf = resp.device_confidence ? Math.round(Number(resp.device_confidence) * 100) : null;
      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push({
        event: eventName,
        reason: reason,
        device_confidence: conf,
        clickronix_source: 'exclusion_engine'
      });
      function pushGtag(){
        try {
          if (typeof gtag !== 'function') return;
          var payload = { reason: reason, engagement_time_msec: 1 };
          if (conf) payload.device_confidence = conf;
          var sendTo = String(resp.google_tag_id || '');
          if (sendTo) payload.send_to = sendTo;
          gtag('event', eventName, payload);
        } catch (e) {}
      }
      readGa4ClientId(resp).then(function(){ pushGtag(); }).catch(function(){ pushGtag(); });
    } catch (e) {}
  }

  function readGa4ClientId(resp){
    return new Promise(function(resolve){
      try {
        var ids = [];
        try {
          if (window.__gtag_measurement_ids && window.__gtag_measurement_ids.length) {
            ids = window.__gtag_measurement_ids.slice();
          }
        } catch (e0) {}
        if (resp && resp.google_tag_id) ids.unshift(String(resp.google_tag_id));
        var mid = null;
        for (var i = 0; i < ids.length; i++) {
          if (String(ids[i]).indexOf('G-') === 0) { mid = String(ids[i]); break; }
        }
        if (mid && typeof gtag === 'function') {
          var done = false;
          gtag('get', mid, 'client_id', function(cid){
            done = true;
            resolve(cid ? String(cid) : null);
          });
          setTimeout(function(){ if (!done) resolve(null); }, 1200);
          return;
        }
      } catch (e) {}
      resolve(null);
    });
  }

  /** Spec: cr_invalid_traffic + full §5 params; once per audience_id:decision_id; before block. */
  function fireInvalidAudienceEvent(resp){
    try {
      if (consentRequired && !hasConsent()) return false;
      if (!(resp.fire_audience_signal || resp.fire_audience_event)) return false;
      var eventName = String(resp.audience_event || 'cr_invalid_traffic');
      var verdict = String(resp.audience_traffic_verdict || resp.audience_traffic_status || resp.traffic_status || '').toLowerCase();
      var protectionAction = String(resp.audience_protection_action || '').toLowerCase();
      // Rule engine already decided fire; still reject empty suspicious-only without rule match payload.
      if (!resp.fire_audience_signal && verdict && verdict !== 'invalid' && protectionAction !== 'blocked') {
        return false;
      }

      var decisionId = String(resp.audience_decision_id || '');
      var audienceId = String(resp.audience_id || 'default');
      var mergeKey = 'cr_aud_params_' + audienceId;
      try {
        if (decisionId && window.sessionStorage) {
          var dedupeKey = 'cr_aud_sig_' + audienceId + ':' + decisionId;
          if (sessionStorage.getItem(dedupeKey) === '1') return false;
          sessionStorage.setItem(dedupeKey, '1');
        }
      } catch (eDedupe) {}

      var payload = {
        event: eventName,
        cr_event_version: String(resp.audience_event_version || '1.0'),
        cr_traffic_verdict: verdict || 'invalid',
        traffic_verdict: verdict || 'invalid',
        traffic_status: verdict || 'invalid',
        clickronix_source: 'protection_tag'
      };
      // Add-only merge: keep previously pushed membership params for this audience (never drop keys).
      try {
        if (window.sessionStorage) {
          var prevRaw = sessionStorage.getItem(mergeKey);
          if (prevRaw) {
            var prev = JSON.parse(prevRaw);
            if (prev && typeof prev === 'object') {
              Object.keys(prev).forEach(function(k){
                if (payload[k] == null && prev[k] != null) payload[k] = prev[k];
              });
            }
          }
        }
      } catch (eMerge) {}
      if (resp.audience_event_id) payload.cr_event_id = String(resp.audience_event_id);
      if (decisionId) payload.cr_decision_id = decisionId;
      if (protectionAction) payload.cr_protection_action = protectionAction;
      if (resp.audience_invalid_category) payload.cr_invalid_category = String(resp.audience_invalid_category);
      if (resp.audience_invalid_reason || resp.audience_detection_type) {
        payload.cr_invalid_reason = String(resp.audience_invalid_reason || resp.audience_detection_type);
      }
      if (resp.audience_risk_score != null && resp.audience_risk_score !== '') payload.cr_risk_score = Number(resp.audience_risk_score);
      if (resp.audience_action) payload.cr_action = String(resp.audience_action);
      if (resp.audience_outcome) payload.cr_outcome = String(resp.audience_outcome);
      if (resp.audience_lead_status) payload.cr_lead_status = String(resp.audience_lead_status);
      if (resp.audience_form_status) payload.cr_form_status = String(resp.audience_form_status);
      if (resp.audience_keyword_class) payload.cr_keyword_class = String(resp.audience_keyword_class);
      if (resp.audience_repeat_click_count != null && resp.audience_repeat_click_count !== '') {
        payload.cr_repeat_click_count = Number(resp.audience_repeat_click_count);
      }
      if (resp.audience_challenge_result) payload.cr_challenge_result = String(resp.audience_challenge_result);
      if (resp.audience_zip_status) payload.cr_zip_status = String(resp.audience_zip_status);
      if (resp.audience_campaign_id) payload.cr_campaign_id = String(resp.audience_campaign_id);
      if (resp.audience_occurred_at) payload.cr_occurred_at = String(resp.audience_occurred_at);
      if (resp.threat_group) payload.threat_group = String(resp.threat_group);
      try {
        if (window.sessionStorage) {
          var toStore = {};
          Object.keys(payload).forEach(function(k){
            if (k === 'event') return;
            if (payload[k] != null && payload[k] !== '') toStore[k] = payload[k];
          });
          sessionStorage.setItem(mergeKey, JSON.stringify(toStore));
        }
      } catch (eStore) {}

      window.dataLayer = window.dataLayer || [];
      window.dataLayer.push(payload);

      function pushGtag(clientId){
        try {
          if (typeof gtag !== 'function') return;
          var gtagPayload = {
            cr_traffic_verdict: payload.cr_traffic_verdict,
            traffic_verdict: payload.traffic_verdict,
            traffic_status: payload.traffic_status,
            engagement_time_msec: 1
          };
          ['cr_event_id','cr_decision_id','cr_protection_action','cr_invalid_category','cr_invalid_reason',
           'cr_risk_score','cr_action','cr_outcome','cr_lead_status','cr_form_status','cr_keyword_class',
           'cr_repeat_click_count','cr_challenge_result','cr_zip_status','cr_campaign_id','cr_occurred_at'
          ].forEach(function(k){ if (payload[k] != null) gtagPayload[k] = payload[k]; });
          if (clientId) gtagPayload.clickronix_client_id = String(clientId);
          var sendTo = String(resp.google_tag_id || '');
          if (sendTo) gtagPayload.send_to = sendTo;
          gtag('event', eventName, gtagPayload);
        } catch (e) {}
      }
      try {
        if (window.google_tag_manager || typeof gtag === 'function') {
          var ids = [];
          try {
            if (window.__gtag_measurement_ids && window.__gtag_measurement_ids.length) {
              ids = window.__gtag_measurement_ids.slice();
            }
          } catch (e2) {}
          if (resp.google_tag_id) {
            ids.unshift(String(resp.google_tag_id));
          }
          if (ids.length && typeof gtag === 'function') {
            var mid = ids[0];
            if (String(mid).indexOf('G-') === 0) {
              gtag('get', mid, 'client_id', function(cid){ pushGtag(cid); });
              return true;
            }
            pushGtag(null);
            return true;
          }
        }
      } catch (e3) {}
      pushGtag(null);
      return true;
    } catch (e) {
      return false;
    }
  }

  function startSessionRecording(meta){
    if (window.__pmRecording) return;
    window.__pmRecording = true;
    var events = [];
    var started = Date.now();
    var lastMove = 0;
    var duration = Number(meta.recording_ms || 60000);
    if (!isFinite(duration) || duration < 5000) duration = 60000;
    if (duration > 120000) duration = 120000;

    function push(type, payload){
      if (events.length >= 800) return;
      var row = {
        t: Date.now() - started,
        ts: Date.now(),
        type: type,
        session_id: sessionId(),
        visitor_id: visitorId()
      };
      if (payload && typeof payload === 'object') {
        for (var k in payload) {
          if (Object.prototype.hasOwnProperty.call(payload, k)) row[k] = payload[k];
        }
      }
      events.push(row);
    }

    push('meta', {
      vw: window.innerWidth || 0,
      vh: window.innerHeight || 0,
      url: String(location.href || ''),
      title: String(document.title || ''),
      referrer: String(document.referrer || '')
    });
    push('session_started', {
      page_url: String(location.href || '').slice(0, 500),
      path: String(location.pathname || '').slice(0, 500),
      referrer: String(document.referrer || '').slice(0, 500),
      vw: window.innerWidth || 0,
      vh: window.innerHeight || 0,
      session_id: sessionId(),
      visitor_id: visitorId()
    });
    try {
      var _qs = new URLSearchParams(String(location.search || ''));
      var _gclid = _qs.get('gclid') || _qs.get('gbraid') || _qs.get('wbraid');
      if (_gclid) {
        push('ad_click_detected', {
          gclid: _qs.get('gclid') || '',
          gbraid: _qs.get('gbraid') || '',
          wbraid: _qs.get('wbraid') || '',
          utm_source: _qs.get('utm_source') || '',
          utm_medium: _qs.get('utm_medium') || '',
          utm_campaign: _qs.get('utm_campaign') || '',
          page_url: String(location.href || '').slice(0, 500)
        });
      }
    } catch (adErr) {}

    var pageEnteredAt = Date.now();
    var lastPagePath = String(location.pathname || '');
    function pushTimeOnPage(nextPath){
      var spent = Math.max(0, Date.now() - pageEnteredAt);
      if (spent < 250) return;
      push('time_on_page', {
        path: lastPagePath,
        duration_ms: spent,
        duration_sec: Math.round(spent / 1000),
        next_path: nextPath || ''
      });
      pageEnteredAt = Date.now();
      lastPagePath = String(nextPath || location.pathname || '');
    }

    function onMove(e){
      var now = Date.now();
      if (now - lastMove < 80) return;
      lastMove = now;
      push('mousemove', { x: e.clientX, y: e.clientY });
    }

    var lastScrollAt = 0;
    var scrollMarks = { 25: false, 50: false, 75: false, 90: false, 100: false };
    function scrollDepthPct(){
      var doc = document.documentElement || document.body;
      var scrollTop = window.scrollY || doc.scrollTop || 0;
      var height = Math.max(1, (doc.scrollHeight || 0) - (window.innerHeight || 0));
      return Math.min(100, Math.round((scrollTop / height) * 100));
    }
    function onScroll(){
      var now = Date.now();
      if (now - lastScrollAt < 120) return;
      lastScrollAt = now;
      push('scroll', { x: window.scrollX || 0, y: window.scrollY || 0 });
      var depth = scrollDepthPct();
      [25, 50, 75, 90, 100].forEach(function(mark){
        if (depth >= mark && !scrollMarks[mark]) {
          scrollMarks[mark] = true;
          push('scroll', {
            depth: mark,
            page_url: String(location.href || '').slice(0, 500),
            path: String(location.pathname || '').slice(0, 500)
          });
        }
      });
    }

    function closestActionEl(el){
      // Prefer any ancestor that is a tel/callto/sms link (nested icon/span clicks).
      var node = el;
      while (node && node !== document && node !== document.documentElement) {
        if (node.getAttribute) {
          var telHref = readHref(node);
          if (isTelHref(telHref) || isTelDataAttr(node)) return node;
        }
        node = node.parentElement;
      }
      node = el;
      while (node && node !== document && node !== document.documentElement) {
        if (!node.tagName) { node = node.parentElement; continue; }
        var tag = String(node.tagName).toUpperCase();
        if (tag === 'A' || tag === 'BUTTON' || tag === 'INPUT' || (node.getAttribute && node.getAttribute('role') === 'button')) {
          return node;
        }
        node = node.parentElement;
      }
      return el;
    }

    function readHref(el){
      if (!el) return '';
      try {
        var attr = el.getAttribute && el.getAttribute('href');
        if (attr != null && String(attr).trim() !== '') return String(attr).trim();
      } catch (eAttr) {}
      try {
        var prop = el.href;
        if (prop == null) return '';
        // SVG <a href> exposes SVGAnimatedString, not a plain string.
        if (typeof prop === 'object' && prop.baseVal != null) return String(prop.baseVal || '').trim();
        return String(prop).trim();
      } catch (eProp) {}
      return '';
    }

    function isTelHref(href){
      var h = String(href || '').trim().toLowerCase();
      if (!h) return false;
      if (/^(tel|callto|sms):/i.test(h)) return true;
      // Rare builders inject scheme after quotes/spaces.
      if (/(?:^|[\\"\'\\s])(tel|callto|sms):\+?\d/i.test(h)) return true;
      return false;
    }

    function isTelDataAttr(el){
      if (!el || !el.getAttribute) return false;
      try {
        var keys = ['data-tel', 'data-phone', 'data-call', 'data-href', 'data-number', 'href'];
        for (var i = 0; i < keys.length; i++) {
          var v = el.getAttribute(keys[i]);
          if (v && isTelHref(String(v))) return true;
          if (v && keys[i] !== 'href' && looksLikePhoneNumber(String(v))) return true;
        }
      } catch (eData) {}
      return false;
    }

    function looksLikePhoneNumber(text){
      var t = String(text || '').replace(/[\\s().+-]/g, '');
      if (!t) return false;
      // 7–15 digits, optional leading country code marker already stripped.
      return /^\\d{7,15}$/.test(t);
    }

    function telNumberFromHref(href){
      return String(href || '').replace(/^(tel|callto|sms):\\/*/i, '').trim().slice(0, 64);
    }

    function elementText(el){
      try { return String(el.innerText || el.textContent || el.value || '').replace(/\\s+/g, ' ').trim().slice(0, 80).toLowerCase(); } catch (errT) { return ''; }
    }

    function isCallLabel(text){
      var t = String(text || '').toLowerCase();
      if (!t || t.length > 80) return false;
      if (/\\b(call\\s*(us|now|today|me|back)?|click\\s*to\\s*call|tap\\s*to\\s*call|phone\\s*(us|now|call)?|dial\\s*(us|now)?|talk\\s*to\\s*(an?\\s*)?(expert|agent|specialist|rep|us)|speak\\s*(to|with)\\s*(an?\\s*)?(expert|agent|specialist|rep|us)|request\\s*(a\\s*)?callback|schedule\\s*(a\\s*)?call)\\b/.test(t)) return true;
      // Bare phone number as the link/button label (common on ISP lead-gen sites).
      if (looksLikePhoneNumber(t)) return true;
      return false;
    }

    function isCallEl(el){
      if (!el || !el.tagName) return false;
      if (isTelDataAttr(el)) return true;
      if (el.getAttribute && (el.getAttribute('data-call') != null || el.getAttribute('data-phone') != null || /^(call|phone|tel)$/i.test(String(el.getAttribute('data-action') || '')))) return true;
      var cls = String(el.className || '').toLowerCase();
      var id = String(el.id || '').toLowerCase();
      if (/\\b(click[_-]?to[_-]?call|call[_-]?now|call[_-]?btn|call[_-]?button|phone[_-]?btn|phone[_-]?button|phone[_-]?number|tel[_-]?btn|tel[_-]?link|calltracker|callrail|whatconverts)\\b/.test(cls + ' ' + id)) return true;
      return isCallLabel(elementText(el));
    }

    function isCtaEl(el){
      if (!el || !el.tagName) return false;
      if (isCallEl(el)) return false;
      var tag = String(el.tagName).toUpperCase();
      if (el.getAttribute && (el.getAttribute('data-cta') != null || el.getAttribute('data-action') === 'cta')) return true;
      if (el.getAttribute && String(el.getAttribute('role') || '').toLowerCase() === 'button') return true;
      var cls = String(el.className || '').toLowerCase();
      var id = String(el.id || '').toLowerCase();
      var hay = cls + ' ' + id;
      if (/\\b(cta|call-to-action|btn-primary|button-primary|btn-cta|convert|signup|sign-up|buy-now|get-started|btn\\b|button\\b|wp-block-button|elementor-button|submit|hero-action|action-btn|primary-action)\\b/.test(hay)) {
        return true;
      }
      if (tag === 'BUTTON') return true;
      if (tag === 'INPUT') {
        var t = String(el.type || '').toLowerCase();
        if (t === 'submit' || t === 'button') return true;
      }
      var text = elementText(el);
      if (text && /\\b(get\\s*started|shop\\s*now|buy\\s*now|order\\s*now|order\\s*online|sign\\s*up|signup|subscribe|check\\s*availability|check\\s*avail|see\\s*(plans|pricing|offers)|view\\s*(plans|pricing|offers)|compare\\s*plans|request\\s*(a\\s*)?quote|get\\s*(a\\s*)?quote|apply\\s*now|learn\\s*more|contact\\s*us|continue|next\\s*step|submit|send|book\\s*now|schedule|claim\\s*(offer|deal)|find\\s*(a\\s*)?plan|choose\\s*(a\\s*)?plan|zip\\s*check|enter\\s*(your\\s*)?zip)\\b/.test(text)) {
        return true;
      }
      var href = '';
      try { href = String(el.href || (el.getAttribute && el.getAttribute('href')) || '').toLowerCase(); } catch (errH) { href = ''; }
      if (href && /(order|checkout|signup|sign-up|subscribe|quote|apply|contact|pricing|plans?|cart|buy|shop|get-started|availability|offer|promo|convert)/.test(href)) {
        if (tag === 'A' || tag === 'BUTTON' || tag === 'INPUT') return true;
      }
      if (tag === 'A' && /\\b(btn|button|cta)\\b/.test(hay)) return true;
      return false;
    }

    function commerceKind(el){
      if (!el || !el.tagName) return '';
      var attrs = '';
      try {
        attrs = [
          el.getAttribute && el.getAttribute('data-action'),
          el.getAttribute && el.getAttribute('data-event'),
          el.getAttribute && el.getAttribute('name'),
          el.id,
          el.className,
          el.innerText || el.textContent || ''
        ].join(' ').toLowerCase();
      } catch (err) { attrs = ''; }
      if (/add[_\\s-]?to[_\\s-]?cart|addtocart|data-add-to-cart/.test(attrs)) return 'add_to_cart';
      if (/\\b(checkout|begin[_\\s-]?checkout|proceed[_\\s-]?to[_\\s-]?checkout)\\b/.test(attrs)) return 'checkout';
      if (/\\b(purchase|place[_\\s-]?order|buy[_\\s-]?now|complete[_\\s-]?order)\\b/.test(attrs)) return 'purchase';
      return '';
    }

    function elementMeta(target){
      var href = readHref(target);
      if (!href && target && target.getAttribute) {
        try {
          href = String(target.getAttribute('data-tel') || target.getAttribute('data-phone') || target.getAttribute('data-href') || '').trim();
        } catch (eDataHref) { href = href || ''; }
      }
      var text = '';
      try {
        text = String((target && (target.innerText || target.textContent || target.value || '')) || '').replace(/\\s+/g, ' ').trim().slice(0, 120);
      } catch (err2) { text = ''; }
      var tag = (target && target.tagName) ? String(target.tagName).toUpperCase() : '';
      var linkType = tag === 'A' ? 'anchor' : (tag === 'BUTTON' ? 'button' : (tag === 'INPUT' ? 'input' : 'element'));
      return {
        href: href.slice(0, 500),
        element_text: text,
        text: text,
        element_id: String((target && target.id) || '').slice(0, 120),
        element_class: String((target && target.className) || '').slice(0, 200),
        id: String((target && target.id) || '').slice(0, 120),
        class: String((target && target.className) || '').slice(0, 200),
        tag: tag,
        link_type: linkType,
        page_url: String(location.href || '').slice(0, 500),
        path: String(location.pathname || '').slice(0, 500),
        title: String(document.title || '').slice(0, 255)
      };
    }

    function onClick(e){
      var target = closestActionEl(e.target);
      var meta = elementMeta(target);
      var tel = isTelHref(meta.href) || isTelDataAttr(target) || isCallEl(target);
      var commerce = !tel && commerceKind(target);
      var cta = !tel && !commerce && isCtaEl(target);

      if (tel) {
        push('phone_click', Object.assign({}, meta, {
          x: e.clientX,
          y: e.clientY,
          tel_number: telNumberFromHref(meta.href) || (looksLikePhoneNumber(meta.element_text) ? String(meta.element_text).replace(/[^\\d+]/g, '').slice(0, 64) : ''),
          link_type: 'tel'
        }));
        // Dialer may unload before pagehide — soft-flush now but keep listening
        // so a second tel click in the same session is still captured.
        softFlushRecording();
      } else if (commerce) {
        push(commerce, Object.assign({}, meta, {
          x: e.clientX,
          y: e.clientY,
          product_name: meta.element_text || undefined
        }));
        finishRecording();
      } else if (cta) {
        push('cta_click', Object.assign({}, meta, {
          x: e.clientX,
          y: e.clientY
        }));
        // CTA navigations often unload before pagehide — flush so the click is stored.
        finishRecording();
      } else if (meta.href && /^mailto:/i.test(meta.href)) {
        push('email_click', Object.assign({}, meta, {
          x: e.clientX,
          y: e.clientY,
          email: String(meta.href).replace(/^mailto:/i, '').split('?')[0].slice(0, 120),
          link_type: 'email'
        }));
      } else if (meta.href && isDownloadHref(meta.href)) {
        push('file_download', Object.assign({}, meta, { x: e.clientX, y: e.clientY, link_type: 'download' }));
      } else if (meta.tag === 'A' && meta.href && isExternalHref(meta.href)) {
        push('external_link', Object.assign({}, meta, { x: e.clientX, y: e.clientY, link_type: 'external' }));
      } else {
        push('click', {
          x: e.clientX,
          y: e.clientY,
          tag: meta.tag,
          href: meta.href,
          text: meta.element_text,
          class: meta.element_class,
          id: meta.element_id,
          page_url: meta.page_url
        });
      }

      if (target && meta.tag === 'A' && meta.href && !tel) {
        markPageSoon();
      }
    }

    function isExternalHref(href){
      try {
        var u = new URL(String(href || ''), location.href);
        return u.protocol.indexOf('http') === 0 && u.host && u.host !== location.host;
      } catch (e) { return false; }
    }
    function isDownloadHref(href){
      var h = String(href || '').toLowerCase().split('?')[0].split('#')[0];
      return /\\.(pdf|docx?|xlsx?|pptx?|zip|rar|csv|txt|ics)(\\s*$)/i.test(h)
        || /[?&]download=/.test(String(href || '').toLowerCase());
    }
    function isZipField(el){
      if (!el || !el.tagName) return false;
      var name = String(el.name || el.id || el.getAttribute('placeholder') || el.getAttribute('aria-label') || '').toLowerCase();
      var autocomplete = String(el.getAttribute('autocomplete') || '').toLowerCase();
      if (autocomplete === 'postal-code') return true;
      return /(^|[_-])(zip|zipcode|postal|postalcode|postcode)([_-]|$)/i.test(name)
        || /zip\\s*code|postal\\s*code|enter\\s*(your\\s*)?zip/i.test(name);
    }
    function isChatEl(el){
      if (!el || !el.tagName) return false;
      var hay = String((el.className || '') + ' ' + (el.id || '') + ' ' + (el.getAttribute && el.getAttribute('aria-label') || '')).toLowerCase();
      return /\\b(intercom|tidio|drift|hubspot|crisp|tawk|livechat|olark|zendesk|chat-widget|chat-button|open-chat|start-chat|chat-now)\\b/.test(hay);
    }
    function pageIntentFromPath(path, title){
      var p = String(path || '').toLowerCase();
      var t = String(title || '').toLowerCase();
      var hay = p + ' ' + t;
      if (/pricing|plans?|rates?|cost|quote/.test(hay)) return 'pricing_viewed';
      if (/provider|carrier|isp|fiber|cable|compare/.test(hay)) return 'provider_viewed';
      if (/availability|coverage|serviceable|check.?service/.test(hay)) return 'availability_viewed';
      return '';
    }

    function onZipBlur(e){
      var el = e.target;
      if (!isZipField(el)) return;
      var val = String(el.value || '').replace(/[^0-9A-Za-z\\-\\s]/g, '').trim().slice(0, 16);
      if (val.length < 3) return;
      push('zip_checked', {
        zip_code: val,
        form_id: formKey(el.form || null),
        page_url: String(location.href || '').slice(0, 500),
        path: String(location.pathname || '').slice(0, 500)
      });
    }
    var zipEntered = {};
    function onZipInput(e){
      var el = e.target;
      if (!isZipField(el)) return;
      var val = String(el.value || '').replace(/[^0-9A-Za-z\\-\\s]/g, '').trim().slice(0, 16);
      if (val.length < 3) return;
      var key = formKey(el.form || null) + '|' + String(el.name || el.id || 'zip');
      if (zipEntered[key] === val) return;
      zipEntered[key] = val;
      push('zip_entered', {
        zip_code: val,
        form_id: formKey(el.form || null),
        page_url: String(location.href || '').slice(0, 500),
        path: String(location.pathname || '').slice(0, 500)
      });
    }

    function onNavOrSearchClick(e){
      var el = e.target && e.target.closest
        ? e.target.closest('a, button, input, [role="button"], [aria-expanded], .menu-toggle, .hamburger, .navbar-toggler, [type="search"]')
        : null;
      if (!el) return;
      var hay = String((el.className || '') + ' ' + (el.id || '') + ' ' + (el.getAttribute && (el.getAttribute('aria-label') || '') || '')).toLowerCase();
      if (el.matches && (el.matches('.menu-toggle, .hamburger, .navbar-toggler, [aria-controls*="nav"], [aria-controls*="menu"]') || /menu-toggle|hamburger|navbar-toggler|nav-toggle|mobile-menu/.test(hay))) {
        push('navigation_menu_opened', {
          element_text: elementText(el).slice(0, 80),
          page_url: String(location.href || '').slice(0, 500),
          path: String(location.pathname || '').slice(0, 500)
        });
      }
      var type = String(el.type || '').toLowerCase();
      var role = String(el.getAttribute && el.getAttribute('role') || '').toLowerCase();
      if (type === 'search' || role === 'searchbox' || /\\b(search|search-btn|search-submit)\\b/.test(hay)) {
        push('search_used', {
          element_text: elementText(el).slice(0, 80),
          page_url: String(location.href || '').slice(0, 500),
          path: String(location.pathname || '').slice(0, 500)
        });
      }
    }

    function onProviderChange(e){
      var el = e.target;
      if (!el || !el.tagName) return;
      var tag = String(el.tagName).toUpperCase();
      if (tag !== 'SELECT' && tag !== 'INPUT') return;
      var name = String(el.name || el.id || '').toLowerCase();
      var val = String(el.value || '').trim().slice(0, 120);
      if (!val) return;
      if (!/(provider|carrier|isp|plan|company)/.test(name) && !(el.getAttribute && el.getAttribute('data-provider') != null)) return;
      push('provider_selected', {
        provider: val,
        field: name.slice(0, 80),
        page_url: String(location.href || '').slice(0, 500),
        path: String(location.pathname || '').slice(0, 500)
      });
    }

    function bindVideoTracking(){
      function attach(v){
        if (!v || v.__pmVideoBound) return;
        v.__pmVideoBound = true;
        v.addEventListener('play', function(){
          push('video_played', {
            src: String(v.currentSrc || v.src || '').slice(0, 500),
            page_url: String(location.href || '').slice(0, 500),
            path: String(location.pathname || '').slice(0, 500)
          });
        });
        v.addEventListener('ended', function(){
          push('video_completed', {
            src: String(v.currentSrc || v.src || '').slice(0, 500),
            page_url: String(location.href || '').slice(0, 500),
            path: String(location.pathname || '').slice(0, 500)
          });
        });
      }
      try {
        var vids = document.querySelectorAll('video');
        for (var i = 0; i < vids.length && i < 20; i++) attach(vids[i]);
      } catch (e) {}
    }

    var formsSeen = {};
    function observeFormsInView(){
      if (!('IntersectionObserver' in window)) {
        try {
          var forms = document.querySelectorAll('form');
          for (var i = 0; i < forms.length && i < 20; i++) markFormViewed(forms[i]);
        } catch (e) {}
        return;
      }
      try {
        var io = new IntersectionObserver(function(entries){
          entries.forEach(function(entry){
            if (!entry.isIntersecting) return;
            markFormViewed(entry.target);
            try { io.unobserve(entry.target); } catch (e2) {}
          });
        }, { threshold: 0.35 });
        var list = document.querySelectorAll('form');
        for (var j = 0; j < list.length && j < 30; j++) io.observe(list[j]);
      } catch (err) {}
    }
    function markFormViewed(form){
      if (!form) return;
      var key = formKey(form);
      if (formsSeen[key]) return;
      formsSeen[key] = true;
      push('form_view', {
        form_id: key,
        form_name: formName(form),
        page_url: String(location.href || '').slice(0, 500),
        path: String(location.pathname || '').slice(0, 500)
      });
    }

    function onClickCaptureExtras(e){
      var target = e.target;
      if (!target) return;
      // Chat widgets often use nested buttons without <a>.
      var node = target;
      for (var depth = 0; node && depth < 5; depth++) {
        if (isChatEl(node)) {
          push('chat_opened', {
            element_text: elementText(node).slice(0, 80),
            element_id: String(node.id || '').slice(0, 120),
            element_class: String(node.className || '').slice(0, 200),
            page_url: String(location.href || '').slice(0, 500),
            path: String(location.pathname || '').slice(0, 500)
          });
          return;
        }
        node = node.parentElement;
      }
    }

    var formStarted = {};
    var fieldsFocused = {};
    function formKey(el){
      if (!el) return 'form';
      return String(el.id || el.getAttribute('name') || el.getAttribute('action') || 'form').slice(0, 120);
    }
    function formName(el){
      if (!el) return '';
      return String(el.getAttribute('name') || el.getAttribute('aria-label') || el.id || '').slice(0, 120);
    }
    function onFormFocus(e){
      var el = e.target;
      if (!el || !el.tagName) return;
      var tag = String(el.tagName).toLowerCase();
      if (tag !== 'input' && tag !== 'textarea' && tag !== 'select') return;
      if (isSensitiveInput(el)) return;
      var form = el.form || (el.closest && el.closest('form'));
      if (!form) return;
      var fieldKey = formKey(form) + '|' + String(el.name || el.id || tag);
      if (!fieldsFocused[fieldKey]) {
        fieldsFocused[fieldKey] = true;
        push('form_field_focused', {
          form_id: formKey(form),
          field_name: String(el.name || el.id || '').slice(0, 120),
          page_url: String(location.href || '').slice(0, 500),
          path: String(location.pathname || '').slice(0, 500)
        });
      }
      var key = formKey(form);
      if (formStarted[key]) return;
      formStarted[key] = true;
      push('form_start', {
        form_id: key,
        form_name: formName(form),
        page_url: String(location.href || '').slice(0, 500),
        path: String(location.pathname || '').slice(0, 500),
        title: String(document.title || '').slice(0, 255)
      });
    }
    function onFormInvalid(e){
      var el = e.target;
      if (!el) return;
      var form = el.form || (el.closest && el.closest('form'));
      if (!form) return;
      form.__pmInvalid = true;
      push('form_validation_failed', {
        form_id: formKey(form),
        field_name: String(el.name || el.id || '').slice(0, 120),
        page_url: String(location.href || '').slice(0, 500),
        path: String(location.pathname || '').slice(0, 500)
      });
    }
    function onFormSubmit(e){
      var form = e.target;
      if (!form || String(form.tagName || '').toUpperCase() !== 'FORM') return;
      var valid = true;
      try {
        if (typeof form.checkValidity === 'function') valid = !!form.checkValidity();
      } catch (err) { valid = true; }
      if (form.__pmInvalid) valid = false;
      form.__pmInvalid = false;
      var payload = {
        form_id: formKey(form),
        form_name: formName(form),
        page_url: String(location.href || '').slice(0, 500),
        path: String(location.pathname || '').slice(0, 500),
        title: String(document.title || '').slice(0, 255),
        success: valid ? 1 : 0,
        status: valid ? 'success' : 'failed'
      };
      push('form_submit', payload);
      if (!valid) {
        push('form_submit_failed', payload);
      }
      // Form posts often navigate away — flush so submit is not lost.
      finishRecording();
    }

    function pushCommerce(type, detail){
      var row = {
        page_url: String(location.href || '').slice(0, 500),
        path: String(location.pathname || '').slice(0, 500),
        title: String(document.title || '').slice(0, 255)
      };
      if (detail && typeof detail === 'object') {
        ['product_id', 'product_name', 'sku', 'order_id', 'value', 'revenue', 'currency'].forEach(function(k){
          if (detail[k] != null) row[k] = String(detail[k]).slice(0, 120);
        });
        if (detail.items && detail.items[0]) {
          var item = detail.items[0];
          if (!row.product_id && item.item_id) row.product_id = String(item.item_id).slice(0, 120);
          if (!row.product_name && item.item_name) row.product_name = String(item.item_name).slice(0, 120);
        }
      }
      push(type, row);
    }
    function onDataLayerPush(){
      try {
        if (!window.dataLayer || !window.dataLayer.length) return;
        var last = window.dataLayer[window.dataLayer.length - 1];
        if (!last || typeof last !== 'object') return;
        var ev = String(last.event || last.eventName || '').toLowerCase();
        if (ev === 'add_to_cart' || ev === 'add-to-cart') pushCommerce('add_to_cart', last);
        else if (ev === 'begin_checkout' || ev === 'checkout') pushCommerce('checkout', last);
        else if (ev === 'purchase' || ev === 'sale') pushCommerce('purchase', {
          order_id: last.transaction_id || last.order_id,
          revenue: last.value || last.revenue,
          currency: last.currency
        });
      } catch (err) {}
    }
    if (window.dataLayer && Array.isArray(window.dataLayer)) {
      var _dlPush = window.dataLayer.push;
      window.dataLayer.push = function(){
        var r = _dlPush.apply(window.dataLayer, arguments);
        onDataLayerPush();
        return r;
      };
    }

    function readMetaKeywords(){
      try {
        var el = document.querySelector('meta[name="keywords"], meta[name="keyword"]');
        return el ? String(el.getAttribute('content') || '').slice(0, 500) : '';
      } catch (err) { return ''; }
    }

    var seenPages = {};
    var firstPageMarked = false;
    function markPage(){
      var u = String(location.href || '').slice(0, 500);
      if (!u || seenPages[u]) return;
      seenPages[u] = true;
      var pagePayload = {
        url: u,
        page_url: u,
        path: String(location.pathname || '').slice(0, 500),
        title: String(document.title || '').slice(0, 255),
        headline: String(document.title || '').slice(0, 255),
        meta_keywords: readMetaKeywords(),
        referrer: String(document.referrer || '').slice(0, 500)
      };
      if (!firstPageMarked) {
        firstPageMarked = true;
        push('page_view', pagePayload);
        push('landing_page_viewed', pagePayload);
      } else {
        pushTimeOnPage(pagePayload.path);
        push('page_change', pagePayload);
        push('next_page_viewed', pagePayload);
      }
      var intent = pageIntentFromPath(pagePayload.path, pagePayload.title);
      if (intent) {
        push(intent, {
          page_url: pagePayload.page_url,
          path: pagePayload.path,
          title: pagePayload.title
        });
      }
      observeFormsInView();
      bindVideoTracking();
    }
    var pageTimer = null;
    function markPageSoon(){
      if (pageTimer) clearTimeout(pageTimer);
      pageTimer = setTimeout(markPage, 400);
    }
    markPage();

    var _pushState = history.pushState;
    var _replaceState = history.replaceState;
    try {
      history.pushState = function(){
        var r = _pushState.apply(history, arguments);
        markPageSoon();
        return r;
      };
      history.replaceState = function(){
        var r = _replaceState.apply(history, arguments);
        markPageSoon();
        return r;
      };
    } catch (histErr) {}

    function isSensitiveInput(el){
      if (!el || !el.tagName) return false;
      var tag = String(el.tagName).toLowerCase();
      if (tag !== 'input' && tag !== 'textarea') return false;
      var type = String(el.type || '').toLowerCase();
      if (type === 'password') return true;
      var name = String(el.name || el.id || '').toLowerCase();
      return /password|passwd|secret|cvv|cvc|ssn|credit/.test(name);
    }

    function onInput(e){
      if (!maskPasswords) return;
      var el = e.target;
      if (!isSensitiveInput(el)) return;
      push('input_masked', {
        tag: String(el.tagName || ''),
        masked: true
      });
    }

    document.addEventListener('mousemove', onMove, { passive: true });
    window.addEventListener('scroll', onScroll, { passive: true });
    document.addEventListener('click', onClick, true);
    document.addEventListener('click', onClickCaptureExtras, true);
    document.addEventListener('click', onNavOrSearchClick, true);
    document.addEventListener('input', onInput, true);
    document.addEventListener('input', onZipInput, true);
    document.addEventListener('change', onProviderChange, true);
    document.addEventListener('focusin', onFormFocus, true);
    document.addEventListener('focusout', onZipBlur, true);
    document.addEventListener('invalid', onFormInvalid, true);
    document.addEventListener('submit', onFormSubmit, true);
    window.addEventListener('popstate', markPageSoon);
    window.addEventListener('hashchange', markPageSoon);
    observeFormsInView();
    bindVideoTracking();

    // Public API for site custom events (no plugin update required).
    window.__pmRecordingPush = function(name, data){
      var type = String(name || 'custom_event').toLowerCase().replace(/\\s+/g, '_').slice(0, 40);
      var row = data && typeof data === 'object' ? data : {};
      push(type, row);
    };
    try {
      var queued = window.__pmEventQueue || [];
      window.__pmEventQueue = [];
      for (var qi = 0; qi < queued.length; qi++) {
        var qe = queued[qi];
        if (!qe) continue;
        window.__pmRecordingPush(qe.name || qe.event || 'custom_event', qe.data || qe);
      }
    } catch (queueErr) {}

    var sent = false;
    var recordingTimer = null;
    function finishRecordingOnHide(){
      if (document.visibilityState === 'hidden') finishRecording();
    }
    function buildRecordingBody(){
      return JSON.stringify({
        domainKey: domainKey,
        session_id: sessionId(),
        visitor_id: visitorId(),
        visit_id: meta.visit_id || null,
        page_url: String(location.href || ''),
        duration_ms: Date.now() - started,
        threat_group: meta.threat_group || null,
        events: events
      });
    }
    function postRecordingBody(body){
      if (navigator.sendBeacon) {
        try {
          var blob = new Blob([body], { type: 'application/json' });
          if (navigator.sendBeacon(sessionRecordingUrl, blob)) return true;
        } catch (eBeacon) {}
      }
      try {
        fetch(sessionRecordingUrl, {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: body,
          mode: 'cors',
          credentials: 'omit',
          keepalive: true
        });
        return true;
      } catch (eFetch) {
        return false;
      }
    }
    /** Beacon without stopping listeners — keeps multi tel/CTA clicks in one session. */
    function softFlushRecording(){
      try {
        postRecordingBody(buildRecordingBody());
      } catch (eSoft) {}
    }
    function finishRecording(){
      if (sent) return;
      sent = true;
      if (recordingTimer) clearTimeout(recordingTimer);
      try { pushTimeOnPage(''); } catch (tErr) {}
      push('session_exit', {
        page_url: String(location.href || '').slice(0, 500),
        path: String(location.pathname || '').slice(0, 500),
        title: String(document.title || '').slice(0, 255),
        url: String(location.href || '').slice(0, 500)
      });
      document.removeEventListener('mousemove', onMove);
      window.removeEventListener('scroll', onScroll);
      document.removeEventListener('click', onClick, true);
      document.removeEventListener('click', onClickCaptureExtras, true);
      document.removeEventListener('click', onNavOrSearchClick, true);
      document.removeEventListener('input', onInput, true);
      document.removeEventListener('input', onZipInput, true);
      document.removeEventListener('change', onProviderChange, true);
      document.removeEventListener('focusin', onFormFocus, true);
      document.removeEventListener('focusout', onZipBlur, true);
      document.removeEventListener('invalid', onFormInvalid, true);
      document.removeEventListener('submit', onFormSubmit, true);
      window.removeEventListener('popstate', markPageSoon);
      window.removeEventListener('hashchange', markPageSoon);
      window.removeEventListener('pagehide', finishRecording);
      document.removeEventListener('visibilitychange', finishRecordingOnHide);
      try {
        history.pushState = _pushState;
        history.replaceState = _replaceState;
      } catch (histRestoreErr) {}
      window.__pmRecordingPush = null;
      window.__pmRecording = false;
      try {
        postRecordingBody(buildRecordingBody());
      } catch (e) {}
    }

    // CTA links frequently navigate before the recording timeout. Flush the
    // captured click on page exit instead of losing it with the old page.
    window.addEventListener('pagehide', finishRecording);
    document.addEventListener('visibilitychange', finishRecordingOnHide);
    recordingTimer = setTimeout(finishRecording, duration);
  }

  function send(payload, done){
    try {
      fetch(collectUrl, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload),
        mode: 'cors',
        credentials: 'omit',
        keepalive: true
      }).then(function(r){ return r.json(); }).then(function(resp){
        persistDeviceFromResponse(resp);
        applyProtection(resp);
        if (done) done(resp);
      }).catch(function(){
        pixel(payload);
      });
      return;
    } catch (e) {}
    pixel(payload);
  }

  function sessionId(){
    var key = 'pm_sid_' + domainKey;
    try {
      var existing = localStorage.getItem(key);
      if (existing) return existing;
      var id = 's_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
      localStorage.setItem(key, id);
      return id;
    } catch (e) {
      return 's_' + Date.now();
    }
  }

  function visitorId(){
    var key = 'pm_vid_' + domainKey;
    try {
      var existing = localStorage.getItem(key);
      if (existing) return existing;
      var id = 'v_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 10);
      localStorage.setItem(key, id);
      return id;
    } catch (e) {
      return 'v_' + Date.now();
    }
  }

  /** Persistent Clickronix device token (cookie + localStorage). Not the fingerprint. */
  function deviceToken(){
    var lsKey = 'cx_did_' + domainKey;
    var cookieName = 'cx_did';
    function readCookie(name){
      try {
        var parts = String(document.cookie || '').split(';');
        for (var i = 0; i < parts.length; i++) {
          var p = parts[i].replace(/^\s+/, '');
          if (p.indexOf(name + '=') === 0) {
            return decodeURIComponent(p.slice(name.length + 1));
          }
        }
      } catch (e) {}
      return null;
    }
    function writeCookie(name, value){
      try {
        var maxAge = 60 * 60 * 24 * 400;
        document.cookie = name + '=' + encodeURIComponent(value) + '; path=/; max-age=' + maxAge + '; SameSite=Lax';
      } catch (e) {}
    }
    try {
      var existing = localStorage.getItem(lsKey) || readCookie(cookieName);
      if (existing && String(existing).length >= 8) {
        localStorage.setItem(lsKey, existing);
        writeCookie(cookieName, existing);
        return String(existing);
      }
      var id = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : ('d_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2, 12));
      localStorage.setItem(lsKey, id);
      writeCookie(cookieName, id);
      return id;
    } catch (e) {
      return 'd_' + Date.now();
    }
  }

  function persistDeviceFromResponse(resp){
    try {
      if (!resp) return;
      if (resp.device_token) {
        var lsKey = 'cx_did_' + domainKey;
        localStorage.setItem(lsKey, String(resp.device_token));
        document.cookie = 'cx_did=' + encodeURIComponent(String(resp.device_token)) + '; path=/; max-age=' + (60*60*24*400) + '; SameSite=Lax';
      }
    } catch (e) {}
  }

  // Stable browser fingerprint (NOT cookies). Device ID on server hashes this.
  // Spec signals: browser/OS/device, UA-CH, screen, touch, CPU/RAM, WebGL, canvas, locale, pointer, API profile.
  function hashString(raw){
    var hash = 0;
    for (var i = 0; i < raw.length; i++) {
      hash = ((hash << 5) - hash) + raw.charCodeAt(i);
      hash |= 0;
    }
    return Math.abs(hash).toString(36);
  }

  function browserFamilyFromUa(ua){
    ua = String(ua || '').toLowerCase();
    if (ua.indexOf('edg/') !== -1 || ua.indexOf('edge/') !== -1) return 'Edge';
    if (ua.indexOf('opr/') !== -1 || ua.indexOf('opera') !== -1) return 'Opera';
    if (ua.indexOf('firefox') !== -1 || ua.indexOf('fxios') !== -1) return 'Firefox';
    if (ua.indexOf('crios') !== -1 || (ua.indexOf('chrome') !== -1 && ua.indexOf('chromium') === -1)) return 'Chrome';
    if (ua.indexOf('safari') !== -1) return 'Safari';
    if (ua.indexOf('samsung') !== -1) return 'Samsung';
    return 'Other';
  }

  function osFamilyFromUa(ua){
    ua = String(ua || '').toLowerCase();
    if (ua.indexOf('iphone') !== -1 || ua.indexOf('ipad') !== -1 || ua.indexOf('ipod') !== -1) return 'iOS';
    if (ua.indexOf('android') !== -1) return 'Android';
    if (ua.indexOf('windows') !== -1) return 'Windows';
    if (ua.indexOf('mac os') !== -1 || ua.indexOf('macintosh') !== -1) return 'macOS';
    if (ua.indexOf('cros') !== -1) return 'Chrome OS';
    if (ua.indexOf('linux') !== -1) return 'Linux';
    return 'Other';
  }

  function osVersionFromUa(ua){
    ua = String(ua || '');
    var m;
    if ((m = ua.match(/Android (\d+(?:\.\d+)?)/))) return 'Android ' + m[1];
    if ((m = ua.match(/CPU (?:iPhone )?OS (\d+[_\.]\d+(?:[_\.]\d+)?)/))) return 'iOS ' + String(m[1]).replace(/_/g, '.');
    if ((m = ua.match(/Mac OS X (\d+[_\.]\d+(?:[_\.]\d+)?)/))) return 'macOS ' + String(m[1]).replace(/_/g, '.');
    if (/Windows NT 10\.0/.test(ua)) return 'Windows 10+';
    if (/Windows NT 6\.3/.test(ua)) return 'Windows 8.1';
    if (/Windows NT 6\.1/.test(ua)) return 'Windows 7';
    if (/CrOS/.test(ua)) return 'Chrome OS';
    return '';
  }

  function browserMajorFromUa(ua){
    ua = String(ua || '');
    var m = ua.match(/(?:Edg|OPR|Firefox|FxiOS|CriOS|Chrome|SamsungBrowser|Version)\/(\d+)/);
    return m ? m[1] : '';
  }

  function deviceTypeFromSignals(ua, touchPoints){
    ua = String(ua || '').toLowerCase();
    if (ua.indexOf('ipad') !== -1 || (ua.indexOf('android') !== -1 && ua.indexOf('mobile') === -1) || ua.indexOf('tablet') !== -1) return 'Tablet';
    if (ua.indexOf('mobi') !== -1 || ua.indexOf('iphone') !== -1 || ua.indexOf('ipod') !== -1 || (touchPoints > 0 && ua.indexOf('windows') === -1 && ua.indexOf('macintosh') === -1)) return 'Mobile';
    return 'Desktop';
  }

  function pointerType(){
    try {
      if (window.matchMedia) {
        var coarse = matchMedia('(pointer: coarse)').matches;
        var fine = matchMedia('(pointer: fine)').matches;
        var anyCoarse = matchMedia('(any-pointer: coarse)').matches;
        if (coarse && !fine) return 'coarse';
        if (fine && anyCoarse) return 'fine+touch';
        if (fine) return 'fine';
      }
    } catch (e) {}
    return Number(navigator.maxTouchPoints || 0) > 0 ? 'coarse' : 'fine';
  }

  function webglInfo(){
    var out = { vendor: '', renderer: '', hash: 'wgl_none', gl: null };
    try {
      var canvas = document.createElement('canvas');
      var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
      if (!gl) return out;
      out.gl = gl;
      var ext = gl.getExtension('WEBGL_debug_renderer_info');
      if (ext) {
        out.vendor = String(gl.getParameter(ext.UNMASKED_VENDOR_WEBGL) || '');
        out.renderer = String(gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) || '');
      } else {
        out.vendor = String(gl.getParameter(gl.VENDOR) || '');
        out.renderer = String(gl.getParameter(gl.RENDERER) || '');
      }
      var bits = [
        gl.getParameter(gl.MAX_TEXTURE_SIZE),
        gl.getParameter(gl.MAX_RENDERBUFFER_SIZE),
        gl.getParameter(gl.MAX_VERTEX_ATTRIBS),
        gl.getParameter(gl.MAX_VERTEX_UNIFORM_VECTORS),
        gl.getParameter(gl.MAX_FRAGMENT_UNIFORM_VECTORS),
        gl.getParameter(gl.MAX_VARYING_VECTORS),
        String(gl.getParameter(gl.MAX_VIEWPORT_DIMS) || ''),
        String(gl.getParameter(gl.ALIASED_LINE_WIDTH_RANGE) || ''),
        String(gl.getParameter(gl.ALIASED_POINT_SIZE_RANGE) || ''),
        String(gl.getParameter(gl.SHADING_LANGUAGE_VERSION) || ''),
        String(gl.getParameter(gl.VERSION) || ''),
        ((gl.getSupportedExtensions() || []).slice().sort().join(','))
      ];
      out.hash = 'wgl_' + hashString(bits.join('|'));
    } catch (e) {}
    return out;
  }

  function canvasHash(){
    try {
      var canvas = document.createElement('canvas');
      canvas.width = 240;
      canvas.height = 60;
      var ctx = canvas.getContext('2d');
      if (!ctx) return 'cnv_none';
      ctx.textBaseline = 'top';
      ctx.font = '14px Arial';
      ctx.fillStyle = '#f60';
      ctx.fillRect(10, 8, 80, 30);
      ctx.fillStyle = '#069';
      ctx.fillText('PromotixFP', 12, 12);
      ctx.fillStyle = 'rgba(102,204,0,0.7)';
      ctx.fillText('PromotixFP', 14, 16);
      ctx.beginPath();
      ctx.arc(180, 28, 16, 0, Math.PI * 2);
      ctx.closePath();
      ctx.fill();
      var data = '';
      try { data = canvas.toDataURL(); } catch (e) { data = 'blocked'; }
      return 'cnv_' + hashString(data + ':' + String(data.length));
    } catch (e) {
      return 'cnv_err';
    }
  }

  function featureApiProfile(){
    var n = navigator;
    var flags = [
      'wgl2=' + (typeof WebGL2RenderingContext !== 'undefined' ? 1 : 0),
      'gpu=' + (n.gpu ? 1 : 0),
      'sw=' + ('serviceWorker' in n ? 1 : 0),
      'notif=' + ('Notification' in window ? 1 : 0),
      'bt=' + (n.bluetooth ? 1 : 0),
      'usb=' + (n.usb ? 1 : 0),
      'hid=' + (n.hid ? 1 : 0),
      'serial=' + (n.serial ? 1 : 0),
      'xr=' + (n.xr ? 1 : 0),
      'md=' + (n.mediaDevices && n.mediaDevices.getUserMedia ? 1 : 0),
      'rtc=' + ('RTCPeerConnection' in window ? 1 : 0),
      'ac=' + ((window.AudioContext || window.webkitAudioContext) ? 1 : 0),
      'wasm=' + (window.WebAssembly ? 1 : 0),
      'offc=' + ('OffscreenCanvas' in window ? 1 : 0),
      'sab=' + (typeof SharedArrayBuffer !== 'undefined' ? 1 : 0),
      'pay=' + ('PaymentRequest' in window ? 1 : 0),
      'cred=' + (n.credentials ? 1 : 0),
      'wake=' + (n.wakeLock ? 1 : 0),
      'share=' + (typeof n.share === 'function' ? 1 : 0),
      'storage=' + (n.storage ? 1 : 0)
    ];
    return 'cap_' + hashString(flags.join('|'));
  }

  function clientHintsSync(){
    var uaData = navigator.userAgentData;
    var out = {
      platform: uaData && uaData.platform ? String(uaData.platform) : String(navigator.platform || ''),
      mobile: uaData ? (uaData.mobile ? 1 : 0) : null,
      brands: '',
      architecture: '',
      bitness: '',
      model: '',
      platformVersion: '',
      fullVersionList: '',
      uaFullVersion: ''
    };
    if (uaData && uaData.brands && uaData.brands.length) {
      out.brands = uaData.brands.map(function(b){ return String(b.brand || '') + '/' + String(b.version || ''); }).join(', ');
    }
    return out;
  }

  function clientHintsAsync(){
    var low = clientHintsSync();
    var uaData = navigator.userAgentData;
    if (!uaData || typeof uaData.getHighEntropyValues !== 'function') {
      return Promise.resolve(low);
    }
    var high = uaData.getHighEntropyValues([
      'architecture', 'bitness', 'model', 'platformVersion', 'fullVersionList', 'uaFullVersion', 'wow64'
    ]).then(function(h){
      return Object.assign({}, low, {
        architecture: String(h.architecture || ''),
        bitness: String(h.bitness || ''),
        model: String(h.model || ''),
        platformVersion: String(h.platformVersion || ''),
        fullVersionList: (h.fullVersionList || []).map(function(b){ return String(b.brand || '') + '/' + String(b.version || ''); }).join(', '),
        uaFullVersion: String(h.uaFullVersion || '')
      });
    }).catch(function(){ return low; });

    return Promise.race([
      high,
      new Promise(function(resolve){ setTimeout(function(){ resolve(low); }, 220); })
    ]);
  }

  function brandFromHints(hints){
    var list = String((hints && (hints.fullVersionList || hints.brands)) || '').toLowerCase();
    if (list.indexOf('edge') !== -1) return 'Edge';
    if (list.indexOf('opera') !== -1) return 'Opera';
    if (list.indexOf('firefox') !== -1) return 'Firefox';
    if (list.indexOf('chrome') !== -1) return 'Chrome';
    if (list.indexOf('safari') !== -1) return 'Safari';
    if (list.indexOf('chromium') !== -1) return 'Chromium';
    return '';
  }

  function majorFromHints(hints){
    var list = (hints && hints.fullVersionList) ? hints.fullVersionList : (hints && hints.brands ? hints.brands : '');
    var parts = String(list).split(',');
    for (var i = 0; i < parts.length; i++) {
      var piece = String(parts[i] || '').trim();
      if (!piece || /not.?a.?brand/i.test(piece) || /brand/i.test(piece) && /not/i.test(piece)) continue;
      var ver = piece.split('/')[1] || '';
      var major = String(ver).split('.')[0];
      if (major) return major;
    }
    if (hints && hints.uaFullVersion) return String(hints.uaFullVersion).split('.')[0];
    return '';
  }

  function clientHintsLabel(hints, osFamily, deviceType){
    var platform = (hints && hints.platform) ? String(hints.platform) : osFamily;
    var brand = brandFromHints(hints) || 'Chromium';
    var form = (hints && hints.mobile === 1) || deviceType === 'Mobile' ? 'Mobile' : (deviceType === 'Tablet' ? 'Tablet' : 'Desktop');
    return [platform, brand, form].filter(Boolean).join(' / ');
  }

  function buildFingerprintSignals(hints){
    var ua = String(navigator.userAgent || '');
    var touchPoints = Number(navigator.maxTouchPoints || 0);
    var gl = webglInfo();
    var pr = Number(window.devicePixelRatio || 1);
    var osFamily = osFamilyFromUa(ua);
    var deviceType = deviceTypeFromSignals(ua, touchPoints);
    var osVersion = '';
    if (hints && hints.platformVersion) {
      var plat = String(hints.platform || osFamily || '').trim();
      osVersion = (plat ? plat + ' ' : '') + String(hints.platformVersion);
    }
    if (!osVersion) osVersion = osVersionFromUa(ua);
    var major = majorFromHints(hints) || browserMajorFromUa(ua);
    var family = brandFromHints(hints) || browserFamilyFromUa(ua);
    var mem = navigator.deviceMemory ? (String(navigator.deviceMemory) + ' GB') : '0';
    var cores = navigator.hardwareConcurrency ? (String(navigator.hardwareConcurrency) + ' cores') : '0';
    return {
      browser_family: family,
      browser_major: major,
      user_agent: ua.slice(0, 220),
      client_hints: clientHintsLabel(hints, osFamily, deviceType),
      os_family: osFamily,
      os_version: osVersion,
      device_type: deviceType,
      screen_size: String(screen.width || 0) + ' x ' + String(screen.height || 0),
      pixel_ratio: String(pr),
      touch_points: String(touchPoints),
      hardware_concurrency: cores,
      device_memory: mem,
      webgl_vendor: gl.vendor,
      webgl_renderer: gl.renderer,
      webgl_hash: gl.hash,
      canvas_hash: canvasHash(),
      language: String(navigator.language || (navigator.languages && navigator.languages[0]) || ''),
      timezone: String(((Intl.DateTimeFormat().resolvedOptions() || {}).timeZone) || ''),
      pointer_type: pointerType(),
      api_profile: featureApiProfile()
    };
  }

  function fingerprintIdFromSignals(signals){
    var keys = [
      'browser_family','browser_major','client_hints','os_family','os_version','device_type',
      'screen_size','pixel_ratio','touch_points','hardware_concurrency','device_memory',
      'webgl_vendor','webgl_renderer','webgl_hash','canvas_hash','language','timezone',
      'pointer_type','api_profile','user_agent'
    ];
    var parts = [];
    for (var i = 0; i < keys.length; i++) {
      parts.push(keys[i] + '=' + String(signals[keys[i]] || ''));
    }
    var raw = parts.join('|');
    return 'cfp3_' + hashString(raw) + '_' + String(raw.length);
  }

  function collectDeviceFingerprint(){
    var key = 'pm_fp_v3_' + domainKey;
    try {
      var cached = localStorage.getItem(key);
      if (cached) {
        var parsed = JSON.parse(cached);
        if (parsed && parsed.id && parsed.signals) return Promise.resolve(parsed);
      }
    } catch (e) {}

    return clientHintsAsync().then(function(hints){
      var signals = buildFingerprintSignals(hints);
      var result = { id: fingerprintIdFromSignals(signals), signals: signals };
      try { localStorage.setItem(key, JSON.stringify(result)); } catch (e) {}
      return result;
    });
  }

  function deviceFingerprint(){
    try {
      var cached = localStorage.getItem('pm_fp_v3_' + domainKey);
      if (cached) {
        var parsed = JSON.parse(cached);
        if (parsed && parsed.id) return parsed.id;
      }
    } catch (e) {}
    var signals = buildFingerprintSignals(clientHintsSync());
    return fingerprintIdFromSignals(signals);
  }

  function storedAttribution(key, value){
    var storageKey = 'pm_' + key + '_' + domainKey;
    try {
      if (value) {
        localStorage.setItem(storageKey, value);
        return value;
      }
      return localStorage.getItem(storageKey) || null;
    } catch (e) {
      return value || null;
    }
  }

  function searchParamsFromUrl(u){
    var params = {};
    try {
      u.searchParams.forEach(function(v, k){ params[k] = v; });
      var hash = String(u.hash || '');
      if (hash.indexOf('?') !== -1) {
        var hashQuery = hash.split('?').slice(1).join('?').split('#')[0];
        new URLSearchParams(hashQuery).forEach(function(v, k){
          if (!params[k]) params[k] = v;
        });
      } else if (hash.indexOf('=') !== -1 && hash.charAt(1) !== '/') {
        new URLSearchParams(hash.replace(/^#/, '')).forEach(function(v, k){
          if (!params[k]) params[k] = v;
        });
      }
    } catch (e) {}
    return params;
  }

  function readAttribution(u){
    var out = {
      gclid: null,
      gbraid: null,
      wbraid: null,
      utm_source: null,
      utm_medium: null,
      utm_campaign: null,
      utm_term: null,
      adgroup_id: null,
      keyword: null,
      device: null,
      network: null,
      matchtype: null,
      creative: null,
      placement: null,
      source: null
    };
    try {
      var params = searchParamsFromUrl(u);
      out.gclid = storedAttribution('gclid', params.gclid || null);
      out.gbraid = storedAttribution('gbraid', params.gbraid || null);
      out.wbraid = storedAttribution('wbraid', params.wbraid || null);
      out.adgroup_id = storedAttribution('adgroup_id', params.adgroup_id || null);
      out.keyword = storedAttribution('keyword', params.keyword || null);
      out.device = storedAttribution('pm_device', params.device || null);
      out.network = storedAttribution('pm_network', params.network || null);
      out.matchtype = storedAttribution('pm_matchtype', params.matchtype || null);
      out.creative = storedAttribution('pm_creative', params.creative || null);
      out.placement = storedAttribution('pm_placement', params.placement || null);
      out.source = storedAttribution('pm_source', params.source || null);
      if (trackSource) out.utm_source = storedAttribution('utm_source', params.utm_source || null);
      if (trackMedium) out.utm_medium = storedAttribution('utm_medium', params.utm_medium || null);
      if (trackCampaign) out.utm_campaign = storedAttribution('utm_campaign', params.utm_campaign || null);
      if (trackTerm) out.utm_term = storedAttribution('utm_term', params.utm_term || null);
    } catch (e) {}
    return out;
  }

  var lastTrackedUrl = '';
  function pageview(force){
    var currentUrl = String(location.href || '');
    if (!force && currentUrl === lastTrackedUrl) return;
    lastTrackedUrl = currentUrl;

    var payload = {
      domainKey: domainKey,
      type: 'pageview',
      url: String(location.href || ''),
      path: String(location.pathname || ''),
      referrer: String(document.referrer || ''),
      session_id: sessionId(),
      device_token: deviceToken(),
      ts: Date.now()
    };
    try {
      var u = new URL(location.href);
      var attr = readAttribution(u);
      payload.gclid = attr.gclid;
      payload.gbraid = attr.gbraid;
      payload.wbraid = attr.wbraid;
      payload.utm_source = attr.utm_source;
      payload.utm_medium = attr.utm_medium;
      payload.utm_campaign = attr.utm_campaign;
      payload.utm_term = attr.utm_term;
      payload.adgroup_id = attr.adgroup_id;
      payload.keyword = attr.keyword || attr.utm_term;
      if (attr.keyword && !payload.utm_term) payload.utm_term = attr.keyword;
      if (attr.source === 'google_ads') {
        payload.utm_source = payload.utm_source || 'google';
        payload.utm_medium = payload.utm_medium || 'cpc';
      }
      payload.ad_click_meta = {
        source: attr.source,
        adgroup_id: attr.adgroup_id,
        keyword: attr.keyword,
        device: attr.device,
        network: attr.network,
        matchtype: attr.matchtype,
        creative: attr.creative,
        placement: attr.placement
      };
    } catch (e) {}

    function finishSend(fp){
      payload.fingerprint = fp && fp.id ? fp.id : deviceFingerprint();
      if (fp && fp.signals) payload.fingerprint_signals = fp.signals;
      readGa4ClientId({}).then(function(cid){
        if (cid) payload.ga4_client_id = cid;
        send(payload);
      }).catch(function(){ send(payload); });
    }

    collectDeviceFingerprint().then(function(fp){
      finishSend(fp);
    }).catch(function(){
      finishSend(null);
    });
  }

  function hookSpaNavigation(){
    var pushState = history.pushState;
    var replaceState = history.replaceState;
    function onRouteChange(){
      if (location.href === lastTrackedUrl) return;
      pageview(true);
    }
    history.pushState = function(){
      pushState.apply(history, arguments);
      onRouteChange();
    };
    history.replaceState = function(){
      replaceState.apply(history, arguments);
      onRouteChange();
    };
    window.addEventListener('popstate', onRouteChange);
  }

  function bootstrap(){
    if (!hasConsent()) {
      showConsentBanner();
      return;
    }
    earlyIpCheck(function(){
      // Always record the pageview. Tag Manager / website analytics must
      // connect without a Google Ads click ID. Protection overlay (if any)
      // is already applied inside earlyIpCheck.
      pageview(true);
      hookSpaNavigation();
    });
  }

  bootstrap();
})();
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function noscript(Request $request, string $domainKey): Response
    {
        $domain = Domain::where('domain_key', $domainKey)->first();
        if (! $domain || ($domain->status ?? 'pending') === 'disabled') {
            return response('', 204);
        }

        $collectUrl = url('/ingest/visit');
        $params = http_build_query([
            'domainKey' => $domainKey,
            'type' => 'pageview',
            'url' => (string) $request->headers->get('Referer', ''),
            'path' => '/',
            'click_source' => 'noscript',
            'ts' => (string) (int) (microtime(true) * 1000),
            '_' => (string) time(),
        ]);
        $pixelSrc = e($collectUrl . '?' . $params);

        $html = <<<HTML
<!DOCTYPE html><html><head><meta charset="utf-8"><title></title></head>
<body style="margin:0;padding:0;">
<img src="{$pixelSrc}" width="1" height="1" alt="" style="position:absolute;left:-9999px;" />
</body></html>
HTML;

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    private function json(mixed $value): string
    {
        if (is_string($value) && ($value === 'true' || $value === 'false')) {
            return $value;
        }

        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

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

        $js = <<<JS
(function(){
  var domainKey = {$domainKeyJson};
  var collectUrl = {$collectUrlJson};
  var sessionRecordingUrl = {$sessionRecordingUrlJson};
  var ipCheckUrl = {$ipCheckUrlJson};
  var consentRequired = {$consentRequiredJs};
  var maskPasswords = {$maskPasswordsJs};
  var consentRegions = {$consentRegionsJson};
  var trackSource = {$trackSource};
  var trackMedium = {$trackMedium};
  var trackCampaign = {$trackCampaign};
  var trackTerm = {$trackTerm};

  function qp(obj){
    try{
      var p = new URLSearchParams();
      for (var k in obj){
        if (!Object.prototype.hasOwnProperty.call(obj,k)) continue;
        var v = obj[k];
        if (v === undefined || v === null || v === '') continue;
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
      var img = new Image();
      img.referrerPolicy = 'no-referrer-when-downgrade';
      img.src = collectUrl + (collectUrl.indexOf('?') === -1 ? '?' : '&') + qp(payload);
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
      overlay.innerHTML = '<div><p style="font-size:20px;font-weight:600;margin:0 0 8px;">Access restricted</p><p style="opacity:.75;margin:0;">This visit was blocked by PromoTix protection.</p></div>';
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
      overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483646;background:#111;color:#fff;display:flex;align-items:center;justify-content:center;font:16px/1.4 system-ui,sans-serif;text-align:center;padding:24px;';
      overlay.innerHTML = '<div><p style="font-size:42px;font-weight:700;margin:0 0 8px;">403</p><p style="opacity:.75;margin:0;">Forbidden</p></div>';
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
      overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483645;background:rgba(13,13,13,.92);display:flex;align-items:center;justify-content:center;padding:24px;';
      overlay.innerHTML = '<div style="width:min(360px,100%);background:#1a1a1a;border:1px solid #6400B2;border-radius:12px;padding:20px;color:#fff;font:14px system-ui,sans-serif;"><p style="margin:0 0 12px;font-weight:600;">Verify you are human</p><p style="margin:0 0 12px;opacity:.8;">Solve: <strong>' + a + ' + ' + b + '</strong></p><input id="pm-captcha-input" type="text" inputmode="numeric" style="width:100%;height:36px;border-radius:6px;border:1px solid #6400B2;background:#101010;color:#fff;padding:0 10px;margin-bottom:10px;"><button id="pm-captcha-submit" type="button" style="width:100%;height:36px;border:0;border-radius:6px;background:#6400B2;color:#fff;font-weight:600;cursor:pointer;">Continue</button><p id="pm-captcha-error" style="display:none;color:#f87171;margin:10px 0 0;">Incorrect answer</p></div>';
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
    if (resp.blocked) {
      applyBlockResponse(resp);
      return;
    }
    if (resp.captcha_required && !captchaPassed()) {
      showCaptcha();
    }
    if (resp.record_session) {
      startSessionRecording(resp);
    }
  }

  function startSessionRecording(meta){
    if (window.__pmRecording) return;
    window.__pmRecording = true;
    var events = [];
    var started = Date.now();
    var lastMove = 0;
    var duration = Number(meta.recording_ms || 10000);

    function push(type, payload){
      if (events.length >= 500) return;
      var row = { t: Date.now() - started, type: type };
      if (payload && typeof payload === 'object') {
        for (var k in payload) {
          if (Object.prototype.hasOwnProperty.call(payload, k)) row[k] = payload[k];
        }
      }
      events.push(row);
    }

    push('meta', {
      vw: window.innerWidth || 0,
      vh: window.innerHeight || 0
    });

    function onMove(e){
      var now = Date.now();
      if (now - lastMove < 80) return;
      lastMove = now;
      push('mousemove', { x: e.clientX, y: e.clientY });
    }

    var lastScrollAt = 0;
    function onScroll(){
      var now = Date.now();
      if (now - lastScrollAt < 120) return;
      lastScrollAt = now;
      push('scroll', { x: window.scrollX || 0, y: window.scrollY || 0 });
    }

    function closestActionEl(el){
      var node = el;
      while (node && node !== document && node !== document.documentElement) {
        if (!node.tagName) { node = node.parentElement; continue; }
        var tag = String(node.tagName).toUpperCase();
        if (tag === 'A' || tag === 'BUTTON' || (node.getAttribute && node.getAttribute('role') === 'button')) {
          return node;
        }
        node = node.parentElement;
      }
      return el;
    }

    function isTelHref(href){
      var h = String(href || '').trim().toLowerCase();
      return h.indexOf('tel:') === 0 || h.indexOf('callto:') === 0 || h.indexOf('sms:') === 0;
    }

    function isCtaEl(el){
      if (!el || !el.tagName) return false;
      var tag = String(el.tagName).toUpperCase();
      if (el.getAttribute && (el.getAttribute('data-cta') != null || el.getAttribute('data-action') === 'cta')) return true;
      var cls = String(el.className || '').toLowerCase();
      var id = String(el.id || '').toLowerCase();
      var hay = cls + ' ' + id;
      if (/\\b(cta|call-to-action|btn-primary|button-primary|btn-cta|convert|signup|sign-up|buy-now|get-started|btn\\b|button\\b|wp-block-button|elementor-button|submit)\\b/.test(hay)) {
        return true;
      }
      if (tag === 'BUTTON') return true;
      if (tag === 'INPUT') {
        var t = String(el.type || '').toLowerCase();
        if (t === 'submit' || t === 'button') return true;
      }
      if (tag === 'A' && /\\b(btn|button|cta)\\b/.test(hay)) return true;
      return false;
    }

    function onClick(e){
      var target = closestActionEl(e.target);
      var href = '';
      try {
        href = String((target && (target.href || (target.getAttribute && target.getAttribute('href')))) || '');
      } catch (err) { href = ''; }
      var tel = isTelHref(href);
      var cta = !tel && isCtaEl(target);
      push('click', {
        x: e.clientX,
        y: e.clientY,
        tag: (target && target.tagName) ? String(target.tagName) : '',
        href: href.slice(0, 500),
        class: String((target && target.className) || '').slice(0, 200),
        id: String((target && target.id) || '').slice(0, 120),
        cta: cta ? 1 : 0,
        tel: tel ? 1 : 0
      });
      if (target && String(target.tagName).toUpperCase() === 'A' && href && !tel) {
        markPageSoon();
      }
    }

    var seenPages = {};
    function markPage(){
      var u = String(location.href || '').slice(0, 500);
      if (!u || seenPages[u]) return;
      seenPages[u] = true;
      push('page', { url: u });
    }
    var pageTimer = null;
    function markPageSoon(){
      if (pageTimer) clearTimeout(pageTimer);
      pageTimer = setTimeout(markPage, 400);
    }
    markPage();

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
    document.addEventListener('input', onInput, true);
    window.addEventListener('popstate', markPageSoon);
    window.addEventListener('hashchange', markPageSoon);

    var sent = false;
    var recordingTimer = null;
    function finishRecordingOnHide(){
      if (document.visibilityState === 'hidden') finishRecording();
    }
    function finishRecording(){
      if (sent) return;
      sent = true;
      if (recordingTimer) clearTimeout(recordingTimer);
      document.removeEventListener('mousemove', onMove);
      window.removeEventListener('scroll', onScroll);
      document.removeEventListener('click', onClick, true);
      document.removeEventListener('input', onInput, true);
      window.removeEventListener('popstate', markPageSoon);
      window.removeEventListener('hashchange', markPageSoon);
      window.removeEventListener('pagehide', finishRecording);
      document.removeEventListener('visibilitychange', finishRecordingOnHide);
      window.__pmRecording = false;
      try {
        fetch(sessionRecordingUrl, {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({
            domainKey: domainKey,
            session_id: sessionId(),
            visit_id: meta.visit_id || null,
            page_url: String(location.href || ''),
            duration_ms: Date.now() - started,
            threat_group: meta.threat_group || null,
            events: events
          }),
          mode: 'cors',
          credentials: 'omit',
          keepalive: true
        });
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

  // Stable browser fingerprint (NOT cookies). Device ID on server hashes this.
  // Signals: WebGL vendor/renderer, canvas, browser/OS/device family, touch, pixel ratio, screen.
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
    if (ua.indexOf('edg/') !== -1 || ua.indexOf('edge/') !== -1) return 'edge';
    if (ua.indexOf('opr/') !== -1 || ua.indexOf('opera') !== -1) return 'opera';
    if (ua.indexOf('firefox') !== -1 || ua.indexOf('fxios') !== -1) return 'firefox';
    if (ua.indexOf('crios') !== -1 || (ua.indexOf('chrome') !== -1 && ua.indexOf('chromium') === -1)) return 'chrome';
    if (ua.indexOf('safari') !== -1) return 'safari';
    if (ua.indexOf('samsung') !== -1) return 'samsung';
    return 'other';
  }

  function osFamilyFromUa(ua){
    ua = String(ua || '').toLowerCase();
    if (ua.indexOf('iphone') !== -1 || ua.indexOf('ipad') !== -1 || ua.indexOf('ipod') !== -1) return 'ios';
    if (ua.indexOf('android') !== -1) return 'android';
    if (ua.indexOf('windows') !== -1) return 'windows';
    if (ua.indexOf('mac os') !== -1 || ua.indexOf('macintosh') !== -1) return 'macos';
    if (ua.indexOf('cros') !== -1) return 'chromeos';
    if (ua.indexOf('linux') !== -1) return 'linux';
    return 'other';
  }

  function deviceTypeFromSignals(ua, touchPoints){
    ua = String(ua || '').toLowerCase();
    if (ua.indexOf('ipad') !== -1 || (ua.indexOf('android') !== -1 && ua.indexOf('mobile') === -1) || ua.indexOf('tablet') !== -1) return 'tablet';
    if (ua.indexOf('mobi') !== -1 || ua.indexOf('iphone') !== -1 || ua.indexOf('ipod') !== -1 || (touchPoints > 0 && ua.indexOf('windows') === -1 && ua.indexOf('macintosh') === -1)) return 'mobile';
    return 'desktop';
  }

  function webglInfo(){
    var out = { vendor: '', renderer: '' };
    try {
      var canvas = document.createElement('canvas');
      var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
      if (!gl) return out;
      var ext = gl.getExtension('WEBGL_debug_renderer_info');
      if (ext) {
        out.vendor = String(gl.getParameter(ext.UNMASKED_VENDOR_WEBGL) || '');
        out.renderer = String(gl.getParameter(ext.UNMASKED_RENDERER_WEBGL) || '');
      } else {
        out.vendor = String(gl.getParameter(gl.VENDOR) || '');
        out.renderer = String(gl.getParameter(gl.RENDERER) || '');
      }
    } catch (e) {}
    return out;
  }

  function canvasCharacteristics(){
    try {
      var canvas = document.createElement('canvas');
      canvas.width = 240;
      canvas.height = 60;
      var ctx = canvas.getContext('2d');
      if (!ctx) return 'none';
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
      return hashString(data) + ':' + String(data.length) + ':' + String(canvas.width) + 'x' + String(canvas.height);
    } catch (e) {
      return 'err';
    }
  }

  function deviceFingerprint(){
    // v2 cache: richer WebGL/canvas/device signals (do not reuse v1 weak FP).
    var key = 'pm_fp_v2_' + domainKey;
    try {
      var cached = localStorage.getItem(key);
      if (cached && cached.length > 8) return cached;
    } catch (e) {}
    var parts = [];
    try {
      var ua = String(navigator.userAgent || '');
      var touchPoints = Number(navigator.maxTouchPoints || 0);
      var gl = webglInfo();
      var pr = Number(window.devicePixelRatio || 1);
      parts.push('ua=' + ua);
      parts.push('lang=' + String(navigator.language || (navigator.languages && navigator.languages[0]) || ''));
      parts.push('plat=' + String(navigator.platform || ''));
      parts.push('tz=' + String((Intl.DateTimeFormat().resolvedOptions() || {}).timeZone || ''));
      parts.push('tzoff=' + String(new Date().getTimezoneOffset()));
      parts.push('bf=' + browserFamilyFromUa(ua));
      parts.push('osf=' + osFamilyFromUa(ua));
      parts.push('dt=' + deviceTypeFromSignals(ua, touchPoints));
      parts.push('touch=' + String(touchPoints));
      parts.push('touchcap=' + String((('ontouchstart' in window) || touchPoints > 0) ? 1 : 0));
      parts.push('pr=' + String(pr));
      parts.push('scr=' + String(screen.width || 0) + 'x' + String(screen.height || 0) + 'x' + String(screen.colorDepth || 0) + 'x' + String(screen.pixelDepth || 0));
      parts.push('aw=' + String(window.screen.availWidth || 0) + 'x' + String(window.screen.availHeight || 0));
      parts.push('or=' + String(screen.orientation && screen.orientation.type ? screen.orientation.type : (window.orientation || 0)));
      parts.push('glv=' + gl.vendor);
      parts.push('glr=' + gl.renderer);
      parts.push('cvs=' + canvasCharacteristics());
      parts.push('hc=' + String(navigator.hardwareConcurrency || 0));
      parts.push('mem=' + String(navigator.deviceMemory || 0));
      parts.push('cookie=' + String(navigator.cookieEnabled ? 1 : 0));
    } catch (e) {}
    var raw = parts.join('|');
    var fp = 'cfp2_' + hashString(raw) + '_' + String(raw.length);
    try { localStorage.setItem(key, fp); } catch (e) {}
    return fp;
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
      fingerprint: deviceFingerprint(),
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

    send(payload);
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
    earlyIpCheck(function(blocked){
      if (blocked) return;
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

<?php

namespace App\Http\Controllers;

use App\Models\Domain;
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

        $js = <<<JS
(function(){
  var domainKey = {$this->json($domainKey)};
  var collectUrl = {$this->json($collectUrl)};
  var sessionRecordingUrl = {$this->json($sessionRecordingUrl)};
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
      var img = new Image();
      img.referrerPolicy = 'no-referrer-when-downgrade';
      img.src = collectUrl + (collectUrl.indexOf('?') === -1 ? '?' : '&') + qp(payload);
    }catch(e){}
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
      var overlay = document.createElement('div');
      overlay.id = 'pm-block-overlay';
      overlay.style.cssText = 'position:fixed;inset:0;z-index:2147483646;background:#0d0d0d;color:#fff;display:flex;align-items:center;justify-content:center;font:16px/1.4 system-ui,sans-serif;text-align:center;padding:24px;';
      overlay.innerHTML = '<div><p style="font-size:20px;font-weight:600;margin:0 0 8px;">Access restricted</p><p style="opacity:.75;margin:0;">This visit was blocked by PromoTix protection.</p></div>';
      (document.body || document.documentElement).appendChild(overlay);
      document.documentElement.style.overflow = 'hidden';
    } catch (e) {}
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
      hidePage();
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

    function onScroll(){
      push('scroll', { x: window.scrollX || 0, y: window.scrollY || 0 });
    }

    function onClick(e){
      push('click', {
        x: e.clientX,
        y: e.clientY,
        tag: (e.target && e.target.tagName) ? String(e.target.tagName) : ''
      });
    }

    document.addEventListener('mousemove', onMove, { passive: true });
    window.addEventListener('scroll', onScroll, { passive: true });
    document.addEventListener('click', onClick, true);

    setTimeout(function(){
      document.removeEventListener('mousemove', onMove);
      window.removeEventListener('scroll', onScroll);
      document.removeEventListener('click', onClick, true);
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
    }, duration);
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
      gad_campaignid: null,
      campaign_id: null,
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
      out.gad_campaignid = storedAttribution('gad_campaignid', params.gad_campaignid || params.campaign_id || null);
      out.campaign_id = storedAttribution('campaign_id', params.campaign_id || params.gad_campaignid || null);
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
      payload.gad_campaignid = attr.gad_campaignid || attr.campaign_id;
      payload.campaign_id = attr.campaign_id;
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

  pageview(true);
  hookSpaNavigation();
})();
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function noscript(Request $request, string $domainKey): Response
    {
        return response()->noContent();
    }

    private function json(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

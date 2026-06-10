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
        $gateUrl = url('/ip-check');

        // Minimal tag: sends pageview data. Browser IP is captured server-side.
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
  var gateUrl = {$this->json($gateUrl)};
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

  function enforceBlock(payload){
    try {
      var style = document.createElement('style');
      style.textContent = 'html,body{visibility:hidden!important;height:0!important;overflow:hidden!important;margin:0!important;padding:0!important}';
      (document.head || document.documentElement).appendChild(style);
      if (window.stop) window.stop();
      document.documentElement.innerHTML = '';
      document.body && (document.body.innerHTML = '');
    } catch (e) {}
    try {
      location.replace('about:blank');
    } catch (e2) {}
  }

  function handleProtectionResponse(data){
    if (data && (data.blocked === true || data.allowed === false)) {
      enforceBlock(data);
      return true;
    }
    return false;
  }

  function send(payload){
    try {
      fetch(collectUrl, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload),
        mode: 'cors',
        credentials: 'omit',
        keepalive: true
      }).then(function(res){
        return res.json().catch(function(){ return null; });
      }).then(function(data){
        handleProtectionResponse(data);
      }).catch(function(){
        try {
          if (navigator.sendBeacon){
            navigator.sendBeacon(collectUrl, new Blob([JSON.stringify(payload)], {type: 'application/json'}));
            return;
          }
        } catch (e) {}
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

  function pageview(){
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
      payload.gclid = u.searchParams.get('gclid') || null;
      payload.utm_source = trackSource ? (u.searchParams.get('utm_source') || null) : null;
      payload.utm_medium = trackMedium ? (u.searchParams.get('utm_medium') || null) : null;
      payload.utm_campaign = trackCampaign ? (u.searchParams.get('utm_campaign') || null) : null;
      payload.utm_term = trackTerm ? (u.searchParams.get('utm_term') || null) : null;
    } catch (e) {}

    try {
      fetch(gateUrl, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({
          domainKey: domainKey,
          path: payload.path || '',
          referrer: payload.referrer || ''
        }),
        mode: 'cors',
        credentials: 'omit'
      }).then(function(res){
        return res.json();
      }).then(function(data){
        if (handleProtectionResponse(data)) return;
        send(payload);
      }).catch(function(){
        send(payload);
      });
    } catch (e) {
      send(payload);
    }
  }

  pageview();
})();
JS;

        return response($js, 200, [
            'Content-Type' => 'application/javascript; charset=UTF-8',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
        ]);
    }

    public function noscript(Request $request, string $domainKey): Response
    {
        // Just record a minimal hit (if someone uses the iframe).
        // For now, return a blank 204 to avoid rendering anything.
        return response()->noContent();
    }

    private function json(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}


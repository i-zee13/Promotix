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

        // Tracking-only tag: records visits server-side. No client-side IP block / page hide.
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

  function send(payload){
    try {
      fetch(collectUrl, {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify(payload),
        mode: 'cors',
        credentials: 'omit',
        keepalive: true
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

  function readAttribution(u){
    var out = {
      gclid: null,
      gbraid: null,
      wbraid: null,
      utm_source: null,
      utm_medium: null,
      utm_campaign: null,
      utm_term: null,
      gad_campaignid: null
    };
    try {
      out.gclid = storedAttribution('gclid', u.searchParams.get('gclid'));
      out.gbraid = storedAttribution('gbraid', u.searchParams.get('gbraid'));
      out.wbraid = storedAttribution('wbraid', u.searchParams.get('wbraid'));
      out.gad_campaignid = storedAttribution('gad_campaignid', u.searchParams.get('gad_campaignid'));
      if (trackSource) out.utm_source = storedAttribution('utm_source', u.searchParams.get('utm_source'));
      if (trackMedium) out.utm_medium = storedAttribution('utm_medium', u.searchParams.get('utm_medium'));
      if (trackCampaign) out.utm_campaign = storedAttribution('utm_campaign', u.searchParams.get('utm_campaign'));
      if (trackTerm) out.utm_term = storedAttribution('utm_term', u.searchParams.get('utm_term'));
    } catch (e) {}
    return out;
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
      var attr = readAttribution(u);
      payload.gclid = attr.gclid;
      payload.gbraid = attr.gbraid;
      payload.wbraid = attr.wbraid;
      payload.utm_source = attr.utm_source;
      payload.utm_medium = attr.utm_medium;
      payload.utm_campaign = attr.utm_campaign;
      payload.utm_term = attr.utm_term;
      payload.gad_campaignid = attr.gad_campaignid;
    } catch (e) {}

    send(payload);
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
        return response()->noContent();
    }

    private function json(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

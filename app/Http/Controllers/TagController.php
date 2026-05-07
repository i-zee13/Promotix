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
      if (navigator.sendBeacon){
        var ok = navigator.sendBeacon(collectUrl, new Blob([JSON.stringify(payload)], {type: 'application/json'}));
        if (!ok) pixel(payload);
        return;
      }
      if (window.fetch){
        fetch(collectUrl, {
            method: 'POST',
            headers: {'Content-Type':'application/json'},
            body: JSON.stringify(payload),
            mode: 'cors',
            credentials: 'omit',
            keepalive: true
          }).catch(function(){ pixel(payload); });
        return;
      }
      pixel(payload);
    } catch (e) {}
  }

  function pageview(){
    var payload = {
      domainKey: domainKey,
      type: 'pageview',
      url: String(location.href || ''),
      path: String(location.pathname || ''),
      referrer: String(document.referrer || ''),
      ts: Date.now()
    };
    // Basic paid params
    try {
      var u = new URL(location.href);
      payload.gclid = u.searchParams.get('gclid') || null;
      payload.utm_source = trackSource ? (u.searchParams.get('utm_source') || null) : null;
      payload.utm_medium = trackMedium ? (u.searchParams.get('utm_medium') || null) : null;
      payload.utm_campaign = trackCampaign ? (u.searchParams.get('utm_campaign') || null) : null;
      payload.utm_term = trackTerm ? (u.searchParams.get('utm_term') || null) : null;
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
        // Just record a minimal hit (if someone uses the iframe).
        // For now, return a blank 204 to avoid rendering anything.
        return response()->noContent();
    }

    private function json(string $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}


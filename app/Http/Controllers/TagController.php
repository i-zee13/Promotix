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
      var node = el;
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

    function isTelHref(href){
      var h = String(href || '').trim().toLowerCase();
      return h.indexOf('tel:') === 0 || h.indexOf('callto:') === 0 || h.indexOf('sms:') === 0;
    }

    function telNumberFromHref(href){
      return String(href || '').replace(/^(tel|callto|sms):/i, '').trim().slice(0, 64);
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
      var href = '';
      var text = '';
      try {
        href = String((target && (target.href || (target.getAttribute && target.getAttribute('href')))) || '');
      } catch (err) { href = ''; }
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
      var tel = isTelHref(meta.href);
      var commerce = commerceKind(target);
      var cta = !tel && !commerce && isCtaEl(target);

      if (tel) {
        push('phone_click', Object.assign({}, meta, {
          tel_number: telNumberFromHref(meta.href),
          link_type: 'tel'
        }));
      } else if (commerce) {
        push(commerce, Object.assign({}, meta, {
          product_name: meta.element_text || undefined
        }));
      } else if (cta) {
        push('cta_click', meta);
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

    var formStarted = {};
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
      push('form_submit', {
        form_id: formKey(form),
        form_name: formName(form),
        page_url: String(location.href || '').slice(0, 500),
        path: String(location.pathname || '').slice(0, 500),
        title: String(document.title || '').slice(0, 255),
        success: valid ? 1 : 0,
        status: valid ? 'success' : 'failed'
      });
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
      } else {
        push('page_change', pagePayload);
      }
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
    document.addEventListener('input', onInput, true);
    document.addEventListener('focusin', onFormFocus, true);
    document.addEventListener('invalid', onFormInvalid, true);
    document.addEventListener('submit', onFormSubmit, true);
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
      push('session_exit', {
        page_url: String(location.href || '').slice(0, 500),
        path: String(location.pathname || '').slice(0, 500),
        title: String(document.title || '').slice(0, 255),
        url: String(location.href || '').slice(0, 500)
      });
      document.removeEventListener('mousemove', onMove);
      window.removeEventListener('scroll', onScroll);
      document.removeEventListener('click', onClick, true);
      document.removeEventListener('input', onInput, true);
      document.removeEventListener('focusin', onFormFocus, true);
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
      window.__pmRecording = false;
      try {
        fetch(sessionRecordingUrl, {
          method: 'POST',
          headers: {'Content-Type':'application/json'},
          body: JSON.stringify({
            domainKey: domainKey,
            session_id: sessionId(),
            visitor_id: visitorId(),
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

    collectDeviceFingerprint().then(function(fp){
      payload.fingerprint = fp.id;
      payload.fingerprint_signals = fp.signals || {};
      send(payload);
    }).catch(function(){
      payload.fingerprint = deviceFingerprint();
      send(payload);
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

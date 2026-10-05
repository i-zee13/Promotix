#!/usr/bin/env bash
# Capture live Clickronix visit + session-recording JSON.
# Usage:
#   DOMAIN_KEY=your_key bash scripts/capture-cta-session-json.sh
# Optional:
#   BASE_URL=http://localhost:8000 PAGE_URL=https://yoursite.com/pricing

set -euo pipefail
BASE_URL="${BASE_URL:-http://localhost:8000}"
DOMAIN_KEY="${DOMAIN_KEY:?Set DOMAIN_KEY=...}"
PAGE_URL="${PAGE_URL:-https://example.com/pricing}"
OUT_DIR="${OUT_DIR:-storage/app/public/exports}"
mkdir -p "$OUT_DIR"
TS="$(date +%Y%m%d-%H%M%S)"
SESSION_ID="sess_cap_${TS}"
VISITOR_ID="vis_cap_${TS}"

echo "==> POST ${BASE_URL}/ingest/visit"
VISIT_RESP="$(curl -sS -X POST "${BASE_URL}/ingest/visit" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d "$(jq -n \
    --arg k "$DOMAIN_KEY" \
    --arg u "$PAGE_URL" \
    --arg s "$SESSION_ID" \
    --arg v "$VISITOR_ID" \
    '{domainKey:$k,url:$u,path:"/pricing",title:"Pricing",session_id:$s,visitor_id:$v}')")"
echo "$VISIT_RESP" | tee "${OUT_DIR}/ingest-visit-${TS}.json" | jq .

RECORD_SESSION="$(echo "$VISIT_RESP" | jq -r '.record_session // false')"
VISIT_ID="$(echo "$VISIT_RESP" | jq -r '.visit_id // empty')"

if [[ "$RECORD_SESSION" != "true" ]]; then
  echo ""
  echo "!! record_session is NOT true — Session Recording OFF / plan / settings."
  echo "   Captured visit JSON only: ${OUT_DIR}/ingest-visit-${TS}.json"
  exit 0
fi

NOW_MS="$(($(date +%s)*1000))"
echo ""
echo "==> POST ${BASE_URL}/ingest/session-recording (with cta_click)"
REC_RESP="$(curl -sS -X POST "${BASE_URL}/ingest/session-recording" \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d "$(jq -n \
    --arg k "$DOMAIN_KEY" \
    --arg s "$SESSION_ID" \
    --arg v "$VISITOR_ID" \
    --arg u "$PAGE_URL" \
    --argjson vid "${VISIT_ID:-null}" \
    --argjson ts "$NOW_MS" \
    '{
      domainKey:$k,
      session_id:$s,
      visitor_id:$v,
      visit_id:$vid,
      page_url:$u,
      duration_ms:8400,
      threat_group:null,
      events:[
        {type:"pageview",t:0,ts:$ts,page_url:$u,path:"/pricing",title:"Pricing"},
        {type:"scroll",t:1200,ts:($ts+1200),depth:40},
        {type:"cta_click",t:3200,ts:($ts+3200),href:"/signup",element_text:"Get Started",text:"Get Started",element_id:"hero-cta",element_class:"btn btn-primary",tag:"A",link_type:"anchor",page_url:$u,path:"/pricing",title:"Pricing"},
        {type:"session_exit",t:8400,ts:($ts+8400),page_url:$u,path:"/pricing"}
      ]
    }')")"
echo "$REC_RESP" | tee "${OUT_DIR}/ingest-session-recording-${TS}.json" | jq .

echo ""
echo "Saved:"
echo "  ${OUT_DIR}/ingest-visit-${TS}.json"
echo "  ${OUT_DIR}/ingest-session-recording-${TS}.json"

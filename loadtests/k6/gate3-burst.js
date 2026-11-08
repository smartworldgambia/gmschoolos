import http from 'k6/http';
import { check, sleep } from 'k6';

/**
 * Gate 3 — burst / autoscaling behaviour (Profile B-ish).
 * Prefer multiple generators (distributed) against a shared BASE_URL.
 * Does NOT certify 2M users.
 */
const BASE = __ENV.BASE_URL || 'http://127.0.0.1:8000';

export const options = {
  scenarios: {
    gate3_burst: {
      executor: 'ramping-arrival-rate',
      startRate: Number(__ENV.START_RATE || 10),
      timeUnit: '1s',
      preAllocatedVUs: Number(__ENV.VUS || 30),
      maxVUs: Number(__ENV.MAX_VUS || 100),
      stages: [
        { target: Number(__ENV.PEAK_RATE || 50), duration: '30s' },
        { target: Number(__ENV.PEAK_RATE || 50), duration: '1m' },
        { target: 5, duration: '30s' },
      ],
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.02'],
    http_req_duration: ['p(95)<1200'],
  },
};

export default function () {
  const res = http.get(`${BASE}/health`);
  check(res, { 'health 200': (r) => r.status === 200 });
  http.get(`${BASE}/metrics`);
  sleep(0.05);
}

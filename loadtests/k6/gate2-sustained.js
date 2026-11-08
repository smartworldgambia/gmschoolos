import http from 'k6/http';
import { check, sleep } from 'k6';

/**
 * Gate 2 — sustained load / latency targets (Profile A baseline).
 * Run against staging only. Results must be recorded via:
 *   php artisan schoolos:record-capacity-run gate2 A --p95=... --errors=... --rps=...
 */
const BASE = __ENV.BASE_URL || 'http://127.0.0.1:8000';

export const options = {
  scenarios: {
    gate2_sustained: {
      executor: 'constant-arrival-rate',
      rate: Number(__ENV.RATE || 20),
      timeUnit: '1s',
      duration: __ENV.DURATION || '2m',
      preAllocatedVUs: Number(__ENV.VUS || 20),
      maxVUs: Number(__ENV.MAX_VUS || 50),
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.01'],
    http_req_duration: ['p(95)<800'],
  },
};

export default function () {
  const paths = ['/health', '/ready', '/metrics', '/', '/sites/brightfuture'];
  const path = paths[Math.floor(Math.random() * paths.length)];
  const res = http.get(`${BASE}${path}`);
  check(res, { 'status ok-ish': (r) => r.status === 200 || r.status === 404 });
  sleep(0.1);
}

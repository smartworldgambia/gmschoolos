import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE = __ENV.BASE_URL || 'http://127.0.0.1:8000';

export const options = {
  scenarios: {
    gate1_smoke: {
      executor: 'constant-vus',
      vus: Number(__ENV.VUS || 2),
      duration: __ENV.DURATION || '30s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.05'],
    http_req_duration: ['p(95)<1500'],
  },
};

export default function () {
  const health = http.get(`${BASE}/health`);
  check(health, {
    'health 200': (r) => r.status === 200,
    'health ok': (r) => {
      try {
        return r.json('status') === 'ok';
      } catch (e) {
        return false;
      }
    },
  });

  const ready = http.get(`${BASE}/ready`);
  check(ready, {
    'ready 200': (r) => r.status === 200,
  });

  const metricsHeaders = {};
  if (__ENV.METRICS_TOKEN) {
    metricsHeaders.Authorization = `Bearer ${__ENV.METRICS_TOKEN}`;
  }
  const metrics = http.get(`${BASE}/metrics`, { headers: metricsHeaders });
  check(metrics, {
    'metrics allowed': (r) => r.status === 200 || r.status === 401 || r.status === 403,
    'metrics has queues when allowed': (r) => {
      if (r.status !== 200) return true;
      try {
        return Array.isArray(r.json('queues'));
      } catch (e) {
        return false;
      }
    },
  });

  const home = http.get(`${BASE}/`);
  check(home, { 'landing 200': (r) => r.status === 200 });

  const site = http.get(`${BASE}/sites/brightfuture`);
  check(site, {
    'public site reachable or missing seed': (r) => r.status === 200 || r.status === 404,
  });

  sleep(1);
}

import http from 'k6/http';
import { check, sleep } from 'k6';
import { parseHTML } from 'k6/html';

const BASE = __ENV.BASE_URL || 'http://127.0.0.1:8000';
const EMAIL = __ENV.ADMIN_EMAIL || 'admin@brightfuture.test';
const PASSWORD = __ENV.ADMIN_PASSWORD || 'password';

export const options = {
  scenarios: {
    gate1_journeys: {
      executor: 'constant-vus',
      vus: Number(__ENV.VUS || 1),
      duration: __ENV.DURATION || '45s',
    },
  },
  thresholds: {
    http_req_failed: ['rate<0.1'],
    http_req_duration: ['p(95)<2500'],
  },
};

function csrfFromLoginPage(body) {
  const doc = parseHTML(body);
  const token = doc.find('input[name="_token"]').attr('value');
  return token;
}

export default function () {
  const jar = http.cookieJar();

  const loginPage = http.get(`${BASE}/login`, { jar });
  check(loginPage, { 'login page 200': (r) => r.status === 200 });

  const token = csrfFromLoginPage(loginPage.body);
  const login = http.post(
    `${BASE}/login`,
    {
      email: EMAIL,
      password: PASSWORD,
      _token: token,
    },
    {
      jar,
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
      },
      redirects: 5,
    }
  );

  check(login, {
    'login redirected or ok': (r) => r.status === 200 || r.status === 302,
  });

  const admin = http.get(`${BASE}/admin`, { jar, redirects: 5 });
  check(admin, { 'admin dashboard 200': (r) => r.status === 200 });

  const students = http.get(`${BASE}/students`, { jar, redirects: 5 });
  check(students, { 'students 200': (r) => r.status === 200 });

  const lms = http.get(`${BASE}/lms/courses`, { jar, redirects: 5 });
  check(lms, { 'lms 200': (r) => r.status === 200 });

  sleep(1);
}

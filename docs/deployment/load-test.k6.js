// Read-only load test for the public side of the LMS (Phase 17).
//
// WHAT IT DOES: anonymous visitors loading the home page, the course catalogue,
// one course page and /up. It never logs in, never posts a form and never starts
// a checkout, so it creates no orders, no accounts and no emails.
//
// WHERE TO RUN IT: against STAGING, or against production only at a quiet hour
// and with the team's agreement. The target is a required parameter on purpose;
// there is no default, so it cannot be pointed at production by accident.
//
//   k6 run -e BASE_URL=https://staging.example.com -e COURSE_SLUG=my-course docs/deployment/load-test.k6.js
//
// Install k6 from https://k6.io (not an npm or composer dependency of this repo).
// Ramp: 1 -> 20 -> 50 virtual users, each pausing 1-3 s between pages, which is
// closer to people reading than to a flood. Raise STAGES only after a clean run.

import http from 'k6/http';
import { check, sleep } from 'k6';

const BASE_URL = __ENV.BASE_URL;
const COURSE_SLUG = __ENV.COURSE_SLUG;

if (!BASE_URL) {
    throw new Error('Set -e BASE_URL=https://... (required; there is no default).');
}

export const options = {
    stages: [
        { duration: '1m', target: 20 },
        { duration: '3m', target: 50 },
        { duration: '1m', target: 0 },
    ],
    thresholds: {
        http_req_failed: ['rate<0.01'], // under 1% errors
        http_req_duration: ['p(95)<1500'], // 95% of pages under 1.5 s
    },
};

export default function () {
    const pages = ['/', '/courses', '/up'];

    if (COURSE_SLUG) {
        pages.push(`/courses/${COURSE_SLUG}`);
    }

    for (const path of pages) {
        const response = http.get(`${BASE_URL}${path}`);

        check(response, {
            [`${path} is 200`]: (r) => r.status === 200,
        });

        sleep(1 + Math.random() * 2);
    }
}

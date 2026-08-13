import http from 'k6/http';
import { check, sleep } from 'k6';
import exec from 'k6/execution';
import { Rate } from 'k6/metrics';

function requiredEnvironment(name) {
    const value = __ENV[name];

    if (!value) {
        throw new Error(`Missing required environment variable: ${name}`);
    }

    return value;
}

const baseUrl = __ENV.BASE_URL || 'http://nginx-load';
const workspaceId = requiredEnvironment('WORKSPACE_ID');
const projectId = requiredEnvironment('PROJECT_ID');
const password = requiredEnvironment('LOAD_TEST_PASSWORD');
const profile = __ENV.PROFILE || 'baseline';
const from = '2025-08-13';
const to = '2026-08-13';

const apiErrors = new Rate('api_errors');
const serverErrors = new Rate('server_errors');
const rateLimited = new Rate('rate_limited');
const accessErrors = new Rate('access_errors');

function constantScenario(execName, vus, duration) {
    return {
        executor: 'constant-vus',
        exec: execName,
        vus,
        duration,
        gracefulStop: '10s',
    };
}

function smokeScenario(execName) {
    return {
        executor: 'shared-iterations',
        exec: execName,
        vus: 1,
        iterations: 1,
        maxDuration: '30s',
    };
}

function peakScenario(execName) {
    return {
        executor: 'ramping-vus',
        exec: execName,
        startVUs: 5,
        stages: [
            { duration: '2m', target: 5 },
            { duration: '2m', target: 10 },
            { duration: '2m', target: 15 },
            { duration: '30s', target: 0 },
        ],
        gracefulRampDown: '10s',
    };
}

const profileScenarios = {
    smoke: {
        smoke_workspace: smokeScenario('workspaceStatistics'),
        smoke_personal: smokeScenario('personalStatistics'),
        smoke_project: smokeScenario('projectStatistics'),
        smoke_team: smokeScenario('teamStatistics'),
    },
    warmup: {
        workspace: constantScenario('workspaceStatistics', 1, '1m'),
        personal: constantScenario('personalStatistics', 1, '1m'),
        project: constantScenario('projectStatistics', 1, '1m'),
        team: constantScenario('teamStatistics', 1, '1m'),
    },
    baseline: {
        workspace: constantScenario('workspaceStatistics', 1, '5m'),
        personal: constantScenario('personalStatistics', 5, '5m'),
        project: constantScenario('projectStatistics', 5, '5m'),
        team: constantScenario('teamStatistics', 5, '5m'),
    },
    peak: {
        workspace: constantScenario('workspaceStatistics', 1, '6m30s'),
        personal: peakScenario('personalStatistics'),
        project: peakScenario('projectStatistics'),
        team: peakScenario('teamStatistics'),
    },
    soak: {
        workspace: constantScenario('workspaceStatistics', 1, '15m'),
        personal: constantScenario('personalStatistics', 5, '15m'),
        project: constantScenario('projectStatistics', 5, '15m'),
        team: constantScenario('teamStatistics', 5, '15m'),
    },
};

if (!profileScenarios[profile]) {
    throw new Error(`Unknown PROFILE: ${profile}`);
}

export const options = {
    scenarios: profileScenarios[profile],
    noCookiesReset: true,
    summaryTrendStats: ['avg', 'min', 'med', 'max', 'p(90)', 'p(95)', 'p(99)'],
    thresholds: {
        api_errors: ['rate==0'],
        server_errors: ['rate==0'],
        rate_limited: ['rate==0'],
        access_errors: ['rate==0'],
        checks: ['rate>0.99'],
        http_req_duration: ['p(99)<1500'],
        'http_req_duration{endpoint:personal}': ['p(95)<500'],
        'http_req_duration{endpoint:project}': ['p(95)<750'],
        'http_req_duration{endpoint:team}': ['p(95)<750'],
        'http_req_duration{endpoint:workspace}': ['p(95)<1000'],
    },
};

let authenticatedEmail = null;

function employeeEmail() {
    const number = String(exec.vu.idInTest).padStart(3, '0');

    return `statistics-load-user-${number}@example.test`;
}

function recordStatus(status) {
    serverErrors.add(status >= 500);
    rateLimited.add(status === 429);
    accessErrors.add([401, 403, 404, 419].includes(status));
}

function login(email) {
    if (authenticatedEmail === email) {
        return;
    }

    const headers = {
        Accept: 'application/json',
        Origin: baseUrl,
        Referer: `${baseUrl}/`,
    };
    const csrfResponse = http.get(`${baseUrl}/sanctum/csrf-cookie`, {
        headers,
        tags: { endpoint: 'authentication' },
    });
    recordStatus(csrfResponse.status);

    const cookies = http.cookieJar().cookiesForURL(baseUrl);
    const csrfCookies = cookies['XSRF-TOKEN'];

    if (!csrfCookies || csrfCookies.length === 0) {
        apiErrors.add(true);
        throw new Error(`XSRF-TOKEN cookie was not returned for ${email}`);
    }

    const response = http.post(
        `${baseUrl}/api/v1/login`,
        JSON.stringify({ email, password }),
        {
            headers: {
                ...headers,
                'Content-Type': 'application/json',
                'X-XSRF-TOKEN': decodeURIComponent(csrfCookies[0]),
            },
            tags: { endpoint: 'authentication' },
        },
    );
    recordStatus(response.status);

    const successful = check(response, {
        'login returns 200': (result) => result.status === 200,
    });
    apiErrors.add(!successful);

    if (!successful) {
        throw new Error(`Login failed for ${email} with status ${response.status}`);
    }

    authenticatedEmail = email;
}

function requestStatistics(path, endpoint) {
    const response = http.get(`${baseUrl}${path}`, {
        headers: {
            Accept: 'application/json',
            Origin: baseUrl,
            Referer: `${baseUrl}/`,
        },
        tags: { endpoint },
    });
    recordStatus(response.status);

    const successful = check(response, {
        [`${endpoint} returns 200`]: (result) => result.status === 200,
        [`${endpoint} returns approved period`]: (result) => {
            try {
                return result.json('data.period.status') === 'approved';
            } catch (_) {
                return false;
            }
        },
    });
    apiErrors.add(!successful);
    sleep(1);
}

function period(granularity) {
    return `from=${from}&to=${to}&granularity=${granularity}`;
}

export function workspaceStatistics() {
    login('statistics-load-admin@example.test');
    requestStatistics(
        `/api/v1/workspaces/${workspaceId}/statistics?${period('month')}`,
        'workspace',
    );
}

export function personalStatistics() {
    login(employeeEmail());
    requestStatistics(
        `/api/v1/workspaces/${workspaceId}/statistics/me?${period('month')}`,
        'personal',
    );
}

export function projectStatistics() {
    login(employeeEmail());
    requestStatistics(
        `/api/v1/workspaces/${workspaceId}/projects/${projectId}/statistics?${period('day')}`,
        'project',
    );
}

export function teamStatistics() {
    login(employeeEmail());
    requestStatistics(
        `/api/v1/workspaces/${workspaceId}/projects/${projectId}/statistics/team?${period('month')}`,
        'team',
    );
}

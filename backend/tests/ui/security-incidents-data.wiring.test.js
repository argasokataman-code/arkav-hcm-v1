import { describe, test, expect, beforeEach, afterEach, vi } from "vitest";

function flush(times = 8) {
    return Array.from({ length: times }).reduce(
        (p) => p.then(() => Promise.resolve()),
        Promise.resolve()
    );
}

function buildDom() {
    document.body.innerHTML = `
        <div data-si-page>
            <span data-si-stat="total">0</span>
            <span data-si-stat="detected">0</span>
            <span data-si-stat="notified">0</span>
            <span data-si-stat="resolved">0</span>
            <input data-si-filter-q />
            <select data-si-filter-status>
                <option value="">All</option>
                <option value="detected">Detected</option>
                <option value="notified">Notified</option>
                <option value="resolved">Resolved</option>
            </select>
            <button data-si-filter-apply>Filter</button>
            <div data-si-list></div>
            <form data-si-create-form>
                <input name="title" value="" />
                <textarea name="description"></textarea>
                <input name="detected_at" value="" />
                <input name="affected_subjects_count" value="0" />
                <input name="affected_data_types" value="" />
                <input name="reported_to_bssn_at" value="" />
                <button type="submit">Create</button>
            </form>
            <div id="siDetailModal">
                <div id="siDetailModalBody"></div>
            </div>
            <div id="siCreateModal"></div>
        </div>
    `;
}

const INCIDENTS_PAYLOAD = {
    success: true,
    data: {
        data: [
            {
                uuid: "aaa-111",
                title: "Payroll data leak",
                description: "Unauthorized export detected",
                status: "detected",
                affected_data_types: ["salary", "bank_account_no"],
                affected_subjects_count: 5,
                affected_user_uuids: ["u1", "u2"],
                detected_at: "2026-06-01T10:00:00",
                reported_to_bssn_at: null,
                notifications_sent_at: null,
                resolved_at: null,
            },
            {
                uuid: "bbb-222",
                title: "Attendance breach",
                description: "GPS data exposed",
                status: "resolved",
                affected_data_types: ["location"],
                affected_subjects_count: 2,
                affected_user_uuids: [],
                detected_at: "2026-05-15T08:00:00",
                reported_to_bssn_at: "2026-05-16T09:00:00",
                notifications_sent_at: "2026-05-16T10:00:00",
                resolved_at: "2026-05-20T12:00:00",
            },
        ],
    },
};

describe("security-incidents-data wiring", () => {
    let fetchMock;

    beforeEach(() => {
        buildDom();

        window.__ARCAV_DISABLE_REDIRECTS__ = true;
        window.AuthApi = {
            getToken: vi.fn(() => "test-token-123"),
            handleUnauthorizedFromApi: vi.fn(() => false),
            handleForbiddenFromApi: vi.fn(() => false),
        };

        fetchMock = vi.fn((url, options = {}) => {
            const u = String(url);
            if (u.includes("/v1/admin/security-incidents?per_page=50") && options.method === "GET") {
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: async () => INCIDENTS_PAYLOAD,
                });
            }
            if (u.match(/\/v1\/admin\/security-incidents\/[a-z0-9-]+$/) && options.method === "GET") {
                const uuid = u.split("/").pop();
                const incident = INCIDENTS_PAYLOAD.data.data.find((r) => r.uuid === uuid);
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: async () => ({ success: true, data: incident }),
                });
            }
            if (u.includes("/notify-subjects") && options.method === "POST") {
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: async () => ({ success: true, data: { queued: true } }),
                });
            }
            if (u.includes("/resolve") && options.method === "POST") {
                return Promise.resolve({
                    ok: true,
                    status: 200,
                    json: async () => ({ success: true, data: { status: "resolved" } }),
                });
            }
            if (u === "/v1/admin/security-incidents" && options.method === "POST") {
                return Promise.resolve({
                    ok: true,
                    status: 201,
                    json: async () => ({ success: true, data: { uuid: "new-uuid", status: "detected" } }),
                });
            }
            return Promise.resolve({
                ok: false,
                status: 404,
                json: async () => ({ success: false, error: { code: "NOT_FOUND", message: "Not found" } }),
            });
        });
        vi.stubGlobal("fetch", fetchMock);

        window.bootstrap = {
            Modal: class {
                static getOrCreateInstance() {
                    return new window.bootstrap.Modal();
                }
                show() {}
                hide() {}
            },
        };
    });

    afterEach(() => {
        delete window.AuthApi;
        delete window.bootstrap;
        delete window.__ARCAV_DISABLE_REDIRECTS__;
    });

    async function loadModule() {
        vi.resetModules();
        await import("../../../frontend/resources/js/security/security-incidents-data.js");
        document.dispatchEvent(new Event("DOMContentLoaded"));
        await flush();
    }

    test("loads list and renders incidents on init", async () => {
        await loadModule();

        const list = document.querySelector("[data-si-list]");
        expect(list.innerHTML).toContain("Payroll data leak");
        expect(list.innerHTML).toContain("Attendance breach");

        // Stats updated
        expect(document.querySelector("[data-si-stat='total']").textContent).toBe("2");
        expect(document.querySelector("[data-si-stat='detected']").textContent).toBe("1");
        expect(document.querySelector("[data-si-stat='resolved']").textContent).toBe("1");
    });

    test("sends Authorization header with fetch requests", async () => {
        await loadModule();

        const [url, options] = fetchMock.mock.calls[0];
        expect(url).toContain("/v1/admin/security-incidents");
        expect(options.headers.Authorization).toBe("Bearer test-token-123");
    });

    test("renders status badges correctly", async () => {
        await loadModule();

        const list = document.querySelector("[data-si-list]");
        // detected → bg-danger
        expect(list.innerHTML).toContain("bg-danger");
        expect(list.innerHTML).toContain("Terdeteksi");
        // resolved → bg-success
        expect(list.innerHTML).toContain("bg-success");
        expect(list.innerHTML).toContain("Selesai");
    });

    test("shows action buttons based on status", async () => {
        await loadModule();

        const list = document.querySelector("[data-si-list]");
        // detected incident should have notify + resolve + detail buttons
        const detectedCard = list.innerHTML.split("Payroll data leak")[0];
        expect(list.innerHTML).toContain('data-si-action="notify"');
        expect(list.innerHTML).toContain('data-si-action="resolve"');
        expect(list.innerHTML).toContain('data-si-action="detail"');
    });

    test("empty list shows info message", async () => {
        fetchMock = vi.fn(() =>
            Promise.resolve({
                ok: true,
                status: 200,
                json: async () => ({ success: true, data: { data: [] } }),
            })
        );
        vi.stubGlobal("fetch", fetchMock);

        await loadModule();

        const list = document.querySelector("[data-si-list]");
        expect(list.innerHTML).toContain("Tidak ada insiden keamanan tercatat");
    });

    test("create form sends POST with correct body", async () => {
        await loadModule();

        const form = document.querySelector("[data-si-create-form]");
        form.querySelector('[name="title"]').value = "New incident";
        form.querySelector('[name="description"]').value = "Something happened";
        form.querySelector('[name="detected_at"]').value = "2026-06-10T10:00:00";
        form.querySelector('[name="affected_subjects_count"]').value = "3";
        form.querySelector('[name="affected_data_types"]').value = "salary, npwp";

        form.dispatchEvent(new Event("submit", { bubbles: true }));
        await flush(4);

        const postCall = fetchMock.mock.calls.find(
            ([, opts]) => opts && opts.method === "POST" && String(fetchMock.mock.calls.find((c) => c[1] === opts)[0]) === "/v1/admin/security-incidents"
        );
        expect(postCall).toBeTruthy();
        const body = JSON.parse(postCall[1].body);
        expect(body.title).toBe("New incident");
        expect(body.description).toBe("Something happened");
        expect(body.affected_data_types).toEqual(["salary", "npwp"]);
        expect(body.affected_subjects_count).toBe(3);
    });

    test("filter by status works", async () => {
        await loadModule();

        // Select "resolved" filter
        const statusSelect = document.querySelector("[data-si-filter-status]");
        statusSelect.value = "resolved";

        const filterBtn = document.querySelector("[data-si-filter-apply]");
        filterBtn.click();
        await flush();

        const list = document.querySelector("[data-si-list]");
        // Should only show resolved incident
        expect(list.innerHTML).toContain("Attendance breach");
        expect(list.innerHTML).not.toContain("Payroll data leak");
    });

    test("filter by search query works", async () => {
        await loadModule();

        const qInput = document.querySelector("[data-si-filter-q]");
        qInput.value = "payroll";

        const filterBtn = document.querySelector("[data-si-filter-apply]");
        filterBtn.click();
        await flush();

        const list = document.querySelector("[data-si-list]");
        expect(list.innerHTML).toContain("Payroll data leak");
        expect(list.innerHTML).not.toContain("Attendance breach");
    });

    test("XSS protection — title is escaped", async () => {
        const xssPayload = {
            success: true,
            data: {
                data: [
                    {
                        uuid: "xss-111",
                        title: '<img src=x onerror="window.__xss=true">',
                        description: "test",
                        status: "detected",
                        affected_data_types: [],
                        affected_subjects_count: 0,
                        affected_user_uuids: [],
                        detected_at: "2026-06-01T10:00:00",
                    },
                ],
            },
        };

        fetchMock = vi.fn(() =>
            Promise.resolve({
                ok: true,
                status: 200,
                json: async () => xssPayload,
            })
        );
        vi.stubGlobal("fetch", fetchMock);

        await loadModule();

        const list = document.querySelector("[data-si-list]");
        expect(list.querySelector("img")).toBeNull();
        expect(window.__xss).toBeUndefined();
        expect(list.innerHTML).toContain("&lt;img");
    });

    test("API error shows error toast", async () => {
        fetchMock = vi.fn(() =>
            Promise.resolve({
                ok: false,
                status: 403,
                json: async () => ({ success: false, error: { code: "AUTH_FORBIDDEN", message: "Forbidden" } }),
            })
        );
        vi.stubGlobal("fetch", fetchMock);

        await loadModule();

        const list = document.querySelector("[data-si-list]");
        expect(list.innerHTML).toContain("Gagal memuat data");
    });
});

const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");
const { test, expect, request: apiRequest } = require("@playwright/test");

const WP_CONTAINER = process.env.WP_CONTAINER;
const WP_USERNAME = process.env.WP_USERNAME || "admin";
const LOCAL_ENV_PLUGIN = "/var/www/html/wp-content/mu-plugins/surveyjs-pro-e2e-local-env.php";
const PROTOCOL_VERSION = "2025-06-18";

function docker(args, input) {
  return execFileSync("docker", ["exec", "-i", WP_CONTAINER, ...args], { input, stdio: ["pipe", "pipe", "ignore"] }).toString();
}

function fixture(...args) {
  const output = docker(["php", "--", ...args.map(String)], fs.readFileSync(path.join(__dirname, "fixtures/ai.php")));
  return JSON.parse(output.trim().split("\n").pop());
}

test.describe("AI connection (MCP) with Pro", () => {
  test.skip(!WP_CONTAINER, "Set WP_CONTAINER to run PHP in the WordPress container");

  let state = null;
  let api = null;
  let session = null;
  let id = 0;

  async function rpc(method, params = {}) {
    const headers = { "Content-Type": "application/json", Accept: "application/json, text/event-stream", Authorization: state.header };
    if (session) Object.assign(headers, { "Mcp-Session-Id": session, "MCP-Protocol-Version": PROTOCOL_VERSION });
    const response = await api.post(state.endpoint, { headers, data: JSON.stringify({ jsonrpc: "2.0", id: ++id, method, params }) });
    expect(response.status(), await response.text()).toBe(200);
    session = response.headers()["mcp-session-id"] || session;
    return (await response.json()).result;
  }

  async function tool(name, args = {}) {
    const result = await rpc("tools/call", { name, arguments: args });
    expect(result.isError, JSON.stringify(result.content)).toBeFalsy();
    return result.structuredContent;
  }

  test.beforeAll(async ({ baseURL }) => {
    docker(["sh", "-c", `cat > ${LOCAL_ENV_PLUGIN}`], "<?php\nif ( ! defined( 'WP_ENVIRONMENT_TYPE' ) ) {\n    define( 'WP_ENVIRONMENT_TYPE', 'local' );\n}\n");
    state = fixture("setup", WP_USERNAME);
    api = await apiRequest.newContext({ baseURL, storageState: { cookies: [], origins: [] } });
  });

  test.afterAll(async () => {
    if (state) fixture("cleanup", WP_USERNAME, JSON.stringify(state));
    docker(["rm", "-f", LOCAL_ENV_PLUGIN]);
    if (api) await api.dispose();
  });

  test("Pro adds its settings and PDF setting descriptions to the SurveyJS MCP server", async () => {
    test.skip(state.adapter !== "active", "needs the MCP Adapter plugin (or a plugin that bundles it) to be active");
    await rpc("initialize", { protocolVersion: PROTOCOL_VERSION, capabilities: {}, clientInfo: { name: "e2e", version: "1" } });

    const tools = (await rpc("tools/list")).tools.map((item) => item.name);
    expect(tools).toEqual(expect.arrayContaining(["surveyjs-pro-get-settings", "surveyjs-pro-update-settings", "surveyjs-get-form-settings"]));

    const settings = await tool("surveyjs-pro-get-settings");
    expect(settings.settings).toHaveProperty("creator");
    expect(settings.settings).toHaveProperty("license_key_entered");
    expect(JSON.stringify(settings)).not.toContain(state.proSettings.license_key || "no-license-key");

    const theme = state.proSettings.admin_theme === "dark" ? "light" : "dark";
    const updated = await tool("surveyjs-pro-update-settings", { admin_theme: theme });
    expect(updated.changed).toEqual(["admin_theme"]);
    expect(fixture("pro-settings").admin_theme).toBe(theme);

    const form = await tool("surveyjs-get-form-settings", { id: state.form });
    expect(form.schema.proPdfFormat.enum).toContain("a4");
    expect(form.schema.proPdfAttachNotification.description).toContain("Attach a PDF");

    const changed = await tool("surveyjs-update-form-settings", { id: state.form, settings: { proPdfOrientation: "landscape", proPdfWatermark: "DRAFT" } });
    expect(changed.changed.sort()).toEqual(["proPdfOrientation", "proPdfWatermark"]);
    expect(changed.message).toContain("clicks Save");
    expect(JSON.parse(fixture("draft", WP_USERNAME, state.form).settings).proPdfOrientation).toBe("landscape");
  });
});

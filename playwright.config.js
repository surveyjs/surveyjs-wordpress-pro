const fs = require("fs");
const path = require("path");
const { defineConfig } = require("@playwright/test");

const demoEnv = path.join(__dirname, "../../demo/.env");
if (fs.existsSync(demoEnv)) {
  for (const line of fs.readFileSync(demoEnv, "utf8").split(/\r?\n/)) {
    const match = line.match(/^\s*([A-Za-z_][A-Za-z0-9_]*)\s*=\s*(.*?)\s*$/);
    if (match && process.env[match[1]] === undefined) {
      process.env[match[1]] = match[2].replace(/^(['"])(.*)\1$/, "$2");
    }
  }
}

const channel = process.env.PW_CHANNEL ?? "chrome";

module.exports = defineConfig({
  testDir: "tests/e2e",
  timeout: 180000,
  expect: { timeout: 30000 },
  workers: 1,
  retries: process.env.CI ? 2 : 0,
  reporter: process.env.CI ? [["github"], ["list"], ["blob"]] : "list",
  use: {
    baseURL: process.env.WP_BASE_URL || "http://localhost:9876",
    ...(channel && { channel }),
    viewport: { width: 1400, height: 900 },
    trace: "retain-on-failure"
  },
  projects: [
    { name: "setup", testMatch: /auth\.setup\.js/ },
    {
      name: "e2e",
      dependencies: ["setup"],
      use: { storageState: path.join(__dirname, "tests/e2e/.auth/admin.json") }
    }
  ]
});

const path = require("path");
const { execFileSync } = require("child_process");

const root = path.resolve(__dirname, "..");
const IMAGE = "wordpress:cli-php8.3";

execFileSync("docker", [
  "run", "--rm",
  "-v", `${root}:/app`,
  "-w", "/app",
  IMAGE,
  "wp", "i18n", "make-pot", ".", "languages/surveyjs-pro.pot",
  "--slug=surveyjs-pro",
  "--domain=surveyjs-pro",
  "--exclude=libs,scripts,vip,dist,test-results,playwright-report",
  "--headers=" + JSON.stringify({ "Report-Msgid-Bugs-To": "https://surveyjs.io/wordpress-pro" }),
  "--file-comment=Copyright (C) Devsoft Baltic OÜ. This file is distributed under the same license as the SurveyJS Forms Pro plugin."
], { stdio: "inherit", env: { ...process.env, MSYS_NO_PATHCONV: "1" } });

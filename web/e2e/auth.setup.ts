import { test as setup, expect } from "@playwright/test";
import path from "path";

const AUTH_FILE = path.join(__dirname, ".auth/user.json");

setup("authenticate", async ({ page, request }) => {
  // Hit the login API directly — faster than UI flow
  const apiBase = process.env.API_BASE_URL ?? "http://localhost:8000/api/v1";

  const res = await request.post(`${apiBase}/auth/login`, {
    data: {
      email: process.env.TEST_EMAIL ?? "admin@demo.com",
      password: process.env.TEST_PASSWORD ?? "password",
    },
  });

  const body = await res.json();
  const token: string = body?.data?.token ?? body?.token;

  expect(token, "Login must return a token").toBeTruthy();

  // Inject token into localStorage so the Zustand store picks it up
  await page.goto("/");
  await page.evaluate((t) => {
    const existing = JSON.parse(localStorage.getItem("smartpos-auth") ?? "{}");
    localStorage.setItem(
      "smartpos-auth",
      JSON.stringify({ state: { token: t, user: existing?.state?.user }, version: 0 }),
    );
  }, token);

  await page.context().storageState({ path: AUTH_FILE });
});

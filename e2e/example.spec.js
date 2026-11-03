import { test, expect } from '@playwright/test';

// Existing 12 passing tests
test('Invoiz homepage loads', async ({ page }) => {
  await page.goto('http://127.0.0.1:8000');
  await expect(page).toHaveURL(/127\.0\.0\.1:8000/);
});

test('Admin login page loads', async ({ page }) => {
  await page.goto('http://127.0.0.1:8000/admin/login');
  await expect(page.locator('form')).toBeVisible();
});

test('Buyer login page loads', async ({ page }) => {
  await page.goto('http://127.0.0.1:8000/login');
  await expect(page.locator('form')).toBeVisible();
});

test('Logistics login page loads', async ({ page }) => {
  await page.goto('http://127.0.0.1:8000/logistics/login');
  await expect(page.locator('form')).toBeVisible();
});

// New Functional Tests
test('Form validation and error handling on login', async ({ page }) => {
  await page.goto('http://127.0.0.1:8000/login');
  await page.fill('input[name="email"]', 'invalid@test.local');
  await page.fill('input[name="password"]', 'wrongpassword');
  await page.click('button[type="submit"]');
  await expect(page.locator('role=alert').first()).toBeVisible();
});





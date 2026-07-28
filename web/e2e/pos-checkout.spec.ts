/**
 * POS terminal — happy path E2E test.
 *
 * Prerequisites (real backend running or MSW mocks):
 *   - At least one product exists in the catalogue
 *   - Cash register is open for the authenticated user's branch
 */
import { test, expect } from "@playwright/test";

test.describe("POS checkout happy path", () => {
  test.beforeEach(async ({ page }) => {
    await page.goto("/pos");
    // Wait for POS page to be interactive
    await expect(page.getByRole("heading", { name: /POS Terminal/i })).toBeVisible({ timeout: 15_000 });
  });

  test("can search and add a product to the cart", async ({ page }) => {
    const searchInput = page.getByPlaceholder(/search products/i);
    await searchInput.fill("prod");

    // At least one product card should appear
    const productCard = page.locator("[data-testid='product-card']").first();
    // Fall back to any button in the product grid if testid not set
    const fallback = page.locator(".grid button").first();

    const card = (await productCard.count()) > 0 ? productCard : fallback;
    await expect(card).toBeVisible({ timeout: 5_000 });
    await card.click();

    // Cart should show the item
    await expect(page.locator("[data-testid='cart-item'], .cart-item").first()).toBeVisible({ timeout: 3_000 });
  });

  test("payment modal opens and can complete checkout", async ({ page }) => {
    // Add first visible product
    const productBtn = page.locator(".grid button").first();
    if ((await productBtn.count()) === 0) {
      test.skip(true, "No products visible — seed data required");
      return;
    }
    await productBtn.click();

    // Open payment modal
    const checkoutBtn = page.getByRole("button", { name: /checkout/i });
    await expect(checkoutBtn).toBeEnabled({ timeout: 3_000 });
    await checkoutBtn.click();

    // Payment modal title
    await expect(page.getByRole("dialog").getByText(/payment/i)).toBeVisible({ timeout: 3_000 });

    // Select cash payment method
    const cashOption = page.getByRole("button", { name: /cash/i });
    if ((await cashOption.count()) > 0) await cashOption.click();

    // Confirm payment
    const confirmBtn = page.getByRole("button", { name: /confirm|pay|complete/i }).last();
    await confirmBtn.click();

    // Receipt modal should appear
    await expect(page.getByText(/receipt|thank you|sale complete/i)).toBeVisible({ timeout: 8_000 });
  });

  test("can remove an item from the cart", async ({ page }) => {
    const productBtn = page.locator(".grid button").first();
    if ((await productBtn.count()) === 0) {
      test.skip(true, "No products visible — seed data required");
      return;
    }
    await productBtn.click();

    // Wait for cart item
    const removeBtn = page.getByRole("button", { name: /remove|×|trash/i }).first();
    if ((await removeBtn.count()) > 0) {
      await removeBtn.click();
      // Cart should be empty
      await expect(page.getByText(/cart is empty|no items/i)).toBeVisible({ timeout: 3_000 });
    }
  });
});

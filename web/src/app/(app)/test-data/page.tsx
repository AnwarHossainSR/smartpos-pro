"use client";

import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { toast } from "@/components/ui/toast";
import { Check, Copy } from "lucide-react";
import { useState } from "react";

function CopyButton({ text, label }: { text: string; label: string }) {
  const [copied, setCopied] = useState(false);

  const handleCopy = async () => {
    await navigator.clipboard.writeText(text);
    setCopied(true);
    toast(`Copied ${label}`);
    setTimeout(() => setCopied(false), 2000);
  };

  return (
    <button
      onClick={handleCopy}
      className="inline-flex items-center gap-1.5 rounded-md border border-[var(--border)] bg-[var(--surface)] px-2.5 py-1 text-xs font-medium transition-colors hover:bg-[var(--surface-2)]"
    >
      {copied ? <Check className="h-3 w-3" /> : <Copy className="h-3 w-3" />}
      {copied ? "Copied" : "Copy"}
    </button>
  );
}

interface Section {
  title: string;
  description: string;
  data: string;
}

const SECTIONS: Section[] = [
  // ── Registration ──
  {
    title: "1. Register",
    description: "Fill the registration form at /register",
    data: `Name: Admin User
Business Name: Demo Store BD
Email: admin@demostore.com
Password: password123`,
  },
  // ── Business Settings ──
  {
    title: "2. Business Settings",
    description: "Fill at /settings",
    data: `Business Name: Demo Store BD
Email: admin@demostore.com
Phone: +880 1712-345678
Address: 123 Demo Road, Dhaka 1205, Bangladesh
Tax Number: 1234567890
Currency: BDT
Timezone: Asia/Dhaka
Date Format: YYYY-MM-DD`,
  },
  // ── Tax Rates ──
  {
    title: "3. Tax Rates",
    description: "Add at /settings/tax → Tax Rates tab",
    data: `VAT 15%  |  15%  |  inclusive
WHT 10%  |  10%  |  exclusive
Zero Tax  |  0%  |  inclusive`,
  },
  // ── Tax Groups ──
  {
    title: "4. Tax Groups",
    description: "Add at /settings/tax → Tax Groups tab",
    data: `Standard VAT → VAT 15%
WHT + VAT → WHT 10%, VAT 15%`,
  },
  // ── Tax Zones ──
  {
    title: "5. Tax Zones",
    description: "Add at /settings/tax → Tax Zones tab",
    data: `Domestic → Bangladesh`,
  },
  // ── Categories ──
  {
    title: "6. Categories",
    description: "Create via the product form dropdown",
    data: `Electronics
Groceries
Clothing
Stationery`,
  },
  // ── Brands ──
  {
    title: "7. Brands",
    description: "Create via the product form dropdown",
    data: `Samsung
Unilever
Nokia
Local Brand`,
  },
  // ── Units ──
  {
    title: "8. Units",
    description: "Create via the product form dropdown",
    data: `piece
kg
box
pair
liter`,
  },
  // ── Products ──
  {
    title: "9. Products",
    description: "Add 6 products at /products",
    data: `Samsung Galaxy A15  |  SAM-A15  |  Electronics  |  Samsung  |  piece  |  Cost: 12000  |  Price: 15999  |  Tax: Standard VAT  |  Stock: 50  |  Min: 10
Nokia 105  |  NK-105  |  Electronics  |  Nokia  |  piece  |  Cost: 1500  |  Price: 2200  |  Tax: Standard VAT  |  Stock: 30  |  Min: 5
Rice 5kg Bag  |  RCE-5KG  |  Groceries  |  Local Brand  |  box  |  Cost: 350  |  Price: 450  |  Tax: Zero Tax  |  Stock: 100  |  Min: 20
Sunlight Soap  |  SUN-01  |  Groceries  |  Unilever  |  piece  |  Cost: 35  |  Price: 50  |  Tax: Standard VAT  |  Stock: 200  |  Min: 30
Pencil 10-Pack  |  PEN-10  |  Stationery  |  Local Brand  |  box  |  Cost: 40  |  Price: 60  |  Tax: Zero Tax  |  Stock: 80  |  Min: 15
T-Shirt Basic  |  TSH-01  |  Clothing  |  Local Brand  |  piece  |  Cost: 180  |  Price: 350  |  Tax: Standard VAT  |  Stock: 40  |  Min: 10`,
  },
  // ── Customers ──
  {
    title: "10. Customers",
    description: "Add 4 customers at /customers",
    data: `Walk-in Customer  |  01700-000001
Rahim Uddin  |  01712-345678  |  rahim@test.com  |  456 Elephant Road, Dhaka
Karim Store  |  01812-345679  |  karim@store.com  |  78 Agrabad, Chittagong
Fatima Begum  |  01912-345680  |  fatima@test.com  |  321 Mirpur, Dhaka`,
  },
  // ── Suppliers ──
  {
    title: "11. Suppliers",
    description: "Add 3 suppliers at /suppliers",
    data: `Tech Distributors BD  |  01711-111111  |  sales@techdist.com  |  12 Electronic Market, Dhaka  |  Balance: 50000
Fresh Foods Ltd  |  01811-222222  |  info@freshfoods.com  |  45 Farmgate, Dhaka  |  Balance: 25000
Cloth House Wholesale  |  01911-333333  |  order@clothhouse.com  |  78 Zindabazar, Sylhet  |  Balance: 0`,
  },
  // ── Cash Register ──
  {
    title: "12. Cash Register",
    description: "Create and open at /cash-register",
    data: `Register Name: Main Register  |  Branch: Main Branch
Opening Cash: 5000`,
  },
  // ── Sale 1 ──
  {
    title: "13. Sale 1 — Cash",
    description: "POS → checkout",
    data: `Samsung Galaxy A15 × 1
Sunlight Soap × 2
Customer: Walk-in Customer
Payment: Cash 16099`,
  },
  // ── Sale 2 ──
  {
    title: "14. Sale 2 — Small Cash",
    description: "POS → checkout",
    data: `Rice 5kg Bag × 1
Pencil 10-Pack × 3
Customer: Rahim Uddin
Payment: Cash 580`,
  },
  // ── Sale 3 ──
  {
    title: "15. Sale 3 — Credit",
    description: "POS → checkout (partial payment)",
    data: `Nokia 105 × 2
T-Shirt Basic × 1 (discount: 50)
Customer: Karim Store
Payment: Cash 3000  |  Total: 4350  |  Due: 1350`,
  },
  // ── Sale 4 ──
  {
    title: "16. Sale 4 — Park/Hold",
    description: "POS → click Park (F4)",
    data: `Samsung Galaxy A15 × 1
→ Click Park button`,
  },
  // ── Sale 5 ──
  {
    title: "17. Sale 5 — Another Cash",
    description: "POS → checkout",
    data: `Sunlight Soap × 5
Pencil 10-Pack × 2
Customer: Walk-in Customer
Payment: Cash 370`,
  },
  // ── Close Register ──
  {
    title: "18. Close Register",
    description: "Close session at /cash-register",
    data: `Actual Cash Counted: 21679`,
  },
  // ── Purchase 1 ──
  {
    title: "19. Purchase Order 1",
    description: "Create at /purchases",
    data: `Supplier: Tech Distributors BD
Warehouse: Main Warehouse
Samsung Galaxy A15  |  Qty: 20  |  Cost: 12000
Nokia 105  |  Qty: 30  |  Cost: 1500
Total: 285000`,
  },
  // ── Purchase 2 ──
  {
    title: "20. Purchase Order 2",
    description: "Create at /purchases",
    data: `Supplier: Fresh Foods Ltd
Warehouse: Main Warehouse
Rice 5kg Bag  |  Qty: 50  |  Cost: 350
Sunlight Soap  |  Qty: 100  |  Cost: 35
Total: 21000`,
  },
  // ── Expenses ──
  {
    title: "21. Expenses",
    description: "Record at /expenses",
    data: `Rent  |  Monthly shop rent - August  |  25000  |  bank_transfer  |  2026-08-01
Utilities  |  Electricity bill  |  3500  |  cash  |  2026-08-10
Transport  |  Delivery fuel cost  |  1200  |  cash  |  2026-08-15`,
  },
  // ── Team ──
  {
    title: "22. Team Members",
    description: "Invite at /team",
    data: `Sales User  |  sales@testshop.com  |  cashier
Manager User  |  manager@testshop.com  |  manager`,
  },
  // ── Coupons ──
  {
    title: "23. Coupons",
    description: "Create at /loyalty",
    data: `WELCOME10  |  percentage  |  10%  |  min: 500  |  max uses: 100  |  expiry: 2026-12-31
FLAT200  |  fixed  |  200  |  min: 2000  |  max uses: 50  |  expiry: 2026-12-31`,
  },
  // ── Webhooks ──
  {
    title: "24. Webhook",
    description: "Add at /settings/webhooks",
    data: `URL: https://webhook.site/test-unique-url
Events: sale.completed, customer.created`,
  },
  // ── Data Counts ──
  {
    title: "25. Data Counts (Verify)",
    description: "Expected counts after all steps above",
    data: `Products: 6
Categories: 4
Brands: 4
Units: 5
Customers: 4
Suppliers: 3
Sales (completed): 4
Sales (held): 1
Cash register sessions: 1 (closed)
Purchases: 2
Expenses: 3
Team members: 3 (including you)
Coupons: 2
Tax rates: 3
Tax groups: 2
Webhooks: 1
Shifts: 1`,
  },
];

export default function TestDataPage() {
  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-xl font-semibold">Test Data Guide</h1>
        <p className="mt-1 text-sm text-[var(--muted)]">
          Copy each section below and paste into the corresponding page.
          Start with a fresh database.
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        {SECTIONS.map((section) => (
          <Card key={section.title}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-3">
              <CardTitle className="text-sm font-medium">{section.title}</CardTitle>
              <CopyButton text={section.data} label={section.title} />
            </CardHeader>
            <CardContent>
              <p className="mb-3 text-xs text-[var(--muted)]">{section.description}</p>
              <pre className="whitespace-pre-wrap rounded-md bg-[var(--surface-2)] p-3 text-xs leading-relaxed">
                {section.data}
              </pre>
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}

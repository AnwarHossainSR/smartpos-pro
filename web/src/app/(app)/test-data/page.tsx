import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { toast } from "@/components/ui/toast";
import { Check, Copy } from "lucide-react";
import { useState } from "react";

function CopyIcon({ text, label }: { text: string; label: string }) {
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
      className="ml-1 inline-flex h-5 w-5 shrink-0 items-center justify-center rounded text-[var(--muted)] transition-colors hover:bg-[var(--surface-2)] hover:text-[var(--foreground)]"
      title={`Copy ${label}`}
    >
      {copied ? <Check className="h-3 w-3 text-green-500" /> : <Copy className="h-3 w-3" />}
    </button>
  );
}

function Field({ label, value }: { label: string; value: string }) {
  return (
    <div className="flex items-center justify-between gap-2 py-1">
      <span className="text-xs text-[var(--muted)]">{label}</span>
      <div className="flex items-center gap-1">
        <span className="rounded bg-[var(--surface-2)] px-2 py-0.5 font-mono text-xs">{value}</span>
        <CopyIcon text={value} label={label} />
      </div>
    </div>
  );
}

function Row({ values }: { values: { label: string; value: string }[] }) {
  return (
    <div className="flex flex-wrap items-center gap-x-3 gap-y-1 rounded-md bg-[var(--surface-2)] px-3 py-1.5">
      {values.map((v) => (
        <div key={v.label} className="flex items-center gap-1">
          <span className="text-xs text-[var(--muted)]">{v.label}:</span>
          <span className="font-mono text-xs">{v.value}</span>
          <CopyIcon text={v.value} label={v.label} />
        </div>
      ))}
    </div>
  );
}

interface Section {
  title: string;
  description: string;
  fields: { label: string; value: string }[];
  rows?: { label: string; value: string }[][];
}

const SECTIONS: Section[] = [
  // ── Registration ──
  {
    title: "1. Register",
    description: "Fill the registration form at /register",
    fields: [
      { label: "Name", value: "Admin User" },
      { label: "Business Name", value: "Demo Store BD" },
      { label: "Email", value: "admin@demostore.com" },
      { label: "Password", value: "password123" },
    ],
  },
  // ── Business Settings ──
  {
    title: "2. Business Settings",
    description: "Fill at /settings",
    fields: [
      { label: "Business Name", value: "Demo Store BD" },
      { label: "Email", value: "admin@demostore.com" },
      { label: "Phone", value: "+880 1712-345678" },
      { label: "Address", value: "123 Demo Road, Dhaka 1205, Bangladesh" },
      { label: "Tax Number", value: "1234567890" },
      { label: "Currency", value: "BDT" },
      { label: "Timezone", value: "Asia/Dhaka" },
      { label: "Date Format", value: "YYYY-MM-DD" },
    ],
  },
  // ── Tax Rates ──
  {
    title: "3. Tax Rates",
    description: "Add at /settings/tax → Tax Rates tab",
    fields: [],
    rows: [
      [{ label: "Name", value: "VAT 15%" }, { label: "Rate", value: "15%" }, { label: "Type", value: "inclusive" }],
      [{ label: "Name", value: "WHT 10%" }, { label: "Rate", value: "10%" }, { label: "Type", value: "exclusive" }],
      [{ label: "Name", value: "Zero Tax" }, { label: "Rate", value: "0%" }, { label: "Type", value: "inclusive" }],
    ],
  },
  // ── Tax Groups ──
  {
    title: "4. Tax Groups",
    description: "Add at /settings/tax → Tax Groups tab",
    fields: [],
    rows: [
      [{ label: "Name", value: "Standard VAT" }, { label: "Rates", value: "VAT 15%" }],
      [{ label: "Name", value: "WHT + VAT" }, { label: "Rates", value: "WHT 10%, VAT 15%" }],
    ],
  },
  // ── Tax Zones ──
  {
    title: "5. Tax Zones",
    description: "Add at /settings/tax → Tax Zones tab",
    fields: [
      { label: "Name", value: "Domestic" },
      { label: "Country", value: "Bangladesh" },
    ],
  },
  // ── Categories ──
  {
    title: "6. Categories",
    description: "Create via the product form dropdown",
    fields: [
      { label: "Category 1", value: "Electronics" },
      { label: "Category 2", value: "Groceries" },
      { label: "Category 3", value: "Clothing" },
      { label: "Category 4", value: "Stationery" },
    ],
  },
  // ── Brands ──
  {
    title: "7. Brands",
    description: "Create via the product form dropdown",
    fields: [
      { label: "Brand 1", value: "Samsung" },
      { label: "Brand 2", value: "Unilever" },
      { label: "Brand 3", value: "Nokia" },
      { label: "Brand 4", value: "Local Brand" },
    ],
  },
  // ── Units ──
  {
    title: "8. Units",
    description: "Create via the product form dropdown",
    fields: [
      { label: "Unit 1", value: "piece" },
      { label: "Unit 2", value: "kg" },
      { label: "Unit 3", value: "box" },
      { label: "Unit 4", value: "pair" },
      { label: "Unit 5", value: "liter" },
    ],
  },
  // ── Products ──
  {
    title: "9. Products",
    description: "Add 6 products at /products",
    fields: [],
    rows: [
      [
        { label: "Name", value: "Samsung Galaxy A15" },
        { label: "SKU", value: "SAM-A15" },
        { label: "Category", value: "Electronics" },
        { label: "Brand", value: "Samsung" },
        { label: "Unit", value: "piece" },
        { label: "Cost", value: "12000" },
        { label: "Price", value: "15999" },
        { label: "Tax", value: "Standard VAT" },
        { label: "Stock", value: "50" },
        { label: "Min", value: "10" },
      ],
      [
        { label: "Name", value: "Nokia 105" },
        { label: "SKU", value: "NK-105" },
        { label: "Category", value: "Electronics" },
        { label: "Brand", value: "Nokia" },
        { label: "Unit", value: "piece" },
        { label: "Cost", value: "1500" },
        { label: "Price", value: "2200" },
        { label: "Tax", value: "Standard VAT" },
        { label: "Stock", value: "30" },
        { label: "Min", value: "5" },
      ],
      [
        { label: "Name", value: "Rice 5kg Bag" },
        { label: "SKU", value: "RCE-5KG" },
        { label: "Category", value: "Groceries" },
        { label: "Brand", value: "Local Brand" },
        { label: "Unit", value: "box" },
        { label: "Cost", value: "350" },
        { label: "Price", value: "450" },
        { label: "Tax", value: "Zero Tax" },
        { label: "Stock", value: "100" },
        { label: "Min", value: "20" },
      ],
      [
        { label: "Name", value: "Sunlight Soap" },
        { label: "SKU", value: "SUN-01" },
        { label: "Category", value: "Groceries" },
        { label: "Brand", value: "Unilever" },
        { label: "Unit", value: "piece" },
        { label: "Cost", value: "35" },
        { label: "Price", value: "50" },
        { label: "Tax", value: "Standard VAT" },
        { label: "Stock", value: "200" },
        { label: "Min", value: "30" },
      ],
      [
        { label: "Name", value: "Pencil 10-Pack" },
        { label: "SKU", value: "PEN-10" },
        { label: "Category", value: "Stationery" },
        { label: "Brand", value: "Local Brand" },
        { label: "Unit", value: "box" },
        { label: "Cost", value: "40" },
        { label: "Price", value: "60" },
        { label: "Tax", value: "Zero Tax" },
        { label: "Stock", value: "80" },
        { label: "Min", value: "15" },
      ],
      [
        { label: "Name", value: "T-Shirt Basic" },
        { label: "SKU", value: "TSH-01" },
        { label: "Category", value: "Clothing" },
        { label: "Brand", value: "Local Brand" },
        { label: "Unit", value: "piece" },
        { label: "Cost", value: "180" },
        { label: "Price", value: "350" },
        { label: "Tax", value: "Standard VAT" },
        { label: "Stock", value: "40" },
        { label: "Min", value: "10" },
      ],
    ],
  },
  // ── Customers ──
  {
    title: "10. Customers",
    description: "Add 4 customers at /customers",
    fields: [],
    rows: [
      [{ label: "Name", value: "Walk-in Customer" }, { label: "Phone", value: "01700-000001" }],
      [{ label: "Name", value: "Rahim Uddin" }, { label: "Phone", value: "01712-345678" }, { label: "Email", value: "rahim@test.com" }, { label: "Address", value: "456 Elephant Road, Dhaka" }],
      [{ label: "Name", value: "Karim Store" }, { label: "Phone", value: "01812-345679" }, { label: "Email", value: "karim@store.com" }, { label: "Address", value: "78 Agrabad, Chittagong" }],
      [{ label: "Name", value: "Fatima Begum" }, { label: "Phone", value: "01912-345680" }, { label: "Email", value: "fatima@test.com" }, { label: "Address", value: "321 Mirpur, Dhaka" }],
    ],
  },
  // ── Suppliers ──
  {
    title: "11. Suppliers",
    description: "Add 3 suppliers at /suppliers",
    fields: [],
    rows: [
      [{ label: "Name", value: "Tech Distributors BD" }, { label: "Phone", value: "01711-111111" }, { label: "Email", value: "sales@techdist.com" }, { label: "Address", value: "12 Electronic Market, Dhaka" }, { label: "Balance", value: "50000" }],
      [{ label: "Name", value: "Fresh Foods Ltd" }, { label: "Phone", value: "01811-222222" }, { label: "Email", value: "info@freshfoods.com" }, { label: "Address", value: "45 Farmgate, Dhaka" }, { label: "Balance", value: "25000" }],
      [{ label: "Name", value: "Cloth House Wholesale" }, { label: "Phone", value: "01911-333333" }, { label: "Email", value: "order@clothhouse.com" }, { label: "Address", value: "78 Zindabazar, Sylhet" }, { label: "Balance", value: "0" }],
    ],
  },
  // ── Cash Register ──
  {
    title: "12. Cash Register",
    description: "Create and open at /cash-register",
    fields: [
      { label: "Register Name", value: "Main Register" },
      { label: "Branch", value: "Main Branch" },
      { label: "Opening Cash", value: "5000" },
    ],
  },
  // ── Sale 1 ──
  {
    title: "13. Sale 1 — Cash",
    description: "POS → checkout",
    fields: [],
    rows: [
      [{ label: "Item", value: "Samsung Galaxy A15" }, { label: "Qty", value: "1" }],
      [{ label: "Item", value: "Sunlight Soap" }, { label: "Qty", value: "2" }],
      [{ label: "Customer", value: "Walk-in Customer" }, { label: "Payment", value: "Cash" }, { label: "Amount", value: "16099" }],
    ],
  },
  // ── Sale 2 ──
  {
    title: "14. Sale 2 — Small Cash",
    description: "POS → checkout",
    fields: [],
    rows: [
      [{ label: "Item", value: "Rice 5kg Bag" }, { label: "Qty", value: "1" }],
      [{ label: "Item", value: "Pencil 10-Pack" }, { label: "Qty", value: "3" }],
      [{ label: "Customer", value: "Rahim Uddin" }, { label: "Payment", value: "Cash" }, { label: "Amount", value: "580" }],
    ],
  },
  // ── Sale 3 ──
  {
    title: "15. Sale 3 — Credit",
    description: "POS → checkout (partial payment)",
    fields: [],
    rows: [
      [{ label: "Item", value: "Nokia 105" }, { label: "Qty", value: "2" }],
      [{ label: "Item", value: "T-Shirt Basic" }, { label: "Qty", value: "1" }, { label: "Discount", value: "50" }],
      [{ label: "Customer", value: "Karim Store" }, { label: "Payment", value: "Cash" }, { label: "Amount", value: "3000" }, { label: "Total", value: "4350" }, { label: "Due", value: "1350" }],
    ],
  },
  // ── Sale 4 ──
  {
    title: "16. Sale 4 — Park/Hold",
    description: "POS → click Park (F4)",
    fields: [],
    rows: [
      [{ label: "Item", value: "Samsung Galaxy A15" }, { label: "Qty", value: "1" }],
      [{ label: "Action", value: "Click Park button" }],
    ],
  },
  // ── Sale 5 ──
  {
    title: "17. Sale 5 — Another Cash",
    description: "POS → checkout",
    fields: [],
    rows: [
      [{ label: "Item", value: "Sunlight Soap" }, { label: "Qty", value: "5" }],
      [{ label: "Item", value: "Pencil 10-Pack" }, { label: "Qty", value: "2" }],
      [{ label: "Customer", value: "Walk-in Customer" }, { label: "Payment", value: "Cash" }, { label: "Amount", value: "370" }],
    ],
  },
  // ── Close Register ──
  {
    title: "18. Close Register",
    description: "Close session at /cash-register",
    fields: [
      { label: "Actual Cash Counted", value: "21679" },
    ],
  },
  // ── Purchase 1 ──
  {
    title: "19. Purchase Order 1",
    description: "Create at /purchases",
    fields: [
      { label: "Supplier", value: "Tech Distributors BD" },
      { label: "Warehouse", value: "Main Warehouse" },
    ],
    rows: [
      [{ label: "Item", value: "Samsung Galaxy A15" }, { label: "Qty", value: "20" }, { label: "Cost", value: "12000" }],
      [{ label: "Item", value: "Nokia 105" }, { label: "Qty", value: "30" }, { label: "Cost", value: "1500" }],
      [{ label: "Total", value: "285000" }],
    ],
  },
  // ── Purchase 2 ──
  {
    title: "20. Purchase Order 2",
    description: "Create at /purchases",
    fields: [
      { label: "Supplier", value: "Fresh Foods Ltd" },
      { label: "Warehouse", value: "Main Warehouse" },
    ],
    rows: [
      [{ label: "Item", value: "Rice 5kg Bag" }, { label: "Qty", value: "50" }, { label: "Cost", value: "350" }],
      [{ label: "Item", value: "Sunlight Soap" }, { label: "Qty", value: "100" }, { label: "Cost", value: "35" }],
      [{ label: "Total", value: "21000" }],
    ],
  },
  // ── Expenses ──
  {
    title: "21. Expenses",
    description: "Record at /expenses",
    fields: [],
    rows: [
      [{ label: "Category", value: "Rent" }, { label: "Description", value: "Monthly shop rent - August" }, { label: "Amount", value: "25000" }, { label: "Method", value: "bank_transfer" }, { label: "Date", value: "2026-08-01" }],
      [{ label: "Category", value: "Utilities" }, { label: "Description", value: "Electricity bill" }, { label: "Amount", value: "3500" }, { label: "Method", value: "cash" }, { label: "Date", value: "2026-08-10" }],
      [{ label: "Category", value: "Transport" }, { label: "Description", value: "Delivery fuel cost" }, { label: "Amount", value: "1200" }, { label: "Method", value: "cash" }, { label: "Date", value: "2026-08-15" }],
    ],
  },
  // ── Team ──
  {
    title: "22. Team Members",
    description: "Invite at /team",
    fields: [],
    rows: [
      [{ label: "Name", value: "Sales User" }, { label: "Email", value: "sales@testshop.com" }, { label: "Role", value: "cashier" }],
      [{ label: "Name", value: "Manager User" }, { label: "Email", value: "manager@testshop.com" }, { label: "Role", value: "manager" }],
    ],
  },
  // ── Coupons ──
  {
    title: "23. Coupons",
    description: "Create at /loyalty",
    fields: [],
    rows: [
      [{ label: "Code", value: "WELCOME10" }, { label: "Type", value: "percentage" }, { label: "Value", value: "10%" }, { label: "Min Purchase", value: "500" }, { label: "Max Uses", value: "100" }, { label: "Expiry", value: "2026-12-31" }],
      [{ label: "Code", value: "FLAT200" }, { label: "Type", value: "fixed" }, { label: "Value", value: "200" }, { label: "Min Purchase", value: "2000" }, { label: "Max Uses", value: "50" }, { label: "Expiry", value: "2026-12-31" }],
    ],
  },
  // ── Webhooks ──
  {
    title: "24. Webhook",
    description: "Add at /settings/webhooks",
    fields: [
      { label: "URL", value: "https://webhook.site/test-unique-url" },
      { label: "Events", value: "sale.completed, customer.created" },
    ],
  },
  // ── Data Counts ──
  {
    title: "25. Data Counts (Verify)",
    description: "Expected counts after all steps above",
    fields: [
      { label: "Products", value: "6" },
      { label: "Categories", value: "4" },
      { label: "Brands", value: "4" },
      { label: "Units", value: "5" },
      { label: "Customers", value: "4" },
      { label: "Suppliers", value: "3" },
      { label: "Sales (completed)", value: "4" },
      { label: "Sales (held)", value: "1" },
      { label: "Cash register sessions", value: "1 (closed)" },
      { label: "Purchases", value: "2" },
      { label: "Expenses", value: "3" },
      { label: "Team members", value: "3 (including you)" },
      { label: "Coupons", value: "2" },
      { label: "Tax rates", value: "3" },
      { label: "Tax groups", value: "2" },
      { label: "Webhooks", value: "1" },
      { label: "Shifts", value: "1" },
    ],
  },
];

export default function TestDataPage() {
  return (
    <div className="space-y-4">
      <div>
        <h1 className="text-xl font-semibold">Test Data Guide</h1>
        <p className="mt-1 text-sm text-[var(--muted)]">
          Click the copy icon next to any value to copy it. Start with a fresh database.
        </p>
      </div>

      <div className="grid grid-cols-1 gap-4 xl:grid-cols-2">
        {SECTIONS.map((section) => (
          <Card key={section.title}>
            <CardHeader className="pb-3">
              <CardTitle className="text-sm font-medium">{section.title}</CardTitle>
              <p className="text-xs text-[var(--muted)]">{section.description}</p>
            </CardHeader>
            <CardContent className="space-y-2">
              {section.fields.map((f) => (
                <Field key={f.label} label={f.label} value={f.value} />
              ))}
              {section.rows?.map((row, i) => (
                <Row key={i} values={row} />
              ))}
            </CardContent>
          </Card>
        ))}
      </div>
    </div>
  );
}

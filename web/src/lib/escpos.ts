const ESC = 0x1b;
const GS = 0x1d;

export class EscPos {
  private buffer: number[] = [];

  text(str: string): this {
    for (let i = 0; i < str.length; i++) {
      this.buffer.push(str.charCodeAt(i));
    }
    this.buffer.push(0x0a);
    return this;
  }

  bold(): this {
    this.buffer.push(ESC, 0x45, 0x01);
    return this;
  }

  boldOff(): this {
    this.buffer.push(ESC, 0x45, 0x00);
    return this;
  }

  alignLeft(): this {
    this.buffer.push(ESC, 0x61, 0x00);
    return this;
  }

  alignCenter(): this {
    this.buffer.push(ESC, 0x61, 0x01);
    return this;
  }

  alignRight(): this {
    this.buffer.push(ESC, 0x61, 0x02);
    return this;
  }

  feed(n: number = 1): this {
    for (let i = 0; i < n; i++) {
      this.buffer.push(0x0a);
    }
    return this;
  }

  cut(full: boolean = false): this {
    this.buffer.push(GS, 0x56, full ? 0x00 : 0x01);
    return this;
  }

  drawer(): this {
    this.buffer.push(ESC, 0x70, 0x00, 0x32, 0x4e);
    return this;
  }

  initialize(): this {
    this.buffer.push(ESC, 0x40);
    return this;
  }

  feedLines(n: number): this {
    this.buffer.push(ESC, 0x64, n);
    return this;
  }

  reverseOn(): this {
    this.buffer.push(ESC, 0x42, 0x01);
    return this;
  }

  reverseOff(): this {
    this.buffer.push(ESC, 0x42, 0x00);
    return this;
  }

  underlineOn(): this {
    this.buffer.push(ESC, 0x2d, 0x01);
    return this;
  }

  underlineOff(): this {
    this.buffer.push(ESC, 0x2d, 0x00);
    return this;
  }

  charSize(width: number, height: number): this {
    const size = ((width - 1) << 4) | (height - 1);
    this.buffer.push(GS, 0x21, size);
    return this;
  }

  charSizeNormal(): this {
    this.buffer.push(GS, 0x21, 0x00);
    return this;
  }

  byteArray(): Uint8Array {
    return new Uint8Array(this.buffer);
  }

  reset(): this {
    this.buffer = [];
    return this;
  }
}

interface SaleItem {
  name: string;
  quantity: number;
  price: number;
  total: number;
}

interface SalePayment {
  method: string;
  amount: number;
}

interface SaleData {
  id: string;
  invoice_number?: string;
  created_at: string;
  items: SaleItem[];
  subtotal: number;
  tax_amount: number;
  discount_amount: number;
  total: number;
  payments: SalePayment[];
  change_amount?: number;
  customer_name?: string;
}

interface BusinessData {
  name: string;
  address?: string;
  phone?: string;
  tax_number?: string;
  currency?: string;
}

export function formatReceipt(sale: SaleData, business: BusinessData): Uint8Array {
  const ep = new EscPos();
  const width = 42;

  ep.initialize();

  ep.alignCenter();
  ep.bold();
  ep.text(business.name);
  ep.boldOff();

  if (business.address) ep.text(business.address);
  if (business.phone) ep.text(business.phone);
  if (business.tax_number) ep.text(`TAX: ${business.tax_number}`);

  ep.feed(1);
  ep.alignCenter();
  ep.charSize(1, 1);
  ep.text("═".repeat(width));
  ep.charSizeNormal();

  ep.alignLeft();
  ep.text(`Invoice: ${sale.invoice_number ?? sale.id}`);
  ep.text(`Date: ${new Date(sale.created_at).toLocaleString()}`);
  if (sale.customer_name) ep.text(`Customer: ${sale.customer_name}`);

  ep.feed(1);
  ep.text("─".repeat(width));

  for (const item of sale.items) {
    const name = item.name.length > 20 ? item.name.slice(0, 17) + "..." : item.name;
    const line = `${name}  x${item.quantity}`;
    const price = formatMoney(item.total);
    ep.text(`${line.padEnd(width - price.length)}${price}`);
  }

  ep.text("─".repeat(width));

  ep.alignRight();
  ep.text(`Subtotal:${pad(formatMoney(sale.subtotal))}`);
  if (sale.discount_amount > 0) {
    ep.text(`Discount:${pad(formatMoney(sale.discount_amount))}`);
  }
  ep.text(`Tax:${pad(formatMoney(sale.tax_amount))}`);

  ep.bold();
  ep.text(`TOTAL:${pad(formatMoney(sale.total))}`);
  ep.boldOff();

  ep.text("─".repeat(width));

  for (const p of sale.payments) {
    ep.text(`${p.method}:${pad(formatMoney(p.amount))}`);
  }
  if (sale.change_amount && sale.change_amount > 0) {
    ep.text(`Change:${pad(formatMoney(sale.change_amount))}`);
  }

  ep.feed(2);
  ep.alignCenter();
  ep.text("Thank you for your purchase!");
  ep.feed(2);

  return ep.byteArray();
}

function formatMoney(amount: number): string {
  const sym = "$";
  return `${sym}${amount.toFixed(2)}`;
}

function pad(str: string): string {
  return str;
}

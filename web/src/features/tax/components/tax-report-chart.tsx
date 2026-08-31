/** The actual recharts implementation — kept in its own module so the page can pull it in with
 *  a dynamic `import()`, which Vite splits into its own chunk. recharts is the heaviest
 *  dependency in this app; nothing above this file imports it statically, so pages that never
 *  render a chart don't pay for it. */
import {
  Bar,
  BarChart,
  CartesianGrid,
  Legend,
  ResponsiveContainer,
  Tooltip,
  XAxis,
  YAxis,
} from "recharts";
import type { TaxReportComponent } from "@/features/tax/types";
import { formatCurrency } from "@/lib/utils";

interface Row {
  name: string;
  output: number;
  input: number;
}

function mergeByComponent(output: TaxReportComponent[], input: TaxReportComponent[]): Row[] {
  const byName = new Map<string, Row>();
  for (const c of output) byName.set(c.name, { name: c.name, output: c.amount, input: 0 });
  for (const c of input) {
    const existing = byName.get(c.name);
    if (existing) existing.input = c.amount;
    else byName.set(c.name, { name: c.name, output: 0, input: c.amount });
  }
  return Array.from(byName.values());
}

export default function TaxReportChart({
  output,
  input,
}: {
  output: TaxReportComponent[];
  input: TaxReportComponent[];
}) {
  const data = mergeByComponent(output, input);
  if (data.length === 0) return null;

  return (
    <ResponsiveContainer width="100%" height="100%">
      <BarChart data={data} margin={{ left: -10 }}>
        <CartesianGrid strokeDasharray="3 3" stroke="var(--border)" vertical={false} />
        <XAxis
          dataKey="name"
          tick={{ fontSize: 11 }}
          stroke="var(--muted)"
          tickLine={false}
          axisLine={false}
        />
        <YAxis
          tick={{ fontSize: 11 }}
          stroke="var(--muted)"
          tickLine={false}
          axisLine={false}
          width={60}
        />
        <Tooltip
          contentStyle={{
            background: "var(--surface)",
            border: "1px solid var(--border)",
            borderRadius: 12,
            fontSize: 12,
          }}
          formatter={(v) => formatCurrency(Number(v))}
        />
        <Legend wrapperStyle={{ fontSize: 12 }} />
        <Bar dataKey="output" name="Output tax" fill="var(--primary)" radius={[4, 4, 0, 0]} />
        <Bar
          dataKey="input"
          name="Input tax"
          fill="var(--color-danger, #ef4444)"
          radius={[4, 4, 0, 0]}
          fillOpacity={0.6}
        />
      </BarChart>
    </ResponsiveContainer>
  );
}

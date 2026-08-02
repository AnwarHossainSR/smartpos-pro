/** Types for the tax engine API — see backend `App\Http\Resources\Tax*Resource`. */

export type TaxRateType = "percentage" | "fixed";

export interface TaxRate {
  id: number;
  name: string;
  code: string | null;
  rate: number;
  type: TaxRateType;
  is_compound: boolean;
  is_active: boolean;
  /** Only present when this rate is returned as a component of a TaxGroup (pivot column). */
  priority?: number;
  created_at: string;
}

/** One component in a group's ordered rate list — the shape the group builder edits and
 *  the POST/PUT `rates` payload expects. */
export interface TaxGroupRateInput {
  tax_rate_id: number;
  priority: number;
}

export interface TaxGroup {
  id: number;
  name: string;
  code: string | null;
  is_default: boolean;
  is_active: boolean;
  rates: TaxRate[];
  products_count: number;
  created_at: string;
}

export interface TaxZone {
  id: number;
  name: string;
  country_code: string | null;
  state: string | null;
  postcode_pattern: string | null;
  tax_group_id: number | null;
  tax_group: TaxGroup | null;
  is_default: boolean;
  priority: number;
  is_active: boolean;
  created_at: string;
}

export type TaxSourcing = "origin" | "destination";
export type TaxRoundingScope = "line" | "component" | "invoice";
export type PriceDisplay = "inclusive" | "exclusive";

export interface TaxProfile {
  id: number;
  code: string;
  name: string;
  country_code: string | null;
  sourcing: TaxSourcing;
  rounding_scope: TaxRoundingScope;
  rounding_mode: string;
  price_display: PriceDisplay;
  tax_number_label: string;
  tax_number_pattern: string | null;
  required_invoice_fields: string[];
  is_active: boolean;
}

export interface TaxReportComponent {
  name: string;
  rate: number;
  base_amount: number;
  amount: number;
}

export interface TaxReportSide {
  total: number;
  components: TaxReportComponent[];
}

export interface TaxSummaryReport {
  from: string;
  to: string;
  output_tax: TaxReportSide;
  input_tax: TaxReportSide;
  net_payable: number;
}

export interface TaxReturnReport extends TaxSummaryReport {
  period: { from: string; to: string };
  business: { name: string | null; tax_number_label: string; tax_number: string | null };
  profile: { code: string | null; name: string | null; required_invoice_fields: string[] };
}

export interface TaxReportFilters {
  from: string;
  to: string;
  branch_id?: number | null;
}

/* ---- form payloads ---- */

export interface TaxRateFormValues {
  name: string;
  code?: string;
  rate: number;
  type: TaxRateType;
  is_compound: boolean;
  is_active: boolean;
}

export interface TaxGroupFormValues {
  name: string;
  code?: string;
  is_default: boolean;
  is_active: boolean;
  rates: TaxGroupRateInput[];
}

export interface TaxZoneFormValues {
  name: string;
  country_code?: string | null;
  state?: string | null;
  postcode_pattern?: string | null;
  tax_group_id: number | null;
  is_default: boolean;
  priority: number;
  is_active: boolean;
}

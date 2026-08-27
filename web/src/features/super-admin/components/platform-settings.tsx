import { useState } from "react";
import { usePlatformSettings, useTenantModuleOverrides, useUpdateTenantModules } from "../hooks";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { Skeleton } from "@/components/ui/skeleton";
import { Badge } from "@/components/ui/badge";
import { Save, Settings, ToggleLeft, ToggleRight } from "lucide-react";

const MODULE_LABELS: Record<string, string> = {
  pos: "Point of Sale",
  inventory: "Inventory Management",
  accounting: "Accounting",
  hr: "Human Resources",
  crm: "Customer Relations",
  reports: "Reports & Analytics",
  multi_branch: "Multi-Branch",
  ecommerce: "E-Commerce Integration",
};

interface SettingsFormProps {
  settings: {
    modules: Record<string, boolean>;
    maintenance_mode: boolean;
    registration_enabled: boolean;
  };
  onSave: (values: {
    modules: Record<string, boolean>;
    maintenance_mode: boolean;
    registration_enabled: boolean;
  }) => void;
  isPending: boolean;
}

function SettingsForm({ settings, onSave, isPending }: SettingsFormProps) {
  const [modules, setModules] = useState(settings.modules);
  const [maintenanceMode, setMaintenanceMode] = useState(
    settings.maintenance_mode,
  );
  const [registrationEnabled, setRegistrationEnabled] = useState(
    settings.registration_enabled,
  );

  const handleSave = () => {
    onSave({
      modules,
      maintenance_mode: maintenanceMode,
      registration_enabled: registrationEnabled,
    });
  };

  return (
    <div className="space-y-6">
      <div className="flex items-center justify-between">
        <div>
          <h2 className="text-lg font-semibold">Platform Settings</h2>
          <p className="text-sm text-[var(--muted)]">
            Configure global modules and platform behavior
          </p>
        </div>
        <Button onClick={handleSave} disabled={isPending}>
          <Save className="mr-2 h-4 w-4" />
          {isPending ? "Saving..." : "Save Changes"}
        </Button>
      </div>

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <Settings className="h-4 w-4" />
            Module Toggles
          </CardTitle>
        </CardHeader>
        <CardContent>
          <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
            {Object.entries(MODULE_LABELS).map(([key, label]) => (
              <button
                key={key}
                type="button"
                onClick={() =>
                  setModules((prev) => ({ ...prev, [key]: !prev[key] }))
                }
                className="flex items-center justify-between rounded-[var(--radius)] border border-[var(--border)] p-3 transition-colors hover:bg-[var(--surface-2)]"
              >
                <span className="text-sm font-medium">{label}</span>
                {modules[key] ? (
                  <ToggleRight className="h-6 w-6 text-[var(--success)]" />
                ) : (
                  <ToggleLeft className="h-6 w-6 text-[var(--muted)]" />
                )}
              </button>
            ))}
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Platform Behavior</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium">Maintenance Mode</p>
              <p className="text-xs text-[var(--muted)]">
                Temporarily disable access for all tenants
              </p>
            </div>
            <button
              type="button"
              onClick={() => setMaintenanceMode(!maintenanceMode)}
            >
              {maintenanceMode ? (
                <ToggleRight className="h-6 w-6 text-[var(--danger)]" />
              ) : (
                <ToggleLeft className="h-6 w-6 text-[var(--muted)]" />
              )}
            </button>
          </div>

          <div className="flex items-center justify-between">
            <div>
              <p className="text-sm font-medium">New Registration</p>
              <p className="text-xs text-[var(--muted)]">
                Allow new tenants to register
              </p>
            </div>
            <button
              type="button"
              onClick={() => setRegistrationEnabled(!registrationEnabled)}
            >
              {registrationEnabled ? (
                <ToggleRight className="h-6 w-6 text-[var(--success)]" />
              ) : (
                <ToggleLeft className="h-6 w-6 text-[var(--muted)]" />
              )}
            </button>
          </div>
        </CardContent>
      </Card>
    </div>
  );
}

export function PlatformSettings() {
  const { data, isLoading, update } = usePlatformSettings();
  const settings = data?.data;
  const { data: overridesData, isLoading: overridesLoading } = useTenantModuleOverrides();
  const updateTenantModules = useUpdateTenantModules();
  const [selectedTenant, setSelectedTenant] = useState<string | null>(null);

  const overrides = overridesData?.data ?? {};

  if (isLoading) {
    return (
      <div className="space-y-4">
        {Array.from({ length: 3 }).map((_, i) => (
          <Card key={i}>
            <CardHeader>
              <Skeleton className="h-4 w-32" />
            </CardHeader>
            <CardContent>
              <Skeleton className="h-20 w-full" />
            </CardContent>
          </Card>
        ))}
      </div>
    );
  }

  if (!settings) {
    return null;
  }

  const allModules = settings.modules;
  const selectedModules = selectedTenant ? overrides[selectedTenant] ?? {} : null;

  return (
    <div className="space-y-6">
      <SettingsForm
        key={JSON.stringify(settings)}
        settings={settings}
        onSave={(values) => update.mutate(values)}
        isPending={update.isPending}
      />

      <Card>
        <CardHeader>
          <CardTitle className="flex items-center gap-2">
            <ToggleRight className="h-4 w-4" />
            Per-Tenant Module Overrides
          </CardTitle>
        </CardHeader>
        <CardContent>
          <p className="mb-4 text-sm text-[var(--muted)]">
            Override which modules are enabled for individual tenants.
          </p>

          {overridesLoading ? (
            <Skeleton className="h-32 w-full" />
          ) : Object.keys(overrides).length === 0 ? (
            <p className="text-sm text-[var(--muted)]">No tenant overrides configured yet.</p>
          ) : (
            <div className="space-y-4">
              <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                {Object.entries(overrides).map(([tenantId, modules]) => {
                  const enabledCount = Object.values(modules).filter(Boolean).length;
                  const totalCount = Object.values(modules).length;
                  return (
                    <button
                      key={tenantId}
                      type="button"
                      onClick={() => setSelectedTenant(selectedTenant === tenantId ? null : tenantId)}
                      className={`flex items-center justify-between rounded-[var(--radius)] border p-3 text-left transition-colors ${
                        selectedTenant === tenantId
                          ? "border-[var(--primary)] bg-[var(--primary)]/5"
                          : "border-[var(--border)] hover:bg-[var(--surface-2)]"
                      }`}
                    >
                      <div>
                        <p className="text-sm font-medium">{tenantId}</p>
                        <p className="text-xs text-[var(--muted)]">
                          {enabledCount}/{totalCount} modules enabled
                        </p>
                      </div>
                      <Badge tone={enabledCount > 0 ? "success" : "info"}>
                        {enabledCount} active
                      </Badge>
                    </button>
                  );
                })}
              </div>

              {selectedTenant && selectedModules && (
                <div className="rounded-[var(--radius)] border border-[var(--border)] p-4">
                  <p className="mb-3 text-sm font-medium">Module overrides for {selectedTenant}</p>
                  <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                    {Object.entries(allModules).map(([key, globalEnabled]) => {
                      const override = selectedModules[key];
                      const isOverridden = override !== undefined;
                      const enabled = isOverridden ? override : globalEnabled;

                      return (
                        <button
                          key={key}
                          type="button"
                          onClick={() => {
                            const newModules = { ...selectedModules, [key]: !enabled };
                            updateTenantModules.mutate({
                              tenantId: selectedTenant,
                              modules: newModules,
                            });
                          }}
                          className="flex items-center justify-between rounded-[var(--radius)] border border-[var(--border)] p-3 transition-colors hover:bg-[var(--surface-2)]"
                        >
                          <div>
                            <span className="text-sm font-medium">{MODULE_LABELS[key] ?? key}</span>
                            {!isOverridden && (
                              <span className="ml-2 text-xs text-[var(--muted)]">(global)</span>
                            )}
                          </div>
                          {enabled ? (
                            <ToggleRight className="h-6 w-6 text-[var(--success)]" />
                          ) : (
                            <ToggleLeft className="h-6 w-6 text-[var(--muted)]" />
                          )}
                        </button>
                      );
                    })}
                  </div>
                  {updateTenantModules.isPending && (
                    <p className="mt-2 text-xs text-[var(--muted)]">Saving...</p>
                  )}
                </div>
              )}
            </div>
          )}
        </CardContent>
      </Card>
    </div>
  );
}

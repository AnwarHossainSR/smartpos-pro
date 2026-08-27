import { useModulesStore } from "@/stores/modules";
import { Card, CardContent } from "@/components/ui/card";
import { Button } from "@/components/ui/button";
import { useNavigate } from "react-router";

export function RequireModule({
  module,
  children,
}: {
  module: string;
  children: React.ReactNode;
}) {
  const isEnabled = useModulesStore((s) => s.modules[module]) ?? false;
  const loaded = useModulesStore((s) => s.loaded);
  const navigate = useNavigate();

  if (!loaded) return null;

  if (!isEnabled) {
    return (
      <Card>
        <CardContent className="py-12 text-center">
          <p className="text-sm text-[var(--muted)]">
            This module is not enabled for your account.
          </p>
          <Button
            variant="ghost"
            className="mt-2"
            onClick={() => navigate("/dashboard")}
          >
            Go to Dashboard
          </Button>
        </CardContent>
      </Card>
    );
  }

  return <>{children}</>;
}

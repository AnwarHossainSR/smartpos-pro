import { ArrowLeft } from "lucide-react";
import { Link } from "react-router";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { DeliveryLog } from "@/features/webhooks/components/delivery-log";
import { WebhookList } from "@/features/webhooks/components/webhook-list";
import { useTranslation } from "@/i18n/context";
import { useAuth } from "@/stores/auth";

export default function WebhooksPage() {
  const { can } = useAuth();
  const t = useTranslation();
  const canManage = can("settings.update");

  if (!canManage) {
    return (
      <div className="py-16 text-center text-[var(--muted)]">
        You don&apos;t have permission to view webhook settings.
      </div>
    );
  }

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-2">
        <Link to="/settings" className="text-[var(--muted)] hover:text-[var(--fg)]">
          <ArrowLeft className="h-4 w-4" />
        </Link>
      </div>

      <div className="grid grid-cols-1 gap-5 xl:grid-cols-4">
        <Card className="xl:col-span-3">
          <CardHeader>
            <CardTitle>{t("webhooks.title")}</CardTitle>
          </CardHeader>
          <CardContent>
            <WebhookList />
          </CardContent>
        </Card>

        <div className="space-y-5">
          <Card>
            <CardHeader>
              <CardTitle>{t("webhooks.about")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm text-[var(--muted)]">
              <p>{t("webhooks.aboutHint1")}</p>
              <p>{t("webhooks.aboutHint2")}</p>
              <p>{t("webhooks.aboutHint3")}</p>
            </CardContent>
          </Card>
        </div>
      </div>

      <Card>
        <CardHeader>
          <CardTitle>{t("webhooks.deliveryLog")}</CardTitle>
        </CardHeader>
        <CardContent>
          <DeliveryLog />
        </CardContent>
      </Card>
    </div>
  );
}

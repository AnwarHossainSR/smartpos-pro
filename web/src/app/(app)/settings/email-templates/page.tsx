import { useMutation, useQuery, useQueryClient } from "@tanstack/react-query";
import { ArrowLeft, RotateCcw, Send } from "lucide-react";
import { useState } from "react";
import { Link } from "react-router";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input, Label } from "@/components/ui/input";
import { Skeleton } from "@/components/ui/skeleton";
import { toast } from "@/components/ui/toast";
import { useTranslation } from "@/i18n/context";
import { api, type Envelope, errorMessage } from "@/lib/api";
import { queryKeys } from "@/lib/query-keys";
import { useDebouncedValue } from "@/lib/use-debounced-value";
import { useAuth } from "@/stores/auth";

interface EmailTemplate {
  key: string;
  name: string;
  description: string;
  variables: Record<string, string>;
  subject: string;
  body: string;
  default_subject: string;
  default_body: string;
  is_customised: boolean;
}

export default function EmailTemplatesPage() {
  const { can } = useAuth();
  const t = useTranslation();

  const [activeKey, setActiveKey] = useState<string | null>(null);

  const { data, isPending } = useQuery({
    queryKey: queryKeys.emailTemplates.lists(),
    queryFn: () => api<Envelope<EmailTemplate[]>>("/email-templates"),
    enabled: can("settings.update"),
  });

  if (!can("settings.update")) {
    return (
      <div className="py-16 text-center text-[var(--muted)]">
        {t("emailTemplates.noPermission")}
      </div>
    );
  }

  const templates = data?.data ?? [];
  const active = templates.find((tpl) => tpl.key === activeKey) ?? templates[0] ?? null;

  return (
    <div className="space-y-5">
      <div className="flex items-center gap-2">
        <Link to="/settings" className="text-[var(--muted)] hover:text-[var(--fg)]">
          <ArrowLeft className="h-4 w-4" />
        </Link>
        <h1 className="text-lg font-semibold">{t("emailTemplates.title")}</h1>
      </div>

      {isPending ? (
        <Skeleton className="h-96 w-full" />
      ) : (
        <div className="grid grid-cols-1 gap-5 xl:grid-cols-4">
          <Card className="xl:col-span-1">
            <CardHeader>
              <CardTitle className="text-base">{t("emailTemplates.emails")}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-1">
              {templates.map((tpl) => (
                <button
                  key={tpl.key}
                  type="button"
                  onClick={() => setActiveKey(tpl.key)}
                  className={`flex w-full items-center justify-between gap-2 rounded-md px-3 py-2 text-start text-sm transition-colors ${
                    tpl.key === active?.key
                      ? "bg-[var(--surface-2)] font-medium"
                      : "hover:bg-[var(--surface-2)]"
                  }`}
                >
                  <span className="min-w-0 truncate">{tpl.name}</span>
                  {tpl.is_customised && <Badge tone="info">{t("emailTemplates.custom")}</Badge>}
                </button>
              ))}
            </CardContent>
          </Card>

          {active && <TemplateEditor key={active.key} template={active} />}
        </div>
      )}
    </div>
  );
}

function TemplateEditor({ template }: { template: EmailTemplate }) {
  const t = useTranslation();
  const qc = useQueryClient();

  const [subject, setSubject] = useState(template.subject);
  const [body, setBody] = useState(template.body);
  const [testEmail, setTestEmail] = useState("");

  // The preview is a POST, and the query key is the draft — so debounce the draft before
  // it reaches the key, or every keystroke in the editor renders a fresh email server-side.
  const debouncedSubject = useDebouncedValue(subject, 400);
  const debouncedBody = useDebouncedValue(body, 400);
  const draft = { key: template.key, subject: debouncedSubject, body: debouncedBody };

  const preview = useQuery({
    queryKey: queryKeys.emailTemplates.preview(draft),
    enabled: !!draft.subject && !!draft.body,
    queryFn: () =>
      api<Envelope<{ subject: string; html: string }>>(`/email-templates/${template.key}/preview`, {
        method: "POST",
        body: { subject: draft.subject, body: draft.body },
      }),
  });

  const save = useMutation({
    mutationFn: () =>
      api(`/email-templates/${template.key}`, { method: "PUT", body: { subject, body } }),
    onSuccess: () => {
      toast(t("emailTemplates.saved"));
      qc.invalidateQueries({ queryKey: queryKeys.emailTemplates.lists() });
    },
    onError: (e) => toast(errorMessage(e, t("emailTemplates.saveFailed")), "error"),
  });

  const reset = useMutation({
    mutationFn: () => api(`/email-templates/${template.key}`, { method: "DELETE" }),
    onSuccess: () => {
      setSubject(template.default_subject);
      setBody(template.default_body);
      qc.invalidateQueries({ queryKey: queryKeys.emailTemplates.lists() });
      toast(t("emailTemplates.reset"));
    },
    onError: (e) => toast(errorMessage(e, t("emailTemplates.saveFailed")), "error"),
  });

  const sendTest = useMutation({
    mutationFn: () =>
      api(`/email-templates/${template.key}/test`, { method: "POST", body: { email: testEmail } }),
    onSuccess: () => toast(t("emailTemplates.testSent", { email: testEmail })),
    onError: (e) => toast(errorMessage(e, t("emailTemplates.testFailed")), "error"),
  });

  return (
    <>
      <Card className="xl:col-span-2">
        <CardHeader>
          <CardTitle className="text-base">{template.name}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-4">
          <p className="text-sm text-[var(--muted)]">{template.description}</p>

          <div className="space-y-1">
            <Label htmlFor="subject">{t("emailTemplates.subject")}</Label>
            <Input id="subject" value={subject} onChange={(e) => setSubject(e.target.value)} />
          </div>

          <div className="space-y-1">
            <Label htmlFor="body">{t("emailTemplates.body")}</Label>
            <textarea
              id="body"
              value={body}
              onChange={(e) => setBody(e.target.value)}
              rows={12}
              className="w-full rounded-md border border-[var(--border)] bg-[var(--surface)] p-3 font-mono text-sm"
            />
          </div>

          <div className="space-y-2">
            <p className="text-xs text-[var(--muted)]">{t("emailTemplates.variablesHint")}</p>
            <div className="flex flex-wrap gap-1.5">
              {Object.entries(template.variables).map(([name, description]) => (
                <button
                  key={name}
                  type="button"
                  title={description}
                  onClick={() => setBody((current) => `${current}{${name}}`)}
                  className="rounded-full bg-[var(--surface-2)] px-2.5 py-1 font-mono text-xs hover:bg-[var(--border)]"
                >
                  {`{${name}}`}
                </button>
              ))}
            </div>
          </div>

          <div className="flex flex-wrap gap-2">
            <Button onClick={() => save.mutate()} disabled={save.isPending}>
              {save.isPending ? t("emailTemplates.saving") : t("common.save")}
            </Button>
            <Button
              variant="outline"
              onClick={() => reset.mutate()}
              disabled={!template.is_customised || reset.isPending}
            >
              <RotateCcw className="h-4 w-4" /> {t("emailTemplates.resetAction")}
            </Button>
          </div>

          <div className="flex flex-wrap items-end gap-2 border-t border-[var(--border)] pt-4">
            <div className="min-w-48 flex-1 space-y-1">
              <Label htmlFor="test-email">{t("emailTemplates.testTo")}</Label>
              <Input
                id="test-email"
                type="email"
                value={testEmail}
                onChange={(e) => setTestEmail(e.target.value)}
                placeholder="you@example.com"
              />
            </div>
            <Button
              variant="outline"
              onClick={() => sendTest.mutate()}
              disabled={!testEmail || sendTest.isPending}
            >
              <Send className="h-4 w-4" /> {t("emailTemplates.sendTest")}
            </Button>
          </div>
        </CardContent>
      </Card>

      <Card className="xl:col-span-1">
        <CardHeader>
          <CardTitle className="text-base">{t("emailTemplates.preview")}</CardTitle>
        </CardHeader>
        <CardContent className="space-y-2">
          {preview.isPending ? (
            <Skeleton className="h-48 w-full" />
          ) : preview.data ? (
            <>
              <p className="text-sm font-medium">{preview.data.data.subject}</p>
              <div
                className="space-y-2 text-sm [&_a]:text-[var(--primary)] [&_h1]:text-base [&_h1]:font-semibold [&_ul]:list-disc [&_ul]:ps-5"
                dangerouslySetInnerHTML={{ __html: preview.data.data.html }}
              />
            </>
          ) : (
            <p className="text-sm text-[var(--muted)]">{t("emailTemplates.previewEmpty")}</p>
          )}
        </CardContent>
      </Card>
    </>
  );
}

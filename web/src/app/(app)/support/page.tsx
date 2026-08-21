import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input, Label } from "@/components/ui/input";
import { toast } from "@/components/ui/toast";
import { api } from "@/lib/api";
import { useAuth } from "@/stores/auth";
import { useMutation } from "@tanstack/react-query";
import { BookOpen, CheckCircle, ExternalLink, Headset, Mail, MessageSquare, Send } from "lucide-react";
import { useState } from "react";

interface ContactForm {
  subject: string;
  message: string;
}

const FAQ_ITEMS = [
  {
    q: "How do I add a new product?",
    a: "Go to Catalog → Products and click 'Add Product'. Fill in the details including price, stock, and tax profile.",
  },
  {
    q: "How do I process a return?",
    a: "Open the sale from Sales history, click 'Return' and select the items being returned. Stock will be adjusted automatically.",
  },
  {
    q: "How do I invite team members?",
    a: "Navigate to People → Team and click 'Invite Member'. Enter their email and assign a role with the appropriate permissions.",
  },
  {
    q: "How do I set up tax rates?",
    a: "Go to Settings → Tax. Create tax rates and assign them to products or categories.",
  },
  {
    q: "Can I use the POS offline?",
    a: "Yes, SmartPOS Pro works offline. Transactions are synced automatically when connectivity is restored.",
  },
];

export default function SupportPage() {
  const user = useAuth((s) => s.user);
  const [form, setForm] = useState<ContactForm>({ subject: "", message: "" });
  const [submitted, setSubmitted] = useState(false);

  const mutation = useMutation({
    mutationFn: (data: ContactForm) =>
      api("/support/ticket", { method: "POST", body: data }),
    onSuccess: () => {
      toast("Support ticket submitted successfully");
      setSubmitted(true);
      setForm({ subject: "", message: "" });
    },
  });

  function handleSubmit(e: React.FormEvent) {
    e.preventDefault();
    if (!form.subject.trim() || !form.message.trim()) return;
    mutation.mutate(form);
  }

  return (
    <div className="max-w-5xl space-y-6">
      <div className="flex items-center gap-3">
        <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-[var(--primary)]/10">
          <Headset className="h-5 w-5 text-[var(--primary)]" />
        </div>
        <p className="text-sm text-[var(--muted)]">Get help with your SmartPOS Pro account</p>
      </div>

      <div className="grid grid-cols-1 gap-5 lg:grid-cols-3">
        <div className="space-y-5 lg:col-span-2">
          <Card>
            <CardHeader>
              <CardTitle>Contact Us</CardTitle>
            </CardHeader>
            <CardContent>
              {submitted ? (
                <div className="flex flex-col items-center gap-3 py-8 text-center">
                  <CheckCircle className="h-12 w-12 text-green-500" />
                  <p className="text-sm font-medium">Ticket submitted successfully</p>
                  <p className="text-sm text-[var(--muted)]">We&apos;ll get back to you within 24 hours.</p>
                  <Button variant="outline" onClick={() => setSubmitted(false)}>
                    Submit Another Ticket
                  </Button>
                </div>
              ) : (
                <form onSubmit={handleSubmit} className="space-y-4">
                  <div>
                    <Label htmlFor="email">Email</Label>
                    <Input id="email" value={user?.email ?? ""} disabled className="mt-1.5" />
                  </div>
                  <div>
                    <Label htmlFor="subject">Subject *</Label>
                    <Input
                      id="subject"
                      value={form.subject}
                      onChange={(e) => setForm({ ...form, subject: e.target.value })}
                      placeholder="Brief description of your issue"
                      className="mt-1.5"
                      required
                    />
                  </div>
                  <div>
                    <Label htmlFor="message">Message *</Label>
                    <textarea
                      id="message"
                      value={form.message}
                      onChange={(e) => setForm({ ...form, message: e.target.value })}
                      placeholder="Describe your issue in detail..."
                      rows={5}
                      className="mt-1.5 w-full rounded-[var(--radius)] border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm transition-colors placeholder:text-[var(--muted)] focus:border-[var(--primary)] focus:outline-none focus:ring-1 focus:ring-[var(--primary)]"
                      required
                    />
                  </div>
                  <Button type="submit" disabled={mutation.isPending || !form.subject.trim() || !form.message.trim()}>
                    {mutation.isPending ? (
                      "Submitting..."
                    ) : (
                      <>
                        <Send className="mr-2 h-4 w-4" />
                        Submit Ticket
                      </>
                    )}
                  </Button>
                </form>
              )}
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Frequently Asked Questions</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
              {FAQ_ITEMS.map((item, i) => (
                <details key={i} className="group rounded-[var(--radius)] border border-[var(--border)] p-4">
                  <summary className="cursor-pointer text-sm font-medium text-[var(--foreground)] marker:hidden list-none flex items-center justify-between">
                    {item.q}
                    <span className="ml-2 text-[var(--muted)] transition-transform group-open:rotate-180">
                      ▼
                    </span>
                  </summary>
                  <p className="mt-2 text-sm text-[var(--muted)]">{item.a}</p>
                </details>
              ))}
            </CardContent>
          </Card>
        </div>

        <div className="space-y-5">
          <Card>
            <CardHeader>
              <CardTitle>Quick Links</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2">
              <a
                href="mailto:support@smartpos.com"
                className="flex items-center gap-3 rounded-[var(--radius)] p-3 text-sm transition-colors hover:bg-[var(--surface-2)]"
              >
                <Mail className="h-4 w-4 shrink-0 text-[var(--primary)]" />
                <span className="flex-1 font-medium">Email Support</span>
                <ExternalLink className="h-3.5 w-3.5 text-[var(--muted)]" />
              </a>
              <a
                href="#"
                className="flex items-center gap-3 rounded-[var(--radius)] p-3 text-sm transition-colors hover:bg-[var(--surface-2)]"
              >
                <MessageSquare className="h-4 w-4 shrink-0 text-[var(--primary)]" />
                <span className="flex-1 font-medium">Live Chat</span>
                <ExternalLink className="h-3.5 w-3.5 text-[var(--muted)]" />
              </a>
              <a
                href="#"
                className="flex items-center gap-3 rounded-[var(--radius)] p-3 text-sm transition-colors hover:bg-[var(--surface-2)]"
              >
                <BookOpen className="h-4 w-4 shrink-0 text-[var(--primary)]" />
                <span className="flex-1 font-medium">Documentation</span>
                <ExternalLink className="h-3.5 w-3.5 text-[var(--muted)]" />
              </a>
            </CardContent>
          </Card>

          <Card>
            <CardHeader>
              <CardTitle>Business Hours</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
              <div className="flex justify-between">
                <span className="text-[var(--muted)]">Monday - Friday</span>
                <span>9:00 AM - 6:00 PM</span>
              </div>
              <div className="flex justify-between">
                <span className="text-[var(--muted)]">Saturday</span>
                <span>10:00 AM - 2:00 PM</span>
              </div>
              <div className="flex justify-between">
                <span className="text-[var(--muted)]">Sunday</span>
                <span className="text-[var(--muted)]">Closed</span>
              </div>
              <div className="mt-3 border-t border-[var(--border)] pt-3">
                <p className="text-[var(--muted)]">Average response time: 2-4 hours</p>
              </div>
            </CardContent>
          </Card>
        </div>
      </div>
    </div>
  );
}

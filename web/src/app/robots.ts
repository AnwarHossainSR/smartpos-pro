import type { MetadataRoute } from "next";
import { PRIVATE_PREFIXES, SITE_URL } from "@/lib/seo";

/**
 * Two audiences, deliberately given different answers.
 *
 * Search crawlers get the marketing pages and are kept out of everything else.
 * The private list includes `/order` and `/portal`, which need no login but are
 * addressed by a secret token and render a named customer's purchases — those are
 * also `noindex` at the page level, because robots.txt is a request, not a control.
 *
 * AI crawlers are allowed on the same public surface. That is a decision, not an
 * oversight: an assistant asked "what POS software handles multi-branch stock"
 * should be able to read the marketing pages and `/llms.txt` and answer. They are
 * held to exactly the same private-path exclusions.
 */
export default function robots(): MetadataRoute.Robots {
  const disallow = [...PRIVATE_PREFIXES];

  /** Crawlers that build training corpora or answer questions live. */
  const aiAgents = [
    "GPTBot",
    "OAI-SearchBot",
    "ChatGPT-User",
    "ClaudeBot",
    "Claude-User",
    "Claude-SearchBot",
    "anthropic-ai",
    "PerplexityBot",
    "Perplexity-User",
    "Google-Extended",
    "Applebot-Extended",
    "meta-externalagent",
    "Bytespider",
    "CCBot",
  ];

  return {
    rules: [
      { userAgent: "*", allow: "/", disallow },
      { userAgent: aiAgents, allow: ["/", "/llms.txt"], disallow },
    ],
    sitemap: `${SITE_URL}/sitemap.xml`,
    host: SITE_URL,
  };
}

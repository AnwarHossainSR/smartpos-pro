/**
 * Renders a JSON-LD block.
 *
 * A Server Component with no "use client": the markup must be in the HTML the
 * crawler receives, and anything that only appears after hydration is invisible to
 * the crawlers and agents that do not run JavaScript.
 *
 * `JSON.stringify` output is escaped before it goes into the script tag — a `<`
 * inside any string value would otherwise be read by the HTML parser as the start
 * of a tag and truncate the block.
 */
export function JsonLd({ data }: { data: Record<string, unknown> | Record<string, unknown>[] }) {
  const json = JSON.stringify(data).replace(/</g, "\\u003c");

  return (
    <script
      type="application/ld+json"
      // Safe: the value is our own serialised object with `<` neutralised above,
      // never user input.
      dangerouslySetInnerHTML={{ __html: json }}
    />
  );
}

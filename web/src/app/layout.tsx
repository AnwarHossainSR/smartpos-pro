import type { Metadata, Viewport } from "next";
import { Geist, Geist_Mono, Noto_Sans_Bengali, Noto_Sans_JP, Noto_Sans_Arabic } from "next/font/google";
import "./globals.css";
import { Providers } from "@/components/providers";
import { JsonLd } from "@/components/seo/json-ld";
import { organizationLd, SITE, SITE_URL, softwareApplicationLd, webSiteLd } from "@/lib/seo";

const geist = Geist({ variable: "--font-geist-sans", subsets: ["latin"] });
const geistMono = Geist_Mono({ variable: "--font-geist-mono", subsets: ["latin"] });
const notoBengali = Noto_Sans_Bengali({ variable: "--font-noto-bengali", subsets: ["bengali"] });
const notoJP = Noto_Sans_JP({ variable: "--font-noto-jp", subsets: ["latin"] });
const notoArabic = Noto_Sans_Arabic({ variable: "--font-noto-arabic", subsets: ["arabic"] });

export const metadata: Metadata = {
  // Without this, every relative OG/canonical URL below resolves against localhost
  // in a production build and link previews break silently.
  metadataBase: new URL(SITE_URL),
  title: {
    default: `${SITE.name} — Run the whole shop from one counter`,
    // Page files export a bare title ("Sign in") and get the brand appended here.
    template: `%s · ${SITE.name}`,
  },
  description: SITE.description,
  applicationName: SITE.name,
  keywords: [
    "point of sale",
    "POS software",
    "retail inventory management",
    "multi-branch POS",
    "small business accounting",
    "barcode billing software",
    "stock management",
    "supermarket POS",
    "pharmacy POS",
    "restaurant POS",
  ],
  authors: [{ name: SITE.name }],
  creator: SITE.name,
  publisher: SITE.name,
  // The default for the whole tree is indexable; `(app)`, `/portal` and `/order`
  // each override it with a noindex in their own layout.
  robots: {
    index: true,
    follow: true,
    googleBot: { index: true, follow: true, "max-image-preview": "large", "max-snippet": -1 },
  },
  alternates: { canonical: "/" },
  openGraph: {
    type: "website",
    url: SITE_URL,
    siteName: SITE.name,
    title: `${SITE.name} — Run the whole shop from one counter`,
    description: SITE.description,
    locale: SITE.locale,
  },
  twitter: {
    card: "summary_large_image",
    title: `${SITE.name} — Run the whole shop from one counter`,
    description: SITE.shortDescription,
    creator: SITE.twitter,
  },
  manifest: "/manifest.json",
  icons: {
    icon: [
      { url: "/icon.svg", type: "image/svg+xml" },
      { url: "/icon-192.png", sizes: "192x192", type: "image/png" },
    ],
    apple: "/icon-192.png",
  },
  formatDetection: { telephone: false },
  category: "business",
};

export const viewport: Viewport = {
  width: "device-width",
  initialScale: 1,
  // The POS runs full-screen on a till; letting the address bar tint follow the
  // theme keeps the chrome from flashing white against a dark terminal.
  themeColor: [
    { media: "(prefers-color-scheme: light)", color: "#ffffff" },
    { media: "(prefers-color-scheme: dark)", color: "#101322" },
  ],
};

const NO_FLASH_SCRIPT = `(function(){try{
var d=document.documentElement;
var t="system",p="indigo";
var rt=localStorage.getItem("smartpos-theme");
if(rt){var st=JSON.parse(rt).state||{};t=st.theme||t;p=st.palette||p;}
if(t==="dark")d.classList.add("dark");else if(t==="light")d.classList.add("light");
d.setAttribute("data-theme",p);
var locale="en",dir="ltr";
var rl=localStorage.getItem("smartpos-locale");
if(rl){var sl=JSON.parse(rl).state||{};locale=sl.locale||locale;}
if(locale==="ar")dir="rtl";
d.setAttribute("lang",locale);
d.setAttribute("dir",dir);
}catch(e){}})();`;

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" dir="ltr" suppressHydrationWarning>
      <head>
        <script dangerouslySetInnerHTML={{ __html: NO_FLASH_SCRIPT }} />
        {/*
          Site-wide structured data. It lives in the root layout so it is present in
          the server-rendered HTML of every page — crawlers and agents that do not
          execute JavaScript would never see it if it were rendered client-side.
        */}
        <JsonLd data={[organizationLd(), webSiteLd(), softwareApplicationLd()]} />
      </head>
      <body
        className={`${geist.variable} ${geistMono.variable} ${notoBengali.variable} ${notoJP.variable} ${notoArabic.variable}`}
      >
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}

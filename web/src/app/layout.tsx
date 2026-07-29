import type { Metadata, Viewport } from "next";
import { Geist, Geist_Mono, Noto_Sans_Bengali, Noto_Sans_JP, Noto_Sans_Arabic } from "next/font/google";
import "./globals.css";
import { Providers } from "@/components/providers";

const geist = Geist({ variable: "--font-geist-sans", subsets: ["latin"] });
const geistMono = Geist_Mono({ variable: "--font-geist-mono", subsets: ["latin"] });
// Per-script fonts for locales Geist doesn't cover glyphs for. Only downloaded by the
// browser when `[data-lang]` on <html> actually applies that font-family (see globals.css).
const notoBengali = Noto_Sans_Bengali({ variable: "--font-noto-bengali", subsets: ["bengali"] });
const notoJP = Noto_Sans_JP({ variable: "--font-noto-jp", subsets: ["latin"] });
const notoArabic = Noto_Sans_Arabic({ variable: "--font-noto-arabic", subsets: ["arabic"] });

export const metadata: Metadata = {
  title: "SmartPOS Pro",
  description: "Modern multi-tenant POS & retail management platform",
  manifest: "/manifest.json",
  appleWebApp: {
    capable: true,
    statusBarStyle: "black-translucent",
    title: "SmartPOS",
  },
};

export const viewport: Viewport = {
  themeColor: "#4f46e5",
};

// Runs synchronously while the browser parses <head>, before first paint, so the persisted
// theme/palette/locale apply with no flash. Mirrors ThemeSync/LocaleSync, which take over
// once React hydrates (see docs/REDESIGN_I18N_PLAN.md and the Next.js "preventing flash
// before hydration" guide).
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
}catch(e){}})()`;

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="en" suppressHydrationWarning>
      <head>
        <script dangerouslySetInnerHTML={{ __html: NO_FLASH_SCRIPT }} />
      </head>
      <body
        className={`${geist.variable} ${geistMono.variable} ${notoBengali.variable} ${notoJP.variable} ${notoArabic.variable}`}
      >
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}

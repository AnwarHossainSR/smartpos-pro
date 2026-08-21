import { Geist, Geist_Mono, Noto_Sans_Bengali, Noto_Sans_JP, Noto_Sans_Arabic } from "next/font/google";
import "./globals.css";
import { Providers } from "@/components/providers";

const geist = Geist({ variable: "--font-geist-sans", subsets: ["latin"] });
const geistMono = Geist_Mono({ variable: "--font-geist-mono", subsets: ["latin"] });
const notoBengali = Noto_Sans_Bengali({ variable: "--font-noto-bengali", subsets: ["bengali"] });
const notoJP = Noto_Sans_JP({ variable: "--font-noto-jp", subsets: ["latin"] });
const notoArabic = Noto_Sans_Arabic({ variable: "--font-noto-arabic", subsets: ["arabic"] });

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
      </head>
      <body
        className={`${geist.variable} ${geistMono.variable} ${notoBengali.variable} ${notoJP.variable} ${notoArabic.variable}`}
      >
        <Providers>{children}</Providers>
      </body>
    </html>
  );
}

# SmartPOS Pro - Byaboharokari Guide (Bangla)

**Sonsorki:** 1.0
**Tarikh:** August 2026

---

## Suchipotro

1. [SmartPOS Pro Ki?](#1-smartpos-pro-ki)
2. [Modulesamuh - Sarosongkhep](#2-modulesamuh)
3. [Moduler Madhye Somporko](#3-moduler-madhye-somporko)
4. [Prothombar Setup - Kivabe Shuru Korben](#4-prothombar-setup)
5. [Dainik Byaboharer Dhap - Ekdiner Probaho](#5-dainik-byaboharer-dhap)
6. [Module-voittik Bistarito Bakkha](#6-module-voittik-bistarito-bakkha)
7. [Byaboharokari Bhumika O Onumoti](#7-byaboharokari-bhumika-o-onumoti)
8. [Settings O Configuration](#8-settings-o-configuration)
9. [Report O Bishleshon](#9-report-o-bishleshon)
10. [Shobdaboli](#10-shobdaboli)

---

## 1. SmartPOS Pro Ki?

SmartPOS Pro holo ekta **multi-tenant Point of Sale (POS) o Retail SaaS application**. Sohoj bhashay bola jay:

> Eti ekta **digital dokan bastapona sistema** jeta apnar puro byabsakek jaygay chalate sahajjo kore - ponnyo talika theke shuru kore sel, kroy, inventory, hisab-nikesh, kharcha, tax, report ebong grahok bastapona porjontto.

### Kader Jonno?

- Choto theke majhari retail dokan (grocery, pharmacy, kaprer dokan, electronics dokan ityadi)
- Ekadhik shakha ba godam ase emon byabsa
- Jara sel, stock, ebong hisab eksathe dekhte chan
- Jara kreta-bikreta partner track korte chan
- Jara tax (VAT/GST) sothikbhabe manage korte chan

### Mul Bishishto

| Bishishto | Biboron |
|-----------|---------|
| **Multi-Tenant** | Ekti server-e onek byabsa eksathe cholte pare, tothyo aladakrito |
| **Multi-Shakha** | Ekti byabsar onek shakha o godam thakte pare |
| **Real-Time** | Sel howar sathe sathe stock, hisab update hoy |
| **POS Terminal** | Barcode scan ba search kore druto sel |
| **Hisab-Nikesh** | Dual-entry accounting, journal, trial balance |
| **Tax Bastapona** | VAT, GST, Sales Tax - desho onujayi configuring |
| **Report** | Sel, lav, stock, rin, tax - sob dhoroner report |
| **Loyalty** | Point puraskar o coupon system |
| **Cash Register** | Dainik cash open/close, X-report, Z-report |
| **Audit Trail** | Protiti guruttbopurno kajer log rakha hoy |
| **Bohu Bhasha** | Bangla, English, Arabic, German, Japanese |

---

## 2. Modulesamuh

SmartPOS Pro-te mot **21-ti module** ache. Protiti module ekti nirnistho kaj porichalonito kore:

| # | Module | Kon Kaje Lage |
|---|--------|---------------|
| 1 | **Dashboard** | Sarosongkhep dekha - ajker sel, lav, stock |
| 2 | **Shakha (Branches)** | Dokan/shakhar talika o bastapona |
| 3 | **Godam (Warehouses)** | Godamer talika o bastapona |
| 4 | **Ponnyo (Products)** | Ponnyo toiri, shompadona, category, brand, ekok |
| 5 | **Inventory** | Stock poriman, stock sorano, somonnoy |
| 6 | **POS** | Point of Sale - cart, checkout, receipt |
| 7 | **Sel (Sales)** | Seler talika, bistarito, return |
| 8 | **Kroy (Purchases)** | Sorborokhari theke ponnyo kena |
| 9 | **Grahok (Customers)** | Grahok talika, rin, loyalty |
| 10 | **Sorborokhari (Suppliers)** | Sorborokhari talika o rin |
| 11 | **Kharcha (Expenses)** | Dainik kharchar hisab |
| 12 | **Hisab-Nikesh (Accounting)** | Account, journal, trial balance |
| 13 | **Tax** | Tax rate, group, zone, profile |
| 14 | **Report** | Sel, lav, stock, rin report |
| 15 | **Coupon** | Discount coupon toiri o bastapona |
| 16 | **Loyalty** | Point deya o redeem |
| 17 | **Subscription** | Plan, payment, simabaddhota |
| 18 | **Byaboharokari (Users)** | User toiri, shompadona, bhumika nirdharon |
| 19 | **Bhumika (Roles)** | Role toiri, onumoti set kora |
| 20 | **Settings** | Byabsar tothyo, theme, bhasha |
| 21 | **Audit** | Sob karyokoloper log dekha |

---

## 3. Moduler Madhye Somporko

Niche dekhano hocche kivabe modulegulo ekoporere sathe somporkito:

```
+------------------------------------------------------------------+
|                    SmartPOS Pro - Module Somporko                  |
+------------------------------------------------------------------+

  +----------+     +----------+     +----------+
  |  Shakha  |---->|  Godam   |---->| Inventory|
  | (Branch) |     |(Warehouse)|    |          |
  +----------+     +----------+     +----+-----+
                                         |
                          +--------------+--------------+
                          v              v              v
                    +----------+  +----------+  +----------+
                    |   Sel    |  |   Kroy   |  | Stock    |
                    |  (Sale)  |  |(Purchase)|  | Sorano   |
                    +----+-----+  +----+-----+  +----------+
                         |            |
           +-------------+------------+
           v             v
     +----------+  +----------+
     |  Ponnyo  |  | Sorborokhari|
     |(Product) |  | (Supplier) |
     +----+-----+  +------------+
          |
  +-------+-------+----------+
  v       v       v          v
+------+ +------+ +------+ +------+
|Catego| |Brand | |Ekok  | |Tax   |
|ry    | |      | |      | |Group |
+------+ +------+ +------+ +------+

  +----------+     +----------+     +----------+
  | Grahok   |---->|Loyalty   |     |  Coupon  |
  |(Customer)|     |          |     |          |
  +----+-----+     +----------+     +----+-----+
       |                                  |
       +----------+  +-------------------+
                  v  v
              +----------+
              | Sel/POS  |
              +----+-----+
                   |
        +----------+----------+
        v          v          v
  +----------+ +----------+ +----------+
  | Kharcha | |Hisab-    | |Cash      |
  |(Expense) | |Nikesh    | |Register  |
  +----------+ +----------+ +----------+
        |          |          |
        +----------+----------+
                   v
              +----------+
              | Report   |
              +----------+

  +----------+     +----------+     +----------+
  |Byaboharokari-->| Bhumika  |---->| Onumoti  |
  |  (User)  |     |  (Role)  |     |(Permission)|
  +----------+     +----------+     +----------+

  +----------+     +----------+
  | Settings |     | Audit    |
  +----------+     +----------+
```

### Somporko er Bakkha:

**Ponnyo o Inventory:**
- Protiti ponnyo ekti **category**, **brand**, **ekok** ebong **tax group**-er sathe somporkito
- Ponnyor protiti godame aladabhabe **stock poriman** thake (InventoryBalance)
- Protiti sel, kroy, stock sorano - sobkisu ekti **immutable ledger** (StockMovement) toiri kore

**Sel o POS:**
- POS theke sel hole **stock come** jay shobochalitobhabe
- Sel-e grahok thakle **grahok-er rin** barete pare
- Sel-e coupon byabohar hole **coupon-er byabohar gonona** bare
- Seler por **loyalty point** shobochalitobhabe jog hoy
- Sel return hole **stock phire ashe** ebong **tax biporit** hoy

**Kroy o Sorborokhari:**
- Kroy korle **stock bare**
- Kroy-er sathe **sorborokharir rin** somporkito hoy

**Hisab-Nikesh o Kharcha:**
- Sel, kroy, kharcha - sobkisu ekti **dual-entry journal** toiri kore
- **Cash Register** dainik cash flow track kore
- **Kharcha** onumodon prokria atikromon kore

**Grahok o Loyalty:**
- Grahok proti sel-e **loyalty point** orjito hoy
- Point **redeem** kora jay (discount hishebe)
- Grahok-er **rin balance** track hoy

---

## 4. Prothombar Setup

### Dhap 1: Registration

1. App-e **Register** pejay jan
2. Nicher tothyo gulo puron korun:
   - **Byabsar Nam** - apnar dokan/kompanir nam
   - **Byabsar Dhoron** - Retail, Wholesale, ityadi
   - **Maliker Nam** - apnar puro nam
   - **Email** - login-er jonno byabohar hobe
   - **Password** - shoktishali password din
3. **Register** button-e click korun

Erpor shobochalitobhabe toiri hobe:
- Ekti **Tenant** (apnar byabsar jonno alada jayga)
- Ekti **Business**
- Ekti **Shakha** (Branch) - default
- Ekti **Godam** (Warehouse) - default
- Ekti **CashRegister** - default
- Apni **Owner** bhumikay thakben - sob onumoti thakbe

### Dhap 2: Email Jachai

Email-e ekti **jachokoron link** pathano hobe. Link-e click kore email jachai korun.

### Dhap 3: Prathomik Settings

Login korar por **Settings** pejay jan ebong:
- **Byabsar puro thikana** din
- **Mudra** select korun (BDT, INR, USD, EUR ityadi)
- **Tax Profile** select korun (BD_VAT, IN_GST, EU_VAT, US_SALES_TAX)
- **Mullo Prodashon** select korun (Tax Inclusive ba Tax Exclusive)
- **Bhasha** select korun (Bangla, English ityadi)
- **Theme** select korun (Light/Dark, ronger palette)

### Dhap 4: Ponnyo Jog Korun

**Products** pejay jan ebong:
1. Prothome **Category** toiri korun (jemon: "Grocery", "Paniyo")
2. **Brand** toiri korun (jemon: "Pran", "Coca-Cola")
3. **Ekok** toiri korun (jemon: "KG", "Piece", "Litre")
4. **Ponnyo** toiri korun:
   - Nam, SKU, Barcode
   - Krommulyo o Bikrommulyo
   - Tax group (jodi thake)
   - Stock poriman (priti godamer jonno)

### Dhap 5: Team Sodossho Jog Korun

**Team** pejay jan:
1. **Bhumika (Roles)** toiri korun - proyojoniyo onumoti din
2. **Byaboharokari (Users)** jog korun - tader bhumika din

**Udahoron Bhumika:**
- **Cashier**: shudhu POS, sel, grahok dekha
- **Manager**: sob dekha + stock somonnoy + report
- **Accountant**: hisab-nikesh + kharcha + tax

---

## 5. Dainik Byaboharer Dhap

Niche ekta **sadharon dokaner ekdiner probaho** dekhano hocche:

```
Sokal 9:00 - Cash Register Khola
    |
    v
Sokal 9:01 - POS Terminal Prostut
    |
    v
Sokal 9:05 - Grahok Ase
    |
    +----> Ponnyo Scan/Search
    |        |
    |        v
    |     Cart-e Jog
    |        |
    |        v
    |     Checkout (Payment)
    |        |
    |        +----> Stock Come
    |        +----> Sel Record Toiri
    |        +----> Loyalty Point Jog
    |        +----> Receipt Print
    |
    v
Dupur - Kroy Order
    |
    +----> Sorborokhari Nirbachon
    +----> Ponnyo Nirbachon
    +----> Poriman O Mullo
    +----> Kroy Somponno -> Stock Bare
    |
    v
Bikel - Kharcha Record
    |
    +----> Kharchar Dhoron (Bidyut, Bhara, Beton ityadi)
    +----> Onumodon Prokria
    |
    v
Sandhya 8:00 - Cash Register Bondho
    |
    +----> Noddo Gonna
    +----> Z-Report
    +----> Dainik Sarosongkhep
```

### Bistarito Dhap:

#### Dhap 1: Cash Register Khola

1. **Cash Register** pejay jan
2. **Open Session** button-e click korun
3. **Opening Cash** likhun - koto takay dokan khulchen (jemon: 5000 taka)
4. **Open** button-e click korun

#### Dhap 2: POS Theke Sel

1. **POS** pejay jan
2. **Barcode scan** korun athoba **ponnyo-nam search** korun
3. Ponnyo **cart-e jog** hobe
4. **Poriman** poriborton korun proyojon hole
5. **Discount** din (onumoti thakle)
6. **Checkout** button-e click korun
7. **Payment poddhoti** nirbachon korun:
   - **Cash** - noddo payment
   - **Card** - card payment
   - **Bank Transfer** - bank transfer
   - **Mobile Banking** - bKash, Nagad ityadi
   - **Wallet** - wallet
   - **Due** - rine (pore debe)
8. **Complete Sale** button-e click korun
9. **Receipt** print korun

#### Dhap 3: Sel Batil ba Return

- **Sel Batil (Void)**: Sales pejay geay sel-er bistarite `Void` button-e click korun
- **Return**: Sales pejay geay `Return` button-e click korun, ferote dite chaoa ponnyo nirbachon korun

#### Dhap 4: Cash Register Bondho

1. **Cash Register** pejay jan
2. **Close Session** button-e click korun
3. **Actual Cash** likhun - hate gonno kore koto taka ache
4. System **Expected Cash** dekhabe ebong **Difference** gonona korbe
5. **Close** button-e click korun
6. **Z-Report** (PDF) download korun

---

## 6. Module-Voittik Bistarito Bakkha

### 6.1 Dashboard

**Pejay:** `/dashboard`

Dashboard apnar byabsar **sarosongkhep** dekhay:

| Tothyo | Ki Dekhay |
|--------|-----------|
| **Ajker Sel** | Aj koto takar sel hoyeche |
| **Ajker Lav** | Aj koto taka lav hoyeche |
| **Stock-er Obostha** | Kom stock ase emon ponnyo |
| **Grahok-er Rin** | Grahokder moto bekya |
| **Sel Trend** | Goti kokkhoner sel-er graph |
| **Sheersh Ponnyo** | Sobcheye beshi bikri howa ponnyo |

**Kivabe Byabohar Korben:**
- Tarikh range select korun (aj, got soptaho, got mash)
- Shakha filter korun
- Graph theke trend bujhun

---

### 6.2 Ponnyo Bastapona

**Pejay:** `/products`

#### Ponnyo Category (Category)

Ponnyoguloke **goshtibhuto** korar jonno:
- Udahoron: "Grocery", "Paniyo", "Patro", "Kapor"
- Protiti category-te ekti **default tax group** deowa jay

#### Brand (Brand)

Ponnyor brand track korar jonno:
- Udahoron: "Pran", "Coca-Cola", "Samsung"
- Report-e brand onujayi sel dekha jay

#### Ekok (Unit)

Ponnyo porimap-er ekok:
- Udahoron: "KG", "Gram", "Piece", "Litre", "Bosta"

#### Ponnyo Toiri

1. **Products** pejay **Add Product** button-e click korun
2. Nicher tothyo gulo puron korun:

| Field | Biboron |
|-------|---------|
| **Nam** | Ponnyo-r nam |
| **SKU** | Stock Keeping Unit (unique code) |
| **Barcode** | Barcode nombor (jodi thake) |
| **Category** | Kon goshtite pore |
| **Brand** | Kon brand-er |
| **Ekok** | Kivabe mapa hoy |
| **Tax Group** | Kon tax proyojyo |
| **Krommulyo** | Koto takay kinechen |
| **Bikrommulyo** | Koto takay bikri korben |
| **Stock Tracking** | Ha/Na - stock kom-beshi hobe kina |
| **Stock** | Protiti godame koto ache |

**Guruttbopurno:** Bikrommulyo system thekei neoya hoy - POS-e client theke mullo neoya hoy na. Eti nirokhotar bishishto.

#### Ponnyo-r Variant

Ekti ponnyo-r bibhinno roop thakte pare:
- Udahoron: Ekti shirt-er 3 size (S, M, L), 4 rong
- Protiti variant-er alada SKU, barcode, mullo thakte pare

---

### 6.3 Inventory Bastapona

**Pejay:** `/inventory`

Inventory holo **stock bastaponar hridoy**.

#### Stock Balance

- Protiti ponnyo protiti godame koto tuku ache ta dekhun
- **Kom Stock** filter diye jebono ponnyo shesh hote choleche ta dekhun
- Stock mullo o gorokharcha dekhun

#### Stock Movement

Stock kono ponnyo-r **sompurno itihash** dekhun:
- Kokhon kena hoyeche (kroy)
- Kokhon bikri hoyeche (sel)
- Kokhon sorano hoyeche (stock transfer)
- Kokhon somonnoy kora hoyeche (adjustment)
- Protiti movement-er por stock koto chilo

#### Stock Somonnoy (Stock Adjustment)

Jodi stock mmanuali thik korte hoy:
1. **Inventory** pejay **Adjust Stock** jan
2. Ponnyo nirbachon korun
3. Godam nirbachon korun
4. **Poriman** likhun (+ ba -)
5. **Karon** likhun (ken somonnoy korchen)
6. **Submit** korun

#### Stock Transfer

Ekti godam theke onno godame ponnyo sorano:
1. **Stock Transfers** section-e **New Transfer** korun
2. **From Warehouse** o **To Warehouse** nirbachon korun
3. Ponnyo o poriman nirbachon korun
4. **Request Transfer** korun
5. Onumodonkari **Approve** korben
6. Gontobyer godamer kormochari **Receive** korben

**Probaho:** Request -> Approve -> Receive (tinti dhap)

---

### 6.4 POS (Point of Sale)

**Pejay:** `/pos`

POS holo apnar **dainik kajer mul sthan**.

#### POS Terminal-er Angsho Gulo:

```
+---------------------------------------------------+
|                    POS Terminal                      |
+------------------------+----------------------------+
|                        |                            |
|   Ponnyo-r Grid        |       Cart                 |
|                        |                            |
|  +-----+ +-----+      |  Ponnyo    Poriman  Mullo  |
|  |Pn-1 | |Pn-2 |      |  ------------------------ |
|  +-----+ +-----+      |  Item 1    2       200     |
|  +-----+ +-----+      |  Item 2    1       150     |
|  |Pn-3 | |Pn-4 |      |                            |
|  +-----+ +-----+      |  ------------------------ |
|  +-----+ +-----+      |  Subtotal:       350      |
|  |Pn-5 | |Pn-6 |      |  Tax:            35       |
|  +-----+ +-----+      |  Discount:       -20      |
|                        |  ------------------------ |
|  [Barcode Scan]        |  Total:          365      |
|  [Search...]           |                            |
|                        |  [Checkout] [Hold] [Clear] |
|                        |                            |
+------------------------+----------------------------+
```

#### POS-e Kaj Korar Dhap:

**1. Ponnyo Jog Korar:**
- Barcode scanner diye scan korun
- Athoba search box-e ponnyor nam likhun
- Athoba grid theke sorasori click korun

**2. Cart Poriborton:**
- Poriman poriborton: +/- button byabohar korun
- Item muchun: delete icon-e click korun
- Discount din: "Discount" button (onumoti lage)
- Mullo poriborton: "Price Override" button (onumoti lage)

**3. Hold:**
- Grahokkichukhon dure gele sel "Hold" korun
- Pore fire eshe "Resume" korun

**4. Checkout:**
- "Checkout" button-e click korun
- Payment poddhoti nirbachon korun
- Grahok nirbachon korun (rin dite chaile)
- "Complete Sale" korun

**5. Receipt:**
- PDF receipt toiri hobe
- Print korun ba email-e pathan

---

### 6.5 Sel Bastapona

**Pejay:** `/sales`

#### Seler Talika:

- Sob sel dekhun (tarikh, grahok, poriman, obostha)
- **Filter:** Tarikh range, shakha, grahok, obostha, payment status
- **Search:** Invoice nombor ba grahok-nam diye khujun

#### Seler Obostha (Status):

| Obostha | Artho |
|---------|-------|
| **Completed** | Sel somponno |
| **Pending** | Payment baki |
| **Voided** | Sel batil |
| **Returned** | Sel return hoyeche |

#### Seler Bistarito:

Protiti sel-er bistarito dekhun:
- Kon ponnyo koto kore bikri hoyeche
- Kon tax proyojyo chilo
- Kon coupon byabohar hoyeche
- Kon payment poddhotite payment hoyeche
- Invoice PDF download korun

#### Sel Return:

Grahok ponnyo fire dile:
1. Sel-er bistarite **Return** button-e click korun
2. Ferote dite chaoa ponnyo nirbachon korun
3. Poriman nirbachon korun
4. **Refund poddhoti** nirbachon korun
5. **Submit** korun
6. Stock shobochalitobhabe phire ashe
7. Tax shobochalitobhabe biporit hoy

---

### 6.6 Kroy Bastapona

**Pejay:** `/purchases`

#### Kroy Toiri:

1. **New Purchase** button-e click korun
2. **Sorborokhari** nirbachon korun
3. **Godam** nirbachon korun (kon godame ponnyo ashbe)
4. **Ponnyo** nirbachon korun ebong poriman o mullo din
5. **Kroy Somponno** korun

#### Kroyr Probhav:

- Stock **bare** shobochalitobhabe
- Sorborokharir **rin balance** update hoy
- Ekti **stock movement** record hoy
- **Hisab-Nikesh**-e journal entry toiri hoy (porikolponito)

---

### 6.7 Grahok Bastapona

**Pejay:** `/customers`

#### Grahok Tothyo:

| Tothyo | Biboron |
|--------|---------|
| **Nam** | Grahok-er puro nam |
| **Phone** | Mobile nombor |
| **Email** | Email thikana |
| **Rin Balance** | Koto taka rin ache |
| **Loyalty Point** | Koto point joma ache |
| **Credit Limit** | Sorbochho koto rin dite parben |
| **Tax Tothyo** | Tax ID/nombor |

#### Rin Porishodhon:

- Grahok rin porishodhon korle **Sales** pejay giye **Add Payment** korun
- Athoba **POS** theke sel korar somoy "Due" payment nirbachon korun

---

### 6.8 Sorborokhari Bastapona

**Pejay:** `/suppliers`

#### Sorborokhari Tothyo:

| Tothyo | Biboron |
|--------|---------|
| **Nam** | Sorborokharir nam |
| **Company** | Kompanir nam |
| **Phone** | Jogajog nombor |
| **Rin Balance** | Koto taka dite ache |
| **Opening Balance** | Shurur rin |

---

### 6.9 Kharcha Bastapona

**Pejay:** `/expenses`

#### Kharchar Dhoron:

Bibhinno dhoroner kharcha track korun:
- Bidyut bil
- Bhara
- Beton
- Rokkhonabekkhon
- Poribohon
- Onnanyo

#### Kharcha Toiri O Onumodon:

1. **New Expense** button-e click korun
2. **Kharchar dhoron** nirbachon korun
3. **Poriman** likhun
4. **Shakha** nirbachon korun
5. **Biboron** likhun
6. **Submit** korun
7. Manager/Accountant **onumodon** korben

---

### 6.10 Hisab-Nikesh

**Pejay:** `/accounting`

#### Chart of Accounts:

Byabsar sob account dekhun:
- **Asset** (Sompad): Noddo, Bank, Inventory
- **Liability** (Dayo): Sorborokhari rin, rin
- **Equity** (Muldhon): Maliker muldhon
- **Revenue** (Aay): Sel, Service
- **Expense** (Kharcha): Bidyut, Bhara, Beton

#### Journal Entry:

Dual-entry accounting:
1. **New Journal** button-e click korun
2. **Biboron** likhun
3. **Debit** o **Credit** line jog korun
4. Debit o Credit soman hobe
5. **Post** korun

#### Trial Balance:

Sob account-er bortoman balance dekhun - debit o Credit soman ache kina jachai korun.

#### Cash Register:

- **Cash Register khulun** (Opening Cash shoho)
- **Cash in/out** record korun dinker moddhe
- **Bondho korun** (Actual Cash shoho)
- **X-Report** (kholar por) ba **Z-Report** (bondhoder por) PDF download korun

---

### 6.11 Tax Bastapona

**Pejay:** `/settings/tax`

#### Tax Rate:

Bibhinno tax rate set korun:
- Udahoron: "VAT 15%", "SD 5%", "Advance Income Tax 2%"

#### Tax Group:

Ekadhik tax rate eksathe jure din:
- Udahoron: "VAT+SD" = VAT 15% + SD 5%

#### Tax Zone:

Bibhinno elakay bhinnno tax:
- Udahoron: "Dhaka" = 15% VAT, "Chattogram" = 10% VAT

#### Tax Profile:

Desho onujayi tax system:
- **BD_VAT** - Bangladesh VAT
- **IN_GST** - Bharot GST
- **EU_VAT** - Europio VAT
- **US_SALES_TAX** - American Sales Tax
- **GENERIC** - Sadharon

#### Kivabe Kaj Kore:

1. Ponnyo ekti **tax group** deowa hoy
2. Sel-er somoy shei group-er sob tax rate **shobochalitobhabe** proyojyo hoy
3. Protiti sel item-e tax **snapshot** hishebe songrokkhito hoy
4. Return-er somoy mul snapshot theke **biporit** hoy (punorgonona hoy na)

---

### 6.12 Coupon O Loyalty

**Pejay:** `/loyalty`

#### Coupon:

Discount coupon toiri korun:
- **Percentage** - 10% discount
- **Fixed** - 500 taka discount
- **Minimum Purchase** - kompokkhe koto takar sele coupon kaj korbe
- **Byaboharer Sima** - mot kokhbar byabohar kora jabe
- **Tarikh Range** - kokhon theke kokhon porjonto karyokor

POS-e coupon byabohar:
1. Cart-e ponnyo jog korun
2. "Apply Coupon" button-e click korun
3. Coupon code likhun
4. Discount shobochalitobhabe proyojyo hobe

#### Loyalty Point:

- Grahok proti sel-e **point orjon** kore (shobochalit)
- Point **redeem** kora jay (discount hishebe)
- Point **somonnoy** kora jay (manuali beshano/kamano)

---

### 6.13 Report

**Pejay:** `/reports`

#### Sel Summary Report:

- Nirnistho tarikh range-e moto sel, kharcha, lav
- Shakha onujayi tulona

#### Ponnyo Onujayi Sel:

- Kon ponnyo sobcheye beshi bikri hoyeche
- Kon ponnyo kom bikri hoyeche

#### Lav Report:

- Protiti sel-er lav (bikrommulyo - krommulyo)
- Samagric lav-er hisab

#### Stock Mulloyan:

- Godame moto koto takar stock ache
- Ponnyo onujayi stock mullo

#### Kom Stock Report:

- Jebono ponnyo-r stock come geche
- Punorjonno korte hobe emon ponnyo

#### Rin Report:

- Grahokder moto bekya
- Grahok onujayi rin-er talika

#### Tax Report:

- **Tax Summary**: Moto koto tax songrohito hoyeche
- **Tax Return**: Sarkari tax return form

---

### 6.14 Team O Onumoti

**Pejay:** `/team`

#### Bhumika (Roles):

Notun bhumika toiri korun ebong proyojoniyo onumoti din:

**Udahoron:**

| Bhumika | Onumoti |
|---------|---------|
| **Cashier** | POS, sel, grahok |
| **Manager** | Sob + stock somonnoy + report |
| **Accountant** | Hisab-nikesh + kharcha + tax |
| **Godam Kormochari** | Inventory + kroy |

#### Onumoti Prokar (21 Module x 76 Onumoti):

Protiti module-er jonno alada onumoti:
- **view** - dekhte parben
- **create** - toiri korte parben
- **update** - shompadona korte parben
- **delete** - muchte parben
- **export** - export korte parben
- **approve** - onumodon korte parben
- **manage** - sompurno bastapona

---

### 6.15 Subscription

**Pejay:** `/subscription`

#### Plan:

Bibhinno plan theke pochondo-shoi plan nirbachon korun:

| Simabaddhota | Artho |
|-------------|-------|
| **Shakha Sima** | Sorbochho koto-ti shakha toiri kora jabe |
| **Godam Sima** | Sorbochho koto-ti godam toiri kora jabe |
| **Byaboharokari Sima** | Sorbochho kotojon user thakbe |
| **Ponnyo Sima** | Sorbochho koto-ti ponnyo toiri kora jabe |
| **Masik Sel Sima** | Mash-e koto takar sel kora jabe |

`null` sima = simahin (joto khushi)

---

### 6.16 Notification

**Pejay:** `/notifications`

- Ghtnaboliye tothyo dekhun
- Poridhito notification dekhun
- Notification muchhe din

---

### 6.17 Audit Log

**Pejay:** `/audit-log`

- Kon user ki kaj koreche ta dekhun
- Sob guruttbopurno kajer log thake
- Actor, action, model, tarikh onujayi filter korun

---

## 7. Byaboharokari Bhumika O Onumoti

### Default Bhumika (Registration-e Provisioned)

| Bhumika | Slug | Onumoti |
|---------|------|---------|
| **Owner** | (implicit) | SOB ONUMOTI (implicitly code-e granted) |
| **Admin** | `admin` | SOB ONUMOTI (`*`) |
| **Branch Manager** | `branch_manager` | dashboard.view, branches.view, warehouses.view, products.view, inventory.view/adjust/approve, pos.view/create/refund/discount/price_override, sales.view/refund, customers.view/create, reports.view/export |
| **Cashier** | `cashier` | dashboard.view, products.view, pos.view/create, sales.view, customers.view/create |
| **Accountant** | `accountant` | dashboard.view, accounting.view/create/approve, expenses.view/create/approve, taxes.view/manage, reports.view/export, sales.view, purchases.view |
| **Viewer** | `viewer` | dashboard.view, products.view, sales.view, reports.view |

### Bhumika-Nirdharon Nirokhotar Niyom

- User **nijeke bhumika poriborton** korte pare na (403)
- Shudhu **owner** (ba super-admin) `owner` role grant/revoke korte pare
- Shakha-scoped user **shakhar bahire user shompadon** korte pare na
- `owner` role-er onumoti **non-owner** edit korte pare na
- Sob role-poriborton **audit** hoy

---

## 8. Settings O Configuration

### Business Settings

**Pejay:** `/settings`

- **Business Name** - Byabsar nam
- **Business Type** - Byabsar dhoron
- **Currency** - Mudra (BDT, INR, USD, EUR ityadi)
- **Tax Profile** - Tax system (BD_VAT, IN_GST, EU_VAT, US_SALES_TAX)
- **Price Display** - Tax Inclusive ba Tax Exclusive
- **Address** - Byabsar thikana
- **Phone** - Jogajog nombor
- **Email** - Byabsar email

### Appearance

- **Theme** - Light ba Dark
- **Color Palette** - 6-ti ronger palette theke nirbachon
- **Sidebar** - Collapsed ba Expanded

### Localization

- **Language** - Bangla, English, Arabic, German, Japanese
- **Number Locale** - Songo rupokh
- **Date Locale** - Tarikh rupokh

### Notification

- Email notification on/off
- In-app notification

---

## 9. Report O Bishleshon

### Kothay Report Dekhte Parben

| Report Pejay | Ki Dekhte Parben |
|-------------|------------------|
| `/reports` | Sel Summary, Ponnyo Onujayi Sel, Lav, Stock Mulloyan, Kom Stock, Rin |
| `/reports/tax` | Tax Summary, Tax Return |
| `/loyalty` | Loyalty Point, Coupon |
| `/dashboard` | Dainik Sarosongkhep, Sel Trend |

### Report Kivabe Byabohar Korben

1. Report pejay jan
2. **Tarikh range** select korun
3. **Shakha** filter korun (jodi ekadhik shakha thake)
4. **Generate** button-e click korun
5. Report dekhun
6. **Export** button diye PDF/Excel-e download korun

---

## 10. Shobdaboli

| Shobdo | Artho |
|--------|-------|
| **Tenant** | Ekti byabsar jonno alada jayga (multi-tenancy-er angsho) |
| **Branch** | Dokan/okhil |
| **Warehouse** | Godam |
| **SKU** | Stock Keeping Unit - ponnyo-r unique code |
| **Barcode** | Ponnyo-r scanning code |
| **Inventory** | Stock bastapona |
| **Stock Movement** | Stock er itihas (kibeche, kobe, koto) |
| **Immutable** | Pariborton kora jay na (ledger) |
| **Dual-Entry** | Prতিটি transaction 2 bar lekha hoy (debit + credit) |
| **Journal** | Hisab-nikesh-er record |
| **Trial Balance** | Sob account-er balance-er talika |
| **Snapshot** | Sel-er somoy mullo/tax er photo (return-e byabohar hoy) |
| **Idempotency** | Eki request bar bar pathaleo eki folash falbe |
| **RBAC** | Role-Based Access Control - bhumika-voittik onumoti |
| **Audit Log** | Kader ki koreche er record |
| **X-Report** | Cash register kholar por-er report |
| **Z-Report** | Cash register bondhoder por-er report |
| **Opening Cash** | Dokan khular somoy cash poriman |
| **Actual Cash** | Hate gonno kore pawa cash |
| **Expected Cash** | System-er ganito cash |
| **Difference** | Actual - Expected |

---

**SmartPOS Pro - Byaboharokari Guide Somaponno**

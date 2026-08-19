# SmartPOS Pro Project Master Prompt

Create a modern, production ready POS and retail management platform with a premium user interface, fast performance, and complete business functionality.

## Project name

SmartPOS Pro

## Main goal

Build a complete cloud based POS application for retail shops, supermarkets, pharmacies, electronics stores, fashion stores, restaurants, wholesalers, and multi branch businesses.

The application should feel modern, simple, fast, and professional. It must not look like a traditional outdated admin dashboard. The interface should be clean, spacious, responsive, and easy for cashiers, managers, accountants, and business owners to use.

## Technology stack

### Frontend

1. Next.js 16 with App Router
2. React 19
3. TypeScript
4. Tailwind CSS
5. shadcn/ui
6. TanStack Query
7. Zustand for local UI state
8. React Hook Form
9. Zod validation
10. Recharts for analytics
11. Lucide icons
12. Framer Motion for subtle animations
13. PWA support for offline POS functionality

### Backend

1. Laravel 13
2. PHP 8.4
3. Laravel Sanctum authentication
4. Laravel Reverb for real time updates
5. Laravel Queue and Horizon
6. Laravel Scheduler
7. REST API architecture
8. Service and repository pattern where appropriate
9. Form Request validation
10. API Resources for response formatting
11. Policies and Gates for authorization
12. Activity logging and audit trail

### Database and infrastructure

1. PostgreSQL
2. Redis
3. S3 compatible object storage
4. Docker
5. Nginx
6. GitHub Actions
7. AWS deployment support
8. Cloudflare support
9. PostHog error monitoring (backend only)
10. PostHog product analytics

## Architecture requirements

1. Use a multi tenant architecture.
2. Each business must have fully isolated data.
3. Each business can create multiple branches and warehouses.
4. Each branch can have separate users, inventory, pricing, and reports.
5. Use tenant scoped queries across all business related tables.
6. Support scalable API design.
7. Keep the code modular and maintainable.
8. Separate domain logic from controllers.
9. Add proper database indexes.
10. Use database transactions for sales, purchases, payments, returns, and stock changes.
11. Prevent race conditions during inventory updates.
12. Use idempotency keys for sensitive API operations.
13. Store all inventory movements in an immutable stock ledger.
14. Store all financial movements in an immutable accounting ledger.
15. Add audit logs for important actions.

## Design direction

Create a premium SaaS interface inspired by Linear, Stripe Dashboard, Shopify Admin, Square POS, and modern fintech applications.

The design should include:

1. Clean typography
2. Spacious layouts
3. Soft shadows
4. Rounded cards
5. Clear visual hierarchy
6. Smooth hover states
7. Subtle page transitions
8. Elegant empty states
9. Helpful loading skeletons
10. Accessible color contrast
11. Dark mode and light mode
12. Responsive mobile, tablet, and desktop layouts
13. Keyboard friendly navigation
14. Consistent spacing and reusable design tokens
15. Professional charts and data tables
16. No overcrowded screens
17. No outdated gradients or excessive visual effects
18. Use accent colors carefully
19. Show useful contextual actions
20. Keep common workflows within as few clicks as possible

## Main application layout

1. Collapsible sidebar
2. Top navigation bar
3. Global search
4. Branch selector
5. Warehouse selector
6. Date range selector
7. Notifications panel
8. Quick action button
9. User profile menu
10. Keyboard shortcut command menu
11. Breadcrumb navigation
12. Contextual page actions

## User roles

1. Super Admin
2. Business Owner
3. Admin
4. Branch Manager
5. Warehouse Manager
6. Cashier
7. Accountant
8. Sales Staff
9. Purchase Staff
10. Viewer

Create a complete role and permission management system.

Permissions should support:

1. Module level access
2. Page level access
3. Action level access
4. Branch specific access
5. Warehouse specific access
6. Create permission
7. View permission
8. Update permission
9. Delete permission
10. Export permission
11. Approve permission
12. Refund permission
13. Discount permission
14. Price override permission

## Authentication features

1. Login
2. Registration
3. Forgot password
4. Reset password
5. Email verification
6. Two factor authentication
7. Session management
8. Device history
9. Login activity
10. Secure logout
11. Remember me
12. Optional Google login
13. Optional Microsoft login

## Onboarding flow

1. Create business
2. Add business information
3. Select business type
4. Add first branch
5. Add warehouse
6. Configure currency
7. Configure tax
8. Configure invoice settings
9. Import products
10. Add opening stock
11. Invite team members
12. Configure receipt
13. Complete onboarding checklist

## Dashboard

Create a highly visual and useful dashboard with:

1. Today’s sales
2. Today’s profit
3. Today’s expenses
4. Today’s purchases
5. Net cash flow
6. Total customers
7. Total products
8. Low stock count
9. Out of stock count
10. Pending payments
11. Customer dues
12. Supplier dues
13. Sales trend chart
14. Profit trend chart
15. Purchase trend chart
16. Expense trend chart
17. Payment method breakdown
18. Top selling products
19. Low performing products
20. Best customers
21. Recent sales
22. Recent purchases
23. Recent expenses
24. Branch performance
25. Warehouse stock overview
26. Alerts and reminders
27. Date comparison
28. Previous period comparison
29. Custom date filtering
30. Export dashboard report

## POS terminal

Create a fast and polished POS screen optimized for touch screens and keyboard usage.

The POS screen should include:

1. Product search
2. Barcode scanning
3. SKU search
4. Category filter
5. Brand filter
6. Favorite products
7. Recently sold products
8. Product grid
9. Product list
10. Product image
11. Variant selection
12. Unit selection
13. Quantity controls
14. Price override with permission
15. Item level discount
16. Cart level discount
17. Coupon support
18. Tax calculation
19. Inclusive tax
20. Exclusive tax
21. Customer selection
22. Quick customer creation
23. Walk in customer
24. Sales notes
25. Shipping charge
26. Service charge
27. Delivery address
28. Hold sale
29. Resume held sale
30. Suspend sale
31. Draft sale
32. Quotation creation
33. Multiple payment methods
34. Split payment
35. Partial payment
36. Cash payment
37. Card payment
38. Bank payment
39. Mobile banking payment
40. Digital wallet payment
41. Due payment
42. Change calculation
43. Custom amount keypad
44. Quick cash buttons
45. Receipt preview
46. Print receipt
47. Email receipt
48. SMS receipt
49. WhatsApp receipt
50. Full screen POS mode
51. Keyboard shortcuts
52. Touch optimized buttons
53. Offline sale support
54. Automatic sync after internet recovery
55. Cart recovery after browser refresh
56. Return and exchange workflow
57. Refund workflow
58. Customer loyalty redemption
59. Gift card support
60. Cash drawer integration support
61. Thermal printer support
62. Multiple receipt templates

## Sales management

1. Sales list
2. Sales details
3. Invoice generation
4. Draft sales
5. Quotations
6. Held sales
7. Completed sales
8. Credit sales
9. Partial payments
10. Due sales
11. Sales returns
12. Item exchanges
13. Refunds
14. Payment history
15. Sales notes
16. Shipping details
17. Sales approval
18. Invoice status
19. Payment status
20. Branch wise filtering
21. Cashier wise filtering
22. Customer wise filtering
23. Product wise filtering
24. Date filtering
25. Export to PDF
26. Export to Excel
27. Export to CSV
28. Bulk invoice download
29. Duplicate invoice
30. Print invoice

## Product management

1. Product list
2. Product creation
3. Product editing
4. Product deletion
5. Product archive
6. Product restore
7. Product categories
8. Product subcategories
9. Brands
10. Units
11. Product variants
12. Variant attributes
13. SKU generation
14. Barcode generation
15. QR code generation
16. Product images
17. Product gallery
18. Product description
19. Purchase price
20. Selling price
21. Wholesale price
22. Branch specific pricing
23. Customer group pricing
24. Tax configuration
25. Discount configuration
26. Minimum stock level
27. Maximum stock level
28. Reorder quantity
29. Batch tracking
30. Serial number tracking
31. Expiry tracking
32. Warranty tracking
33. Product bundles
34. Combo products
35. Service products
36. Digital products
37. Product tags
38. Product status
39. Supplier mapping
40. Opening stock
41. Bulk import
42. Bulk update
43. Bulk export
44. Product duplication
45. Barcode label printing
46. Product history
47. Price history
48. Stock history

## Inventory management

1. Real time stock
2. Branch wise stock
3. Warehouse wise stock
4. Available stock
5. Reserved stock
6. Damaged stock
7. Expired stock
8. In transit stock
9. Stock adjustment
10. Stock increase
11. Stock decrease
12. Stock transfer
13. Branch transfer
14. Warehouse transfer
15. Transfer approval
16. Transfer receiving
17. Transfer rejection
18. Stock counting
19. Cycle counting
20. Physical inventory count
21. Stock discrepancy
22. Stock ledger
23. Inventory valuation
24. FIFO valuation
25. Weighted average valuation
26. Low stock alerts
27. Out of stock alerts
28. Reorder suggestions
29. Stock movement history
30. Batch inventory
31. Serial number inventory
32. Expiry alerts
33. Damaged product tracking
34. Lost product tracking
35. Stock aging report
36. Inventory forecast
37. Supplier based stock view
38. Product based stock view
39. Category based stock view

## Purchase management

1. Purchase requests
2. Purchase orders
3. Purchase invoices
4. Goods received notes
5. Supplier selection
6. Purchase approval
7. Partial receiving
8. Full receiving
9. Purchase payment
10. Partial payment
11. Due payment
12. Purchase returns
13. Supplier refund
14. Purchase attachments
15. Purchase notes
16. Purchase taxes
17. Purchase discounts
18. Additional costs
19. Shipping cost
20. Import cost
21. Landed cost calculation
22. Batch creation
23. Expiry capture
24. Serial number capture
25. Purchase history
26. Payment history
27. Supplier ledger
28. Purchase status
29. Payment status
30. Export and print

## Customer management

1. Customer list
2. Customer profile
3. Customer groups
4. Customer type
5. Contact information
6. Billing address
7. Shipping address
8. Tax information
9. Credit limit
10. Opening balance
11. Customer balance
12. Customer dues
13. Customer ledger
14. Purchase history
15. Payment history
16. Return history
17. Loyalty points
18. Reward history
19. Gift cards
20. Customer notes
21. Attachments
22. Tags
23. Birthday
24. Anniversary
25. Customer status
26. Communication history
27. Email customer
28. SMS customer
29. WhatsApp customer
30. Customer statement download

## Supplier management

1. Supplier list
2. Supplier profile
3. Contact information
4. Address
5. Tax information
6. Opening balance
7. Supplier balance
8. Supplier dues
9. Supplier ledger
10. Purchase history
11. Payment history
12. Purchase return history
13. Supplier notes
14. Attachments
15. Tags
16. Supplier status
17. Supplier statement
18. Email supplier
19. SMS supplier
20. WhatsApp supplier

## Expense management

1. Expense categories
2. Expense subcategories
3. Create expense
4. Recurring expenses
5. Branch expenses
6. Department expenses
7. Employee expenses
8. Expense approval
9. Expense attachments
10. Receipt upload
11. Expense payment method
12. Expense account
13. Expense date
14. Expense notes
15. Expense tags
16. Expense reports
17. Monthly expense summary
18. Category expense summary
19. Export expense data

## Accounting module

1. Chart of accounts
2. Account groups
3. Cash accounts
4. Bank accounts
5. Mobile banking accounts
6. Digital wallet accounts
7. Income accounts
8. Expense accounts
9. Asset accounts
10. Liability accounts
11. Equity accounts
12. Journal entries
13. Journal approval
14. General ledger
15. Customer ledger
16. Supplier ledger
17. Trial balance
18. Profit and loss statement
19. Balance sheet
20. Cash flow statement
21. Account transfer
22. Bank deposit
23. Bank withdrawal
24. Bank reconciliation
25. Cash reconciliation
26. Opening balances
27. Closing balances
28. Fiscal year
29. Accounting periods
30. Petty cash
31. Daily cash report
32. Cash register report
33. Payment vouchers
34. Receipt vouchers
35. Contra vouchers
36. Accounting attachments
37. Financial report export

## Cash register management

1. Open register
2. Opening cash
3. Cash in
4. Cash out
5. Register transactions
6. Register balance
7. Expected cash
8. Actual cash
9. Cash difference
10. Close register
11. Register closing note
12. Register session history
13. Cashier session
14. Manager approval
15. Register report

## Loyalty and promotions

1. Loyalty points
2. Points earning rules
3. Points redemption rules
4. Customer levels
5. Membership tiers
6. Gift cards
7. Coupon codes
8. Promotional campaigns
9. Product promotions
10. Category promotions
11. Buy one get one offer
12. Bundle discounts
13. Time based discounts
14. Branch specific promotions
15. Customer group promotions
16. Minimum purchase conditions
17. Usage limits
18. Expiry dates
19. Promotion performance report

## Employee and staff management

1. Employee list
2. Employee profile
3. Designation
4. Department
5. Branch assignment
6. Role assignment
7. Shift assignment
8. Attendance
9. Clock in
10. Clock out
11. Leave records
12. Salary information
13. Commission rules
14. Sales commission
15. Cashier performance
16. Employee activity
17. Login activity
18. Employee documents
19. Employee status

## Reports

Create a complete reporting center.

Include:

1. Sales summary report
2. Sales detail report
3. Sales by product
4. Sales by category
5. Sales by brand
6. Sales by branch
7. Sales by cashier
8. Sales by customer
9. Sales by payment method
10. Hourly sales report
11. Daily sales report
12. Monthly sales report
13. Annual sales report
14. Profit report
15. Gross profit report
16. Net profit report
17. Purchase report
18. Purchase return report
19. Sales return report
20. Expense report
21. Tax report
22. Inventory report
23. Stock valuation report
24. Stock movement report
25. Low stock report
26. Out of stock report
27. Expiry report
28. Damaged stock report
29. Customer due report
30. Supplier due report
31. Customer ledger report
32. Supplier ledger report
33. Cash flow report
34. Cash register report
35. Payment report
36. Branch performance report
37. Employee performance report
38. Loyalty report
39. Discount report
40. Tax summary report
41. Profit and loss report
42. Balance sheet report
43. Trial balance report
44. Audit log report
45. Custom report builder

All reports should support:

1. Date range filtering
2. Branch filtering
3. Warehouse filtering
4. User filtering
5. Customer filtering
6. Supplier filtering
7. Product filtering
8. Category filtering
9. Payment method filtering
10. Export to PDF
11. Export to Excel
12. Export to CSV
13. Print view
14. Scheduled email reports
15. Saved report filters

## Notification system

1. In app notifications
2. Email notifications
3. SMS notifications
4. WhatsApp notifications
5. Low stock alerts
6. Out of stock alerts
7. Expiry alerts
8. Due payment reminders
9. Purchase order reminders
10. Transfer approval alerts
11. Expense approval alerts
12. Refund alerts
13. Suspicious activity alerts
14. Daily summary notifications
15. Weekly summary notifications
16. Monthly summary notifications

## Settings

1. Business profile
2. Business logo
3. Branch settings
4. Warehouse settings
5. Currency settings
6. Currency formatting
7. Date formatting
8. Time formatting
9. Timezone
10. Language
11. Tax settings
12. Invoice settings
13. Receipt settings
14. Barcode settings
15. Label settings
16. POS settings
17. Payment method settings
18. Customer settings
19. Supplier settings
20. Inventory settings
21. Purchase settings
22. Sales settings
23. Loyalty settings
24. Accounting settings
25. Notification settings
26. Email settings
27. SMS settings
28. WhatsApp settings
29. Storage settings
30. Backup settings
31. Security settings
32. Audit settings
33. Integration settings
34. Theme settings
35. Custom domain support

## Integrations

1. Stripe
2. PayPal
3. SSLCommerz
4. bKash
5. Nagad
6. Rocket
7. Twilio
8. WhatsApp Cloud API
9. SendGrid
10. Mailgun
11. AWS SES
12. S3
13. Cloudinary
14. Google Drive backup
15. QuickBooks
16. Xero
17. Zapier
18. Webhooks
19. REST API access
20. External barcode scanners
21. Thermal printers
22. Cash drawers
23. Customer displays

## Offline PWA functionality

1. Installable POS application
2. Offline product search
3. Offline cart
4. Offline sale creation
5. Local queue for pending sales
6. Automatic sync after internet recovery
7. Conflict handling
8. Sync status indicator
9. Offline customer selection
10. Offline receipt generation
11. Local cache versioning
12. Secure IndexedDB storage

## Audit and security

1. Activity logs
2. Login history
3. Device history
4. Failed login attempts
5. IP tracking
6. Important action confirmation
7. Permission checks
8. Two factor authentication
9. Rate limiting
10. CSRF protection
11. XSS prevention
12. SQL injection prevention
13. Secure file uploads
14. Encrypted sensitive data
15. Password policies
16. Session expiration
17. Remote session logout
18. API token management
19. Audit log export
20. Suspicious activity alerts

## Super Admin SaaS panel

1. Tenant management
2. Subscription plans
3. Plan features
4. Feature limits
5. Trial management
6. Billing management
7. Coupon management
8. Tenant suspension
9. Tenant impersonation
10. Usage monitoring
11. Storage usage
12. API usage
13. Active user count
14. Transaction count
15. Revenue metrics
16. Subscription metrics
17. Churn metrics
18. Failed payment tracking
19. Support ticket management
20. Announcement management
21. System settings
22. System logs
23. Queue monitoring
24. Scheduled job monitoring
25. Global audit logs

## Subscription plans

Create Free, Starter, Professional, Business, and Enterprise plans.

Plans should support limits for:

1. Branches
2. Warehouses
3. Users
4. Products
5. Monthly sales
6. Storage
7. Reports
8. API access
9. Integrations
10. Advanced accounting
11. Offline POS
12. Priority support

## User experience requirements

1. Every table must include search, filters, sorting, pagination, column visibility, bulk selection, export, and saved views.
2. Every form must include validation, helpful error messages, autosave where useful, confirmation before destructive actions, and unsaved change warnings.
3. Use loading skeletons instead of blank loading screens.
4. Use clear empty states with action buttons.
5. Use toast notifications for success and error messages.
6. Show breadcrumbs on inner pages.
7. Add keyboard shortcuts for POS and common actions.
8. Make all critical workflows mobile responsive.
9. Add tooltips for unfamiliar actions.
10. Preserve filter state when navigating back.
11. Add recent items and favorite pages.
12. Use optimistic UI updates where safe.
13. Add confirmation dialogs for refunds, deletions, stock adjustments, and financial actions.
14. Show real time updates for inventory, sales, and notifications.
15. Add command palette navigation.

## Frontend structure

Create reusable components for:

1. App shell
2. Sidebar
3. Header
4. Page header
5. Data table
6. Filter bar
7. Date range picker
8. Stats card
9. Chart card
10. Form field
11. Modal
12. Drawer
13. Confirmation dialog
14. Empty state
15. Loading skeleton
16. File uploader
17. Product selector
18. Customer selector
19. Supplier selector
20. Payment modal
21. Receipt preview
22. Status badge
23. Permission guard
24. Branch selector
25. Warehouse selector

## Backend modules

1. Authentication
2. Tenancy
3. Users
4. Roles
5. Permissions
6. Businesses
7. Branches
8. Warehouses
9. Products
10. Categories
11. Brands
12. Units
13. Variants
14. Inventory
15. Stock movements
16. Sales
17. Sale items
18. Payments
19. Returns
20. Purchases
21. Purchase items
22. Suppliers
23. Customers
24. Expenses
25. Accounting
26. Loyalty
27. Promotions
28. Employees
29. Reports
30. Notifications
31. Subscriptions
32. Integrations
33. Audit logs
34. Settings
35. Files
36. Background jobs

## Database expectations

Create a complete normalized PostgreSQL schema with migrations, indexes, foreign keys, tenant scoping, soft deletes where appropriate, audit fields, and transaction safe inventory logic.

Important entities should include:

1. Tenants
2. Businesses
3. Branches
4. Warehouses
5. Users
6. Roles
7. Permissions
8. Customers
9. Suppliers
10. Categories
11. Brands
12. Units
13. Products
14. Product variants
15. Product prices
16. Product batches
17. Product serial numbers
18. Inventory balances
19. Stock movements
20. Stock transfers
21. Sales
22. Sale items
23. Sale payments
24. Sale returns
25. Purchases
26. Purchase items
27. Purchase payments
28. Purchase returns
29. Expenses
30. Accounts
31. Journal entries
32. Journal lines
33. Cash registers
34. Cash register sessions
35. Loyalty transactions
36. Coupons
37. Promotions
38. Notifications
39. Attachments
40. Audit logs
41. Subscriptions
42. Plans
43. Feature limits
44. Settings

## API requirements

1. Use RESTful endpoints.
2. Use versioned API routes.
3. Add pagination.
4. Add filtering.
5. Add sorting.
6. Add search.
7. Add API Resources.
8. Add Form Requests.
9. Add authorization policies.
10. Add rate limiting.
11. Add idempotency support.
12. Add consistent error responses.
13. Add OpenAPI documentation.
14. Add webhook support.
15. Add API token support.

## Testing requirements

1. Unit tests
2. Feature tests
3. API tests
4. Permission tests
5. Tenant isolation tests
6. Inventory transaction tests
7. Sale creation tests
8. Return tests
9. Purchase tests
10. Accounting tests
11. Subscription limit tests
12. Frontend component tests
13. End to end tests
14. POS checkout tests
15. Offline sync tests

## Seed data

Create realistic seed data for:

1. One demo tenant
2. Three branches
3. Two warehouses
4. Ten users with different roles
5. One hundred products
6. Multiple categories
7. Multiple brands
8. Multiple customers
9. Multiple suppliers
10. Sales history
11. Purchase history
12. Expenses
13. Accounting data
14. Notifications
15. Subscription plans

## Development requirements

1. Use clean folder structures.
2. Use strict TypeScript.
3. Use ESLint.
4. Use Prettier.
5. Use PHPStan.
6. Use Laravel Pint.
7. Add Docker Compose.
8. Add environment example files.
9. Add database setup instructions.
10. Add seed instructions.
11. Add test instructions.
12. Add deployment instructions.
13. Add API documentation.
14. Add architecture documentation.
15. Add module documentation.

## Initial pages to build first

1. Login
2. Registration
3. Onboarding
4. Dashboard
5. POS terminal
6. Products list
7. Product creation
8. Inventory overview
9. Sales list
10. Sale details
11. Purchases list
12. Customers list
13. Suppliers list
14. Expenses list
15. Reports dashboard
16. User management
17. Roles and permissions
18. Branch settings
19. Business settings
20. Subscription page

## Important implementation rule

Do not generate only static mockups.

Create working pages, reusable components, realistic data flows, API contracts, validation, state management, responsive layouts, loading states, empty states, error states, and permission based access.

Start by creating:

1. Complete system architecture
2. Database schema
3. Backend module structure
4. Frontend folder structure
5. Design system
6. Authentication flow
7. Tenant onboarding flow
8. Dashboard
9. POS terminal
10. Product and inventory modules

After completing each module, provide:

1. Files created
2. Database changes
3. API endpoints
4. UI components
5. Validation rules
6. Test coverage
7. Remaining tasks

The final result should feel like a polished commercial SaaS product, not a basic admin template.

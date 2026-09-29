# Spec: Credit Payment and Customer Balance (Utang) System

## Problem

The current sales system supports Cash, GCash, and Credit payment methods but does not properly track:
- Partial payments against credit transactions
- Customer outstanding balances (utang)
- Payment history for each credit transaction
- Subsequent payments toward previously incurred credit

This prevents the store from offering credit/utang facilities to regular customers with accurate balance tracking and settlement workflows.

## Users

1. **Cashier / Store Owner** - Records credit sales, accepts partial payments, settles outstanding balances, views customer credit dashboard.
2. **Business Owner / Auditor** - Reviews credit history, balances, and payment records across all customers.

## Goals

- Allow credit transactions (utang) with partial initial payments and automatic balance calculation.
- Maintain customer-level outstanding balance by summing all credit transactions minus all payments received.
- Accept subsequent partial or full payments against existing credit.
- Provide a customer credit dashboard with totals, history, and payment status.
- Preserve original credit transaction totals and link each subsequent payment to its source transaction/customer.
- Do not break existing Cash / GCash / regular Credit workflows.

## Non-Goals

- No invoice PDF generation or printing.
- No SMS/email notifications to customers.
- No user roles or permissions beyond the existing authenticated setup.
- No multi-currency support; remain PHP only.
- No Eloquent ORM models; use Laravel Query Builder exclusively (per project convention).
- No custom helper functions; use inline logic and Query Builder methods.

## Functional Requirements

### FR1 — Credit Transaction Creation
When the payment method is `Credit` (or renamed label `Credit/Utang`), the sale flow must:
- Require a customer (either selected existing customer or new regular customer by name). Walk-in customers are NOT allowed for Credit payments.
- Record the sale with items, total_amount, and initial amount_paid (can be 0.00).
- Create a corresponding credit transaction record linked to the sale and customer, storing:
  - Original total amount (unchangeable)
  - Initial payment amount
  - Initial remaining balance (total - initial payment)
  - Payment status (see FR5)

### FR2 — Partial Payment on New Credit Sale
When recording a new Credit sale, the cashier may enter `amount_paid` < `total_amount` (including 0.00). The system must calculate:
```
remaining_balance = total_amount - amount_paid
```
and persist both values. Validation: amount_paid >= 0.00.

### FR3 — Customer Running Balance
Per-customer outstanding balance is always **computed** from credit transactions minus payments (never stored as a single updatable field without a derived computation path):
```
customer_outstanding = SUM(credit_transactions.original_total) - SUM(all credit_payments.amount_paid for that customer)
```
Customer endpoints and the credit dashboard return this derived value.

### FR4 — Subsequent Credit Payment (Pay Existing Utang)
Provide an endpoint and UI to record a payment against an existing customer with outstanding balance. The payment:
- Links to the customer
- Optionally links to a specific credit transaction (for per-transaction settlement)
- Supports full or partial amount
- Validates: amount > 0 and amount <= (per-transaction balance if specified, otherwise customer outstanding)
- Creates a credit_payments record
- Recomputes affected credit transaction balance and status

### FR5 — Payment Status Calculation
The system computes status for each credit transaction dynamically:
- **Paid** — original_total > 0 AND balance == 0 AND paid within the original transaction (no later payments needed)
- **Partially Paid** — total_payments > 0 AND balance > 0
- **Unpaid/Credit** — total_payments == 0 AND balance > 0
- **Fully Settled** — previously had balance > 0, now balance == 0 via subsequent payment(s)

### FR6 — Credit Payment History
For each customer and/or credit transaction, return complete history including:
- Customer name
- Transaction ID / transaction_number
- Transaction date
- Items purchased (concatenated list or per-line)
- Total amount
- Amount paid (initial + any subsequent)
- Remaining balance
- Current payment status
- Individual payment records (date, amount, notes)

### FR7 — Validation Guards
- Payment amounts must be >= 0 on sale creation.
- Payment amounts for subsequent credit pay must be > 0.
- Subsequent payment must NOT exceed the customer's current outstanding (or specific transaction balance if target specified).
- Credit payment method requires a valid, persisted customer (not null, not walk-in).

### FR8 — Credit Dashboard Tab
Add a new `credit` tab in the SalesTrackerApp UI showing:
- Per-customer summary rows: Name, Total Credit, Total Payments, Remaining Balance, Last Payment Date, Status.
- Expandable customer detail: credit transaction list + payment history.
- "Pay Credit" action: open form to apply a payment to customer (with optional specific transaction select).
- Clear display of Total Amount, Amount Paid, Remaining Balance for each transaction.

### FR9 — Database Relationship Model (Separation of Concerns)
- Original `sales`, `payments`, `sale_items` tables remain unchanged in schema for existing behavior.
- New `credit_transactions` table stores the credit-specific snapshot of a sale: sale_id FK, customer_id FK, original_total, initial_payment, created_at.
- New `credit_payments` table stores each subsequent (and optionally initial) payment record: id, credit_transaction_id FK (nullable for customer-level), customer_id FK, amount_paid, payment_date, notes, created_at.
- Original `payments.sale_id` unique constraint kept — existing workflows untouched. Credit supplementary payments live in `credit_payments`.

### FR10 — No Breaking Changes
- Existing `Cash` and `GCash` flows identical save/load behavior.
- Existing routes continue to work; new routes prefixed logically (e.g. `/api/credit-transactions`, `/api/credit-payments`, `/api/customers/{id}/credit-summary`).
- Sale index/history endpoints continue returning same shape; status fields are additional where needed.

## Non-Functional Requirements

- **NFR1 Performance**: Queries for credit dashboard and customer summary use indexed joins (customer_id, credit_transaction_id, sale_date).
- **NFR2 Consistency**: All writes that modify credit balance or create records happen inside DB transactions.
- **NFR3 Conventions**: Follow existing project conventions strictly:
  - Controllers use `Illuminate\Support\Facades\DB` Query Builder (no Eloquent).
  - No helper functions; logic lives in controller private methods or inline.
  - JSON responses: `{ message, data }` shape with appropriate HTTP codes (200/201/404/422/500).
  - Validation via `Illuminate\Support\Facades\Validator`.
  - Frontend: Options API Vue 3 component in SalesTrackerApp.vue; same `panel`, `btn`, `table-wrap`, `form-grid` CSS classes.
  - Money formatting: `Intl.NumberFormat('en-PH', {style: 'currency', currency: 'PHP'})`.
- **NFR4 UX**: Credit/Utang UI clearly labels Total Amount, Amount Paid, and Remaining Balance. Inputs reject negative values.

## Constraints & Dependencies

- PHP/Laravel backend as configured (see composer.json and existing controllers).
- Vue 3 single-file component SalesTrackerApp.vue.
- Existing DB tables: `customers`, `sales`, `sale_items`, `payments` must not be structurally modified except where explicitly stated (no drops of existing columns/keys).
- New migrations only additive.

## Assumptions

- A single sale with method "Credit" maps to exactly one credit_transaction row.
- The existing `payments` table's `amount_paid` for Credit sales captures the INITIAL payment at sale time. Subsequent payments live in `credit_payments`.
- "Credit/Utang" will be the label shown in UI dropdown for `Credit` enum value to match user terminology; backend value stays `Credit` to keep compatibility.
- A customer-level payment (not tied to a single credit transaction) will apply credits to the OLDEST unpaid credit transactions first until the payment amount is exhausted (FIFO allocation).

## Open Questions

None at this time. Assumptions above encode reasonable defaults; if user pushes back we adjust.

---

## Acceptance Criteria

### AC1 (rule) — New tables created via migrations
- `credit_transactions` table exists with: id, sale_id (FK sales cascade), customer_id (FK customers restrict), original_total(10,2), initial_payment(10,2), created_at, updated_at. Indices on customer_id, sale_id.
- `credit_payments` table exists with: id, credit_transaction_id (FK credit_transactions cascade, nullable), customer_id (FK customers restrict), amount_paid(10,2), notes (string nullable), payment_date datetime. Indices on credit_transaction_id, customer_id, payment_date.
- Evidence: run `php artisan migrate:status` lists the two new migrations as Ran.

### AC2 (rule) — Credit sale creates credit_transaction and computes initial balance
- POST `/api/sales` with payment_method=Credit, valid customer_id/customer_name, and amount_paid < total_amount:
  - sale row inserted
  - payments row inserted (as today)
  - credit_transactions row inserted with correct original_total, initial_payment, and balance = original_total - initial_payment (derived)
- Evidence: inspect DB rows after hitting endpoint via curl/tinker or frontend.

### AC3 (rule) — Credit sale rejects walk-in / missing customer
- POST `/api/sales` with payment_method=Credit and no customer (walk-in or empty name) returns 422 with validation error.
- Evidence: HTTP 422 response containing `errors` referencing customer.

### AC4 (rule) — Subsequent credit payment endpoint works
- POST `/api/credit-payments` with customer_id + amount_paid:
  - credit_payments row inserted
  - FIFO allocation to customer's unpaid credit transactions
  - Affected transaction balance recomputed correctly
- Evidence: DB credit_payments row present, and GET credit summary for that customer reflects reduced balance.

### AC5 (rule) — Payment validation guards prevent invalid amounts
- Subsequent payment amount <= 0 → 422.
- Subsequent payment amount > customer outstanding → 422.
- Initial amount_paid on credit sale < 0 → 422.
- Evidence: HTTP 422 with errors payload for each invalid case.

### AC6 (rule) — Payment status computed correctly per transaction
For a credit_transaction:
- balance > 0 AND total_payments == 0 → `Unpaid/Credit`
- balance > 0 AND total_payments > 0 AND total_payments < original_total → `Partially Paid`
- balance == 0 AND all paid via initial only (no credit_payments rows) → `Paid`
- balance == 0 AND any subsequent credit_payments rows exist → `Fully Settled`
- Evidence: endpoint response includes a `payment_status` field matching rules above for a constructed set of fixtures.

### AC7 (rule) — Credit summary per customer endpoint
GET `/api/customers/{id}/credit-summary` returns:
```
{
  customer: {id, name, customer_type},
  summary: {
    total_credit,
    total_payments,
    remaining_balance,
    last_payment_date
  },
  credit_transactions: [
    {id, sale_id, transaction_number, sale_date, original_total, initial_payment,
     total_subsequent_payments, balance, payment_status}
  ],
  payments: [
    {id, credit_transaction_id, amount_paid, payment_date, notes}
  ]
}
```
- Evidence: endpoint returns JSON with exact top-level keys; numbers sum correctly.

### AC8 (rule) — Credit dashboard list endpoint
GET `/api/credit-dashboard` returns list of customers with outstanding credit (or all customers option) including: name, total_credit, total_payments, remaining_balance, last_payment_date, current_status.
- Evidence: array rows include each named field.

### AC9 (rule) — Existing Cash/GCash sales unchanged
POST `/api/sales` Cash flow: change_amount = max(0, paid - total), saved in payments row. No credit_transactions row created.
POST `/api/sales` GCash flow: amount_paid defaults to total. No credit_transactions row created.
- Evidence: credit_transactions table empty after Cash/GCash saves; existing tests or manual flow works as before.

### AC10 (rule) — Frontend Credit tab present and functional
SalesTrackerApp tabs array contains a credit entry. The rendered section:
- Shows summary table for all customers with credit (fields per AC8).
- Clicking customer row expands to show transactions + payments per AC7.
- "Pay Credit" button opens form that POSTs to /api/credit-payments and refreshes data on success.
- In the New Sale tab, when payment_method Credit/Utang is selected: (a) enforces customer selection, (b) displays live-computed Remaining Balance (saleTotal - amountPaid).
- Evidence: manual navigation in browser confirms all UI elements.

### AC11 (rubric) — Coding conventions fidelity
Scale 0-2:
- 2: No Eloquent queries anywhere. Validator usage matches SaleController patterns. Response shape matches existing {message,data}. Vue additions use same CSS classes, tabs array, and option helpers (money/dateTime). No helper functions introduced.
- 1: One or two minor deviations (e.g., stray comment or a helper) but overall consistent.
- 0: Multiple deviations or usage of unsupported patterns (Eloquent, helpers, custom facades).
Pass threshold: 2.

### AC12 (rubric) — UI clarity for cashiers
Scale 0-2:
- 2: Every credit form labels Total Amount / Amount Paid / Remaining Balance. Negative values blocked via min attributes. Status pill uses distinct text. Payment success / validation errors shown in the existing notice strip.
- 1: Labels present but layout crowded or no inline computed balance.
- 0: Labels missing or confusing.
Pass threshold: 2.

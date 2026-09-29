# Tasks: Credit Payment and Customer Balance System

Implementation queue, dependency-ordered vertical slices. Every task maps to one or more Acceptance Criteria in spec.md.

## Task 1: Create database migrations for credit_transactions and credit_payments

- **Status**: pending
- **Priority**: high
- **Maps to AC**: AC1
- **Predecessors**: none
- **Files to create**:
  - `database/migrations/2026_09_29_000001_create_credit_transactions_table.php`
  - `database/migrations/2026_09_29_000002_create_credit_payments_table.php`

### Implementation details

**Migration 1 — credit_transactions:**
```
- id (PK)
- sale_id BIGINT UNSIGNED FK→sales.id CASCADE ON DELETE, indexed
- customer_id BIGINT UNSIGNED FK→customers.id RESTRICT ON DELETE, indexed
- original_total DECIMAL(10,2) DEFAULT 0
- initial_payment DECIMAL(10,2) DEFAULT 0
- created_at, updated_at
```

**Migration 2 — credit_payments:**
```
- id (PK)
- credit_transaction_id BIGINT UNSIGNED NULL FK→credit_transactions.id CASCADE ON DELETE, indexed
- customer_id BIGINT UNSIGNED FK→customers.id RESTRICT ON DELETE, indexed
- amount_paid DECIMAL(10,2) NOT NULL
- notes VARCHAR(255) NULL
- payment_date DATETIME NOT NULL, indexed
- created_at, updated_at
```

### Task-local Test Requirements

- **TR1.1 (rule)**: Running `php artisan migrate` applies both migrations without error.
- **TR1.2 (rule)**: `php artisan migrate:status` lists both new migrations as `Ran`.
- **TR1.3 (rule)**: Both tables exist after migrate with the exact columns listed above (verify via `DESCRIBE` or `Schema::hasColumn` in tinker).

---

## Task 2: Update SaleController — credit_transaction creation + validation

- **Status**: pending
- **Priority**: high
- **Maps to AC**: AC2, AC3, AC5 (partial for initial payment), AC9
- **Predecessors**: Task 1
- **Files to modify**: `app/Http/Controllers/SaleController.php`

### Implementation details

1. In `store()` validation rules:
   - When `payment_method === 'Credit'`: add a rule that `customer_id` OR resolvable `customer_name` must produce a real customer (i.e., after resolveCustomer, we ensure customer_id is not null). Implement as an after-validation check or explicit throw before DB transaction.
   - Reject walk-in for Credit explicitly.

2. In the DB transaction inside `store()`, after sale + payments rows are inserted:
   - IF `payment_method === 'Credit'`:
     - Insert a row into `credit_transactions` with:
       - sale_id = new sale id
       - customer_id = resolved customer id (must be non-null)
       - original_total = $totalAmount
       - initial_payment = $payment['amount_paid']
       - timestamps = $timestamp

3. In `preparePayment()`:
   - For `Credit` method: allow amount_paid < total (already done), but throw if amount_paid < 0 explicitly.

4. In `resolveCustomer()`: no change needed, but caller must assert customer_id is not null for Credit.

### Task-local Test Requirements

- **TR2.1 (rule)**: POST credit sale with valid customer creates credit_transactions row with correct original_total, initial_payment, sale_id, customer_id.
- **TR2.2 (rule)**: POST credit sale without customer (walk-in / empty name) → HTTP 422 customer-required error.
- **TR2.3 (rule)**: POST credit sale with negative amount_paid → HTTP 422.
- **TR2.4 (rule)**: POST Cash / GCash sale: credit_transactions table has no new rows after.

---

## Task 3: Create credit dashboard + customer credit summary endpoints

- **Status**: pending
- **Priority**: high
- **Maps to AC**: AC6, AC7, AC8
- **Predecessors**: Task 2
- **Files to create**: `app/Http/Controllers/CreditController.php`
- **Files to modify**: `routes/api.php`

### Implementation details

New `CreditController` with methods:

1. **dashboard()** — GET `/api/credit-dashboard`
   Returns a list (per customer) of:
   - customer_id, name, customer_type
   - total_credit = SUM(credit_transactions.original_total)
   - total_payments = SUM(credit_transactions.initial_payment) + SUM(credit_payments.amount_paid)
   - remaining_balance = total_credit - total_payments
   - last_payment_date = MAX between (max sale_date of credit transactions' sales, max credit_payments.payment_date)
   - payment_status per customer:
     - remaining_balance == 0 AND any credit_payments → "Fully Settled"
     - remaining_balance == 0 AND no credit_payments → "Paid"
     - total_payments > 0 AND remaining_balance > 0 → "Partially Paid"
     - else (total_payments == 0, remaining_balance > 0) → "Unpaid/Credit"
   Return customers that have credit_transactions rows (optionally all customers but filter).

2. **customerSummary(int $customerId)** — GET `/api/customers/{id}/credit-summary`
   Returns exactly the AC7 shape.
   For each credit_transaction row, also join to get sale's transaction_number + sale_date.
   For each transaction compute:
   - total_subsequent_payments = sum(credit_payments where credit_transaction_id matches)
   - balance = original_total - initial_payment - total_subsequent_payments
   - payment_status per AC6

3. Private helpers on controller:
   - `paymentStatusForTransaction($originalTotal, $initialPayment, $subsequentPayments, $hasSubsequentRows, $balance)`
   - `customerStatusFromAggregates(...)`

Add to `routes/api.php`:
```
Route::get('/credit-dashboard', [CreditController::class, 'dashboard']);
Route::get('/customers/{id}/credit-summary', [CreditController::class, 'customerSummary']);
```

### Task-local Test Requirements

- **TR3.1 (rule)**: GET /api/credit-dashboard returns array rows with keys: customer_id, name, total_credit, total_payments, remaining_balance, last_payment_date, payment_status.
- **TR3.2 (rule)**: GET /api/customers/{id}/credit-summary returns exact AC7 top-level keys (customer, summary, credit_transactions, payments).
- **TR3.3 (rule)**: For a transaction fixture that has been subsequently paid in full, payment_status is "Fully Settled".
- **TR3.4 (rule)**: For an unpaid credit transaction, status is "Unpaid/Credit".
- **TR3.5 (rule)**: Numeric fields remain balance = total_credit - total_payments (verify with 2+ different fixtures).

---

## Task 4: Create credit payment (pay utang) endpoint

- **Status**: pending
- **Priority**: high
- **Maps to AC**: AC4, AC5 (partial for subsequent payments), AC6
- **Predecessors**: Task 3
- **Files to modify**:
  - `app/Http/Controllers/CreditController.php` (add `storePayment`)
  - `routes/api.php`

### Implementation details

New method `storePayment(Request $request)` on CreditController.

Endpoint: **POST /api/credit-payments**

Validation:
```
- customer_id: required, integer, exists:customers,id
- credit_transaction_id: nullable, integer, exists:credit_transactions,id (and must belong to that customer when provided)
- amount_paid: required, numeric, min:0.01 (strictly > 0)
- notes: nullable, string, max:255
```

Business logic (inside DB transaction):
1. Load customer credit summary via same computation as customerSummary.
2. If specific credit_transaction_id given:
   - Compute its current balance.
   - If amount_paid > that balance → 422 "Payment exceeds transaction balance."
   - Insert one credit_payment linking that transaction + customer.
3. Else (customer-level payment, FIFO):
   - Remaining = amount_paid
   - Iterate credit transactions ordered by sale_date ASC (oldest first) with balance > 0:
     - Apply = min(remaining, transaction_current_balance)
     - Insert credit_payment row with credit_transaction_id set.
     - Remaining -= Apply
     - If remaining == 0 break.
   - If remaining > 0 after all (shouldn't happen if validation passed): rollback + 422 "Payment exceeds customer outstanding balance."
4. Commit; return the inserted credit_payment(s) summary.

Response 201:
```
{
  message: 'Credit payment recorded.',
  data: {
    payments: [...],
    total_applied: number,
    new_customer_balance: number
  }
}
```

Route: `Route::post('/credit-payments', [CreditController::class, 'storePayment']);`

### Task-local Test Requirements

- **TR4.1 (rule)**: POST valid payment for specific transaction: credit_payments row created, transaction balance decreases.
- **TR4.2 (rule)**: POST valid customer-level payment with 2 unpaid transactions: FIFO allocation to oldest first.
- **TR4.3 (rule)**: POST amount <= 0 → HTTP 422.
- **TR4.4 (rule)**: POST amount > outstanding → HTTP 422.
- **TR4.5 (rule)**: POST for specific transaction where customer mismatch → 422 (transaction not found or not belong to customer — via exists + after-validation check).

---

## Task 5: Update CustomerController@show to include credit summary

- **Status**: pending
- **Priority**: medium
- **Maps to AC**: AC7 (reuse + integrate into existing flow)
- **Predecessors**: Task 3
- **Files to modify**: `app/Http/Controllers/CustomerController.php`

### Implementation details

In `CustomerController::show($id)`, after loading history, ALSO call CreditController's customerSummary-equivalent computation (replicate inline since no helpers), and merge into the response data as `credit_summary` key.

This is a simple quality-of-life integration so existing customer-detail endpoints surface credit info.

### Task-local Test Requirements

- **TR5.1 (rule)**: GET `/api/customers/{id}` for a customer with credit returns `credit_summary` key in data.

---

## Task 6: Update SalesTrackerApp.vue — Credit tab dashboard + pay credit form

- **Status**: pending
- **Priority**: high
- **Maps to AC**: AC10
- **Predecessors**: Task 3, Task 4
- **Files to modify**: `resources/js/components/SalesTrackerApp.vue`

### Implementation details

1. Add a new tab entry in `tabs` array: `{ id: 'credit', label: 'Credit / Utang', icon: '$' }` at an intuitive position (after customers, before inventory).

2. Add new data properties:
   - `creditDashboard: []`
   - `expandedCreditCustomerId: null`
   - `customerCreditDetail: null` (holds AC7 shape for expanded row)
   - `creditPaymentForm: { customer_id: '', credit_transaction_id: '', amount_paid: 0, notes: '' }`
   - `showCreditPaymentModal: false` (or inline form, whatever matches current UI style — current app uses inline panels, so inline form is preferred)

3. New section:
   ```html
   <section v-if="activeTab === 'credit'" class="page-grid">
     <!-- customer summary table -->
     <section class="panel">
       table with Name, Total Credit, Total Payments, Remaining Balance, Last Payment Date, Status.
       Rows are clickable to expand / fetch detail.
     </section>
     <!-- expanded detail -->
     <section v-if="customerCreditDetail" class="panel">
       <h3>Customer Detail: {{ customerCreditDetail.customer.name }}</h3>
       Summary totals box.
       <h4>Credit Transactions</h4>
       table with TRX#, Date, Original Total, Initial Payment, Total Subsequent, Balance, Status.
       Each row has "Pay This" prefill button.
       <h4>Payment History</h4>
       table with Date, Amount, Linked TRX, Notes.
     </section>
     <!-- payment form -->
     <section class="panel">
       <h3>Record Credit Payment</h3>
       <form @submit.prevent="saveCreditPayment" class="form-grid">
         Customer (select from customers with non-zero balance),
         Optional: specific transaction (select from list of unpaid for selected customer),
         Amount Paid (number, min 0.01),
         Notes (optional),
         <button class="btn btn-primary" type="submit" :disabled="savingCreditPayment">Record Payment</button>
       </form>
     </section>
   </section>
   ```

4. New methods:
   - `async loadCreditDashboard()` — GET `/api/credit-dashboard` → this.creditDashboard
   - `async openCustomerCreditDetail(customerId)` — GET `/api/customers/{id}/credit-summary` → this.customerCreditDetail, set expanded
   - `async saveCreditPayment()` — validate form, POST `/api/credit-payments`, then refresh both dashboard + detail if expanded, reset form, show notice.
   - `prefillTransactionForPayment(transactionId)` — sets creditPaymentForm fields.
   - In `refreshAll()`: also await `loadCreditDashboard()`.

5. Payment statuses get CSS pill classes (ok / warn / low pattern like stock):
   - Paid / Fully Settled → green-ish (stock-pill `ok`)
   - Partially Paid → amber
   - Unpaid/Credit → red

### Task-local Test Requirements

- **TR6.1 (rule)**: Tabs list contains 'credit' tab, clicking shows credit dashboard section.
- **TR6.2 (rule)**: Dashboard table renders rows with 6 required columns for each returned customer.
- **TR6.3 (rule)**: Clicking a customer row triggers HTTP call to /api/customers/{id}/credit-summary and populates detail section.
- **TR6.4 (rule)**: Record Credit Payment form submit → POST to correct endpoint → triggers notice + data refresh.
- **TR6.5 (rubric)**: UI clarity score ≥ 2 by AC12 standard (labels, balance display, validation feedback present).

---

## Task 7: Update SalesTrackerApp.vue — New Sale credit enhancements

- **Status**: pending
- **Priority**: high
- **Maps to AC**: AC10 (sale section), AC3 (frontend guard)
- **Predecessors**: Task 6
- **Files to modify**: `resources/js/components/SalesTrackerApp.vue`

### Implementation details

1. In New Sale form, when payment_method === 'Credit':
   - Add a new summary row in the payment grid area (or after it) showing:
     ```
     Total Amount:      ₱XX.XX
     Amount Paid:       ₱YY.YY
     Remaining Balance: ₱ZZ.ZZ   (computed live = saleTotal - amount_paid)
     ```
   - Force customer_type to `Regular Customer` (can't be Walk-in) and auto-flag validation: if customer_name empty + credit selected, disable submit button.

2. Update `syncPaymentAmount()`:
   - For Credit, do NOT force amount_paid to 0. The user may choose partial payment at sale time.
   - Keep behavior: if user changes to GCash, auto-fill amount_paid = total.

3. Add computed `creditSaleBalance` for live display.

4. In `saveSale()`:
   - Before POST, if payment_method === 'Credit' and (saleForm.customer_id is empty and saleForm.customer_name is empty), show a notice error (this is client-side guard for UX; server still validates).

5. Optionally: in the Payment Method dropdown, the option text can say `Credit/Utang` while the value stays `Credit` for backend compat. Update the `<option>` text label.

### Task-local Test Requirements

- **TR7.1 (rule)**: With Credit payment method selected + valid items + total 50 + paid 40 → inline shows Remaining Balance ₱10.00.
- **TR7.2 (rule)**: Credit selected with empty customer → Save Sale button disabled AND form submit blocked with error notice.
- **TR7.3 (rule)**: Switching to GCash still auto-fills amount_paid = total. Switching to Cash → manual allowed.

---

## Task 8: Run migrations and verify all ACs end-to-end

- **Status**: pending
- **Priority**: high
- **Maps to AC**: All ACs via evidence collection
- **Predecessors**: Tasks 1–7
- **Scope**: No code changes unless regressions found. This is the verification & evidence-gathering pass.

### Implementation details

1. Backup DB or ensure test environment.
2. Run `php artisan migrate`.
3. Manual or scripted verification of each AC/TR:
   - Cash sale, GCash sale, Credit sale.
   - Credit with partial initial.
   - Credit payment endpoint: specific transaction, FIFO multi-transaction.
   - Invalid payment cases (0, negative, over balance).
   - Dashboard / customerSummary endpoints.
   - Frontend: navigate Credit tab, expand row, submit payment form, submit credit sale with remaining balance display.
4. If any failure: revert task status of the broken slice to in_progress, fix, re-run task TRs.

### Completion Evidence

For each task, record:
- Migrate output (TR1).
- Sample HTTP responses / curl output / tinker output (TR2–TR5).
- Frontend screenshots or description of verified UI elements (TR6–TR7).

---

## Cancelled Items

None.

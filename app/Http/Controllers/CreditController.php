<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use Throwable;

class CreditController extends Controller
{
    public function dashboard(): JsonResponse
    {
        $initialAgg = DB::table('credit_transactions')
            ->join('customers', 'customers.id', '=', 'credit_transactions.customer_id')
            ->select(
                'credit_transactions.customer_id',
                'customers.name',
                'customers.customer_type',
                DB::raw('COALESCE(SUM(credit_transactions.original_total), 0) as total_credit'),
                DB::raw('COALESCE(SUM(credit_transactions.initial_payment), 0) as total_initial')
            )
            ->groupBy('credit_transactions.customer_id', 'customers.name', 'customers.customer_type');

        $subsequentAgg = DB::table('credit_payments')
            ->select(
                'customer_id',
                DB::raw('COALESCE(SUM(amount_paid), 0) as total_subsequent'),
                DB::raw('MAX(payment_date) as last_payment_date')
            )
            ->groupBy('customer_id');

        $saleDateAgg = DB::table('credit_transactions')
            ->join('sales', 'sales.id', '=', 'credit_transactions.sale_id')
            ->select(
                'credit_transactions.customer_id',
                DB::raw('MAX(sales.sale_date) as last_sale_date')
            )
            ->groupBy('credit_transactions.customer_id');

        $rows = DB::table('customers')
            ->joinSub($initialAgg, 'initial_agg', function ($join): void {
                $join->on('initial_agg.customer_id', '=', 'customers.id');
            })
            ->leftJoinSub($subsequentAgg, 'subsequent_agg', function ($join): void {
                $join->on('subsequent_agg.customer_id', '=', 'customers.id');
            })
            ->leftJoinSub($saleDateAgg, 'sale_date_agg', function ($join): void {
                $join->on('sale_date_agg.customer_id', '=', 'customers.id');
            })
            ->select(
                'customers.id as customer_id',
                'customers.name',
                'customers.customer_type',
                DB::raw('COALESCE(initial_agg.total_credit, 0) as total_credit'),
                DB::raw('COALESCE(initial_agg.total_initial, 0) as total_initial'),
                DB::raw('COALESCE(subsequent_agg.total_subsequent, 0) as total_subsequent'),
                DB::raw('COALESCE(subsequent_agg.last_payment_date, sale_date_agg.last_sale_date) as last_payment_date'),
                DB::raw('EXISTS(SELECT 1 FROM credit_payments WHERE credit_payments.customer_id = customers.id) as has_subsequent')
            )
            ->orderBy('customers.name')
            ->get();

        $results = [];
        foreach ($rows as $row) {
            $totalCredit = (float) $row->total_credit;
            $totalInitial = (float) $row->total_initial;
            $totalSubsequent = (float) $row->total_subsequent;
            $totalPayments = $totalInitial + $totalSubsequent;
            $remainingBalance = round($totalCredit - $totalPayments, 2);
            $hasSubsequent = (bool) $row->has_subsequent;

            $status = $this->customerStatus($totalPayments, $remainingBalance, $hasSubsequent);

            $results[] = (object) [
                'customer_id' => (int) $row->customer_id,
                'name' => $row->name,
                'customer_type' => $row->customer_type,
                'total_credit' => $totalCredit,
                'total_payments' => round($totalPayments, 2),
                'remaining_balance' => $remainingBalance,
                'last_payment_date' => $row->last_payment_date,
                'payment_status' => $status,
            ];
        }

        return new JsonResponse(['data' => $results]);
    }

    public function customerSummary(int $customerId): JsonResponse
    {
        $customer = DB::table('customers')->where('id', $customerId)->first();
        if ($customer === null) {
            return new JsonResponse(['message' => 'Customer not found.'], 404);
        }

        $txnSubsequent = DB::table('credit_payments')
            ->select(
                'credit_transaction_id',
                DB::raw('COALESCE(SUM(amount_paid), 0) as txn_subsequent')
            )
            ->whereNotNull('credit_transaction_id')
            ->groupBy('credit_transaction_id');

        $transactions = DB::table('credit_transactions')
            ->join('sales', 'sales.id', '=', 'credit_transactions.sale_id')
            ->leftJoinSub($txnSubsequent, 'txn_sub', function ($join): void {
                $join->on('txn_sub.credit_transaction_id', '=', 'credit_transactions.id');
            })
            ->where('credit_transactions.customer_id', $customerId)
            ->select(
                'credit_transactions.id',
                'credit_transactions.sale_id',
                'sales.transaction_number',
                'sales.sale_date',
                'credit_transactions.original_total',
                'credit_transactions.initial_payment',
                DB::raw('COALESCE(txn_sub.txn_subsequent, 0) as total_subsequent_payments'),
                DB::raw('EXISTS(SELECT 1 FROM credit_payments cp WHERE cp.credit_transaction_id = credit_transactions.id) as has_txn_subsequent')
            )
            ->orderBy('sales.sale_date', 'asc')
            ->orderBy('credit_transactions.id', 'asc')
            ->get();

        $creditTransactions = [];
        $totalCredit = 0;
        $totalInitial = 0;
        $totalSubsequentAll = 0;

        foreach ($transactions as $txn) {
            $orig = (float) $txn->original_total;
            $init = (float) $txn->initial_payment;
            $sub = (float) $txn->total_subsequent_payments;
            $balance = round($orig - $init - $sub, 2);
            $hasSubsequent = (bool) $txn->has_txn_subsequent;
            $status = $this->txnStatus($orig, $init, $sub, $hasSubsequent, $balance);

            $creditTransactions[] = (object) [
                'id' => (int) $txn->id,
                'sale_id' => (int) $txn->sale_id,
                'transaction_number' => $txn->transaction_number,
                'sale_date' => $txn->sale_date,
                'original_total' => $orig,
                'initial_payment' => $init,
                'total_subsequent_payments' => $sub,
                'balance' => $balance,
                'payment_status' => $status,
            ];

            $totalCredit += $orig;
            $totalInitial += $init;
            $totalSubsequentAll += $sub;
        }

        $payments = DB::table('credit_payments')
            ->leftJoin('credit_transactions', 'credit_transactions.id', '=', 'credit_payments.credit_transaction_id')
            ->leftJoin('sales', 'sales.id', '=', 'credit_transactions.sale_id')
            ->where('credit_payments.customer_id', $customerId)
            ->select(
                'credit_payments.id',
                'credit_payments.credit_transaction_id',
                'sales.transaction_number',
                'credit_payments.amount_paid',
                'credit_payments.payment_date',
                'credit_payments.notes'
            )
            ->orderBy('credit_payments.payment_date', 'desc')
            ->orderBy('credit_payments.id', 'desc')
            ->get();

        $paymentsMapped = [];
        foreach ($payments as $p) {
            $paymentsMapped[] = (object) [
                'id' => (int) $p->id,
                'credit_transaction_id' => $p->credit_transaction_id !== null ? (int) $p->credit_transaction_id : null,
                'transaction_number' => $p->transaction_number,
                'amount_paid' => (float) $p->amount_paid,
                'payment_date' => $p->payment_date,
                'notes' => $p->notes,
            ];
        }

        $totalPayments = round($totalInitial + $totalSubsequentAll, 2);
        $remainingBalance = round($totalCredit - $totalPayments, 2);

        $lastPayment = $paymentsMapped[0]->payment_date ?? null;
        $lastSale = $transactions->last()->sale_date ?? null;
        $lastPaymentDate = $lastPayment ?? $lastSale;

        return new JsonResponse([
            'data' => [
                'customer' => [
                    'id' => (int) $customer->id,
                    'name' => $customer->name,
                    'customer_type' => $customer->customer_type,
                ],
                'summary' => [
                    'total_credit' => round($totalCredit, 2),
                    'total_payments' => $totalPayments,
                    'remaining_balance' => $remainingBalance,
                    'last_payment_date' => $lastPaymentDate,
                ],
                'credit_transactions' => $creditTransactions,
                'payments' => $paymentsMapped,
            ],
        ]);
    }

    public function storePayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_id' => ['required', 'integer', 'exists:customers,id'],
            'credit_transaction_id' => ['nullable', 'integer', 'exists:credit_transactions,id'],
            'amount_paid' => ['required', 'numeric', 'min:0.01'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        if ($validator->fails()) {
            return new JsonResponse(['message' => 'Validation failed.', 'errors' => $validator->errors()], 422);
        }

        try {
            $result = DB::transaction(function () use ($request): array {
                $timestamp = Carbon::now();
                $paymentDate = $timestamp->toDateTimeString();
                $customerId = (int) $request->input('customer_id');
                $specificTxnId = $request->input('credit_transaction_id');
                $amountPaid = (float) $request->input('amount_paid');
                $notes = $request->input('notes');

                $txnSubsequent = DB::table('credit_payments')
                    ->select(
                        'credit_transaction_id',
                        DB::raw('COALESCE(SUM(amount_paid), 0) as txn_subsequent')
                    )
                    ->whereNotNull('credit_transaction_id')
                    ->where('customer_id', $customerId)
                    ->groupBy('credit_transaction_id');

                $transactions = DB::table('credit_transactions')
                    ->join('sales', 'sales.id', '=', 'credit_transactions.sale_id')
                    ->leftJoinSub($txnSubsequent, 'txn_sub', function ($join): void {
                        $join->on('txn_sub.credit_transaction_id', '=', 'credit_transactions.id');
                    })
                    ->where('credit_transactions.customer_id', $customerId)
                    ->select(
                        'credit_transactions.id',
                        'credit_transactions.original_total',
                        'credit_transactions.initial_payment',
                        DB::raw('COALESCE(txn_sub.txn_subsequent, 0) as total_subsequent')
                    )
                    ->orderBy('sales.sale_date', 'asc')
                    ->orderBy('credit_transactions.id', 'asc')
                    ->lockForUpdate()
                    ->get();

                $txnBalances = [];
                $customerOutstanding = 0;
                foreach ($transactions as $txn) {
                    $bal = round(
                        (float) $txn->original_total
                        - (float) $txn->initial_payment
                        - (float) $txn->total_subsequent,
                        2
                    );
                    $txnBalances[(int) $txn->id] = $bal;
                    if ($bal > 0) {
                        $customerOutstanding += $bal;
                    }
                }

                if ($customerOutstanding <= 0) {
                    throw new InvalidArgumentException('Customer has no outstanding balance.');
                }

                $inserted = [];

                if ($specificTxnId !== null && $specificTxnId !== '') {
                    $specificTxnIdInt = (int) $specificTxnId;

                    $txnMatch = $transactions->firstWhere('id', $specificTxnIdInt);
                    if ($txnMatch === null) {
                        throw new InvalidArgumentException('Selected credit transaction does not belong to this customer.');
                    }

                    $txnBalance = $txnBalances[$specificTxnIdInt] ?? 0;
                    if ($txnBalance <= 0) {
                        throw new InvalidArgumentException('This credit transaction has no remaining balance.');
                    }
                    if ($amountPaid > $txnBalance + 0.0001) {
                        throw new InvalidArgumentException('Payment exceeds the remaining balance of this credit transaction.');
                    }

                    $id = DB::table('credit_payments')->insertGetId([
                        'credit_transaction_id' => $specificTxnIdInt,
                        'customer_id' => $customerId,
                        'amount_paid' => $amountPaid,
                        'notes' => $notes,
                        'payment_date' => $paymentDate,
                        'created_at' => $timestamp,
                        'updated_at' => $timestamp,
                    ]);

                    $inserted[] = (object) [
                        'id' => $id,
                        'credit_transaction_id' => $specificTxnIdInt,
                        'amount_paid' => $amountPaid,
                    ];
                } else {
                    if ($amountPaid > $customerOutstanding + 0.0001) {
                        throw new InvalidArgumentException('Payment exceeds the customer\'s outstanding balance.');
                    }

                    $remaining = $amountPaid;
                    foreach ($transactions as $txn) {
                        if ($remaining <= 0.0001) {
                            break;
                        }
                        $txnId = (int) $txn->id;
                        $txnBalance = $txnBalances[$txnId] ?? 0;
                        if ($txnBalance <= 0) {
                            continue;
                        }
                        $apply = min($remaining, $txnBalance);
                        $apply = round($apply, 2);
                        if ($apply <= 0) {
                            continue;
                        }

                        $id = DB::table('credit_payments')->insertGetId([
                            'credit_transaction_id' => $txnId,
                            'customer_id' => $customerId,
                            'amount_paid' => $apply,
                            'notes' => $remaining - $apply < 0.0001 ? $notes : null,
                            'payment_date' => $paymentDate,
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ]);

                        $inserted[] = (object) [
                            'id' => $id,
                            'credit_transaction_id' => $txnId,
                            'amount_paid' => $apply,
                        ];

                        $remaining = round($remaining - $apply, 2);
                    }

                    if ($remaining > 0.0001) {
                        throw new InvalidArgumentException('Unable to allocate the full payment amount to credit transactions.');
                    }
                }

                $totalApplied = round(array_sum(array_column($inserted, 'amount_paid')), 2);
                $newCustomerBalance = max(0, round($customerOutstanding - $totalApplied, 2));

                return [
                    'payments' => $inserted,
                    'total_applied' => $totalApplied,
                    'new_customer_balance' => $newCustomerBalance,
                ];
            });
        } catch (InvalidArgumentException $exception) {
            return new JsonResponse(['message' => $exception->getMessage()], 422);
        } catch (Throwable $exception) {
            return new JsonResponse(['message' => 'Unable to record credit payment.', 'detail' => $exception->getMessage()], 500);
        }

        return new JsonResponse([
            'message' => 'Credit payment recorded.',
            'data' => $result,
        ], 201);
    }

    private function txnStatus(float $originalTotal, float $initialPayment, float $subsequent, bool $hasSubsequent, float $balance): string
    {
        $totalPaid = $initialPayment + $subsequent;

        if ($balance <= 0.0001) {
            if ($hasSubsequent) {
                return 'Fully Settled';
            }
            return 'Paid';
        }

        if ($totalPaid > 0) {
            return 'Partially Paid';
        }

        return 'Unpaid/Credit';
    }

    private function customerStatus(float $totalPayments, float $remainingBalance, bool $hasSubsequent): string
    {
        if ($remainingBalance <= 0.0001) {
            if ($hasSubsequent) {
                return 'Fully Settled';
            }
            return 'Paid';
        }

        if ($totalPayments > 0) {
            return 'Partially Paid';
        }

        return 'Unpaid/Credit';
    }
}

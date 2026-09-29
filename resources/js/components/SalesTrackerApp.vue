<template>
    <div :class="['app-shell', { 'sidebar-collapsed': sidebarCollapsed, 'mobile-sidebar-open': mobileSidebarOpen }]">
        <button
            class="sidebar-backdrop"
            type="button"
            aria-label="Close sidebar"
            @click="closeMobileSidebar"
        ></button>

        <aside id="app-sidebar" class="sidebar">
            <div class="sidebar-header">
                <div class="brand-block">
                    <div class="brand-mark">RFC</div>
                    <div class="brand-copy">
                        <h1>Store</h1>
                        <p>Sales and stock</p>
                    </div>
                </div>

                <button
                    class="sidebar-toggle desktop-sidebar-toggle"
                    type="button"
                    :aria-expanded="!sidebarCollapsed"
                    :aria-label="sidebarToggleLabel"
                    :title="sidebarToggleLabel"
                    @click="toggleSidebar"
                >
                    <span aria-hidden="true">{{ sidebarCollapsed ? '>' : '<' }}</span>
                </button>

                <button
                    class="sidebar-toggle mobile-sidebar-close"
                    type="button"
                    aria-label="Close sidebar"
                    title="Close sidebar"
                    @click="closeMobileSidebar"
                >
                    <span aria-hidden="true">X</span>
                </button>
            </div>

            <nav class="tab-list" aria-label="Main">
                <button
                    v-for="tab in tabs"
                    :key="tab.id"
                    type="button"
                    :class="['tab-button', { active: activeTab === tab.id }]"
                    :title="sidebarCollapsed ? tab.label : null"
                    @click="selectTab(tab.id)"
                >
                    <span class="tab-icon" aria-hidden="true">{{ tab.icon }}</span>
                    <span class="tab-label">{{ tab.label }}</span>
                </button>
            </nav>
        </aside>

        <main class="workspace">
            <header class="topbar">
                <div class="topbar-title">
                    <button
                        class="mobile-menu-button"
                        type="button"
                        aria-label="Open sidebar"
                        title="Open sidebar"
                        aria-controls="app-sidebar"
                        :aria-expanded="mobileSidebarOpen"
                        @click="openMobileSidebar"
                    >
                        <span aria-hidden="true"></span>
                        <span aria-hidden="true"></span>
                        <span aria-hidden="true"></span>
                    </button>
                    <div class="topbar-copy">
                        <p class="eyebrow">RFC Store System</p>
                        <h2>{{ currentTitle }}</h2>
                    </div>
                </div>
                <div class="topbar-actions">
                    <button class="btn btn-primary" type="button" @click="refreshAll" :disabled="loading">
                        Refresh
                    </button>
                    <span v-if="user" class="user-pill">{{ user.name }}</span>
                    <button v-if="user" class="btn btn-muted" type="button" @click="$emit('logout')">
                        Log Out
                    </button>
                </div>
            </header>

            <div v-if="notice.text" :class="['notice', notice.type]">
                {{ notice.text }}
            </div>

            <section v-if="activeTab === 'dashboard'" class="page-grid">
                <div class="summary-grid">
                    <article class="metric-card strong">
                        <span>Today's Sales</span>
                        <strong>{{ money(dashboard.today_sales) }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>Customers Today</span>
                        <strong>{{ dashboard.customers_today }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>Transactions Today</span>
                        <strong>{{ dashboard.transactions_today }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>Items Sold Today</span>
                        <strong>{{ dashboard.items_sold_today }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>Cash Sales</span>
                        <strong>{{ money(dashboard.payment_totals.Cash) }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>GCash Sales</span>
                        <strong>{{ money(dashboard.payment_totals.GCash) }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>Credit Sales</span>
                        <strong>{{ money(dashboard.payment_totals.Credit) }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>Average Sale</span>
                        <strong>{{ money(dashboard.average_sale) }}</strong>
                    </article>
                </div>

                <div class="two-column">
                    <section class="panel">
                        <div class="panel-heading">
                            <h3>Current Stock</h3>
                        </div>
                        <div class="stock-list">
                            <div v-for="product in dashboard.current_stock" :key="product.id" class="stock-row">
                                <div>
                                    <strong>{{ product.name }}</strong>
                                    <span>{{ money(product.selling_price) }}</span>
                                </div>
                                <span :class="['stock-pill', stockLevel(product)]">
                                    {{ product.current_stock }}
                                </span>
                            </div>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="panel-heading">
                            <h3>Customer Trends</h3>
                        </div>
                        <div class="compact-stats">
                            <div>
                                <span>This Week</span>
                                <strong>{{ dashboard.customers_this_week }}</strong>
                            </div>
                            <div>
                                <span>This Month</span>
                                <strong>{{ dashboard.customers_this_month }}</strong>
                            </div>
                            <div>
                                <span>Total Customers</span>
                                <strong>{{ dashboard.total_customers }}</strong>
                            </div>
                        </div>
                        <div v-if="dashboard.low_stock.length" class="low-stock-box">
                            <strong>Low Stock</strong>
                            <span v-for="product in dashboard.low_stock" :key="product.id">
                                {{ product.name }}: {{ product.current_stock }}
                            </span>
                        </div>
                    </section>
                </div>
            </section>

            <section v-if="activeTab === 'sales'" class="page-grid">
                <form class="panel sale-panel" @submit.prevent="saveSale">
                    <div class="panel-heading">
                        <h3>New Sale</h3>
                        <strong>{{ money(saleTotal) }}</strong>
                    </div>

                    <div class="form-grid">
                        <label>
                            Customer Type
                            <select v-model="saleForm.customer_type" @change="syncCustomerType">
                                <option>Walk-in Customer</option>
                                <option>Regular Customer</option>
                            </select>
                        </label>
                        <label>
                            Customer Name
                            <input
                                v-model="saleForm.customer_name"
                                list="customer-options"
                                type="text"
                                placeholder="Optional for walk-in"
                                @input="syncCustomerFromName"
                            >
                        </label>
                        <datalist id="customer-options">
                            <option v-for="customer in customers" :key="customer.id" :value="customer.name"></option>
                        </datalist>
                    </div>

                    <div class="line-items">
                        <div class="line-item heading">
                            <span>Product</span>
                            <span>Qty</span>
                            <span>Price</span>
                            <span>Total</span>
                            <span></span>
                        </div>
                        <div v-for="(item, index) in saleForm.items" :key="index" class="line-item">
                            <select v-model.number="item.product_id">
                                <option disabled value="">Select product</option>
                                <option
                                    v-for="product in activeProducts"
                                    :key="product.id"
                                    :value="product.id"
                                    :disabled="product.current_stock <= 0"
                                >
                                    {{ product.name }} - stock {{ product.current_stock }}
                                </option>
                            </select>
                            <input v-model.number="item.quantity" type="number" min="1">
                            <span>{{ money(itemPrice(item)) }}</span>
                            <strong>{{ money(itemTotal(item)) }}</strong>
                            <button class="icon-btn danger" type="button" @click="removeSaleItem(index)" :disabled="saleForm.items.length === 1">
                                X
                            </button>
                        </div>
                    </div>

                    <button class="btn btn-muted" type="button" @click="addSaleItem">Add Item</button>

                    <div class="payment-grid">
                        <label>
                            Payment Method
                            <select v-model="saleForm.payment_method" @change="syncPaymentAmount">
                                <option>Cash</option>
                                <option>GCash</option>
                                <option value="Credit">Credit / Utang</option>
                            </select>
                        </label>
                        <label>
                            Amount Paid
                            <input v-model.number="saleForm.amount_paid" type="number" min="0" step="0.01">
                        </label>
                        <div class="change-box">
                            <span v-if="saleForm.payment_method === 'Cash'">Change</span>
                            <span v-else-if="saleForm.payment_method === 'Credit'">Remaining Balance</span>
                            <span v-else>Amount Paid</span>
                            <strong>{{ saleForm.payment_method === 'Credit' ? money(creditSaleBalance) : money(changeAmount) }}</strong>
                        </div>
                    </div>

                    <div v-if="saleForm.payment_method === 'Credit'" class="credit-summary">
                        <div>
                            <span>Total Amount</span>
                            <strong>{{ money(saleTotal) }}</strong>
                        </div>
                        <div>
                            <span>Amount Paid</span>
                            <strong>{{ money(Number(saleForm.amount_paid || 0)) }}</strong>
                        </div>
                        <div class="balance-highlight">
                            <span>Remaining Balance (Utang)</span>
                            <strong>{{ money(creditSaleBalance) }}</strong>
                        </div>
                        <p v-if="creditSaleNeedsCustomer" class="warn-text">
                            Credit/Utang payment requires a customer name. Please select or enter a customer.
                        </p>
                    </div>

                    <div class="actions">
                        <button
                            class="btn btn-primary"
                            type="submit"
                            :disabled="savingSale || creditSaleNeedsCustomer"
                        >
                            Save Sale
                        </button>
                    </div>
                </form>

                <section class="panel">
                    <div class="panel-heading">
                        <h3>Recent Sales</h3>
                    </div>
                    <div class="table-wrap compact">
                        <table>
                            <thead>
                                <tr>
                                    <th>Transaction</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in salesHistory.slice(0, 8)" :key="row.id + '-' + row.product_id">
                                    <td>{{ row.transaction_number }}</td>
                                    <td>{{ customerLabel(row) }}</td>
                                    <td>{{ money(row.sale_total) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>

            <section v-if="activeTab === 'products'" class="page-grid">
                <form class="panel" @submit.prevent="saveProduct">
                    <div class="panel-heading">
                        <h3>Add Product</h3>
                    </div>
                    <div class="form-grid product-form">
                        <label>
                            Product Name
                            <input v-model="productForm.name" required>
                        </label>
                        <label>
                            Selling Price
                            <input v-model.number="productForm.selling_price" type="number" min="0" step="0.01" required>
                        </label>
                        <label>
                            Current Stock
                            <input v-model.number="productForm.current_stock" type="number" min="0" required>
                        </label>
                        <label>
                            Minimum Stock
                            <input v-model.number="productForm.minimum_stock" type="number" min="0" required>
                        </label>
                        <label>
                            Status
                            <select v-model="productForm.status">
                                <option>Active</option>
                                <option>Inactive</option>
                            </select>
                        </label>
                    </div>
                    <button class="btn btn-primary" type="submit">Save Product</button>
                </form>

                <section class="panel">
                    <div class="panel-heading">
                        <h3>Products</h3>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Minimum</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="product in products" :key="product.id">
                                    <template v-if="editingProductId === product.id">
                                        <td><input v-model="productEditForm.name"></td>
                                        <td><input v-model.number="productEditForm.selling_price" type="number" min="0" step="0.01"></td>
                                        <td><input v-model.number="productEditForm.current_stock" type="number" min="0"></td>
                                        <td><input v-model.number="productEditForm.minimum_stock" type="number" min="0"></td>
                                        <td>
                                            <select v-model="productEditForm.status">
                                                <option>Active</option>
                                                <option>Inactive</option>
                                            </select>
                                        </td>
                                        <td class="row-actions">
                                            <button class="btn btn-small btn-primary" type="button" @click="updateProduct(product.id)">Save</button>
                                            <button class="btn btn-small btn-muted" type="button" @click="cancelProductEdit">Cancel</button>
                                        </td>
                                    </template>
                                    <template v-else>
                                        <td>{{ product.name }}</td>
                                        <td>{{ money(product.selling_price) }}</td>
                                        <td><span :class="['stock-pill', stockLevel(product)]">{{ product.current_stock }}</span></td>
                                        <td>{{ product.minimum_stock }}</td>
                                        <td>{{ product.status }}</td>
                                        <td>
                                            <button class="btn btn-small btn-muted" type="button" @click="editProduct(product)">Edit</button>
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>

            <section v-if="activeTab === 'customers'" class="page-grid">
                <form class="panel" @submit.prevent="saveCustomer">
                    <div class="panel-heading">
                        <h3>Add Customer</h3>
                    </div>
                    <div class="form-grid">
                        <label>
                            Customer Name
                            <input v-model="customerForm.name" required>
                        </label>
                        <label>
                            Customer Type
                            <select v-model="customerForm.customer_type">
                                <option>Regular Customer</option>
                                <option>Walk-in Customer</option>
                            </select>
                        </label>
                    </div>
                    <button class="btn btn-primary" type="submit">Save Customer</button>
                </form>

                <section class="panel">
                    <div class="panel-heading">
                        <h3>Customers</h3>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Transactions</th>
                                    <th>Items</th>
                                    <th>Total</th>
                                    <th>Last Purchase</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="customer in customers" :key="customer.id">
                                    <td>{{ customer.name }}</td>
                                    <td>{{ customer.customer_type }}</td>
                                    <td>{{ customer.transactions }}</td>
                                    <td>{{ customer.items_purchased }}</td>
                                    <td>{{ money(customer.total_amount) }}</td>
                                    <td>{{ dateTime(customer.last_purchase_date) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>

            <section v-if="activeTab === 'inventory'" class="page-grid">
                <form class="panel" @submit.prevent="saveStockAdjustment">
                    <div class="panel-heading">
                        <h3>Stock Adjustment</h3>
                    </div>
                    <div class="form-grid">
                        <label>
                            Product
                            <select v-model.number="stockForm.product_id" required>
                                <option disabled value="">Select product</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">
                                    {{ product.name }} - {{ product.current_stock }}
                                </option>
                            </select>
                        </label>
                        <label>
                            Type
                            <select v-model="stockForm.adjustment_type">
                                <option>Add</option>
                                <option>Deduct</option>
                                <option>Damaged</option>
                            </select>
                        </label>
                        <label>
                            Quantity
                            <input v-model.number="stockForm.quantity" type="number" min="1" required>
                        </label>
                        <label>
                            Reason
                            <input v-model="stockForm.reason" placeholder="Optional">
                        </label>
                    </div>
                    <button class="btn btn-primary" type="submit">Save Adjustment</button>
                </form>

                <section class="panel">
                    <div class="panel-heading">
                        <h3>Inventory Records</h3>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Product</th>
                                    <th>Beginning</th>
                                    <th>Added</th>
                                    <th>Sold</th>
                                    <th>Damaged</th>
                                    <th>Adjustments</th>
                                    <th>Ending</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="record in inventoryReport" :key="record.id">
                                    <td>{{ shortDate(record.inventory_date) }}</td>
                                    <td>{{ record.product_name }}</td>
                                    <td>{{ record.beginning_stock }}</td>
                                    <td>{{ record.stock_added }}</td>
                                    <td>{{ record.quantity_sold }}</td>
                                    <td>{{ record.damaged }}</td>
                                    <td>{{ record.adjustments }}</td>
                                    <td>{{ record.ending_stock }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>

            <section v-if="activeTab === 'reports'" class="page-grid">
                <div class="summary-grid">
                    <article class="metric-card">
                        <span>Customers Today</span>
                        <strong>{{ trends.customers_today }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>Customers Yesterday</span>
                        <strong>{{ trends.customers_yesterday }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>Average Customers</span>
                        <strong>{{ trends.average_customers_per_day }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>Average Spending</span>
                        <strong>{{ money(trends.average_spent_per_customer) }}</strong>
                    </article>
                </div>

                <div class="two-column">
                    <section class="panel">
                        <div class="panel-heading">
                            <h3>Customer Sales</h3>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Customer</th>
                                        <th>Transactions</th>
                                        <th>Items</th>
                                        <th>Total</th>
                                        <th>Last Purchase</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in customerReport" :key="row.customer_name">
                                        <td>{{ row.customer_name }}</td>
                                        <td>{{ row.transactions }}</td>
                                        <td>{{ row.items_purchased }}</td>
                                        <td>{{ money(row.total_amount) }}</td>
                                        <td>{{ dateTime(row.last_purchase_date) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="panel-heading">
                            <h3>Product Sales</h3>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Customers</th>
                                        <th>Qty Sold</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-for="row in productReport" :key="row.id">
                                        <td>{{ row.name }}</td>
                                        <td>{{ row.customers_who_bought }}</td>
                                        <td>{{ row.quantity_sold }}</td>
                                        <td>{{ money(row.total_sales) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </section>

            <section v-if="activeTab === 'history'" class="page-grid">
                <form class="panel filters" @submit.prevent="loadSalesHistory">
                    <div class="panel-heading">
                        <h3>Sales History</h3>
                    </div>
                    <div class="form-grid history-filters">
                        <label>
                            Period
                            <select v-model="historyFilters.period">
                                <option value="">All</option>
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                            </select>
                        </label>
                        <label>
                            From
                            <input v-model="historyFilters.date_from" type="date">
                        </label>
                        <label>
                            To
                            <input v-model="historyFilters.date_to" type="date">
                        </label>
                        <label>
                            Customer
                            <select v-model="historyFilters.customer_id">
                                <option value="">All</option>
                                <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                    {{ customer.name }}
                                </option>
                            </select>
                        </label>
                        <label>
                            Product
                            <select v-model="historyFilters.product_id">
                                <option value="">All</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">
                                    {{ product.name }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <button class="btn btn-primary" type="submit">Apply Filters</button>
                </form>

                <section class="panel">
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Transaction</th>
                                    <th>Date/Time</th>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Total</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="row in salesHistory" :key="row.id + '-' + row.product_id + '-' + row.product_name">
                                    <td>{{ row.transaction_number }}</td>
                                    <td>{{ dateTime(row.sale_date) }}</td>
                                    <td>{{ customerLabel(row) }}</td>
                                    <td>{{ row.product_name }}</td>
                                    <td>{{ row.quantity }}</td>
                                    <td>{{ money(row.total) }}</td>
                                    <td>{{ row.payment_method }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>

            <section v-if="activeTab === 'credit'" class="page-grid">
                <section class="panel">
                    <div class="panel-heading">
                        <h3>Customer Credit Summary</h3>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Total Credit</th>
                                    <th>Total Payments</th>
                                    <th>Remaining Balance</th>
                                    <th>Last Payment Date</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="creditDashboard.length === 0">
                                    <td colspan="7" class="empty-row">No credit transactions recorded yet.</td>
                                </tr>
                                <tr
                                    v-for="row in creditDashboard"
                                    :key="row.customer_id"
                                    :class="['clickable-row', { selected: customerCreditDetail && customerCreditDetail.customer.id === row.customer_id }]"
                                    @click="openCustomerCreditDetail(row.customer_id)"
                                >
                                    <td><strong>{{ row.name }}</strong></td>
                                    <td>{{ money(row.total_credit) }}</td>
                                    <td>{{ money(row.total_payments) }}</td>
                                    <td>
                                        <span :class="row.remaining_balance > 0 ? 'text-danger' : 'text-ok'">
                                            <strong>{{ money(row.remaining_balance) }}</strong>
                                        </span>
                                    </td>
                                    <td>{{ dateTime(row.last_payment_date) }}</td>
                                    <td>
                                        <span :class="['status-pill', creditStatusClass(row.payment_status)]">
                                            {{ row.payment_status }}
                                        </span>
                                    </td>
                                    <td class="row-actions">
                                        <button
                                            class="btn btn-small btn-primary"
                                            type="button"
                                            @click.stop="prefillCustomerForPayment(row.customer_id)"
                                            :disabled="row.remaining_balance <= 0"
                                        >
                                            Pay Credit
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section v-if="customerCreditDetail" class="panel">
                    <div class="panel-heading">
                        <h3>Customer Detail: {{ customerCreditDetail.customer.name }}</h3>
                        <button class="btn btn-small btn-muted" type="button" @click="closeCustomerCreditDetail">Close</button>
                    </div>

                    <div class="compact-stats">
                        <div>
                            <span>Total Credit (Utang)</span>
                            <strong>{{ money(customerCreditDetail.summary.total_credit) }}</strong>
                        </div>
                        <div>
                            <span>Total Payments Made</span>
                            <strong>{{ money(customerCreditDetail.summary.total_payments) }}</strong>
                        </div>
                        <div class="balance-highlight">
                            <span>Remaining Balance</span>
                            <strong :class="customerCreditDetail.summary.remaining_balance > 0 ? 'text-danger' : 'text-ok'">
                                {{ money(customerCreditDetail.summary.remaining_balance) }}
                            </strong>
                        </div>
                        <div>
                            <span>Last Payment Date</span>
                            <strong>{{ dateTime(customerCreditDetail.summary.last_payment_date) }}</strong>
                        </div>
                    </div>

                    <div class="panel-heading sub-heading">
                        <h4>Credit Transactions</h4>
                    </div>
                    <div class="table-wrap compact">
                        <table>
                            <thead>
                                <tr>
                                    <th>Transaction #</th>
                                    <th>Date</th>
                                    <th>Original Total</th>
                                    <th>Initial Payment</th>
                                    <th>Subsequent Payments</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="txn in customerCreditDetail.credit_transactions" :key="txn.id">
                                    <td>{{ txn.transaction_number }}</td>
                                    <td>{{ dateTime(txn.sale_date) }}</td>
                                    <td>{{ money(txn.original_total) }}</td>
                                    <td>{{ money(txn.initial_payment) }}</td>
                                    <td>{{ money(txn.total_subsequent_payments) }}</td>
                                    <td>
                                        <span :class="txn.balance > 0 ? 'text-danger' : 'text-ok'">
                                            <strong>{{ money(txn.balance) }}</strong>
                                        </span>
                                    </td>
                                    <td>
                                        <span :class="['status-pill', creditStatusClass(txn.payment_status)]">
                                            {{ txn.payment_status }}
                                        </span>
                                    </td>
                                    <td class="row-actions">
                                        <button
                                            class="btn btn-small btn-primary"
                                            type="button"
                                            @click="prefillTransactionForPayment(txn)"
                                            :disabled="txn.balance <= 0"
                                        >
                                            Pay This
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="panel-heading sub-heading">
                        <h4>Payment History</h4>
                    </div>
                    <div class="table-wrap compact">
                        <table>
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Linked Transaction</th>
                                    <th>Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="customerCreditDetail.payments.length === 0">
                                    <td colspan="4" class="empty-row">No subsequent payments yet.</td>
                                </tr>
                                <tr v-for="p in customerCreditDetail.payments" :key="p.id">
                                    <td>{{ dateTime(p.payment_date) }}</td>
                                    <td><strong>{{ money(p.amount_paid) }}</strong></td>
                                    <td>{{ p.transaction_number || 'Initial (at sale)' }}</td>
                                    <td>{{ p.notes || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-heading">
                        <h3>Record Credit Payment (Pay Utang)</h3>
                    </div>
                    <form class="form-grid" @submit.prevent="saveCreditPayment">
                        <label>
                            Customer
                            <select v-model.number="creditPaymentForm.customer_id" required>
                                <option disabled value="">Select customer with outstanding balance</option>
                                <option
                                    v-for="row in creditCustomersWithBalance"
                                    :key="row.customer_id"
                                    :value="row.customer_id"
                                >
                                    {{ row.name }} - Balance: {{ money(row.remaining_balance) }}
                                </option>
                            </select>
                        </label>
                        <label v-if="creditPaymentForm.customer_id && unpaidTxnsForPaymentCustomer.length > 0">
                            Apply to Specific Transaction (Optional)
                            <select v-model.number="creditPaymentForm.credit_transaction_id">
                                <option value="">Apply to oldest first (FIFO)</option>
                                <option
                                    v-for="txn in unpaidTxnsForPaymentCustomer"
                                    :key="txn.id"
                                    :value="txn.id"
                                >
                                    {{ txn.transaction_number }} - {{ money(txn.balance) }} remaining
                                </option>
                            </select>
                        </label>
                        <label>
                            Amount Paid
                            <input v-model.number="creditPaymentForm.amount_paid" type="number" min="0.01" step="0.01" required>
                        </label>
                        <label>
                            Notes (Optional)
                            <input v-model="creditPaymentForm.notes" type="text" placeholder="e.g. Partial payment">
                        </label>
                        <div class="actions span-full">
                            <button class="btn btn-primary" type="submit" :disabled="savingCreditPayment || !creditPaymentForm.customer_id || creditPaymentForm.amount_paid <= 0">
                                Record Payment
                            </button>
                            <button v-if="creditPaymentForm.customer_id || creditPaymentForm.amount_paid > 0" class="btn btn-muted" type="button" @click="resetCreditPaymentForm">
                                Clear
                            </button>
                        </div>
                    </form>
                </section>
            </section>
        </main>
    </div>
</template>

<script>
export default {
    name: 'SalesTrackerApp',
    props: {
        user: {
            type: Object,
            default: null,
        },
    },
    emits: ['logout'],
    data() {
        return {
            activeTab: 'dashboard',
            sidebarCollapsed: false,
            mobileSidebarOpen: false,
            loading: false,
            savingSale: false,
            notice: {
                type: 'success',
                text: '',
            },
            tabs: [
                { id: 'dashboard', label: 'Dashboard', icon: '1' },
                { id: 'sales', label: 'New Sale', icon: '2' },
                { id: 'products', label: 'Products', icon: '3' },
                { id: 'customers', label: 'Customers', icon: '4' },
                { id: 'credit', label: 'Credit / Utang', icon: '$' },
                { id: 'inventory', label: 'Inventory', icon: '5' },
                { id: 'reports', label: 'Reports', icon: '6' },
                { id: 'history', label: 'History', icon: '7' },
            ],
            dashboard: this.emptyDashboard(),
            products: [],
            customers: [],
            customerReport: [],
            productReport: [],
            trends: {},
            inventoryReport: [],
            salesHistory: [],
            creditDashboard: [],
            customerCreditDetail: null,
            savingCreditPayment: false,
            saleForm: this.emptySaleForm(),
            productForm: this.emptyProductForm(),
            productEditForm: this.emptyProductForm(),
            editingProductId: null,
            customerForm: {
                name: '',
                customer_type: 'Regular Customer',
            },
            creditPaymentForm: {
                customer_id: '',
                credit_transaction_id: '',
                amount_paid: 0,
                notes: '',
            },
            stockForm: {
                product_id: '',
                adjustment_type: 'Add',
                quantity: 1,
                reason: '',
            },
            historyFilters: {
                period: 'today',
                date_from: '',
                date_to: '',
                customer_id: '',
                product_id: '',
            },
        };
    },
    computed: {
        currentTitle() {
            const tab = this.tabs.find((item) => item.id === this.activeTab);
            return tab ? tab.label : 'Dashboard';
        },
        sidebarToggleLabel() {
            return this.sidebarCollapsed ? 'Expand sidebar' : 'Collapse sidebar';
        },
        activeProducts() {
            return this.products.filter((product) => product.status === 'Active');
        },
        saleTotal() {
            return this.saleForm.items.reduce((total, item) => total + this.itemTotal(item), 0);
        },
        creditSaleBalance() {
            if (this.saleForm.payment_method !== 'Credit') {
                return 0;
            }
            return Math.max(0, this.saleTotal - Number(this.saleForm.amount_paid || 0));
        },
        creditSaleNeedsCustomer() {
            return this.saleForm.payment_method === 'Credit'
                && !this.saleForm.customer_id
                && !String(this.saleForm.customer_name || '').trim();
        },
        creditCustomersWithBalance() {
            return this.creditDashboard.filter((row) => Number(row.remaining_balance) > 0.0001);
        },
        unpaidTxnsForPaymentCustomer() {
            if (!this.creditPaymentForm.customer_id || !this.customerCreditDetail) {
                if (this.creditPaymentForm.customer_id) {
                    const hit = this.creditDashboard.find((r) => Number(r.customer_id) === Number(this.creditPaymentForm.customer_id));
                    if (hit && this._customerTxnCache && this._customerTxnCache.customerId === Number(this.creditPaymentForm.customer_id)) {
                        return this._customerTxnCache.txns;
                    }
                }
                return [];
            }
            if (Number(this.customerCreditDetail.customer.id) !== Number(this.creditPaymentForm.customer_id)) {
                return [];
            }
            return this.customerCreditDetail.credit_transactions.filter((txn) => Number(txn.balance) > 0.0001);
        },
        changeAmount() {
            if (this.saleForm.payment_method !== 'Cash') {
                return 0;
            }

            return Math.max(0, Number(this.saleForm.amount_paid || 0) - this.saleTotal);
        },
    },
    created() {
        this.refreshAll();
    },
    mounted() {
        window.addEventListener('keydown', this.handleShellKeydown);
    },
    beforeUnmount() {
        window.removeEventListener('keydown', this.handleShellKeydown);
    },
    methods: {
        emptyDashboard() {
            return {
                today_sales: 0,
                customers_today: 0,
                customers_this_week: 0,
                customers_this_month: 0,
                total_customers: 0,
                transactions_today: 0,
                items_sold_today: 0,
                average_sale: 0,
                payment_totals: {
                    Cash: 0,
                    GCash: 0,
                    Credit: 0,
                },
                current_stock: [],
                low_stock: [],
            };
        },
        emptySaleForm() {
            return {
                customer_id: '',
                customer_name: '',
                customer_type: 'Walk-in Customer',
                items: [
                    {
                        product_id: '',
                        quantity: 1,
                    },
                ],
                payment_method: 'Cash',
                amount_paid: 0,
            };
        },
        emptyProductForm() {
            return {
                name: '',
                selling_price: 0,
                current_stock: 0,
                minimum_stock: 0,
                status: 'Active',
            };
        },
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed;
        },
        openMobileSidebar() {
            this.mobileSidebarOpen = true;
        },
        closeMobileSidebar() {
            this.mobileSidebarOpen = false;
        },
        selectTab(tabId) {
            this.activeTab = tabId;
            this.closeMobileSidebar();
        },
        handleShellKeydown(event) {
            if (event.key === 'Escape') {
                this.closeMobileSidebar();
            }
        },
        async refreshAll() {
            this.loading = true;

            try {
                await Promise.all([
                    this.loadProducts(),
                    this.loadDashboard(),
                    this.loadCustomers(),
                    this.loadReports(),
                    this.loadInventoryReport(),
                    this.loadSalesHistory(),
                    this.loadCreditDashboard(),
                ]);
            } finally {
                this.loading = false;
            }
        },
        async loadCreditDashboard() {
            try {
                const response = await window.axios.get('/api/credit-dashboard');
                this.creditDashboard = response.data.data || [];
            } catch (err) {
                this.creditDashboard = [];
            }
        },
        async openCustomerCreditDetail(customerId) {
            if (this.customerCreditDetail && Number(this.customerCreditDetail.customer.id) === Number(customerId)) {
                this.closeCustomerCreditDetail();
                return;
            }
            try {
                const response = await window.axios.get(`/api/customers/${customerId}/credit-summary`);
                this.customerCreditDetail = response.data.data;
                this._customerTxnCache = {
                    customerId: Number(customerId),
                    txns: (response.data.data.credit_transactions || []).filter((t) => Number(t.balance) > 0.0001),
                };
            } catch (err) {
                this.showError(err);
            }
        },
        closeCustomerCreditDetail() {
            this.customerCreditDetail = null;
            this._customerTxnCache = null;
        },
        prefillCustomerForPayment(customerId) {
            this.creditPaymentForm.customer_id = customerId;
            this.creditPaymentForm.credit_transaction_id = '';
            const row = this.creditDashboard.find((r) => Number(r.customer_id) === Number(customerId));
            this.creditPaymentForm.amount_paid = row ? Number(row.remaining_balance) : 0;
            this.creditPaymentForm.notes = '';

            if (!this.customerCreditDetail || Number(this.customerCreditDetail.customer.id) !== Number(customerId)) {
                this.openCustomerCreditDetail(customerId);
            }
        },
        prefillTransactionForPayment(txn) {
            this.creditPaymentForm.customer_id = this.customerCreditDetail
                ? Number(this.customerCreditDetail.customer.id)
                : this.creditPaymentForm.customer_id;
            this.creditPaymentForm.credit_transaction_id = Number(txn.id);
            this.creditPaymentForm.amount_paid = Number(txn.balance);
            this.creditPaymentForm.notes = '';
        },
        resetCreditPaymentForm() {
            this.creditPaymentForm = {
                customer_id: '',
                credit_transaction_id: '',
                amount_paid: 0,
                notes: '',
            };
        },
        async saveCreditPayment() {
            this.savingCreditPayment = true;
            try {
                const payload = {
                    customer_id: this.creditPaymentForm.customer_id,
                    credit_transaction_id: this.creditPaymentForm.credit_transaction_id || null,
                    amount_paid: Number(this.creditPaymentForm.amount_paid),
                    notes: this.creditPaymentForm.notes || null,
                };
                await window.axios.post('/api/credit-payments', payload);
                this.showNotice('Credit payment recorded successfully.');
                this.resetCreditPaymentForm();
                await this.loadCreditDashboard();
                if (this.customerCreditDetail) {
                    await this.openCustomerCreditDetail(this.customerCreditDetail.customer.id);
                }
                await this.loadCustomers();
            } catch (err) {
                this.showError(err);
            } finally {
                this.savingCreditPayment = false;
            }
        },
        creditStatusClass(status) {
            switch (status) {
                case 'Paid':
                case 'Fully Settled':
                    return 'ok';
                case 'Partially Paid':
                    return 'warn';
                case 'Unpaid/Credit':
                    return 'low';
                default:
                    return '';
            }
        },
        async loadProducts() {
            const response = await window.axios.get('/api/products');
            this.products = response.data.data;
        },
        async loadDashboard() {
            const response = await window.axios.get('/api/dashboard/today');
            this.dashboard = response.data.data;
        },
        async loadCustomers() {
            const response = await window.axios.get('/api/customers');
            this.customers = response.data.data;
        },
        async loadReports() {
            const [customers, products, trends] = await Promise.all([
                window.axios.get('/api/reports/customers'),
                window.axios.get('/api/reports/products'),
                window.axios.get('/api/reports/trends'),
            ]);

            this.customerReport = customers.data.data;
            this.productReport = products.data.data;
            this.trends = trends.data.data;
        },
        async loadInventoryReport() {
            const response = await window.axios.get('/api/reports/inventory');
            this.inventoryReport = response.data.data;
        },
        async loadSalesHistory() {
            const params = Object.entries(this.historyFilters)
                .filter(([, value]) => value !== '')
                .reduce((carry, [key, value]) => {
                    carry[key] = value;
                    return carry;
                }, {});

            const response = await window.axios.get('/api/sales', { params });
            this.salesHistory = response.data.data;
        },
        async saveSale() {
            this.savingSale = true;

            try {
                const payload = {
                    customer_id: this.saleForm.customer_id || null,
                    customer_name: this.saleForm.customer_name || null,
                    customer_type: this.saleForm.customer_type,
                    items: this.saleForm.items
                        .filter((item) => item.product_id && Number(item.quantity) > 0)
                        .map((item) => ({
                            product_id: item.product_id,
                            quantity: item.quantity,
                        })),
                    payment_method: this.saleForm.payment_method,
                    amount_paid: this.paymentAmountForPayload(),
                };

                await window.axios.post('/api/sales', payload);
                this.showNotice('Sale saved.');
                this.saleForm = this.emptySaleForm();
                await this.refreshAll();
            } catch (error) {
                this.showError(error);
            } finally {
                this.savingSale = false;
            }
        },
        async saveProduct() {
            try {
                await window.axios.post('/api/products', this.productForm);
                this.productForm = this.emptyProductForm();
                this.showNotice('Product saved.');
                await this.refreshAll();
            } catch (error) {
                this.showError(error);
            }
        },
        editProduct(product) {
            this.editingProductId = product.id;
            this.productEditForm = {
                name: product.name,
                selling_price: Number(product.selling_price),
                current_stock: Number(product.current_stock),
                minimum_stock: Number(product.minimum_stock),
                status: product.status,
            };
        },
        cancelProductEdit() {
            this.editingProductId = null;
            this.productEditForm = this.emptyProductForm();
        },
        async updateProduct(productId) {
            try {
                await window.axios.put(`/api/products/${productId}`, this.productEditForm);
                this.cancelProductEdit();
                this.showNotice('Product updated.');
                await this.refreshAll();
            } catch (error) {
                this.showError(error);
            }
        },
        async saveCustomer() {
            try {
                await window.axios.post('/api/customers', this.customerForm);
                this.customerForm = {
                    name: '',
                    customer_type: 'Regular Customer',
                };
                this.showNotice('Customer saved.');
                await this.refreshAll();
            } catch (error) {
                this.showError(error);
            }
        },
        async saveStockAdjustment() {
            try {
                await window.axios.post('/api/stock-adjustments', this.stockForm);
                this.stockForm = {
                    product_id: '',
                    adjustment_type: 'Add',
                    quantity: 1,
                    reason: '',
                };
                this.showNotice('Stock updated.');
                await this.refreshAll();
            } catch (error) {
                this.showError(error);
            }
        },
        addSaleItem() {
            this.saleForm.items.push({
                product_id: '',
                quantity: 1,
            });
        },
        removeSaleItem(index) {
            this.saleForm.items.splice(index, 1);
        },
        itemPrice(item) {
            const product = this.products.find((row) => Number(row.id) === Number(item.product_id));
            return product ? Number(product.selling_price) : 0;
        },
        itemTotal(item) {
            return this.itemPrice(item) * Number(item.quantity || 0);
        },
        syncCustomerType() {
            if (this.saleForm.customer_type === 'Walk-in Customer' && !this.saleForm.customer_name) {
                this.saleForm.customer_id = '';
            }
        },
        syncCustomerFromName() {
            const cleanName = this.saleForm.customer_name.trim().toLowerCase();
            const customer = this.customers.find((row) => row.name.toLowerCase() === cleanName);

            this.saleForm.customer_id = customer ? customer.id : '';

            if (customer) {
                this.saleForm.customer_type = customer.customer_type;
            }
        },
        syncPaymentAmount() {
            if (this.saleForm.payment_method === 'GCash') {
                this.saleForm.amount_paid = this.saleTotal;
            }
        },
        paymentAmountForPayload() {
            if (this.saleForm.payment_method === 'GCash') {
                return this.saleTotal;
            }

            if (this.saleForm.payment_method === 'Credit') {
                return Number(this.saleForm.amount_paid || 0);
            }

            return this.saleForm.amount_paid;
        },
        customerLabel(row) {
            return row.customer_name || row.walk_in_number || 'Walk-in';
        },
        stockLevel(product) {
            if (Number(product.current_stock) <= Number(product.minimum_stock)) {
                return 'low';
            }

            return 'ok';
        },
        money(value) {
            return new Intl.NumberFormat('en-PH', {
                style: 'currency',
                currency: 'PHP',
            }).format(Number(value || 0));
        },
        dateTime(value) {
            if (!value) {
                return 'None';
            }

            return new Date(String(value).replace(' ', 'T')).toLocaleString('en-PH', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
                hour: 'numeric',
                minute: '2-digit',
            });
        },
        shortDate(value) {
            if (!value) {
                return '';
            }

            return new Date(String(value).replace(' ', 'T')).toLocaleDateString('en-PH', {
                month: 'short',
                day: 'numeric',
                year: 'numeric',
            });
        },
        showNotice(text) {
            this.notice = {
                type: 'success',
                text,
            };
            window.setTimeout(() => {
                this.notice.text = '';
            }, 3000);
        },
        showError(error) {
            const message = error.response && error.response.data && error.response.data.message
                ? error.response.data.message
                : 'Something went wrong.';

            this.notice = {
                type: 'error',
                text: message,
            };
        },
    },
};
</script>

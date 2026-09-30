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
                    <span aria-hidden="true">{{ sidebarCollapsed ? '›' : '‹' }}</span>
                </button>

                <button
                    class="sidebar-toggle mobile-sidebar-close"
                    type="button"
                    aria-label="Close sidebar"
                    title="Close sidebar"
                    @click="closeMobileSidebar"
                >
                    <span aria-hidden="true">✕</span>
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
                <button
                    v-if="user"
                    type="button"
                    class="tab-button sidebar-logout-btn"
                    :title="sidebarCollapsed ? 'Log Out' : null"
                    @click="handleSidebarLogout"
                >
                    <span class="tab-icon" aria-hidden="true">⎋</span>
                    <span class="tab-label">Log Out</span>
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
                    <button
                        class="btn btn-primary topbar-refresh-btn hide-mobile"
                        type="button"
                        @click="refreshAll"
                        :disabled="loading"
                        :class="{ 'btn-loading': loading }"
                    >
                        <span v-if="!loading" aria-hidden="true">↻</span>
                        Refresh
                    </button>
                    <span v-if="user" class="user-pill hide-mobile" :title="'Signed in as ' + user.name" >
                        {{ user.name }}
                    </span>
                    <button v-if="user" class="btn btn-muted topbar-logout-btn hide-mobile" type="button" @click="$emit('logout')">
                        <span aria-hidden="true">⎋</span>
                        Log Out
                    </button>
                </div>
            </header>

            <div v-if="notice.text" :class="['notice', notice.type]" role="alert">
                <span aria-hidden="true">{{ notice.type === 'success' ? '✓' : '✕' }}</span>
                <span>{{ notice.text }}</span>
            </div>

            <section v-if="activeTab === 'dashboard'" class="page-grid">
                <div class="summary-grid">
                    <article class="metric-card strong">
                        <span>💰 Today's Sales</span>
                        <strong>{{ money(dashboard.today_sales) }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>👥 Customers Today</span>
                        <strong>{{ dashboard.customers_today || 0 }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>🧾 Transactions Today</span>
                        <strong>{{ dashboard.transactions_today || 0 }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>📦 Items Sold Today</span>
                        <strong>{{ dashboard.items_sold_today || 0 }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>💵 Cash Sales</span>
                        <strong>{{ money(dashboard.payment_totals.Cash) }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>📱 GCash Sales</span>
                        <strong>{{ money(dashboard.payment_totals.GCash) }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>💳 Credit Sales</span>
                        <strong>{{ money(dashboard.payment_totals.Credit) }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>📊 Average Sale</span>
                        <strong>{{ money(dashboard.average_sale) }}</strong>
                    </article>
                </div>

                <div class="two-column">
                    <section class="panel">
                        <div class="panel-heading">
                            <h3>📦 Current Stock</h3>
                            <span class="eyebrow">{{ dashboard.current_stock ? dashboard.current_stock.length : 0 }} products</span>
                        </div>
                        <div v-if="!dashboard.current_stock || dashboard.current_stock.length === 0" class="empty-state" style="padding: 40px 20px;">
                            <div class="empty-state-icon">📦</div>
                            <h4>No products in stock</h4>
                            <p>Add products from the Products tab to start tracking inventory.</p>
                        </div>
                        <div v-else class="stock-list">
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
                            <h3>📈 Customer Trends</h3>
                        </div>
                        <div class="compact-stats">
                            <div>
                                <span>This Week</span>
                                <strong>{{ dashboard.customers_this_week || 0 }}</strong>
                            </div>
                            <div>
                                <span>This Month</span>
                                <strong>{{ dashboard.customers_this_month || 0 }}</strong>
                            </div>
                            <div>
                                <span>Total Customers</span>
                                <strong>{{ dashboard.total_customers || 0 }}</strong>
                            </div>
                        </div>
                        <div v-if="dashboard.low_stock && dashboard.low_stock.length" class="low-stock-box">
                            <strong>⚠️ Low Stock Alert — {{ dashboard.low_stock.length }} item(s)</strong>
                            <span v-for="product in dashboard.low_stock" :key="product.id">
                                {{ product.name }}: {{ product.current_stock }} remaining
                            </span>
                        </div>
                    </section>
                </div>
            </section>

            <section v-if="activeTab === 'sales'" class="page-grid">
                <form class="panel sale-panel" @submit.prevent="saveSale" novalidate>
                    <div class="panel-heading">
                        <h3>🛒 New Sale</h3>
                        <strong>{{ money(saleTotal) }}</strong>
                    </div>

                    <p class="form-section-title">Customer Information</p>
                    <div class="form-grid">
                        <label>
                            Customer Type<span class="required">*</span>
                            <select v-model="saleForm.customer_type" @change="syncCustomerType" style="width: 70%;">
                                <option>Walk-in Customer</option>
                                <option>Regular Customer</option>
                            </select>
                        </label>
                        <label id="customer-select-dropdown" class="customer-select-wrapper">
                            Customer Name<span class="required">*</span>
                            <div
                                class="vselect-field"
                                :class="{ 'vselect-focused': customerDropdownOpen }"
                                @click.stop="toggleCustomerDropdown"
                            >
                                <div class="vselect-selection">
                                    <span v-if="saleForm.customer_name" class="vselect-selected-text">
                                        {{ saleForm.customer_name }}
                                    </span>
                                    <span v-else class="vselect-placeholder">
                                        Select a customer or walk-in
                                    </span>
                                </div>
                                <div class="vselect-actions">
                                    <span
                                        v-if="saleForm.customer_name"
                                        class="vselect-clear"
                                        @click.stop="clearCustomerSelection"
                                        title="Clear selection"
                                    >✕</span>
                                    <span class="vselect-caret" :class="{ 'open': customerDropdownOpen }">▾</span>
                                </div>
                            </div>
                            <div v-if="customerDropdownOpen" class="vselect-menu" @click.stop>
                                <div class="vselect-search">
                                    <input
                                        id="customer-search-input"
                                        v-model="customerSearchQuery"
                                        type="text"
                                        placeholder="Search customers..."
                                        @input="() => {}"
                                        @keyup.enter="confirmCustomerSearch"
                                    >
                                </div>
                                <ul class="vselect-list">
                                    <li
                                        class="vselect-option walk-in-option"
                                        :class="{ 'selected': !saleForm.customer_name && saleForm.customer_type === 'Walk-in Customer' }"
                                        @click="selectWalkInCustomer"
                                    >
                                        <span class="vselect-option-icon">🚶</span>
                                        <span class="vselect-option-text">Walk-in Customer</span>
                                    </li>
                                    <li
                                        v-for="customer in filteredCustomerOptions"
                                        :key="customer.id"
                                        class="vselect-option"
                                        :class="{ 'selected': saleForm.customer_id && Number(saleForm.customer_id) === Number(customer.id) }"
                                        @click="selectCustomerFromDropdown(customer)"
                                    >
                                        <span class="vselect-option-icon">
                                            {{ customer.customer_type === 'Regular Customer' ? '⭐' : '👤' }}
                                        </span>
                                        <span class="vselect-option-main">
                                            <span class="vselect-option-text">{{ customer.name }}</span>
                                            <span class="vselect-option-sub">{{ customer.customer_type }}</span>
                                        </span>
                                    </li>
                                    <li v-if="filteredCustomerOptions.length === 0 && customerSearchQuery.trim()" class="vselect-option vselect-empty">
                                        <span v-if="customerSearchQuery.trim()" @click="confirmCustomerSearch" class="vselect-create">
                                            + Use "{{ customerSearchQuery.trim() }}" as new customer name
                                        </span>
                                    </li>
                                    <li v-if="customers.length === 0 && !customerSearchQuery.trim()" class="vselect-option vselect-empty">
                                        No saved customers yet. Add one from the Customers tab.
                                    </li>
                                </ul>
                            </div>
                            <span class="form-helper">Optional for walk-in customers. Required for Credit/Utang.</span>
                        </label>
                    </div>

                    <p class="form-section-title">Sale Items</p>
                    <div class="line-items">
                        <div class="line-item heading">
                            <span>Product</span>
                            <span>Qty</span>
                            <span>Price</span>
                            <span>Total</span>
                            <span></span>
                        </div>
                        <div v-for="(item, index) in saleForm.items" :key="index" class="line-item">
                            <select v-model.number="item.product_id" required>
                                <option disabled value="">— Select a product —</option>
                                <option
                                    v-for="product in activeProducts"
                                    :key="product.id"
                                    :value="product.id"
                                    :disabled="product.current_stock <= 0"
                                >
                                    {{ product.name }} — {{ money(product.selling_price) }} (Stock: {{ product.current_stock }})
                                </option>
                            </select>
                            <input v-model.number="item.quantity" type="number" min="1" step="1" required>
                            <span>{{ money(itemPrice(item)) }}</span>
                            <strong>{{ money(itemTotal(item)) }}</strong>
                            <button class="icon-btn danger" type="button" @click="removeSaleItem(index)" :disabled="saleForm.items.length === 1" title="Remove item" aria-label="Remove item">
                                ✕
                            </button>
                        </div>
                    </div>

                    <button class="btn btn-muted" type="button" @click="addSaleItem">
                        <span aria-hidden="true">+</span> Add Item
                    </button>

                    <p class="form-section-title" style="margin-top: 8px;">Payment Details</p>
                    <div class="payment-grid">
                        <label>
                            Payment Method<span class="required">*</span>
                            <select v-model="saleForm.payment_method" @change="syncPaymentAmount">
                                <option value="Cash">💵 Cash</option>
                                <option value="GCash">📱 GCash</option>
                                <option value="Credit">💳 Credit / Utang</option>
                            </select>
                        </label>
                        <label>
                            Amount Paid<span class="required">*</span>
                            <input v-model.number="saleForm.amount_paid" type="number" min="0" step="0.01" placeholder="0.00">
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
                            :class="{ 'btn-loading': savingSale }"
                        >
                            <span v-if="!savingSale" aria-hidden="true">✓</span>
                            {{ savingSale ? 'Saving...' : 'Save Sale' }}
                        </button>
                    </div>
                </form>

                <section class="panel">
                    <div class="panel-heading">
                        <h3>🕒 Recent Sales</h3>
                    </div>
                    <div class="table-wrap compact">
                        <table>
                            <thead>
                                <tr>
                                    <th>Transaction #</th>
                                    <th>Customer</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="salesHistory.length === 0">
                                    <td colspan="3" class="empty-row">
                                        <div class="empty-state" style="padding: 32px 16px;">
                                            <div class="empty-state-icon">📋</div>
                                            <h4>No sales yet</h4>
                                            <p>Sales will appear here once you process a transaction.</p>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="row in salesHistory.slice(0, 8)" :key="row.id + '-' + row.product_id">
                                    <td><strong>{{ row.transaction_number }}</strong></td>
                                    <td>{{ customerLabel(row) }}</td>
                                    <td><strong>{{ money(row.sale_total) }}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>

            <section v-if="activeTab === 'products'" class="page-grid">
                <form class="panel" @submit.prevent="saveProduct" novalidate>
                    <div class="panel-heading">
                        <h3>📦 Add Product</h3>
                    </div>
                    <div class="form-grid product-form">
                        <label>
                            Product Name<span class="required">*</span>
                            <input v-model="productForm.name" required placeholder="e.g. Premium Ice Candy">
                        </label>
                        <label>
                            Selling Price (₱)<span class="required">*</span>
                            <input v-model.number="productForm.selling_price" type="number" min="0" step="0.01" required placeholder="0.00">
                        </label>
                        <label>
                            Current Stock<span class="required">*</span>
                            <input v-model.number="productForm.current_stock" type="number" min="0" required placeholder="0">
                        </label>
                        <label>
                            Minimum Stock Level<span class="required">*</span>
                            <input v-model.number="productForm.minimum_stock" type="number" min="0" required placeholder="5">
                            <span class="form-helper">Alert when stock drops below this number</span>
                        </label>
                        <label>
                            Status<span class="required">*</span>
                            <select v-model="productForm.status">
                                <option value="Active">🟢 Active</option>
                                <option value="Inactive">⚪ Inactive</option>
                            </select>
                        </label>
                    </div>
                    <button class="btn btn-primary" type="submit">
                        <span aria-hidden="true">+</span> Save Product
                    </button>
                </form>

                <section class="panel">
                    <div class="panel-heading">
                        <h3>📦 Products</h3>
                        <span class="eyebrow">{{ products.length }} total</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Min Stock</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="products.length === 0">
                                    <td colspan="6" class="empty-row">
                                        <div class="empty-state" style="padding: 32px 16px;">
                                            <div class="empty-state-icon">📦</div>
                                            <h4>No products yet</h4>
                                            <p>Add your first product using the form above to get started.</p>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="product in products" :key="product.id">
                                    <template v-if="editingProductId === product.id">
                                        <td><input v-model="productEditForm.name" placeholder="Product name"></td>
                                        <td><input v-model.number="productEditForm.selling_price" type="number" min="0" step="0.01" placeholder="0.00"></td>
                                        <td><input v-model.number="productEditForm.current_stock" type="number" min="0" placeholder="0"></td>
                                        <td><input v-model.number="productEditForm.minimum_stock" type="number" min="0" placeholder="0"></td>
                                        <td>
                                            <select v-model="productEditForm.status">
                                                <option value="Active">🟢 Active</option>
                                                <option value="Inactive">⚪ Inactive</option>
                                            </select>
                                        </td>
                                        <td class="row-actions">
                                            <button class="btn btn-small btn-primary" type="button" @click="updateProduct(product.id)">✓ Save</button>
                                            <button class="btn btn-small btn-muted" type="button" @click="cancelProductEdit">✕ Cancel</button>
                                        </td>
                                    </template>
                                    <template v-else>
                                        <td><strong>{{ product.name }}</strong></td>
                                        <td>{{ money(product.selling_price) }}</td>
                                        <td><span :class="['stock-pill', stockLevel(product)]">{{ product.current_stock }}</span></td>
                                        <td>{{ product.minimum_stock }}</td>
                                        <td>
                                            <span :class="['status-pill', product.status === 'Active' ? 'ok' : 'low']">
                                                {{ product.status }}
                                            </span>
                                        </td>
                                        <td class="row-actions">
                                            <button class="btn btn-small btn-muted" type="button" @click="editProduct(product)">✎ Edit</button>
                                        </td>
                                    </template>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>

            <section v-if="activeTab === 'customers'" class="page-grid">
                <form class="panel" @submit.prevent="saveCustomer" novalidate>
                    <div class="panel-heading">
                        <h3>👥 Add Customer</h3>
                    </div>
                    <div class="form-grid">
                        <label>
                            Customer Name<span class="required">*</span>
                            <input v-model="customerForm.name" required placeholder="e.g. Juan Dela Cruz">
                        </label>
                        <label>
                            Customer Type<span class="required">*</span>
                            <select v-model="customerForm.customer_type">
                                <option value="Regular Customer">⭐ Regular Customer</option>
                                <option value="Walk-in Customer">🚶 Walk-in Customer</option>
                            </select>
                        </label>
                    </div>
                    <button class="btn btn-primary" type="submit">
                        <span aria-hidden="true">+</span> Save Customer
                    </button>
                </form>

                <section class="panel">
                    <div class="panel-heading">
                        <h3>👥 Customers</h3>
                        <span class="eyebrow">{{ customers.length }} total</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Transactions</th>
                                    <th>Items</th>
                                    <th>Total Spent</th>
                                    <th>Last Purchase</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="customers.length === 0">
                                    <td colspan="6" class="empty-row">
                                        <div class="empty-state" style="padding: 32px 16px;">
                                            <div class="empty-state-icon">👥</div>
                                            <h4>No customers yet</h4>
                                            <p>Add your first customer using the form above.</p>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="customer in customers" :key="customer.id">
                                    <td><strong>{{ customer.name }}</strong></td>
                                    <td>
                                        <span :class="['status-pill', customer.customer_type === 'Regular Customer' ? 'ok' : '']">
                                            {{ customer.customer_type }}
                                        </span>
                                    </td>
                                    <td><strong>{{ customer.transactions }}</strong></td>
                                    <td>{{ customer.items_purchased }}</td>
                                    <td><strong>{{ money(customer.total_amount) }}</strong></td>
                                    <td>{{ dateTime(customer.last_purchase_date) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>

            <section v-if="activeTab === 'inventory'" class="page-grid">
                <form class="panel" @submit.prevent="saveStockAdjustment" novalidate>
                    <div class="panel-heading">
                        <h3>📋 Stock Adjustment</h3>
                    </div>
                    <div class="form-grid">
                        <label>
                            Product<span class="required">*</span>
                            <select v-model.number="stockForm.product_id" required>
                                <option disabled value="">— Select a product —</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">
                                    {{ product.name }} — Current stock: {{ product.current_stock }}
                                </option>
                            </select>
                        </label>
                        <label>
                            Adjustment Type<span class="required">*</span>
                            <select v-model="stockForm.adjustment_type">
                                <option value="Add">➕ Add Stock (Restock)</option>
                                <option value="Deduct">➖ Deduct Stock</option>
                                <option value="Damaged">🗑️ Damaged / Disposed</option>
                            </select>
                        </label>
                        <label>
                            Quantity<span class="required">*</span>
                            <input v-model.number="stockForm.quantity" type="number" min="1" step="1" required placeholder="1">
                        </label>
                        <label>
                            Reason / Notes
                            <input v-model="stockForm.reason" placeholder="e.g. Received from supplier, expired items">
                            <span class="form-helper">Optional but recommended for audit trail</span>
                        </label>
                    </div>
                    <button class="btn btn-primary" type="submit">
                        <span aria-hidden="true">✓</span> Save Adjustment
                    </button>
                </form>

                <section class="panel">
                    <div class="panel-heading">
                        <h3>📋 Inventory Records</h3>
                        <span class="eyebrow">{{ inventoryReport.length }} records</span>
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
                                <tr v-if="inventoryReport.length === 0">
                                    <td colspan="8" class="empty-row">
                                        <div class="empty-state" style="padding: 32px 16px;">
                                            <div class="empty-state-icon">📋</div>
                                            <h4>No inventory records</h4>
                                            <p>Inventory movement will appear here as products are sold or adjusted.</p>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="record in inventoryReport" :key="record.id">
                                    <td><strong>{{ shortDate(record.inventory_date) }}</strong></td>
                                    <td>{{ record.product_name }}</td>
                                    <td>{{ record.beginning_stock }}</td>
                                    <td>
                                        <span v-if="Number(record.stock_added) > 0" class="status-pill ok">
                                            +{{ record.stock_added }}
                                        </span>
                                        <span v-else>0</span>
                                    </td>
                                    <td>
                                        <span v-if="Number(record.quantity_sold) > 0" class="status-pill warn">
                                            -{{ record.quantity_sold }}
                                        </span>
                                        <span v-else>0</span>
                                    </td>
                                    <td>
                                        <span v-if="Number(record.damaged) > 0" class="status-pill low">
                                            -{{ record.damaged }}
                                        </span>
                                        <span v-else>0</span>
                                    </td>
                                    <td>
                                        <span v-if="Number(record.adjustments) !== 0"
                                            :class="['status-pill', Number(record.adjustments) > 0 ? 'ok' : 'warn']">
                                            {{ Number(record.adjustments) > 0 ? '+' : '' }}{{ record.adjustments }}
                                        </span>
                                        <span v-else>0</span>
                                    </td>
                                    <td><strong>{{ record.ending_stock }}</strong></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>

            <section v-if="activeTab === 'reports'" class="page-grid">
                <div class="summary-grid">
                    <article class="metric-card strong">
                        <span>👥 Customers Today</span>
                        <strong>{{ trends.customers_today || 0 }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>📅 Customers Yesterday</span>
                        <strong>{{ trends.customers_yesterday || 0 }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>📊 Avg Customers / Day</span>
                        <strong>{{ trends.average_customers_per_day || 0 }}</strong>
                    </article>
                    <article class="metric-card">
                        <span>💰 Avg Spending</span>
                        <strong>{{ money(trends.average_spent_per_customer) }}</strong>
                    </article>
                </div>

                <div class="two-column">
                    <section class="panel">
                        <div class="panel-heading">
                            <h3>👥 Customer Sales Report</h3>
                            <span class="eyebrow">{{ customerReport.length }} customers</span>
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
                                    <tr v-if="customerReport.length === 0">
                                        <td colspan="5" class="empty-row">
                                            <div class="empty-state" style="padding: 32px 16px;">
                                                <div class="empty-state-icon">👥</div>
                                                <h4>No customer data yet</h4>
                                                <p>Reports will generate once sales transactions are recorded.</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-for="row in customerReport" :key="row.customer_name">
                                        <td><strong>{{ row.customer_name }}</strong></td>
                                        <td><strong>{{ row.transactions }}</strong></td>
                                        <td>{{ row.items_purchased }}</td>
                                        <td><strong>{{ money(row.total_amount) }}</strong></td>
                                        <td>{{ dateTime(row.last_purchase_date) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section class="panel">
                        <div class="panel-heading">
                            <h3>📦 Product Sales Report</h3>
                            <span class="eyebrow">{{ productReport.length }} products</span>
                        </div>
                        <div class="table-wrap">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Customers</th>
                                        <th>Qty Sold</th>
                                        <th>Total Revenue</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="productReport.length === 0">
                                        <td colspan="4" class="empty-row">
                                            <div class="empty-state" style="padding: 32px 16px;">
                                                <div class="empty-state-icon">📦</div>
                                                <h4>No product data yet</h4>
                                                <p>Product sales data will appear once transactions are recorded.</p>
                                            </div>
                                        </td>
                                    </tr>
                                    <tr v-for="row in productReport" :key="row.id">
                                        <td><strong>{{ row.name }}</strong></td>
                                        <td>{{ row.customers_who_bought }}</td>
                                        <td><strong>{{ row.quantity_sold }}</strong></td>
                                        <td><strong>{{ money(row.total_sales) }}</strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </section>

            <section v-if="activeTab === 'history'" class="page-grid">
                <form class="panel filters" @submit.prevent="loadSalesHistory" novalidate>
                    <div class="panel-heading">
                        <h3>🕒 Sales History &amp; Filters</h3>
                    </div>
                    <div class="form-grid history-filters">
                        <label>
                            Time Period
                            <select v-model="historyFilters.period">
                                <option value="">⏳ All Time</option>
                                <option value="today">☀️ Today</option>
                                <option value="week">📅 This Week</option>
                                <option value="month">📆 This Month</option>
                            </select>
                        </label>
                        <label>
                            From Date
                            <input v-model="historyFilters.date_from" type="date">
                        </label>
                        <label>
                            To Date
                            <input v-model="historyFilters.date_to" type="date">
                        </label>
                        <label>
                            Filter by Customer
                            <select v-model="historyFilters.customer_id">
                                <option value="">👥 All Customers</option>
                                <option v-for="customer in customers" :key="customer.id" :value="customer.id">
                                    {{ customer.name }}
                                </option>
                            </select>
                        </label>
                        <label>
                            Filter by Product
                            <select v-model="historyFilters.product_id">
                                <option value="">📦 All Products</option>
                                <option v-for="product in products" :key="product.id" :value="product.id">
                                    {{ product.name }}
                                </option>
                            </select>
                        </label>
                    </div>
                    <div class="actions">
                        <button class="btn btn-primary" type="submit">
                            <span aria-hidden="true">🔍</span> Apply Filters
                        </button>
                    </div>
                </form>

                <section class="panel">
                    <div class="panel-heading">
                        <h3>🕒 Sales History Records</h3>
                        <span class="eyebrow">{{ salesHistory.length }} entries</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Transaction #</th>
                                    <th>Date / Time</th>
                                    <th>Customer</th>
                                    <th>Product</th>
                                    <th>Qty</th>
                                    <th>Total</th>
                                    <th>Payment</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="salesHistory.length === 0">
                                    <td colspan="7" class="empty-row">
                                        <div class="empty-state" style="padding: 32px 16px;">
                                            <div class="empty-state-icon">🕒</div>
                                            <h4>No sales history found</h4>
                                            <p>Try adjusting your filters or process a new sale to see records here.</p>
                                        </div>
                                    </td>
                                </tr>
                                <tr v-for="row in salesHistory" :key="row.id + '-' + row.product_id + '-' + row.product_name">
                                    <td><strong>{{ row.transaction_number }}</strong></td>
                                    <td>{{ dateTime(row.sale_date) }}</td>
                                    <td>{{ customerLabel(row) }}</td>
                                    <td>{{ row.product_name }}</td>
                                    <td><strong>{{ row.quantity }}</strong></td>
                                    <td><strong>{{ money(row.total) }}</strong></td>
                                    <td>
                                        <span :class="['status-pill', row.payment_method === 'Cash' ? 'ok' : row.payment_method === 'GCash' ? '' : 'warn']">
                                            {{ row.payment_method }}
                                        </span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </section>

            <section v-if="activeTab === 'credit'" class="page-grid">
                <section class="panel">
                    <div class="panel-heading">
                        <h3>💳 Customer Credit Summary</h3>
                        <span class="eyebrow">{{ creditDashboard.length }} customers</span>
                    </div>
                    <div class="table-wrap">
                        <table>
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Total Credit</th>
                                    <th>Total Payments</th>
                                    <th>Remaining Balance</th>
                                    <th>Last Payment</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="creditDashboard.length === 0">
                                    <td colspan="7" class="empty-row">
                                        <div class="empty-state" style="padding: 32px 16px;">
                                            <div class="empty-state-icon">💳</div>
                                            <h4>No credit transactions yet</h4>
                                            <p>Credit (Utang) sales will appear here once processed. Use the New Sale tab with Credit payment.</p>
                                        </div>
                                    </td>
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
                                            💸 Pay Credit
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <section v-if="customerCreditDetail" class="panel">
                    <div class="panel-heading">
                        <h3>👤 Customer Detail: {{ customerCreditDetail.customer.name }}</h3>
                        <button class="btn btn-small btn-muted" type="button" @click="closeCustomerCreditDetail">
                            ✕ Close
                        </button>
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
                        <h4>📄 Credit Transactions</h4>
                    </div>
                    <div class="table-wrap compact">
                        <table>
                            <thead>
                                <tr>
                                    <th>Transaction #</th>
                                    <th>Date</th>
                                    <th>Original Total</th>
                                    <th>Initial Payment</th>
                                    <th>Subsequent</th>
                                    <th>Balance</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="txn in customerCreditDetail.credit_transactions" :key="txn.id">
                                    <td><strong>{{ txn.transaction_number }}</strong></td>
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
                                            💸 Pay This
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="panel-heading sub-heading">
                        <h4>💰 Payment History</h4>
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
                                    <td colspan="4" class="empty-row">
                                        <div class="empty-state" style="padding: 24px 16px;">
                                            <div class="empty-state-icon" style="width: 44px; height: 44px; font-size: 20px;">💰</div>
                                            <h4>No subsequent payments yet</h4>
                                            <p>Record a payment using the form below to track partial or full payments.</p>
                                        </div>
                                    </td>
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
                        <h3>💸 Record Credit Payment (Pay Utang)</h3>
                    </div>
                    <form class="form-grid" @submit.prevent="saveCreditPayment" novalidate>
                        <label>
                            Customer<span class="required">*</span>
                            <select v-model.number="creditPaymentForm.customer_id" required>
                                <option disabled value="">— Select customer with balance —</option>
                                <option
                                    v-for="row in creditCustomersWithBalance"
                                    :key="row.customer_id"
                                    :value="row.customer_id"
                                >
                                    {{ row.name }} — Balance: {{ money(row.remaining_balance) }}
                                </option>
                            </select>
                        </label>
                        <label v-if="creditPaymentForm.customer_id && unpaidTxnsForPaymentCustomer.length > 0">
                            Apply to Specific Transaction
                            <select v-model.number="creditPaymentForm.credit_transaction_id">
                                <option value="">📋 Apply to oldest first (FIFO)</option>
                                <option
                                    v-for="txn in unpaidTxnsForPaymentCustomer"
                                    :key="txn.id"
                                    :value="txn.id"
                                >
                                    {{ txn.transaction_number }} — {{ money(txn.balance) }} remaining
                                </option>
                            </select>
                            <span class="form-helper">Optional. FIFO is applied automatically if not specified.</span>
                        </label>
                        <label>
                            Amount Paid (₱)<span class="required">*</span>
                            <input v-model.number="creditPaymentForm.amount_paid" type="number" min="0.01" step="0.01" required placeholder="0.00">
                        </label>
                        <label>
                            Notes / Reference
                            <input v-model="creditPaymentForm.notes" type="text" placeholder="e.g. Partial payment, GCash ref# 12345">
                            <span class="form-helper">Optional. Add a note or reference number.</span>
                        </label>
                        <div class="actions span-full">
                            <button
                                class="btn btn-primary"
                                type="submit"
                                :disabled="savingCreditPayment || !creditPaymentForm.customer_id || creditPaymentForm.amount_paid <= 0"
                                :class="{ 'btn-loading': savingCreditPayment }"
                            >
                                <span v-if="!savingCreditPayment" aria-hidden="true">💸</span>
                                {{ savingCreditPayment ? 'Recording...' : 'Record Payment' }}
                            </button>
                            <button v-if="creditPaymentForm.customer_id || creditPaymentForm.amount_paid > 0" class="btn btn-muted" type="button" @click="resetCreditPaymentForm">
                                ↺ Clear Form
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
                { id: 'dashboard', label: 'Dashboard', icon: '📊' },
                { id: 'sales', label: 'New Sale', icon: '🛒' },
                { id: 'products', label: 'Products', icon: '📦' },
                { id: 'customers', label: 'Customers', icon: '👥' },
                { id: 'credit', label: 'Credit / Utang', icon: '💳' },
                { id: 'inventory', label: 'Inventory', icon: '📋' },
                { id: 'reports', label: 'Reports', icon: '📈' },
                { id: 'history', label: 'History', icon: '🕒' },
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
            customerDropdownOpen: false,
            customerSearchQuery: '',
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
        filteredCustomerOptions() {
            const query = this.customerSearchQuery.trim().toLowerCase();
            if (!query) {
                return this.customers;
            }
            return this.customers.filter((c) =>
                c.name.toLowerCase().includes(query)
            );
        },
    },
    created() {
        this.refreshAll();
    },
    mounted() {
        window.addEventListener('keydown', this.handleShellKeydown);
        document.addEventListener('click', this.handleOutsideCustomerDropdown);
    },
    beforeUnmount() {
        window.removeEventListener('keydown', this.handleShellKeydown);
        document.removeEventListener('click', this.handleOutsideCustomerDropdown);
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
        handleSidebarLogout() {
            this.closeMobileSidebar();
            this.$emit('logout');
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
        toggleCustomerDropdown() {
            this.customerDropdownOpen = !this.customerDropdownOpen;
            if (this.customerDropdownOpen) {
                this.customerSearchQuery = this.saleForm.customer_name;
                this.$nextTick(() => {
                    const searchInput = document.getElementById('customer-search-input');
                    if (searchInput) searchInput.focus();
                });
            }
        },
        closeCustomerDropdown() {
            this.customerDropdownOpen = false;
        },
        selectCustomerFromDropdown(customer) {
            this.saleForm.customer_name = customer.name;
            this.saleForm.customer_id = customer.id;
            this.saleForm.customer_type = customer.customer_type;
            this.customerSearchQuery = customer.name;
            this.closeCustomerDropdown();
        },
        selectWalkInCustomer() {
            this.saleForm.customer_name = '';
            this.saleForm.customer_id = '';
            this.saleForm.customer_type = 'Walk-in Customer';
            this.customerSearchQuery = '';
            this.closeCustomerDropdown();
        },
        confirmCustomerSearch() {
            const query = this.customerSearchQuery.trim();
            if (!query) {
                this.selectWalkInCustomer();
                return;
            }
            const exact = this.customers.find(
                (c) => c.name.toLowerCase() === query.toLowerCase()
            );
            if (exact) {
                this.selectCustomerFromDropdown(exact);
            } else {
                this.saleForm.customer_name = query;
                this.saleForm.customer_id = '';
                this.syncCustomerFromName();
                this.closeCustomerDropdown();
            }
        },
        clearCustomerSelection() {
            this.selectWalkInCustomer();
        },
        handleOutsideCustomerDropdown(event) {
            if (!this.customerDropdownOpen) return;
            const dropdown = document.getElementById('customer-select-dropdown');
            if (dropdown && !dropdown.contains(event.target)) {
                this.closeCustomerDropdown();
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

<style scoped>
@media (max-width: 820px) {
    .hide-mobile {
        display: none !important;
    }
}

.customer-select-wrapper {
    position: relative;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.vselect-field {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 70%;
    min-height: 38px;
    padding: 6px 10px;
    background: #ffffff;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    cursor: pointer;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
}

.vselect-field:hover {
    border-color: #9ca3af;
}

.vselect-field.vselect-focused {
    border-color: #4f46e5;
    box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.15);
}

.vselect-selection {
    flex: 1;
    min-width: 0;
    display: flex;
    align-items: center;
}

.vselect-selected-text {
    color: #111827;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.vselect-placeholder {
    color: #9ca3af;
}

.vselect-actions {
    display: flex;
    align-items: center;
    gap: 4px;
    margin-left: 8px;
}

.vselect-clear {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    font-size: 12px;
    color: #9ca3af;
    border-radius: 50%;
    transition: background 0.15s ease, color 0.15s ease;
}

.vselect-clear:hover {
    background: #f3f4f6;
    color: #374151;
}

.vselect-caret {
    font-size: 12px;
    color: #6b7280;
    transition: transform 0.2s ease;
}

.vselect-caret.open {
    transform: rotate(180deg);
}

.vselect-menu {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    width: 70%;
    min-width: 280px;
    max-height: 360px;
    background: #ffffff;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1), 0 8px 10px -6px rgba(0, 0, 0, 0.1);
    z-index: 50;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

.vselect-search {
    padding: 10px;
    border-bottom: 1px solid #f3f4f6;
}

.vselect-search input {
    width: 100%;
    padding: 7px 10px;
    border: 1px solid #d1d5db;
    border-radius: 5px;
    font-size: 13px;
    outline: none;
    transition: border-color 0.15s ease, box-shadow 0.15s ease;
    box-sizing: border-box;
}

.vselect-search input:focus {
    border-color: #4f46e5;
    box-shadow: 0 0 0 2px rgba(79, 70, 229, 0.12);
}

.vselect-list {
    list-style: none;
    margin: 0;
    padding: 6px;
    overflow-y: auto;
    flex: 1;
}

.vselect-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px 10px;
    border-radius: 5px;
    cursor: pointer;
    transition: background 0.12s ease;
}

.vselect-option:hover {
    background: #f3f4f6;
}

.vselect-option.selected {
    background: #eef2ff;
}

.vselect-option.selected:hover {
    background: #e0e7ff;
}

.vselect-option-icon {
    flex-shrink: 0;
    font-size: 15px;
}

.vselect-option-main {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 1px;
}

.vselect-option-text {
    font-size: 13px;
    font-weight: 500;
    color: #111827;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.vselect-option-sub {
    font-size: 11px;
    color: #6b7280;
}

.walk-in-option .vselect-option-text {
    color: #374151;
}

.vselect-option.vselect-empty {
    color: #6b7280;
    font-size: 12px;
    justify-content: center;
    cursor: default;
    padding: 14px 10px;
}

.vselect-option.vselect-empty:hover {
    background: transparent;
}

.vselect-create {
    color: #4f46e5;
    font-weight: 500;
    cursor: pointer;
    text-align: center;
}

.vselect-create:hover {
    color: #4338ca;
    text-decoration: underline;
}
</style>

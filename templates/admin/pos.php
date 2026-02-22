<?php
/**
 * Template: Admin POS (Point of Sale)
 *
 * Manages outlets, menu items, and POS orders with folio posting.
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlPOS">
    <div class="nzl-admin-header">
        <div>
            <h1><?php esc_html_e( 'Point of Sale', 'nozule' ); ?></h1>
        </div>
        <div style="display:flex; gap:0.5rem;">
            <template x-if="activeTab === 'orders'">
                <button class="nzl-btn nzl-btn-primary" @click="openNewOrder()">
                    <?php esc_html_e( 'New Order', 'nozule' ); ?>
                </button>
            </template>
            <template x-if="activeTab === 'outlets'">
                <button class="nzl-btn nzl-btn-primary" @click="openOutletModal()">
                    <?php esc_html_e( 'Add Outlet', 'nozule' ); ?>
                </button>
            </template>
            <template x-if="activeTab === 'items'">
                <button class="nzl-btn nzl-btn-primary" @click="openItemModal()">
                    <?php esc_html_e( 'Add Item', 'nozule' ); ?>
                </button>
            </template>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="nzl-stats-grid" style="margin-bottom:1rem;">
        <div class="nzl-stat-card">
            <div class="nzl-stat-value" x-text="stats.total_orders || 0"></div>
            <div class="nzl-stat-label"><?php esc_html_e( 'Today\'s Orders', 'nozule' ); ?></div>
        </div>
        <div class="nzl-stat-card">
            <div class="nzl-stat-value" x-text="formatCurrency(stats.total_revenue || 0)"></div>
            <div class="nzl-stat-label"><?php esc_html_e( 'Today\'s Revenue', 'nozule' ); ?></div>
        </div>
        <div class="nzl-stat-card">
            <div class="nzl-stat-value" x-text="outlets.length"></div>
            <div class="nzl-stat-label"><?php esc_html_e( 'Active Outlets', 'nozule' ); ?></div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="nzl-tabs" style="margin-bottom:1rem;">
        <button class="nzl-tab" :class="{'active': activeTab === 'orders'}" @click="switchTab('orders')">
            <?php esc_html_e( 'Orders', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'outlets'}" @click="switchTab('outlets')">
            <?php esc_html_e( 'Outlets', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'items'}" @click="switchTab('items')">
            <?php esc_html_e( 'Items', 'nozule' ); ?>
        </button>
    </div>

    <!-- ========================== ORDERS TAB ========================== -->
    <template x-if="activeTab === 'orders'">
        <div>
            <!-- Order Filters -->
            <div class="nzl-card" style="margin-bottom:1rem;">
                <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'Outlet', 'nozule' ); ?></label>
                        <select x-model="orderFilters.outlet_id" @change="loadOrders()" class="nzl-input">
                            <option value=""><?php esc_html_e( 'All Outlets', 'nozule' ); ?></option>
                            <template x-for="outlet in outlets" :key="outlet.id">
                                <option :value="outlet.id" x-text="outlet.name"></option>
                            </template>
                        </select>
                    </div>
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                        <select x-model="orderFilters.status" @change="loadOrders()" class="nzl-input">
                            <option value=""><?php esc_html_e( 'All Statuses', 'nozule' ); ?></option>
                            <option value="open"><?php esc_html_e( 'Open', 'nozule' ); ?></option>
                            <option value="posted"><?php esc_html_e( 'Posted', 'nozule' ); ?></option>
                            <option value="cancelled"><?php esc_html_e( 'Cancelled', 'nozule' ); ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'Date', 'nozule' ); ?></label>
                        <input type="date" x-model="orderFilters.date_from" @change="loadOrders()" class="nzl-input">
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <template x-if="loadingOrders">
                <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
            </template>

            <!-- Orders Table -->
            <template x-if="!loadingOrders">
                <div class="nzl-table-wrap">
                    <table class="nzl-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Order #', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Outlet', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Room', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Items', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Total', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Created', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="order in orders" :key="order.id">
                                <tr>
                                    <td x-text="order.order_number"></td>
                                    <td x-text="getOutletName(order.outlet_id)"></td>
                                    <td x-text="order.room_number || '—'"></td>
                                    <td x-text="order.items_count"></td>
                                    <td x-text="formatCurrency(order.total)"></td>
                                    <td>
                                        <span class="nzl-badge"
                                              :class="{
                                                  'nzl-badge-confirmed': order.status === 'open',
                                                  'nzl-badge-checked_in': order.status === 'posted',
                                                  'nzl-badge-cancelled': order.status === 'cancelled'
                                              }"
                                              x-text="orderStatusLabel(order.status)"></span>
                                    </td>
                                    <td x-text="formatDate(order.created_at)"></td>
                                    <td>
                                        <div style="display:flex; gap:0.25rem; flex-wrap:wrap;">
                                            <button class="nzl-btn nzl-btn-sm" @click="viewOrder(order.id)">
                                                <?php esc_html_e( 'View', 'nozule' ); ?>
                                            </button>
                                            <template x-if="order.status === 'open' && order.booking_id">
                                                <button class="nzl-btn nzl-btn-sm nzl-btn-primary" @click="confirmPostToFolio(order.id)">
                                                    <?php esc_html_e( 'Post to Folio', 'nozule' ); ?>
                                                </button>
                                            </template>
                                            <template x-if="order.status === 'open'">
                                                <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="confirmCancelOrder(order.id)">
                                                    <?php esc_html_e( 'Cancel', 'nozule' ); ?>
                                                </button>
                                            </template>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="orders.length === 0">
                                <tr>
                                    <td colspan="8" style="text-align:center; color:#94a3b8;">
                                        <?php esc_html_e( 'No orders found.', 'nozule' ); ?>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>

            <!-- Pagination -->
            <template x-if="ordersTotalPages > 1">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem;">
                    <span style="font-size:0.875rem; color:#64748b;">
                        <?php esc_html_e( 'Page', 'nozule' ); ?> <span x-text="ordersPage"></span>
                        <?php esc_html_e( 'of', 'nozule' ); ?> <span x-text="ordersTotalPages"></span>
                    </span>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="nzl-btn nzl-btn-sm" @click="ordersPage--; loadOrders()" :disabled="ordersPage <= 1"><?php esc_html_e( 'Previous', 'nozule' ); ?></button>
                        <button class="nzl-btn nzl-btn-sm" @click="ordersPage++; loadOrders()" :disabled="ordersPage >= ordersTotalPages"><?php esc_html_e( 'Next', 'nozule' ); ?></button>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <!-- ========================== OUTLETS TAB ========================== -->
    <template x-if="activeTab === 'outlets'">
        <div>
            <!-- Loading -->
            <template x-if="loadingOutlets">
                <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
            </template>

            <template x-if="!loadingOutlets">
                <div class="nzl-table-wrap">
                    <table class="nzl-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Name', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Name (AR)', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Type', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Items', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="outlet in outlets" :key="outlet.id">
                                <tr>
                                    <td x-text="outlet.name"></td>
                                    <td x-text="outlet.name_ar || '—'"></td>
                                    <td>
                                        <span class="nzl-badge" x-text="outletTypeLabel(outlet.type)"></span>
                                    </td>
                                    <td>
                                        <span class="nzl-badge"
                                              :class="outlet.status === 'active' ? 'nzl-badge-confirmed' : 'nzl-badge-cancelled'"
                                              x-text="outlet.status === 'active' ? '<?php echo esc_js( __( 'Active', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'nozule' ) ); ?>'"></span>
                                    </td>
                                    <td x-text="outlet.item_count || 0"></td>
                                    <td>
                                        <div style="display:flex; gap:0.25rem;">
                                            <button class="nzl-btn nzl-btn-sm" @click="editOutlet(outlet)">
                                                <?php esc_html_e( 'Edit', 'nozule' ); ?>
                                            </button>
                                            <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="confirmDeleteOutlet(outlet.id)">
                                                <?php esc_html_e( 'Delete', 'nozule' ); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="outlets.length === 0">
                                <tr>
                                    <td colspan="6" style="text-align:center; color:#94a3b8;">
                                        <?php esc_html_e( 'No outlets found. Add your first outlet to get started.', 'nozule' ); ?>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </template>

    <!-- ========================== ITEMS TAB ========================== -->
    <template x-if="activeTab === 'items'">
        <div>
            <!-- Item Filter -->
            <div class="nzl-card" style="margin-bottom:1rem;">
                <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'Outlet', 'nozule' ); ?></label>
                        <select x-model="itemFilterOutlet" @change="loadItems()" class="nzl-input">
                            <option value=""><?php esc_html_e( 'All Outlets', 'nozule' ); ?></option>
                            <template x-for="outlet in outlets" :key="outlet.id">
                                <option :value="outlet.id" x-text="outlet.name"></option>
                            </template>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Loading -->
            <template x-if="loadingItems">
                <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
            </template>

            <template x-if="!loadingItems">
                <div class="nzl-table-wrap">
                    <table class="nzl-table">
                        <thead>
                            <tr>
                                <th><?php esc_html_e( 'Name', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Name (AR)', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Outlet', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Category', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Price', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                                <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="item in items" :key="item.id">
                                <tr>
                                    <td x-text="item.name"></td>
                                    <td x-text="item.name_ar || '—'"></td>
                                    <td x-text="getOutletName(item.outlet_id)"></td>
                                    <td x-text="item.category || '—'"></td>
                                    <td x-text="formatCurrency(item.price)"></td>
                                    <td>
                                        <span class="nzl-badge"
                                              :class="item.status === 'active' ? 'nzl-badge-confirmed' : 'nzl-badge-cancelled'"
                                              x-text="item.status === 'active' ? '<?php echo esc_js( __( 'Active', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'nozule' ) ); ?>'"></span>
                                    </td>
                                    <td>
                                        <div style="display:flex; gap:0.25rem;">
                                            <button class="nzl-btn nzl-btn-sm" @click="editItem(item)">
                                                <?php esc_html_e( 'Edit', 'nozule' ); ?>
                                            </button>
                                            <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="confirmDeleteItem(item.id)">
                                                <?php esc_html_e( 'Delete', 'nozule' ); ?>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            </template>
                            <template x-if="items.length === 0">
                                <tr>
                                    <td colspan="7" style="text-align:center; color:#94a3b8;">
                                        <?php esc_html_e( 'No items found.', 'nozule' ); ?>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
        </div>
    </template>

    <!-- ========================== OUTLET MODAL ========================== -->
    <template x-if="showOutletModal">
        <div class="nzl-modal-overlay" @click.self="showOutletModal = false">
            <div class="nzl-modal" style="max-width:550px;">
                <div class="nzl-modal-header">
                    <h3 x-text="outletForm.id ? '<?php echo esc_js( __( 'Edit Outlet', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Outlet', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showOutletModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="nzl-input" x-model="outletForm.name" placeholder="<?php echo esc_attr__( 'Outlet name', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name (Arabic)', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="outletForm.name_ar" dir="rtl" placeholder="<?php echo esc_attr__( 'Arabic name', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Type', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model="outletForm.type">
                                <option value="restaurant"><?php esc_html_e( 'Restaurant', 'nozule' ); ?></option>
                                <option value="minibar"><?php esc_html_e( 'Minibar', 'nozule' ); ?></option>
                                <option value="spa"><?php esc_html_e( 'Spa', 'nozule' ); ?></option>
                                <option value="laundry"><?php esc_html_e( 'Laundry', 'nozule' ); ?></option>
                                <option value="other"><?php esc_html_e( 'Other', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="outletForm.status">
                                <option value="active"><?php esc_html_e( 'Active', 'nozule' ); ?></option>
                                <option value="inactive"><?php esc_html_e( 'Inactive', 'nozule' ); ?></option>
                            </select>
                        </div>
                    </div>
                    <div class="nzl-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Description', 'nozule' ); ?></label>
                        <textarea class="nzl-input" rows="2" x-model="outletForm.description" placeholder="<?php echo esc_attr__( 'Optional description...', 'nozule' ); ?>"></textarea>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showOutletModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveOutlet()" :disabled="saving">
                        <span x-show="!saving" x-text="outletForm.id ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ========================== ITEM MODAL ========================== -->
    <template x-if="showItemModal">
        <div class="nzl-modal-overlay" @click.self="showItemModal = false">
            <div class="nzl-modal" style="max-width:550px;">
                <div class="nzl-modal-header">
                    <h3 x-text="itemForm.id ? '<?php echo esc_js( __( 'Edit Item', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Item', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showItemModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Outlet', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model.number="itemForm.outlet_id">
                                <option value=""><?php esc_html_e( '-- Select Outlet --', 'nozule' ); ?></option>
                                <template x-for="outlet in outlets" :key="outlet.id">
                                    <option :value="outlet.id" x-text="outlet.name"></option>
                                </template>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="nzl-input" x-model="itemForm.name" placeholder="<?php echo esc_attr__( 'Item name', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name (Arabic)', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="itemForm.name_ar" dir="rtl" placeholder="<?php echo esc_attr__( 'Arabic name', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Category', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="itemForm.category" placeholder="<?php echo esc_attr__( 'e.g. Beverages, Main Course...', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Price', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" class="nzl-input" x-model.number="itemForm.price" step="0.01" min="0" placeholder="0.00">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="itemForm.status">
                                <option value="active"><?php esc_html_e( 'Active', 'nozule' ); ?></option>
                                <option value="inactive"><?php esc_html_e( 'Inactive', 'nozule' ); ?></option>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showItemModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveItem()" :disabled="saving">
                        <span x-show="!saving" x-text="itemForm.id ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ========================== NEW ORDER MODAL ========================== -->
    <template x-if="showNewOrderModal">
        <div class="nzl-modal-overlay" @click.self="showNewOrderModal = false">
            <div class="nzl-modal" style="max-width:750px;">
                <div class="nzl-modal-header">
                    <h3><?php esc_html_e( 'New Order', 'nozule' ); ?></h3>
                    <button class="nzl-modal-close" @click="showNewOrderModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <!-- Step 1: Select Outlet & Room -->
                    <div class="nzl-form-grid" style="margin-bottom:1rem;">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Outlet', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model.number="newOrderForm.outlet_id" @change="onNewOrderOutletChange()">
                                <option value=""><?php esc_html_e( '-- Select Outlet --', 'nozule' ); ?></option>
                                <template x-for="outlet in activeOutlets" :key="outlet.id">
                                    <option :value="outlet.id" x-text="outlet.name + ' (' + outletTypeLabel(outlet.type) + ')'"></option>
                                </template>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Room Number', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="newOrderForm.room_number" @blur="lookupBooking()" placeholder="<?php echo esc_attr__( 'e.g. 101', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Booking ID', 'nozule' ); ?></label>
                            <input type="number" class="nzl-input" x-model.number="newOrderForm.booking_id" placeholder="<?php echo esc_attr__( 'Auto or manual', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Guest ID', 'nozule' ); ?></label>
                            <input type="number" class="nzl-input" x-model.number="newOrderForm.guest_id" placeholder="<?php echo esc_attr__( 'Optional', 'nozule' ); ?>">
                        </div>
                    </div>

                    <!-- Booking lookup result -->
                    <template x-if="bookingLookupResult">
                        <div class="nzl-card" style="margin-bottom:1rem; padding:0.75rem; background:#f0fdf4; border-color:#86efac;">
                            <span style="font-size:0.875rem; color:#166534;">
                                <?php esc_html_e( 'Booking found:', 'nozule' ); ?>
                                <strong x-text="'#' + bookingLookupResult.id + ' — ' + (bookingLookupResult.guest_name || '')"></strong>
                            </span>
                        </div>
                    </template>

                    <!-- Step 2: Items Selection -->
                    <template x-if="newOrderForm.outlet_id">
                        <div>
                            <h4 style="margin-bottom:0.5rem; font-weight:600;"><?php esc_html_e( 'Menu Items', 'nozule' ); ?></h4>

                            <template x-if="newOrderOutletItems.length === 0">
                                <p style="color:#94a3b8; font-size:0.875rem;">
                                    <?php esc_html_e( 'No active items for this outlet.', 'nozule' ); ?>
                                </p>
                            </template>

                            <div style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:0.5rem; margin-bottom:1rem;">
                                <template x-for="menuItem in newOrderOutletItems" :key="menuItem.id">
                                    <div class="nzl-card" style="padding:0.75rem; cursor:pointer; transition:all 0.15s;"
                                         :style="isInCart(menuItem.id) ? 'border-color:#3b82f6; background:#eff6ff;' : ''"
                                         @click="addToCart(menuItem)">
                                        <div style="font-weight:600; font-size:0.9rem;" x-text="menuItem.name"></div>
                                        <div style="font-size:0.8rem; color:#64748b;" x-text="menuItem.category || ''"></div>
                                        <div style="font-weight:700; color:#1e40af; margin-top:0.25rem;" x-text="formatCurrency(menuItem.price)"></div>
                                    </div>
                                </template>
                            </div>

                            <!-- Cart -->
                            <template x-if="cart.length > 0">
                                <div>
                                    <h4 style="margin-bottom:0.5rem; font-weight:600;"><?php esc_html_e( 'Order Items', 'nozule' ); ?></h4>
                                    <div class="nzl-table-wrap">
                                        <table class="nzl-table">
                                            <thead>
                                                <tr>
                                                    <th><?php esc_html_e( 'Item', 'nozule' ); ?></th>
                                                    <th style="width:100px;"><?php esc_html_e( 'Qty', 'nozule' ); ?></th>
                                                    <th><?php esc_html_e( 'Price', 'nozule' ); ?></th>
                                                    <th><?php esc_html_e( 'Subtotal', 'nozule' ); ?></th>
                                                    <th style="width:60px;"></th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <template x-for="(cartItem, idx) in cart" :key="idx">
                                                    <tr>
                                                        <td x-text="cartItem.name"></td>
                                                        <td>
                                                            <input type="number" class="nzl-input" style="width:70px; padding:0.25rem 0.5rem;"
                                                                   :value="cartItem.quantity" min="1"
                                                                   @input="updateQuantity(idx, parseInt($event.target.value) || 1)">
                                                        </td>
                                                        <td x-text="formatCurrency(cartItem.price)"></td>
                                                        <td x-text="formatCurrency(cartItem.price * cartItem.quantity)"></td>
                                                        <td>
                                                            <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="removeFromCart(idx)">&times;</button>
                                                        </td>
                                                    </tr>
                                                </template>
                                            </tbody>
                                            <tfoot>
                                                <tr>
                                                    <td colspan="3" style="text-align:right; font-weight:700;">
                                                        <?php esc_html_e( 'Total:', 'nozule' ); ?>
                                                    </td>
                                                    <td style="font-weight:700; color:#1e40af;" x-text="formatCurrency(cartTotal())"></td>
                                                    <td></td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </div>
                            </template>

                            <!-- Notes -->
                            <div class="nzl-form-group" style="margin-top:0.75rem;">
                                <label><?php esc_html_e( 'Notes', 'nozule' ); ?></label>
                                <textarea class="nzl-input" rows="2" x-model="newOrderForm.notes" placeholder="<?php echo esc_attr__( 'Special instructions...', 'nozule' ); ?>"></textarea>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showNewOrderModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="submitNewOrder()" :disabled="saving || cart.length === 0">
                        <span x-show="!saving"><?php esc_html_e( 'Create Order', 'nozule' ); ?></span>
                        <span x-show="saving"><?php esc_html_e( 'Creating...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ========================== ORDER DETAIL MODAL ========================== -->
    <template x-if="showOrderDetail">
        <div class="nzl-modal-overlay" @click.self="showOrderDetail = false">
            <div class="nzl-modal" style="max-width:650px;">
                <div class="nzl-modal-header">
                    <h3>
                        <?php esc_html_e( 'Order', 'nozule' ); ?>
                        <span x-text="orderDetail ? orderDetail.order.order_number : ''"></span>
                    </h3>
                    <button class="nzl-modal-close" @click="showOrderDetail = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <template x-if="loadingOrderDetail">
                        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
                    </template>

                    <template x-if="!loadingOrderDetail && orderDetail">
                        <div>
                            <!-- Order info -->
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:0.75rem; margin-bottom:1rem;">
                                <div>
                                    <span style="font-size:0.8rem; color:#64748b;"><?php esc_html_e( 'Outlet', 'nozule' ); ?></span>
                                    <div style="font-weight:600;" x-text="orderDetail.outlet ? orderDetail.outlet.name : '—'"></div>
                                </div>
                                <div>
                                    <span style="font-size:0.8rem; color:#64748b;"><?php esc_html_e( 'Room', 'nozule' ); ?></span>
                                    <div style="font-weight:600;" x-text="orderDetail.order.room_number || '—'"></div>
                                </div>
                                <div>
                                    <span style="font-size:0.8rem; color:#64748b;"><?php esc_html_e( 'Booking ID', 'nozule' ); ?></span>
                                    <div style="font-weight:600;" x-text="orderDetail.order.booking_id || '—'"></div>
                                </div>
                                <div>
                                    <span style="font-size:0.8rem; color:#64748b;"><?php esc_html_e( 'Status', 'nozule' ); ?></span>
                                    <div>
                                        <span class="nzl-badge"
                                              :class="{
                                                  'nzl-badge-confirmed': orderDetail.order.status === 'open',
                                                  'nzl-badge-checked_in': orderDetail.order.status === 'posted',
                                                  'nzl-badge-cancelled': orderDetail.order.status === 'cancelled'
                                              }"
                                              x-text="orderStatusLabel(orderDetail.order.status)"></span>
                                    </div>
                                </div>
                                <div>
                                    <span style="font-size:0.8rem; color:#64748b;"><?php esc_html_e( 'Created', 'nozule' ); ?></span>
                                    <div style="font-weight:600;" x-text="formatDate(orderDetail.order.created_at)"></div>
                                </div>
                                <div>
                                    <span style="font-size:0.8rem; color:#64748b;"><?php esc_html_e( 'Notes', 'nozule' ); ?></span>
                                    <div style="font-size:0.9rem;" x-text="orderDetail.order.notes || '—'"></div>
                                </div>
                            </div>

                            <!-- Order items -->
                            <h4 style="margin-bottom:0.5rem; font-weight:600;"><?php esc_html_e( 'Items', 'nozule' ); ?></h4>
                            <div class="nzl-table-wrap">
                                <table class="nzl-table">
                                    <thead>
                                        <tr>
                                            <th><?php esc_html_e( 'Item', 'nozule' ); ?></th>
                                            <th><?php esc_html_e( 'Qty', 'nozule' ); ?></th>
                                            <th><?php esc_html_e( 'Unit Price', 'nozule' ); ?></th>
                                            <th><?php esc_html_e( 'Subtotal', 'nozule' ); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <template x-for="oi in orderDetail.items" :key="oi.id">
                                            <tr>
                                                <td x-text="oi.item_name"></td>
                                                <td x-text="oi.quantity"></td>
                                                <td x-text="formatCurrency(oi.unit_price)"></td>
                                                <td x-text="formatCurrency(oi.subtotal)"></td>
                                            </tr>
                                        </template>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" style="text-align:right; font-weight:700;">
                                                <?php esc_html_e( 'Total:', 'nozule' ); ?>
                                            </td>
                                            <td style="font-weight:700; color:#1e40af;" x-text="formatCurrency(orderDetail.order.total)"></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showOrderDetail = false"><?php esc_html_e( 'Close', 'nozule' ); ?></button>
                    <template x-if="orderDetail && orderDetail.order.status === 'open' && orderDetail.order.booking_id">
                        <button class="nzl-btn nzl-btn-primary" @click="showOrderDetail = false; confirmPostToFolio(orderDetail.order.id)">
                            <?php esc_html_e( 'Post to Folio', 'nozule' ); ?>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </template>

</div>

<!-- Toast Notifications -->
<div class="nzl-toast-container" x-data x-show="$store.notifications.items.length > 0">
    <template x-for="notif in $store.notifications.items" :key="notif.id">
        <div class="nzl-toast" :class="'nzl-toast-' + notif.type">
            <span x-text="notif.message"></span>
            <button @click="$store.notifications.remove(notif.id)" style="margin-left:0.5rem; cursor:pointer; background:none; border:none;">&times;</button>
        </div>
    </template>
</div>

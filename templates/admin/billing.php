<?php
/**
 * Template: Admin Billing (Folios + Taxes)
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlBilling">
    <div class="nzl-admin-header">
        <h1><?php esc_html_e( 'Billing', 'nozule' ); ?></h1>
        <div style="display:flex; gap:0.5rem;">
            <template x-if="activeTab === 'taxes'">
                <button class="nzl-btn nzl-btn-primary" @click="openTaxModal()">
                    <?php esc_html_e( 'Add Tax', 'nozule' ); ?>
                </button>
            </template>
        </div>
    </div>

    <!-- Tabs -->
    <div class="nzl-tabs" style="margin-bottom:1rem;">
        <button class="nzl-tab" :class="{'active': activeTab === 'folios'}" @click="activeTab = 'folios'; loadFolios()">
            <?php esc_html_e( 'Folios', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'taxes'}" @click="activeTab = 'taxes'; loadTaxes()">
            <?php esc_html_e( 'Taxes', 'nozule' ); ?>
        </button>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- ======================= FOLIOS TAB ======================= -->
    <template x-if="!loading && activeTab === 'folios'">
        <div>
            <!-- Folio Filters -->
            <div class="nzl-card" style="margin-bottom:1rem;">
                <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                        <select x-model="folioFilters.status" @change="loadFolios()" class="nzl-input">
                            <option value=""><?php esc_html_e( 'All Statuses', 'nozule' ); ?></option>
                            <option value="open"><?php esc_html_e( 'Open', 'nozule' ); ?></option>
                            <option value="closed"><?php esc_html_e( 'Closed', 'nozule' ); ?></option>
                            <option value="void"><?php esc_html_e( 'Void', 'nozule' ); ?></option>
                        </select>
                    </div>
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'Search', 'nozule' ); ?></label>
                        <input type="text" x-model="folioFilters.search" @input.debounce.300ms="loadFolios()"
                               placeholder="<?php esc_attr_e( 'Folio number...', 'nozule' ); ?>"
                               class="nzl-input">
                    </div>
                </div>
            </div>

            <!-- Folios Table -->
            <div class="nzl-table-wrap">
                <table class="nzl-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Folio #', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Guest', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Booking #', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Subtotal', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Tax', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Grand Total', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Paid', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Balance', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="folio in folios" :key="folio.id">
                            <tr>
                                <td>
                                    <a href="#" @click.prevent="viewFolio(folio.id)" class="nzl-link" x-text="folio.folio_number"></a>
                                </td>
                                <td x-text="folio.guest_name"></td>
                                <td x-text="folio.booking_number || '—'"></td>
                                <td x-text="formatPrice(folio.subtotal)"></td>
                                <td x-text="formatPrice(folio.tax_total)"></td>
                                <td><strong x-text="formatPrice(folio.grand_total)"></strong></td>
                                <td x-text="formatPrice(folio.paid_amount)"></td>
                                <td>
                                    <span :style="folio.balance > 0 ? 'color:#ef4444; font-weight:600;' : 'color:#22c55e; font-weight:600;'" x-text="formatPrice(folio.balance)"></span>
                                </td>
                                <td>
                                    <span class="nzl-badge"
                                          :class="{
                                              'nzl-badge-confirmed': folio.status === 'open',
                                              'nzl-badge-checked_out': folio.status === 'closed',
                                              'nzl-badge-cancelled': folio.status === 'void'
                                          }" x-text="statusLabel(folio.status)"></span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:0.25rem;">
                                        <button class="nzl-btn nzl-btn-sm" @click="viewFolio(folio.id)">
                                            <?php esc_html_e( 'View', 'nozule' ); ?>
                                        </button>
                                        <template x-if="folio.status === 'open'">
                                            <button class="nzl-btn nzl-btn-sm nzl-btn-success" @click="closeFolio(folio.id)">
                                                <?php esc_html_e( 'Close', 'nozule' ); ?>
                                            </button>
                                        </template>
                                        <template x-if="folio.status === 'open'">
                                            <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="voidFolio(folio.id)">
                                                <?php esc_html_e( 'Void', 'nozule' ); ?>
                                            </button>
                                        </template>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="folios.length === 0">
                            <tr>
                                <td colspan="10" style="text-align:center; color:#94a3b8;">
                                    <?php esc_html_e( 'No folios found.', 'nozule' ); ?>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Folio Pagination -->
            <template x-if="folioTotalPages > 1">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem;">
                    <span style="font-size:0.875rem; color:#64748b;">
                        <?php esc_html_e( 'Page', 'nozule' ); ?> <span x-text="folioCurrentPage"></span>
                        <?php esc_html_e( 'of', 'nozule' ); ?> <span x-text="folioTotalPages"></span>
                    </span>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="nzl-btn nzl-btn-sm" @click="folioPrevPage()" :disabled="folioCurrentPage <= 1"><?php esc_html_e( 'Previous', 'nozule' ); ?></button>
                        <button class="nzl-btn nzl-btn-sm" @click="folioNextPage()" :disabled="folioCurrentPage >= folioTotalPages"><?php esc_html_e( 'Next', 'nozule' ); ?></button>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <!-- ======================= TAXES TAB ======================= -->
    <template x-if="!loading && activeTab === 'taxes'">
        <div class="nzl-table-wrap">
            <table class="nzl-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Name', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Name (AR)', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Rate', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Applies To', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Active', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Sort Order', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="tax in taxes" :key="tax.id">
                        <tr>
                            <td x-text="tax.name"></td>
                            <td x-text="tax.name_ar || '—'"></td>
                            <td>
                                <span x-text="tax.type === 'percentage' ? tax.rate + '%' : formatPrice(tax.rate)"></span>
                            </td>
                            <td>
                                <span class="nzl-badge" :class="tax.type === 'percentage' ? 'nzl-badge-checked_in' : 'nzl-badge-pending'" x-text="statusLabel(tax.type)"></span>
                            </td>
                            <td x-text="statusLabel(tax.applies_to)"></td>
                            <td>
                                <button class="nzl-btn nzl-btn-sm"
                                        :class="tax.is_active ? 'nzl-btn-success' : 'nzl-btn-danger'"
                                        @click="toggleTaxActive(tax)">
                                    <span x-text="tax.is_active ? '<?php echo esc_js( __( 'Active', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'nozule' ) ); ?>'"></span>
                                </button>
                            </td>
                            <td x-text="tax.sort_order"></td>
                            <td>
                                <div style="display:flex; gap:0.25rem;">
                                    <button class="nzl-btn nzl-btn-sm" @click="editTax(tax)">
                                        <?php esc_html_e( 'Edit', 'nozule' ); ?>
                                    </button>
                                    <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteTax(tax.id)">
                                        <?php esc_html_e( 'Delete', 'nozule' ); ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <template x-if="taxes.length === 0">
                        <tr>
                            <td colspan="8" style="text-align:center; color:#94a3b8;">
                                <?php esc_html_e( 'No taxes configured.', 'nozule' ); ?>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    <!-- ======================= FOLIO DETAIL SIDEBAR ======================= -->
    <template x-if="showFolioPanel">
        <div class="nzl-modal-overlay" @click.self="closeFolioPanel()">
            <div class="nzl-modal" style="max-width:800px; max-height:90vh; overflow-y:auto;">
                <div class="nzl-modal-header">
                    <h3>
                        <?php esc_html_e( 'Folio', 'nozule' ); ?> <span x-text="selectedFolio.folio_number"></span>
                    </h3>
                    <button class="nzl-modal-close" @click="closeFolioPanel()">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <!-- Loading -->
                    <template x-if="loadingFolioDetail">
                        <div style="text-align:center; padding:2rem;"><div class="nzl-spinner nzl-spinner-lg"></div></div>
                    </template>

                    <template x-if="!loadingFolioDetail && selectedFolio">
                        <div>
                            <!-- Folio Header Info -->
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:0.5rem; padding:1rem;">
                                <div style="font-size:0.875rem; color:#64748b; display:flex; flex-direction:column; gap:0.35rem;">
                                    <span><strong><?php esc_html_e( 'Guest', 'nozule' ); ?>:</strong> <span x-text="selectedFolio.guest_name"></span></span>
                                    <span><strong><?php esc_html_e( 'Booking', 'nozule' ); ?>:</strong> <span x-text="selectedFolio.booking_number || '—'"></span></span>
                                    <span><strong><?php esc_html_e( 'Created', 'nozule' ); ?>:</strong> <span x-text="formatDate(selectedFolio.created_at)"></span></span>
                                </div>
                                <div style="font-size:0.875rem; color:#64748b; display:flex; flex-direction:column; gap:0.35rem;">
                                    <span><strong><?php esc_html_e( 'Status', 'nozule' ); ?>:</strong>
                                        <span class="nzl-badge"
                                              :class="{
                                                  'nzl-badge-confirmed': selectedFolio.status === 'open',
                                                  'nzl-badge-checked_out': selectedFolio.status === 'closed',
                                                  'nzl-badge-cancelled': selectedFolio.status === 'void'
                                              }" x-text="statusLabel(selectedFolio.status)"></span>
                                    </span>
                                    <span><strong><?php esc_html_e( 'Currency', 'nozule' ); ?>:</strong> <span x-text="selectedFolio.currency || 'SAR'"></span></span>
                                </div>
                            </div>

                            <!-- Line Items Table -->
                            <h4 style="font-size:0.95rem; font-weight:600; color:#1e293b; margin:0 0 0.75rem 0; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;"><?php esc_html_e( 'Line Items', 'nozule' ); ?></h4>
                            <template x-if="folioItems.length > 0">
                                <div class="nzl-table-wrap">
                                    <table class="nzl-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e( 'Date', 'nozule' ); ?></th>
                                                <th><?php esc_html_e( 'Description', 'nozule' ); ?></th>
                                                <th style="text-align:right;"><?php esc_html_e( 'Qty', 'nozule' ); ?></th>
                                                <th style="text-align:right;"><?php esc_html_e( 'Unit Price', 'nozule' ); ?></th>
                                                <th style="text-align:right;"><?php esc_html_e( 'Tax', 'nozule' ); ?></th>
                                                <th style="text-align:right;"><?php esc_html_e( 'Total', 'nozule' ); ?></th>
                                                <template x-if="selectedFolio.status === 'open'">
                                                    <th style="width:3rem;"></th>
                                                </template>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="item in folioItems" :key="item.id">
                                                <tr>
                                                    <td x-text="formatDate(item.date)"></td>
                                                    <td x-text="item.description"></td>
                                                    <td style="text-align:right;" x-text="item.quantity"></td>
                                                    <td style="text-align:right;" x-text="formatPrice(item.unit_price)"></td>
                                                    <td style="text-align:right;" x-text="formatPrice(item.tax_amount)"></td>
                                                    <td style="text-align:right;"><strong x-text="formatPrice(item.total)"></strong></td>
                                                    <template x-if="selectedFolio.status === 'open'">
                                                        <td>
                                                            <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="removeLineItem(item.id)" style="padding:0.15rem 0.4rem;">&times;</button>
                                                        </td>
                                                    </template>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                            <template x-if="folioItems.length === 0">
                                <p style="text-align:center; color:#94a3b8; font-size:0.875rem; padding:1rem 0;"><?php esc_html_e( 'No line items yet.', 'nozule' ); ?></p>
                            </template>

                            <!-- Add Item Form (only when folio is open) -->
                            <template x-if="selectedFolio.status === 'open'">
                                <div style="margin-top:1rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:0.5rem; padding:1rem;">
                                    <h4 style="font-size:0.85rem; font-weight:600; color:#475569; margin:0 0 0.75rem 0;"><?php esc_html_e( 'Add Item', 'nozule' ); ?></h4>
                                    <div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
                                        <div>
                                            <label class="nzl-label"><?php esc_html_e( 'Category', 'nozule' ); ?></label>
                                            <select class="nzl-input" x-model="itemForm.category" style="min-width:140px;">
                                                <option value="room_charge"><?php esc_html_e( 'Room Charge', 'nozule' ); ?></option>
                                                <option value="extra"><?php esc_html_e( 'Extra', 'nozule' ); ?></option>
                                                <option value="service"><?php esc_html_e( 'Service', 'nozule' ); ?></option>
                                                <option value="food_beverage"><?php esc_html_e( 'Food & Beverage', 'nozule' ); ?></option>
                                                <option value="minibar"><?php esc_html_e( 'Minibar', 'nozule' ); ?></option>
                                                <option value="laundry"><?php esc_html_e( 'Laundry', 'nozule' ); ?></option>
                                                <option value="other"><?php esc_html_e( 'Other', 'nozule' ); ?></option>
                                            </select>
                                        </div>
                                        <div style="flex:1; min-width:160px;">
                                            <label class="nzl-label"><?php esc_html_e( 'Description', 'nozule' ); ?></label>
                                            <input type="text" class="nzl-input" x-model="itemForm.description" placeholder="<?php esc_attr_e( 'Item description...', 'nozule' ); ?>">
                                        </div>
                                        <div style="width:70px;">
                                            <label class="nzl-label"><?php esc_html_e( 'Qty', 'nozule' ); ?></label>
                                            <input type="number" class="nzl-input" x-model.number="itemForm.quantity" min="1" value="1">
                                        </div>
                                        <div style="width:100px;">
                                            <label class="nzl-label"><?php esc_html_e( 'Unit Price', 'nozule' ); ?></label>
                                            <input type="number" step="0.01" min="0" class="nzl-input" x-model.number="itemForm.unit_price" placeholder="0.00">
                                        </div>
                                        <div>
                                            <button class="nzl-btn nzl-btn-primary" @click="addLineItem()" :disabled="savingItem">
                                                <span x-show="!savingItem"><?php esc_html_e( 'Add', 'nozule' ); ?></span>
                                                <span x-show="savingItem"><?php esc_html_e( 'Adding...', 'nozule' ); ?></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Totals Summary -->
                            <div style="margin-top:1.25rem; border-top:2px solid #e2e8f0; padding-top:1rem;">
                                <div style="display:flex; flex-direction:column; align-items:flex-end; gap:0.4rem; font-size:0.925rem;">
                                    <span style="color:#64748b;">
                                        <?php esc_html_e( 'Subtotal', 'nozule' ); ?>: <strong x-text="formatPrice(selectedFolio.subtotal)"></strong>
                                    </span>
                                    <span style="color:#64748b;">
                                        <?php esc_html_e( 'Tax', 'nozule' ); ?>: <strong x-text="formatPrice(selectedFolio.tax_total)"></strong>
                                    </span>
                                    <span style="font-size:1.1rem; font-weight:700; color:#1e293b;">
                                        <?php esc_html_e( 'Grand Total', 'nozule' ); ?>: <span x-text="formatPrice(selectedFolio.grand_total)"></span>
                                    </span>
                                    <span style="color:#22c55e;">
                                        <?php esc_html_e( 'Paid', 'nozule' ); ?>: <strong x-text="formatPrice(selectedFolio.paid_amount)"></strong>
                                    </span>
                                    <span :style="selectedFolio.balance > 0 ? 'color:#ef4444; font-weight:700;' : 'color:#22c55e; font-weight:700;'">
                                        <?php esc_html_e( 'Balance', 'nozule' ); ?>: <span x-text="formatPrice(selectedFolio.balance)"></span>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="closeFolioPanel()"><?php esc_html_e( 'Close', 'nozule' ); ?></button>
                    <template x-if="selectedFolio && selectedFolio.status === 'open'">
                        <button class="nzl-btn nzl-btn-success" @click="closeFolio(selectedFolio.id)">
                            <?php esc_html_e( 'Close Folio', 'nozule' ); ?>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= TAX CREATE/EDIT MODAL ======================= -->
    <template x-if="showTaxModal">
        <div class="nzl-modal-overlay" @click.self="showTaxModal = false">
            <div class="nzl-modal" style="max-width:560px;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingTaxId ? '<?php echo esc_js( __( 'Edit Tax', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Tax', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showTaxModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="nzl-input" x-model="taxForm.name" placeholder="<?php echo esc_attr__( 'e.g. VAT', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name (AR)', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="taxForm.name_ar" placeholder="<?php echo esc_attr__( 'e.g. ضريبة القيمة المضافة', 'nozule' ); ?>" dir="rtl">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Rate', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" step="0.01" min="0" class="nzl-input" x-model.number="taxForm.rate" placeholder="<?php echo esc_attr__( 'e.g. 15', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Type', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model="taxForm.type">
                                <option value="percentage"><?php esc_html_e( 'Percentage', 'nozule' ); ?></option>
                                <option value="fixed"><?php esc_html_e( 'Fixed Amount', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Applies To', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model="taxForm.applies_to">
                                <option value="all"><?php esc_html_e( 'All', 'nozule' ); ?></option>
                                <option value="room_charge"><?php esc_html_e( 'Room Charge', 'nozule' ); ?></option>
                                <option value="extra"><?php esc_html_e( 'Extras', 'nozule' ); ?></option>
                                <option value="service"><?php esc_html_e( 'Services', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Sort Order', 'nozule' ); ?></label>
                            <input type="number" min="0" class="nzl-input" x-model.number="taxForm.sort_order" placeholder="0">
                        </div>
                    </div>
                    <div class="nzl-form-group" style="margin-top:0.75rem;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="checkbox" x-model="taxForm.is_active">
                            <span><?php esc_html_e( 'Active', 'nozule' ); ?></span>
                        </label>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showTaxModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveTax()" :disabled="saving">
                        <span x-show="!saving" x-text="editingTaxId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
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

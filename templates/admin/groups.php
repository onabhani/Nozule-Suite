<?php
/**
 * Template: Admin Group Bookings
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlGroups">
    <div class="nzl-admin-header">
        <h1><?php esc_html_e( 'Group Bookings', 'nozule' ); ?></h1>
        <button class="nzl-btn nzl-btn-primary" @click="openGroupModal()">
            <?php esc_html_e( 'New Group', 'nozule' ); ?>
        </button>
    </div>

    <!-- Filters -->
    <div class="nzl-card" style="margin-bottom:1rem;">
        <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
            <div>
                <label class="nzl-label"><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                <select x-model="filters.status" @change="loadGroups()" class="nzl-input">
                    <option value=""><?php esc_html_e( 'All Statuses', 'nozule' ); ?></option>
                    <option value="tentative"><?php esc_html_e( 'Tentative', 'nozule' ); ?></option>
                    <option value="confirmed"><?php esc_html_e( 'Confirmed', 'nozule' ); ?></option>
                    <option value="checked_in"><?php esc_html_e( 'Checked In', 'nozule' ); ?></option>
                    <option value="checked_out"><?php esc_html_e( 'Checked Out', 'nozule' ); ?></option>
                    <option value="cancelled"><?php esc_html_e( 'Cancelled', 'nozule' ); ?></option>
                </select>
            </div>
            <div>
                <label class="nzl-label"><?php esc_html_e( 'From', 'nozule' ); ?></label>
                <input type="date" x-model="filters.from" @change="loadGroups()" class="nzl-input">
            </div>
            <div>
                <label class="nzl-label"><?php esc_html_e( 'To', 'nozule' ); ?></label>
                <input type="date" x-model="filters.to" @change="loadGroups()" class="nzl-input">
            </div>
            <div>
                <label class="nzl-label"><?php esc_html_e( 'Search', 'nozule' ); ?></label>
                <input type="text" x-model="filters.search" @input.debounce.300ms="loadGroups()"
                       placeholder="<?php esc_attr_e( 'Group name, agency...', 'nozule' ); ?>"
                       class="nzl-input">
            </div>
        </div>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- Groups Table -->
    <template x-if="!loading">
        <div class="nzl-table-wrap">
            <table class="nzl-table">
                <thead>
                    <tr>
                        <th><?php esc_html_e( 'Group #', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Name', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Agency', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Check-in', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Check-out', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Rooms', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Guests', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Total', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="group in groups" :key="group.id">
                        <tr>
                            <td>
                                <a href="#" @click.prevent="viewGroup(group.id)" class="nzl-link" x-text="group.group_number"></a>
                            </td>
                            <td x-text="group.group_name"></td>
                            <td x-text="group.agency_name || '—'"></td>
                            <td x-text="formatDate(group.check_in)"></td>
                            <td x-text="formatDate(group.check_out)"></td>
                            <td x-text="group.room_count || 0"></td>
                            <td x-text="group.guest_count || 0"></td>
                            <td x-text="formatPrice(group.total_amount)"></td>
                            <td>
                                <span class="nzl-badge"
                                      :class="{
                                          'nzl-badge-pending': group.status === 'tentative',
                                          'nzl-badge-confirmed': group.status === 'confirmed',
                                          'nzl-badge-checked_in': group.status === 'checked_in',
                                          'nzl-badge-checked_out': group.status === 'checked_out',
                                          'nzl-badge-cancelled': group.status === 'cancelled'
                                      }" x-text="statusLabel(group.status)"></span>
                            </td>
                            <td>
                                <div style="display:flex; gap:0.25rem; flex-wrap:wrap;">
                                    <button class="nzl-btn nzl-btn-sm" @click="viewGroup(group.id)">
                                        <?php esc_html_e( 'View', 'nozule' ); ?>
                                    </button>
                                    <template x-if="group.status === 'tentative'">
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-success" @click="confirmGroup(group.id)">
                                            <?php esc_html_e( 'Confirm', 'nozule' ); ?>
                                        </button>
                                    </template>
                                    <template x-if="group.status === 'confirmed'">
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-primary" @click="checkInGroup(group.id)">
                                            <?php esc_html_e( 'Check In', 'nozule' ); ?>
                                        </button>
                                    </template>
                                    <template x-if="group.status === 'checked_in'">
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-primary" @click="checkOutGroup(group.id)">
                                            <?php esc_html_e( 'Check Out', 'nozule' ); ?>
                                        </button>
                                    </template>
                                    <template x-if="['tentative','confirmed'].includes(group.status)">
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="cancelGroup(group.id)">
                                            <?php esc_html_e( 'Cancel', 'nozule' ); ?>
                                        </button>
                                    </template>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <template x-if="groups.length === 0">
                        <tr>
                            <td colspan="10" style="text-align:center; color:#94a3b8;">
                                <?php esc_html_e( 'No group bookings found.', 'nozule' ); ?>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    <!-- Pagination -->
    <template x-if="totalPages > 1">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem;">
            <span style="font-size:0.875rem; color:#64748b;">
                <?php esc_html_e( 'Page', 'nozule' ); ?> <span x-text="currentPage"></span>
                <?php esc_html_e( 'of', 'nozule' ); ?> <span x-text="totalPages"></span>
            </span>
            <div style="display:flex; gap:0.5rem;">
                <button class="nzl-btn nzl-btn-sm" @click="prevPage()" :disabled="currentPage <= 1"><?php esc_html_e( 'Previous', 'nozule' ); ?></button>
                <button class="nzl-btn nzl-btn-sm" @click="nextPage()" :disabled="currentPage >= totalPages"><?php esc_html_e( 'Next', 'nozule' ); ?></button>
            </div>
        </div>
    </template>

    <!-- ======================= GROUP DETAIL SIDEBAR ======================= -->
    <template x-if="showDetailPanel">
        <div class="nzl-modal-overlay" @click.self="closeDetail()">
            <div class="nzl-modal" style="max-width:860px; max-height:90vh; overflow-y:auto;">
                <div class="nzl-modal-header">
                    <h3><?php esc_html_e( 'Group Details', 'nozule' ); ?></h3>
                    <button class="nzl-modal-close" @click="closeDetail()">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <!-- Loading -->
                    <template x-if="loadingDetail">
                        <div style="text-align:center; padding:2rem;"><div class="nzl-spinner nzl-spinner-lg"></div></div>
                    </template>

                    <template x-if="!loadingDetail && selectedGroup">
                        <div>
                            <!-- Group Info Header -->
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:0.5rem; padding:1rem;">
                                <div style="font-size:0.875rem; color:#64748b; display:flex; flex-direction:column; gap:0.35rem;">
                                    <h4 style="font-size:1.25rem; font-weight:700; margin:0 0 0.5rem 0; color:#1e293b;" x-text="selectedGroup.group_name"></h4>
                                    <span><strong><?php esc_html_e( 'Group #', 'nozule' ); ?>:</strong> <span x-text="selectedGroup.group_number"></span></span>
                                    <template x-if="selectedGroup.agency_name">
                                        <span><strong><?php esc_html_e( 'Agency', 'nozule' ); ?>:</strong> <span x-text="selectedGroup.agency_name"></span></span>
                                    </template>
                                    <template x-if="selectedGroup.contact_person">
                                        <span><strong><?php esc_html_e( 'Contact', 'nozule' ); ?>:</strong> <span x-text="selectedGroup.contact_person"></span></span>
                                    </template>
                                    <template x-if="selectedGroup.contact_phone">
                                        <span><strong><?php esc_html_e( 'Phone', 'nozule' ); ?>:</strong> <span x-text="selectedGroup.contact_phone" dir="ltr"></span></span>
                                    </template>
                                    <template x-if="selectedGroup.contact_email">
                                        <span><strong><?php esc_html_e( 'Email', 'nozule' ); ?>:</strong> <a :href="'mailto:' + selectedGroup.contact_email" class="nzl-link" x-text="selectedGroup.contact_email"></a></span>
                                    </template>
                                </div>
                                <div style="font-size:0.875rem; color:#64748b; display:flex; flex-direction:column; gap:0.35rem;">
                                    <span><strong><?php esc_html_e( 'Check-in', 'nozule' ); ?>:</strong> <span x-text="formatDate(selectedGroup.check_in)"></span></span>
                                    <span><strong><?php esc_html_e( 'Check-out', 'nozule' ); ?>:</strong> <span x-text="formatDate(selectedGroup.check_out)"></span></span>
                                    <span><strong><?php esc_html_e( 'Status', 'nozule' ); ?>:</strong>
                                        <span class="nzl-badge"
                                              :class="{
                                                  'nzl-badge-pending': selectedGroup.status === 'tentative',
                                                  'nzl-badge-confirmed': selectedGroup.status === 'confirmed',
                                                  'nzl-badge-checked_in': selectedGroup.status === 'checked_in',
                                                  'nzl-badge-checked_out': selectedGroup.status === 'checked_out',
                                                  'nzl-badge-cancelled': selectedGroup.status === 'cancelled'
                                              }" x-text="statusLabel(selectedGroup.status)"></span>
                                    </span>
                                    <template x-if="selectedGroup.payment_terms">
                                        <span><strong><?php esc_html_e( 'Payment Terms', 'nozule' ); ?>:</strong> <span x-text="selectedGroup.payment_terms"></span></span>
                                    </template>
                                    <template x-if="selectedGroup.notes">
                                        <span><strong><?php esc_html_e( 'Notes', 'nozule' ); ?>:</strong> <span x-text="selectedGroup.notes"></span></span>
                                    </template>
                                </div>
                            </div>

                            <!-- Rooming List -->
                            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.75rem; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;">
                                <h4 style="font-size:0.95rem; font-weight:600; color:#1e293b; margin:0;"><?php esc_html_e( 'Rooming List', 'nozule' ); ?></h4>
                                <button class="nzl-btn nzl-btn-sm nzl-btn-primary" @click="showAddRoomForm = !showAddRoomForm">
                                    <span x-text="showAddRoomForm ? '<?php echo esc_js( __( 'Cancel', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Room', 'nozule' ) ); ?>'"></span>
                                </button>
                            </div>

                            <!-- Add Room Inline Form -->
                            <template x-if="showAddRoomForm">
                                <div style="margin-bottom:1rem; background:#f8fafc; border:1px solid #e2e8f0; border-radius:0.5rem; padding:1rem;">
                                    <div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
                                        <div>
                                            <label class="nzl-label"><?php esc_html_e( 'Room Type', 'nozule' ); ?></label>
                                            <select class="nzl-input" x-model.number="roomingForm.room_type_id" style="min-width:150px;">
                                                <option value=""><?php esc_html_e( '-- Select --', 'nozule' ); ?></option>
                                                <template x-for="rt in roomTypes" :key="rt.id">
                                                    <option :value="rt.id" x-text="rt.name"></option>
                                                </template>
                                            </select>
                                        </div>
                                        <div style="flex:1; min-width:140px;">
                                            <label class="nzl-label"><?php esc_html_e( 'Guest Name', 'nozule' ); ?></label>
                                            <input type="text" class="nzl-input" x-model="roomingForm.guest_name" placeholder="<?php esc_attr_e( 'Guest name...', 'nozule' ); ?>">
                                        </div>
                                        <div style="width:110px;">
                                            <label class="nzl-label"><?php esc_html_e( 'Rate/Night', 'nozule' ); ?></label>
                                            <input type="number" step="0.01" min="0" class="nzl-input" x-model.number="roomingForm.rate_per_night" placeholder="0.00">
                                        </div>
                                        <div>
                                            <button class="nzl-btn nzl-btn-primary" @click="addRoomToGroup()" :disabled="savingRoom">
                                                <span x-show="!savingRoom"><?php esc_html_e( 'Add', 'nozule' ); ?></span>
                                                <span x-show="savingRoom"><?php esc_html_e( 'Adding...', 'nozule' ); ?></span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </template>

                            <!-- Rooming List Table -->
                            <template x-if="roomingList.length > 0">
                                <div class="nzl-table-wrap">
                                    <table class="nzl-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e( 'Room Type', 'nozule' ); ?></th>
                                                <th><?php esc_html_e( 'Room #', 'nozule' ); ?></th>
                                                <th><?php esc_html_e( 'Guest Name', 'nozule' ); ?></th>
                                                <th style="text-align:right;"><?php esc_html_e( 'Rate/Night', 'nozule' ); ?></th>
                                                <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                                                <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="entry in roomingList" :key="entry.id">
                                                <tr>
                                                    <td x-text="entry.room_type_name"></td>
                                                    <td x-text="entry.room_number || '—'"></td>
                                                    <td x-text="entry.guest_name"></td>
                                                    <td style="text-align:right;" x-text="formatPrice(entry.rate_per_night)"></td>
                                                    <td>
                                                        <span class="nzl-badge"
                                                              :class="{
                                                                  'nzl-badge-pending': entry.status === 'unassigned',
                                                                  'nzl-badge-confirmed': entry.status === 'assigned',
                                                                  'nzl-badge-checked_in': entry.status === 'checked_in',
                                                                  'nzl-badge-checked_out': entry.status === 'checked_out'
                                                              }" x-text="statusLabel(entry.status)"></span>
                                                    </td>
                                                    <td>
                                                        <div style="display:flex; gap:0.25rem;">
                                                            <template x-if="!entry.room_number">
                                                                <select class="nzl-input" style="width:auto; padding:0.25rem 0.5rem; font-size:0.8rem;" @change="assignRoomToEntry(entry.id, $event.target.value); $event.target.value = ''">
                                                                    <option value=""><?php esc_html_e( 'Assign Room', 'nozule' ); ?></option>
                                                                    <template x-for="room in getAvailableRooms(entry.room_type_id)" :key="room.id">
                                                                        <option :value="room.id" x-text="room.room_number"></option>
                                                                    </template>
                                                                </select>
                                                            </template>
                                                            <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="removeRoomFromGroup(entry.id)">
                                                                <?php esc_html_e( 'Remove', 'nozule' ); ?>
                                                            </button>
                                                        </div>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                            <template x-if="roomingList.length === 0">
                                <p style="text-align:center; color:#94a3b8; font-size:0.875rem; padding:1rem 0;"><?php esc_html_e( 'No rooms added to this group yet.', 'nozule' ); ?></p>
                            </template>

                            <!-- Summary -->
                            <div style="margin-top:1.25rem; border-top:2px solid #e2e8f0; padding-top:1rem;">
                                <div class="nzl-stats-grid">
                                    <div class="nzl-stat-card">
                                        <div class="nzl-stat-value" x-text="selectedGroup.room_count || roomingList.length"></div>
                                        <div class="nzl-stat-label"><?php esc_html_e( 'Total Rooms', 'nozule' ); ?></div>
                                    </div>
                                    <div class="nzl-stat-card">
                                        <div class="nzl-stat-value" x-text="formatPrice(selectedGroup.total_amount)"></div>
                                        <div class="nzl-stat-label"><?php esc_html_e( 'Total Amount', 'nozule' ); ?></div>
                                    </div>
                                    <div class="nzl-stat-card">
                                        <div class="nzl-stat-value" x-text="formatPrice(selectedGroup.paid_amount || 0)"></div>
                                        <div class="nzl-stat-label"><?php esc_html_e( 'Paid', 'nozule' ); ?></div>
                                    </div>
                                    <div class="nzl-stat-card">
                                        <div class="nzl-stat-value" :style="(selectedGroup.total_amount - (selectedGroup.paid_amount || 0)) > 0 ? 'color:#ef4444;' : 'color:#22c55e;'" x-text="formatPrice(selectedGroup.total_amount - (selectedGroup.paid_amount || 0))"></div>
                                        <div class="nzl-stat-label"><?php esc_html_e( 'Balance', 'nozule' ); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="closeDetail()"><?php esc_html_e( 'Close', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="editGroupFromDetail()">
                        <?php esc_html_e( 'Edit Group', 'nozule' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= CREATE/EDIT GROUP MODAL ======================= -->
    <template x-if="showGroupModal">
        <div class="nzl-modal-overlay" @click.self="showGroupModal = false">
            <div class="nzl-modal" style="max-width:680px;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingGroupId ? '<?php echo esc_js( __( 'Edit Group', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'New Group Booking', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showGroupModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <!-- Group Info -->
                    <h4 style="font-size:0.875rem; font-weight:600; color:#475569; margin:0 0 0.75rem 0; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;"><?php esc_html_e( 'Group Information', 'nozule' ); ?></h4>
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Group Name', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" class="nzl-input" x-model="groupForm.group_name" placeholder="<?php echo esc_attr__( 'e.g. Conference Delegates', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Group Name (AR)', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="groupForm.group_name_ar" placeholder="<?php echo esc_attr__( 'الاسم بالعربية', 'nozule' ); ?>" dir="rtl">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Agency Name', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="groupForm.agency_name" placeholder="<?php echo esc_attr__( 'e.g. ABC Travel Agency', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Agency Name (AR)', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="groupForm.agency_name_ar" placeholder="<?php echo esc_attr__( 'اسم الوكالة بالعربية', 'nozule' ); ?>" dir="rtl">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Contact Person', 'nozule' ); ?></label>
                            <input type="text" class="nzl-input" x-model="groupForm.contact_person" placeholder="<?php echo esc_attr__( 'Full name', 'nozule' ); ?>">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Contact Phone', 'nozule' ); ?></label>
                            <input type="tel" class="nzl-input" x-model="groupForm.contact_phone" placeholder="<?php echo esc_attr__( '+966 5xx xxx xxx', 'nozule' ); ?>" dir="ltr">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Contact Email', 'nozule' ); ?></label>
                            <input type="email" class="nzl-input" x-model="groupForm.contact_email" placeholder="<?php echo esc_attr__( 'email@example.com', 'nozule' ); ?>">
                        </div>
                    </div>

                    <!-- Dates & Payment -->
                    <h4 style="font-size:0.875rem; font-weight:600; color:#475569; margin:1.25rem 0 0.75rem 0; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;"><?php esc_html_e( 'Dates & Payment', 'nozule' ); ?></h4>
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Check-in', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="date" class="nzl-input" x-model="groupForm.check_in">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Check-out', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="date" class="nzl-input" x-model="groupForm.check_out">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Currency', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="groupForm.currency">
                                <option value="SYP"><?php esc_html_e( 'SYP - Syrian Pound', 'nozule' ); ?></option>
                                <option value="SAR"><?php esc_html_e( 'SAR - Saudi Riyal', 'nozule' ); ?></option>
                                <option value="USD"><?php esc_html_e( 'USD - US Dollar', 'nozule' ); ?></option>
                                <option value="EUR"><?php esc_html_e( 'EUR - Euro', 'nozule' ); ?></option>
                                <option value="GBP"><?php esc_html_e( 'GBP - British Pound', 'nozule' ); ?></option>
                                <option value="AED"><?php esc_html_e( 'AED - UAE Dirham', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Payment Terms', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model="groupForm.payment_terms">
                                <option value=""><?php esc_html_e( '-- Select --', 'nozule' ); ?></option>
                                <option value="prepaid"><?php esc_html_e( 'Prepaid', 'nozule' ); ?></option>
                                <option value="pay_on_arrival"><?php esc_html_e( 'Pay on Arrival', 'nozule' ); ?></option>
                                <option value="net_15"><?php esc_html_e( 'Net 15', 'nozule' ); ?></option>
                                <option value="net_30"><?php esc_html_e( 'Net 30', 'nozule' ); ?></option>
                                <option value="net_60"><?php esc_html_e( 'Net 60', 'nozule' ); ?></option>
                                <option value="credit"><?php esc_html_e( 'Credit', 'nozule' ); ?></option>
                            </select>
                        </div>
                    </div>

                    <!-- Notes -->
                    <div class="nzl-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Notes', 'nozule' ); ?></label>
                        <textarea class="nzl-input" rows="3" x-model="groupForm.notes" placeholder="<?php echo esc_attr__( 'Internal notes about this group...', 'nozule' ); ?>"></textarea>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showGroupModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveGroup()" :disabled="saving">
                        <span x-show="!saving" x-text="editingGroupId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
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

<?php
/**
 * Template: Admin Housekeeping
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlHousekeeping">
    <div class="nzl-admin-header">
        <h1><?php esc_html_e( 'Housekeeping', 'nozule' ); ?></h1>
        <div style="display:flex; gap:0.5rem;">
            <button class="nzl-btn nzl-btn-success" @click="markSelectedClean()" :disabled="selectedRooms.length === 0">
                <?php esc_html_e( 'Mark All Clean', 'nozule' ); ?>
            </button>
            <button class="nzl-btn nzl-btn-primary" @click="openTaskModal()">
                <?php esc_html_e( 'Add Task', 'nozule' ); ?>
            </button>
        </div>
    </div>

    <!-- Stats Row -->
    <div class="nzl-stats-grid" style="margin-bottom:1rem;">
        <div class="nzl-stat-card">
            <div class="nzl-stat-value" x-text="stats.dirty || 0"></div>
            <div class="nzl-stat-label">
                <span class="nzl-badge nzl-badge-cancelled"><?php esc_html_e( 'Dirty', 'nozule' ); ?></span>
            </div>
        </div>
        <div class="nzl-stat-card">
            <div class="nzl-stat-value" x-text="stats.clean || 0"></div>
            <div class="nzl-stat-label">
                <span class="nzl-badge nzl-badge-confirmed"><?php esc_html_e( 'Clean', 'nozule' ); ?></span>
            </div>
        </div>
        <div class="nzl-stat-card">
            <div class="nzl-stat-value" x-text="stats.inspected || 0"></div>
            <div class="nzl-stat-label">
                <span class="nzl-badge nzl-badge-checked_in"><?php esc_html_e( 'Inspected', 'nozule' ); ?></span>
            </div>
        </div>
        <div class="nzl-stat-card">
            <div class="nzl-stat-value" x-text="stats.out_of_order || 0"></div>
            <div class="nzl-stat-label">
                <span class="nzl-badge nzl-badge-pending"><?php esc_html_e( 'Out of Order', 'nozule' ); ?></span>
            </div>
        </div>
    </div>

    <!-- Filters -->
    <div class="nzl-card" style="margin-bottom:1rem;">
        <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
            <div>
                <label class="nzl-label"><?php esc_html_e( 'Status', 'nozule' ); ?></label>
                <select x-model="filters.status" @change="loadTasks()" class="nzl-input">
                    <option value=""><?php esc_html_e( 'All Statuses', 'nozule' ); ?></option>
                    <option value="dirty"><?php esc_html_e( 'Dirty', 'nozule' ); ?></option>
                    <option value="clean"><?php esc_html_e( 'Clean', 'nozule' ); ?></option>
                    <option value="inspected"><?php esc_html_e( 'Inspected', 'nozule' ); ?></option>
                    <option value="out_of_order"><?php esc_html_e( 'Out of Order', 'nozule' ); ?></option>
                </select>
            </div>
            <div>
                <label class="nzl-label"><?php esc_html_e( 'Priority', 'nozule' ); ?></label>
                <select x-model="filters.priority" @change="loadTasks()" class="nzl-input">
                    <option value=""><?php esc_html_e( 'All Priorities', 'nozule' ); ?></option>
                    <option value="low"><?php esc_html_e( 'Low', 'nozule' ); ?></option>
                    <option value="normal"><?php esc_html_e( 'Normal', 'nozule' ); ?></option>
                    <option value="high"><?php esc_html_e( 'High', 'nozule' ); ?></option>
                    <option value="urgent"><?php esc_html_e( 'Urgent', 'nozule' ); ?></option>
                </select>
            </div>
            <div>
                <label class="nzl-label"><?php esc_html_e( 'Room', 'nozule' ); ?></label>
                <select x-model="filters.room_id" @change="loadTasks()" class="nzl-input">
                    <option value=""><?php esc_html_e( 'All Rooms', 'nozule' ); ?></option>
                    <template x-for="room in rooms" :key="room.id">
                        <option :value="room.id" x-text="room.room_number"></option>
                    </template>
                </select>
            </div>
        </div>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- Housekeeping Tasks Table -->
    <template x-if="!loading">
        <div class="nzl-table-wrap">
            <table class="nzl-table">
                <thead>
                    <tr>
                        <th style="width:2.5rem;">
                            <input type="checkbox" @change="toggleSelectAll($event)" :checked="selectedRooms.length === tasks.length && tasks.length > 0">
                        </th>
                        <th><?php esc_html_e( 'Room', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Type', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Floor', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Priority', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Assigned To', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Task Type', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Notes', 'nozule' ); ?></th>
                        <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="task in tasks" :key="task.id">
                        <tr>
                            <td>
                                <input type="checkbox" :value="task.id" x-model="selectedRooms">
                            </td>
                            <td x-text="task.room_number"></td>
                            <td x-text="task.room_type_name || '—'"></td>
                            <td x-text="task.floor || '—'"></td>
                            <td>
                                <span class="nzl-badge"
                                      :class="{
                                          'nzl-badge-cancelled': task.status === 'dirty',
                                          'nzl-badge-confirmed': task.status === 'clean',
                                          'nzl-badge-checked_in': task.status === 'inspected',
                                          'nzl-badge-pending': task.status === 'out_of_order'
                                      }" x-text="statusLabel(task.status)"></span>
                            </td>
                            <td>
                                <span class="nzl-badge"
                                      :class="{
                                          'nzl-badge-confirmed': task.priority === 'low',
                                          'nzl-badge-checked_in': task.priority === 'normal',
                                          'nzl-badge-pending': task.priority === 'high',
                                          'nzl-badge-cancelled': task.priority === 'urgent'
                                      }" x-text="statusLabel(task.priority)"></span>
                            </td>
                            <td x-text="task.assigned_to_name || '—'"></td>
                            <td x-text="task.task_type ? statusLabel(task.task_type) : '—'"></td>
                            <td>
                                <span x-text="task.notes ? task.notes.substring(0, 40) + (task.notes.length > 40 ? '...' : '') : '—'" style="font-size:0.85em; color:#64748b;"></span>
                            </td>
                            <td>
                                <div style="display:flex; gap:0.25rem; flex-wrap:wrap;">
                                    <template x-if="task.status !== 'clean'">
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-success" @click="markClean(task.id)">
                                            <?php esc_html_e( 'Mark Clean', 'nozule' ); ?>
                                        </button>
                                    </template>
                                    <template x-if="task.status === 'clean'">
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-primary" @click="markInspected(task.id)">
                                            <?php esc_html_e( 'Mark Inspected', 'nozule' ); ?>
                                        </button>
                                    </template>
                                    <select class="nzl-input" style="width:auto; padding:0.25rem 0.5rem; font-size:0.8rem;" @change="assignTask(task.id, $event.target.value); $event.target.value = ''">
                                        <option value=""><?php esc_html_e( 'Assign', 'nozule' ); ?></option>
                                        <template x-for="staff in staffMembers" :key="staff.id">
                                            <option :value="staff.id" x-text="staff.name"></option>
                                        </template>
                                    </select>
                                    <button class="nzl-btn nzl-btn-sm" @click="editTask(task)">
                                        <?php esc_html_e( 'Edit', 'nozule' ); ?>
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </template>
                    <template x-if="tasks.length === 0">
                        <tr>
                            <td colspan="10" style="text-align:center; color:#94a3b8;">
                                <?php esc_html_e( 'No housekeeping tasks found.', 'nozule' ); ?>
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

    <!-- ======================= CREATE/EDIT TASK MODAL ======================= -->
    <template x-if="showTaskModal">
        <div class="nzl-modal-overlay" @click.self="showTaskModal = false">
            <div class="nzl-modal" style="max-width:600px;">
                <div class="nzl-modal-header">
                    <h3 x-text="editingTaskId ? '<?php echo esc_js( __( 'Edit Housekeeping Task', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Housekeeping Task', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showTaskModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Room', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model.number="taskForm.room_id">
                                <option value=""><?php esc_html_e( '-- Select Room --', 'nozule' ); ?></option>
                                <template x-for="room in rooms" :key="room.id">
                                    <option :value="room.id" x-text="room.room_number + ' — ' + (room.room_type_name || '')"></option>
                                </template>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Priority', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model="taskForm.priority">
                                <option value="low"><?php esc_html_e( 'Low', 'nozule' ); ?></option>
                                <option value="normal"><?php esc_html_e( 'Normal', 'nozule' ); ?></option>
                                <option value="high"><?php esc_html_e( 'High', 'nozule' ); ?></option>
                                <option value="urgent"><?php esc_html_e( 'Urgent', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Task Type', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select class="nzl-input" x-model="taskForm.task_type">
                                <option value=""><?php esc_html_e( '-- Select --', 'nozule' ); ?></option>
                                <option value="checkout_clean"><?php esc_html_e( 'Checkout Clean', 'nozule' ); ?></option>
                                <option value="stayover_clean"><?php esc_html_e( 'Stayover Clean', 'nozule' ); ?></option>
                                <option value="deep_clean"><?php esc_html_e( 'Deep Clean', 'nozule' ); ?></option>
                                <option value="touch_up"><?php esc_html_e( 'Touch Up', 'nozule' ); ?></option>
                                <option value="inspection"><?php esc_html_e( 'Inspection', 'nozule' ); ?></option>
                                <option value="maintenance"><?php esc_html_e( 'Maintenance', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Assigned To', 'nozule' ); ?></label>
                            <select class="nzl-input" x-model.number="taskForm.assigned_to">
                                <option value=""><?php esc_html_e( '-- Unassigned --', 'nozule' ); ?></option>
                                <template x-for="staff in staffMembers" :key="staff.id">
                                    <option :value="staff.id" x-text="staff.name"></option>
                                </template>
                            </select>
                        </div>
                    </div>
                    <div class="nzl-form-group" style="margin-top:0.75rem;">
                        <label><?php esc_html_e( 'Notes', 'nozule' ); ?></label>
                        <textarea class="nzl-input" rows="3" x-model="taskForm.notes" placeholder="<?php echo esc_attr__( 'Special instructions or notes...', 'nozule' ); ?>"></textarea>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showTaskModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveTask()" :disabled="saving">
                        <span x-show="!saving" x-text="editingTaskId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
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

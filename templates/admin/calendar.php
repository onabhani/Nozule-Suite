<?php
/**
 * Template: Admin Calendar
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlCalendarView">
    <div class="nzl-admin-header">
        <h1><?php esc_html_e( 'Availability Calendar', 'nozule' ); ?></h1>
    </div>

    <!-- Calendar navigation -->
    <div class="nzl-card" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem;">
        <button class="nzl-btn nzl-btn-sm" @click="prevPeriod()">
            <span class="nzl-nav-arrow">&larr;</span> <?php esc_html_e( 'Previous', 'nozule' ); ?>
        </button>
        <div style="display:flex; align-items:center; gap:1rem;">
            <h2 style="margin:0; font-size:1.125rem;" x-text="periodLabel"></h2>
            <select x-model="viewMode" @change="loadCalendar()" class="nzl-input" style="width:auto;">
                <option value="week"><?php esc_html_e( 'Week', 'nozule' ); ?></option>
                <option value="2week"><?php esc_html_e( '2 Weeks', 'nozule' ); ?></option>
                <option value="month"><?php esc_html_e( 'Month', 'nozule' ); ?></option>
            </select>
        </div>
        <button class="nzl-btn nzl-btn-sm" @click="nextPeriod()">
            <?php esc_html_e( 'Next', 'nozule' ); ?> <span class="nzl-nav-arrow">&rarr;</span>
        </button>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- Calendar grid -->
    <template x-if="!loading && rooms.length > 0">
        <div class="nzl-table-wrap" style="overflow-x:auto;">
            <table class="nzl-table nzl-calendar-table">
                <thead>
                    <tr>
                        <th class="nzl-calendar-room-col"><?php esc_html_e( 'Room', 'nozule' ); ?></th>
                        <template x-for="date in dates" :key="date">
                            <th class="nzl-calendar-date-col" :class="{'nzl-today': isToday(date)}">
                                <div x-text="formatDayName(date)" style="font-size:0.625rem; text-transform:uppercase;"></div>
                                <div x-text="formatDayNum(date)"></div>
                            </th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="room in rooms" :key="room.id">
                        <tr>
                            <td class="nzl-calendar-room-col">
                                <strong x-text="room.room_number"></strong>
                                <div style="font-size:0.75rem; color:#94a3b8;" x-text="room.room_type_name"></div>
                            </td>
                            <template x-for="date in dates" :key="room.id + '-' + date">
                                <td class="nzl-calendar-cell"
                                    :class="getCellClass(room.id, date)"
                                    @click="onCellClick(room.id, date)">
                                    <template x-if="getBookingForCell(room.id, date)">
                                        <div class="nzl-calendar-booking" :title="getBookingForCell(room.id, date).guest_name"
                                             x-text="getBookingLabel(room.id, date)"></div>
                                    </template>
                                </td>
                            </template>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
    </template>

    <!-- No rooms message -->
    <template x-if="!loading && rooms.length === 0">
        <div class="nzl-card" style="text-align:center; padding:3rem;">
            <p style="color:#64748b; margin:0 0 1rem;"><?php esc_html_e( 'No rooms configured yet. Add room types and rooms first.', 'nozule' ); ?></p>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=nzl-rooms' ) ); ?>" class="nzl-btn nzl-btn-primary">
                <?php esc_html_e( 'Go to Rooms', 'nozule' ); ?>
            </a>
        </div>
    </template>

    <!-- Legend -->
    <div class="nzl-calendar-legend" style="margin-top:1rem; display:flex; flex-wrap:wrap; gap:1rem 1.5rem; font-size:0.875rem; color:#64748b;">
        <span style="display:flex; align-items:center; gap:0.25rem;">
            <span style="display:inline-block; width:1rem; height:1rem; border-radius:0.25rem; background:#dcfce7;"></span>
            <?php esc_html_e( 'Available', 'nozule' ); ?>
        </span>
        <span style="display:flex; align-items:center; gap:0.25rem;">
            <span style="display:inline-block; width:1rem; height:1rem; border-radius:0.25rem; background:#dbeafe;"></span>
            <?php esc_html_e( 'Occupied', 'nozule' ); ?>
        </span>
        <span style="display:flex; align-items:center; gap:0.25rem;">
            <span style="display:inline-block; width:1rem; height:1rem; border-radius:0.25rem; background:#fef3c7;"></span>
            <?php esc_html_e( 'Pending', 'nozule' ); ?>
        </span>
        <span style="display:flex; align-items:center; gap:0.25rem;">
            <span style="display:inline-block; width:1rem; height:1rem; border-radius:0.25rem; background:#fecaca;"></span>
            <?php esc_html_e( 'Blocked', 'nozule' ); ?>
        </span>
    </div>
</div>

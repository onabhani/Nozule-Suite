<?php
/**
 * Template: Admin Calendar
 *
 * @package Venezia\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="vhm-admin-wrap" x-data="vhmCalendarView">
    <div class="vhm-admin-header">
        <h1><?php esc_html_e( 'Availability Calendar', 'venezia-hotel' ); ?></h1>
    </div>

    <!-- Calendar navigation -->
    <div class="vhm-card" style="display:flex; align-items:center; justify-content:space-between; margin-bottom:1rem;">
        <button class="vhm-btn vhm-btn-sm" @click="prevPeriod()">
            &larr; <?php esc_html_e( 'Previous', 'venezia-hotel' ); ?>
        </button>
        <div style="display:flex; align-items:center; gap:1rem;">
            <h2 style="margin:0; font-size:1.125rem;" x-text="periodLabel"></h2>
            <select x-model="viewMode" @change="loadCalendar()" class="vhm-input" style="width:auto;">
                <option value="week"><?php esc_html_e( 'Week', 'venezia-hotel' ); ?></option>
                <option value="2week"><?php esc_html_e( '2 Weeks', 'venezia-hotel' ); ?></option>
                <option value="month"><?php esc_html_e( 'Month', 'venezia-hotel' ); ?></option>
            </select>
        </div>
        <button class="vhm-btn vhm-btn-sm" @click="nextPeriod()">
            <?php esc_html_e( 'Next', 'venezia-hotel' ); ?> &rarr;
        </button>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="vhm-admin-loading"><div class="vhm-spinner vhm-spinner-lg"></div></div>
    </template>

    <!-- Calendar grid -->
    <template x-if="!loading">
        <div class="vhm-table-wrap" style="overflow-x:auto;">
            <table class="vhm-table vhm-calendar-table">
                <thead>
                    <tr>
                        <th class="vhm-calendar-room-col"><?php esc_html_e( 'Room', 'venezia-hotel' ); ?></th>
                        <template x-for="date in dates" :key="date">
                            <th class="vhm-calendar-date-col" :class="{'vhm-today': isToday(date)}">
                                <div x-text="formatDayName(date)" style="font-size:0.625rem; text-transform:uppercase;"></div>
                                <div x-text="formatDayNum(date)"></div>
                            </th>
                        </template>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="room in rooms" :key="room.id">
                        <tr>
                            <td class="vhm-calendar-room-col">
                                <strong x-text="room.room_number"></strong>
                                <div style="font-size:0.75rem; color:#94a3b8;" x-text="room.room_type_name"></div>
                            </td>
                            <template x-for="date in dates" :key="room.id + '-' + date">
                                <td class="vhm-calendar-cell"
                                    :class="getCellClass(room.id, date)"
                                    @click="onCellClick(room.id, date)">
                                    <template x-if="getBookingForCell(room.id, date)">
                                        <div class="vhm-calendar-booking" :title="getBookingForCell(room.id, date).guest_name"
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

    <!-- Legend -->
    <div style="margin-top:1rem; display:flex; gap:1.5rem; font-size:0.875rem; color:#64748b;">
        <span style="display:flex; align-items:center; gap:0.25rem;">
            <span style="display:inline-block; width:1rem; height:1rem; border-radius:0.25rem; background:#dcfce7;"></span>
            <?php esc_html_e( 'Available', 'venezia-hotel' ); ?>
        </span>
        <span style="display:flex; align-items:center; gap:0.25rem;">
            <span style="display:inline-block; width:1rem; height:1rem; border-radius:0.25rem; background:#dbeafe;"></span>
            <?php esc_html_e( 'Occupied', 'venezia-hotel' ); ?>
        </span>
        <span style="display:flex; align-items:center; gap:0.25rem;">
            <span style="display:inline-block; width:1rem; height:1rem; border-radius:0.25rem; background:#fef3c7;"></span>
            <?php esc_html_e( 'Pending', 'venezia-hotel' ); ?>
        </span>
        <span style="display:flex; align-items:center; gap:0.25rem;">
            <span style="display:inline-block; width:1rem; height:1rem; border-radius:0.25rem; background:#fecaca;"></span>
            <?php esc_html_e( 'Blocked', 'venezia-hotel' ); ?>
        </span>
    </div>
</div>

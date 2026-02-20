<?php
/**
 * Template: Admin Channel Sync
 *
 * Three-tab interface for OTA channel sync management:
 * 1. Connections — configure channel API credentials
 * 2. Rate Mapping — map local room types to channel IDs
 * 3. Sync Log — view sync operation history
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlChannelSync">
	<div class="nzl-admin-header">
		<h1><?php esc_html_e( 'Channel Sync', 'nozule' ); ?></h1>
	</div>

	<!-- Loading -->
	<template x-if="loading">
		<div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
	</template>

	<template x-if="!loading">
		<div>
			<!-- Tabs -->
			<div class="nzl-tabs" style="margin-bottom:1rem;">
				<button class="nzl-tab" :class="{'active': activeTab === 'connections'}" @click="activeTab = 'connections'">
					<?php esc_html_e( 'Connections', 'nozule' ); ?>
				</button>
				<button class="nzl-tab" :class="{'active': activeTab === 'rate_mapping'}" @click="switchToRateMapping()">
					<?php esc_html_e( 'Rate Mapping', 'nozule' ); ?>
				</button>
				<button class="nzl-tab" :class="{'active': activeTab === 'sync_log'}" @click="switchToSyncLog()">
					<?php esc_html_e( 'Sync Log', 'nozule' ); ?>
				</button>
			</div>

			<!-- ============================================================ -->
			<!-- TAB: Connections -->
			<!-- ============================================================ -->
			<template x-if="activeTab === 'connections'">
				<div>
					<!-- Booking.com Card -->
					<div class="nzl-card" style="margin-bottom:1.5rem;">
						<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
							<div style="display:flex; align-items:center; gap:0.75rem;">
								<h2 style="font-size:1.125rem; font-weight:600; margin:0;">Booking.com</h2>
								<template x-if="getConnection('booking_com')">
									<span class="nzl-badge"
										:class="getConnection('booking_com').is_active ? 'nzl-badge-confirmed' : 'nzl-badge-cancelled'"
										x-text="getConnection('booking_com').is_active ? '<?php echo esc_js( __( 'Active', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'nozule' ) ); ?>'">
									</span>
								</template>
								<template x-if="!getConnection('booking_com')">
									<span class="nzl-badge nzl-badge-cancelled"><?php esc_html_e( 'Not Configured', 'nozule' ); ?></span>
								</template>
							</div>
							<div style="display:flex; gap:0.5rem;">
								<template x-if="getConnection('booking_com') && getConnection('booking_com').is_active">
									<button class="nzl-btn nzl-btn-sm nzl-btn-primary"
										@click="triggerSync('booking_com')"
										:disabled="syncing">
										<span x-show="!syncing"><?php esc_html_e( 'Sync Now', 'nozule' ); ?></span>
										<span x-show="syncing"><?php esc_html_e( 'Syncing...', 'nozule' ); ?></span>
									</button>
								</template>
								<template x-if="getConnection('booking_com')">
									<button class="nzl-btn nzl-btn-sm"
										@click="testConnectionBtn('booking_com')"
										:disabled="testing">
										<span x-show="!testing"><?php esc_html_e( 'Test Connection', 'nozule' ); ?></span>
										<span x-show="testing"><?php esc_html_e( 'Testing...', 'nozule' ); ?></span>
									</button>
								</template>
							</div>
						</div>

						<!-- Last sync info -->
						<template x-if="getConnection('booking_com') && getConnection('booking_com').last_sync_at">
							<p style="font-size:0.875rem; color:#64748b; margin-bottom:1rem;">
								<?php esc_html_e( 'Last sync:', 'nozule' ); ?>
								<strong x-text="getConnection('booking_com').last_sync_at"></strong>
							</p>
						</template>

						<!-- Credentials Form -->
						<div class="nzl-form-grid">
							<div class="nzl-form-group">
								<label><?php esc_html_e( 'Hotel ID', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
								<input type="text" class="nzl-input"
									x-model="connectionForm.hotel_id"
									placeholder="<?php echo esc_attr__( 'e.g. 1234567', 'nozule' ); ?>">
							</div>
							<div class="nzl-form-group">
								<label><?php esc_html_e( 'Username', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
								<input type="text" class="nzl-input"
									x-model="connectionForm.username"
									placeholder="<?php echo esc_attr__( 'API username', 'nozule' ); ?>">
							</div>
							<div class="nzl-form-group">
								<label><?php esc_html_e( 'Password', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
								<input type="password" class="nzl-input"
									x-model="connectionForm.password"
									placeholder="<?php echo esc_attr__( 'API password', 'nozule' ); ?>">
							</div>
							<div class="nzl-form-group">
								<label><?php esc_html_e( 'API Endpoint (optional)', 'nozule' ); ?></label>
								<input type="url" class="nzl-input"
									x-model="connectionForm.api_endpoint"
									placeholder="<?php echo esc_attr__( 'Custom URL for sandbox', 'nozule' ); ?>">
							</div>
						</div>
						<div style="margin-top:0.75rem; display:flex; align-items:center; gap:1rem;">
							<label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
								<input type="checkbox" x-model="connectionForm.use_sandbox">
								<span><?php esc_html_e( 'Use Sandbox Environment', 'nozule' ); ?></span>
							</label>
							<label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
								<input type="checkbox" x-model="connectionForm.is_active">
								<span><?php esc_html_e( 'Active', 'nozule' ); ?></span>
							</label>
						</div>
						<div style="margin-top:1rem; display:flex; gap:0.5rem;">
							<button class="nzl-btn nzl-btn-primary" @click="saveConnectionForm('booking_com')" :disabled="savingConnection">
								<span x-show="!savingConnection"><?php esc_html_e( 'Save Connection', 'nozule' ); ?></span>
								<span x-show="savingConnection"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
							</button>
							<template x-if="getConnection('booking_com')">
								<button class="nzl-btn nzl-btn-danger nzl-btn-sm"
									@click="deleteConnectionBtn('booking_com')">
									<?php esc_html_e( 'Delete', 'nozule' ); ?>
								</button>
							</template>
						</div>
					</div>

					<!-- Empty state when no connections -->
					<template x-if="connections.length === 0">
						<div class="nzl-card" style="text-align:center; padding:2rem;">
							<p style="color:#64748b;"><?php esc_html_e( 'Configure your Booking.com credentials above to start syncing.', 'nozule' ); ?></p>
						</div>
					</template>
				</div>
			</template>

			<!-- ============================================================ -->
			<!-- TAB: Rate Mapping -->
			<!-- ============================================================ -->
			<template x-if="activeTab === 'rate_mapping'">
				<div>
					<div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
						<div class="nzl-form-group" style="margin-bottom:0;">
							<select class="nzl-input" x-model="selectedChannel" @change="loadRateMappings()" style="min-width:200px;">
								<option value=""><?php esc_html_e( '-- Select Channel --', 'nozule' ); ?></option>
								<template x-for="conn in connections" :key="conn.channel_name">
									<option :value="conn.channel_name" x-text="conn.channel_label || conn.channel_name"></option>
								</template>
							</select>
						</div>
						<button class="nzl-btn nzl-btn-primary nzl-btn-sm" @click="openMappingModal()" :disabled="!selectedChannel">
							<?php esc_html_e( 'Add Mapping', 'nozule' ); ?>
						</button>
					</div>

					<template x-if="!selectedChannel">
						<div class="nzl-card" style="text-align:center; padding:2rem;">
							<p style="color:#64748b;"><?php esc_html_e( 'Select a channel to view and manage rate mappings.', 'nozule' ); ?></p>
						</div>
					</template>

					<template x-if="selectedChannel && rateMappings.length === 0">
						<div class="nzl-card" style="text-align:center; padding:2rem;">
							<p style="color:#64748b;"><?php esc_html_e( 'No rate mappings configured for this channel.', 'nozule' ); ?></p>
							<button class="nzl-btn nzl-btn-primary" style="margin-top:1rem;" @click="openMappingModal()">
								<?php esc_html_e( 'Add Your First Mapping', 'nozule' ); ?>
							</button>
						</div>
					</template>

					<template x-if="selectedChannel && rateMappings.length > 0">
						<div class="nzl-card" style="overflow-x:auto;">
							<table class="nzl-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Local Room Type', 'nozule' ); ?></th>
										<th><?php esc_html_e( 'Local Rate Plan', 'nozule' ); ?></th>
										<th><?php esc_html_e( 'Channel Room ID', 'nozule' ); ?></th>
										<th><?php esc_html_e( 'Channel Rate ID', 'nozule' ); ?></th>
										<th><?php esc_html_e( 'Active', 'nozule' ); ?></th>
										<th style="text-align:right;"><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<template x-for="mapping in rateMappings" :key="mapping.id">
										<tr>
											<td x-text="getRoomTypeName(mapping.local_room_type_id)"></td>
											<td x-text="getRatePlanName(mapping.local_rate_plan_id)"></td>
											<td>
												<input type="text" class="nzl-input" style="max-width:150px;"
													:value="mapping.channel_room_id"
													@change="updateMappingField(mapping.id, 'channel_room_id', $event.target.value)">
											</td>
											<td>
												<input type="text" class="nzl-input" style="max-width:150px;"
													:value="mapping.channel_rate_id"
													@change="updateMappingField(mapping.id, 'channel_rate_id', $event.target.value)">
											</td>
											<td>
												<input type="checkbox"
													:checked="mapping.is_active"
													@change="updateMappingField(mapping.id, 'is_active', $event.target.checked ? 1 : 0)">
											</td>
											<td style="text-align:right;">
												<button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteMapping(mapping.id)">
													<?php esc_html_e( 'Delete', 'nozule' ); ?>
												</button>
											</td>
										</tr>
									</template>
								</tbody>
							</table>
						</div>
					</template>
				</div>
			</template>

			<!-- ============================================================ -->
			<!-- TAB: Sync Log -->
			<!-- ============================================================ -->
			<template x-if="activeTab === 'sync_log'">
				<div>
					<!-- Filters -->
					<div class="nzl-card" style="margin-bottom:1rem;">
						<div style="display:flex; flex-wrap:wrap; gap:0.75rem; align-items:flex-end;">
							<div class="nzl-form-group" style="margin-bottom:0;">
								<label style="font-size:0.75rem;"><?php esc_html_e( 'Channel', 'nozule' ); ?></label>
								<select class="nzl-input" x-model="logFilters.channel" @change="loadSyncLog()" style="min-width:150px;">
									<option value=""><?php esc_html_e( 'All', 'nozule' ); ?></option>
									<template x-for="conn in connections" :key="conn.channel_name">
										<option :value="conn.channel_name" x-text="conn.channel_label || conn.channel_name"></option>
									</template>
								</select>
							</div>
							<div class="nzl-form-group" style="margin-bottom:0;">
								<label style="font-size:0.75rem;"><?php esc_html_e( 'Direction', 'nozule' ); ?></label>
								<select class="nzl-input" x-model="logFilters.direction" @change="loadSyncLog()" style="min-width:120px;">
									<option value=""><?php esc_html_e( 'All', 'nozule' ); ?></option>
									<option value="push"><?php esc_html_e( 'Push', 'nozule' ); ?></option>
									<option value="pull"><?php esc_html_e( 'Pull', 'nozule' ); ?></option>
								</select>
							</div>
							<div class="nzl-form-group" style="margin-bottom:0;">
								<label style="font-size:0.75rem;"><?php esc_html_e( 'Status', 'nozule' ); ?></label>
								<select class="nzl-input" x-model="logFilters.status" @change="loadSyncLog()" style="min-width:120px;">
									<option value=""><?php esc_html_e( 'All', 'nozule' ); ?></option>
									<option value="success"><?php esc_html_e( 'Success', 'nozule' ); ?></option>
									<option value="partial"><?php esc_html_e( 'Partial', 'nozule' ); ?></option>
									<option value="failed"><?php esc_html_e( 'Failed', 'nozule' ); ?></option>
									<option value="pending"><?php esc_html_e( 'Pending', 'nozule' ); ?></option>
								</select>
							</div>
							<button class="nzl-btn nzl-btn-sm" @click="resetLogFilters()">
								<?php esc_html_e( 'Reset', 'nozule' ); ?>
							</button>
						</div>
					</div>

					<!-- Log Table -->
					<template x-if="syncLogs.length === 0">
						<div class="nzl-card" style="text-align:center; padding:2rem;">
							<p style="color:#64748b;"><?php esc_html_e( 'No sync log entries found.', 'nozule' ); ?></p>
						</div>
					</template>

					<template x-if="syncLogs.length > 0">
						<div class="nzl-card" style="overflow-x:auto;">
							<table class="nzl-table">
								<thead>
									<tr>
										<th><?php esc_html_e( 'Channel', 'nozule' ); ?></th>
										<th><?php esc_html_e( 'Direction', 'nozule' ); ?></th>
										<th><?php esc_html_e( 'Type', 'nozule' ); ?></th>
										<th><?php esc_html_e( 'Status', 'nozule' ); ?></th>
										<th><?php esc_html_e( 'Records', 'nozule' ); ?></th>
										<th><?php esc_html_e( 'Started', 'nozule' ); ?></th>
										<th><?php esc_html_e( 'Completed', 'nozule' ); ?></th>
										<th><?php esc_html_e( 'Error', 'nozule' ); ?></th>
									</tr>
								</thead>
								<tbody>
									<template x-for="log in syncLogs" :key="log.id">
										<tr>
											<td x-text="log.channel_name"></td>
											<td>
												<span class="nzl-badge"
													:class="log.direction === 'push' ? 'nzl-badge-info' : 'nzl-badge-warning'"
													x-text="log.direction">
												</span>
											</td>
											<td x-text="log.sync_type"></td>
											<td>
												<span class="nzl-badge"
													:class="{
														'nzl-badge-confirmed': log.status === 'success',
														'nzl-badge-warning': log.status === 'partial',
														'nzl-badge-cancelled': log.status === 'failed',
														'nzl-badge-info': log.status === 'pending'
													}"
													x-text="log.status">
												</span>
											</td>
											<td x-text="log.records_processed"></td>
											<td x-text="log.started_at || '-'"></td>
											<td x-text="log.completed_at || '-'"></td>
											<td style="max-width:200px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
												:title="log.error_message"
												x-text="log.error_message || '-'">
											</td>
										</tr>
									</template>
								</tbody>
							</table>

							<!-- Pagination -->
							<template x-if="logPages > 1">
								<div style="display:flex; justify-content:center; gap:0.5rem; margin-top:1rem;">
									<button class="nzl-btn nzl-btn-sm" @click="logPage > 1 && goToLogPage(logPage - 1)" :disabled="logPage <= 1">
										&laquo; <?php esc_html_e( 'Prev', 'nozule' ); ?>
									</button>
									<span style="display:flex; align-items:center; font-size:0.875rem; color:#64748b;">
										<?php esc_html_e( 'Page', 'nozule' ); ?> <span x-text="logPage" style="margin:0 0.25rem;"></span> <?php esc_html_e( 'of', 'nozule' ); ?> <span x-text="logPages" style="margin-left:0.25rem;"></span>
									</span>
									<button class="nzl-btn nzl-btn-sm" @click="logPage < logPages && goToLogPage(logPage + 1)" :disabled="logPage >= logPages">
										<?php esc_html_e( 'Next', 'nozule' ); ?> &raquo;
									</button>
								</div>
							</template>
						</div>
					</template>
				</div>
			</template>
		</div>
	</template>

	<!-- ============================================================ -->
	<!-- Add Rate Mapping Modal -->
	<!-- ============================================================ -->
	<template x-if="showMappingModal">
		<div class="nzl-modal-overlay" @click.self="showMappingModal = false">
			<div class="nzl-modal" style="max-width:540px;">
				<div class="nzl-modal-header">
					<h2><?php esc_html_e( 'Add Rate Mapping', 'nozule' ); ?></h2>
					<button class="nzl-modal-close" @click="showMappingModal = false">&times;</button>
				</div>
				<div class="nzl-modal-body">
					<div class="nzl-form-grid">
						<div class="nzl-form-group">
							<label><?php esc_html_e( 'Local Room Type', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
							<select class="nzl-input" x-model="mappingForm.local_room_type_id">
								<option value=""><?php esc_html_e( '-- Select Room Type --', 'nozule' ); ?></option>
								<template x-for="rt in roomTypes" :key="rt.id">
									<option :value="rt.id" x-text="rt.name"></option>
								</template>
							</select>
						</div>
						<div class="nzl-form-group">
							<label><?php esc_html_e( 'Local Rate Plan', 'nozule' ); ?></label>
							<select class="nzl-input" x-model="mappingForm.local_rate_plan_id">
								<option value="0"><?php esc_html_e( 'Base Rate', 'nozule' ); ?></option>
								<template x-for="rp in ratePlans" :key="rp.id">
									<option :value="rp.id" x-text="rp.name"></option>
								</template>
							</select>
						</div>
						<div class="nzl-form-group">
							<label><?php esc_html_e( 'Channel Room ID', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
							<input type="text" class="nzl-input" x-model="mappingForm.channel_room_id"
								placeholder="<?php echo esc_attr__( 'Room ID on OTA', 'nozule' ); ?>">
						</div>
						<div class="nzl-form-group">
							<label><?php esc_html_e( 'Channel Rate ID', 'nozule' ); ?></label>
							<input type="text" class="nzl-input" x-model="mappingForm.channel_rate_id"
								placeholder="<?php echo esc_attr__( 'Rate plan ID on OTA', 'nozule' ); ?>">
						</div>
					</div>
				</div>
				<div class="nzl-modal-footer">
					<button class="nzl-btn" @click="showMappingModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
					<button class="nzl-btn nzl-btn-primary" @click="saveMappingForm()" :disabled="savingMapping">
						<span x-show="!savingMapping"><?php esc_html_e( 'Save', 'nozule' ); ?></span>
						<span x-show="savingMapping"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
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

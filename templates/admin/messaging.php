<?php
/**
 * Admin template: Guest Messaging — Email Templates & Log (NZL-007)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap nzl-admin" x-data="nzlMessaging">

	<!-- Header -->
	<div class="flex items-center justify-between mb-6">
		<div>
			<h1 class="text-2xl font-bold text-gray-800">
				<?php esc_html_e( 'Guest Messaging', 'nozule' ); ?>
			</h1>
			<p class="text-sm text-gray-500 mt-1">
				<?php esc_html_e( 'Manage email templates and view sent messages.', 'nozule' ); ?>
			</p>
		</div>
	</div>

	<!-- Tabs -->
	<div class="flex border-b mb-6">
		<button @click="switchTab('templates')" class="px-4 py-2 -mb-px text-sm font-medium border-b-2 transition-colors"
			:class="activeTab === 'templates' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
			<?php esc_html_e( 'Email Templates', 'nozule' ); ?>
		</button>
		<button @click="switchTab('log')" class="px-4 py-2 -mb-px text-sm font-medium border-b-2 transition-colors"
			:class="activeTab === 'log' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
			<?php esc_html_e( 'Email Log', 'nozule' ); ?>
		</button>
	</div>

	<!-- ═══ Templates Tab ═══ -->
	<div x-show="activeTab === 'templates'">
		<div class="flex justify-end mb-4">
			<button @click="openTemplateModal()" class="nzl-btn nzl-btn-primary">
				+ <?php esc_html_e( 'New Template', 'nozule' ); ?>
			</button>
		</div>

		<div class="bg-white rounded-lg shadow-sm border overflow-hidden">
			<template x-if="loading">
				<div class="flex justify-center py-12">
					<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
				</div>
			</template>

			<template x-if="!loading && templates.length === 0">
				<div class="text-center py-12 text-gray-500"><?php esc_html_e( 'No email templates found.', 'nozule' ); ?></div>
			</template>

			<template x-if="!loading && templates.length > 0">
				<table class="min-w-full divide-y divide-gray-200">
					<thead class="bg-gray-50">
						<tr>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Name', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Trigger', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Subject', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Status', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-end text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-200">
						<template x-for="tpl in templates" :key="tpl.id">
							<tr class="hover:bg-gray-50">
								<td class="px-4 py-3 text-sm font-medium" x-text="tpl.name"></td>
								<td class="px-4 py-3 text-sm">
									<span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded" x-text="tpl.trigger_event || '—'"></span>
								</td>
								<td class="px-4 py-3 text-sm text-gray-600" x-text="tpl.subject"></td>
								<td class="px-4 py-3">
									<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
										:class="tpl.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
										x-text="tpl.is_active ? '<?php echo esc_js( __( 'Active', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'nozule' ) ); ?>'">
									</span>
								</td>
								<td class="px-4 py-3 text-end space-x-2">
									<button @click="editTemplate(tpl)" class="text-blue-600 hover:text-blue-800 text-sm"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
									<button @click="sendTestEmail(tpl.id)" class="text-green-600 hover:text-green-800 text-sm"><?php esc_html_e( 'Test', 'nozule' ); ?></button>
									<button @click="deleteTemplate(tpl.id)" class="text-red-600 hover:text-red-800 text-sm"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
								</td>
							</tr>
						</template>
					</tbody>
				</table>
			</template>
		</div>
	</div>

	<!-- ═══ Email Log Tab ═══ -->
	<div x-show="activeTab === 'log'">
		<div class="bg-white rounded-lg shadow-sm border p-4 mb-4">
			<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
				<div>
					<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Status', 'nozule' ); ?></label>
					<select x-model="logFilters.status" @change="logCurrentPage=1; loadEmailLog()" class="nzl-input w-full">
						<option value=""><?php esc_html_e( 'All', 'nozule' ); ?></option>
						<option value="sent"><?php esc_html_e( 'Sent', 'nozule' ); ?></option>
						<option value="failed"><?php esc_html_e( 'Failed', 'nozule' ); ?></option>
						<option value="queued"><?php esc_html_e( 'Queued', 'nozule' ); ?></option>
					</select>
				</div>
				<div>
					<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Search', 'nozule' ); ?></label>
					<input type="text" x-model="logFilters.search" @input.debounce.400ms="logCurrentPage=1; loadEmailLog()" class="nzl-input w-full" placeholder="<?php esc_attr_e( 'Email or subject...', 'nozule' ); ?>">
				</div>
			</div>
		</div>

		<div class="bg-white rounded-lg shadow-sm border overflow-hidden">
			<template x-if="loadingLog">
				<div class="flex justify-center py-12">
					<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
				</div>
			</template>

			<template x-if="!loadingLog && emailLogs.length === 0">
				<div class="text-center py-12 text-gray-500"><?php esc_html_e( 'No emails sent yet.', 'nozule' ); ?></div>
			</template>

			<template x-if="!loadingLog && emailLogs.length > 0">
				<div>
					<table class="min-w-full divide-y divide-gray-200">
						<thead class="bg-gray-50">
							<tr>
								<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'To', 'nozule' ); ?></th>
								<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Subject', 'nozule' ); ?></th>
								<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Status', 'nozule' ); ?></th>
								<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Sent At', 'nozule' ); ?></th>
							</tr>
						</thead>
						<tbody class="divide-y divide-gray-200">
							<template x-for="log in emailLogs" :key="log.id">
								<tr class="hover:bg-gray-50">
									<td class="px-4 py-3 text-sm" dir="ltr" x-text="log.to_email"></td>
									<td class="px-4 py-3 text-sm text-gray-600" x-text="log.subject"></td>
									<td class="px-4 py-3">
										<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
											:class="{
												'bg-green-100 text-green-800': log.status === 'sent',
												'bg-red-100 text-red-800': log.status === 'failed',
												'bg-yellow-100 text-yellow-800': log.status === 'queued'
											}" x-text="statusLabel(log.status)">
										</span>
									</td>
									<td class="px-4 py-3 text-sm text-gray-500" dir="ltr" x-text="log.sent_at || log.created_at"></td>
								</tr>
							</template>
						</tbody>
					</table>
					<!-- Log Pagination -->
					<div class="flex items-center justify-between px-4 py-3 border-t" x-show="logTotalPages > 1">
						<button @click="logPrevPage()" :disabled="logCurrentPage <= 1" class="nzl-btn nzl-btn-sm" :class="logCurrentPage <= 1 ? 'opacity-50' : ''">
							<?php esc_html_e( 'Previous', 'nozule' ); ?>
						</button>
						<span class="text-sm text-gray-600" x-text="'<?php echo esc_js( __( 'Page', 'nozule' ) ); ?> ' + logCurrentPage + ' / ' + logTotalPages"></span>
						<button @click="logNextPage()" :disabled="logCurrentPage >= logTotalPages" class="nzl-btn nzl-btn-sm" :class="logCurrentPage >= logTotalPages ? 'opacity-50' : ''">
							<?php esc_html_e( 'Next', 'nozule' ); ?>
						</button>
					</div>
				</div>
			</template>
		</div>
	</div>

	<!-- ═══ Template Modal ═══ -->
	<div x-show="showTemplateModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" x-transition>
		<div class="flex items-center justify-center min-h-screen px-4">
			<div class="fixed inset-0 bg-black bg-opacity-50" @click="showTemplateModal = false"></div>
			<div class="relative bg-white rounded-lg shadow-xl w-full max-w-3xl max-h-[90vh] overflow-y-auto">
				<div class="px-6 py-4 border-b flex items-center justify-between">
					<h3 class="text-lg font-semibold" x-text="editingTemplateId ? '<?php echo esc_js( __( 'Edit Template', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'New Template', 'nozule' ) ); ?>'"></h3>
					<button @click="showTemplateModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
				</div>
				<div class="px-6 py-4 space-y-4">
					<!-- Name & Slug -->
					<div class="grid grid-cols-2 gap-4">
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Name *', 'nozule' ); ?></label>
							<input type="text" x-model="templateForm.name" class="nzl-input w-full">
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Slug *', 'nozule' ); ?></label>
							<input type="text" x-model="templateForm.slug" class="nzl-input w-full font-mono" dir="ltr" :disabled="!!editingTemplateId">
						</div>
					</div>
					<!-- Trigger -->
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Trigger Event', 'nozule' ); ?></label>
						<select x-model="templateForm.trigger_event" class="nzl-input w-full">
							<option value=""><?php esc_html_e( 'Manual Only', 'nozule' ); ?></option>
							<option value="booking_confirmed"><?php esc_html_e( 'Booking Confirmed', 'nozule' ); ?></option>
							<option value="pre_arrival"><?php esc_html_e( 'Pre-Arrival (1 day before)', 'nozule' ); ?></option>
							<option value="booking_checked_in"><?php esc_html_e( 'Guest Checked In', 'nozule' ); ?></option>
							<option value="booking_checked_out"><?php esc_html_e( 'Guest Checked Out', 'nozule' ); ?></option>
						</select>
					</div>
					<!-- Subject bilingual -->
					<div class="grid grid-cols-2 gap-4">
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Subject (English) *', 'nozule' ); ?></label>
							<input type="text" x-model="templateForm.subject" class="nzl-input w-full" dir="ltr">
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Subject (Arabic)', 'nozule' ); ?></label>
							<input type="text" x-model="templateForm.subject_ar" class="nzl-input w-full" dir="rtl">
						</div>
					</div>
					<!-- Body English -->
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Body (English) *', 'nozule' ); ?></label>
						<textarea x-model="templateForm.body" class="nzl-input w-full" dir="ltr" rows="6"></textarea>
						<p class="text-xs text-gray-400 mt-1">
							<?php esc_html_e( 'Variables: {{guest_name}}, {{booking_number}}, {{check_in}}, {{check_out}}, {{room_type}}, {{room_number}}, {{total_amount}}, {{currency}}, {{hotel_name}}, {{hotel_phone}}', 'nozule' ); ?>
						</p>
					</div>
					<!-- Body Arabic -->
					<div>
						<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Body (Arabic)', 'nozule' ); ?></label>
						<textarea x-model="templateForm.body_ar" class="nzl-input w-full" dir="rtl" rows="6"></textarea>
					</div>
					<!-- Active -->
					<div class="flex items-center gap-2">
						<input type="checkbox" x-model="templateForm.is_active" id="tpl_active" class="rounded">
						<label for="tpl_active" class="text-sm font-medium text-gray-700"><?php esc_html_e( 'Active', 'nozule' ); ?></label>
					</div>
				</div>
				<div class="px-6 py-4 border-t flex justify-end gap-3">
					<button @click="showTemplateModal = false" class="nzl-btn"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
					<button @click="saveTemplate()" :disabled="saving" class="nzl-btn nzl-btn-primary">
						<span x-show="!saving" x-text="editingTemplateId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
						<span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

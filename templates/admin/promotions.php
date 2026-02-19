<?php
/**
 * Admin template: Promo Codes / Discounts (NZL-006)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap nzl-admin" x-data="nzlPromotions">

	<!-- Header -->
	<div class="flex items-center justify-between mb-6">
		<div>
			<h1 class="text-2xl font-bold text-gray-800">
				<?php esc_html_e( 'Promo Codes', 'nozule' ); ?>
			</h1>
			<p class="text-sm text-gray-500 mt-1">
				<?php esc_html_e( 'Manage discount codes and promotions for bookings.', 'nozule' ); ?>
			</p>
		</div>
		<button @click="openModal()" class="nzl-btn nzl-btn-primary">
			+ <?php esc_html_e( 'New Promo Code', 'nozule' ); ?>
		</button>
	</div>

	<!-- Filters -->
	<div class="bg-white rounded-lg shadow-sm border p-4 mb-4">
		<div class="grid grid-cols-1 md:grid-cols-3 gap-4">
			<div>
				<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Status', 'nozule' ); ?></label>
				<select x-model="filters.status" @change="currentPage=1; loadPromoCodes()" class="nzl-input w-full">
					<option value=""><?php esc_html_e( 'All', 'nozule' ); ?></option>
					<option value="active"><?php esc_html_e( 'Active', 'nozule' ); ?></option>
					<option value="inactive"><?php esc_html_e( 'Inactive', 'nozule' ); ?></option>
					<option value="expired"><?php esc_html_e( 'Expired', 'nozule' ); ?></option>
				</select>
			</div>
			<div>
				<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Search', 'nozule' ); ?></label>
				<input type="text" x-model="filters.search" @input.debounce.400ms="currentPage=1; loadPromoCodes()" class="nzl-input w-full" placeholder="<?php esc_attr_e( 'Code or name...', 'nozule' ); ?>">
			</div>
		</div>
	</div>

	<!-- Table -->
	<div class="bg-white rounded-lg shadow-sm border overflow-hidden">
		<template x-if="loading">
			<div class="flex justify-center py-12">
				<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
			</div>
		</template>

		<template x-if="!loading && promoCodes.length === 0">
			<div class="text-center py-12 text-gray-500">
				<?php esc_html_e( 'No promo codes found.', 'nozule' ); ?>
			</div>
		</template>

		<template x-if="!loading && promoCodes.length > 0">
			<div class="overflow-x-auto">
				<table class="min-w-full divide-y divide-gray-200">
					<thead class="bg-gray-50">
						<tr>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Code', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Name', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Discount', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Validity', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Usage', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Status', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-end text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-200">
						<template x-for="promo in promoCodes" :key="promo.id">
							<tr class="hover:bg-gray-50">
								<td class="px-4 py-3">
									<span class="font-mono text-sm font-semibold bg-gray-100 px-2 py-1 rounded" x-text="promo.code"></span>
								</td>
								<td class="px-4 py-3">
									<div class="text-sm font-medium text-gray-900" x-text="promo.name"></div>
									<div class="text-xs text-gray-500" dir="rtl" x-show="promo.name_ar" x-text="promo.name_ar"></div>
								</td>
								<td class="px-4 py-3 text-sm">
									<span x-text="promo.discount_type === 'percentage' ? promo.discount_value + '%' : formatPrice(promo.discount_value)"></span>
								</td>
								<td class="px-4 py-3 text-sm text-gray-600">
									<template x-if="promo.valid_from || promo.valid_to">
										<span x-text="(promo.valid_from || '∞') + ' → ' + (promo.valid_to || '∞')"></span>
									</template>
									<template x-if="!promo.valid_from && !promo.valid_to">
										<span class="text-gray-400"><?php esc_html_e( 'Always', 'nozule' ); ?></span>
									</template>
								</td>
								<td class="px-4 py-3 text-sm">
									<span x-text="promo.used_count + ' / ' + (promo.max_uses || '∞')"></span>
								</td>
								<td class="px-4 py-3">
									<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
										:class="promo.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
										x-text="promo.is_active ? '<?php echo esc_js( __( 'Active', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'nozule' ) ); ?>'">
									</span>
								</td>
								<td class="px-4 py-3 text-end">
									<button @click="editPromo(promo)" class="text-blue-600 hover:text-blue-800 text-sm mr-2"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
									<button @click="deletePromo(promo.id)" class="text-red-600 hover:text-red-800 text-sm"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
								</td>
							</tr>
						</template>
					</tbody>
				</table>
			</div>
		</template>

		<!-- Pagination -->
		<div class="flex items-center justify-between px-4 py-3 border-t" x-show="totalPages > 1">
			<button @click="prevPage()" :disabled="currentPage <= 1" class="nzl-btn nzl-btn-sm" :class="currentPage <= 1 ? 'opacity-50' : ''">
				<?php esc_html_e( 'Previous', 'nozule' ); ?>
			</button>
			<span class="text-sm text-gray-600" x-text="'<?php echo esc_js( __( 'Page', 'nozule' ) ); ?> ' + currentPage + ' / ' + totalPages"></span>
			<button @click="nextPage()" :disabled="currentPage >= totalPages" class="nzl-btn nzl-btn-sm" :class="currentPage >= totalPages ? 'opacity-50' : ''">
				<?php esc_html_e( 'Next', 'nozule' ); ?>
			</button>
		</div>
	</div>

	<!-- Create/Edit Modal -->
	<div x-show="showModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" x-transition>
		<div class="flex items-center justify-center min-h-screen px-4">
			<div class="fixed inset-0 bg-black bg-opacity-50" @click="showModal = false"></div>
			<div class="relative bg-white rounded-lg shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">
				<div class="px-6 py-4 border-b flex items-center justify-between">
					<h3 class="text-lg font-semibold" x-text="editingId ? '<?php echo esc_js( __( 'Edit Promo Code', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'New Promo Code', 'nozule' ) ); ?>'"></h3>
					<button @click="showModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
				</div>
				<div class="px-6 py-4 space-y-4">
					<!-- Code -->
					<div class="grid grid-cols-2 gap-4">
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Code *', 'nozule' ); ?></label>
							<input type="text" x-model="form.code" class="nzl-input w-full font-mono uppercase" dir="ltr" placeholder="SUMMER2026" :disabled="!!editingId">
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Discount Type *', 'nozule' ); ?></label>
							<select x-model="form.discount_type" class="nzl-input w-full">
								<option value="percentage"><?php esc_html_e( 'Percentage (%)', 'nozule' ); ?></option>
								<option value="fixed"><?php esc_html_e( 'Fixed Amount', 'nozule' ); ?></option>
							</select>
						</div>
					</div>
					<!-- Name bilingual -->
					<div class="grid grid-cols-2 gap-4">
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Name (English) *', 'nozule' ); ?></label>
							<input type="text" x-model="form.name" class="nzl-input w-full" dir="ltr">
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Name (Arabic)', 'nozule' ); ?></label>
							<input type="text" x-model="form.name_ar" class="nzl-input w-full" dir="rtl">
						</div>
					</div>
					<!-- Discount value + currency -->
					<div class="grid grid-cols-2 gap-4">
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Discount Value *', 'nozule' ); ?></label>
							<input type="number" x-model="form.discount_value" class="nzl-input w-full" dir="ltr" min="0" step="0.01">
						</div>
						<div x-show="form.discount_type === 'fixed'">
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Currency', 'nozule' ); ?></label>
							<select x-model="form.currency_code" class="nzl-input w-full">
								<option value="SYP"><?php esc_html_e( 'SYP', 'nozule' ); ?></option>
								<option value="USD"><?php esc_html_e( 'USD', 'nozule' ); ?></option>
								<option value="EUR"><?php esc_html_e( 'EUR', 'nozule' ); ?></option>
								<option value="SAR"><?php esc_html_e( 'SAR', 'nozule' ); ?></option>
							</select>
						</div>
						<div x-show="form.discount_type === 'percentage'">
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Max Discount Cap', 'nozule' ); ?></label>
							<input type="number" x-model="form.max_discount" class="nzl-input w-full" dir="ltr" min="0" step="0.01" placeholder="<?php esc_attr_e( 'No limit', 'nozule' ); ?>">
						</div>
					</div>
					<!-- Validity dates -->
					<div class="grid grid-cols-2 gap-4">
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Valid From', 'nozule' ); ?></label>
							<input type="date" x-model="form.valid_from" class="nzl-input w-full" dir="ltr">
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Valid To', 'nozule' ); ?></label>
							<input type="date" x-model="form.valid_to" class="nzl-input w-full" dir="ltr">
						</div>
					</div>
					<!-- Usage limits -->
					<div class="grid grid-cols-3 gap-4">
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Max Uses', 'nozule' ); ?></label>
							<input type="number" x-model="form.max_uses" class="nzl-input w-full" dir="ltr" min="0" placeholder="<?php esc_attr_e( 'Unlimited', 'nozule' ); ?>">
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Per Guest Limit', 'nozule' ); ?></label>
							<input type="number" x-model="form.per_guest_limit" class="nzl-input w-full" dir="ltr" min="0" placeholder="<?php esc_attr_e( 'Unlimited', 'nozule' ); ?>">
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Min Nights', 'nozule' ); ?></label>
							<input type="number" x-model="form.min_nights" class="nzl-input w-full" dir="ltr" min="0" placeholder="<?php esc_attr_e( 'None', 'nozule' ); ?>">
						</div>
					</div>
					<!-- Description bilingual -->
					<div class="grid grid-cols-2 gap-4">
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Description (English)', 'nozule' ); ?></label>
							<textarea x-model="form.description" class="nzl-input w-full" dir="ltr" rows="2"></textarea>
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Description (Arabic)', 'nozule' ); ?></label>
							<textarea x-model="form.description_ar" class="nzl-input w-full" dir="rtl" rows="2"></textarea>
						</div>
					</div>
					<!-- Active toggle -->
					<div class="flex items-center gap-2">
						<input type="checkbox" x-model="form.is_active" id="promo_active" class="rounded">
						<label for="promo_active" class="text-sm font-medium text-gray-700"><?php esc_html_e( 'Active', 'nozule' ); ?></label>
					</div>
				</div>
				<div class="px-6 py-4 border-t flex justify-end gap-3">
					<button @click="showModal = false" class="nzl-btn"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
					<button @click="savePromo()" :disabled="saving" class="nzl-btn nzl-btn-primary">
						<span x-show="!saving" x-text="editingId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
						<span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

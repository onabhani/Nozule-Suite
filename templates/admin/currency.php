<?php
/**
 * Admin template: Multi-Currency & Exchange Rates (NZL-008)
 */
if ( ! defined( 'ABSPATH' ) ) exit;
?>
<div class="wrap nzl-admin" x-data="nzlCurrency">

	<!-- Header -->
	<div class="flex items-center justify-between mb-6">
		<div>
			<h1 class="text-2xl font-bold text-gray-800">
				<?php esc_html_e( 'Currency & Exchange Rates', 'nozule' ); ?>
			</h1>
			<p class="text-sm text-gray-500 mt-1">
				<?php esc_html_e( 'Manage currencies, exchange rates, and pricing rules.', 'nozule' ); ?>
			</p>
		</div>
	</div>

	<!-- Tabs -->
	<div class="flex border-b mb-6">
		<button @click="switchTab('currencies')" class="px-4 py-2 -mb-px text-sm font-medium border-b-2 transition-colors"
			:class="activeTab === 'currencies' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
			<?php esc_html_e( 'Currencies', 'nozule' ); ?>
		</button>
		<button @click="switchTab('rates')" class="px-4 py-2 -mb-px text-sm font-medium border-b-2 transition-colors"
			:class="activeTab === 'rates' ? 'border-blue-600 text-blue-600' : 'border-transparent text-gray-500 hover:text-gray-700'">
			<?php esc_html_e( 'Exchange Rates', 'nozule' ); ?>
		</button>
	</div>

	<!-- ═══ Currencies Tab ═══ -->
	<div x-show="activeTab === 'currencies'">
		<div class="flex justify-end mb-4">
			<button @click="openCurrencyModal()" class="nzl-btn nzl-btn-primary">
				+ <?php esc_html_e( 'Add Currency', 'nozule' ); ?>
			</button>
		</div>

		<div class="bg-white rounded-lg shadow-sm border overflow-hidden">
			<template x-if="loading">
				<div class="flex justify-center py-12">
					<div class="animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div>
				</div>
			</template>

			<template x-if="!loading && currencies.length === 0">
				<div class="text-center py-12 text-gray-500"><?php esc_html_e( 'No currencies configured.', 'nozule' ); ?></div>
			</template>

			<template x-if="!loading && currencies.length > 0">
				<table class="min-w-full divide-y divide-gray-200">
					<thead class="bg-gray-50">
						<tr>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Code', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Name', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Symbol', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Exchange Rate', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Status', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-end text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-200">
						<template x-for="cur in currencies" :key="cur.id">
							<tr class="hover:bg-gray-50">
								<td class="px-4 py-3">
									<span class="font-mono text-sm font-semibold" x-text="cur.code"></span>
									<span x-show="cur.is_default" class="ml-1 text-xs bg-blue-100 text-blue-800 px-1.5 py-0.5 rounded"><?php esc_html_e( 'Default', 'nozule' ); ?></span>
								</td>
								<td class="px-4 py-3">
									<div class="text-sm" x-text="cur.name"></div>
									<div class="text-xs text-gray-500" dir="rtl" x-show="cur.name_ar" x-text="cur.name_ar"></div>
								</td>
								<td class="px-4 py-3 text-sm">
									<span x-text="cur.symbol"></span>
									<span x-show="cur.symbol_ar && cur.symbol_ar !== cur.symbol" class="text-gray-400 mx-1">/</span>
									<span x-show="cur.symbol_ar && cur.symbol_ar !== cur.symbol" dir="rtl" x-text="cur.symbol_ar"></span>
								</td>
								<td class="px-4 py-3 text-sm font-mono" dir="ltr" x-text="cur.exchange_rate"></td>
								<td class="px-4 py-3">
									<span class="inline-flex px-2 py-0.5 rounded-full text-xs font-medium"
										:class="cur.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600'"
										x-text="cur.is_active ? '<?php echo esc_js( __( 'Active', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'nozule' ) ); ?>'">
									</span>
								</td>
								<td class="px-4 py-3 text-end space-x-2">
									<button @click="editCurrency(cur)" class="text-blue-600 hover:text-blue-800 text-sm"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
									<button x-show="!cur.is_default" @click="setDefault(cur.id)" class="text-green-600 hover:text-green-800 text-sm"><?php esc_html_e( 'Set Default', 'nozule' ); ?></button>
									<button x-show="!cur.is_default" @click="deleteCurrency(cur.id)" class="text-red-600 hover:text-red-800 text-sm"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
								</td>
							</tr>
						</template>
					</tbody>
				</table>
			</template>
		</div>

		<!-- Syrian/Non-Syrian Pricing Info -->
		<div class="mt-6 bg-blue-50 border border-blue-200 rounded-lg p-4">
			<h3 class="text-sm font-semibold text-blue-800 mb-2"><?php esc_html_e( 'Syrian / Non-Syrian Pricing', 'nozule' ); ?></h3>
			<p class="text-sm text-blue-700">
				<?php esc_html_e( 'To set different prices for Syrian and non-Syrian guests, go to Rates & Pricing and create separate rate plans with the "Guest Type" field set to "Syrian" or "Non-Syrian". The system will automatically apply the correct rate based on guest nationality.', 'nozule' ); ?>
			</p>
		</div>
	</div>

	<!-- ═══ Exchange Rates Tab ═══ -->
	<div x-show="activeTab === 'rates'">
		<div class="bg-white rounded-lg shadow-sm border p-6 mb-4">
			<h3 class="text-lg font-semibold mb-4"><?php esc_html_e( 'Update Exchange Rate', 'nozule' ); ?></h3>
			<div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
				<div>
					<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'From', 'nozule' ); ?></label>
					<select x-model="rateForm.from_currency" class="nzl-input w-full">
						<template x-for="cur in currencies" :key="cur.code">
							<option :value="cur.code" x-text="cur.code + ' - ' + cur.name"></option>
						</template>
					</select>
				</div>
				<div>
					<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'To', 'nozule' ); ?></label>
					<select x-model="rateForm.to_currency" class="nzl-input w-full">
						<template x-for="cur in currencies" :key="cur.code">
							<option :value="cur.code" x-text="cur.code + ' - ' + cur.name"></option>
						</template>
					</select>
				</div>
				<div>
					<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Rate', 'nozule' ); ?></label>
					<input type="number" x-model="rateForm.rate" class="nzl-input w-full" dir="ltr" step="0.000001" min="0">
				</div>
				<div>
					<button @click="saveExchangeRate()" :disabled="savingRate" class="nzl-btn nzl-btn-primary w-full">
						<span x-show="!savingRate"><?php esc_html_e( 'Save Rate', 'nozule' ); ?></span>
						<span x-show="savingRate"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
					</button>
				</div>
			</div>
		</div>

		<!-- Currency Converter -->
		<div class="bg-white rounded-lg shadow-sm border p-6 mb-4">
			<h3 class="text-lg font-semibold mb-4"><?php esc_html_e( 'Quick Converter', 'nozule' ); ?></h3>
			<div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
				<div>
					<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Amount', 'nozule' ); ?></label>
					<input type="number" x-model="convertForm.amount" class="nzl-input w-full" dir="ltr" min="0" step="0.01">
				</div>
				<div>
					<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'From', 'nozule' ); ?></label>
					<select x-model="convertForm.from" class="nzl-input w-full">
						<template x-for="cur in currencies" :key="'cf'+cur.code">
							<option :value="cur.code" x-text="cur.code"></option>
						</template>
					</select>
				</div>
				<div>
					<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'To', 'nozule' ); ?></label>
					<select x-model="convertForm.to" class="nzl-input w-full">
						<template x-for="cur in currencies" :key="'ct'+cur.code">
							<option :value="cur.code" x-text="cur.code"></option>
						</template>
					</select>
				</div>
				<div>
					<button @click="convertCurrency()" class="nzl-btn nzl-btn-primary w-full"><?php esc_html_e( 'Convert', 'nozule' ); ?></button>
				</div>
			</div>
			<div x-show="convertResult !== null" class="mt-4 p-3 bg-green-50 border border-green-200 rounded text-center">
				<span class="text-lg font-semibold text-green-800" x-text="convertResult"></span>
			</div>
		</div>

		<!-- Rate History -->
		<div class="bg-white rounded-lg shadow-sm border overflow-hidden">
			<div class="px-4 py-3 border-b">
				<h3 class="text-sm font-semibold text-gray-700"><?php esc_html_e( 'Recent Exchange Rate Updates', 'nozule' ); ?></h3>
			</div>
			<template x-if="exchangeRates.length === 0">
				<div class="text-center py-8 text-gray-500"><?php esc_html_e( 'No exchange rate history.', 'nozule' ); ?></div>
			</template>
			<template x-if="exchangeRates.length > 0">
				<table class="min-w-full divide-y divide-gray-200">
					<thead class="bg-gray-50">
						<tr>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'From', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'To', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Rate', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Source', 'nozule' ); ?></th>
							<th class="px-4 py-3 text-start text-xs font-medium text-gray-500 uppercase"><?php esc_html_e( 'Date', 'nozule' ); ?></th>
						</tr>
					</thead>
					<tbody class="divide-y divide-gray-200">
						<template x-for="rate in exchangeRates" :key="rate.id">
							<tr class="hover:bg-gray-50">
								<td class="px-4 py-3 text-sm font-mono" x-text="rate.from_currency"></td>
								<td class="px-4 py-3 text-sm font-mono" x-text="rate.to_currency"></td>
								<td class="px-4 py-3 text-sm font-mono" dir="ltr" x-text="rate.rate"></td>
								<td class="px-4 py-3 text-sm text-gray-500" x-text="rate.source"></td>
								<td class="px-4 py-3 text-sm text-gray-500" dir="ltr" x-text="rate.effective_date"></td>
							</tr>
						</template>
					</tbody>
				</table>
			</template>
		</div>
	</div>

	<!-- ═══ Currency Modal ═══ -->
	<div x-show="showCurrencyModal" x-cloak class="fixed inset-0 z-50 overflow-y-auto" x-transition>
		<div class="flex items-center justify-center min-h-screen px-4">
			<div class="fixed inset-0 bg-black bg-opacity-50" @click="showCurrencyModal = false"></div>
			<div class="relative bg-white rounded-lg shadow-xl w-full max-w-lg max-h-[90vh] overflow-y-auto">
				<div class="px-6 py-4 border-b flex items-center justify-between">
					<h3 class="text-lg font-semibold" x-text="editingCurrencyId ? '<?php echo esc_js( __( 'Edit Currency', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Currency', 'nozule' ) ); ?>'"></h3>
					<button @click="showCurrencyModal = false" class="text-gray-400 hover:text-gray-600">&times;</button>
				</div>
				<div class="px-6 py-4 space-y-4">
					<div class="grid grid-cols-2 gap-4">
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Code *', 'nozule' ); ?></label>
							<input type="text" x-model="currencyForm.code" class="nzl-input w-full font-mono uppercase" dir="ltr" maxlength="3" :disabled="!!editingCurrencyId">
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Decimal Places', 'nozule' ); ?></label>
							<input type="number" x-model="currencyForm.decimal_places" class="nzl-input w-full" dir="ltr" min="0" max="4">
						</div>
					</div>
					<div class="grid grid-cols-2 gap-4">
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Name (English) *', 'nozule' ); ?></label>
							<input type="text" x-model="currencyForm.name" class="nzl-input w-full" dir="ltr">
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Name (Arabic)', 'nozule' ); ?></label>
							<input type="text" x-model="currencyForm.name_ar" class="nzl-input w-full" dir="rtl">
						</div>
					</div>
					<div class="grid grid-cols-2 gap-4">
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Symbol *', 'nozule' ); ?></label>
							<input type="text" x-model="currencyForm.symbol" class="nzl-input w-full" dir="ltr" maxlength="10">
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Symbol (Arabic)', 'nozule' ); ?></label>
							<input type="text" x-model="currencyForm.symbol_ar" class="nzl-input w-full" dir="rtl" maxlength="10">
						</div>
					</div>
					<div class="grid grid-cols-2 gap-4">
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Exchange Rate *', 'nozule' ); ?></label>
							<input type="number" x-model="currencyForm.exchange_rate" class="nzl-input w-full" dir="ltr" step="0.000001" min="0">
						</div>
						<div>
							<label class="block text-sm font-medium text-gray-700 mb-1"><?php esc_html_e( 'Sort Order', 'nozule' ); ?></label>
							<input type="number" x-model="currencyForm.sort_order" class="nzl-input w-full" dir="ltr" min="0">
						</div>
					</div>
					<div class="flex items-center gap-2">
						<input type="checkbox" x-model="currencyForm.is_active" id="cur_active" class="rounded">
						<label for="cur_active" class="text-sm font-medium text-gray-700"><?php esc_html_e( 'Active', 'nozule' ); ?></label>
					</div>
				</div>
				<div class="px-6 py-4 border-t flex justify-end gap-3">
					<button @click="showCurrencyModal = false" class="nzl-btn"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
					<button @click="saveCurrency()" :disabled="saving" class="nzl-btn nzl-btn-primary">
						<span x-show="!saving" x-text="editingCurrencyId ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
						<span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
					</button>
				</div>
			</div>
		</div>
	</div>
</div>

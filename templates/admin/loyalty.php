<?php
/**
 * Template: Admin Loyalty Program (NZL-036)
 *
 * Tabs: Members, Tiers, Rewards, Dashboard
 *
 * @package Nozule\Admin
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}
?>
<div class="nzl-admin-wrap" x-data="nzlLoyalty">
    <div class="nzl-admin-header">
        <div>
            <h1><?php esc_html_e( 'Loyalty Program', 'nozule' ); ?></h1>
            <p style="font-size:0.875rem; color:#64748b; margin:0.25rem 0 0;"><?php esc_html_e( 'Manage tiers, members, points, and rewards.', 'nozule' ); ?></p>
        </div>
        <div style="display:flex; gap:0.5rem;">
            <template x-if="activeTab === 'members'">
                <button class="nzl-btn nzl-btn-primary" @click="openEnrollModal()">
                    + <?php esc_html_e( 'Enroll Guest', 'nozule' ); ?>
                </button>
            </template>
            <template x-if="activeTab === 'tiers'">
                <button class="nzl-btn nzl-btn-primary" @click="openTierModal()">
                    + <?php esc_html_e( 'Add Tier', 'nozule' ); ?>
                </button>
            </template>
            <template x-if="activeTab === 'rewards'">
                <button class="nzl-btn nzl-btn-primary" @click="openRewardModal()">
                    + <?php esc_html_e( 'Add Reward', 'nozule' ); ?>
                </button>
            </template>
        </div>
    </div>

    <!-- Tabs -->
    <div class="nzl-tabs" style="margin-bottom:1rem;">
        <button class="nzl-tab" :class="{'active': activeTab === 'members'}" @click="switchTab('members')">
            <?php esc_html_e( 'Members', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'tiers'}" @click="switchTab('tiers')">
            <?php esc_html_e( 'Tiers', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'rewards'}" @click="switchTab('rewards')">
            <?php esc_html_e( 'Rewards', 'nozule' ); ?>
        </button>
        <button class="nzl-tab" :class="{'active': activeTab === 'dashboard'}" @click="switchTab('dashboard')">
            <?php esc_html_e( 'Dashboard', 'nozule' ); ?>
        </button>
    </div>

    <!-- Loading -->
    <template x-if="loading">
        <div class="nzl-admin-loading"><div class="nzl-spinner nzl-spinner-lg"></div></div>
    </template>

    <!-- ======================= MEMBERS TAB ======================= -->
    <template x-if="!loading && activeTab === 'members'">
        <div>
            <!-- Search & Filters -->
            <div class="nzl-card" style="margin-bottom:1rem;">
                <div style="display:flex; flex-wrap:wrap; gap:1rem; align-items:flex-end;">
                    <div style="flex:1; min-width:180px;">
                        <label class="nzl-label"><?php esc_html_e( 'Search', 'nozule' ); ?></label>
                        <input type="text" x-model="memberFilters.search"
                               @input.debounce.400ms="memberPage=1; loadMembers()"
                               class="nzl-input"
                               placeholder="<?php esc_attr_e( 'Guest name or email...', 'nozule' ); ?>">
                    </div>
                    <div>
                        <label class="nzl-label"><?php esc_html_e( 'Tier', 'nozule' ); ?></label>
                        <select x-model="memberFilters.tier_id" @change="memberPage=1; loadMembers()" class="nzl-input">
                            <option value=""><?php esc_html_e( 'All Tiers', 'nozule' ); ?></option>
                            <template x-for="tier in tiers" :key="tier.id">
                                <option :value="tier.id" x-text="tier.name"></option>
                            </template>
                        </select>
                    </div>
                </div>
            </div>

            <!-- Members Table -->
            <div class="nzl-table-wrap">
                <table class="nzl-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Guest Name', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Tier', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Points Balance', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Lifetime Points', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Joined', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="member in members" :key="member.id">
                            <tr>
                                <td>
                                    <a href="#" class="nzl-link" @click.prevent="viewMember(member.id)"
                                       x-text="member.first_name + ' ' + member.last_name"></a>
                                    <div style="font-size:0.8rem; color:#94a3b8;" x-text="member.email"></div>
                                </td>
                                <td>
                                    <span style="display:inline-flex; align-items:center; gap:0.35rem; padding:0.15rem 0.5rem; border-radius:9999px; font-size:0.8rem; font-weight:600; border:2px solid;"
                                          :style="'border-color:' + (member.tier_color || '#94a3b8') + '; color:' + (member.tier_color || '#94a3b8')">
                                        <span style="width:8px; height:8px; border-radius:50%; display:inline-block;"
                                              :style="'background:' + (member.tier_color || '#94a3b8')"></span>
                                        <span x-text="member.tier_name || '—'"></span>
                                    </span>
                                </td>
                                <td style="font-weight:600;" x-text="member.points_balance.toLocaleString()"></td>
                                <td x-text="member.lifetime_points.toLocaleString()"></td>
                                <td style="font-size:0.875rem; color:#64748b;" x-text="formatDate(member.enrolled_at)"></td>
                                <td>
                                    <button class="nzl-btn nzl-btn-sm" @click="viewMember(member.id)">
                                        <?php esc_html_e( 'View', 'nozule' ); ?>
                                    </button>
                                </td>
                            </tr>
                        </template>
                        <template x-if="members.length === 0">
                            <tr>
                                <td colspan="6" style="text-align:center; color:#94a3b8;">
                                    <?php esc_html_e( 'No loyalty members found.', 'nozule' ); ?>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <template x-if="memberTotalPages > 1">
                <div style="display:flex; justify-content:space-between; align-items:center; margin-top:1rem;">
                    <span style="font-size:0.875rem; color:#64748b;">
                        <?php esc_html_e( 'Page', 'nozule' ); ?> <span x-text="memberPage"></span>
                        <?php esc_html_e( 'of', 'nozule' ); ?> <span x-text="memberTotalPages"></span>
                    </span>
                    <div style="display:flex; gap:0.5rem;">
                        <button class="nzl-btn nzl-btn-sm" @click="prevMemberPage()" :disabled="memberPage <= 1"><?php esc_html_e( 'Previous', 'nozule' ); ?></button>
                        <button class="nzl-btn nzl-btn-sm" @click="nextMemberPage()" :disabled="memberPage >= memberTotalPages"><?php esc_html_e( 'Next', 'nozule' ); ?></button>
                    </div>
                </div>
            </template>
        </div>
    </template>

    <!-- ======================= TIERS TAB ======================= -->
    <template x-if="!loading && activeTab === 'tiers'">
        <div>
            <div class="nzl-table-wrap">
                <table class="nzl-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Name (AR)', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Min Points', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Discount %', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Benefits', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="tier in tiers" :key="tier.id">
                            <tr>
                                <td>
                                    <span style="display:inline-flex; align-items:center; gap:0.35rem;">
                                        <span style="width:12px; height:12px; border-radius:50%; display:inline-block;"
                                              :style="'background:' + (tier.color || '#94a3b8')"></span>
                                        <span style="font-weight:600;" x-text="tier.name"></span>
                                    </span>
                                </td>
                                <td dir="rtl" x-text="tier.name_ar || '—'"></td>
                                <td x-text="tier.min_points.toLocaleString()"></td>
                                <td x-text="tier.discount_percent + '%'"></td>
                                <td style="font-size:0.875rem; color:#64748b; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;"
                                    x-text="tier.benefits || '—'"></td>
                                <td>
                                    <div style="display:flex; gap:0.25rem;">
                                        <button class="nzl-btn nzl-btn-sm" @click="editTier(tier)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteTier(tier.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="tiers.length === 0">
                            <tr>
                                <td colspan="6" style="text-align:center; color:#94a3b8;">
                                    <?php esc_html_e( 'No tiers configured. Add your first tier to get started.', 'nozule' ); ?>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>

    <!-- ======================= REWARDS TAB ======================= -->
    <template x-if="!loading && activeTab === 'rewards'">
        <div>
            <div class="nzl-table-wrap">
                <table class="nzl-table">
                    <thead>
                        <tr>
                            <th><?php esc_html_e( 'Name', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Name (AR)', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Points Cost', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Type', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Value', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Active', 'nozule' ); ?></th>
                            <th><?php esc_html_e( 'Actions', 'nozule' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="reward in rewards" :key="reward.id">
                            <tr>
                                <td style="font-weight:600;" x-text="reward.name"></td>
                                <td dir="rtl" x-text="reward.name_ar || '—'"></td>
                                <td x-text="reward.points_cost.toLocaleString()"></td>
                                <td>
                                    <span class="nzl-badge" x-text="rewardTypeLabel(reward.type)"></span>
                                </td>
                                <td x-text="reward.value || '—'"></td>
                                <td>
                                    <span class="nzl-badge"
                                          :class="reward.is_active ? 'nzl-badge-confirmed' : 'nzl-badge-pending'"
                                          x-text="reward.is_active ? '<?php echo esc_js( __( 'Active', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Inactive', 'nozule' ) ); ?>'">
                                    </span>
                                </td>
                                <td>
                                    <div style="display:flex; gap:0.25rem;">
                                        <button class="nzl-btn nzl-btn-sm" @click="editReward(reward)"><?php esc_html_e( 'Edit', 'nozule' ); ?></button>
                                        <button class="nzl-btn nzl-btn-sm nzl-btn-danger" @click="deleteReward(reward.id)"><?php esc_html_e( 'Delete', 'nozule' ); ?></button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="rewards.length === 0">
                            <tr>
                                <td colspan="7" style="text-align:center; color:#94a3b8;">
                                    <?php esc_html_e( 'No rewards configured yet.', 'nozule' ); ?>
                                </td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>

    <!-- ======================= DASHBOARD TAB ======================= -->
    <template x-if="!loading && activeTab === 'dashboard'">
        <div>
            <div class="nzl-stats-grid" style="margin-bottom:1.5rem;">
                <div class="nzl-stat-card">
                    <div class="nzl-stat-value" x-text="stats.total_members.toLocaleString()"></div>
                    <div class="nzl-stat-label"><?php esc_html_e( 'Total Members', 'nozule' ); ?></div>
                </div>
                <div class="nzl-stat-card">
                    <div class="nzl-stat-value" x-text="stats.points_issued.toLocaleString()"></div>
                    <div class="nzl-stat-label"><?php esc_html_e( 'Points Issued', 'nozule' ); ?></div>
                </div>
                <div class="nzl-stat-card">
                    <div class="nzl-stat-value" x-text="stats.rewards_redeemed.toLocaleString()"></div>
                    <div class="nzl-stat-label"><?php esc_html_e( 'Rewards Redeemed', 'nozule' ); ?></div>
                </div>
                <div class="nzl-stat-card">
                    <div class="nzl-stat-value" x-text="stats.active_this_month.toLocaleString()"></div>
                    <div class="nzl-stat-label"><?php esc_html_e( 'Active This Month', 'nozule' ); ?></div>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= ENROLL GUEST MODAL ======================= -->
    <template x-if="showEnrollModal">
        <div class="nzl-modal-overlay" @click.self="showEnrollModal = false">
            <div class="nzl-modal" style="max-width:480px;">
                <div class="nzl-modal-header">
                    <h3><?php esc_html_e( 'Enroll Guest in Loyalty Program', 'nozule' ); ?></h3>
                    <button class="nzl-modal-close" @click="showEnrollModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Search Guest', 'nozule' ); ?></label>
                        <input type="text" x-model="guestSearch"
                               @input.debounce.400ms="searchGuests()"
                               class="nzl-input"
                               placeholder="<?php esc_attr_e( 'Type guest name or email...', 'nozule' ); ?>">
                    </div>
                    <template x-if="guestSearchResults.length > 0">
                        <div style="max-height:200px; overflow-y:auto; border:1px solid #e2e8f0; border-radius:0.5rem; margin-top:0.5rem;">
                            <template x-for="guest in guestSearchResults" :key="guest.id">
                                <div style="padding:0.5rem 0.75rem; cursor:pointer; display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #f1f5f9;"
                                     :style="enrollGuestId === guest.id ? 'background:#eff6ff;' : ''"
                                     @click="selectGuestForEnroll(guest)">
                                    <div>
                                        <div style="font-weight:600; font-size:0.875rem;" x-text="guest.first_name + ' ' + guest.last_name"></div>
                                        <div style="font-size:0.8rem; color:#94a3b8;" x-text="guest.email"></div>
                                    </div>
                                    <template x-if="enrollGuestId === guest.id">
                                        <span style="color:#3b82f6; font-size:1.1rem;">&#10003;</span>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="guestSearch && guestSearchResults.length === 0 && !searchingGuests">
                        <p style="color:#94a3b8; font-size:0.875rem; margin-top:0.5rem;"><?php esc_html_e( 'No guests found.', 'nozule' ); ?></p>
                    </template>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showEnrollModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="enrollGuest()" :disabled="!enrollGuestId || saving">
                        <span x-show="!saving"><?php esc_html_e( 'Enroll', 'nozule' ); ?></span>
                        <span x-show="saving"><?php esc_html_e( 'Enrolling...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= MEMBER DETAIL MODAL ======================= -->
    <template x-if="showMemberModal">
        <div class="nzl-modal-overlay" @click.self="showMemberModal = false">
            <div class="nzl-modal" style="max-width:780px; max-height:90vh; overflow-y:auto;">
                <div class="nzl-modal-header">
                    <h3><?php esc_html_e( 'Member Details', 'nozule' ); ?></h3>
                    <button class="nzl-modal-close" @click="showMemberModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <!-- Loading -->
                    <template x-if="loadingMember">
                        <div style="text-align:center; padding:2rem;"><div class="nzl-spinner nzl-spinner-lg"></div></div>
                    </template>

                    <template x-if="!loadingMember && selectedMember">
                        <div>
                            <!-- Member Info -->
                            <div style="display:grid; grid-template-columns:1fr 1fr; gap:1rem; margin-bottom:1.5rem;">
                                <div>
                                    <h4 style="font-size:1.25rem; font-weight:700; margin:0 0 0.5rem 0;"
                                        x-text="selectedMember.first_name + ' ' + selectedMember.last_name"></h4>
                                    <div style="font-size:0.875rem; color:#64748b; display:flex; flex-direction:column; gap:0.35rem;">
                                        <span><strong><?php esc_html_e( 'Email', 'nozule' ); ?>:</strong> <span x-text="selectedMember.email"></span></span>
                                        <span><strong><?php esc_html_e( 'Phone', 'nozule' ); ?>:</strong> <span x-text="selectedMember.phone || '—'" dir="ltr"></span></span>
                                        <span><strong><?php esc_html_e( 'Enrolled', 'nozule' ); ?>:</strong> <span x-text="formatDate(selectedMember.enrolled_at)"></span></span>
                                    </div>
                                </div>
                                <div>
                                    <!-- Tier Badge -->
                                    <div style="margin-bottom:0.75rem;">
                                        <span style="display:inline-flex; align-items:center; gap:0.35rem; padding:0.25rem 0.75rem; border-radius:9999px; font-size:0.9rem; font-weight:700; border:2px solid;"
                                              :style="'border-color:' + (selectedMember.tier_color || '#94a3b8') + '; color:' + (selectedMember.tier_color || '#94a3b8')">
                                            <span style="width:10px; height:10px; border-radius:50%; display:inline-block;"
                                                  :style="'background:' + (selectedMember.tier_color || '#94a3b8')"></span>
                                            <span x-text="selectedMember.tier_name || '—'"></span>
                                        </span>
                                    </div>
                                    <div class="nzl-stats-grid" style="grid-template-columns:1fr 1fr;">
                                        <div class="nzl-stat-card" style="padding:0.75rem;">
                                            <div class="nzl-stat-value" style="font-size:1.25rem;" x-text="selectedMember.points_balance.toLocaleString()"></div>
                                            <div class="nzl-stat-label"><?php esc_html_e( 'Balance', 'nozule' ); ?></div>
                                        </div>
                                        <div class="nzl-stat-card" style="padding:0.75rem;">
                                            <div class="nzl-stat-value" style="font-size:1.25rem;" x-text="selectedMember.lifetime_points.toLocaleString()"></div>
                                            <div class="nzl-stat-label"><?php esc_html_e( 'Lifetime', 'nozule' ); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div style="display:flex; gap:0.5rem; margin-bottom:1.5rem;">
                                <button class="nzl-btn nzl-btn-primary nzl-btn-sm" @click="openAdjustModal()">
                                    <?php esc_html_e( 'Adjust Points', 'nozule' ); ?>
                                </button>
                                <button class="nzl-btn nzl-btn-sm" @click="openRedeemModal()">
                                    <?php esc_html_e( 'Redeem Reward', 'nozule' ); ?>
                                </button>
                            </div>

                            <!-- Transaction History -->
                            <h4 style="font-size:0.95rem; font-weight:600; color:#1e293b; margin:0 0 0.75rem 0; border-bottom:1px solid #e2e8f0; padding-bottom:0.5rem;">
                                <?php esc_html_e( 'Transaction History', 'nozule' ); ?>
                            </h4>
                            <template x-if="selectedMember.transactions && selectedMember.transactions.length > 0">
                                <div class="nzl-table-wrap">
                                    <table class="nzl-table">
                                        <thead>
                                            <tr>
                                                <th><?php esc_html_e( 'Date', 'nozule' ); ?></th>
                                                <th><?php esc_html_e( 'Type', 'nozule' ); ?></th>
                                                <th><?php esc_html_e( 'Points', 'nozule' ); ?></th>
                                                <th><?php esc_html_e( 'Balance After', 'nozule' ); ?></th>
                                                <th><?php esc_html_e( 'Description', 'nozule' ); ?></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="tx in selectedMember.transactions" :key="tx.id">
                                                <tr>
                                                    <td style="font-size:0.875rem; color:#64748b;" x-text="formatDate(tx.created_at)"></td>
                                                    <td>
                                                        <span class="nzl-badge"
                                                              :class="tx.type === 'earn' ? 'nzl-badge-confirmed' : (tx.type === 'redeem' ? 'nzl-badge-cancelled' : 'nzl-badge-pending')"
                                                              x-text="txTypeLabel(tx.type)">
                                                        </span>
                                                    </td>
                                                    <td style="font-weight:600;"
                                                        :style="tx.points > 0 ? 'color:#16a34a;' : 'color:#dc2626;'"
                                                        x-text="(tx.points > 0 ? '+' : '') + tx.points.toLocaleString()"></td>
                                                    <td x-text="tx.balance_after.toLocaleString()"></td>
                                                    <td style="font-size:0.875rem; color:#64748b; max-width:250px;" x-text="tx.description || '—'"></td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>
                            </template>
                            <template x-if="!selectedMember.transactions || selectedMember.transactions.length === 0">
                                <p style="text-align:center; color:#94a3b8; font-size:0.875rem; padding:1rem 0;">
                                    <?php esc_html_e( 'No transactions yet.', 'nozule' ); ?>
                                </p>
                            </template>
                        </div>
                    </template>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showMemberModal = false"><?php esc_html_e( 'Close', 'nozule' ); ?></button>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= ADJUST POINTS MODAL ======================= -->
    <template x-if="showAdjustModal">
        <div class="nzl-modal-overlay" @click.self="showAdjustModal = false">
            <div class="nzl-modal" style="max-width:480px;">
                <div class="nzl-modal-header">
                    <h3><?php esc_html_e( 'Adjust Points', 'nozule' ); ?></h3>
                    <button class="nzl-modal-close" @click="showAdjustModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <p style="font-size:0.875rem; color:#64748b; margin:0 0 1rem 0;">
                        <?php esc_html_e( 'Current balance:', 'nozule' ); ?>
                        <strong x-text="selectedMember ? selectedMember.points_balance.toLocaleString() : 0"></strong>
                        <?php esc_html_e( 'points', 'nozule' ); ?>
                    </p>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Points (use negative to deduct)', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                        <input type="number" x-model="adjustForm.points" class="nzl-input" dir="ltr" placeholder="100">
                    </div>
                    <div class="nzl-form-group">
                        <label><?php esc_html_e( 'Reason', 'nozule' ); ?></label>
                        <textarea x-model="adjustForm.description" class="nzl-input" rows="2"
                                  placeholder="<?php esc_attr_e( 'Reason for adjustment...', 'nozule' ); ?>"></textarea>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showAdjustModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="submitAdjust()" :disabled="!adjustForm.points || saving">
                        <span x-show="!saving"><?php esc_html_e( 'Adjust', 'nozule' ); ?></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= REDEEM REWARD MODAL ======================= -->
    <template x-if="showRedeemModal">
        <div class="nzl-modal-overlay" @click.self="showRedeemModal = false">
            <div class="nzl-modal" style="max-width:520px;">
                <div class="nzl-modal-header">
                    <h3><?php esc_html_e( 'Redeem Reward', 'nozule' ); ?></h3>
                    <button class="nzl-modal-close" @click="showRedeemModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <p style="font-size:0.875rem; color:#64748b; margin:0 0 1rem 0;">
                        <?php esc_html_e( 'Available points:', 'nozule' ); ?>
                        <strong x-text="selectedMember ? selectedMember.points_balance.toLocaleString() : 0"></strong>
                    </p>
                    <template x-if="rewards.length > 0">
                        <div style="display:flex; flex-direction:column; gap:0.5rem;">
                            <template x-for="reward in rewards" :key="reward.id">
                                <div style="border:1px solid #e2e8f0; border-radius:0.5rem; padding:0.75rem; cursor:pointer; display:flex; justify-content:space-between; align-items:center;"
                                     :style="redeemRewardId === reward.id ? 'border-color:#3b82f6; background:#eff6ff;' : ''"
                                     :class="{'opacity-50': !reward.is_active || (selectedMember && selectedMember.points_balance < reward.points_cost)}"
                                     @click="reward.is_active && selectedMember && selectedMember.points_balance >= reward.points_cost ? redeemRewardId = reward.id : null">
                                    <div>
                                        <div style="font-weight:600;" x-text="reward.name"></div>
                                        <div style="font-size:0.8rem; color:#94a3b8;" x-text="reward.description || rewardTypeLabel(reward.type)"></div>
                                    </div>
                                    <div style="text-align:right;">
                                        <div style="font-weight:700; font-size:0.9rem;" x-text="reward.points_cost.toLocaleString() + ' pts'"></div>
                                        <template x-if="selectedMember && selectedMember.points_balance < reward.points_cost">
                                            <span style="font-size:0.75rem; color:#dc2626;"><?php esc_html_e( 'Insufficient', 'nozule' ); ?></span>
                                        </template>
                                    </div>
                                </div>
                            </template>
                        </div>
                    </template>
                    <template x-if="rewards.length === 0">
                        <p style="text-align:center; color:#94a3b8; font-size:0.875rem; padding:1rem 0;">
                            <?php esc_html_e( 'No rewards available.', 'nozule' ); ?>
                        </p>
                    </template>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showRedeemModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="submitRedeem()" :disabled="!redeemRewardId || saving">
                        <span x-show="!saving"><?php esc_html_e( 'Redeem', 'nozule' ); ?></span>
                        <span x-show="saving"><?php esc_html_e( 'Redeeming...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= TIER MODAL ======================= -->
    <template x-if="showTierModal">
        <div class="nzl-modal-overlay" @click.self="showTierModal = false">
            <div class="nzl-modal" style="max-width:580px;">
                <div class="nzl-modal-header">
                    <h3 x-text="tierForm.id ? '<?php echo esc_js( __( 'Edit Tier', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Tier', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showTierModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name (English)', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" x-model="tierForm.name" class="nzl-input" dir="ltr" placeholder="Bronze">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name (Arabic)', 'nozule' ); ?></label>
                            <input type="text" x-model="tierForm.name_ar" class="nzl-input" dir="rtl">
                        </div>
                    </div>
                    <div class="nzl-form-grid" style="grid-template-columns:1fr 1fr 1fr;">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Min Points', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" x-model="tierForm.min_points" class="nzl-input" dir="ltr" min="0" placeholder="0">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Discount %', 'nozule' ); ?></label>
                            <input type="number" x-model="tierForm.discount_percent" class="nzl-input" dir="ltr" min="0" max="100" step="0.5" placeholder="0">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Color', 'nozule' ); ?></label>
                            <input type="color" x-model="tierForm.color" class="nzl-input" style="height:38px; padding:2px;">
                        </div>
                    </div>
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Benefits (English)', 'nozule' ); ?></label>
                            <textarea x-model="tierForm.benefits" class="nzl-input" rows="2" dir="ltr"
                                      placeholder="<?php esc_attr_e( 'Early check-in, late checkout...', 'nozule' ); ?>"></textarea>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Benefits (Arabic)', 'nozule' ); ?></label>
                            <textarea x-model="tierForm.benefits_ar" class="nzl-input" rows="2" dir="rtl"></textarea>
                        </div>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showTierModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveTier()" :disabled="saving">
                        <span x-show="!saving" x-text="tierForm.id ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
                        <span x-show="saving"><?php esc_html_e( 'Saving...', 'nozule' ); ?></span>
                    </button>
                </div>
            </div>
        </div>
    </template>

    <!-- ======================= REWARD MODAL ======================= -->
    <template x-if="showRewardModal">
        <div class="nzl-modal-overlay" @click.self="showRewardModal = false">
            <div class="nzl-modal" style="max-width:580px;">
                <div class="nzl-modal-header">
                    <h3 x-text="rewardForm.id ? '<?php echo esc_js( __( 'Edit Reward', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Add Reward', 'nozule' ) ); ?>'"></h3>
                    <button class="nzl-modal-close" @click="showRewardModal = false">&times;</button>
                </div>
                <div class="nzl-modal-body">
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name (English)', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="text" x-model="rewardForm.name" class="nzl-input" dir="ltr" placeholder="Free Night Stay">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Name (Arabic)', 'nozule' ); ?></label>
                            <input type="text" x-model="rewardForm.name_ar" class="nzl-input" dir="rtl">
                        </div>
                    </div>
                    <div class="nzl-form-grid" style="grid-template-columns:1fr 1fr 1fr;">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Points Cost', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <input type="number" x-model="rewardForm.points_cost" class="nzl-input" dir="ltr" min="1" placeholder="500">
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Type', 'nozule' ); ?> <span style="color:#ef4444;">*</span></label>
                            <select x-model="rewardForm.type" class="nzl-input">
                                <option value="discount"><?php esc_html_e( 'Discount', 'nozule' ); ?></option>
                                <option value="free_night"><?php esc_html_e( 'Free Night', 'nozule' ); ?></option>
                                <option value="upgrade"><?php esc_html_e( 'Room Upgrade', 'nozule' ); ?></option>
                                <option value="amenity"><?php esc_html_e( 'Amenity', 'nozule' ); ?></option>
                            </select>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Value', 'nozule' ); ?></label>
                            <input type="text" x-model="rewardForm.value" class="nzl-input" dir="ltr"
                                   placeholder="<?php esc_attr_e( 'e.g. 10% or Free breakfast', 'nozule' ); ?>">
                        </div>
                    </div>
                    <div class="nzl-form-grid">
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Description (English)', 'nozule' ); ?></label>
                            <textarea x-model="rewardForm.description" class="nzl-input" rows="2" dir="ltr"></textarea>
                        </div>
                        <div class="nzl-form-group">
                            <label><?php esc_html_e( 'Description (Arabic)', 'nozule' ); ?></label>
                            <textarea x-model="rewardForm.description_ar" class="nzl-input" rows="2" dir="rtl"></textarea>
                        </div>
                    </div>
                    <div style="margin-top:0.5rem;">
                        <label style="display:flex; align-items:center; gap:0.5rem; cursor:pointer;">
                            <input type="checkbox" x-model="rewardForm.is_active">
                            <?php esc_html_e( 'Active', 'nozule' ); ?>
                        </label>
                    </div>
                </div>
                <div class="nzl-modal-footer">
                    <button class="nzl-btn" @click="showRewardModal = false"><?php esc_html_e( 'Cancel', 'nozule' ); ?></button>
                    <button class="nzl-btn nzl-btn-primary" @click="saveReward()" :disabled="saving">
                        <span x-show="!saving" x-text="rewardForm.id ? '<?php echo esc_js( __( 'Update', 'nozule' ) ); ?>' : '<?php echo esc_js( __( 'Create', 'nozule' ) ); ?>'"></span>
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

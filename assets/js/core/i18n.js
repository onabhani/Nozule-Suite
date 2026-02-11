/**
 * Venezia Hotel Manager - Internationalization
 *
 * Simple i18n helper for frontend translations.
 */
var VeneziaI18n = (function () {
    'use strict';

    var translations = {
        en: {
            'check_in': 'Check-in',
            'check_out': 'Check-out',
            'adults': 'Adults',
            'children': 'Children',
            'search': 'Search',
            'book_now': 'Book Now',
            'per_night': 'per night',
            'nights': 'nights',
            'total': 'Total',
            'subtotal': 'Subtotal',
            'taxes': 'Taxes',
            'select_room': 'Select Room',
            'no_availability': 'No rooms available for selected dates',
            'loading': 'Loading...',
            'error_occurred': 'An error occurred. Please try again.',
            'booking_confirmed': 'Booking Confirmed!',
            'booking_number': 'Booking Number',
            'guest_details': 'Guest Details',
            'first_name': 'First Name',
            'last_name': 'Last Name',
            'email': 'Email',
            'phone': 'Phone',
            'special_requests': 'Special Requests',
            'confirm_booking': 'Confirm Booking',
            'cancel': 'Cancel',
            'room_type': 'Room Type',
            'rate_plan': 'Rate Plan',
            'occupancy': 'Occupancy',
            'available': 'Available',
            'unavailable': 'Unavailable',
            'arrival_time': 'Estimated Arrival Time',
            'active': 'Active',
            'inactive': 'Inactive',
            'occupied': 'Occupied',
            'maintenance': 'Maintenance',
            'out_of_order': 'Out of Order',
            'confirm_delete': 'Are you sure you want to delete?',
            'confirm_delete_room_type': 'Are you sure you want to delete this room type?',
            'confirm_delete_room': 'Are you sure you want to delete this room?',
            'confirm_delete_rate_plan': 'Are you sure you want to delete this rate plan?',
            'confirm_delete_seasonal_rate': 'Are you sure you want to delete this seasonal rate?',
            'confirm_delete_channel': 'Are you sure you want to delete this channel connection?',
            'room_type_created': 'Room type created',
            'room_type_updated': 'Room type updated',
            'room_type_deleted': 'Room type deleted',
            'room_created': 'Room created',
            'room_updated': 'Room updated',
            'room_deleted': 'Room deleted',
            'rate_plan_created': 'Rate plan created',
            'rate_plan_updated': 'Rate plan updated',
            'rate_plan_deleted': 'Rate plan deleted',
            'seasonal_rate_created': 'Seasonal rate created',
            'seasonal_rate_updated': 'Seasonal rate updated',
            'seasonal_rate_deleted': 'Seasonal rate deleted',
            'channel_created': 'Channel mapping created',
            'channel_updated': 'Channel updated',
            'channel_deleted': 'Channel deleted',
            'channel_synced': 'Channel synced successfully',
            'failed_load_rooms': 'Failed to load rooms data',
            'failed_save_room_type': 'Failed to save room type',
            'failed_delete_room_type': 'Failed to delete room type',
            'failed_save_room': 'Failed to save room',
            'failed_delete_room': 'Failed to delete room',
            'failed_load_rates': 'Failed to load rates data',
            'failed_save_rate_plan': 'Failed to save rate plan',
            'failed_delete_rate_plan': 'Failed to delete rate plan',
            'failed_save_seasonal_rate': 'Failed to save seasonal rate',
            'failed_delete_seasonal_rate': 'Failed to delete seasonal rate',
            'failed_save_channel': 'Failed to save channel',
            'failed_delete_channel': 'Failed to delete channel',
            'sync_failed': 'Sync failed',
            'select_room_type_first': 'Please create a room type first',
            'select_room_type': 'Please select a room type',
            'guest_created': 'Guest created successfully',
            'guest_updated': 'Guest updated successfully',
            'failed_load_guests': 'Failed to load guests',
            'failed_load_guest': 'Failed to load guest details',
            'failed_save_guest': 'Failed to save guest',
            'fill_required_fields': 'Please fill in all required fields',
            'male': 'Male',
            'female': 'Female',
            'settings_saved': 'Settings saved successfully',
            'failed_save_settings': 'Failed to save settings',
            'select_provider_first': 'Please select a provider first',
            'connection_test_failed': 'Connection test failed'
        },
        ar: {
            'check_in': 'تسجيل الوصول',
            'check_out': 'تسجيل المغادرة',
            'adults': 'بالغين',
            'children': 'أطفال',
            'search': 'بحث',
            'book_now': 'احجز الآن',
            'per_night': 'لليلة',
            'nights': 'ليالي',
            'total': 'المجموع',
            'subtotal': 'المجموع الفرعي',
            'taxes': 'الضرائب',
            'select_room': 'اختر الغرفة',
            'no_availability': 'لا تتوفر غرف للتواريخ المحددة',
            'loading': 'جاري التحميل...',
            'error_occurred': 'حدث خطأ. يرجى المحاولة مرة أخرى.',
            'booking_confirmed': 'تم تأكيد الحجز!',
            'booking_number': 'رقم الحجز',
            'guest_details': 'بيانات الضيف',
            'first_name': 'الاسم الأول',
            'last_name': 'اسم العائلة',
            'email': 'البريد الإلكتروني',
            'phone': 'الهاتف',
            'special_requests': 'طلبات خاصة',
            'confirm_booking': 'تأكيد الحجز',
            'cancel': 'إلغاء',
            'room_type': 'نوع الغرفة',
            'rate_plan': 'خطة السعر',
            'occupancy': 'الإشغال',
            'available': 'متاح',
            'unavailable': 'غير متاح',
            'arrival_time': 'وقت الوصول المتوقع',
            'save': 'حفظ',
            'edit': 'تعديل',
            'delete': 'حذف',
            'close': 'إغلاق',
            'add': 'إضافة',
            'status': 'الحالة',
            'active': 'نشط',
            'inactive': 'غير نشط',
            'name': 'الاسم',
            'actions': 'الإجراءات',
            'dashboard': 'لوحة التحكم',
            'bookings': 'الحجوزات',
            'rooms': 'الغرف',
            'guests': 'الضيوف',
            'rates': 'الأسعار',
            'reports': 'التقارير',
            'settings': 'الإعدادات',
            'channels': 'القنوات',
            'calendar': 'التقويم',
            'inventory': 'المخزون',
            'rate_plans': 'خطط الأسعار',
            'seasonal_rates': 'الأسعار الموسمية',
            'add_rate_plan': 'إضافة خطة سعر',
            'add_seasonal_rate': 'إضافة سعر موسمي',
            'edit_rate_plan': 'تعديل خطة السعر',
            'edit_seasonal_rate': 'تعديل السعر الموسمي',
            'plan_name': 'اسم الخطة',
            'plan_code': 'كود الخطة',
            'modifier_type': 'نوع التعديل',
            'modifier_value': 'قيمة التعديل',
            'percentage': 'نسبة مئوية',
            'fixed': 'مبلغ ثابت',
            'absolute': 'سعر مطلق',
            'min_stay': 'الحد الأدنى للإقامة',
            'max_stay': 'الحد الأقصى للإقامة',
            'priority': 'الأولوية',
            'description': 'الوصف',
            'start_date': 'تاريخ البداية',
            'end_date': 'تاريخ النهاية',
            'season_name': 'اسم الموسم',
            'no_rate_plans': 'لا توجد خطط أسعار.',
            'no_seasonal_rates': 'لا توجد أسعار موسمية.',
            'confirm_delete': 'هل أنت متأكد من الحذف؟',
            'confirm_delete_room_type': 'هل أنت متأكد من حذف نوع الغرفة؟',
            'confirm_delete_room': 'هل أنت متأكد من حذف هذه الغرفة؟',
            'confirm_delete_rate_plan': 'هل أنت متأكد من حذف خطة السعر؟',
            'confirm_delete_seasonal_rate': 'هل أنت متأكد من حذف السعر الموسمي؟',
            'confirm_delete_channel': 'هل أنت متأكد من حذف اتصال القناة؟',
            'saved_successfully': 'تم الحفظ بنجاح',
            'deleted_successfully': 'تم الحذف بنجاح',
            'occupied': 'مشغول',
            'maintenance': 'صيانة',
            'out_of_order': 'خارج الخدمة',
            'room_type_created': 'تم إنشاء نوع الغرفة',
            'room_type_updated': 'تم تحديث نوع الغرفة',
            'room_type_deleted': 'تم حذف نوع الغرفة',
            'room_created': 'تم إنشاء الغرفة',
            'room_updated': 'تم تحديث الغرفة',
            'room_deleted': 'تم حذف الغرفة',
            'rate_plan_created': 'تم إنشاء خطة السعر',
            'rate_plan_updated': 'تم تحديث خطة السعر',
            'rate_plan_deleted': 'تم حذف خطة السعر',
            'seasonal_rate_created': 'تم إنشاء السعر الموسمي',
            'seasonal_rate_updated': 'تم تحديث السعر الموسمي',
            'seasonal_rate_deleted': 'تم حذف السعر الموسمي',
            'channel_created': 'تم إنشاء ربط القناة',
            'channel_updated': 'تم تحديث القناة',
            'channel_deleted': 'تم حذف القناة',
            'channel_synced': 'تمت مزامنة القناة بنجاح',
            'failed_load_rooms': 'فشل تحميل بيانات الغرف',
            'failed_save_room_type': 'فشل حفظ نوع الغرفة',
            'failed_delete_room_type': 'فشل حذف نوع الغرفة',
            'failed_save_room': 'فشل حفظ الغرفة',
            'failed_delete_room': 'فشل حذف الغرفة',
            'failed_load_rates': 'فشل تحميل بيانات الأسعار',
            'failed_save_rate_plan': 'فشل حفظ خطة السعر',
            'failed_delete_rate_plan': 'فشل حذف خطة السعر',
            'failed_save_seasonal_rate': 'فشل حفظ السعر الموسمي',
            'failed_delete_seasonal_rate': 'فشل حذف السعر الموسمي',
            'failed_save_channel': 'فشل حفظ القناة',
            'failed_delete_channel': 'فشل حذف القناة',
            'sync_failed': 'فشلت المزامنة',
            'select_room_type_first': 'يرجى إنشاء نوع غرفة أولاً',
            'select_room_type': 'يرجى اختيار نوع الغرفة',
            'guest_created': 'تم إنشاء الضيف بنجاح',
            'guest_updated': 'تم تحديث بيانات الضيف',
            'failed_load_guests': 'فشل تحميل قائمة الضيوف',
            'failed_load_guest': 'فشل تحميل بيانات الضيف',
            'failed_save_guest': 'فشل حفظ بيانات الضيف',
            'fill_required_fields': 'يرجى ملء جميع الحقول المطلوبة',
            'male': 'ذكر',
            'female': 'أنثى',
            'settings_saved': 'تم حفظ الإعدادات بنجاح',
            'failed_save_settings': 'فشل حفظ الإعدادات',
            'select_provider_first': 'يرجى اختيار مزود الخدمة أولاً',
            'connection_test_failed': 'فشل اختبار الاتصال',
            'pending': 'قيد الانتظار',
            'confirmed': 'مؤكد',
            'checked_in': 'مسجل وصول',
            'checked_out': 'مسجل مغادرة',
            'cancelled': 'ملغي',
            'no_show': 'عدم حضور',
            'today_arrivals': 'وصولات اليوم',
            'today_departures': 'مغادرات اليوم',
            'in_house': 'في الفندق',
            'occupancy_rate': 'نسبة الإشغال',
            'today_revenue': 'إيرادات اليوم',
            'page': 'صفحة',
            'of': 'من',
            'previous': 'السابق',
            'next': 'التالي',
            'all_statuses': 'جميع الحالات',
            'from': 'من',
            'to': 'إلى',
            'no_bookings': 'لا توجد حجوزات.',
            'no_guests': 'لا يوجد ضيوف.',
            'hotel_name': 'اسم الفندق',
            'hotel_email': 'بريد الفندق',
            'hotel_phone': 'هاتف الفندق',
            'currency': 'العملة',
            'timezone': 'المنطقة الزمنية',
            'general': 'عام',
            'notifications': 'الإشعارات',
            'policies': 'السياسات'
        }
    };

    var currentLocale = 'en';

    return {
        /**
         * Set the current locale.
         */
        setLocale: function (locale) {
            currentLocale = locale && translations[locale] ? locale : 'en';
        },

        /**
         * Get a translation string.
         */
        t: function (key, replacements) {
            var locale = translations[currentLocale] || translations['en'];
            var text = locale[key] || translations['en'][key] || key;

            if (replacements) {
                Object.keys(replacements).forEach(function (k) {
                    text = text.replace('{' + k + '}', replacements[k]);
                });
            }

            return text;
        },

        /**
         * Get the current locale.
         */
        getLocale: function () {
            return currentLocale;
        },

        /**
         * Alias for t() — matches WordPress __() convention.
         */
        __: function (key, replacements) {
            return this.t(key, replacements);
        },

        /**
         * Check if current locale is RTL.
         */
        isRTL: function () {
            return currentLocale === 'ar';
        }
    };
})();

// Initialize locale from config (check both admin and public config)
(function () {
    var config = window.VeneziaAdmin || window.VeneziaConfig;
    if (config && config.locale) {
        var locale = config.locale.substring(0, 2);
        VeneziaI18n.setLocale(locale);
    }
})();

window.VeneziaI18n = VeneziaI18n;

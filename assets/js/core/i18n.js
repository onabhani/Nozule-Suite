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
            'arrival_time': 'Estimated Arrival Time'
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
            'saved_successfully': 'تم الحفظ بنجاح',
            'deleted_successfully': 'تم الحذف بنجاح',
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

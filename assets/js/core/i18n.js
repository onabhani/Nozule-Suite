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
            'arrival_time': 'وقت الوصول المتوقع'
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

// Initialize locale from config
if (window.VeneziaConfig && window.VeneziaConfig.locale) {
    var locale = window.VeneziaConfig.locale.substring(0, 2);
    VeneziaI18n.setLocale(locale);
}

window.VeneziaI18n = VeneziaI18n;

/**
 * Nozule - Guest Registration Component
 */
document.addEventListener('alpine:init', function () {
    Alpine.data('guestRegister', function () {
        return {
            form: {
                email: '',
                password: '',
                first_name: '',
                last_name: '',
                phone: '',
                locale: (window.NozuleConfig && window.NozuleConfig.locale) || 'en'
            },
            submitting: false,
            success: false,
            error: '',

            submit: async function () {
                this.error = '';
                if (this.form.password.length < 8) {
                    this.error = 'Password must be at least 8 characters.';
                    return;
                }
                this.submitting = true;
                try {
                    const res = await fetch(NozuleConfig.apiBase + '/me/register', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-WP-Nonce': NozuleConfig.nonce
                        },
                        body: JSON.stringify(this.form),
                        credentials: 'same-origin'
                    });
                    const data = await res.json();
                    if (!res.ok) {
                        this.error = (data && data.message) || 'Registration failed.';
                        this.submitting = false;
                        return;
                    }
                    this.success = true;
                    const redirect = (window.NozuleConfig && window.NozuleConfig.myAccountUrl) || '/my-account/';
                    setTimeout(function () { window.location.href = redirect; }, 1200);
                } catch (e) {
                    this.error = e.message || 'Network error.';
                    this.submitting = false;
                }
            }
        };
    });
});

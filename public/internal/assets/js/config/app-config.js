/**
 * Global Application Configuration
 * Contains API endpoints, base URLs, and other global settings
 */
window.AppConfig = (function () {
    /**
     * Get the base URL for API calls from Laravel-provided meta tag
     * @returns {string} - The base URL for API endpoints
     */
    function getBaseUrl() {
        // Get base URL from Laravel-provided meta tag
        const metaBaseUrl = document.querySelector('meta[name="api-base-url"]');
        if (metaBaseUrl) {
            const baseUrl = metaBaseUrl.getAttribute("content");
            if (baseUrl) {
                console.log("Using Laravel-provided base URL:", baseUrl);
                return baseUrl;
            }
        }

        // Fallback: assume /api/v1 if meta tag is missing
        console.warn("Base URL meta tag not found, using fallback: /api/v1");
        return "/api/v1";
    }

    return {
        // API Configuration
        api: {
            baseUrl: getBaseUrl(),
            timeout: 10000, // 10 seconds
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-Requested-With": "XMLHttpRequest",
            },
        },

        // Form Configuration
        forms: {
            validation: {
                errorClass: "is-invalid",
                validClass: "is-valid",
                errorElement: "div",
                errorPlacement: "after",
            },
        },

        // UI Configuration
        ui: {
            loadingText: "Loading...",
            errorText: "Error loading data",
            noDataText: "No data available",
        },

        // Utility Methods
        utils: {
            /**
             * Replace parameters in URL template
             * @param {string} template - URL template with :param placeholders
             * @param {object} params - Parameters to replace
             * @returns {string} - Final URL
             */
            buildUrl: function (template, params = {}) {
                let url = template;

                // If template doesn't start with baseUrl, prepend it
                if (!template.startsWith(window.AppConfig.api.baseUrl)) {
                    url = window.AppConfig.api.baseUrl + template;
                }

                // Replace parameters
                Object.keys(params).forEach((key) => {
                    url = url.replace(`:${key}`, params[key]);
                });

                return url;
            },

            /**
             * Make API request with default configuration
             * @param {string} url - API endpoint URL
             * @param {object} options - Fetch options
             * @returns {Promise} - Fetch promise
             */
            apiRequest: async function (url, options = {}) {
                const defaultOptions = {
                    headers: window.AppConfig.api.headers,
                    ...options,
                };

                try {
                    const response = await fetch(url, defaultOptions);

                    if (!response.ok) {
                        throw new Error(
                            `HTTP error! status: ${response.status}`
                        );
                    }

                    return await response.json();
                } catch (error) {
                    console.error("API request failed:", error);
                    throw error;
                }
            },
        },
    };
})();

// Add CSRF token to headers if available
if (document.querySelector('meta[name="csrf-token"]')) {
    window.AppConfig.api.headers["X-CSRF-TOKEN"] = document
        .querySelector('meta[name="csrf-token"]')
        .getAttribute("content");
}

// Debug: Log the detected base URL for verification
console.log("Detected API Base URL:", window.AppConfig.api.baseUrl);

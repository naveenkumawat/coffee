// Add this to the beginning of your user-module.js or inline in your create/edit.blade.php files

/**
 * Check and load jQuery Validation plugin if needed
 * This ensures jQuery Validation is loaded before we initialize it
 */
function ensureJQueryValidation() {
    return new Promise((resolve, reject) => {
        // Check if jQuery is available
        if (typeof $ === "undefined") {
            console.error(
                "jQuery is not loaded! Please check plugins.bundle.js is included properly."
            );
            reject(new Error("jQuery not found"));
            return;
        }

        // Check if jQuery Validation plugin is already loaded
        if ($.fn && $.fn.validate) {
            console.log("✅ jQuery Validation already loaded");
            resolve(true);
            return;
        }

        console.log(
            "⚠️ jQuery Validation not found, attempting to load dynamically"
        );

        // Try to load it dynamically
        const script = document.createElement("script");
        script.src =
            "/assets/plugins/custom/jquery-validate/jquery.validate.min.js";
        script.onload = function () {
            console.log("✅ jQuery Validation loaded successfully");

            // Also load additional methods if needed
            const additionalScript = document.createElement("script");
            additionalScript.src =
                "/assets/plugins/custom/jquery-validate/additional-methods.min.js";
            additionalScript.onload = function () {
                console.log("✅ jQuery Validation Additional Methods loaded");
                resolve(true);
            };
            additionalScript.onerror = function () {
                console.warn(
                    "⚠️ Could not load additional methods, but continuing with base validation"
                );
                resolve(true);
            };
            document.head.appendChild(additionalScript);
        };
        script.onerror = function () {
            console.error("❌ Failed to load jQuery Validation");
            reject(new Error("Failed to load jQuery Validation"));
        };
        document.head.appendChild(script);
    });
}

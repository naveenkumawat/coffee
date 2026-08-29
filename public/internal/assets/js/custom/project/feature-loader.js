/**
 * Feature Loader
 *
 * Centralized loader for all JavaScript features
 * Manages dependencies and initialization order
 *
 * @package ZYLM Manufacturing System
 * @version 2.0
 */

class FeatureLoader {
    constructor() {
        this.features = new Map();
        this.loadedFeatures = new Set();
        this.initializationOrder = [
            "ui-interactions",
            "status-toggle",
            "entity-actions",
        ];

        this.init();
    }

    init() {
        console.log("Feature Loader initialized");
        this.registerFeatures();
        this.loadFeatures();
    }

    /**
     * Register all available features
     */
    registerFeatures() {
        this.features.set("ui-interactions", {
            file: "/assets/js/custom/project/features/ui-interactions.js",
            dependencies: [],
            instance: null,
        });

        this.features.set("status-toggle", {
            file: "/assets/js/custom/project/features/status-toggle.js",
            dependencies: [],
            instance: null,
        });

        this.features.set("entity-actions", {
            file: "/assets/js/custom/project/features/entity-actions.js",
            dependencies: ["ui-interactions"],
            instance: null,
        });

    }

    /**
     * Load features in correct order
     */
    async loadFeatures() {
        for (const featureName of this.initializationOrder) {
            await this.loadFeature(featureName);
        }

        console.log("All features loaded successfully");
        this.triggerReadyEvent();
    }

    /**
     * Load a specific feature
     */
    async loadFeature(featureName) {
        if (this.loadedFeatures.has(featureName)) {
            return Promise.resolve();
        }

        const feature = this.features.get(featureName);
        if (!feature) {
            console.warn(`Feature '${featureName}' not found`);
            return Promise.reject(`Feature not found: ${featureName}`);
        }

        // Load dependencies first
        for (const dependency of feature.dependencies) {
            await this.loadFeature(dependency);
        }

        // Load the feature script
        try {
            await this.loadScript(feature.file);
            this.loadedFeatures.add(featureName);
            console.log(`Feature loaded: ${featureName}`);
        } catch (error) {
            console.error(`Failed to load feature '${featureName}':`, error);
            throw error;
        }
    }

    /**
     * Load script dynamically
     */
    loadScript(src) {
        return new Promise((resolve, reject) => {
            // Check if script is already loaded
            const existingScript = document.querySelector(
                `script[src="${src}"]`
            );
            if (existingScript) {
                resolve();
                return;
            }

            const script = document.createElement("script");
            script.src = src;
            script.async = true;

            script.onload = () => resolve();
            script.onerror = () =>
                reject(new Error(`Failed to load script: ${src}`));

            document.head.appendChild(script);
        });
    }

    /**
     * Check if feature is loaded
     */
    isFeatureLoaded(featureName) {
        return this.loadedFeatures.has(featureName);
    }

    /**
     * Get feature instance
     */
    getFeature(featureName) {
        const feature = this.features.get(featureName);
        return feature ? feature.instance : null;
    }

    /**
     * Trigger ready event when all features are loaded
     */
    triggerReadyEvent() {
        const event = new CustomEvent("featuresReady", {
            detail: {
                loadedFeatures: Array.from(this.loadedFeatures),
                loader: this,
            },
        });

        document.dispatchEvent(event);
    }

    /**
     * Add custom feature
     */
    addFeature(name, config) {
        this.features.set(name, {
            file: config.file,
            dependencies: config.dependencies || [],
            instance: config.instance || null,
        });
    }

    /**
     * Remove feature
     */
    removeFeature(name) {
        this.features.delete(name);
        this.loadedFeatures.delete(name);
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", () => {
        window.FeatureLoader = new FeatureLoader();
    });
} else {
    window.FeatureLoader = new FeatureLoader();
}

// Global utility function
window.waitForFeatures = function (callback) {
    if (window.FeatureLoader && window.FeatureLoader.loadedFeatures.size > 0) {
        callback();
    } else {
        document.addEventListener("featuresReady", callback, { once: true });
    }
};

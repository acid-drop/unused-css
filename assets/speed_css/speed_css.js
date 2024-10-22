/**
 * CSSUsageCollector class is responsible for collecting and processing CSS rules
 * from stylesheets on the current page. It supports options to conditionally log warnings,
 * include specific classes based on regex patterns, and run when the browser is idle.
 */
class CSSUsageCollector {
    /**
     * @param {boolean} logWarnings - Whether to log warnings to the console. Defaults to true.
     * @param {Array<RegExp>} includePatterns - An array of regex patterns. Selectors matching these patterns will always be included.
     */
    constructor(logWarnings = true, includePatterns = []) {

        /**
         * Directory to exclude from processing, defaults to 'acd-unused-css' if not defined.
         * @type {string}
         */
        this.cacheDir = (typeof speed_css_vars !== 'undefined' && speed_css_vars.cache_directory)
            ? speed_css_vars.cache_directory
            : 'acd-unused-css';

        /**
         * Object to store collected CSS rules, keyed by stylesheet href.
         * @type {Object<string, string>}
         */
        this.stylesheetsCSS = {};

        /**
         * The host of the current document.
         * @type {string}
         */
        this.currentHost = window.location.host;

        /**
         * Whether to log warnings to the console.
         * @type {boolean}
         */
        this.logWarnings = logWarnings;

        /**
         * An array of regex patterns. Selectors matching these patterns will always be included.
         * @type {Array<RegExp>}
         */
        this.includePatterns = includePatterns;

        /**
         * Total length of CSS processed.
         * @type {number}
         */
        this.totalOriginalLength = 0;

        /**
         * Total length of CSS after filtering.
         * @type {number}
         */
        this.totalFilteredLength = 0;

        /**
         * Whether or not the user has collected
         * @type {boolean}
         */
        this.has_collected = false;

    }

    /**
     * Initiates the CSS collection process by iterating over all stylesheets
     * and processing each one that matches the criteria.
     * @returns {Promise<Object<string, string>>} - The collected CSS rules organized by stylesheet href.
     */
    async collect() {

        this.has_collected = true;


        // Process styles and include used variables
        for (let sheet of document.styleSheets) {
            if (this.shouldProcessSheet(sheet)) {
                await this.processSheet(sheet);
            }
        }

        const cssClean = this.getResults();

        if (Object.keys(cssClean).length > 0) {

            var bodyClass = document.body.className;
            var classList = bodyClass.split(/\s+/);
            var regex = /^(rtl|home|blog|privacy-policy|archive|date|search(-[a-zA-Z0-9-_]+)?|paged|attachment|error404|[a-zA-Z0-9-__]+-template|single(-[a-zA-Z0-9-_]+)?|page(-[a-zA-Z0-9-_]+)?|post-type-archive(-[a-zA-Z0-9-_]+)?|author(-[a-zA-Z0-9-_]+)?|category(-[a-zA-Z0-9-_]+)?|tag(-[a-zA-Z0-9-_]+)?|tax(-[a-zA-Z0-9-_]+)?|term(-[a-zA-Z0-9-_]+)?)$/;
            var post_types = classList.filter(function(cls) {
              return regex.test(cls);
            });

            this.updateConfig({ css: cssClean, 
                                url: document.location.href, 
                                post_id: (document.body.className.split(' ').find(cls => cls.startsWith('postid-') || cls.startsWith('page-id-'))?.replace(/(postid-|page-id-)/, '') || null), 
                                post_types: post_types,
                                reduction: this.calculatePercentageDifference() 
                              }
                            );
        }

        return this.stylesheetsCSS;
    }

    /**
     * Processes an individual stylesheet, collecting font-family usage and processing rules.
     * Includes matching selectors, @font-face rules, and rules that match include patterns.
     * @param {CSSStyleSheet} sheet - The stylesheet to process.
     */
    async processSheet(sheet) {
        let cssTxt = '';
        const foundFamilies = new Set();

        // First pass: Collect font-family usage
        try {
            for (let rule of sheet.cssRules) {
                if (rule.type === CSSRule.STYLE_RULE) {
                    const foundFamily = rule.style.getPropertyValue('font-family').trim().replace(/['"]/g, '');
                    if (foundFamily) {
                        foundFamilies.add(foundFamily);
                    }
                }
            }
        } catch (e) {
            this.warn(`Error processing stylesheet '${sheet.href}':`, e);
        }

        // Second pass: Process rules, including @font-face and matching selectors
        try {
            for (let rule of sheet.cssRules) {
                if (rule.type === CSSRule.IMPORT_RULE) {
                    const importedSheet = rule.styleSheet;
                    if (importedSheet) {
                        await this.processSheet(importedSheet);
                    }
                } else if (rule.type === CSSRule.MEDIA_RULE) {
                    // Handle media queries
                    cssTxt += `@media ${rule.media.mediaText} {\n`;
                    for (let innerRule of rule.cssRules) {
                        if (innerRule.type === CSSRule.STYLE_RULE && (this.shouldIncludeSelector(innerRule.selectorText) || this.doesSelectorMatch(innerRule))) {
                            const reconstructedRule = this.reconstructRule(innerRule);
                            cssTxt += `  ${reconstructedRule}\n`;
                        }
                    }
                    cssTxt += `}\n`;
                } else if (rule.type === CSSRule.KEYFRAMES_RULE) {
                    cssTxt += `@keyframes ${rule.name} {\n`;
                    for (let keyframe of rule.cssRules) {
                        if (keyframe.type === CSSRule.KEYFRAME_RULE) {
                            cssTxt += `  ${keyframe.keyText} { ${keyframe.style.cssText} }\n`;
                        }
                    }
                    cssTxt += `}\n`;
                } else if (rule.type === CSSRule.FONT_FACE_RULE) {
                    const foundFamily = rule.style.getPropertyValue('font-family').trim().replace(/['"]/g, '');
                    if (foundFamily && foundFamilies.has(foundFamily)) {
                        cssTxt += rule.cssText + '\n';
                    }
                } else if (rule.selectorText) {
                    try {
                        this.totalOriginalLength += rule.cssText.length; // Track original length

                        if (this.shouldIncludeSelector(rule.selectorText) || this.doesSelectorMatch(rule)) {
                            const reconstructedRule = this.reconstructRule(rule);
                            cssTxt += reconstructedRule + '\n';
                            this.totalFilteredLength += reconstructedRule.length; // Track filtered length
                        }
                    } catch (e) {
                        this.warn(`Error testing selector '${rule.selectorText}':`, e);
                    }
                }
            }
        } catch (e) {
            this.warn(`Error processing stylesheet '${sheet.href}':`, e);
        }

        if (sheet.href) {
            // Ensure href is always included in the result, even if no cssTxt is found
            this.stylesheetsCSS[sheet.href] = (this.stylesheetsCSS[sheet.href] || '') + cssTxt;
        }
    }

    /**
     * Determines whether a stylesheet should be processed based on its host and path.
     * @param {CSSStyleSheet} sheet - The stylesheet to evaluate.
     * @returns {boolean} - True if the stylesheet should be processed, otherwise false.
     */
    shouldProcessSheet(sheet) {
        if (!sheet.href) {
            return false;
        }
        try {
            const sheetURL = new URL(sheet.href);
            //For stats mode, don't reproceed if already processed
            const isProcessed = (typeof sheet.ownerNode.dataset.ucssProcessed == "string") && sheet.ownerNode.dataset.ucssProcessed == "true";
            return sheetURL.host === this.currentHost && !sheetURL.pathname.includes(this.cacheDir) && !isProcessed;
        } catch (e) {
            this.warn(`Error processing stylesheet '${sheet.href}':`, e);
            return false;
        }
    }

    /**
     * Reconstructs a CSS rule into a string, ensuring proper formatting and handling shorthand properties,
     * CSS variables, and complex selectors.
     * @param {CSSStyleRule} rule - The CSS rule to reconstruct.
     * @returns {string} - The reconstructed CSS rule as a string.
     */
    reconstructRule(rule) {
        // Get the full CSS rule as text
        let ruleText = rule.cssText;
    
        // Check if the rule has a 'content' property that needs to be escaped
        const style = rule.style;
        for (let i = 0; i < style.length; i++) {
            const propName = style[i];
    
            // If the property is 'content', replace it with the re-escaped version
            if (propName === 'content') {
                let originalContent = style.getPropertyValue(propName);
                let escapedContent = this.reEscapeContent(originalContent);
    
                // Replace the original 'content' in the ruleText with the escaped version
                ruleText = ruleText.replace(`content: ${originalContent}`, `content: ${escapedContent}`);
            }
        }
    
        return ruleText;
    }    

    /**
     * Re-escapes content property values to preserve special characters in CSS content strings.
     * @param {string} content - The content value to re-escape.
     * @returns {string} - The re-escaped content value.
     */
    reEscapeContent(content) {
        return content.replace(/[\uE000-\uF8FF]/g, function (ch) {
            return '\\' + ch.charCodeAt(0).toString(16).padStart(4, '0');
        });
    }

    /**
     * Checks if a selector matches any elements in the document.
     * Strips dynamic pseudo-classes for matching purposes.
     * @param {CSSStyleRule} rule - The CSS rule to test.
     * @returns {boolean} - True if the selector matches any elements, otherwise false.
     */
    doesSelectorMatch(rule) {

        const selector = rule.selectorText;

        try {

            // Special case: :root should always match
            if (selector.trim() === ':root') {
                return true;
            }

            if (!this.isValidSelector(selector)) {
                this.warn(`Invalid selector: '${selector}'`);
                return false;
            }

            // Strip dynamic pseudo-classes like :hover, :focus, :active for matching purposes
            var cleanedSelector = selector.replace(/:not\([^)]+\)/g, '').replace(/,\s*$/, '');
            cleanedSelector = cleanedSelector.replace(/:(hover|active|focus|visited|focus-within|focus-visible)/g, '');

            const baseSelector = cleanedSelector.replace(/::?[\w-]+$/, '');
            const pseudoElementMatch = /::[\w-]+$/.test(cleanedSelector);

            const elements = document.querySelectorAll(baseSelector);
            if (elements.length === 0) {
                return false;
            }

            if (!pseudoElementMatch) {
                return true;
            }

            for (const element of elements) {
                const style = window.getComputedStyle(element, cleanedSelector.match(/::[\w-]+$/)[0]);
                if (style.content !== 'none' && style.display !== 'none') {
                    return true;
                }
            }

            return false;
        } catch (e) {
            this.warn(`Error querying selector '${selector}':`, e);
            return false;
        }
    }
 

    /**
     * Checks if a selector should be included based on user-defined regex patterns.
     * @param {string} selector - The CSS selector to check.
     * @returns {boolean} - True if the selector matches any include pattern, otherwise false.
     */
    shouldIncludeSelector(selector) {
        return this.includePatterns.some(pattern => pattern.test(selector));
    }

    /**
     * Validates whether a CSS selector is properly formatted and supported by the browser.
     * @param {string} selector - The CSS selector to validate.
     * @returns {boolean} - True if the selector is valid, otherwise false.
     */
    isValidSelector(selector) {
        try {
            document.createElement('div').querySelector(selector);
            return true;
        } catch (e) {
            return false;
        }
    }

    /**
     * Sends the collected CSS data to the server via a POST request.
     * @param {Object} data - The data to send, typically containing CSS and the current page URL.
     */
    updateConfig(data) {

        (async () => {
            try {
                // Convert the input data to a JSON string and compress it
                const inputStr = JSON.stringify(data);
                const base64CompressedData = await this.compressAndBase64Encode(inputStr);

                if(base64CompressedData == false) {
                    console.warn('Unable to update CSS on this browser');
                    return;
                }
        
                // Send the compressed data via fetch
                const response = await fetch('/wp-json/unused-css/update_css', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ compressedData: base64CompressedData }), // Wrap in an object if needed
                });
        
                // Handle the response
                const responseData = await response.json();
                console.log('Successfully updated CSS:', responseData);
            } catch (error) {
                console.error('Error updating config:', error);
            }
        })();
        
    }

    /**
     * Retrieves the collected CSS rules organized by stylesheet href.
     * @returns {Object<string, string>} - The collected CSS rules.
     */
    getResults() {
        return this.stylesheetsCSS;
    }

    /**
     * Logs a warning to the console if logging is enabled.
     * @param  {...any} args - Arguments to pass to console.warn.
     */
    warn(...args) {
        if (this.logWarnings) {
            console.warn(...args);
        }
    }

    /**
     * Calculates the percentage difference between the total original and filtered CSS lengths.
     * @returns {number} - The percentage difference.
     */
    calculatePercentageDifference() {
        if (this.totalOriginalLength === 0) return 0;
        const difference = this.totalOriginalLength - this.totalFilteredLength;
        return (difference / this.totalOriginalLength) * 100;
    }

    /**
     * Compress a string and return a base64-encoded compressed result.
     *
     * @param {string} inputString
     * @returns {Promise<string>} Base64 encoded compressed string
     */
    async compressAndBase64Encode(inputString) {
        // Convert the input string to a byte stream (Uint8Array)
        const stream = new Blob([inputString]).stream();
    
        // Create a compressed stream using GZIP
        const compressedStream = stream.pipeThrough(new CompressionStream("gzip"));
    
        // Collect the compressed chunks into an array
        const chunks = [];
        try {

            for await (const chunk of compressedStream) {
            chunks.push(chunk);
            }

            // Concatenate the chunks into a single Uint8Array
            const compressedData = await this.concatUint8Arrays(chunks);
        
            // Convert the Uint8Array to a binary string for base64 encoding
            const binaryString = String.fromCharCode(...compressedData);
        
            // Base64-encode the binary string
            const base64String = btoa(binaryString);
        
            return base64String;


        } catch (error) {
            //Doesn't work on this browser
            return false;
        }
    
    }
  
    /**
     * Combine multiple Uint8Arrays into one.
     *
     * @param {ReadonlyArray<Uint8Array>} uint8arrays
     * @returns {Promise<Uint8Array>}
     */
    async concatUint8Arrays(uint8arrays) {
        const blob = new Blob(uint8arrays);
        const buffer = await blob.arrayBuffer();
        return new Uint8Array(buffer);
    }   
  

    /**
     * Adds an event listener to the window scroll event to debounce CSS usage collection
     * until the user has stopped scrolling for 1 second.
     * 
     * This method is useful for cases where you want to only collect CSS usage after
     * the user has finished scrolling.
     */
    collect_after_scroll() {

        // Start a fallback timeout to collect after 5 seconds of page load
        const fallbackTimeout = setTimeout(() => {
            this.collect_when_idle();
        }, 5000);  // 5000ms = 5 seconds

        // Define the scroll listener function to debounce collection after scroll activity
        this.scrollListener = () => {
            // Cancel the fallback timeout if the user scrolls
            clearTimeout(fallbackTimeout);

            // Debounce to wait for idle state after scrolling
            clearTimeout(window.scrollIdleTimeout);
            window.scrollIdleTimeout = setTimeout(() => {
                this.collect_when_idle();
            }, 1000);  // 1000ms delay after scroll ends
        };

        // Add the scroll event listener
        window.addEventListener('scroll', this.scrollListener);  


    }

    /**
     * Collects CSS usage when the browser is in an idle state. This is useful for cases
     * where you want to avoid collecting CSS usage while the user is actively interacting
     * with the page.
     */
    collect_when_idle() {

        if (typeof this.scrollListener !== "undefined") {
            window.removeEventListener('scroll', this.scrollListener); 
        }

        // Only run the collector when idle
        requestIdleCallback(() => {
            if(this.has_collected == false) {
                this.collect();                
            }            
        });

    }

}

(function() {
    var includePatterns = JSON.parse(speed_css_vars.include_patterns); // patterns to always include
    includePatterns = includePatterns.map(pattern => new RegExp(pattern.replace(/\\\\/g, '\\')));
    const collector = new CSSUsageCollector(false, includePatterns); // Pass false to disable warning logging
    collector.collect_after_scroll();
})();


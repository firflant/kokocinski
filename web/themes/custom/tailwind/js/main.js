/**
 * @file
 * Main JavaScript file for the Tailwind theme.
 */

((Drupal) => {
  "use strict";

  /**
   * Auto-collapse status messages after 5 seconds with smooth animation.
   */
  Drupal.behaviors.statusMessages = {
    attach: (context, settings) => {
      const messageContainers = context.querySelectorAll(
        ".status-message-container:not([data-auto-collapse-attached])",
      );

      if (messageContainers.length === 0) {
        return;
      }

      messageContainers.forEach((container) => {
        // Mark as attached to prevent duplicate processing
        container.setAttribute("data-auto-collapse-attached", "true");

        const message = container.querySelector(".status-message");
        if (!message) {
          return;
        }

        // Store original height for smooth collapse
        const originalHeight = container.scrollHeight;
        container.style.maxHeight = `${originalHeight}px`;
        container.style.opacity = "1";

        // Collapse function
        const collapse = () => {
          container.style.maxHeight = "0";
          container.style.opacity = "0";
          container.style.marginBottom = "0";
          container.style.paddingTop = "0";
          container.style.paddingBottom = "0";

          // Remove from DOM after animation completes
          setTimeout(() => {
            if (container.parentNode) {
              container.style.display = "none";
            }
          }, 500); // Match transition duration
        };

        // Auto-collapse after 5 seconds
        setTimeout(collapse, 5000);
      });
    },
  };

})(Drupal);

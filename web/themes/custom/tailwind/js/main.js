/**
 * @file
 * Main JavaScript file for the Tailwind theme.
 */

((Drupal) => {
  "use strict";

  /**
   * Navbar sticky behavior on scroll up.
   */
  Drupal.behaviors.tailwindNavbar = {
    attach: (context, settings) => {
      const navbar = context.querySelector("#main-navbar");
      if (!navbar) {
        return;
      }

      let lastScrollY = window.scrollY;
      let isScrollingUp = false;

      const handleScroll = () => {
        const currentScrollY = window.scrollY;
        const scrollDifference = currentScrollY - lastScrollY;

        // Close mobile menu on any scroll via custom event
        document.dispatchEvent(new CustomEvent("closeMobileMenu"));

        // Determine scroll direction
        if (scrollDifference < 0) {
          // Scrolling up
          if (!isScrollingUp && currentScrollY > 100) {
            isScrollingUp = true;
            navbar.classList.add("sticky-nav-active");
          }
        } else if (scrollDifference > 0) {
          // Scrolling down
          if (isScrollingUp) {
            isScrollingUp = false;
            navbar.classList.remove("sticky-nav-active");
          }
        }

        // Reset to static if at top of page
        if (currentScrollY <= 0) {
          navbar.classList.remove("sticky-nav-active");
          isScrollingUp = false;
        }

        lastScrollY = currentScrollY;
      };

      // Throttle scroll events for better performance
      let ticking = false;
      const throttledHandleScroll = () => {
        if (!ticking) {
          window.requestAnimationFrame(() => {
            handleScroll();
            ticking = false;
          });
          ticking = true;
        }
      };

      window.addEventListener("scroll", throttledHandleScroll, {
        passive: true,
      });
    },
  };

  /**
   * Mobile menu toggle behavior with smooth animations.
   */
  Drupal.behaviors.mobileMenu = {
    attach: (context, settings) => {
      const elements = {
        button: context.querySelector("#mobile-menu-button"),
        menu: context.querySelector("#mobile-menu"),
        iconOpen: context.querySelector("#mobile-menu-icon-open"),
        iconClose: context.querySelector("#mobile-menu-icon-close"),
      };

      // Early return if any element is missing
      if (!Object.values(elements).every((el) => el)) {
        return;
      }

      // Prevent duplicate attachment
      if (elements.button.dataset.mobileMenuAttached) {
        return;
      }
      elements.button.dataset.mobileMenuAttached = "true";

      const menuClasses = {
        open: ["scale-x-100", "opacity-100", "max-h-96"],
        closed: ["scale-x-0", "opacity-0", "max-h-0"],
      };

      const toggleMenu = (isOpen) => {
        elements.menu.classList.remove(
          ...menuClasses[isOpen ? "closed" : "open"],
        );
        elements.menu.classList.add(...menuClasses[isOpen ? "open" : "closed"]);
        elements.button.setAttribute("aria-expanded", isOpen);
        elements.iconOpen.classList.toggle("hidden", isOpen);
        elements.iconClose.classList.toggle("hidden", !isOpen);
      };

      const isMenuOpen = () => elements.menu.classList.contains("scale-x-100");

      const isClickOutside = (e) => {
        return (
          !elements.button.contains(e.target) &&
          !elements.menu.contains(e.target)
        );
      };

      // Toggle menu on button click
      elements.button.addEventListener("click", () => {
        const isExpanded =
          elements.button.getAttribute("aria-expanded") === "true";
        toggleMenu(!isExpanded);
      });

      // Close menu when clicking outside
      document.addEventListener("click", (e) => {
        if (isMenuOpen() && isClickOutside(e)) {
          toggleMenu(false);
        }
      });

      // Close menu on Escape key
      document.addEventListener("keydown", (e) => {
        if (e.key === "Escape" && isMenuOpen()) {
          toggleMenu(false);
          elements.button.focus();
        }
      });

      // Close menu on custom event (e.g., from scroll handler)
      document.addEventListener("closeMobileMenu", () => {
        if (isMenuOpen()) {
          toggleMenu(false);
        }
      });
    },
  };

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

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

  /**
   * Unified accordion behavior with configurable options.
   */
  const initAccordion = (context, config) => {
    const {
      singleOpen = false,
      targetSelector = null,
      openedOnDesktop = false,
    } = config;

    // Unified classes - all accordions use the same class names
    const ATTACHED_ATTR = "data-accordion-attached";
    const OPEN_CLASS = "accordion-item-open";
    const ITEM_SELECTOR = ".accordion-item";
    const BUTTON_SELECTOR = ".accordion-button";
    const CONTENT_SELECTOR = ".accordion-content";
    const ICON_SELECTOR = ".accordion-icon";

    // Desktop breakpoint (768px = md in Tailwind)
    const isDesktop = () => window.innerWidth >= 768;

    // Limit search scope if targetSelector is provided
    const searchScope = targetSelector
      ? context.querySelector(targetSelector)
      : context;
    if (!searchScope) {
      return;
    }

    const items = searchScope.querySelectorAll(
      `${ITEM_SELECTOR}:not([${ATTACHED_ATTR}])`,
    );
    if (items.length === 0) {
      return;
    }

    const updateAccordion = (button, content, icon, isOpen) => {
      if (isOpen) {
        // Open - remove hidden, set initial state, then animate
        content.classList.remove("hidden");
        content.style.maxHeight = "0";
        content.style.opacity = "0";
        // Force reflow, then animate to full height
        requestAnimationFrame(() => {
          const height = content.scrollHeight;
          content.style.maxHeight = `${height}px`;
          content.style.opacity = "1";
        });
        if (icon) {
          icon.classList.remove("rotate-0");
          icon.classList.add("rotate-180");
        }
      } else {
        // Close - animate to collapsed state
        content.style.maxHeight = "0";
        content.style.opacity = "0";
        if (icon) {
          icon.classList.remove("rotate-180");
          icon.classList.add("rotate-0");
        }
        // Add hidden after animation completes
        setTimeout(() => {
          content.classList.add("hidden");
        }, 300);
      }
    };

    items.forEach((item) => {
      item.setAttribute(ATTACHED_ATTR, "true");

      const button = item.querySelector(BUTTON_SELECTOR);
      const content = item.querySelector(CONTENT_SELECTOR);
      const icon = item.querySelector(ICON_SELECTOR);

      if (!button || !content) {
        return;
      }

      // Handle toggle
      const handleToggle = () => {
        const isCurrentlyOpen = button.getAttribute("aria-expanded") === "true";
        const willBeOpen = !isCurrentlyOpen;

        // If single-open, close all other items in the search scope
        if (singleOpen && willBeOpen) {
          const allItems = searchScope.querySelectorAll(ITEM_SELECTOR);

          allItems.forEach((otherItem) => {
            if (otherItem !== item) {
              const otherButton = otherItem.querySelector(BUTTON_SELECTOR);
              const otherContent = otherItem.querySelector(CONTENT_SELECTOR);
              const otherIcon = otherItem.querySelector(ICON_SELECTOR);

              if (otherButton && otherContent) {
                otherButton.setAttribute("aria-expanded", "false");
                updateAccordion(otherButton, otherContent, otherIcon, false);
                otherItem.classList.remove(OPEN_CLASS);
              }
            }
          });
        }

        // Toggle this item
        button.setAttribute("aria-expanded", willBeOpen);
        updateAccordion(button, content, icon, willBeOpen);
        item.classList.toggle(OPEN_CLASS, willBeOpen);
      };

      button.addEventListener("click", handleToggle);

      // If openedOnDesktop is true, disable pointer events on desktop
      if (openedOnDesktop) {
        button.classList.add("md:pointer-events-none");
      }

      // Initialize based on openedOnDesktop and initial state
      if (openedOnDesktop && isDesktop()) {
        // On desktop with openedOnDesktop: initialize as open
        button.setAttribute("aria-expanded", "true");
        updateAccordion(button, content, icon, true);
        item.classList.add(OPEN_CLASS);
      } else {
        // Normal initialization
        const initialExpanded = button.getAttribute("aria-expanded") === "true";
        if (initialExpanded) {
          updateAccordion(button, content, icon, true);
        } else {
          button.setAttribute("aria-expanded", "false");
          // Set initial closed state
          content.style.maxHeight = "0";
          content.style.opacity = "0";
        }
      }
    });
  };

  /**
   * Services accordion behavior.
   */
  Drupal.behaviors.servicesAccordion = {
    attach: (context, settings) => {
      initAccordion(context, {
        targetSelector: ".services-accordions",
        singleOpen: false,
        openedOnDesktop: true,
      });
    },
  };
})(Drupal);

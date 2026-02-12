/**
 * @file
 * Navbar JavaScript behavior.
 */

((Drupal) => {
  "use strict";

  /**
   * Navbar sticky behavior on scroll up.
   */
  Drupal.behaviors.tailwindNavbar = {
    attach: (context) => {
      const navbar = context.querySelector("#main-navbar");
      if (!navbar) {
        return;
      }

      let lastScrollY = window.scrollY;
      let isScrollingUp = false;

      const handleScroll = () => {
        const currentScrollY = window.scrollY;
        const scrollDifference = currentScrollY - lastScrollY;

        // Close mobile menu on any scroll via custom event.
        document.dispatchEvent(new CustomEvent("closeMobileMenu"));

        // Determine scroll direction.
        if (scrollDifference < 0) {
          // Scrolling up.
          if (!isScrollingUp && currentScrollY > 100) {
            isScrollingUp = true;
            navbar.classList.add("sticky-nav-active");
          }
        } else if (scrollDifference > 0) {
          // Scrolling down.
          if (isScrollingUp) {
            isScrollingUp = false;
            navbar.classList.remove("sticky-nav-active");
          }
        }

        // Reset to static if at top of page.
        if (currentScrollY <= 0) {
          navbar.classList.remove("sticky-nav-active");
          isScrollingUp = false;
        }

        lastScrollY = currentScrollY;
      };

      // Throttle scroll events for better performance.
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
    attach: (context) => {
      const elements = {
        button: context.querySelector("#mobile-menu-button"),
        menu: context.querySelector("#mobile-menu"),
        iconOpen: context.querySelector("#mobile-menu-icon-open"),
        iconClose: context.querySelector("#mobile-menu-icon-close"),
      };

      // Early return if any element is missing.
      if (!Object.values(elements).every((el) => el)) {
        return;
      }

      // Prevent duplicate attachment.
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
        elements.button.setAttribute("aria-expanded", String(isOpen));
        elements.iconOpen.classList.toggle("hidden", isOpen);
        elements.iconClose.classList.toggle("hidden", !isOpen);
      };

      const isMenuOpen = () => elements.menu.classList.contains("scale-x-100");

      const isClickOutside = (event) => {
        return (
          !elements.button.contains(event.target) &&
          !elements.menu.contains(event.target)
        );
      };

      // Toggle menu on button click.
      elements.button.addEventListener("click", () => {
        const isExpanded =
          elements.button.getAttribute("aria-expanded") === "true";
        toggleMenu(!isExpanded);
      });

      // Close menu when clicking outside.
      document.addEventListener("click", (event) => {
        if (isMenuOpen() && isClickOutside(event)) {
          toggleMenu(false);
        }
      });

      // Close menu on Escape key.
      document.addEventListener("keydown", (event) => {
        if (event.key === "Escape" && isMenuOpen()) {
          toggleMenu(false);
          elements.button.focus();
        }
      });

      // Close menu on custom event (e.g., from scroll handler).
      document.addEventListener("closeMobileMenu", () => {
        if (isMenuOpen()) {
          toggleMenu(false);
        }
      });
    },
  };
})(Drupal);

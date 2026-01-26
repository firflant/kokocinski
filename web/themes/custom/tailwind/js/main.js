/**
 * @file
 * Main JavaScript file for the Tailwind theme.
 */

((Drupal) => {
  "use strict";

  /**
   * Navigation sticky behavior on scroll up.
   */
  Drupal.behaviors.tailwindNavigation = {
    attach: (context, settings) => {
      const navigation = context.querySelector("#main-navigation");
      if (!navigation) {
        return;
      }

      let lastScrollY = window.scrollY;
      let isScrollingUp = false;

      const handleScroll = () => {
        const currentScrollY = window.scrollY;
        const scrollDifference = currentScrollY - lastScrollY;

        // Determine scroll direction
        if (scrollDifference < 0) {
          // Scrolling up
          if (!isScrollingUp && currentScrollY > 100) {
            isScrollingUp = true;
            navigation.classList.add("sticky-nav-active");
          }
        } else if (scrollDifference > 0) {
          // Scrolling down
          if (isScrollingUp) {
            isScrollingUp = false;
            navigation.classList.remove("sticky-nav-active");
          }
        }

        // Reset to static if at top of page
        if (currentScrollY <= 0) {
          navigation.classList.remove("sticky-nav-active");
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
})(Drupal);

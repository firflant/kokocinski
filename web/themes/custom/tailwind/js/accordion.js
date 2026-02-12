/**
 * @file
 * Shared accordion utilities for theme components.
 */

((Drupal) => {
  "use strict";

  Drupal.tailwindAccordion = Drupal.tailwindAccordion || {};

  /**
   * Unified accordion behavior with configurable options.
   */
  Drupal.tailwindAccordion.initAccordion = (context, config = {}) => {
    const {
      singleOpen = false,
      targetSelector = null,
      openedOnDesktop = false,
    } = config;

    const ATTACHED_ATTR = "data-accordion-attached";
    const OPEN_CLASS = "accordion-item-open";
    const ITEM_SELECTOR = ".accordion-item";
    const BUTTON_SELECTOR = ".accordion-button";
    const CONTENT_SELECTOR = ".accordion-content";
    const ICON_SELECTOR = ".accordion-icon";

    const isDesktop = () => window.innerWidth >= 768;

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
        content.classList.remove("hidden");
        content.style.maxHeight = "0";
        content.style.opacity = "0";

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
        content.style.maxHeight = "0";
        content.style.opacity = "0";

        if (icon) {
          icon.classList.remove("rotate-180");
          icon.classList.add("rotate-0");
        }

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

      const handleToggle = () => {
        const isCurrentlyOpen = button.getAttribute("aria-expanded") === "true";
        const willBeOpen = !isCurrentlyOpen;

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

        button.setAttribute("aria-expanded", String(willBeOpen));
        updateAccordion(button, content, icon, willBeOpen);
        item.classList.toggle(OPEN_CLASS, willBeOpen);
      };

      button.addEventListener("click", handleToggle);

      if (openedOnDesktop) {
        button.classList.add("md:pointer-events-none");
      }

      if (openedOnDesktop && isDesktop()) {
        button.setAttribute("aria-expanded", "true");
        updateAccordion(button, content, icon, true);
        item.classList.add(OPEN_CLASS);
      } else {
        const initialExpanded = button.getAttribute("aria-expanded") === "true";

        if (initialExpanded) {
          updateAccordion(button, content, icon, true);
        } else {
          button.setAttribute("aria-expanded", "false");
          content.style.maxHeight = "0";
          content.style.opacity = "0";
        }
      }
    });
  };
})(Drupal);

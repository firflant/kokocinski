/**
 * @file
 * About services component behavior.
 */

((Drupal) => {
  "use strict";

  /**
   * Services accordion behavior.
   */
  Drupal.behaviors.servicesAccordion = {
    attach: (context) => {
      if (!Drupal.tailwindAccordion?.initAccordion) {
        return;
      }

      Drupal.tailwindAccordion.initAccordion(context, {
        targetSelector: ".services-accordions",
        singleOpen: false,
        openedOnDesktop: true,
      });
    },
  };
})(Drupal);
